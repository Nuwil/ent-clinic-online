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
