<?php
// One-off script to reconcile non-canonical ent_type values to canonical enum values in patient_visits
require_once __DIR__ . '/../config/Database.php';
$db = Database::getInstance()->getConnection();

function normalize($val) {
    $raw = strtolower(trim((string)$val));
    $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $raw);
    $normalized = preg_replace('/\s+/', ' ', trim($normalized));
    if ($raw === '' || $raw === null) return 'misc';
    $syn = [
        'head & neck'=>'head_neck_tumor','head/neck'=>'head_neck_tumor','head_neck'=>'head_neck_tumor','headneck'=>'head_neck_tumor','head_neck_tumor'=>'head_neck_tumor',
        'lifestyle medicine'=>'lifestyle_medicine','lifestyle_medicine'=>'lifestyle_medicine','lifestyle'=>'lifestyle_medicine',
        'misc'=>'misc','other'=>'misc','misc/others'=>'misc','misc / others'=>'misc'
    ];
    $allowed = ['ear','nose','throat','head_neck_tumor','lifestyle_medicine','misc'];
    if (in_array($raw, $allowed, true)) return $raw;
    if (in_array($normalized, $allowed, true)) return $normalized;
    if (isset($syn[$raw])) return $syn[$raw];
    if (isset($syn[$normalized])) return $syn[$normalized];
    if (preg_match('/head\s*.*\s*neck/', $normalized)) return 'head_neck_tumor';
    if (strpos($normalized, 'lifestyle') !== false) return 'lifestyle_medicine';
    if (preg_match('/^misc|\bother\b|\bothers\b/', $normalized)) return 'misc';
    return null;
}

$stmt = $db->query("SELECT id, ent_type FROM patient_visits");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$updates = 0;
foreach ($rows as $r) {
    $id = $r['id']; $cur = $r['ent_type'];
    $n = normalize($cur);
    if ($n && $n !== $cur) {
        $u = $db->prepare("UPDATE patient_visits SET ent_type = :ent WHERE id = :id");
        $u->execute(['ent' => $n, 'id' => $id]);
        $updates++;
        echo "Updated visit $id: $cur -> $n\n";
    }
}
echo "Done. Updated $updates rows.\n";
