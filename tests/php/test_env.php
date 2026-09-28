<?php
require __DIR__ . '/../../vendor/autoload.php';

$parsed = parse_env("# comment line\nNJ_DB_HOST=localhost\nNJ_DB_NAME = mydb \nNJ_DB_PASS=\"se cret\"\n\nBAD LINE NO EQUALS\nNJ_DB_USER='root'\n");
assert($parsed['NJ_DB_HOST'] === 'localhost');
assert($parsed['NJ_DB_NAME'] === 'mydb');            // trimmed around key and value
assert($parsed['NJ_DB_PASS'] === 'se cret');         // surrounding double quotes stripped
assert($parsed['NJ_DB_USER'] === 'root');            // single quotes stripped
assert(!array_key_exists('BAD LINE NO EQUALS', $parsed));  // lines without = skipped
assert(!isset($parsed['# comment line']));           // comments skipped
echo "test_env OK\n";
