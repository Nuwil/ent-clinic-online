<?php
session_start();
echo "<h2>Session Status</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "User in session: " . (isset($_SESSION['user']) ? 'YES' : 'NO') . "<br>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

echo "<h2>Request Info</h2>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "<br>";

if (isset($_SESSION['user'])) {
    echo "<h2>✓ Authenticated!</h2>";
    echo "Role: " . $_SESSION['user']['role'] . "<br>";
    echo "<p><a href='/?page=patients'>Go to Patients</a></p>";
    echo "<p><a href='/?page=settings'>Go to Settings</a></p>";
    echo "<p><a href='/'>Home</a></p>";
} else {
    echo "<h2>✗ Not authenticated</h2>";
    echo "<p><a href='/'>Go to login</a></p>";
}
?>
