<?php
// Test: inference dry-run and apply
$base = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
$headersDoctor = ['X-User-Id' => 2, 'X-User-Role' => 'doctor'];
$headersAdmin = ['X-User-Id' => 1, 'X-User-Role' => 'admin'];

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
    curl_close($ch);
    return $res;
}

$today = date('Y-m-d');
// Create a visit with blank ent_type and head/neck complaint
$payload = ['patient_id' => 1, 'visit_date' => date('Y-m-d H:i:s'), 'visit_type' => 'Consultation', 'ent_type' => '', 'chief_complaint' => 'Lump in the neck noticed recently'];
$r = http_req('POST', $base . '/api.php?route=/api/visits', $payload, $headersDoctor);
$j = json_decode($r, true);
if (!($j && isset($j['data']['id']))) { echo "FAILED: Visit creation failed: $r\n"; exit(1); }
$id = $j['data']['id'];

// Dry-run inference as doctor
$dr = http_req('GET', $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today . '&apply_inference=0', null, $headersDoctor);
$dj = json_decode($dr, true);
if (!($dj && isset($dj['data']['matched']))) { echo "FAILED: Dry-run inference response missing: $dr\n"; exit(1); }
$matched = $dj['data']['matched'];
$hn = $matched['head_neck_tumor'] ?? [];
if (!in_array($id, $hn)) { echo "FAILED: Dry-run did not match created visit in head_neck list\n"; echo $dr; exit(1); }

// Apply inference as admin
$ar = http_req('GET', $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today . '&apply_inference=1', null, $headersAdmin);
$aj = json_decode($ar, true);
if (!($aj && isset($aj['data']['applied']))) { echo "FAILED: Apply inference response missing: $ar\n"; exit(1); }
$applied = $aj['data']['applied'];
$found = false; foreach ($applied as $row) if ($row['id'] == $id) $found = true;
if (!$found) { echo "FAILED: Apply did not update created visit\n"; echo $ar; exit(1); }

// Verify the visit ent_type updated
$v = http_req('GET', $base . '/api.php?route=/api/visits/' . $id, null, $headersAdmin);
$vj = json_decode($v, true);
if (!($vj && isset($vj['data']))) { echo "FAILED: Could not fetch visit $id: $v\n"; exit(1); }
if (($vj['data']['ent_type'] ?? '') !== 'head_neck_tumor') { echo "FAILED: Visit ent_type not updated: " . json_encode($vj) . "\n"; exit(1); }

echo "Inference apply flow succeeded: visit $id updated to head_neck_tumor\n";
exit(0);
