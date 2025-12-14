<?php
// Test: debug=1 should return cancellations_debug with rows/sample to aid diagnosis
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
    curl_close($ch);
    return $res;
}

// Create appointment dated in the past
$past = date('Y-m-d', strtotime('-1 week'));
$payload = [ 'patient_id' => 1, 'start_at' => $past . ' 09:00:00', 'end_at' => $past . ' 09:30:00', 'type' => 'follow_up' ];
$res = curl_post($base . '/api.php?route=/api/appointments', $payload, $headers);
$j = json_decode($res, true);
if (!($j && isset($j['data']['id']))) { echo "Failed to create appointment: $res\n"; exit(1); }
$aptId = $j['data']['id'];

// Cancel now (updated_at will be today)
$cres = curl_post($base . '/api.php?route=/api/appointments/' . $aptId . '/cancel', ['reason' => 'Debug Canceled'], $headers);

// Query analytics with debug=1
$today = date('Y-m-d');
$analyticsUrl = $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today . '&debug=1';
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $analyticsUrl); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-User-Id: 1', 'X-User-Role: admin']); $ares = curl_exec($ch); curl_close($ch);
$aj = json_decode($ares, true);

if (!isset($aj['data']['cancellations_by_reason'])) { echo "No cancellations_by_reason in analytics response\n"; echo $ares; exit(1); }
if (!isset($aj['data']['cancellations_debug'])) { echo "No cancellations_debug present in debug payload\n"; echo $ares; exit(1); }
$rows = $aj['data']['cancellations_debug']['rows'] ?? null;
$sample = $aj['data']['cancellations_debug']['sample'] ?? null;
if ($rows === null) { echo "cancellations_debug.rows missing\n"; exit(1); }
echo "✓ cancellations_debug present (rows=" . count($rows) . ") sample=" . count($sample) . "\n";
exit(0);
