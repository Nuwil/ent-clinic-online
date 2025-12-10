<?php
/**
 * Database Migration: Add Vitals Columns to Appointments
 * 
 * This script adds vitals columns to the appointments and patient_visits tables
 * Run this once to update your database schema
 */

// Check if already executed
if (!isset($_GET['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Migration - Add Vitals</title>
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
            <h1>Database Migration</h1>
            <p>This script will add the following columns to your database:</p>
            <ul>
                <li><strong>appointments table:</strong> blood_pressure, temperature, pulse_rate, respiratory_rate, oxygen_saturation</li>
                <li><strong>patient_visits table:</strong> pulse_rate, respiratory_rate, oxygen_saturation</li>
            </ul>
            <div class="warning">
                <strong>⚠️ Important:</strong> Back up your database before running this migration!
            </div>
            <p>Click the button below to proceed with the migration.</p>
            <a href="?confirm=1" class="button">Run Migration</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance();
    
    $migrations = [
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS blood_pressure VARCHAR(20)",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS temperature DECIMAL(4,1)",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS pulse_rate INT",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS respiratory_rate INT",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS oxygen_saturation INT",
        "ALTER TABLE patient_visits ADD COLUMN IF NOT EXISTS pulse_rate INT",
        "ALTER TABLE patient_visits ADD COLUMN IF NOT EXISTS respiratory_rate INT",
        "ALTER TABLE patient_visits ADD COLUMN IF NOT EXISTS oxygen_saturation INT"
    ];
    
    $results = [];
    foreach ($migrations as $sql) {
        try {
            $db->query($sql);
            $results[] = ['sql' => $sql, 'success' => true, 'message' => 'Success'];
        } catch (Exception $e) {
            $results[] = ['sql' => $sql, 'success' => false, 'message' => $e->getMessage()];
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
            .code { background: #f8f9fa; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 12px; overflow-x: auto; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Migration Results</h1>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <div style="font-weight: bold; margin-bottom: 4px;"><?php echo $result['success'] ? '✓ Success' : '✗ Error'; ?></div>
                    <div class="code"><?php echo htmlspecialchars($result['sql']); ?></div>
                    <?php if (!$result['success']): ?>
                        <div style="margin-top: 4px; font-size: 12px;"><?php echo htmlspecialchars($result['message']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <a href="<?php echo isset($_GET['return']) ? htmlspecialchars($_GET['return']) : '/'; ?>" class="back-link">← Back</a>
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
                <strong>Error:</strong> Could not connect to database
            </div>
            <div class="code"><?php echo htmlspecialchars($e->getMessage()); ?></div>
        </div>
    </body>
    </html>
    <?php
}
?>
