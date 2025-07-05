<?php

/*
|--------------------------------------------------------------------------
| API Routes para Sudoku Minimalista - CORREGIDAS
|--------------------------------------------------------------------------
*/

// Declarar las rutas como variable global
global $routes;
$routes = [
    // Rutas de Puzzles - Todas las dificultades
    'GET:/puzzle/new/easy' => ['SudokuController', 'getNewPuzzle'],
    'GET:/puzzle/new/medium' => ['SudokuController', 'getNewPuzzle'], 
    'GET:/puzzle/new/hard' => ['SudokuController', 'getNewPuzzle'],
    'GET:/puzzle/new/expert' => ['SudokuController', 'getNewPuzzle'],
    'GET:/puzzle/new/master' => ['SudokuController', 'getNewPuzzle'],
    'GET:/puzzle/{id}' => ['SudokuController', 'getPuzzle'],
    'POST:/puzzle/validate' => ['SudokuController', 'validateSolution'],
    
    // Rutas de Juego
    'POST:/game/save' => ['GameController', 'saveProgress'],
    'POST:/game/complete' => ['GameController', 'completePuzzle'],
    'GET:/game/current' => ['GameController', 'getCurrentGame'],
    'GET:/game/history' => ['GameController', 'getGameHistory'],
    
    // Rutas de Estadísticas
    'GET:/stats' => ['StatsController', 'getUserStats'],
    'POST:/stats/update' => ['StatsController', 'updateStats'],
    'GET:/stats/leaderboard' => ['StatsController', 'getLeaderboard'],
    
    // Ruta de pistas
    'POST:/hint' => ['SudokuController', 'getHint'],
    
    // Configuración de usuario
    'GET:/user/settings' => ['GameController', 'getUserSettings'],
    'POST:/user/settings' => ['GameController', 'updateUserSettings'],
];
