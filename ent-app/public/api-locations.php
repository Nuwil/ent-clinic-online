<?php
// Simple locations API that reads worldcities.csv and returns minimal lists
// Usage: api-locations.php?action=countries
//        api-locations.php?action=states&country=Country+Name
//        api-locations.php?action=cities&country=Country+Name&state=State+Name

$csvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' ;
// try relative path two levels up if needed
$base = realpath(__DIR__ . '/..');
// worldcities.csv sits at project root (one level above ent-app)
$possible = [
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'worldcities.csv',
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'worldcities.csv',
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'worldcities.csv',
    dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'worldcities.csv',
    __DIR__ . DIRECTORY_SEPARATOR . 'worldcities.csv'
];
$csvFile = null;
foreach ($possible as $p) {
    if (file_exists($p)) { $csvFile = $p; break; }
}

if (!$csvFile) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'worldcities.csv not found']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'countries';

// Helper: stream CSV and yield rows
function stream_csv($path) {
    $f = fopen($path, 'r');
    if (!$f) return;
    $headers = null;
    while (($row = fgetcsv($f)) !== false) {
        if (!$headers) {
            $headers = $row;
            continue;
        }
        $out = [];
        foreach ($headers as $i => $h) {
            $out[$h] = isset($row[$i]) ? $row[$i] : '';
        }
        yield $out;
    }
    fclose($f);
}

header('Content-Type: application/json');

// caching directory (web-accessible) under public/data/locations
$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'locations';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// TTL for caches (seconds)
$CACHE_TTL = 24 * 60 * 60; // 24 hours

function cache_get($path, $ttl) {
    if (file_exists($path) && (time() - filemtime($path) < $ttl)) {
        return @file_get_contents($path);
    }
    return false;
}

function cache_put($path, $content) {
    @file_put_contents($path, $content);
}

if ($action === 'countries') {
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'countries.json';
    $cached = cache_get($cacheFile, $CACHE_TTL);
    if ($cached !== false) { echo $cached; exit; }

    $countries = [];
    foreach (stream_csv($csvFile) as $r) {
        $c = trim($r['country']);
        if ($c === '') continue;
        $countries[$c] = true;
    }
    $list = array_values(array_keys($countries));
    sort($list, SORT_STRING);
    $out = json_encode(['countries' => $list]);
    cache_put($cacheFile, $out);
    echo $out;
    exit;
}

if ($action === 'states') {
    $country = isset($_GET['country']) ? trim($_GET['country']) : '';
    if ($country === '') { echo json_encode(['states' => []]); exit; }
    $slug = preg_replace('/[^a-z0-9\-_]/i', '_', strtolower($country));
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'states_' . $slug . '.json';
    $cached = cache_get($cacheFile, $CACHE_TTL);
    if ($cached !== false) { echo $cached; exit; }

    $states = [];
    foreach (stream_csv($csvFile) as $r) {
        if (strcasecmp(trim($r['country']), $country) !== 0) continue;
        $st = trim($r['admin_name']);
        if ($st === '') continue;
        $states[$st] = true;
    }
    $list = array_values(array_keys($states));
    sort($list, SORT_STRING);
    $out = json_encode(['states' => $list]);
    cache_put($cacheFile, $out);
    echo $out;
    exit;
}

if ($action === 'cities') {
    $country = isset($_GET['country']) ? trim($_GET['country']) : '';
    $state = isset($_GET['state']) ? trim($_GET['state']) : '';
    if ($country === '') { echo json_encode(['cities' => []]); exit; }
    $slugC = preg_replace('/[^a-z0-9\-_]/i', '_', strtolower($country));
    $slugS = $state !== '' ? preg_replace('/[^a-z0-9\-_]/i', '_', strtolower($state)) : 'all';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'cities_' . $slugC . '_' . $slugS . '.json';
    $cached = cache_get($cacheFile, $CACHE_TTL);
    if ($cached !== false) { echo $cached; exit; }

    $cities = [];
    foreach (stream_csv($csvFile) as $r) {
        if (strcasecmp(trim($r['country']), $country) !== 0) continue;
        if ($state !== '' && strcasecmp(trim($r['admin_name']), $state) !== 0) continue;
        $city = trim($r['city']);
        if ($city === '') continue;
        $cities[$city] = true;
    }
    $list = array_values(array_keys($cities));
    sort($list, SORT_STRING);
    $out = json_encode(['cities' => $list]);
    cache_put($cacheFile, $out);
    echo $out;
    exit;
}

// fallback
echo json_encode(['error' => 'invalid action']);
