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
    $baseUrl = $protocol . '://' . $host . '/ENT-clinic-online/ent-app/public/api';
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init();

    // Use session cookie for auth but release the PHP session lock before making the HTTP call
    $headers = ['Content-Type: application/json'];

    // Capture session cookie if present
    $cookie = null;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $cookie = session_name() . '=' . session_id();
        // release session write lock so the API endpoint can start its own session
        session_write_close();
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // set reasonable timeouts to avoid extremely long hangs
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Forward session cookie if we captured one
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
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
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $errInfo = ['http_code' => $httpCode, 'response' => $result ?? $response];
        if (!empty($curlErr)) $errInfo['curl_error'] = $curlErr;
        $_SESSION['api_last_error'] = $errInfo;
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
 * RBAC Helper Functions
 */

/**
 * Get current user from session
 */
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
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
 * Check if a page is accessible by the current user's role
 */
function canAccessPage($page) {
    $role = getCurrentUserRole();
    if (!$role) return false;
    
    // Define page access rules
    $pageAccess = [
        'admin' => ['admin', 'patients', 'patient-profile', 'analytics', 'settings'],
        'doctor' => ['doctor', 'patients', 'patient-profile', 'analytics'],
        'staff' => ['staff', 'patients', 'patient-profile']
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
    $roleNames = [
        'admin' => 'Administrator',
        'doctor' => 'Doctor',
        'staff' => 'Secretary'
    ];
    return $roleNames[$role] ?? ucfirst($role);
}

