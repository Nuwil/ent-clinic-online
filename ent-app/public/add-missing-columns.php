<?php
// Add missing patient columns
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $migrations = [
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS height DECIMAL(5,2) COMMENT 'Height in cm'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS weight DECIMAL(5,2) COMMENT 'Weight in kg'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS bmi DECIMAL(5,2) COMMENT 'Body Mass Index (kg/m2)'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS blood_pressure VARCHAR(20) COMMENT 'e.g., 120/80'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS temperature DECIMAL(4,1) COMMENT 'Temperature in Celsius'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS vitals_updated_at TIMESTAMP NULL COMMENT 'Last update of vitals'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS vaccine_history TEXT"
    ];
    
    echo "=== Adding Missing Patient Columns ===\n\n";
    
    foreach ($migrations as $sql) {
        echo "Running: $sql\n";
        try {
            $db->exec($sql);
            echo "✓ Success\n\n";
        } catch (Exception $e) {
            echo "⚠ " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "\n=== Verifying Columns ===\n";
    $colStmt = $db->query("SHOW COLUMNS FROM patients");
    $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required = ['height', 'weight', 'bmi', 'blood_pressure', 'temperature', 'vaccine_history', 'emergency_contact_name', 'emergency_contact_phone'];
    $missing = [];
    
    $found = array_map(fn($c) => $c['Field'], $cols);
    
    foreach ($required as $col) {
        if (in_array($col, $found)) {
            echo "✓ $col exists\n";
        } else {
            echo "✗ $col MISSING\n";
            $missing[] = $col;
        }
    }
    
    if (empty($missing)) {
        echo "\n✓ All required columns exist!\n";
    } else {
        echo "\n✗ Missing columns: " . implode(', ', $missing) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
