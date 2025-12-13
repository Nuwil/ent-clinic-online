<?php
// Debug script to log form submission data for edit patient profile
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

// Log what was POSTed
file_put_contents(
    __DIR__ . '/../logs/profile_form_debug.log',
    "\n\n=== Profile Form Debug Log ===\n" .
    "Time: " . date('Y-m-d H:i:s') . "\n" .
    "POST Data:\n" .
    print_r($_POST, true) . "\n" .
    "Action: " . ($_POST['action'] ?? 'NOT SET') . "\n" .
    "ID: " . ($_POST['id'] ?? 'NOT SET') . "\n" .
    "vaccine_history: " . ($_POST['vaccine_history'] ?? 'NOT SET') . "\n" .
    "emergency_contact_name: " . ($_POST['emergency_contact_name'] ?? 'NOT SET') . "\n" .
    "emergency_contact_phone: " . ($_POST['emergency_contact_phone'] ?? 'NOT SET') . "\n",
    FILE_APPEND
);

// Also test the API call that would be made
if ($_POST['action'] === 'update_patient_profile') {
    $id = $_POST['id'] ?? null;
    $data = [
        'vaccine_history' => $_POST['vaccine_history'] ?? '',
        'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
        'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? '',
    ];
    
    file_put_contents(
        __DIR__ . '/../logs/profile_form_debug.log',
        "\n\nData to be sent to API:\n" .
        "PUT /api/patients/" . $id . "\n" .
        "Payload: " . json_encode($data, JSON_PRETTY_PRINT) . "\n",
        FILE_APPEND
    );
    
    echo "Debug log written to logs/profile_form_debug.log\n";
    echo "Check the log file to see what data was captured from the form.\n";
    echo "Vaccine History: " . ($data['vaccine_history'] ?? 'NOT SET') . "\n";
    echo "Emergency Contact Name: " . ($data['emergency_contact_name'] ?? 'NOT SET') . "\n";
    echo "Emergency Contact Phone: " . ($data['emergency_contact_phone'] ?? 'NOT SET') . "\n";
}
?>
