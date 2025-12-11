<?php
// Debug script to test patient update
require_once __DIR__ . '/../config/Database.php';

$patientId = isset($_GET['id']) ? $_GET['id'] : 7; // Test with patient ID 7

$db = Database::getInstance()->getConnection();

// Get current patient data
$stmt = $db->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
$stmt->execute([$patientId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo "Patient not found\n";
    exit;
}

echo "Patient ID: " . $patient['id'] . "\n";
echo "Patient Name: " . $patient['first_name'] . " " . $patient['last_name'] . "\n";
echo "\n=== Current Database Columns ===\n";

// Show all columns in patients table
$colStmt = $db->query("SHOW COLUMNS FROM patients");
$cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total columns: " . count($cols) . "\n\n";

foreach ($cols as $c) {
    $fieldName = $c['Field'];
    $fieldType = $c['Type'];
    $currentValue = $patient[$fieldName] ?? 'NULL';
    echo sprintf("%-35s | %s | Value: %s\n", $fieldName, $fieldType, substr($currentValue, 0, 30));
}

echo "\n=== Test Update Data ===\n";

// Simulate update data
$updateData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test@example.com',
    'phone' => '1234567890',
    'gender' => 'male',
    'date_of_birth' => '2000-01-01',
    'medical_history' => 'Test history',
    'allergies' => 'Test allergies',
    'vaccine_history' => 'Test vaccines',
    'insurance_provider' => 'Test insurance',
    'insurance_id' => 'TEST123',
    'emergency_contact_name' => 'Test Emergency',
    'emergency_contact_phone' => '0987654321',
    'height' => 170,
    'weight' => 70,
    'bmi' => 24.22,
    'address' => 'Test address',
    'city' => 'Test city',
    'state' => 'Test state',
    'postal_code' => '12345',
    'country' => 'Test country',
    'occupation' => 'Test occupation'
];

// Filter by actual columns
$allowedFields = [];
$existingCols = [];
foreach ($cols as $c) {
    if (isset($c['Field'])) $existingCols[] = $c['Field'];
}
$updateData = array_intersect_key($updateData, array_flip($existingCols));

echo "Filtered fields to update: " . count($updateData) . "\n";
foreach ($updateData as $field => $value) {
    echo "  - $field: $value\n";
}

// Try the update
echo "\n=== Attempting Update ===\n";
if (empty($updateData)) {
    echo "ERROR: No data to update!\n";
} else {
    try {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updateData)));
        $sql = "UPDATE patients SET $set WHERE id = ?";
        
        echo "SQL: $sql\n";
        
        $params = array_values($updateData);
        $params[] = $patientId;
        
        echo "Params: " . json_encode($params) . "\n";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        echo "✓ Update successful! Rows affected: " . $stmt->rowCount() . "\n";
    } catch (Exception $e) {
        echo "✗ Update failed: " . $e->getMessage() . "\n";
    }
}
?>
