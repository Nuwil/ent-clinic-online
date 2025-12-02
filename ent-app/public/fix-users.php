<?php
/**
 * Fix Demo Users - Recreate with proper password hashes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Checking and fixing user accounts...\n\n";
    
    // Demo users
    $demoUsers = [
        [
            'username' => 'admin',
            'email' => 'admin@entclinic.com',
            'password' => 'admin123',
            'full_name' => 'Administrator',
            'role' => 'admin'
        ],
        [
            'username' => 'doctor_demo',
            'email' => 'doctor@entclinic.local',
            'password' => 'password',
            'full_name' => 'Doctor Demo',
            'role' => 'doctor'
        ],
        [
            'username' => 'staff_demo',
            'email' => 'staff@entclinic.local',
            'password' => 'password',
            'full_name' => 'Secretary Demo',
            'role' => 'staff'
        ]
    ];
    
    foreach ($demoUsers as $user) {
        // Check if user exists
        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$user['username']]);
        $existing = $stmt->fetch();
        
        $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
        
        if ($existing) {
            // Test if current hash is valid
            $isValid = password_verify($user['password'], $existing['password_hash']);
            
            if ($isValid) {
                echo "✓ User '{$user['username']}' exists with VALID password\n";
            } else {
                echo "⚠ User '{$user['username']}' exists with INVALID password hash - updating...\n";
                // Update password
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
                $stmt->execute([$passwordHash, $user['username']]);
                echo "  ✓ Password hash updated\n";
            }
        } else {
            echo "➕ Creating user '{$user['username']}'\n";
            // Create user
            $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([
                $user['username'],
                $user['email'],
                $passwordHash,
                $user['full_name'],
                $user['role']
            ]);
            echo "  ✓ User created\n";
        }
    }
    
    echo "\n✅ All user accounts verified/fixed!\n\n";
    echo "Demo Accounts:\n";
    echo "  Admin:     admin / admin123\n";
    echo "  Doctor:    doctor_demo / password\n";
    echo "  Secretary: staff_demo / password\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
