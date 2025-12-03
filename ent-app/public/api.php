<?php
/**
 * API Entry Point
 * Handles all API requests
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../api/Router.php';
require_once __DIR__ . '/../api/PatientsController.php';
require_once __DIR__ . '/../api/VisitsController.php';
require_once __DIR__ . '/../api/AnalyticsController.php';
require_once __DIR__ . '/../api/AuthController.php';
require_once __DIR__ . '/../api/MedicinesController.php';
require_once __DIR__ . '/../api/PrescriptionController.php';

$router = new Router();

// Patients endpoints
$router->get('/api/patients', function () {
    (new PatientsController())->index();
});

$router->get('/api/patients/:id', function ($id) {
    (new PatientsController())->show($id);
});

$router->post('/api/patients', function () {
    (new PatientsController())->store();
});

$router->put('/api/patients/:id', function ($id) {
    (new PatientsController())->update($id);
});

$router->delete('/api/patients/:id', function ($id) {
    (new PatientsController())->delete($id);
});

// Patient Visits endpoints
$router->get('/api/visits', function () {
    (new VisitsController())->index();
});

$router->get('/api/visits/:id', function ($id) {
    (new VisitsController())->show($id);
});

$router->post('/api/visits', function () {
    (new VisitsController())->store();
});

$router->put('/api/visits/:id', function ($id) {
    (new VisitsController())->update($id);
});

$router->delete('/api/visits/:id', function ($id) {
    (new VisitsController())->delete($id);
});

// Medicines endpoints
$router->get('/api/medicines', function () {
    (new MedicinesController())->index();
});

$router->post('/api/medicines', function () {
    (new MedicinesController())->store();
});

// Prescription endpoints
$router->post('/api/prescription/export', function () {
    (new PrescriptionController())->export();
});

// Health check
$router->get('/api/health', function () {
    try {
        $db = Database::getInstance();
        echo json_encode([
            'status' => 'ok',
            'message' => 'ENT Clinic API is running',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => 'connected'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed',
            'error' => ENV === 'development' ? $e->getMessage() : 'Internal server error'
        ]);
    }
});

// Analytics endpoint
$router->get('/api/analytics', function () {
    (new AnalyticsController())->index();
});

// Auth endpoints
$router->post('/api/auth/login', function () {
    (new AuthController())->login();
});

$router->post('/api/auth/logout', function () {
    (new AuthController())->logout();
});

$router->get('/api/auth/me', function () {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    header('Content-Type: application/json');
    if (!empty($_SESSION['user'])) {
        echo json_encode(['success' => true, 'data' => $_SESSION['user']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
    }
    exit;
});

$router->dispatch();

