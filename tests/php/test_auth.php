<?php
require __DIR__ . '/../../vendor/autoload.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='alice'");

$r = register_user($db, 'alice', 'pw123456');
assert($r['ok'] === true && strlen($r['token']) === 64);
assert($r['total'] === 0 && $r['today'] === 0);

// duplicate username
$r2 = register_user($db, 'alice', 'other');
assert($r2['ok'] === false && $r2['error'] === 'username_taken');

// bad login
$bad = login_user($db, 'alice', 'wrong', false);
assert($bad['ok'] === false && $bad['error'] === 'bad_credentials');

// login while a device is active -> device_active, no force
$active = login_user($db, 'alice', 'pw123456', false);
assert($active['ok'] === false && ($active['device_active'] ?? false) === true);

// forced login rotates token, invalidating the old one
$old = $r['token'];
$forced = login_user($db, 'alice', 'pw123456', true);
assert($forced['ok'] === true && $forced['token'] !== $old);
assert(user_id_for_token($db, $old) === null);            // old device displaced
assert(user_id_for_token($db, $forced['token']) !== null);

logout_user($db, $forced['token']);
assert(user_id_for_token($db, $forced['token']) === null);
echo "test_auth OK\n";
