<?php
// RBAC Completion API tests - secretary must not complete, doctor/admin can
$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$patientId = $argv[2] ?? 1;
$testDate = $argv[3] ?? date('Y-m-d', strtotime('+1 day'));

function http_post($url, $data = [], $headers = []) {
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

$headersAdmin = ['X-User-Id' => 1, 'X-User-Role' => 'admin'];
$headersDoctor = ['X-User-Id' => 2, 'X-User-Role' => 'doctor'];
$headersSecretary = ['X-User-Id' => 3, 'X-User-Role' => 'secretary'];

// Create appointment as admin
$start = $testDate . ' 09:00:00';
$end = $testDate . ' 10:00:00';
$payload = ['patient_id' => (int)$patientId, 'start_at' => $start, 'end_at' => $end, 'type' => 'follow_up'];
list($res, $info) = http_post($baseUrl . '/api.php?route=/api/appointments', $payload, $headersAdmin);
if ($info['http_code'] !== 201) { echo "FAILED: could not create appointment (HTTP {$info['http_code']})\nResponse: $res\n"; exit(1); }
$j = json_decode($res, true);
$aptId = $j['data']['id'] ?? null;
if (!$aptId) { echo "FAILED: no appointment id returned\n"; exit(1); }

// Accept as doctor
list($resDoc, $infoDoc) = http_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/accept', [], $headersDoctor);
if ($infoDoc['http_code'] < 200 || $infoDoc['http_code'] >= 300) { echo "FAILED: Doctor accept expected success; got HTTP {$infoDoc['http_code']}. Response: $resDoc\n"; exit(1); }

// Try complete as secretary - expected 403
$completePayload = ['chief_complaint' => 'Some CC', 'ent_type' => 'ear', 'diagnosis' => 'Test'];
list($resSec, $infoSec) = http_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/complete', $completePayload, $headersSecretary);
if ($infoSec['http_code'] !== 403) { echo "FAILED: Secretary complete should be 403; got HTTP {$infoSec['http_code']}. Response: $resSec\n"; exit(1); }

// Try complete as doctor - expected success
list($resDocComplete, $infoDocComplete) = http_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/complete', $completePayload, $headersDoctor);
if ($infoDocComplete['http_code'] < 200 || $infoDocComplete['http_code'] >= 300) { echo "FAILED: Doctor complete expected success; got HTTP {$infoDocComplete['http_code']}. Response: $resDocComplete\n"; exit(1); }

echo "RBAC complete endpoint tests PASSED\n";
?>