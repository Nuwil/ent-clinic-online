<?php
// Test to ensure duplicate visit creation doesn't inflate ENT counts
$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$patientId = $argv[2] ?? 1;

function http_req($method, $url, $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $h = [];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $h[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

$headers = ['X-User-Id: 2', 'X-User-Role: doctor'];
$today = date('Y-m-d');
list($res1,) = http_req('GET', $baseUrl . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today, null, $headers);
$d1 = json_decode($res1, true);
$ent = $d1['data']['ent_distribution'] ?? $d1['ent_distribution'] ?? null;
if (!$ent) { echo "FAILED: Cannot read ent_distribution\n"; exit(1); }
$labels = $ent['labels']; $data = $ent['data'];
$idx = array_search('Ears', $labels);
$before = ($idx !== false) ? $data[$idx] : 0;

$visitDate = date('Y-m-d H:i:s');
$payload = [
    'patient_id' => (int)$patientId,
    'visit_date' => $visitDate,
    'visit_type' => 'Consultation',
    'ent_type' => 'ear',
    'chief_complaint' => 'Duplicate test'
];
// Post twice in quick succession
list($r2,) = http_req('POST', $baseUrl . '/api.php?route=/api/visits', $payload, $headers);
list($r3,) = http_req('POST', $baseUrl . '/api.php?route=/api/visits', $payload, $headers);

// Query analytics again
list($res2,) = http_req('GET', $baseUrl . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today, null, $headers);
$d2 = json_decode($res2, true);
$ent2 = $d2['data']['ent_distribution'] ?? $d2['ent_distribution'] ?? null;
if (!$ent2) { echo "FAILED: Cannot read ent_distribution (after)\n"; exit(1); }
$labels2 = $ent2['labels']; $data2 = $ent2['data'];
$idx2 = array_search('Ears', $labels2);
$after = ($idx2 !== false) ? $data2[$idx2] : 0;

if ($after !== $before + 1) {
    echo "FAILED: Duplicate protection failed (before={$before} after={$after})\n";
    exit(1);
}

echo "Duplicate protection PASSED (before={$before} after={$after})\n";
?>