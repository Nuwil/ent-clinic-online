<?php
// Test: appointment canceled now should appear in analytics when filtering by today (updated_at)
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

// Create appointment dated in the past (yesterday)
$past = date('Y-m-d', strtotime('-1 week'));
$payload = [ 'patient_id' => 1, 'start_at' => $past . ' 09:00:00', 'end_at' => $past . ' 09:30:00', 'type' => 'follow_up' ];
list($res,) = curl_post($base . '/api.php?route=/api/appointments', $payload, $headers);
$j = json_decode($res, true);
if (!($j && isset($j['data']['id']))) { echo "Failed to create appointment: $res\n"; exit(1); }
$aptId = $j['data']['id'];

// Cancel now (updated_at will be today)
list($cres,) = curl_post($base . '/api.php?route=/api/appointments/' . $aptId . '/cancel', ['reason' => 'Canceled Now Test'], $headers);

// Query analytics for today - should include cancellation by updated_at
$today = date('Y-m-d');
$analyticsUrl = $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today;
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $analyticsUrl); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-User-Id: 1', 'X-User-Role: admin']); $ares = curl_exec($ch); $ainfo = curl_getinfo($ch); curl_close($ch);
$aj = json_decode($ares, true);

if (!isset($aj['data']['cancellations_by_reason'])) { echo "No cancellations_by_reason in analytics response\n"; echo $ares; exit(1); }
$labels = $aj['data']['cancellations_by_reason']['labels'] ?? [];
$found = false;
foreach ($labels as $lab) {
    if (stripos($lab, 'Canceled Now Test') !== false || strtolower($lab) === 'canceled now test') { $found = true; break; }
}
if ($found) { echo "✓ Cancellation by updated_at found in analytics\n"; exit(0); } else { echo "✗ Cancellation not found. Labels: " . implode(', ', $labels) . "\n"; exit(1); }
