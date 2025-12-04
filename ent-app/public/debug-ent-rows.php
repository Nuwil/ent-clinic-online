<?php
require_once __DIR__ . '/../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$start = '2025-01-01';
$end = '2025-12-31';

$sql = "SELECT DATE(visit_date) AS d, ent_type, COUNT(*) AS c, DAYOFWEEK(visit_date) AS dow
        FROM patient_visits
        WHERE DATE(visit_date) BETWEEN ? AND ?
        GROUP BY DATE(visit_date), ent_type, DAYOFWEEK(visit_date)
        ORDER BY d ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(["$start 00:00:00", "$end 23:59:59"]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Rows: " . count($rows) . "\n";
$dist = ['ear'=>0,'nose'=>0,'throat'=>0];
foreach ($rows as $r) {
    $type = $r['ent_type'];
    $cnt = (int)$r['c'];
    echo "Date={$r['d']} | ent_type='" . var_export($type,true) . "' | count=$cnt | dow={$r['dow']}\n";
    if (isset($dist[$type])) $dist[$type] += $cnt;
}

echo "\nAggregated from rows:\n";
print_r($dist);

// Also compute daily totals
$daily = [];
foreach ($rows as $r) {
    $daily[$r['d']] = ($daily[$r['d']] ?? 0) + (int)$r['c'];
}

echo "\nSample daily_series (first 10):\n";
$cnt=0;
foreach ($daily as $d=>$c) {
    echo "$d => $c\n";
    if (++$cnt>=10) break;
}

?>