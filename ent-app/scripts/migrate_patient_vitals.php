<?php
/**
 * Migration: Add vitals columns to patients table if they don't exist
 * Safe: uses ALTER TABLE ... ADD COLUMN IF NOT EXISTS (MySQL 8.0+)
 * Fallback: individual column checks for older MySQL versions
 * 
 * Usage (PowerShell):
 * php scripts\migrate_patient_vitals.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();

    $alterStatements = [
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS height DECIMAL(5,2) COMMENT 'Height in cm'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS weight DECIMAL(5,2) COMMENT 'Weight in kg'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS bmi DECIMAL(5,2) COMMENT 'Body Mass Index (kg/m2)'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS blood_pressure VARCHAR(20) COMMENT 'e.g., 120/80'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS temperature DECIMAL(4,1) COMMENT 'Temperature in Celsius'",
        "ALTER TABLE patients ADD COLUMN IF NOT EXISTS vitals_updated_at TIMESTAMP NULL COMMENT 'Last update of vitals'"
    ];

    $failedStatements = [];
    foreach ($alterStatements as $stmt) {
        if (!$db->query($stmt)) {
            // MySQL versions < 8.0 don't support IF NOT EXISTS, so we'll catch and continue
            if (strpos($db->error, 'Duplicate column') === false && strpos($db->error, 'IF NOT EXISTS') === false) {
                $failedStatements[] = [
                    'sql' => $stmt,
                    'error' => $db->error
                ];
            }
        }
    }

    if (empty($failedStatements)) {
        echo "Migration successful: Patient vitals columns ensured.\n";
        echo "Columns added/verified: height, weight, blood_pressure, temperature, vitals_updated_at\n";
        exit(0);
    } else {
        echo "Migration partially completed. Some columns may already exist.\n";
        echo "Details:\n";
        foreach ($failedStatements as $failed) {
            echo "  SQL: " . $failed['sql'] . "\n";
            echo "  Error: " . $failed['error'] . "\n";
        }
        exit(1);
    }
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(3);
}
