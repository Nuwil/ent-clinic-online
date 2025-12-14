<?php
/**
 * Main Entry Point - PHP Frontend
 */

// Start session
session_start();

// Include helpers
require_once __DIR__ . '/includes/helpers.php';

// Handle POST request for login FIRST, before any authentication check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/Database.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginError = null;
    
    if (empty($username) || empty($password)) {
        $loginError = 'Username and password required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE (username = ? OR email = ?) LIMIT 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $loginError = 'Invalid username or password';
            } elseif (!$user['is_active']) {
                $loginError = 'Account is disabled';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $loginError = 'Invalid username or password';
            } else {
                // ✓ LOGIN SUCCESSFUL
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'name' => $user['full_name'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name']
                ];
                
                // Determine landing page by role - redirect to role-specific dashboard
                $redirectPage = getDashboardForRole($user['role']);
                
                // Send redirect header and STOP execution
                header('Location: /ENT-clinic-online/ent-app/public/?page=' . $redirectPage);
                exit;
            }
        } catch (Exception $e) {
            $loginError = 'Login error: ' . $e->getMessage();
        }
    }
    
    // Login failed - fall through to show login page with error
}

// NOW check if user is authenticated
if (!isset($_SESSION['user'])) {
    // User not logged in - show login page
    include __DIR__ . '/pages/login.php';
    exit;
}

// Check page access control - ensure user can access the requested page
$currentPage = getCurrentPage();
if (!canAccessPage($currentPage)) {
    // User doesn't have access to this page - redirect to their dashboard
    $userRole = getCurrentUserRole();
    $dashboard = getDashboardForRole($userRole);
    $_SESSION['message'] = 'You do not have permission to access this page.';
    header('Location: /ENT-clinic-online/ent-app/public/?page=' . $dashboard);
    exit;
}

