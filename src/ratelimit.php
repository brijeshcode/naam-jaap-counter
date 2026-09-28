<?php
require_once __DIR__ . '/db.php';

// Records an attempt for $key and returns true if within limit, false if over.
function rate_check(PDO $db, string $key, int $max = 20, int $windowSec = 60): bool {
    $db->prepare('INSERT INTO auth_attempts (rkey) VALUES (?)')->execute([$key]);
    $s = $db->prepare('SELECT COUNT(*) FROM auth_attempts
                       WHERE rkey = ? AND created_at >= (NOW() - INTERVAL ? SECOND)');
    $s->execute([$key, $windowSec]);
    return ((int)$s->fetchColumn()) <= $max;
}
