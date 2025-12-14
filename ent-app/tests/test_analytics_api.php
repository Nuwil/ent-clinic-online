<?php
// Test that GET /api/analytics returns valid JSON payload and summary for admin/doctor roles
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

$headersAdmin = ['X-User-Id: 1', 'X-User-Role: admin'];
$headersDoctor = ['X-User-Id: 2', 'X-User-Role: doctor'];

list($resA, $infoA) = http_get($baseUrl . '/api.php?route=/api/analytics', $headersAdmin);
$dataA = json_decode($resA, true);
if (!$dataA || (empty($dataA['data']) && empty($dataA['summary']))) {
    echo "FAILED: Admin analytics response invalid (no summary/data)\n";
    exit(1);
}

list($resD, $infoD) = http_get($baseUrl . '/api.php?route=/api/analytics', $headersDoctor);
$dataD = json_decode($resD, true);
if (!$dataD || (empty($dataD['data']) && empty($dataD['summary']))) {
    echo "FAILED: Doctor analytics response invalid (no summary/data)\n";
    exit(1);
}

// Ensure summary has keys
$summary = $dataA['data']['summary'] ?? $dataA['summary'] ?? null;
if (!$summary || !isset($summary['total_patients']) || !isset($summary['appointments_completed'])) {
    echo "FAILED: Analytics summary missing expected keys\n";
    exit(1);
}

// Ensure forecast exists and suggestions present
$forecast = $dataA['data']['forecast'] ?? $dataA['forecast'] ?? null;
if (!$forecast || !isset($forecast['labels']) || !isset($forecast['data'])) {
    echo "FAILED: Analytics forecast missing expected keys\n";
    exit(1);
}

// Ensure ENT distribution is present
$ent = $dataA['data']['ent_distribution'] ?? $dataA['ent_distribution'] ?? null;
if (!$ent || !isset($ent['labels']) || !isset($ent['data'])) {
    echo "FAILED: Analytics ent_distribution missing expected keys\n";
    exit(1);
}

$suggestions = $dataA['data']['suggestions'] ?? $dataA['suggestions'] ?? null;
if ($suggestions === null) {
    echo "FAILED: Analytics suggestions missing\n";
    exit(1);
}

echo "Analytics API test PASSED\n";
?>
