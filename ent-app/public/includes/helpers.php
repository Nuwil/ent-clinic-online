<?php
/**
 * Helper Functions for PHP Frontend
 */

/**
 * Make API call
 */
function apiCall($method, $endpoint, $data = null) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // If caller passed a full URL, use it as-is
    if (preg_match('#^https?://#i', $endpoint)) {
        $url = $endpoint;
    } else {
        // If caller passed an api.php path (like '/api.php?route=...'), use it directly on this host
        if (strpos($endpoint, '/api.php') === 0 || strpos($endpoint, 'api.php') !== false) {
            // Ensure leading slash
            if ($endpoint[0] !== '/') $endpoint = '/' . $endpoint;
            $url = $protocol . '://' . $host . $endpoint;
        } else {
            // Parse endpoint to separate path from query string
            $parts = parse_url($endpoint);
            $path = $parts['path'] ?? '/';
            $queryString = $parts['query'] ?? '';

            // Build API URL: /api.php?route=/api/analytics&param1=value1...
            // This works regardless of .htaccess mod_rewrite status
            $url = $protocol . '://' . $host . '/ENT-clinic-online/ent-app/public/api.php';
            $url .= '?route=' . urlencode($path);
            if ($queryString) {
                $url .= '&' . $queryString;
            }
        }
    }
    
    $ch = curl_init();

    // Use session cookie for auth but release the PHP session lock before making the HTTP call
    $headers = ['Content-Type: application/json'];
    
    // Ensure session is available so we can forward auth info (start briefly if needed)
    $startedSessionHere = false;
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
        $startedSessionHere = true;
    }

    // Add header-based auth as fallback/supplement (reads from session if present)
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user'])) {
        $userId = $_SESSION['user']['id'] ?? null;
        $userRole = $_SESSION['user']['role'] ?? null;
        if ($userId && $userRole) {
            $headers[] = 'X-User-Id: ' . $userId;
            $headers[] = 'X-User-Role: ' . $userRole;
        }
    }

    // Capture session cookie if present
    $cookie = null;
    $hadActiveSession = false;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $cookie = session_name() . '=' . session_id();
        $hadActiveSession = true;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // set reasonable timeouts to avoid extremely long hangs
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Allow following redirects and SSL
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Forward session cookie if we captured one
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    
    // Close session AFTER setting up curl but BEFORE executing to avoid lock
    if ($hadActiveSession || $startedSessionHere) {
        session_write_close();
    }


    if ($data && in_array($method, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return $result['data'] ?? $result;
    }
    // store last API error in session for UI to show (re-open session if we closed it earlier)
    if (!headers_sent()) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $errInfo = ['http_code' => $httpCode, 'response' => $result ?? $response];
            if (!empty($curlErr)) $errInfo['curl_error'] = $curlErr;
            $_SESSION['api_last_error'] = $errInfo;
        }
    }

    return false;
}

/**
 * Format date
 */
