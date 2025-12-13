<?php
// Simple CLI test for appointments and visit creation via API
require_once __DIR__ . '/../config/Database.php';

$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$patientId = $argv[2] ?? 1;
$testDate = $argv[3] ?? date('Y-m-d', strtotime('+1 day'));
$adminId = $argv[4] ?? 1;

function curl_post($url, $data, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $h = ['Content-Type: application/json'];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

function curl_get($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $h = [];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

$headers = ['X-User-Id' => $adminId, 'X-User-Role' => 'admin'];

echo "Test date: $testDate\n";

// Create appointment
$start = $testDate . ' 09:00:00';
$end = $testDate . ' 10:00:00';
$payload = [
    'patient_id' => (int)$patientId,
    'type' => 'follow_up',
    'start_at' => $start,
    'end_at' => $end,
    'notes' => 'Test appointment created via test script',
    'chief_complaint' => 'Test appointment CC',
    'blood_pressure' => '120/80'
];
$url = $baseUrl . '/api.php?route=/api/appointments';
list($res, $info) = curl_post($url, $payload, $headers);
echo "POST /api/appointments -> HTTP {$info['http_code']}\n";
echo "Response: $res\n";

if ($info['http_code'] !== 201) {
    echo "Appointment creation failed. Please check logs.\n";
    exit(1);
}

$j = json_decode($res, true);
$aptId = $j['data']['id'] ?? null;
if (!$aptId) {
    echo "No appointment id returned.\n";
    exit(1);
}

echo "Appointment created with id: $aptId\n";

// Fetch appointments for the date
list($res2, $info2) = curl_get($baseUrl . '/api.php?route=/api/appointments&start=' . $testDate . '&end=' . $testDate, $headers);
$j2 = json_decode($res2, true);
$count = count($j2['appointments'] ?? []);
echo "Appointments on $testDate: $count\n";
foreach (($j2['appointments'] ?? []) as $a) {
    if ($a['id'] == $aptId) echo "Found created appointment chief complaint: " . ($a['chief_complaint'] ?? '') . "\n";
}

// Accept appointment
list($res3, $info3) = curl_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/accept', [], $headers);
echo "Accept -> HTTP {$info3['http_code']} Response: $res3\n";

// Complete appointment (create visit)
$completePayload = [
    'chief_complaint' => 'Test visit CC',
    'ent_type' => 'ear',
    'diagnosis' => 'Otitis externa',
    'treatment' => 'Ear drops',
    'prescription' => 'Ear drops 3x daily',
    'notes' => 'Test visit notes',
    'temperature' => 37.2,
    'blood_pressure' => '120/78'
];
list($res4, $info4) = curl_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/complete', $completePayload, $headers);
echo "Complete -> HTTP {$info4['http_code']} Response: $res4\n";

// Check visits persisted
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM patient_visits WHERE appointment_id = ? LIMIT 1');
$stmt->execute([$aptId]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);
if ($visit) {
    echo "Visit found: id={$visit['id']}, ent_type={$visit['ent_type']}, diagnosis={$visit['diagnosis']}\n";
    echo "Chief complaint: {$visit['chief_complaint']}, prescription: {$visit['prescription']}, notes: {$visit['notes']}\n";
} else {
    echo "No visit found for appointment id $aptId.\n";
}

// Clean up: optional - keep data for debugging

echo "Test finished.\n";
