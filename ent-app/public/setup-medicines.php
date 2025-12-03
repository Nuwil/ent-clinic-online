<?php
/**
 * Quick setup script to insert medicines
 * Access via: http://localhost/ent-app/public/setup-medicines.php
 */

require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance();

$medicines = [
    ['Amoxicillin', '500', 'mg'],
    ['Ibuprofen', '200', 'mg'],
    ['Paracetamol', '500', 'mg'],
    ['Cetirizine', '10', 'mg'],
    ['Omeprazole', '20', 'mg'],
    ['Metronidazole', '400', 'mg'],
    ['Cephalexin', '500', 'mg'],
    ['Aspirin', '81', 'mg'],
    ['Loratadine', '10', 'mg'],
    ['Dexamethasone', '0.5', 'mg'],
    ['Ambroxol', '30', 'mg'],
    ['Diphenhydramine', '25', 'mg'],
    ['Fluconazole', '150', 'mg'],
    ['Hydrocodone', '5', 'mg'],
    ['Itraconazole', '100', 'mg'],
    ['Ketoconazole', '200', 'mg'],
    ['Levofloxacin', '500', 'mg'],
    ['Mometasone', '50', 'mcg'],
    ['Nifedipine', '30', 'mg'],
    ['Oxymetazoline', '0.05', '%']
];

try {
    $inserted = 0;
    $sql = "INSERT IGNORE INTO medicines (name, dosage, unit, is_active) VALUES (?, ?, ?, TRUE)";
    
    foreach ($medicines as $med) {
        try {
            $db->query($sql, [$med[0], $med[1], $med[2]]);
            $inserted++;
        } catch (Exception $e) {
            // Continue on individual insert errors
        }
    }
    
    echo "<div style='padding: 20px; font-family: Arial; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px; margin: 20px;'>";
    echo "<h2 style='color: #2e7d32; margin-top: 0;'>✅ Medicines Setup Complete!</h2>";
    echo "<p style='color: #1b5e20; margin: 0;'><strong>Inserted/Updated:</strong> $inserted medicines</p>";
    echo "<p style='color: #1b5e20; margin: 10px 0 0 0;'>You can now see the medicines in the prescription panel!</p>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='padding: 20px; font-family: Arial; background: #ffebee; border: 1px solid #f44336; border-radius: 4px; margin: 20px;'>";
    echo "<h2 style='color: #c62828; margin-top: 0;'>❌ Error</h2>";
    echo "<p style='color: #b71c1c;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
