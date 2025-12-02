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

$router->dispatch();

