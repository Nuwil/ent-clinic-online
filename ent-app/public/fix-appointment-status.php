<?php
/**
 * Database Migration: Update Appointment Status Values
 * 
 * This script updates old status values to new ones
 * scheduled → Pending
 * accepted → Accepted
 * completed → Completed
 * cancelled → Cancelled
 */

if (!isset($_GET['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Fix Appointment Status Values</title>
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
            <h1>Fix Appointment Status Values</h1>
            <p>This script will update old status values to the new format:</p>
            <ul>
                <li><strong>scheduled</strong> → <strong>Pending</strong></li>
                <li><strong>accepted</strong> → <strong>Accepted</strong></li>
                <li><strong>completed</strong> → <strong>Completed</strong></li>
                <li><strong>cancelled</strong> → <strong>Cancelled</strong></li>
            </ul>
            <div class="warning">
                <strong>⚠️ Important:</strong> Back up your database before running this migration!
            </div>
            <p>Click the button below to proceed.</p>
            <a href="?confirm=1" class="button">Run Status Update</a>
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
    
    $updates = [
        "UPDATE appointments SET status = 'Pending' WHERE status = 'scheduled'",
        "UPDATE appointments SET status = 'Accepted' WHERE status = 'accepted'",
        "UPDATE appointments SET status = 'Completed' WHERE status = 'completed'",
        "UPDATE appointments SET status = 'Cancelled' WHERE status = 'cancelled'"
    ];
    
    $results = [];
    foreach ($updates as $sql) {
        try {
            $rowCount = $db->query($sql)->rowCount();
            $results[] = [
                'sql' => $sql,
                'success' => true,
                'message' => "Updated $rowCount rows"
            ];
        } catch (Exception $e) {
            $results[] = [
                'sql' => $sql,
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Check for any remaining invalid status values
    try {
        $statuses = $db->fetchAll("SELECT DISTINCT status FROM appointments");
        $statusValues = array_column($statuses, 'status');
        $results[] = [
            'sql' => 'Current status values in database',
            'success' => true,
            'message' => implode(', ', $statusValues) ?: 'No appointments found'
        ];
    } catch (Exception $e) {
        $results[] = [
            'sql' => 'Check status values',
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Status Update Results</title>
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
            <h1>Status Update Results</h1>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <div style="font-weight: bold; margin-bottom: 4px;"><?php echo $result['success'] ? '✓ Success' : '✗ Error'; ?></div>
                    <div class="code"><?php echo htmlspecialchars($result['sql']); ?></div>
                    <div style="margin-top: 4px; font-size: 12px;"><?php echo htmlspecialchars($result['message']); ?></div>
                </div>
            <?php endforeach; ?>
            <a href="/" class="back-link">← Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .error { background: #f8d7da; border: 1px solid #dc3545; padding: 12px; border-radius: 4px; color: #721c24; }
            .code { background: #f8f9fa; padding: 12px; border-radius: 4px; font-family: monospace; overflow-x: auto; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Database Error</h1>
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
