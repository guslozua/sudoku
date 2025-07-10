<?php

// Router API para Sudoku - VERSIÓN CORREGIDA CON BYPASS
if (!session_id()) {
    session_start();
}

// Cargar sistemas de seguridad y optimización
require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/cors.php';
require_once __DIR__ . '/optimization/cache.php';
require_once __DIR__ . '/optimization/performance.php';
require_once __DIR__ . '/simple_autoload.php';

// Iniciar monitoreo de performance
PerformanceMiddleware::before();

// Aplicar configuración CORS segura
CORSConfig::handlePreflight();
CORSConfig::applyHeaders();

// Validar origen en requests importantes
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !CORSConfig::validateOrigin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Origen no permitido']);
    exit;
}

// Validar CSRF en requests POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFProtection::validateRequest();
}

header('Content-Type: application/json');

// Parsear la ruta
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Limpiar la ruta - remover query string y normalizar
$route = parse_url($requestUri, PHP_URL_PATH);
$route = preg_replace('#^/Sudoku/public/api#', '', $route);
$route = rtrim($route, '/');
if (empty($route)) {
    $route = '/';
}

// Log detallado para debugging
error_log("🔄 API Debug - Original URI: $requestUri");
error_log("🔄 API Debug - Parsed route: $route");
error_log("🔄 API Debug - Method: $method");
error_log("🔄 API Debug - Looking for: $method:$route");

// ========== BYPASS DIRECTO PARA STATS ==========
if ($method === 'GET' && strpos($route, '/stats') === 0) {
    error_log("🔄 BYPASS: Ejecutando StatsController para ruta: $route");
    
    try {
        $controller = new App\Http\Controllers\StatsController();
        
        if ($route === '/stats') {
            error_log("🎯 Ejecutando getUserStats");
            $result = $controller->getUserStats();
        } elseif ($route === '/stats/dashboard') {
            error_log("🎯 Ejecutando getDashboardStats");
            $result = $controller->getDashboardStats();
        } elseif ($route === '/stats/leaderboard') {
            error_log("🎯 Ejecutando getLeaderboard");
            $result = $controller->getLeaderboard();
        } else {
            throw new Exception("Ruta stats no reconocida: $route");
        }
        
        echo json_encode($result);
        error_log("✅ BYPASS exitoso para: $route");
        exit;
        
    } catch (Exception $e) {
        error_log("❌ Error en bypass: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error en bypass: ' . $e->getMessage(),
            'route' => $route,
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
        exit;
    }
}

// Definir rutas exactas
$exactRoutes = [
    // Rutas de Puzzles
    'GET:/puzzle/new/easy' => ['SudokuController', 'getNewPuzzle', ['easy']],
    'GET:/puzzle/new/medium' => ['SudokuController', 'getNewPuzzle', ['medium']], 
    'GET:/puzzle/new/hard' => ['SudokuController', 'getNewPuzzle', ['hard']],
    'GET:/puzzle/new/expert' => ['SudokuController', 'getNewPuzzle', ['expert']],
    'GET:/puzzle/new/master' => ['SudokuController', 'getNewPuzzle', ['master']],
    'POST:/puzzle/validate' => ['SudokuController', 'validateSolution'],
    
    // Rutas de Juego
    'POST:/game/save' => ['GameController', 'saveProgress'],
    'POST:/game/complete' => ['GameController', 'completePuzzle'],
    'GET:/game/current' => ['GameController', 'getCurrentGame'],
    'GET:/game/history' => ['GameController', 'getGameHistory'],
    
    // Ruta de pistas
    'POST:/hint' => ['SudokuController', 'getHint'],
    
    // Rutas de logros
    'GET:/achievements' => ['SudokuController', 'getAchievements'],
    'POST:/achievements/check' => ['SudokuController', 'checkAchievements'],
    
    // Rutas de analíticas
    'GET:/analytics/dashboard' => ['StatsController', 'getDashboardAnalytics'],
    'GET:/analytics/progress' => ['StatsController', 'getProgressAnalytics'],
];

// Log de todas las rutas disponibles para debugging
error_log("🔍 Rutas disponibles: " . implode(', ', array_keys($exactRoutes)));

// Buscar ruta exacta
$routeKey = "$method:$route";

if (isset($exactRoutes[$routeKey])) {
    error_log("✅ Ruta exacta encontrada: $routeKey");
    $controllerInfo = $exactRoutes[$routeKey];
    executeController($controllerInfo, $route);
} else {
    // Buscar rutas con parámetros dinámicos
    $found = false;
    
    foreach ($exactRoutes as $pattern => $controllerInfo) {
        $patternParts = explode(':', $pattern, 2);
        $patternMethod = $patternParts[0];
        $patternRoute = $patternParts[1];
        
        if ($method !== $patternMethod) {
            continue;
        }
        
        // Convertir patrón a regex (ej: /puzzle/{id} -> /puzzle/(\d+))
        $regex = preg_replace('/\{[^}]+\}/', '([^/]+)', $patternRoute);
        $regex = '#^' . $regex . '$#';
        
        if (preg_match($regex, $route, $matches)) {
            $found = true;
            array_shift($matches); // Remover la coincidencia completa
            
            error_log("✅ Ruta dinámica encontrada: $pattern -> $route");
            executeController($controllerInfo, $route, $matches);
            break;
        }
    }
    
    if (!$found) {
        error_log("❌ Ruta no encontrada: $method $route");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Endpoint no encontrado: $method $route",
            'debug' => [
                'original_uri' => $requestUri,
                'parsed_route' => $route,
                'method' => $method,
                'route_key' => $routeKey,
                'available_routes' => array_keys($exactRoutes)
            ]
        ]);
    }
}

