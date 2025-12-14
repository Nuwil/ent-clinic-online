<?php
// Ensure analytics trend data returns arrays and forecast length is 7
$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';

function http_get($url, $headers = []) {
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

$admin = ['X-User-Id: 1', 'X-User-Role: admin'];
list($res, $info) = http_get($baseUrl . '/api.php?route=/api/analytics', $admin);
$json = json_decode($res, true);
if (!$json) { echo "FAILED: Invalid JSON from analytics\n"; exit(1); }
$data = $json['data'] ?? $json;
if (empty($data['visits_trend']['labels']) || empty($data['visits_trend']['data'])) { echo "FAILED: Trend missing data\n"; exit(1); }
if (empty($data['forecast']['data']) || count($data['forecast']['data']) !== 7) { echo "FAILED: Forecast should have 7 entries\n"; exit(1); }
echo "Analytics trend DB test PASSED\n";
?>
