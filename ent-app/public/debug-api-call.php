<?php
// Test the actual API call for patient update
session_start();
require_once __DIR__ . '/../config/Database.php';

// Simulate user session
$_SESSION['user'] = [
    'id' => 1,
    'role' => 'admin'
];

// Test with patient ID 7
$patientId = 7;

$data = [
    'first_name' => 'Gullsha',
    'last_name' => 'Gaddi',
    'email' => 'test@example.com',
    'phone' => '1234567890',
    'gender' => 'female',
    'date_of_birth' => '2009-09-09',
    'medical_history' => 'Test',
    'allergies' => 'Test',
    'vaccine_history' => 'Test',
    'insurance_provider' => 'Test Insurance',
    'insurance_id' => 'TEST123',
    'emergency_contact_name' => 'Emergency Name',
    'emergency_contact_phone' => '9999999999',
    'height' => 170,
    'weight' => 70,
    'bmi' => 24.22,
    'address' => 'Test Address',
    'city' => 'Test City',
    'state' => 'Test State',
    'postal_code' => '12345',
    'country' => 'Iraq',
    'occupation' => 'Student'
];

echo "=== Testing Patient Update API Call ===\n";
echo "Patient ID: $patientId\n";
echo "Fields: " . count($data) . "\n\n";

// Simulate API call
$protocol = 'http';
$host = 'localhost';
$url = "$protocol://$host/ENT-clinic-online/ent-app/public/api.php?route=/api/patients/$patientId";

echo "URL: $url\n";
echo "Method: PUT\n";
echo "Data: " . json_encode($data) . "\n\n";

$ch = curl_init();
$headers = [
    'Content-Type: application/json',
    'X-User-Id: 1',
    'X-User-Role: admin'
];

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Use session cookie
$cookie = session_name() . '=' . session_id();
curl_setopt($ch, CURLOPT_COOKIE, $cookie);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

session_write_close();

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "=== Response ===\n";
echo "HTTP Code: $httpCode\n";
echo "CURL Error: " . ($curlErr ?: 'None') . "\n";
echo "Response Body:\n";
echo $response . "\n\n";

$result = json_decode($response, true);
echo "Parsed JSON:\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "\n✓ API call would be treated as SUCCESS\n";
} else {
    echo "\n✗ API call would be treated as FAILURE\n";
    echo "Error details: " . json_encode($result) . "\n";
}
?>
