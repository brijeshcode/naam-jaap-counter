<?php
require __DIR__ . '/../../vendor/autoload.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='dan'");
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('dan','x','dtok')");
$uid = (int)$db->query("SELECT id FROM users WHERE username='dan'")->fetchColumn();

$today = date('Y-m-d'); $d3 = date('Y-m-d', strtotime('-3 day'));
push_days($db,$uid,[$today=>10, $d3=>7]);

$daily = chart_data($db,$uid,'daily',0);
assert(count($daily['labels']) === 7 && count($daily['values']) === 7);
assert($daily['labels'][6] === $today);              // newest last
assert((int)$daily['values'][6] === 10);
assert((int)$daily['values'][3] === 7);              // 3 days ago
assert((int)$daily['values'][0] === 0);              // 6 days ago, no data

$monthly = chart_data($db,$uid,'monthly',0);
assert(count($monthly['labels']) === 12);
assert($monthly['labels'][11] === date('Y-m'));
assert((int)$monthly['values'][11] === 17);          // both entries this month

$yearly = chart_data($db,$uid,'yearly',0);
assert(count($yearly['labels']) === 10);
assert($yearly['labels'][9] === date('Y'));
assert((int)$yearly['values'][9] === 17);

// paging past history: all zeros, no error
$old = chart_data($db,$uid,'daily',500);
assert(count($old['values']) === 7 && array_sum($old['values']) === 0);
echo "test_charts OK\n";
