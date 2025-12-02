<?php
/**
 * Debug Login - Test credentials and database
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "<h2>Debug Info</h2>";
    
    // Get all users
    $stmt = $db->prepare('SELECT id, username, email, password_hash, full_name, role, is_active FROM users');
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<h3>Users in Database:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th><th>Password Hash</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>{$user['is_active']}</td>";
        echo "<td><code>" . substr($user['password_hash'], 0, 20) . "...</code></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Test Credentials:</h3>";
    
    // Test admin login
    $testCreds = [
        'admin' => 'admin123',
        'doctor_demo' => 'password',
        'staff_demo' => 'password'
    ];
    
    foreach ($testCreds as $username => $password) {
        echo "<h4>Testing: $username / $password</h4>";
        
        $stmt = $db->prepare('SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "<p style='color: red;'>❌ User not found</p>";
        } else {
            echo "<p>User found: {$user['full_name']} ({$user['role']})</p>";
            echo "<p>Is Active: {$user['is_active']}</p>";
            echo "<p>Password Hash: <code>" . $user['password_hash'] . "</code></p>";
            
            $verify = password_verify($password, $user['password_hash']);
            echo "<p>Password Verify Result: <strong>" . ($verify ? "✓ PASS" : "✗ FAIL") . "</strong></p>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
}

?>
