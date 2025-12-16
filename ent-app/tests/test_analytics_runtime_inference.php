<?php
// Test: runtime inference during analytics should detect Head & Neck matches from text
$base = $argv[1] ?? 'http://localhost/ent-clinic-online/ent-app/public';
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
    curl_close($ch);
    return $res;
}

$today = date('Y-m-d');

// Create a visit with blank ent_type and a head/neck complaint
$payload = ['patient_id' => 1, 'visit_date' => date('Y-m-d H:i:s'), 'visit_type' => 'Consultation', 'ent_type' => '', 'chief_complaint' => 'Lump in the neck noticed recently'];
$r = http_req('POST', $base . '/api.php?route=/api/visits', $payload, $headers);
$j = json_decode($r, true);
if (!($j && isset($j['data']['id']))) { echo "FAILED: Visit creation failed: $r\n"; exit(1); }

// Query analytics with debug=1 and expect inference_debug to show matched>0
$ares = http_req('GET', $base . '/api.php?route=/api/analytics&start=' . $today . '&end=' . $today . '&debug=1', null, $headers);
$aj = json_decode($ares, true);
if (!isset($aj['data'])) { echo "FAILED: No analytics data returned\n"; echo $ares; exit(1); }
$inf = $aj['data']['ent_debug']['inference_debug'] ?? null;
if (!$inf || !isset($inf['head_neck']) || ($inf['head_neck']['matched'] ?? 0) <= 0) { echo "FAILED: Runtime inference did not detect Head & Neck match\n"; echo $ares; exit(1); }
echo "Runtime inference detected Head & Neck matches: matched=" . $inf['head_neck']['matched'] . "\n";
exit(0);
