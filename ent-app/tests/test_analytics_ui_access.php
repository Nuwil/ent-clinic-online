<?php
// Test UI access to analytics page for admin/doctor/secretary via header-based auth
$baseUrl = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';

function http_get($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $h = [];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

$admin = ['X-User-Id: 1', 'X-User-Role: admin'];
$doctor = ['X-User-Id: 2', 'X-User-Role: doctor'];
$secretary = ['X-User-Id: 3', 'X-User-Role: secretary'];

list($resA, $infoA) = http_get($baseUrl . '/?page=analytics', $admin);
if (strpos($resA, '<h2>Analytics</h2>') === false) { echo "FAILED: Admin should see analytics page\n"; exit(1); }

list($resD, $infoD) = http_get($baseUrl . '/?page=analytics', $doctor);
if (strpos($resD, '<h2>Analytics</h2>') === false) { echo "FAILED: Doctor should see analytics page\n"; exit(1); }

list($resS, $infoS) = http_get($baseUrl . '/?page=analytics', $secretary);
if (strpos($resS, '<h2>Analytics</h2>') !== false) { echo "FAILED: Secretary should NOT have analytics page access\n"; exit(1); }

echo "Analytics UI access tests PASSED\n";
?>