/**
 * Ejecutar controlador
 */
function executeController($controllerInfo, $route, $params = [])
{
    $controllerName = $controllerInfo[0];
    $methodName = $controllerInfo[1];
    $staticParams = isset($controllerInfo[2]) ? $controllerInfo[2] : [];
    
    try {
        // Cargar el controlador
        $controllerClass = "App\\Http\\Controllers\\$controllerName";
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controlador $controllerClass no encontrado");
        }
        
        $controller = new $controllerClass();
        
        // Verificar que el método existe
        if (!method_exists($controller, $methodName)) {
            throw new Exception("Método $methodName no encontrado en $controllerClass");
        }
        
        error_log("🎯 Ejecutando: $controllerClass::$methodName");
        
        // Crear objeto Request simulado
        $request = new class {
            public function input($key, $default = null) {
                return $_POST[$key] ?? $_GET[$key] ?? $default;
            }
            
            public function all() {
                return array_merge($_GET, $_POST);
            }
            
            public function get($key, $default = null) {
                return $_GET[$key] ?? $default;
            }
            
            public function json($key = null, $default = null) {
                static $jsonData = null;
                if ($jsonData === null) {
                    $jsonData = json_decode(file_get_contents('php://input'), true) ?: [];
                }
                return $key ? ($jsonData[$key] ?? $default) : $jsonData;
            }
        };
        
        // Ejecutar el método del controlador
        $allParams = array_merge([$request], $staticParams, $params);
        $result = call_user_func_array([$controller, $methodName], $allParams);
        
        // Manejar diferentes tipos de respuesta
        if (is_object($result) && method_exists($result, 'getContent')) {
            // Respuesta de Laravel
            echo $result->getContent();
        } elseif (is_array($result) || is_object($result)) {
            // Array u objeto - convertir a JSON
            echo json_encode($result);
        } else {
            // String o valor simple
            echo $result;
        }
        
    } catch (Exception $e) {
        error_log("❌ Error ejecutando controlador: " . $e->getMessage());
        error_log("❌ Stack trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error interno: ' . $e->getMessage(),
            'debug' => [
                'controller' => $controllerName,
                'method' => $methodName,
                'route' => $route,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
}

// Finalizar monitoreo de performance
$performanceMetrics = PerformanceMiddleware::after(true);

// Log de métricas si hay problemas
if ($performanceMetrics['execution_time_ms'] > 500) {
    error_log("⚠️ Respuesta lenta detectada: " . $performanceMetrics['execution_time_ms'] . "ms para $route");
}

if ($performanceMetrics['cache_hit_ratio'] < 60 && $performanceMetrics['cache_hits'] + $performanceMetrics['cache_misses'] > 0) {
    error_log("⚠️ Cache hit ratio bajo: " . $performanceMetrics['cache_hit_ratio'] . "% para $route");
}
