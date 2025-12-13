<?php
/**
 * Test script to verify vaccine_history, emergency_contact_name, and emergency_contact_phone 
 * are properly saved when updating a patient
 */
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    
    // Test with patient ID 7 (or use any existing patient)
    $testPatientId = 7;
    
    // Get current patient data
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$testPatientId]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        echo json_encode(['error' => 'Patient not found']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Test patient data retrieved',
        'patient_id' => $testPatientId,
        'current_values' => [
            'vaccine_history' => $patient['vaccine_history'] ?? 'NULL',
            'emergency_contact_name' => $patient['emergency_contact_name'] ?? 'NULL',
            'emergency_contact_phone' => $patient['emergency_contact_phone'] ?? 'NULL'
        ],
        'instructions' => [
            'step1' => 'Open patient profile page for patient ID ' . $testPatientId,
            'step2' => 'Click "Edit Patient Profile" button',
            'step3' => 'Enter new values in the following fields:',
            'fields' => [
                'Vaccine History' => 'e.g., Flu 2025, COVID-19 Booster',
                'Emergency Contact Name' => 'e.g., John Smith',
                'Emergency Contact Phone' => 'e.g., +1234567890'
            ],
            'step4' => 'Click "Save Changes" button',
            'step5' => 'Page will reload and show success message',
            'step6' => 'Reopen Edit Patient Profile to verify values were saved'
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
