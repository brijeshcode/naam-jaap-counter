<?php
// HTTP helpers, autoloaded once so api.php can be re-included in tests without redeclare.

function req_body(): array {
    $raw = $GLOBALS['__test_input'] ?? file_get_contents('php://input');
    $j = json_decode($raw ?: '{}', true);
    return is_array($j) ? $j : [];
}

function out(array $data): void { echo json_encode($data); }

// Returns the user id for the request token, or null after emitting a 409 body.
function require_user(PDO $db, array $b): ?int {
    $uid = user_id_for_token($db, (string)($b['token'] ?? ''));
    if ($uid === null) { http_response_code(409); out(['error' => 'stale_token']); return null; }
    return $uid;
}
