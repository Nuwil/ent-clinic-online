<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

echo "<h2>Create Test Appointment</h2>";
echo "<pre>";

try {
    // Get a patient ID
    $stmt = $db->query("SELECT id FROM patients LIMIT 1");
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_id = $patient['id'];
    
    // Get a doctor ID
    $stmt = $db->query("SELECT id FROM users WHERE role = 'doctor' LIMIT 1");
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    $doctor_id = $doctor['id'];
    
    echo "Patient ID: $patient_id\n";
    echo "Doctor ID: $doctor_id\n\n";
    
    // Create appointment with Pending status
    $appointment_date = date('Y-m-d H:i:s', strtotime('+1 day 10:00'));
    $stmt = $db->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_type, duration, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $patient_id,
        $doctor_id,
        $appointment_date,
        'consultation',
        30,
        'Pending',
        'Test appointment for debugging'
    ]);
    
    if ($result) {
        $apt_id = $db->lastInsertId();
        echo "✓ Test appointment created successfully!\n";
        echo "Appointment ID: $apt_id\n";
        echo "Patient ID: $patient_id\n";
        echo "Date: $appointment_date\n";
        echo "Status: Pending\n\n";
        
        echo "Visit the patient profile here:\n";
        echo "http://localhost/ent-clinic-online/ent-app/public/pages/patient-profile.php?id=$patient_id\n";
    } else {
        echo "✗ Failed to create appointment\n";
        print_r($stmt->errorInfo());
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    echo "\n" . $e->getTraceAsString();
}

echo "</pre>";
?>
