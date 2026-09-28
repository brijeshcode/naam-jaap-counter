<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/counts.php';

function register_user(PDO $db, string $u, string $p): array {
    $u = trim($u);
    if (strlen($u) < 1 || strlen($u) > 50 || strlen($p) < 4) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $exists = $db->prepare('SELECT 1 FROM users WHERE username = ?');
    $exists->execute([$u]);
    if ($exists->fetchColumn()) return ['ok' => false, 'error' => 'username_taken'];
    $token = new_token();
    $ins = $db->prepare('INSERT INTO users (username,password_hash,session_token) VALUES (?,?,?)');
    $ins->execute([$u, password_hash($p, PASSWORD_DEFAULT), $token]);
    return ['ok' => true, 'token' => $token, 'total' => 0, 'today' => 0];
}

function login_user(PDO $db, string $u, string $p, bool $force): array {
    $s = $db->prepare('SELECT id,password_hash,session_token FROM users WHERE username = ?');
    $s->execute([trim($u)]);
    $row = $s->fetch();
    if (!$row || !password_verify($p, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'bad_credentials'];
    }
    if (!empty($row['session_token']) && !$force) {
        return ['ok' => false, 'device_active' => true];
    }
    $token = new_token();
    $db->prepare('UPDATE users SET session_token = ? WHERE id = ?')
       ->execute([$token, $row['id']]);
    $uid = (int)$row['id'];
    return ['ok' => true, 'token' => $token,
            'total' => user_total($db, $uid), 'today' => today_count($db, $uid)];
}

function logout_user(PDO $db, string $token): void {
    $db->prepare('UPDATE users SET session_token = NULL WHERE session_token = ?')
       ->execute([$token]);
}
