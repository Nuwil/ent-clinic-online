<?php
/**
 * Test Login Flow Debug
 */
session_start();

echo "<h2>Session Debug</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "SESSION Contents:\n";
var_dump($_SESSION);
echo "</pre>";

echo "<h2>GET/POST Debug</h2>";
echo "<pre>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "GET Data:\n";
var_dump($_GET);
echo "POST Data:\n";
var_dump($_POST);
echo "</pre>";

// Simulate what happens when user clicks login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    echo "<h2>Simulating Login...</h2>";
    $_SESSION['user'] = [
        'id' => 1,
        'username' => 'admin',
        'email' => 'admin@entclinic.com',
        'name' => 'Administrator',
        'role' => 'admin',
        'full_name' => 'Administrator'
    ];
    
    echo "<p>Session set. Current SESSION:</p>";
    echo "<pre>";
    var_dump($_SESSION);
    echo "</pre>";
    
    echo "<p>Now redirecting to: <strong>?page=settings</strong></p>";
    header('Location: ?page=settings');
    exit;
}

// Check if session has user
if (isset($_SESSION['user'])) {
    echo "<h2>✓ User is authenticated!</h2>";
    echo "<pre>";
    var_dump($_SESSION['user']);
    echo "</pre>";
    
    echo "<p>Current page parameter: " . ($_GET['page'] ?? 'NOT SET') . "</p>";
    echo "<p><a href='?page=settings'>Go to Settings</a></p>";
    echo "<p><a href='?page=patients'>Go to Patients</a></p>";
} else {
    echo "<h2>✗ User is NOT authenticated</h2>";
    echo "<p>
        <form method='POST'>
            <button type='submit' name='test_login' value='1'>Test Login (set session)</button>
        </form>
    </p>";
}

?>
