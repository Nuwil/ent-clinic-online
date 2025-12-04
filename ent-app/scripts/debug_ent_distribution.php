<?php
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "ENT type distribution in patient_visits:\n";
    $stmt = $db->query("SELECT ent_type, COUNT(*) as c FROM patient_visits GROUP BY ent_type ORDER BY c DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "(no rows)\n";
    } else {
        foreach ($rows as $r) {
            $type = $r['ent_type'] ?? '(NULL)';
            echo sprintf("%20s : %s\n", $type, $r['c']);
        }
    }

    echo "\nSample rows (latest 10):\n";
    $s = $db->query("SELECT id, patient_id, visit_date, ent_type, chief_complaint FROM patient_visits ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($s as $r) {
        echo sprintf("%s | %s | %s | %s | %s\n", $r['id'], $r['patient_id'], $r['visit_date'], $r['ent_type'], substr($r['chief_complaint'] ?? '', 0, 50));
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
