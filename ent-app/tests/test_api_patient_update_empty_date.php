<?php
// Test updating a patient with empty date_of_birth and empty numeric fields
$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$adminId = $argv[2] ?? 1;

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

$headers = ['X-User-Id' => $adminId, 'X-User-Role' => 'admin'];

// 1) Create a new patient
$createPayload = [
    'first_name' => 'UpdateTest',
    'last_name' => 'Patient',
    'gender' => 'male'
];
$list = curl_post($baseUrl . '/api.php?route=/api/patients', $createPayload, $headers);
list($resCreate, $infoCreate) = $list;
if ($infoCreate['http_code'] !== 201) {
    echo "CREATE failed: HTTP {$infoCreate['http_code']} Response: $resCreate\n";
    exit(1);
}
$j = json_decode($resCreate, true);
$patientId = $j['data']['id'] ?? null;
if (!$patientId) {
    echo "No patient id returned. Response: $resCreate\n";
    exit(1);
}

echo "Created patient id: $patientId\n";

// 2) Attempt to update with empty date_of_birth and empty height/weight
$updatePayload = [
    'date_of_birth' => '',
    'height' => '',
    'weight' => ''
];
list($resUpdate, $infoUpdate) = curl_post($baseUrl . '/api.php?route=/api/patients/' . $patientId, $updatePayload, $headers);
echo "UPDATE -> HTTP {$infoUpdate['http_code']} Response: $resUpdate\n";
if ($infoUpdate['http_code'] >= 200 && $infoUpdate['http_code'] < 300) {
    echo "Update accepted\n";
} else {
    echo "Update failed: $resUpdate\n";
    exit(1);
}

// 3) Fetch patient and verify date_of_birth is null
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

list($resShow, $infoShow) = curl_get($baseUrl . '/api.php?route=/api/patients/' . $patientId, $headers);
$show = json_decode($resShow, true);
$dob = $show['data']['date_of_birth'] ?? null;
$height = $show['data']['height'] ?? null;
$weight = $show['data']['weight'] ?? null;

echo "After update: dob=" . var_export($dob, true) . " height=" . var_export($height, true) . " weight=" . var_export($weight, true) . "\n";

echo "Test complete.\n";
