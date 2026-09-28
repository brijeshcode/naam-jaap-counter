<?php
// Parse .env-style content into an associative array. Skips blanks/comments and
// lines without '=', trims keys/values, strips surrounding single or double quotes.
function parse_env(string $content): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if ((strlen($v) >= 2) && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if ($k !== '') $out[$k] = $v;
    }
    return $out;
}

// Load project-root .env (if present) into the environment. Real env vars win.
function nj_load_env(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $path = __DIR__ . '/../.env';
    if (!is_file($path)) return;
    foreach (parse_env(file_get_contents($path)) as $k => $v) {
        if (getenv($k) === false) putenv("$k=$v");
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    nj_load_env();
    $host = getenv('NJ_DB_HOST') ?: '127.0.0.1';
    $name = getenv('NJ_DB_NAME') ?: 'naam_jaap';
    $user = getenv('NJ_DB_USER') ?: 'root';
    $pass = getenv('NJ_DB_PASS') ?: '';
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function new_token(): string { return bin2hex(random_bytes(32)); }
function user_id_for_token(PDO $db, string $token): ?int {
    if ($token === '') return null;
    $s = $db->prepare('SELECT id FROM users WHERE session_token = ?');
    $s->execute([$token]);
    $id = $s->fetchColumn();
    return $id === false ? null : (int)$id;
}