// User is authenticated - get current page
$currentPage = getCurrentPage();
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
            'medical_history' => $_POST['medical_history'] ?? '',
            'allergies' => $_POST['allergies'] ?? '',
            'vaccine_history' => $_POST['vaccine_history'] ?? '',
            'insurance_provider' => $_POST['insurance_provider'] ?? '',
            'insurance_id' => $_POST['insurance_id'] ?? '',
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? '',
            'occupation' => $_POST['occupation'] ?? '',
            'height' => isset($_POST['height']) ? $_POST['height'] : null,
            'weight' => isset($_POST['weight']) ? $_POST['weight'] : null,
            'bmi' => isset($_POST['bmi']) ? $_POST['bmi'] : null,
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'state' => $_POST['state'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? '',
            'country' => $_POST['country'] ?? '',
            'allergies' => $_POST['allergies'] ?? ''
        ];
        
        if ($action === 'add_patient') {
            $result = apiCall('POST', '/api/patients', $data);
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
            $result = apiCall('PUT', '/api/patients/' . $id, $data);
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
        $result = apiCall('DELETE', '/api/patients/' . $id);
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
        $currentUser = $_SESSION['user'] ?? null;

        // For updates require admin/doctor. For creation allow secretary (staff) to add limited info.
        if ($action === 'update_visit') {
            $allowedRoles = ['admin', 'doctor'];
        } else {
            // add_visit
            $allowedRoles = ['admin', 'doctor', 'staff'];
        }

        if (!($currentUser && in_array($currentUser['role'] ?? '', $allowedRoles))) {
            $_SESSION['message'] = 'Unauthorized: insufficient permissions to add or edit visits.';
            $patientId = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
            redirect('/?page=patient-profile&id=' . $patientId);
        }

        // Normalize visit_date: interpret incoming datetime-local as Asia/Manila,
        // convert to UTC before sending to the API so stored datetimes are consistent.
        $rawVisitDate = isset($_POST['visit_date']) ? $_POST['visit_date'] : null;
        if ($rawVisitDate) {
            try {
                $dt = new DateTime($rawVisitDate, new DateTimeZone('Asia/Manila'));
                $dt->setTimezone(new DateTimeZone('UTC'));
                // Use MySQL DATETIME friendly format (no timezone designator)
                $visitDateForApi = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $visitDateForApi = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            }
        } else {
            $visitDateForApi = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        // Build data payload depending on role
        $role = $currentUser['role'] ?? '';
        $appointmentId = isset($_POST['appointment_id']) && $_POST['appointment_id'] ? $_POST['appointment_id'] : null;
        
        if ($role === 'staff' && $action === 'add_visit') {
            // Secretaries may only add basic visit with chief complaint and vitals
            $data = [
                'patient_id' => isset($_POST['patient_id']) ? $_POST['patient_id'] : '',
                'appointment_id' => $appointmentId,
                'visit_date' => $visitDateForApi,
                'chief_complaint' => isset($_POST['chief_complaint']) ? $_POST['chief_complaint'] : '',
                'height' => isset($_POST['height']) ? $_POST['height'] : null,
                'weight' => isset($_POST['weight']) ? $_POST['weight'] : null,
                'blood_pressure' => isset($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null,
                'temperature' => isset($_POST['temperature']) ? $_POST['temperature'] : null,
                'vitals_notes' => isset($_POST['vitals_notes']) ? $_POST['vitals_notes'] : null
            ];
            $result = apiCall('POST', '/api/visits', $data);
            // Mark the appointment as Completed if visit was created successfully and appointment_id was provided
            if ($result && $appointmentId) {
                apiCall('POST', '/api/appointments/' . $appointmentId . '/complete', []);
            }
            $_SESSION['message'] = $result ? 'Visit (chief complaint) added successfully' : 'Failed to add visit';
        } else {
            // Admins/doctors (and staff shouldn't reach here for update) get full form
            $data = [
                'patient_id' => isset($_POST['patient_id']) ? $_POST['patient_id'] : '',
                'appointment_id' => $appointmentId,
                'visit_date' => $visitDateForApi,
                'visit_type' => isset($_POST['visit_type']) ? $_POST['visit_type'] : '',
                'ent_type' => isset($_POST['ent_type']) ? $_POST['ent_type'] : 'ear',
                'chief_complaint' => isset($_POST['chief_complaint']) ? $_POST['chief_complaint'] : '',
                'diagnosis' => isset($_POST['diagnosis']) ? $_POST['diagnosis'] : '',
                'treatment_plan' => isset($_POST['treatment_plan']) ? $_POST['treatment_plan'] : '',
                'prescription' => isset($_POST['prescription']) ? $_POST['prescription'] : '',
                'notes' => isset($_POST['notes']) ? $_POST['notes'] : '',
                'height' => isset($_POST['height']) ? $_POST['height'] : null,
                'weight' => isset($_POST['weight']) ? $_POST['weight'] : null,
                'blood_pressure' => isset($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null,
                'temperature' => isset($_POST['temperature']) ? $_POST['temperature'] : null,
                'vitals_notes' => isset($_POST['vitals_notes']) ? $_POST['vitals_notes'] : null
            ];

            if ($action === 'add_visit') {
                $result = apiCall('POST', '/api/visits', $data);
                // Mark the appointment as Completed if visit was created successfully and appointment_id was provided
                if ($result && $appointmentId) {
                    apiCall('POST', '/api/appointments/' . $appointmentId . '/complete', []);
                }
                $_SESSION['message'] = $result ? 'Visit added successfully' : 'Failed to add visit';
            } else {
                $id = isset($_POST['id']) ? $_POST['id'] : '';
                $result = apiCall('PUT', '/api/visits/' . $id, $data);
                $_SESSION['message'] = $result ? 'Visit updated successfully' : 'Failed to update visit';
            }
        }

        $patientId = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
        redirect('/?page=patient-profile&id=' . $patientId);
    }
    
    // Handle delete visit
    if ($action === 'delete_visit') {
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $patientId = isset($_POST['patient_id']) ? $_POST['patient_id'] : '';
        $result = apiCall('DELETE', '/api/visits/' . $id);
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
            'medical_history' => isset($_POST['medical_history']) ? $_POST['medical_history'] : '',
            'allergies' => isset($_POST['allergies']) ? $_POST['allergies'] : '',
            'vaccine_history' => isset($_POST['vaccine_history']) ? $_POST['vaccine_history'] : '',
            'insurance_provider' => isset($_POST['insurance_provider']) ? $_POST['insurance_provider'] : '',
            'insurance_id' => isset($_POST['insurance_id']) ? $_POST['insurance_id'] : '',
            'emergency_contact_name' => isset($_POST['emergency_contact_name']) ? $_POST['emergency_contact_name'] : '',
            'emergency_contact_phone' => isset($_POST['emergency_contact_phone']) ? $_POST['emergency_contact_phone'] : '',
            'height' => isset($_POST['height']) ? $_POST['height'] : null,
            'weight' => isset($_POST['weight']) ? $_POST['weight'] : null,
            'bmi' => isset($_POST['bmi']) ? $_POST['bmi'] : null
        ];
        
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $result = apiCall('PUT', '/api/patients/' . $id, $data);
        if ($result) {
            $_SESSION['message'] = 'Patient information updated successfully';
        } else {
            // Include any API error details in the UI message for easier debugging
            $apiErr = $_SESSION['api_last_error'] ?? null;
            $detail = '';
            if (!empty($apiErr)) {
                $resp = $apiErr['response'] ?? null;
                if (is_array($resp) && isset($resp['error'])) {
                    $detail = ' - ' . (is_array($resp['error']) ? json_encode($resp['error']) : (string)$resp['error']);
                } elseif (is_array($resp) && isset($resp['message'])) {
                    $detail = ' - ' . (string)$resp['message'];
                } elseif (is_string($resp)) {
                    $detail = ' - ' . $resp;
                }
            }
            $_SESSION['message'] = 'Failed to update patient information' . $detail;
        }
        redirect('/?page=patient-profile&id=' . $id);
    }

    // Handle logout
    if ($action === 'logout') {
        // Call API logout to clear session server-side
        apiCall('POST', '/auth/logout');
        session_unset();
        session_destroy();
        redirect('/pages/login.php');
    }

    // Admin user management actions
    if (in_array($action, ['create_user', 'delete_user', 'update_user'])) {
        require_once __DIR__ . '/../config/Database.php';
        $db = Database::getInstance();
        $currentUser = $_SESSION['user'] ?? null;
        if (!($currentUser && $currentUser['role'] === 'admin')) {
            $_SESSION['message'] = 'Unauthorized';
            redirect('/?page=patients');
        }

        if ($action === 'create_user') {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            $full_name = $_POST['full_name'] ?? '';
            $password = $_POST['password'] ?? 'password';
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'full_name' => $full_name,
                    'role' => $role,
                    'is_active' => 1
                ]);
                $_SESSION['message'] = 'User created';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Failed to create user: ' . $e->getMessage();
            }
            redirect('/?page=settings');
        }

        if ($action === 'delete_user') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    $db->delete('users', 'id = ?', [$id]);
                    $_SESSION['message'] = 'User deleted';
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Failed to delete user: ' . $e->getMessage();
                }
            }
            redirect('/?page=settings');
        }

        if ($action === 'update_user') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                $data = [];
                // Basic fields
                if (isset($_POST['full_name'])) $data['full_name'] = $_POST['full_name'];
                if (isset($_POST['role'])) $data['role'] = $_POST['role'];
                if (isset($_POST['is_active'])) $data['is_active'] = $_POST['is_active'] ? 1 : 0;

                // Allow updating username and email (with uniqueness checks)
                $newUsername = isset($_POST['username']) ? trim($_POST['username']) : null;
                $newEmail = isset($_POST['email']) ? trim($_POST['email']) : null;

                // Check username uniqueness
                if ($newUsername !== null && $newUsername !== '') {
                    $existing = $db->fetch('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1', [$newUsername, $id]);
                    if ($existing) {
                        $_SESSION['message'] = 'Username already taken by another account';
                        redirect('/?page=settings');
                    }
                    $data['username'] = $newUsername;
                }

                // Check email uniqueness
                if ($newEmail !== null && $newEmail !== '') {
                    $existingEmail = $db->fetch('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1', [$newEmail, $id]);
                    if ($existingEmail) {
                        $_SESSION['message'] = 'Email already used by another account';
                        redirect('/?page=settings');
                    }
                    $data['email'] = $newEmail;
                }

                // If password provided, hash it and update password_hash
                if (!empty($_POST['password'])) {
                    $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                if (!empty($data)) {
                    try {
                        $db->update('users', $data, 'id = ?', [$id]);
                        $_SESSION['message'] = 'User updated';
                    } catch (Exception $e) {
                        $_SESSION['message'] = 'Failed to update user: ' . $e->getMessage();
                    }
                }
            }
            redirect('/?page=settings');
        }
    }
}

// Get current page (already set above for access control check)

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
