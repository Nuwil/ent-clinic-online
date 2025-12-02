<?php
/**
 * Analytics Debug Page
 * Test if predictive outlook is receiving data correctly
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../public/includes/helpers.php';

session_start();

// Check if user is authenticated and is doctor/admin
if (!isset($_SESSION['user'])) {
    die('Not authenticated. <a href="' . baseUrl() . '/">Go to login</a>');
}

$user = $_SESSION['user'];
if (!in_array($user['role'] ?? '', ['admin', 'doctor'])) {
    die('Not authorized. Only admin and doctor roles can access this.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Analytics Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f7fb;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { color: #667eea; margin-top: 30px; }
        .debug-box {
            background: #f0f4ff;
            border: 1px solid #d4e1ff;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #d97706; font-weight: bold; }
        .info { color: #0066cc; font-weight: bold; }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin: 10px 0;
        }
        button:hover { background: #5867f2; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th { background: #f5f7fb; font-weight: bold; }
        table tr:hover { background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Analytics Debug Console</h1>
        <p>Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo ucfirst($user['role']); ?>)</p>
        
        <button onclick="testAnalyticsAPI()">🔍 Test Analytics API</button>
        <button onclick="testDatabase()">🗄️ Test Database</button>
        <button onclick="location.href='<?php echo baseUrl(); ?>/?page=analytics'">↩️ Back to Analytics</button>

        <h2>Database Statistics</h2>
        <div id="dbStats"></div>

        <h2>API Response</h2>
        <div id="apiResponse"></div>

        <h2>Forecast Data Table</h2>
        <div id="forecastTable"></div>
    </div>

    <script>
        function testDatabase() {
            const output = document.getElementById('dbStats');
            output.innerHTML = '<div class="info">Loading...</div>';
            
            fetch('<?php echo baseUrl(); ?>/api.php?route=/api/health')
                .then(r => r.json())
                .then(data => {
                    output.innerHTML = '<div class="success">✓ Database Connected</div><div class="debug-box">' + 
                        JSON.stringify(data, null, 2) + '</div>';
                })
                .catch(err => {
                    output.innerHTML = '<div class="error">✗ Database Error</div><div class="debug-box">' + 
                        err.message + '</div>';
                });
        }

        function testAnalyticsAPI() {
            const output = document.getElementById('apiResponse');
            const tableOutput = document.getElementById('forecastTable');
            output.innerHTML = '<div class="info">Loading...</div>';
            tableOutput.innerHTML = '';
            
            fetch('<?php echo baseUrl(); ?>/api.php?route=/api/analytics&trend_days=90&horizon=7')
                .then(r => r.json())
                .then(data => {
                    // Handle both response formats (with and without 'data' wrapper)
                    const apiData = data.data ? data.data : data;
                    if ((data.data || data.forecast_rows) && (apiData.forecast_rows || apiData.status)) {
                        output.innerHTML = '<div class="success">✓ API Response Received</div><div class="debug-box">' + 
                            JSON.stringify({
                                status: 'success',
                                forecast_rows_count: apiData.forecast_rows?.length || 0,
                                total_visits_all: apiData.total_visits_all,
                                forecast_stats: apiData.forecast_stats,
                                ent_distribution: apiData.ent_distribution
                            }, null, 2) + '</div>';
                        
                        // Build forecast table
                        if (apiData.forecast_rows && apiData.forecast_rows.length > 0) {
                            let html = '<table><thead><tr>' +
                                '<th>Date</th><th>Day</th><th>SF</th><th>Base</th><th>Forecast</th>' +
                                '</tr></thead><tbody>';
                            
                            apiData.forecast_rows.forEach(row => {
                                html += '<tr>' +
                                    '<td>' + row.date + '</td>' +
                                    '<td>' + row.label + '</td>' +
                                    '<td>' + row.sf + '</td>' +
                                    '<td>' + row.base + '</td>' +
                                    '<td><strong>' + row.value + '</strong></td>' +
                                    '</tr>';
                            });
                            html += '</tbody></table>';
                            tableOutput.innerHTML = html;
                        } else {
                            tableOutput.innerHTML = '<div class="warning">⚠️ No forecast rows generated</div>';
                        }
                    } else {
                        output.innerHTML = '<div class="error">✗ API Error</div><div class="debug-box">' + 
                            JSON.stringify(data, null, 2) + '</div>';
                    }
                })
                .catch(err => {
                    output.innerHTML = '<div class="error">✗ Request Failed</div><div class="debug-box">' + 
                        err.message + '</div>';
                });
        }

        // Auto-run on load
        window.addEventListener('load', () => {
            testDatabase();
            setTimeout(() => testAnalyticsAPI(), 500);
        });
    </script>
</body>
</html>
