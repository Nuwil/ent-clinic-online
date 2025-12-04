<?php
/**
 * Debug helper: check prescription_items table and counts for given visit_id / patient_id
 * Usage: php scripts\debug_prescription_items.php --visit=25 --patient=9
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$options = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $k = $parts[0];
        $v = $parts[1] ?? true;
        $options[$k] = $v;
    }
}

$visitId = isset($options['visit']) ? intval($options['visit']) : null;
$patientId = isset($options['patient']) ? intval($options['patient']) : null;

try {
    $db = Database::getInstance()->getConnection();

    $schema = DB_CONFIG['name'] ?? null;
    echo "Database: " . ($schema ?: 'unknown') . PHP_EOL;

    // Check tables
    $tablesToCheck = ['prescription_items', 'patient_visits', 'patients'];
    foreach ($tablesToCheck as $t) {
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
        $stmt->execute([$schema, $t]);
        $res = $stmt->fetch();
        $exists = ($res && $res['cnt'] > 0) ? 'YES' : 'NO';
        echo "Table {$t}: {$exists}" . PHP_EOL;
    }

    if ($visitId) {
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM prescription_items WHERE visit_id = ?');
        $stmt->execute([$visitId]);
        $r = $stmt->fetch();
        echo "Prescription items for visit_id={$visitId}: " . ($r['cnt'] ?? 0) . PHP_EOL;
    }

    if ($patientId) {
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM prescription_items WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        $r = $stmt->fetch();
        echo "Prescription items for patient_id={$patientId}: " . ($r['cnt'] ?? 0) . PHP_EOL;
    }

    // Show last 5 prescription_items rows for visit if available
    if ($visitId) {
        $stmt = $db->prepare('SELECT id, visit_id, patient_id, medicine_id, medicine_name, instruction, doctor_id, created_at FROM prescription_items WHERE visit_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$visitId]);
        $rows = $stmt->fetchAll();
        echo "Last prescription items for visit_id={$visitId}:\n";
        foreach ($rows as $row) {
            echo json_encode($row) . PHP_EOL;
        }
    }

    echo "Done." . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "Check ent-app/logs/api_errors.log for more details." . PHP_EOL;
    exit(1);
}
