<?php
// Integration test: create -> accept -> complete appointment; verify chief_complaint & visit persistence
require_once __DIR__ . '/../config/Database.php';

$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$patientId = $argv[2] ?? 1;
$date = $argv[3] ?? date('Y-m-d', strtotime('+1 day'));
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

$headersAdmin = ['X-User-Id' => $adminId, 'X-User-Role' => 'admin'];
$headersDoctor = ['X-User-Id' => 2, 'X-User-Role' => 'doctor'];

echo "Integration test date: $date\n";

$start = $date . ' 09:00:00';
$end = $date . ' 10:00:00';
$payload = [
    'patient_id' => (int)$patientId,
    'type' => 'follow_up',
    'start_at' => $start,
    'end_at' => $end,
    'notes' => 'Integration test appointment',
    'chief_complaint' => 'Integration CC',
];

list($res, $info) = curl_post($baseUrl . '/api.php?route=/api/appointments', $payload, $headersAdmin);
if ($info['http_code'] !== 201) {
    echo "FAILED: Appointment creation HTTP {$info['http_code']} Response: $res\n";
    exit(1);
}
$j = json_decode($res, true);
$aptId = $j['data']['id'] ?? null;
if (!$aptId) { echo "FAILED: No appointment id returned\n"; exit(1); }
echo "Created appointment id $aptId\n";

// Verify appointment appears in list with chief_complaint
list($res2,$info2) = curl_get($baseUrl . '/api.php?route=/api/appointments&start=' . $date . '&end=' . $date, $headersAdmin);
$j2 = json_decode($res2, true);
$found = false;
foreach (($j2['appointments'] ?? []) as $a) {
    if ($a['id'] == $aptId) {
        $found = true;
        if (($a['chief_complaint'] ?? '') !== 'Integration CC') {
            echo "FAILED: chief_complaint mismatch in appointment list: '" . ($a['chief_complaint'] ?? '') . "'\n";
            exit(1);
        }
    }
}
if (!$found) { echo "FAILED: Created appointment not in list\n"; exit(1); }
echo "Appointment list contains chief_complaint\n";

// Accept as doctor
list($res3,$info3) = curl_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/accept', [], $headersDoctor);
if ($info3['http_code'] < 200 || $info3['http_code'] >= 300) {
    echo "FAILED: Accept returned HTTP {$info3['http_code']} Response: $res3\n"; exit(1);
}
echo "Appointment accepted\n";

// Complete as doctor (create visit), providing a different chief complaint for the visit
$completePayload = ['chief_complaint' => 'Visit CC', 'ent_type' => 'ear', 'diagnosis' => 'Test', 'notes' => 'Integration visit'];
list($res4,$info4) = curl_post($baseUrl . '/api.php?route=/api/appointments/' . $aptId . '/complete', $completePayload, $headersDoctor);
if ($info4['http_code'] < 200 || $info4['http_code'] >= 300) { echo "FAILED: Complete returned HTTP {$info4['http_code']} Response: $res4\n"; exit(1);} 
echo "Appointment completed, visit created\n";

// Check DB for visit
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM patient_visits WHERE appointment_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$aptId]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$visit) { echo "FAILED: No visit found for appointment $aptId\n"; exit(1); }
if (($visit['chief_complaint'] ?? '') !== 'Visit CC') { echo "FAILED: Visit chief_complaint mismatch: '" . ($visit['chief_complaint'] ?? '') . "'\n"; exit(1); }

echo "Visit persisted with chief_complaint: {$visit['chief_complaint']}\n";

// RBAC quick check via API: doctor should NOT have 'view_settings' permission. We check via helper page GET of settings and expect 403 for doctor trying to access operations guarded by hasPermission('manage_users') via API? Since settings is frontend page requiring admin role, we'll instead check via helper test script test_rbac.php (separate). 

echo "Integration test PASSED\n";
return 0;
?>