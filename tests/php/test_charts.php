<?php
require __DIR__ . '/../../vendor/autoload.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='dan'");
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('dan','x','dtok')");
$uid = (int)$db->query("SELECT id FROM users WHERE username='dan'")->fetchColumn();

$today = date('Y-m-d');
push_days($db,$uid,[$today=>17]);

// weekly: 7 day-of-week bars, today's count lands on its weekday slot.
$weekly = chart_data($db,$uid,'weekly',0);
assert(count($weekly['labels']) === 7 && count($weekly['values']) === 7);
$dow = (int)date('N') - 1;                            // Mon=0 .. Sun=6
assert($weekly['labels'][$dow] === date('D'));
assert((int)$weekly['values'][$dow] === 17);
assert(array_sum($weekly['values']) === 17);

// monthly: one bar per day of the current month, labelled by day number.
$monthly = chart_data($db,$uid,'monthly',0);
assert(count($monthly['labels']) === (int)date('t'));
$dayIdx = (int)date('j') - 1;
assert($monthly['labels'][$dayIdx] === (string)(int)date('j'));
assert((int)$monthly['values'][$dayIdx] === 17);

// yearly: 12 month bars, labelled by month name.
$yearly = chart_data($db,$uid,'yearly',0);
assert(count($yearly['labels']) === 12);
$monIdx = (int)date('n') - 1;
assert($yearly['labels'][$monIdx] === date('M'));
assert((int)$yearly['values'][$monIdx] === 17);

// paging past history: all zeros, no error
$old = chart_data($db,$uid,'weekly',500);
assert(count($old['values']) === 7 && array_sum($old['values']) === 0);
echo "test_charts OK\n";
