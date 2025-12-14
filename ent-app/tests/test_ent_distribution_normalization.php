<?php
// Test that non-canonical ent_type labels are normalized and appear in ENT distribution
require_once __DIR__ . '/../config/Database.php';
$base = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$headers = ['X-User-Id' => 2, 'X-User-Role' => 'doctor'];

function http_req($method, $url, $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $h = [];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $h[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

$today = date('Y-m-d');

// Create a few visits with different ent_type variations
$cases = [
    ['ent_type' => 'Head & Neck', 'label' => 'Head & Neck'],
    ['ent_type' => 'lifestyle', 'label' => 'Lifestyle'],
    ['ent_type' => 'Other', 'label' => 'Misc / Others']
];

foreach ($cases as $c) {
    $payload = ['patient_id' => 1, 'visit_date' => date('Y-m-d H:i:s'), 'visit_type' => 'Consultation', 'ent_type' => $c['ent_type'], 'chief_complaint' => 'Normalization test'];
    list($r,) = http_req('POST', $base . '/api.php?route=/api/visits', $payload, $headers);
}

// Query analytics for today
list($ares,) = http_req('GET', $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today, null, $headers);
$aj = json_decode($ares, true);
$ent = $aj['data']['ent_distribution'] ?? $aj['ent_distribution'] ?? null;
if (!$ent) { echo "FAILED: Cannot read ent_distribution\n"; exit(1); }
$labels = $ent['labels']; $data = $ent['data'];

foreach ($cases as $c) {
    $idx = array_search($c['label'], $labels);
    $val = ($idx !== false) ? $data[$idx] : 0;
    if ($val <= 0) { echo "FAILED: Expected {$c['label']} to have >0 count (got {$val})\n"; exit(1); }
}

echo "ENT normalization test PASSED\n"; exit(0);
