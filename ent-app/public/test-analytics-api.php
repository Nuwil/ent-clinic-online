<?php
/**
 * Local test to fetch analytics API and inspect ent_distribution
 */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../api/Controller.php';
require_once __DIR__ . '/../api/AnalyticsController.php';

// Simulate a session user (doctor/admin role for auth)
if (session_status() === PHP_SESSION_NONE) @session_start();
$_SESSION['user'] = [
    'id' => 1,
    'role' => 'doctor',
    'name' => 'Test Doctor'
];

// Simulate GET request params for "all time"
$_GET['start_date'] = '2000-01-01';
$_GET['end_date'] = date('Y-m-d');

// Instantiate controller and call index()
$controller = new AnalyticsController();
$controller->index();
?>
