<?php
require_once __DIR__ . '/vendor/autoload.php';

if (!headers_sent()) header('Content-Type: application/json');

$db = db();
$action = $_GET['action'] ?? '';
$b = req_body();

switch ($action) {
    case 'register':
    case 'login':
        // Throttle auth attempts per client IP to blunt brute-force / signup abuse.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (!rate_check($db, $ip)) { http_response_code(429); out(['error' => 'rate_limited']); break; }
        if ($action === 'register') {
            out(register_user($db, (string)($b['username'] ?? ''), (string)($b['password'] ?? '')));
        } else {
            out(login_user($db, (string)($b['username'] ?? ''), (string)($b['password'] ?? ''), !empty($b['force'])));
        }
        break;
    case 'logout':
        logout_user($db, (string)($b['token'] ?? ''));
        out(['ok' => true]);
        break;
    case 'push':
        $uid = require_user($db, $b);
        if ($uid === null) break;
        $total = push_days($db, $uid, is_array($b['days'] ?? null) ? $b['days'] : []);
        out(['ok' => true, 'total' => $total, 'today' => today_count($db, $uid)]);
        break;
    case 'reset_day':
        $uid = require_user($db, $b);
        if ($uid === null) break;
        $total = reset_day($db, $uid, (string)($b['day'] ?? date('Y-m-d')));
        out(['ok' => true, 'total' => $total, 'today' => today_count($db, $uid)]);
        break;
    case 'pull':
        $uid = require_user($db, $b);
        if ($uid === null) break;
        out(pull_state($db, $uid));
        break;
    case 'chart':
        $uid = require_user($db, $b);
        if ($uid === null) break;
        out(chart_data($db, $uid, (string)($_GET['type'] ?? 'daily'), (int)($_GET['cursor'] ?? 0)));
        break;
    default:
        http_response_code(400);
        out(['error' => 'unknown_action']);
}
