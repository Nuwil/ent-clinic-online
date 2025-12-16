<?php
/**
 * Database Schema Migration: Update from old appointments table to new schema
 * This migrates data from the old appointments table to the correct schema
 */

require_once __DIR__ . '/../config/Database.php';

if (!isset($_GET['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Schema Migration</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h1 { color: #333; }
            p { color: #666; line-height: 1.6; }
            .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px; margin: 15px 0; color: #856404; }
            .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
            .button:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Schema Migration</h1>
            <p>This will update your appointments table structure from the old schema to the new one.</p>
            <div class="warning">
                <strong>⚠️ Important:</strong> Back up your database before running this migration!
            </div>
            <p>Changes:</p>
            <ul>
                <li>Rename columns: start_at → appointment_date, end_at duration</li>
                <li>Rename columns: type → appointment_type</li>
                <li>Convert status values: scheduled → Pending, accepted → Accepted, etc.</li>
                <li>Add missing columns: doctor_id, blood_pressure, temperature, pulse_rate, respiratory_rate, oxygen_saturation</li>
            </ul>
            <a href="?confirm=1" class="button">Run Migration</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    $db = Database::getInstance();
    $results = [];

    // Step 1: Check if old schema exists
    try {
        $checkOld = $db->fetch("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'appointments' AND COLUMN_NAME = 'start_at'");
        $hasOldSchema = !empty($checkOld);
    } catch (Exception $e) {
        $hasOldSchema = false;
    }

    if (!$hasOldSchema) {
        $results[] = [
            'success' => true,
            'message' => 'Old schema not found. Database is already using the correct schema.'
        ];
    } else {
        // Backup old table
        $sql = "CREATE TABLE appointments_backup AS SELECT * FROM appointments";
        try {
            $db->query($sql);
            $results[] = ['success' => true, 'message' => 'Created backup table: appointments_backup'];
        } catch (Exception $e) {
            $results[] = ['success' => false, 'message' => 'Failed to create backup: ' . $e->getMessage()];
        }

        // Rename old table
        $sql = "RENAME TABLE appointments TO appointments_old";
        try {
            $db->query($sql);
            $results[] = ['success' => true, 'message' => 'Renamed old appointments table to appointments_old'];
        } catch (Exception $e) {
            $results[] = ['success' => false, 'message' => 'Failed to rename table: ' . $e->getMessage()];
        }

        // Create new table with correct schema
        $sql = "CREATE TABLE IF NOT EXISTS appointments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            doctor_id INT,
            appointment_date DATETIME NOT NULL,
            appointment_type VARCHAR(100),
            duration INT,
            status ENUM('Pending', 'Accepted', 'Completed', 'Cancelled', 'No-Show') DEFAULT 'Pending',
            notes TEXT,
            blood_pressure VARCHAR(20),
            temperature DECIMAL(4,1),
            pulse_rate INT,
            respiratory_rate INT,
            oxygen_saturation INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES users(id),
            INDEX idx_patient_id (patient_id),
            INDEX idx_doctor_id (doctor_id),
            INDEX idx_appointment_date (appointment_date),
            INDEX idx_status (status)
        )";
        try {
            $db->query($sql);
            $results[] = ['success' => true, 'message' => 'Created new appointments table'];
        } catch (Exception $e) {
            $results[] = ['success' => false, 'message' => 'Failed to create new table: ' . $e->getMessage()];
        }

        // Migrate data from old table
        $sql = "INSERT INTO appointments (patient_id, appointment_date, appointment_type, status, notes, created_at)
                SELECT patient_id, start_at, type, 
                       CASE 
                           WHEN status = 'scheduled' THEN 'Pending'
                           WHEN status = 'accepted' THEN 'Accepted'
                           WHEN status = 'completed' THEN 'Completed'
                           WHEN status = 'cancelled' THEN 'Cancelled'
                           ELSE 'Pending'
                       END,
                       notes, created_at
                FROM appointments_old";
        try {
            $db->query($sql);
            $results[] = ['success' => true, 'message' => 'Migrated data from old table'];
        } catch (Exception $e) {
            $results[] = ['success' => false, 'message' => 'Failed to migrate data: ' . $e->getMessage()];
        }

        // Drop old table
        $sql = "DROP TABLE IF EXISTS appointments_old";
        try {
            $db->query($sql);
            $results[] = ['success' => true, 'message' => 'Dropped old appointments_old table'];
        } catch (Exception $e) {
            $results[] = ['success' => false, 'message' => 'Could not drop old table: ' . $e->getMessage()];
        }
    }

    // Apply any standalone SQL migration files in the database/migrations folder
    $migrationsDir = __DIR__ . '/../database/migrations';
    if (is_dir($migrationsDir)) {
        $files = glob($migrationsDir . '/*.sql');
        foreach ($files as $f) {
            try {
                $sql = file_get_contents($f);
                if (!trim($sql)) continue;
                // Use Database->query() to execute migration SQL (handles prepared execution)
                try {
                    $db->query($sql);
                    $results[] = ['success' => true, 'message' => 'Applied migration: ' . basename($f)];
                } catch (Exception $e) {
                    $results[] = ['success' => false, 'message' => 'Failed to apply migration ' . basename($f) . ': ' . $e->getMessage()];
                }
            } catch (Exception $e) {
                $results[] = ['success' => false, 'message' => 'Failed migration ' . basename($f) . ': ' . $e->getMessage()];
            }
        }
    }

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migration Results</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h1 { color: #333; }
            .result { padding: 12px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #ccc; }
            .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
            .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Migration Results</h1>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <div style="font-weight: bold; margin-bottom: 4px;"><?php echo $result['success'] ? '✓ Success' : '✗ Error'; ?></div>
                    <div><?php echo htmlspecialchars($result['message']); ?></div>
                </div>
            <?php endforeach; ?>
            <a href="/ent-clinic-online/public/pages/appointments.php" class="back-link">← Back to Appointments</a>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migration Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .error { background: #f8d7da; border: 1px solid #dc3545; padding: 12px; border-radius: 4px; color: #721c24; }
            .code { background: #f8f9fa; padding: 12px; border-radius: 4px; font-family: monospace; overflow-x: auto; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Migration Error</h1>
            <div class="error">
                <strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
