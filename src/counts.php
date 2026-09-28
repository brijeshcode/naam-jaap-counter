<?php
require_once __DIR__ . '/db.php';

function user_total(PDO $db, int $uid, int $mantra = 1): int {
    $s = $db->prepare('SELECT COALESCE(SUM(count),0) FROM daily_counts WHERE user_id=? AND mantra_id=?');
    $s->execute([$uid, $mantra]);
    return (int)$s->fetchColumn();
}
function today_count(PDO $db, int $uid, int $mantra = 1): int {
    $s = $db->prepare('SELECT count FROM daily_counts WHERE user_id=? AND mantra_id=? AND day=CURDATE()');
    $s->execute([$uid, $mantra]);
    $c = $s->fetchColumn();
    return $c === false ? 0 : (int)$c;
}
function push_days(PDO $db, int $uid, array $days, int $mantra = 1): int {
    $sql = 'INSERT INTO daily_counts (user_id,mantra_id,day,count) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE count = GREATEST(count, VALUES(count))';
    $stmt = $db->prepare($sql);
    foreach ($days as $day => $count) {
        $count = (int)$count;
        if ($count <= 0) continue;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$day)) continue;
        $stmt->execute([$uid, $mantra, $day, $count]);
    }
    return user_total($db, $uid, $mantra);
}
function reset_day(PDO $db, int $uid, string $day, int $mantra = 1): int {
    $db->prepare('DELETE FROM daily_counts WHERE user_id=? AND mantra_id=? AND day=?')
       ->execute([$uid, $mantra, $day]);
    return user_total($db, $uid, $mantra);
}
function pull_state(PDO $db, int $uid, int $recent = 60, int $mantra = 1): array {
    $s = $db->prepare('SELECT day, count FROM daily_counts
                       WHERE user_id=? AND mantra_id=? AND day >= (CURDATE() - INTERVAL ? DAY)
                       ORDER BY day');
    $s->execute([$uid, $mantra, $recent]);
    $days = [];
    foreach ($s as $r) $days[$r['day']] = (int)$r['count'];
    return ['total' => user_total($db,$uid,$mantra), 'today' => today_count($db,$uid,$mantra), 'days' => $days];
}
