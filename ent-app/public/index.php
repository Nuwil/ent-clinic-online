<?php
/**
 * Main Entry Point - PHP Frontend
 */

// Start session
session_start();

// Include helpers
require_once __DIR__ . '/includes/helpers.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $page = $_POST['page'] ?? getCurrentPage();
    
    // Handle patient actions
    if ($action === 'add_patient' || $action === 'update_patient') {
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'medical_history' => $_POST['medical_history'] ?? ''
        ];
        
        if ($action === 'add_patient') {
            $result = apiCall('POST', '/patients', $data);
                if ($result) {
                    $_SESSION['message'] = 'Patient added successfully';
                } else {
                    $err = $_SESSION['api_last_error'] ?? null;
                    if ($err) {
                        if (is_array($err)) {
                            if (isset($err['error'])) $msg = is_array($err['error']) ? json_encode($err['error']) : $err['error'];
                            elseif (isset($err['message'])) $msg = $err['message'];
                            else $msg = json_encode($err);
                        } else {
                            $msg = (string)$err;
                        }
                        $_SESSION['message'] = 'Failed to add patient: ' . $msg;
                    } else {
                        $_SESSION['message'] = 'Failed to add patient';
                    }
                }
        } else {
            $id = $_POST['id'] ?? '';
            $result = apiCall('PUT', '/patients/' . $id, $data);
            if ($result) {
                $_SESSION['message'] = 'Patient updated successfully';
            } else {
                $err = $_SESSION['api_last_error'] ?? null;
                $_SESSION['message'] = $err ? 'Failed to update patient: ' . (is_array($err) ? json_encode($err) : (string)$err) : 'Failed to update patient';
            }
        }
        redirect('/?page=patients');
    }
    
    // Handle delete patient
    if ($action === 'delete_patient') {
        $id = $_POST['id'] ?? '';
        $result = apiCall('DELETE', '/patients/' . $id);
        if ($result) {
            $_SESSION['message'] = 'Patient deleted successfully';
        } else {
            $err = $_SESSION['api_last_error'] ?? null;
            $_SESSION['message'] = $err ? 'Failed to delete patient: ' . (is_array($err) ? json_encode($err) : (string)$err) : 'Failed to delete patient';
        }
        redirect('/?page=patients');
    }
    
    // Handle visit actions
    if ($action === 'add_visit' || $action === 'update_visit') {
        $data = [
            'patient_id' => isset($_POST['patient_id']) ? $_POST['patient_id'] : '',
            'visit_date' => isset($_POST['visit_date']) ? $_POST['visit_date'] : date('Y-m-d\TH:i'),
            'visit_type' => isset($_POST['visit_type']) ? $_POST['visit_type'] : '',
            'ent_type' => isset($_POST['ent_type']) ? $_POST['ent_type'] : 'ear',
            'chief_complaint' => isset($_POST['chief_complaint']) ? $_POST['chief_complaint'] : '',
            'diagnosis' => isset($_POST['diagnosis']) ? $_POST['diagnosis'] : '',
            'treatment_plan' => isset($_POST['treatment_plan']) ? $_POST['treatment_plan'] : '',
            'prescription' => isset($_POST['prescription']) ? $_POST['prescription'] : '',
            'notes' => isset($_POST['notes']) ? $_POST['notes'] : ''
        ];
        
        if ($action === 'add_visit') {
            $result = apiCall('POST', '/visits', $data);
            $_SESSION['message'] = $result ? 'Visit added successfully' : 'Failed to add visit';
        } else {
            $id = isset($_POST['id']) ? $_POST['id'] : '';
            $result = apiCall('PUT', '/visits/' . $id, $data);
            $_SESSION['message'] = $result ? 'Visit updated successfully' : 'Failed to update visit';
        }
        $patientId = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
        redirect('/?page=patient-profile&id=' . $patientId);
    }
    
    // Handle delete visit
    if ($action === 'delete_visit') {
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $patientId = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
        $result = apiCall('DELETE', '/visits/' . $id);
        $_SESSION['message'] = $result ? 'Visit deleted successfully' : 'Failed to delete visit';
        redirect('/?page=patient-profile&id=' . $patientId);
    }
    
    // Handle update patient profile
    if ($action === 'update_patient_profile') {
        $data = [
            'first_name' => isset($_POST['first_name']) ? $_POST['first_name'] : '',
            'last_name' => isset($_POST['last_name']) ? $_POST['last_name'] : '',
            'email' => isset($_POST['email']) ? $_POST['email'] : '',
            'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
            'address' => isset($_POST['address']) ? $_POST['address'] : '',
            'city' => isset($_POST['city']) ? $_POST['city'] : '',
            'state' => isset($_POST['state']) ? $_POST['state'] : '',
            'postal_code' => isset($_POST['postal_code']) ? $_POST['postal_code'] : '',
            'country' => isset($_POST['country']) ? $_POST['country'] : '',
            'occupation' => isset($_POST['occupation']) ? $_POST['occupation'] : '',
            'date_of_birth' => isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '',
            'gender' => isset($_POST['gender']) ? $_POST['gender'] : '',
            'medical_history' => isset($_POST['medical_history']) ? $_POST['medical_history'] : ''
        ];
        
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $result = apiCall('PUT', '/patients/' . $id, $data);
        $_SESSION['message'] = $result ? 'Patient information updated successfully' : 'Failed to update patient information';
        redirect('/?page=patient-profile&id=' . $id);
    }
}

// Get current page
$currentPage = getCurrentPage();

// Include header
include __DIR__ . '/includes/header.php';

// Display message if any
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">' . e($_SESSION['message']) . '</div>';
    unset($_SESSION['message']);
    if (isset($_SESSION['api_last_error'])) unset($_SESSION['api_last_error']);
}

// Include the appropriate page
$pageFile = __DIR__ . '/pages/' . $currentPage . '.php';
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    include __DIR__ . '/pages/patients.php';
}

// Include footer
include __DIR__ . '/includes/footer.php';
