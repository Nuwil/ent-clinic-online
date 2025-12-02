<?php
/**
 * Base Controller for API endpoints
 */

require_once __DIR__ . '/../config/Database.php';

class Controller
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    protected function error($message, $statusCode = 400)
    {
        $this->json(['error' => $message], $statusCode);
    }

    protected function success($data, $message = 'Success', $statusCode = 200)
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    protected function getInput()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input ?? $_REQUEST;
    }

    // Extract API caller user from headers (X-User-Id, X-User-Role) if present
    protected function getApiUser()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            return ['id' => $_SESSION['user']['id'] ?? null, 'role' => $_SESSION['user']['role'] ?? null];
        }

        // If header-based auth is explicitly allowed (development only), fall back to X-User headers
        if (defined('ALLOW_HEADER_AUTH') && ALLOW_HEADER_AUTH) {
            // Try getallheaders, fallback to $_SERVER
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            if (empty($headers)) {
                foreach ($_SERVER as $k => $v) {
                    if (strpos($k, 'HTTP_') === 0) {
                        $name = str_replace('HTTP_', '', $k);
                        $name = str_replace('_', '-', $name);
                        $headers[$name] = $v;
                    }
                }
            }

            $user = ['id' => null, 'role' => null];
            if (!empty($headers)) {
                if (isset($headers['X-User-Id'])) $user['id'] = $headers['X-User-Id'];
                if (isset($headers['X-User-Role'])) $user['role'] = $headers['X-User-Role'];
                // Some servers normalize header names to lowercase
                if (isset($headers['x-user-id'])) $user['id'] = $headers['x-user-id'];
                if (isset($headers['x-user-role'])) $user['role'] = $headers['x-user-role'];
            }

            return $user;
        }

        return ['id' => null, 'role' => null];
    }

    // Check authorization: roles can be string or array of allowed roles
    protected function isAuthorized($allowedRoles)
    {
        $user = $this->getApiUser();
        if (!$user || empty($user['role'])) return false;
        if (is_string($allowedRoles)) $allowedRoles = [$allowedRoles];
        return in_array($user['role'], $allowedRoles);
    }

    protected function requireRole($allowedRoles)
    {
        if (!$this->isAuthorized($allowedRoles)) {
            $this->error('Unauthorized', 403);
        }
    }

    protected function validate($data, $rules)
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $rules_array = explode('|', $rule);

            foreach ($rules_array as $r) {
                $r = trim($r);

                if ($r === 'required' && (empty($data[$field]) && !is_numeric($data[$field]))) {
                    $errors[$field] = "$field is required";
                }

                if (strpos($r, 'min:') === 0) {
                    $min = (int)substr($r, 4);
                    if (strlen($data[$field] ?? '') < $min) {
                        $errors[$field] = "$field must be at least $min characters";
                    }
                }

                if (strpos($r, 'max:') === 0) {
                    $max = (int)substr($r, 4);
                    if (strlen($data[$field] ?? '') > $max) {
                        $errors[$field] = "$field must not exceed $max characters";
                    }
                }

                if ($r === 'email' && !filter_var($data[$field] ?? '', FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "$field must be a valid email";
                }

                if ($r === 'numeric' && !is_numeric($data[$field] ?? '')) {
                    $errors[$field] = "$field must be numeric";
                }
            }
        }

        return $errors;
    }
}
