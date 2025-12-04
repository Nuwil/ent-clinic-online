<?php
/**
 * Backfill script: add `full_name` column to `patients` table if missing
 * and populate it from `first_name` + ' ' + `last_name`.
 */
require_once __DIR__ . '/../config/Database.php';

echo "Backfill full_name script\n";
try {
    $db = Database::getInstance()->getConnection();

    // Check if column exists
    $stmt = $db->prepare("SHOW COLUMNS FROM patients LIKE 'full_name'");
    $stmt->execute();
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        echo "Column full_name already exists.\n";
    } else {
        echo "Adding column full_name...\n";
        $db->exec("ALTER TABLE patients ADD COLUMN full_name VARCHAR(255) NULL AFTER email");
        echo "Column added.\n";
    }

    // Populate full_name for existing rows
    echo "Populating full_name from first_name and last_name...\n";
    $update = $db->prepare("UPDATE patients SET full_name = TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) WHERE full_name IS NULL OR full_name = ''");
    $update->execute();
    $rows = $update->rowCount();
    echo "Updated $rows rows.\n";

    // Show sample rows
    $sample = $db->query("SELECT id, first_name, last_name, full_name FROM patients ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSample rows:\n";
    foreach ($sample as $r) {
        echo sprintf("%s | %s %s | %s\n", $r['id'], $r['first_name'], $r['last_name'], $r['full_name']);
    }

    echo "\nDone.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
