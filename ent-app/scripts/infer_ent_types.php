<?php
// Infer missing or generic ent_type values from textual fields in patient_visits
// Usage: php infer_ent_types.php [--dry-run]
require_once __DIR__ . '/../config/Database.php';
$dry = in_array('--dry-run', $argv);
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT id, ent_type, chief_complaint, diagnosis, notes FROM patient_visits WHERE ent_type IS NULL OR TRIM(ent_type) = '' OR ent_type = 'misc'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " rows to analyze\n";
$updated = 0;
foreach ($rows as $r) {
    $text = strtolower(($r['chief_complaint'] ?? '') . ' ' . ($r['diagnosis'] ?? '') . ' ' . ($r['notes'] ?? ''));
    $new = null;
    if (preg_match('/head\b|neck\b|lump|mass|tumor/', $text)) {
        $new = 'head_neck_tumor';
    } elseif (preg_match('/lifestyle|diet|smoking|exercise|obesity|weight/', $text)) {
        $new = 'lifestyle_medicine';
    } elseif (preg_match('/\bear\b|hearing|earache|otitis/', $text)) {
        $new = 'ear';
    } elseif (preg_match('/nose|sinus|sneez|nasal|rhinitis/', $text)) {
        $new = 'nose';
    } elseif (preg_match('/throat|tonsil|dysphagia|sore throat/', $text)) {
        $new = 'throat';
    }

    if ($new) {
        echo "Visit {$r['id']}: inferred $new from text snippet\n";
        if (!$dry) {
            $u = $db->prepare("UPDATE patient_visits SET ent_type = :ent WHERE id = :id");
            $u->execute(['ent' => $new, 'id' => $r['id']]);
        }
        $updated++;
    }
}

echo "Inference complete. Updated $updated rows.\n";
