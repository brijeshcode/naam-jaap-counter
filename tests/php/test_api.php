<?php
require __DIR__ . '/../../vendor/autoload.php';
db()->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
db()->exec("DELETE FROM users WHERE username IN ('carol')");
db()->exec("DELETE FROM auth_attempts");   // avoid rate-limit carryover across runs

function call(string $action, array $body, string $qs = ''): array {
    $_GET['action'] = $action;
    parse_str($qs, $extra);
    foreach ($extra as $k => $v) $_GET[$k] = $v;
    $GLOBALS['__test_input'] = json_encode($body);
    ob_start(); include __DIR__ . '/../../api.php'; $out = ob_get_clean();
    return json_decode($out, true);
}

$reg = call('register', ['username'=>'carol','password'=>'pw123456']);
assert($reg['ok'] === true);
$tok = $reg['token'];

// push then pull round-trips
$today = date('Y-m-d');
$p = call('push', ['token'=>$tok, 'days'=>[$today=>108]]);
assert($p['ok'] === true && $p['total'] === 108);
$pl = call('pull', ['token'=>$tok]);
assert($pl['total'] === 108 && $pl['today'] === 108);

// forced login elsewhere displaces this token
$new = call('login', ['username'=>'carol','password'=>'pw123456','force'=>1]);
assert($new['ok'] === true && $new['token'] !== $tok);

// old (displaced) token is now rejected with 409 and must NOT overwrite data
$rej = call('push', ['token'=>$tok, 'days'=>[$today=>999]]);
assert(($rej['error'] ?? '') === 'stale_token');
$pl2 = call('pull', ['token'=>$new['token']]);
assert($pl2['today'] === 108);   // 999 from displaced device was rejected
echo "test_api OK\n";
