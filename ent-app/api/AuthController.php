<?php
/**
 * Authentication Controller for API
 */

require_once __DIR__ . '/Controller.php';

class AuthController extends Controller
{
    // POST /api/auth/login
    public function login()
    {
        try {
            $input = $this->getInput();
            $username = trim($input['username'] ?? $input['email'] ?? '');
            $password = $input['password'] ?? '';

            if (!$username || !$password) {
                $this->error('username/email and password required', 400);
            }

            // Find user by username or email
            $stmt = $this->db->prepare('SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE (username = ? OR email = ?) LIMIT 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->error('Invalid credentials', 401);
            }

            if (!$user['is_active']) {
                $this->error('Account is disabled', 403);
            }

            if (!password_verify($password, $user['password_hash'])) {
                $this->error('Invalid credentials', 401);
            }

            // Successful login: create session
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => $user['full_name'],
                'role' => $user['role']
            ];

            // Do not return password hash
            $out = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => $user['full_name'],
                'role' => $user['role']
            ];

            $this->success($out, 'Logged in');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    // POST /api/auth/logout
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        // Unset user and destroy session
        unset($_SESSION['user']);
        // Optionally destroy session entirely
        session_unset();
        session_destroy();

        $this->success(['logged_out' => true], 'Logged out');
    }
}
