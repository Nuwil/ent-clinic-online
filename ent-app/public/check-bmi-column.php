<?php
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

$colStmt = $db->query("SHOW COLUMNS FROM patients");
$cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Checking for BMI Column ===\n";
$found = false;
foreach ($cols as $c) {
    if ($c['Field'] === 'bmi') {
        $found = true;
        echo "✓ Found: " . json_encode($c) . "\n";
    }
}

if (!$found) {
    echo "✗ BMI column NOT FOUND\n";
    echo "\nAll columns:\n";
    foreach ($cols as $c) {
        echo "  - " . $c['Field'] . " (" . $c['Type'] . ")\n";
    }
}
?>
