<?php
// Test that repeated calls to /api/analytics with same params return identical responses
$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$start = $argv[2] ?? date('Y-m-d');
$end = $argv[3] ?? date('Y-m-d');
$headers = ['X-User-Id: 1', 'X-User-Role: admin'];

function http_get($url, $headers = []) {
    $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $h = []; foreach ($headers as $k => $v) $h[] = "$k: $v"; curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $res = curl_exec($ch); curl_close($ch); return $res;
}

$url = $baseUrl . "/api.php?route=/api/analytics&start={$start}&end={$end}";
$r1 = http_get($url, $headers);
$r2 = http_get($url, $headers);
if ($r1 !== $r2) {
    echo "FAILED: Analytics responses differ between calls\n";
    file_put_contents('tests/analytics_debug_1.json', $r1);
    file_put_contents('tests/analytics_debug_2.json', $r2);
    exit(1);
}

echo "Analytics determinism test PASSED\n";
?>