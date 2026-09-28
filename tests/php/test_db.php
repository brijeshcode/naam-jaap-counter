<?php
require __DIR__ . '/../../vendor/autoload.php';
$db = db();                                   // connects to $NJ_DB_NAME
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
assert(strlen(new_token()) === 64);
assert(user_id_for_token($db, 'nope') === null);
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('t','x','abc')");
$id = user_id_for_token($db, 'abc');
assert(is_int($id) && $id > 0);
$db->exec("DELETE FROM users WHERE username='t'");
echo "test_db OK\n";
