<?php
require_once __DIR__ . '/db.php';

function chart_data(PDO $db, int $uid, string $type, int $cursor, int $mantra = 1): array {
    $cursor = max(0, $cursor);
    if ($type === 'monthly') return chart_month($db, $uid, $mantra, $cursor);
    if ($type === 'yearly')  return chart_year($db, $uid, $mantra, $cursor);
    return chart_week($db, $uid, $mantra, $cursor); // weekly (default)
}

// Current week's 7 days (Mon..Sun), cursor pages whole weeks back.
function chart_week(PDO $db, int $uid, int $mantra, int $cursor): array {
    $monday = strtotime("monday this week -{$cursor} week");
    $keys = $labels = [];
    for ($i = 0; $i < 7; $i++) {
        $t = strtotime("+$i day", $monday);
        $keys[]   = date('Y-m-d', $t);
        $labels[] = date('D', $t);          // Mon, Tue, ...
    }
    $range = date('j M', $monday) . ' – ' . date('j M Y', strtotime('+6 day', $monday));
    return chart_map($db, $uid, $mantra, '%Y-%m-%d', $keys, $labels, $range);
}

// Every day of the (cursor-th previous) month, labelled by day number.
function chart_month(PDO $db, int $uid, int $mantra, int $cursor): array {
    $first = strtotime("first day of -{$cursor} month");
    $days = (int)date('t', $first);
    $keys = $labels = [];
    for ($d = 1; $d <= $days; $d++) {
        $keys[]   = date('Y-m-', $first) . sprintf('%02d', $d);
        $labels[] = (string)$d;
    }
    return chart_map($db, $uid, $mantra, '%Y-%m-%d', $keys, $labels, date('M Y', $first));
}

// Every month of the (cursor-th previous) year, labelled by month name.
function chart_year(PDO $db, int $uid, int $mantra, int $cursor): array {
    $year = (int)date('Y') - $cursor;
    $keys = $labels = [];
    for ($m = 1; $m <= 12; $m++) {
        $keys[]   = sprintf('%04d-%02d', $year, $m);
        $labels[] = date('M', mktime(0, 0, 0, $m, 1, $year)); // Jan..Dec
    }
    return chart_map($db, $uid, $mantra, '%Y-%m', $keys, $labels, (string)$year);
}

// Sum counts grouped by $fmt, then map onto $keys; $labels are what the UI shows.
function chart_map(PDO $db, int $uid, int $mantra, string $fmt, array $keys, array $labels, string $range): array {
    $sql = "SELECT DATE_FORMAT(day, ?) AS p, COALESCE(SUM(count),0) AS c
            FROM daily_counts WHERE user_id=? AND mantra_id=? GROUP BY p";
    $s = $db->prepare($sql); $s->execute([$fmt, $uid, $mantra]);
    $by = [];
    foreach ($s as $r) $by[$r['p']] = (int)$r['c'];
    $values = array_map(fn($k) => $by[$k] ?? 0, $keys);
    return ['labels' => $labels, 'values' => $values, 'range' => $range];
}
