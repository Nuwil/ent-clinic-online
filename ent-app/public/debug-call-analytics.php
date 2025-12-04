<?php
require_once __DIR__ . '/../api/AnalyticsController.php';
// Start session and set dummy admin user
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user'] = ['id'=>1,'role'=>'admin'];

// Simulate GET params
$_GET['start_date'] = '2025-12-01';
$_GET['end_date'] = '2025-12-04';

$ctrl = new AnalyticsController();
$ctrl->index();
?>