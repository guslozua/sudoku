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

