<?php
/**
 * API Router - Handles all HTTP requests and routes them to appropriate endpoints
 */

class Router
{
    private $routes = [];
    private $method;
    private $path;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Remove the base path (case-insensitive)
        $basePath = str_ireplace('\\', '/', __DIR__);
        $basePath = str_ireplace(str_ireplace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '', $basePath);
        
        // Handle different URL formats:
        // 1. Clean URLs (with mod_rewrite): /api/analytics
        // 2. Direct access: /api.php/api/analytics
        // 3. Fallback: /api.php with route parameter
        
        if (strpos($this->path, '/api.php/') !== false) {
            // Format: .../api.php/api/analytics -> /api/analytics
            $pos = strpos($this->path, '/api.php/');
            $this->path = substr($this->path, $pos + 8); // Remove '.../api.php/'
        } elseif (strpos($this->path, '/api.php') !== false && strpos($this->path, '/api.php') === strlen($this->path) - 8) {
            // Format: /api.php -> check for route parameter
            if (!empty($_GET['route'])) {
                $this->path = $_GET['route'];
            } else {
                $this->path = '/api';
            }
            } else {
                // Remove base path (for clean URLs)
                if (strpos($this->path, $basePath) === 0) {
                    $this->path = substr($this->path, strlen($basePath));
                }
        }
        
        if ($this->path === '' || $this->path === '/api.php') {
            $this->path = '/api';
        }
        
        // Debug logging
        error_log('Router: method=' . $this->method . ', uri=' . $_SERVER['REQUEST_URI'] . ', path=' . $this->path);
    }

    public function get($path, $callback)
    {
        $this->addRoute('GET', $path, $callback);
    }

    public function post($path, $callback)
    {
        $this->addRoute('POST', $path, $callback);
    }

    public function put($path, $callback)
    {
        $this->addRoute('PUT', $path, $callback);
    }

    public function delete($path, $callback)
    {
        $this->addRoute('DELETE', $path, $callback);
    }

    private function addRoute($method, $path, $callback)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }

    public function dispatch()
    {
        // Set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Content-Type: application/json; charset=utf-8');

        // Handle preflight requests
        if ($this->method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $this->method && $this->pathMatches($route['path'], $this->path)) {
                try {
                    $params = $this->extractParams($route['path'], $this->path);
                    call_user_func_array($route['callback'], $params);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Internal Server Error',
                        'message' => defined('ENV') && ENV === 'development' ? $e->getMessage() : 'An error occurred'
                    ]);
                }
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        error_log('Router 404: No matching route for ' . $this->method . ' ' . $this->path . '. Registered routes: ' . json_encode(array_column($this->routes, 'path')));
        echo json_encode(['error' => 'Not Found', 'path' => $this->path, 'method' => $this->method, 'available_routes' => array_column($this->routes, 'path')]);
    }

    private function pathMatches($pattern, $path)
    {
        $pattern = preg_replace('/:\w+/', '([^/]+)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        return preg_match('/^' . $pattern . '$/', $path);
    }

    private function extractParams($pattern, $path)
    {
        $pattern = preg_replace_callback('/:\w+/', function ($m) {
            return '([^/]+)';
        }, $pattern);
        $pattern = str_replace('/', '\/', $pattern);

        if (preg_match('/^' . $pattern . '$/', $path, $matches)) {
            array_shift($matches);
            return $matches;
        }

        return [];
    }
}
