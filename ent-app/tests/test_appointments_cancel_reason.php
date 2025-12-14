<?php
// Test that cancellation reasons are stored and returned by analytics
require_once __DIR__ . '/../config/Database.php';
$base = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$headers = ['X-User-Id' => 1, 'X-User-Role' => 'admin'];

function curl_post($url, $data, $headers=[]) {
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

// Create appointment for today
$today = date('Y-m-d');
$payload = [
    'patient_id' => 1,
    'start_at' => $today . ' 09:00:00',
    'end_at' => $today . ' 09:30:00',
    'type' => 'follow_up'
];
list($res, $info) = curl_post($base . '/api.php?route=/api/appointments', $payload, $headers);
$j = json_decode($res, true);
if (($info['http_code'] ?? 0) !== 201) {
    echo "Failed to create appointment: $res\n"; exit(1);
}
$aptId = $j['data']['id'] ?? null;
if (!$aptId) { echo "No appointment id; response: $res\n"; exit(1); }

// Cancel with reason
list($cres, $cinfo) = curl_post($base . '/api.php?route=/api/appointments/' . $aptId . '/cancel', ['reason' => 'Test Reason'], $headers);
if (($cinfo['http_code'] ?? 0) < 200 || ($cinfo['http_code'] ?? 0) >= 300) { echo "Cancel failed: $cres\n"; exit(1); }

// Query analytics for today
$analyticsUrl = $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today;
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $analyticsUrl); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-User-Id: 1', 'X-User-Role: admin']); $ares = curl_exec($ch); $ainfo = curl_getinfo($ch); curl_close($ch);
$aj = json_decode($ares, true);

echo "Analytics response: HTTP {$ainfo['http_code']}\n";
if (!isset($aj['data']['cancellations_by_reason'])) { echo "No cancellations_by_reason in analytics response\n"; echo $ares; exit(1); }
$labels = $aj['data']['cancellations_by_reason']['labels'] ?? [];
$counts = $aj['data']['cancellations_by_reason']['data'] ?? [];
$found = false;
foreach ($labels as $i => $lab) {
    if (stripos($lab, 'Test Reason') !== false || strtolower($lab) === 'test reason') { $found = true; break; }
}
if ($found) { echo "✓ Cancellation reason found in analytics\n"; exit(0); } else { echo "✗ Cancellation reason not found. Labels: " . implode(', ', $labels) . "\n"; exit(1); }
