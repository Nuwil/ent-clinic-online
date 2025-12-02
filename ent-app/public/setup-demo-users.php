<?php
/**
 * Setup Demo Users
 * Run this script once to seed demo accounts for testing
 * php public/setup-demo-users.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Demo users to create
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
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$user['username']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "✓ User '{$user['username']}' already exists\n";
        } else {
            // Create user
            $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([
                $user['username'],
                $user['email'],
                $passwordHash,
                $user['full_name'],
                $user['role']
            ]);
            echo "✓ Created user '{$user['username']}' (password: {$user['password']})\n";
        }
    }
    
    echo "\n✅ Demo users setup complete!\n";
    echo "You can now login with:\n";
    echo "  - Admin: admin / admin123\n";
    echo "  - Doctor: doctor_demo / password\n";
    echo "  - Secretary: staff_demo / password\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
