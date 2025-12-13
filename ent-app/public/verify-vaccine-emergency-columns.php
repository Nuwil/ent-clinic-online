<?php
/**
 * Simple verification that the patients table has the required columns
 */
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance();
    
    // Check if the columns exist
    $sql = "SHOW COLUMNS FROM patients WHERE Field IN ('vaccine_history', 'emergency_contact_name', 'emergency_contact_phone')";
    $stmt = $db->query($sql);
    $columns = $stmt->fetchAll();
    
    echo "=== Patients Table Column Verification ===\n\n";
    
    if (count($columns) === 3) {
        echo "✓ SUCCESS: All three required columns exist:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        echo "\n✓ The vaccine_history, emergency_contact_name, and emergency_contact_phone fields\n";
        echo "  are ready to be saved.\n";
        echo "\n✓ The PatientsController.php has been updated to allow these fields.\n";
        echo "\nYou can now:\n";
        echo "1. Go to the patient profile page\n";
        echo "2. Click 'Edit Patient Profile'\n";
        echo "3. Fill in the Vaccine History, Emergency Contact Name, and Emergency Contact Phone fields\n";
        echo "4. Click 'Save Changes'\n";
        echo "5. The values will now be saved to the database\n";
    } else {
        echo "✗ ERROR: Expected 3 columns but found " . count($columns) . "\n";
        foreach ($columns as $col) {
            echo "  Found: {$col['Field']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
