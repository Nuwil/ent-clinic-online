<?php
// Quick migration runner
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Add emergency contact fields
    $migrations = [
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(150) AFTER insurance_id",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20) AFTER emergency_contact_name"
    ];
    
    foreach ($migrations as $sql) {
        echo "Running: $sql\n";
        $db->exec($sql);
        echo "✓ Success\n";
    }
    
    echo "\n✓ All migrations applied successfully!\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
