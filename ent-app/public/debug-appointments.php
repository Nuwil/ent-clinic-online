<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

// Get all appointments from the database
echo "<h2>All Appointments in Database</h2>";
echo "<pre>";

$stmt = $db->query("SELECT id, patient_id, appointment_date, appointment_type, status, notes FROM appointments ORDER BY appointment_date DESC");
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total appointments: " . count($appointments) . "\n\n";

foreach ($appointments as $apt) {
    echo "ID: {$apt['id']}, Patient: {$apt['patient_id']}, Date: {$apt['appointment_date']}, Type: {$apt['appointment_type']}, Status: {$apt['status']}, Notes: {$apt['notes']}\n";
}

echo "\n\n<h2>Appointments by Status</h2>";
$stmt = $db->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($statusCounts as $row) {
    echo "Status '{$row['status']}': {$row['count']} appointments\n";
}

echo "\n\n<h2>First 5 Patients</h2>";
$stmt = $db->query("SELECT id, first_name, last_name FROM patients LIMIT 5");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($patients as $patient) {
    $patient_id = $patient['id'];
    $stmt2 = $db->prepare("SELECT id, appointment_date, appointment_type, status FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC");
    $stmt2->execute([$patient_id]);
    $apts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nPatient ID {$patient_id} ({$patient['first_name']} {$patient['last_name']}): " . count($apts) . " appointments\n";
    foreach ($apts as $apt) {
        echo "  - {$apt['appointment_date']} | {$apt['appointment_type']} | Status: {$apt['status']}\n";
    }
}

echo "</pre>";
?>
