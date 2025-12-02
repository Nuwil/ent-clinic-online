<?php
session_start();

// Set some data in the session
$_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
$_SESSION['test'] = 'this is a test';

echo "Session name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session save path: " . ini_get('session.save_path') . "\n";
echo "Session status: " . session_status() . "\n";

// Try to check if session file exists
$sessionPath = ini_get('session.save_path');
if (empty($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
}
$sessionFile = $sessionPath . '/sess_' . session_id();
echo "Expected session file: $sessionFile\n";
echo "Session file exists: " . (file_exists($sessionFile) ? 'YES' : 'NO') . "\n";

// Check current session content
echo "\nSession contents:\n";
var_dump($_SESSION);
