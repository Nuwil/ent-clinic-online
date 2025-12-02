<?php
/**
 * Simple login test
 */
session_start();

// Clear any existing session for fresh test
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

echo "<h1>Login Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h2>Login Attempt</h2>";
    echo "Username: $username<br>";
    echo "Password: " . str_repeat('*', strlen($password)) . "<br>";
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE (username = ? OR email = ?) LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "<p style='color:red;'>❌ User not found</p>";
        } else {
            echo "<p>✓ User found: {$user['full_name']} ({$user['role']})</p>";
            
            $verify = password_verify($password, $user['password_hash']);
            echo "<p>Password verify: " . ($verify ? "✓ PASS" : "❌ FAIL") . "</p>";
            
            if ($verify && $user['is_active']) {
                echo "<p style='color:green;'><strong>✓ Login successful!</strong></p>";
                
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name']
                ];
                
                echo "<p>Session set. Redirecting in 2 seconds...</p>";
                echo "<p><a href='?'>Redirect now</a></p>";
                header('Refresh: 2; url=/ENT-clinic-online/ent-app/public/?page=patients');
            }
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Current session:</p>";
    echo "<pre>";
    var_dump($_SESSION);
    echo "</pre>";
}

?>

<hr>
<h2>Test Login Form</h2>
<form method="POST">
    <p>
        <label>Username: <input type="text" name="username" value="admin"></label>
    </p>
    <p>
        <label>Password: <input type="password" name="password" value="admin123"></label>
    </p>
    <p>
        <button type="submit">Test Login</button>
        <a href="?logout=1">Clear Session</a>
    </p>
</form>

<hr>
<h2>User Accounts in DB:</h2>
<?php
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SELECT id, username, email, full_name, role, is_active FROM users ORDER BY id');
    $users = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Role</th><th>Active</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['username']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['full_name']}</td>";
        echo "<td>{$u['role']}</td>";
        echo "<td>{$u['is_active']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
