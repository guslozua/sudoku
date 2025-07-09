<?php

// Autoload simple para el proyecto Sudoku
spl_autoload_register(function ($class) {
    // Convertir namespace a ruta de archivo
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    
    // Verificar si la clase usa el namespace App
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Obtener el nombre relativo de la clase
    $relative_class = substr($class, $len);
    
    // Convertir a ruta de archivo
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Si el archivo existe, cargarlo
    if (file_exists($file)) {
        require $file;
    }
});

// Log del autoload
function sudoku_log($message) {
    error_log("[SUDOKU AUTOLOAD] " . $message);
}

sudoku_log("Autoload simple inicializado");
