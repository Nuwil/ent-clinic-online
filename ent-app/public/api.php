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
require_once __DIR__ . '/../api/AuthController.php';
require_once __DIR__ . '/../api/MedicinesController.php';
require_once __DIR__ . '/../api/PrescriptionController.php';
require_once __DIR__ . '/../api/AppointmentsController.php';
require_once __DIR__ . '/../api/WaitlistController.php';
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
// Fetch prescription items for timeline UI
$router->get('/api/prescription/items', function () {
    (new PrescriptionController())->items();
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

// Appointments endpoints
$router->get('/api/appointments', function () {
    (new AppointmentsController())->index();
});

$router->post('/api/appointments', function () {
    (new AppointmentsController())->create();
});

$router->post('/api/appointments/:id/accept', function ($id) {
    (new AppointmentsController())->accept($id);
});

$router->post('/api/appointments/:id/complete', function ($id) {
    (new AppointmentsController())->complete($id);
});

$router->put('/api/appointments/:id/reschedule', function ($id) {
    (new AppointmentsController())->reschedule($id);
});

$router->post('/api/appointments/:id/cancel', function ($id) {
    (new AppointmentsController())->cancel($id);
});

$router->get('/api/appointments/slots', function () {
    (new AppointmentsController())->slots();
});

$router->get('/api/doctors', function () {
    (new AppointmentsController())->doctors();
});

// Waitlist endpoints
$router->get('/api/waitlist', function () {
    (new WaitlistController())->index();
});

// Analytics endpoints
$router->get('/api/analytics', function () {
    (new AnalyticsController())->index();
});

$router->post('/api/waitlist', function () {
    (new WaitlistController())->add();
});

$router->delete('/api/waitlist/:id', function ($id) {
    (new WaitlistController())->remove($id);
});

$router->post('/api/waitlist/:id/notify', function ($id) {
    (new WaitlistController())->notify($id);
});

$router->dispatch();

