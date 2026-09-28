<?php
require __DIR__ . '/../../vendor/autoload.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM auth_attempts WHERE rkey='9.9.9.9'");

// max 5 attempts per window: first 5 allowed, 6th blocked
for ($i = 1; $i <= 5; $i++) {
    assert(rate_check($db, '9.9.9.9', 5, 60) === true, "attempt $i should be allowed");
}
assert(rate_check($db, '9.9.9.9', 5, 60) === false, '6th attempt blocked');

// a different key is unaffected
assert(rate_check($db, '8.8.8.8', 5, 60) === true, 'other key allowed');
echo "test_ratelimit OK\n";
