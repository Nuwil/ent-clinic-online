<?php
// Test Analytics API call
require_once __DIR__ . '/includes/helpers.php';

// Start session
session_start();

// Simulate logged-in user
$_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

echo "=== Test Analytics API Call ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session User: " . json_encode($_SESSION['user']) . "\n\n";

echo "Calling apiCall()...\n";
$result = apiCall('GET', '/api/analytics?trend_days=90&horizon=7');

if ($result) {
    echo "\n✅ SUCCESS! API returned data:\n";
    echo "  - ent_distribution: " . json_encode($result['ent_distribution'] ?? 'N/A') . "\n";
    echo "  - daily_counts: " . json_encode($result['daily_counts'] ?? 'N/A') . "\n";
    echo "  - forecast_rows count: " . count($result['forecast_rows'] ?? []) . "\n";
    echo "  - forecast_stats: " . json_encode($result['forecast_stats'] ?? 'N/A') . "\n";
} else {
    echo "\n❌ FAILED! API returned false\n";
    if (isset($_SESSION['api_last_error'])) {
        echo "Error details: " . json_encode($_SESSION['api_last_error']) . "\n";
    }
}
