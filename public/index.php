<?php

// Bootstrap principal del Sudoku
if (!session_id()) {
    session_start();
}

// Cargar sistema CSRF
require_once __DIR__ . '/../security/csrf.php';

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Log para debugging
error_log("🚀 Request: $method $requestUri");

// Si es una ruta API, usar el router API
if (strpos($requestUri, '/api/') !== false) {
    require_once __DIR__ . '/../api_router.php';
    exit;
}

// Si es la raíz o sudoku, mostrar la vista
if (preg_match('#^(/Sudoku/public/?|/?)$#', $requestUri)) {
    // Cargar la vista del juego
    $content = file_get_contents(__DIR__ . '/../resources/views/sudoku/index.blade.php');
    
    // Procesar directivas blade básicas con token CSRF real
    $csrfToken = CSRFProtection::getToken();
    $content = str_replace('{{ csrf_token() }}', $csrfToken, $content);
    
    echo $content;
    exit;
}

// 404 para otras rutas
http_response_code(404);
echo "<h1>404 - Página no encontrada</h1>";
echo "<p>Ruta solicitada: $requestUri</p>";
echo "<p><a href='/Sudoku/public'>Ir al juego</a></p>";
