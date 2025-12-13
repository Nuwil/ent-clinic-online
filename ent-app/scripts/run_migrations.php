<?php
// Run SQL migrations located in ent-app/database/migrations
// Usage: php run_migrations.php [--dry-run]

require_once __DIR__ . '/../config/Database.php';

$dryRun = in_array('--dry-run', $argv);
$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.sql');
if (!$files) { echo "No migration files found in $dir\n"; exit(0); }

$db = Database::getInstance()->getConnection();

foreach ($files as $file) {
    $name = basename($file);
    echo "Running migration: $name\n";
    $sql = file_get_contents($file);
    if ($dryRun) { echo "DRY RUN: would execute $name\n"; continue; }
    try {
        // MySQL may use multiple statements; execute each separately for safety
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($stmts as $stmt) {
            if ($stmt === '') continue;
            $db->exec($stmt);
        }
        echo "Executed: $name\n";
    } catch (PDOException $e) {
        echo "ERROR executing $name: " . $e->getMessage() . "\n";
    }
}

echo "Migrations completed.\n";
?>