<?php
/**
 * Database Connection Test Script
 * Use this to diagnose database connection issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #004085; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1>🔍 Database Connection Test</h1>";

// Load configuration
require_once __DIR__ . '/../config/config.php';

echo "<h2>Configuration</h2>";
echo "<div class='info'>";
echo "<strong>Environment:</strong> " . ENV . "<br>";
echo "<strong>Host:</strong> " . DB_CONFIG['host'] . "<br>";
echo "<strong>Port:</strong> " . DB_CONFIG['port'] . "<br>";
echo "<strong>Database:</strong> " . DB_CONFIG['name'] . "<br>";
echo "<strong>User:</strong> " . DB_CONFIG['user'] . "<br>";
echo "<strong>Password:</strong> " . (empty(DB_CONFIG['password']) ? '(empty)' : '***') . "<br>";
echo "</div>";

// Test 1: Check if MySQL extension is loaded
echo "<h2>Test 1: PHP MySQL Extension</h2>";
if (extension_loaded('pdo_mysql')) {
    echo "<div class='success'>✅ PDO MySQL extension is loaded</div>";
} else {
    echo "<div class='error'>❌ PDO MySQL extension is NOT loaded</div>";
    echo "<div class='warning'>Please enable the pdo_mysql extension in php.ini</div>";
}

// Test 2: Try to connect to MySQL server (without database)
echo "<h2>Test 2: MySQL Server Connection</h2>";
try {
    $dsn = "mysql:host=" . DB_CONFIG['host'] . ";port=" . DB_CONFIG['port'];
    $pdo = new PDO($dsn, DB_CONFIG['user'], DB_CONFIG['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "<div class='success'>✅ Successfully connected to MySQL server</div>";
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<div class='info'><strong>MySQL Version:</strong> $version</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Failed to connect to MySQL server</div>";
    echo "<div class='error'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    
    if (strpos($e->getMessage(), '2002') !== false || strpos($e->getMessage(), 'actively refused') !== false) {
        echo "<div class='warning'>";
        echo "<strong>🔧 Troubleshooting Steps:</strong><br>";
        echo "1. Open XAMPP Control Panel<br>";
        echo "2. Make sure MySQL service is <strong>Running</strong> (click 'Start' if it's not)<br>";
        echo "3. Check if port " . DB_CONFIG['port'] . " is available<br>";
        echo "4. Try restarting XAMPP if the issue persists<br>";
        echo "5. Check Windows Firewall or antivirus blocking the connection<br>";
        echo "</div>";
    }
    exit;
}

// Test 3: Check if database exists
echo "<h2>Test 3: Database Existence</h2>";
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_CONFIG['name'] . "'");
    $dbExists = $stmt->rowCount() > 0;
    
    if ($dbExists) {
        echo "<div class='success'>✅ Database '" . DB_CONFIG['name'] . "' exists</div>";
    } else {
        echo "<div class='warning'>⚠️ Database '" . DB_CONFIG['name'] . "' does NOT exist</div>";
        echo "<div class='info'>";
        echo "<strong>To create the database:</strong><br>";
        echo "1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>";
        echo "2. Click 'New' to create a database<br>";
        echo "3. Name it: <strong>" . DB_CONFIG['name'] . "</strong><br>";
        echo "4. Or run the schema.sql file from the database folder<br>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error checking database: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 4: Try to connect to the specific database
echo "<h2>Test 4: Database Connection</h2>";
try {
    require_once __DIR__ . '/../config/Database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "<div class='success'>✅ Successfully connected to database '" . DB_CONFIG['name'] . "'</div>";
    
    // Test query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '" . DB_CONFIG['name'] . "'");
    $tableCount = $stmt->fetch()['count'];
    echo "<div class='info'><strong>Tables in database:</strong> $tableCount</div>";
    
    // Check for required tables
    $requiredTables = ['users', 'patients', 'patient_visits'];
    $existingTables = [];
    foreach ($requiredTables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
        }
    }
    
    if (count($existingTables) === count($requiredTables)) {
        echo "<div class='success'>✅ All required tables exist</div>";
    } else {
        $missing = array_diff($requiredTables, $existingTables);
        echo "<div class='warning'>⚠️ Missing tables: " . implode(', ', $missing) . "</div>";
        echo "<div class='info'>Run the schema.sql file to create missing tables</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Failed to connect to database</div>";
    echo "<div class='error'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 5: List available databases
echo "<h2>Test 5: Available Databases</h2>";
try {
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'>";
    echo "<strong>Available databases:</strong><br>";
    echo "<ul>";
    foreach ($databases as $db) {
        if (!in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
            echo "<li>$db</li>";
        }
    }
    echo "</ul>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div class='error'>Could not list databases: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<div class='info'>";
echo "<strong>Next Steps:</strong><br>";
echo "1. If MySQL is not running, start it from XAMPP Control Panel<br>";
echo "2. If database doesn't exist, create it using phpMyAdmin or run schema.sql<br>";
echo "3. If connection works, you can delete this test file (test-db-connection.php)<br>";
echo "</div>";

echo "</body></html>";
?>

