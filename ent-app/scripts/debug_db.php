<?php
// Simple DB diagnostic script for local debugging
require_once __DIR__ . '/../config/Database.php';

echo "Debug DB Script\n";
try {
    $db = Database::getInstance()->getConnection();
    echo "Connected to DB successfully.\n";

    $tables = [
        'patients',
        'patient_visits',
        'prescription_items',
        'users'
    ];

    foreach ($tables as $t) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as c FROM `$t`");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $row['c'] ?? 'N/A';
            echo sprintf("Table %-20s : %s\n", $t, $count);
        } catch (Exception $e) {
            echo sprintf("Table %-20s : ERROR (%s)\n", $t, $e->getMessage());
        }
    }

    // Show latest 5 patients for quick check
    try {
        $stmt = $db->query("SELECT id, full_name, phone, created_at FROM patients ORDER BY id DESC LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nLatest patients:\n";
        if (count($rows) === 0) {
            echo "  (none)\n";
        } else {
            foreach ($rows as $r) {
                echo sprintf("  %s | %s | %s | %s\n", $r['id'], $r['full_name'], $r['phone'], $r['created_at']);
            }
        }
    } catch (Exception $e) {
        echo "Could not fetch latest patients: " . $e->getMessage() . "\n";
    }

    // Show patients table structure to diagnose missing columns
    try {
        echo "\nPatients table columns:\n";
        $cols = $db->query("SHOW COLUMNS FROM patients")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo sprintf("  %-20s %s %s\n", $c['Field'], $c['Type'], $c['Null']);
        }
    } catch (Exception $e) {
        echo "Could not describe patients table: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";
