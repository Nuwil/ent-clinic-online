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
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return $result['data'] ?? $result;
    }

    // store last API error in session for UI to show (if session started)
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['api_last_error'] = $result ?? $response;
    }

    return false;
}

/**
 * Format date
 */
function formatDate($dateString, $includeTime = false) {
    if (!$dateString) return 'N/A';
    
    $timestamp = strtotime($dateString);
    if ($includeTime) {
        return date('M d, Y h:i A', $timestamp);
    }
    return date('M d, Y', $timestamp);
}

/**
 * Get current page from URL
 */
function getCurrentPage() {
    $page = isset($_GET['page']) ? $_GET['page'] : 'patients';
    $allowedPages = ['patients', 'patient-profile', 'analytics', 'settings'];
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

