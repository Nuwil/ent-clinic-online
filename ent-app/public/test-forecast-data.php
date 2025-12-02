<?php
/**
 * Diagnostic Script - Check Forecast Data Issues
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

session_start();

// Check if authenticated
if (!isset($_SESSION['user'])) {
    die('<h2>❌ Not authenticated</h2><p><a href="/">Go to login</a></p>');
}

$user = $_SESSION['user'];
if (!in_array($user['role'] ?? '', ['admin', 'doctor'])) {
    die('<h2>❌ Unauthorized - Admin/Doctor only</h2>');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forecast Data Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f7fb; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .section { margin: 30px 0; border-left: 4px solid #667eea; padding-left: 15px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #d97706; font-weight: bold; }
        .info { color: #0066cc; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background: #f5f7fb; font-weight: bold; }
        table tr:hover { background: #f9f9f9; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        button { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin: 10px 0; }
        button:hover { background: #5867f2; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Forecast Data Diagnostic Tool</h1>
        <p>Logged in as: <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo ucfirst($user['role']); ?>)</p>
        
        <div class="section">
            <h2>1️⃣ Database Connection Status</h2>
            <?php
            try {
                $db = Database::getInstance()->getConnection();
                echo '<p class="success">✓ Database connected successfully</p>';
            } catch (Exception $e) {
                echo '<p class="error">✗ Database connection failed: ' . $e->getMessage() . '</p>';
                die();
            }
            ?>
        </div>

        <div class="section">
            <h2>2️⃣ Patient Records Count</h2>
            <?php
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
                $result = $stmt->fetch();
                $patientCount = $result['count'] ?? 0;
                
                if ($patientCount > 0) {
                    echo '<p class="success">✓ ' . $patientCount . ' patients found</p>';
                } else {
                    echo '<p class="warning">⚠️ No patients found - need to add patients first</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>

        <div class="section">
            <h2>3️⃣ Patient Visits Count & Data</h2>
            <?php
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM patient_visits");
                $result = $stmt->fetch();
                $visitCount = $result['count'] ?? 0;
                
                if ($visitCount > 0) {
                    echo '<p class="success">✓ ' . $visitCount . ' visits found</p>';
                    
                    // Show visit details
                    echo '<h3>Recent Visits:</h3>';
                    $stmt = $db->query("
                        SELECT pv.*, p.first_name, p.last_name 
                        FROM patient_visits pv 
                        JOIN patients p ON pv.patient_id = p.id 
                        ORDER BY pv.visit_date DESC 
                        LIMIT 10
                    ");
                    $visits = $stmt->fetchAll();
                    
                    echo '<table>';
                    echo '<tr><th>Date</th><th>Patient</th><th>Type</th><th>ENT Type</th></tr>';
                    foreach ($visits as $v) {
                        $date = date('Y-m-d H:i', strtotime($v['visit_date']));
                        $patient = $v['first_name'] . ' ' . $v['last_name'];
                        $type = $v['visit_type'] ?? 'N/A';
                        $entType = $v['ent_type'] ?? 'N/A';
                        echo "<tr><td>$date</td><td>$patient</td><td>$type</td><td>$entType</td></tr>";
                    }
                    echo '</table>';
                } else {
                    echo '<p class="error">✗ No visits found - this is the problem!</p>';
                    echo '<p class="info">💡 Solution: Add at least one visit to a patient</p>';
                    echo '<ol>';
                    echo '<li>Go to Patients page</li>';
                    echo '<li>Click on a patient name</li>';
                    echo '<li>Click "Add Visit"</li>';
                    echo '<li>Fill in the form and save</li>';
                    echo '<li>Return to Analytics</li>';
                    echo '</ol>';
                }
            } catch (Exception $e) {
                echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>

        <div class="section">
            <h2>4️⃣ Daily Visit Counts (Last 30 Days)</h2>
            <?php
            try {
                $today = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime('-30 days'));
                
                $stmt = $db->prepare("
                    SELECT DATE(visit_date) as visit_day, COUNT(*) as count
                    FROM patient_visits
                    WHERE DATE(visit_date) >= ?
                    GROUP BY DATE(visit_date)
                    ORDER BY visit_day DESC
                ");
                $stmt->execute([$startDate]);
                $dailyData = $stmt->fetchAll();
                
                if (count($dailyData) > 0) {
                    echo '<p class="success">✓ ' . count($dailyData) . ' days with visits</p>';
                    echo '<table>';
                    echo '<tr><th>Date</th><th>Visit Count</th></tr>';
                    foreach ($dailyData as $d) {
                        echo "<tr><td>{$d['visit_day']}</td><td>{$d['count']}</td></tr>";
                    }
                    echo '</table>';
                } else {
                    echo '<p class="warning">⚠️ No visits in last 30 days</p>';
                    echo '<p class="info">Add recent visits for forecast to work</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>

        <div class="section">
            <h2>5️⃣ Weekly Seasonality Check</h2>
            <?php
            try {
                $stmt = $db->query("
                    SELECT DAYOFWEEK(visit_date) as dow, COUNT(*) as count
                    FROM patient_visits
                    GROUP BY DAYOFWEEK(visit_date)
                    ORDER BY dow
                ");
                $weeklyData = $stmt->fetchAll();
                
                $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                
                if (count($weeklyData) > 0) {
                    echo '<p class="success">✓ Visits detected by day of week</p>';
                    echo '<table>';
                    echo '<tr><th>Day</th><th>Visit Count</th></tr>';
                    foreach ($weeklyData as $w) {
                        $dow = $w['dow'];
                        $count = $w['count'];
                        $dayName = $dayNames[$dow] ?? 'Unknown';
                        echo "<tr><td>$dayName ($dow)</td><td>$count</td></tr>";
                    }
                    echo '</table>';
                } else {
                    echo '<p class="warning">⚠️ No visits found for seasonality</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>

        <div class="section">
            <h2>6️⃣ API Response Test</h2>
            <p>Click the button below to test the Analytics API:</p>
            <button onclick="testAPI()">🧪 Test Analytics API</button>
            <div id="apiResult"></div>
        </div>

        <div class="section">
            <h2>7️⃣ Troubleshooting Summary</h2>
            <?php
            $issues = [];
            
            // Check patients
            $patientStmt = $db->query("SELECT COUNT(*) as count FROM patients");
            $patientResult = $patientStmt->fetch();
            if (($patientResult['count'] ?? 0) == 0) {
                $issues[] = ['level' => 'CRITICAL', 'issue' => 'No patients', 'fix' => 'Add patients first'];
            }
            
            // Check visits
            $visitStmt = $db->query("SELECT COUNT(*) as count FROM patient_visits");
            $visitResult = $visitStmt->fetch();
            if (($visitResult['count'] ?? 0) == 0) {
                $issues[] = ['level' => 'CRITICAL', 'issue' => 'No visits', 'fix' => 'Add visits to patients'];
            }
            
            if (count($issues) == 0) {
                echo '<p class="success">✓ All data requirements met</p>';
                echo '<p class="info">If forecast still shows 0, use debug console to inspect API response</p>';
            } else {
                echo '<h3>Issues Found:</h3>';
                foreach ($issues as $issue) {
                    echo '<div class="' . strtolower($issue['level']) . '">';
                    echo '⚠️ ' . $issue['issue'] . ' → ' . $issue['fix'];
                    echo '</div><br>';
                }
            }
            ?>
        </div>

        <div style="margin-top: 20px;">
            <button onclick="window.location.href='/?page=analytics'">← Back to Analytics</button>
            <button onclick="window.location.href='/?page=patients'">→ Go to Patients</button>
        </div>
    </div>

    <script>
        function testAPI() {
            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<p style="color: #0066cc;">🔄 Testing API...</p>';
            
            fetch('<?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http'; ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/ENT-clinic-online/ent-app/public/api.php?route=/api/analytics&horizon=7&trend_days=90')
                .then(r => r.json())
                .then(data => {
                    // Handle both response formats
                    const apiData = data.data ? data.data : data;
                    if (apiData && (apiData.forecast_rows || apiData.status === 'success' || apiData.ent_distribution)) {
                        const forecastCount = apiData.forecast_rows?.length || 0;
                        const totalVisits = apiData.total_visits_all || 0;
                        const baseLevel = apiData.forecast_stats?.baseLevel || 0;
                        
                        let html = '<div style="background: #f0fdf4; padding: 15px; border-radius: 6px; margin-top: 10px;">';
                        html += '<p style="color: #10b981; font-weight: bold;">✓ API Response Successful</p>';
                        html += '<p><strong>Total Visits:</strong> ' + totalVisits + '</p>';
                        html += '<p><strong>Base Level:</strong> ' + baseLevel.toFixed(2) + '</p>';
                        html += '<p><strong>Forecast Rows:</strong> ' + forecastCount + '</p>';
                        
                        if (forecastCount > 0) {
                            html += '<h4>Forecast Data (First 3):</h4><table style="width: 100%; border-collapse: collapse;">';
                            html += '<tr style="background: #f5f5f5;"><th style="border: 1px solid #ddd; padding: 8px;">Date</th><th style="border: 1px solid #ddd; padding: 8px;">Day</th><th style="border: 1px solid #ddd; padding: 8px;">Value</th></tr>';
                            for (let i = 0; i < Math.min(3, forecastCount); i++) {
                                const row = apiData.forecast_rows[i];
                                html += '<tr><td style="border: 1px solid #ddd; padding: 8px;">' + row.date + '</td>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px;">' + row.label + '</td>';
                                html += '<td style="border: 1px solid #ddd; padding: 8px;"><strong>' + row.value + '</strong></td></tr>';
                            }
                            html += '</table>';
                        } else {
                            html += '<p style="color: #d97706;">⚠️ No forecast rows generated - check data</p>';
                        }
                        html += '</div>';
                        resultDiv.innerHTML = html;
                    } else {
                        resultDiv.innerHTML = '<div style="background: #fee; padding: 15px; border-radius: 6px; color: #c33;"><p>❌ API Error</p><pre>' + JSON.stringify(data, null, 2) + '</pre></div>';
                    }
                })
                .catch(err => {
                    resultDiv.innerHTML = '<div style="background: #fee; padding: 15px; border-radius: 6px; color: #c33;"><p>❌ Request Failed</p><p>' + err.message + '</p></div>';
                });
        }
    </script>
</body>
</html>
