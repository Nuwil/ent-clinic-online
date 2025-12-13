<?php
// Verify header exposes isDoctorOrAdmin flag correctly for roles
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
$headersSecretary = ['X-User-Id: 3', 'X-User-Role: secretary'];

list($resA,$infoA) = http_get($baseUrl . '/?page=appointments', $headersAdmin);
if (strpos($resA, 'window.isDoctorOrAdmin = true') === false) { echo "FAILED: Admin page should set window.isDoctorOrAdmin = true\n"; exit(1); }

list($resD,$infoD) = http_get($baseUrl . '/?page=appointments', $headersDoctor);
if (strpos($resD, 'window.isDoctorOrAdmin = true') === false) { echo "FAILED: Doctor page should set window.isDoctorOrAdmin = true\n"; exit(1); }

list($resS,$infoS) = http_get($baseUrl . '/?page=appointments', $headersSecretary);
if (strpos($resS, 'window.isDoctorOrAdmin = false') === false) { echo "FAILED: Secretary page should set window.isDoctorOrAdmin = false\n"; exit(1); }

echo "UI role flag tests PASSED\n";
?>