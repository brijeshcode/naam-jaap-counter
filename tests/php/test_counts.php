<?php
require __DIR__ . '/../../vendor/autoload.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='bob'");
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('bob','x','tok')");
$uid = (int)$db->query("SELECT id FROM users WHERE username='bob'")->fetchColumn();

assert(user_total($db,$uid) === 0 && today_count($db,$uid) === 0);

$today = date('Y-m-d'); $yest = date('Y-m-d', strtotime('-1 day'));
$total = push_days($db,$uid,[$today=>108, $yest=>50]);
assert($total === 158);
assert(today_count($db,$uid) === 108);

// idempotent replay: same values do not double-count
assert(push_days($db,$uid,[$today=>108]) === 158);

// monotonic: a lower value is ignored (GREATEST)
assert(push_days($db,$uid,[$today=>5]) === 158);
assert(today_count($db,$uid) === 108);

// higher value wins
assert(push_days($db,$uid,[$today=>216]) === 266);

// non-positive skipped, no empty-day row
push_days($db,$uid,['2000-01-01'=>0]);
$cnt = $db->query("SELECT COUNT(*) FROM daily_counts WHERE day='2000-01-01'")->fetchColumn();
assert((int)$cnt === 0);

$state = pull_state($db,$uid);
assert($state['total'] === 266 && $state['today'] === 216);
assert($state['days'][$today] === 216 && $state['days'][$yest] === 50);

// reset_day clears only that day (today), leaving other days intact
$t2 = reset_day($db,$uid,$today);
assert(today_count($db,$uid) === 0);
assert($t2 === 50);                                   // only yesterday's 50 remains
$cnt = $db->query("SELECT COUNT(*) FROM daily_counts WHERE user_id=$uid AND day='$today'")->fetchColumn();
assert((int)$cnt === 0);                              // today's row deleted
echo "test_counts OK\n";
