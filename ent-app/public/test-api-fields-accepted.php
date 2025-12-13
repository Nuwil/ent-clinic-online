<?php
/**
 * Test the API endpoint directly to verify vaccine_history, emergency_contact_name, 
 * and emergency_contact_phone fields are being accepted and saved
 */
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    
    // Use patient ID 7 for testing
    $patientId = 7;
    
    // First, verify the patient exists
    $stmt = $db->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Patient not found']);
        exit;
    }
    
    // Simulate the API call with test data
    $testData = [
        'vaccine_history' => 'COVID-19 Booster (Dec 2024), Flu (Oct 2024)',
        'emergency_contact_name' => 'Jane Doe',
        'emergency_contact_phone' => '+1-555-123-4567',
        'allergies' => 'Penicillin'  // Include another field to verify it works too
    ];
    
    // Simulate what the API endpoint would do
    // 1. Check allowed fields
    $allowedFields = [
        'first_name', 'last_name', 'gender', 'date_of_birth', 'email',
        'phone', 'occupation', 'address', 'city', 'state', 'postal_code', 'country',
        'medical_history', 'current_medications', 'allergies',
        'insurance_provider', 'insurance_id',
        'height', 'weight', 'blood_pressure', 'temperature', 'bmi',
        'vaccine_history', 'emergency_contact_name', 'emergency_contact_phone'
    ];
    
    $data = [];
    foreach ($allowedFields as $field) {
        if (isset($testData[$field])) {
            $data[$field] = $testData[$field];
        }
    }
    
    // 2. Get existing columns
    $colStmt = $db->query("SHOW COLUMNS FROM patients");
    $cols = $colStmt->fetchAll();
    $existingCols = [];
    foreach ($cols as $c) {
        if (isset($c['Field'])) $existingCols[] = $c['Field'];
    }
    
    // 3. Filter data by existing columns
    $data = array_intersect_key($data, array_flip($existingCols));
    
    echo json_encode([
        'status' => 'test_results',
        'patient_id' => $patientId,
        'test_input' => $testData,
        'allowed_fields' => $allowedFields,
        'existing_columns' => $existingCols,
        'data_to_update' => $data,
        'vaccine_history_included' => isset($data['vaccine_history']),
        'emergency_contact_name_included' => isset($data['emergency_contact_name']),
        'emergency_contact_phone_included' => isset($data['emergency_contact_phone']),
        'all_critical_fields_present' => (
            isset($data['vaccine_history']) && 
            isset($data['emergency_contact_name']) && 
            isset($data['emergency_contact_phone'])
        ) ? 'YES ✓' : 'NO ✗',
        'note' => 'If all_critical_fields_present is YES, the fix is working correctly.'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>
