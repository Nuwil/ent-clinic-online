<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../api/Router.php';

$db = Database::getInstance()->getConnection();

// Simulate the API call that the frontend makes
echo "<h2>API Appointments Response Test</h2>";
echo "<pre>";

// Get a patient ID (use the first patient)
$stmt = $db->query("SELECT id FROM patients LIMIT 1");
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo "No patients found in database!\n";
    exit;
}

$patient_id = $patient['id'];
echo "Testing API call with patient_id: $patient_id\n\n";

// Now simulate what the API controller does
$sql = "SELECT *, appointment_date AS start_at, DATE_ADD(appointment_date, INTERVAL COALESCE(duration,0) MINUTE) AS end_at, appointment_type AS type FROM appointments WHERE patient_id = ? ORDER BY appointment_date";
$stmt = $db->prepare($sql);
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Raw SQL Result:\n";
echo "Total appointments for patient $patient_id: " . count($appointments) . "\n\n";

foreach ($appointments as $apt) {
    echo "ID: {$apt['id']}\n";
    echo "  Date: {$apt['appointment_date']}\n";
    echo "  Type: {$apt['type']}\n";
    echo "  Status: {$apt['status']}\n";
    echo "  Notes: {$apt['notes']}\n\n";
}

// Now simulate the frontend filter
echo "\n=== Frontend Filter Simulation ===\n";
$filtered = array_filter($appointments, function($a) {
    return $a['status'] !== 'Completed' && $a['status'] !== 'Cancelled' && $a['status'] !== 'No-Show';
});

echo "After filtering out Completed/Cancelled/No-Show:\n";
echo "Filtered appointments: " . count($filtered) . "\n\n";

foreach ($filtered as $apt) {
    echo "ID: {$apt['id']} | Status: {$apt['status']} | Date: {$apt['appointment_date']}\n";
}

// Show JSON response
echo "\n=== JSON Response (what frontend receives) ===\n";
$response = ['appointments' => $appointments];
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

echo "\n</pre>";
?>
