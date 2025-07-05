<?php

// Bootstrap básico para Laravel
require_once __DIR__ . '/../vendor/autoload.php';

// Inicializar sesión
if (!session_id()) {
    session_start();
}

// Crear una aplicación básica
$app = new class {
    private $routes = [];
    private $apiRoutes = [];
    
    public function get($uri, $action) {
        $this->routes['GET'][$uri] = $action;
        return $this;
    }
    
    public function post($uri, $action) {
        $this->routes['POST'][$uri] = $action;
        return $this;
    }
    
    public function apiRoute($method, $uri, $action) {
        $this->apiRoutes[$method][$uri] = $action;
        return $this;
    }
    
    public function handleRequest() {
        global $app;
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        
        // Remover /Sudoku/public del URI si está presente
        $uri = preg_replace('#^/Sudoku/public#', '', $uri);
        if (empty($uri)) $uri = '/';
        
        // Manejar rutas API
        if (strpos($uri, '/api/') === 0) {
            $apiUri = substr($uri, 4); // Remover /api
            $routeKey = $method . ':' . $apiUri;
            
            // Buscar ruta exacta
            global $routes;
            if (isset($routes[$routeKey])) {
                return $this->executeAction($routes[$routeKey]);
            }
            
            // Buscar rutas con parámetros dinámicos para API
            foreach ($routes as $pattern => $action) {
                if (strpos($pattern, $method . ':') === 0) {
                    $routePattern = substr($pattern, strlen($method . ':'));
                    if ($this->matchRoute($routePattern, $apiUri)) {
                        $params = $this->extractParams($routePattern, $apiUri);
                        // Crear objeto Request simple
                        $request = new class {
                            public function input($key, $default = null) {
                                $input = json_decode(file_get_contents('php://input'), true) ?: [];
                                return $input[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
                            }
                            
                            public function has($key) {
                                $input = json_decode(file_get_contents('php://input'), true) ?: [];
                                return isset($input[$key]) || isset($_POST[$key]) || isset($_GET[$key]);
                            }
                        };
                        
                        // Para rutas con parámetros, pasar solo los parámetros
                        if (strpos($routePattern, '{') !== false) {
                            return $this->executeAction($action, $params);
                        } else {
                            return $this->executeAction($action, array_merge([$request], $params));
                        }
                    }
                }
            }
            
            // API 404
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Endpoint no encontrado']);
            return;
        }
        
        // Manejar rutas web
        if (isset($this->routes[$method][$uri])) {
            return $this->executeAction($this->routes[$method][$uri]);
        }
        
        // Buscar rutas con parámetros dinámicos
        foreach ($this->routes[$method] ?? [] as $pattern => $action) {
            if ($this->matchRoute($pattern, $uri)) {
                return $this->executeAction($action, $this->extractParams($pattern, $uri));
            }
        }
        
        // Ruta por defecto
        if ($uri === '/' || $uri === '') {
            if (isset($this->routes['GET']['/'])) {
                return $this->executeAction($this->routes['GET']['/']);
            }
        }
        
        // 404
        http_response_code(404);
        echo "404 - Página no encontrada";
    }
    
    private function matchRoute($pattern, $uri) {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern);
        return preg_match("#^$pattern$#", $uri);
    }
    
    private function extractParams($pattern, $uri) {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern);
        preg_match("#^$pattern$#", $uri, $matches);
        return array_slice($matches, 1);
    }
    
    private function executeAction($action, $params = []) {
        if (is_callable($action)) {
            return $action(...$params);
        }
        
        if (is_array($action) && count($action) === 2) {
            list($controller, $method) = $action;
            
            if (is_string($controller)) {
                $controllerClass = "App\\Http\\Controllers\\$controller";
                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                } else {
                    echo "Error: Controlador $controllerClass no encontrado";
                    return;
                }
            }
            
            if (method_exists($controller, $method)) {
                try {
                    return $controller->$method(...$params);
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => $e->getMessage()]);
                    return;
                }
            } else {
                echo "Error: Método $method no encontrado en el controlador";
                return;
            }
        }
        
        echo "Error: Acción no válida";
    }
};

// Simular clases de Laravel
class Route {
    private static $app;
    
    public static function setApp($app) {
        self::$app = $app;
    }
    
    public static function get($uri, $action) {
        return self::$app->get($uri, $action);
    }
    
    public static function post($uri, $action) {
        return self::$app->post($uri, $action);
    }
    
    public static function middleware($middleware) {
        return new class($middleware) {
            private $middleware;
            
            public function __construct($middleware) {
                $this->middleware = $middleware;
            }
            
            public function group($callback) {
                $callback();
            }
        };
    }
    
    public static function prefix($prefix) {
        return new class($prefix) {
            private $prefix;
            
            public function __construct($prefix) {
                $this->prefix = $prefix;
            }
            
            public function group($callback) {
                global $app;
                $oldPrefix = $app->currentPrefix ?? '';
                $app->currentPrefix = $oldPrefix . '/' . trim($this->prefix, '/');
                $callback();
                $app->currentPrefix = $oldPrefix;
            }
        };
    }
    
    public static function fallback($action) {
        // No implementado en esta versión simple
    }
}

// Configurar la aplicación
Route::setApp($app);

return $app;
