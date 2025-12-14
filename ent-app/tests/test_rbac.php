<?php
// Simple RBAC checks for Secretary and Doctor roles
require_once __DIR__ . '/../public/includes/helpers.php';

// Helper to simulate session user
function setUser($id, $role) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user'] = ['id' => $id, 'role' => $role, 'full_name' => ucfirst($role) . ' User'];
}

function showCheck($role, $page) {
    setUser(100, $role);
    $can = canAccessPage($page) ? 'YES' : 'NO';
    $perm = hasPermission('view_settings') ? 'YES' : 'NO';
    echo "Role: $role | canAccessPage($page): $can | hasPermission(view_settings): $perm\n";
}

// Tests
// Secretary should be able to access 'appointments' (and for compatibility 'secretary-appointments')
setUser(101, 'secretary');
if (!canAccessPage('secretary-appointments')) { echo "FAILED: Secretary cannot access secretary-appointments\n"; exit(1); }
if (!canAccessPage('appointments')) { echo "FAILED: Secretary cannot access appointments\n"; exit(1); }
if (hasPermission('view_settings')) { echo "FAILED: Secretary should NOT have view_settings permission\n"; exit(1); }

// Doctor should NOT be able to access 'settings' or have view_settings permission
setUser(201, 'doctor');
if (canAccessPage('settings')) { echo "FAILED: Doctor should NOT access settings\n"; exit(1); }
if (hasPermission('view_settings')) { echo "FAILED: Doctor should NOT have view_settings permission\n"; exit(1); }

// Admin should be able to access 'settings' and have view_settings permission
setUser(1, 'admin');
if (!canAccessPage('settings')) { echo "FAILED: Admin should access settings\n"; exit(1); }
if (!hasPermission('view_settings')) { echo "FAILED: Admin should have view_settings permission\n"; exit(1); }

// Admin and Doctor should be able to access Analytics
if (!canAccessPage('analytics')) { echo "FAILED: Admin should access analytics\n"; exit(1); }
setUser(201, 'doctor');
if (!canAccessPage('analytics')) { echo "FAILED: Doctor should access analytics\n"; exit(1); }

echo "RBAC tests PASSED\n";

?>