function formatDate($dateString, $includeTime = false) {
    if (!$dateString) return 'N/A';
    
    try {
        // If the stored date string does not include a timezone indicator, assume UTC storage.
        // This matches the convention where we convert incoming local datetimes to UTC on save.
        $looksLikeHasTZ = preg_match('/([+-]\d{2}:?\d{2}|Z)$/', $dateString) ||
                             (strpos($dateString, 'T') !== false && (strpos($dateString, '+') !== false || strpos($dateString, 'Z') !== false));

        if ($looksLikeHasTZ) {
            $dt = new DateTime($dateString);
        } else {
            // Parse as UTC then convert to Manila for display
            $dt = new DateTime($dateString, new DateTimeZone('UTC'));
        }

        // Convert display to Philippine Time
        $manila = new DateTimeZone('Asia/Manila');
        $dt->setTimezone($manila);
        if ($includeTime) {
            return $dt->format('M d, Y h:i A');
        }
        return $dt->format('M d, Y');
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Get current page from URL
 */
function getCurrentPage() {
    $page = isset($_GET['page']) ? $_GET['page'] : 'patients';
    // Add role-based dashboard pages
    $allowedPages = [
        'patients',
        'patient-profile',
        'analytics',
        'settings',
        'admin',    // admin dashboard
        'doctor',   // doctor dashboard
        'staff'     // secretary/staff dashboard
    ];
    return in_array($page, $allowedPages) ? $page : 'patients';
}

/**
 * Escape HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get base URL
 */
function baseUrl() {
    return '/ENT-clinic-online/ent-app/public';
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: " . baseUrl() . $url);
    exit;
}

/**
 * RBAC (Role-Based Access Control) Helper Functions
 */

/**
 * Role definitions with permissions matrix
 * Defines what each role can access and what actions they can perform
 */
function getRBACMatrix() {
    return [
        'admin' => [
            'display_name' => 'Administrator',
            'icon' => 'fa-crown',
            'description' => 'Full system access and control',
            'permissions' => [
                'view_patients' => true,
                'create_patient' => true,
                'edit_patient' => true,
                'delete_patient' => true,
                'view_visits' => true,
                'create_visit' => true,
                'edit_visit' => true,
                'delete_visit' => true,
                'view_analytics' => true,
                'view_settings' => true,
                'manage_users' => true,
                'export_data' => true,
                'view_admin_dashboard' => true,
            ],
            'accessible_pages' => ['admin', 'patients', 'patient-profile', 'analytics', 'settings', 'medical-certificate']
        ],
        'doctor' => [
            'display_name' => 'Doctor',
            'icon' => 'fa-user-md',
            'description' => 'Patient care and medical record access',
            'permissions' => [
                'view_patients' => true,
                'create_patient' => false,
                'edit_patient' => false,
                'delete_patient' => false,
                'view_visits' => true,
                'create_visit' => true,
                'edit_visit' => true,
                'delete_visit' => true,
                'view_analytics' => true,
                'view_settings' => false,
                'manage_users' => false,
                'export_data' => false,
                'view_doctor_dashboard' => true,
            ],
            'accessible_pages' => ['doctor', 'patients', 'patient-profile', 'analytics', 'medical-certificate']
        ],
        'staff' => [
            'display_name' => 'Secretary',
            'icon' => 'fa-clipboard-list',
            'description' => 'Patient management and administrative support',
            'permissions' => [
                'view_patients' => true,
                'create_patient' => true,
                'edit_patient' => true,
                'delete_patient' => false,
                'view_visits' => true,
                'create_visit' => false,
                'edit_visit' => false,
                'delete_visit' => false,
                'view_analytics' => false,
                'view_settings' => false,
                'manage_users' => false,
                'export_data' => false,
                'view_staff_dashboard' => true,
            ],
            'accessible_pages' => ['staff', 'patients', 'patient-profile', 'medical-certificate']
        ]
    ];
}

/**
 * Get current user from session
 */
function getCurrentUser() {
    // Avoid starting session after headers have been sent to prevent warnings
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user's role
 */
function getCurrentUserRole() {
    $user = getCurrentUser();
    return $user['role'] ?? null;
}

/**
 * Get current user's role configuration
 */
function getCurrentUserRoleConfig() {
    $role = getCurrentUserRole();
    $rbac = getRBACMatrix();
    return $rbac[$role] ?? [];
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    $userRole = getCurrentUserRole();
    if (is_array($role)) {
        return in_array($userRole, $role);
    }
    return $userRole === $role;
}

/**
 * Check if user has a specific permission
 */
function hasPermission($permission) {
    $roleConfig = getCurrentUserRoleConfig();
    return isset($roleConfig['permissions'][$permission]) && $roleConfig['permissions'][$permission];
}

/**
 * Require permission - block action if user lacks permission
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        $_SESSION['message'] = 'You do not have permission to perform this action.';
        http_response_code(403);
        die('Access Denied: ' . htmlspecialchars($permission));
    }
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return getCurrentUser() !== null;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        redirect('/');
        exit;
    }
}

/**
 * Require specific role(s) - redirect if user doesn't have required role
 */
function requireRole($allowedRoles) {
    requireAuth();
    $userRole = getCurrentUserRole();
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    if (!in_array($userRole, $allowedRoles)) {
        // Redirect to user's dashboard based on their role
        $dashboard = getDashboardForRole($userRole);
        $_SESSION['message'] = 'You do not have permission to access this page.';
        redirect('/?page=' . $dashboard);
        exit;
    }
}

/**
 * Get dashboard page for a role
 */
function getDashboardForRole($role) {
    switch ($role) {
        case 'admin':
            return 'admin';
        case 'doctor':
            return 'doctor';
        case 'staff':
            return 'staff';
        default:
            return 'patients';
    }
}

/**
 * Get dashboard display name for a role
 */
function getDashboardDisplayName($role) {
    switch ($role) {
        case 'admin':
            return 'Admin Dashboard';
        case 'doctor':
            return 'Doctor Dashboard';
        case 'staff':
            return 'Secretary Dashboard';
        default:
            return 'Dashboard';
    }
}

/**
 * Check if a page is accessible by the current user's role
 */
function canAccessPage($page) {
    $role = getCurrentUserRole();
    if (!$role) return false;
    
    // Define page access rules
    $pageAccess = [
        'admin' => ['admin', 'patients', 'patient-profile', 'analytics', 'settings', 'medical-certificate'],
        'doctor' => ['doctor', 'patients', 'patient-profile', 'analytics', 'medical-certificate'],
        'staff' => ['staff', 'patients', 'patient-profile', 'medical-certificate']
    ];
    
    return isset($pageAccess[$role]) && in_array($page, $pageAccess[$role]);
}

/**
 * Get allowed pages for current user's role
 */
function getAllowedPages() {
    $role = getCurrentUserRole();
    if (!$role) return [];
    
    $pageAccess = [
        'admin' => ['admin', 'patients', 'patient-profile', 'analytics', 'settings'],
        'doctor' => ['doctor', 'patients', 'patient-profile', 'analytics'],
        'staff' => ['staff', 'patients', 'patient-profile']
    ];
    
    return $pageAccess[$role] ?? [];
}

/**
 * Get role display name
 */
function getRoleDisplayName($role) {
    $rbac = getRBACMatrix();
    return $rbac[$role]['display_name'] ?? ucfirst($role);
}

/**
 * Get role icon
 */
function getRoleIcon($role) {
    $rbac = getRBACMatrix();
    return $rbac[$role]['icon'] ?? 'fa-user';
}

/**
 * Get all valid roles
 */
function getAllRoles() {
    return array_keys(getRBACMatrix());
}

