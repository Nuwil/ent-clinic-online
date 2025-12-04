<?php
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance();

$start = '2025-01-01';
$end = '2025-12-31';

echo "Testing ENT Distribution Query\n";
echo "Date Range: $start to $end\n\n";

// Test 1: Raw query to see all results
$stmt = $db->prepare('SELECT DATE(visit_date) AS d, ent_type, COUNT(*) AS c FROM patient_visits WHERE DATE(visit_date) BETWEEN ? AND ? GROUP BY DATE(visit_date), ent_type');
$stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
$rows = $stmt->fetchAll();

echo "Query Results by Date and ENT Type:\n";
$distribution = ['ear' => 0, 'nose' => 0, 'throat' => 0];
foreach ($rows as $r) {
    $type = $r['ent_type'] ?? 'ear';
    $count = (int)$r['c'];
    echo "  Date: {$r['d']} | Type: {$type} | Count: {$count}\n";
    
    if (isset($distribution[$type])) {
        $distribution[$type] += $count;
    }
}

echo "\nAggregated Distribution:\n";
foreach ($distribution as $type => $count) {
    echo "  {$type}: {$count}\n";
}

echo "\nTotal Visits in Range: " . array_sum($distribution) . "\n";
?>
