<?php
// Integration test: create a visit with empty ent_type but 'lump in neck' complaint, run inference, and ensure Head & Neck shows up
$base = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$phpCli = $argv[2] ?? 'php';
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

// Query baseline
list($r0,) = http_req('GET', $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today, null, $headers);
$d0 = json_decode($r0, true);
$ent0 = $d0['data']['ent_distribution'] ?? $d0['ent_distribution'] ?? null;
if (!$ent0) { echo "FAILED: Cannot read ent_distribution (baseline)\n"; exit(1); }
$labels0 = $ent0['labels']; $data0 = $ent0['data'];
$idx0 = array_search('Head & Neck', $labels0);
$before = ($idx0 !== false) ? $data0[$idx0] : 0;

// Create visit with blank ent_type
$payload = ['patient_id' => 1, 'visit_date' => date('Y-m-d H:i:s'), 'visit_type' => 'Consultation', 'ent_type' => '', 'chief_complaint' => 'Lump in the neck'];
list($cr,) = http_req('POST', $base . '/api.php?route=/api/visits', $payload, $headers);
$j = json_decode($cr, true);
if (!($j && isset($j['data']['id']))) { echo "FAILED: Visit creation failed: $cr\n"; exit(1); }

// Run inference script via CLI
$cmd = escapeshellcmd($phpCli) . ' ' . escapeshellarg(__DIR__ . '/../scripts/infer_ent_types.php');
exec($cmd, $out, $rc);
if ($rc !== 0) { echo "Inference script failed (rc=$rc):\n" . implode("\n", $out) . "\n"; exit(1); }

// Query analytics again
list($r1,) = http_req('GET', $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today, null, $headers);
$d1 = json_decode($r1, true);
$ent1 = $d1['data']['ent_distribution'] ?? $d1['ent_distribution'] ?? null;
if (!$ent1) { echo "FAILED: Cannot read ent_distribution (after)\n"; exit(1); }
$labels1 = $ent1['labels']; $data1 = $ent1['data'];
$idx1 = array_search('Head & Neck', $labels1);
$after = ($idx1 !== false) ? $data1[$idx1] : 0;

if ($after <= $before) { echo "FAILED: Expected Head & Neck to increase (before={$before} after={$after})\n"; exit(1); }
echo "ENT inference test PASSED (before={$before} after={$after})\n";
exit(0);
