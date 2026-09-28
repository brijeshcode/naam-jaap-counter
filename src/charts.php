<?php
require_once __DIR__ . '/db.php';

function chart_data(PDO $db, int $uid, string $type, int $cursor, int $mantra = 1): array {
    $cursor = max(0, $cursor);
    if ($type === 'monthly') return chart_periods($db,$uid,$mantra,'%Y-%m',12,$cursor);
    if ($type === 'yearly')  return chart_periods($db,$uid,$mantra,'%Y',10,$cursor);
    return chart_periods($db,$uid,$mantra,'%Y-%m-%d',7,$cursor);
}

function chart_periods(PDO $db,int $uid,int $mantra,string $fmt,int $n,int $cursor): array {
    // Build the N period labels for this window, oldest -> newest.
    $labels = [];
    $offset = $cursor * $n;                    // how many periods back the window's newest sits
    for ($i = $n - 1; $i >= 0; $i--) {
        $back = $offset + $i;                  // periods before today for this slot
        $labels[] = period_label($fmt, $back);
    }
    // Sum counts grouped by the same format, then map onto labels (0 if absent).
    $sql = "SELECT DATE_FORMAT(day, ?) AS p, COALESCE(SUM(count),0) AS c
            FROM daily_counts WHERE user_id=? AND mantra_id=? GROUP BY p";
    $s = $db->prepare($sql); $s->execute([$fmt,$uid,$mantra]);
    $byPeriod = [];
    foreach ($s as $r) $byPeriod[$r['p']] = (int)$r['c'];
    $values = array_map(fn($l) => $byPeriod[$l] ?? 0, $labels);
    return ['labels'=>$labels, 'values'=>$values];
}

function period_label(string $fmt,int $back): string {
    if ($fmt === '%Y-%m-%d') return date('Y-m-d', strtotime("-$back day"));
    if ($fmt === '%Y-%m')    return date('Y-m',  strtotime("first day of -$back month"));
    return date('Y', strtotime("-$back year"));
}
