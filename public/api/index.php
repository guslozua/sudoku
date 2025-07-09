<?php
/**
 * 🎯 API SUDOKU - Versión CORREGIDA
 * Eliminado código duplicado y queries rotas
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ✅ LOGGING MEJORADO
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

// Configuración BD
$host = '127.0.0.1';
$dbName = 'sudoku';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("❌ DB Connection Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

// ✅ INICIAR SESIÓN UNA SOLA VEZ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener ruta
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/Sudoku/public/api', '', $path);
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Log de requests
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $method,
    'path' => $path,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];
error_log("📡 API Request: " . json_encode($logData));

// Función para responder JSON
function respondJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// ✅ FUNCIÓN MEJORADA PARA OBTENER USUARIO
function getUserId($pdo) {
    $sessionId = $_SESSION['sudoku_session_id'] ?? null;
    
    if (!$sessionId) {
        $sessionId = 'sudoku_' . uniqid() . '.' . mt_rand();
        $_SESSION['sudoku_session_id'] = $sessionId;
    }

    // ✅ USAR TRANSACCIÓN PARA EVITAR RACE CONDITIONS
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $stmt = $pdo->prepare("
                INSERT INTO users (session_id, is_anonymous, is_premium, preferred_difficulty, theme_preference, created_at, updated_at) 
                VALUES (?, 1, 0, 'medium', 'auto', NOW(), NOW())
            ");
            $stmt->execute([$sessionId]);
            $userId = $pdo->lastInsertId();
            $pdo->commit();
            error_log("👤 Nuevo usuario creado: ID $userId, Session: $sessionId");
            return $userId;
        }
        
        $pdo->commit();
        error_log("👤 Usuario existente: ID {$user['id']}, Session: $sessionId");
        return $user['id'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("❌ Error creando usuario: " . $e->getMessage());
        throw $e;
    }
}

// ✅ FUNCIÓN PARA VALIDAR INPUTS
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        if ($rule['required'] && !isset($data[$field])) {
            $errors[] = "Campo {$field} es requerido";
        }
        
        if (isset($data[$field]) && isset($rule['type'])) {
            switch ($rule['type']) {
                case 'int':
                    if (!is_numeric($data[$field])) {
                        $errors[] = "Campo {$field} debe ser numérico";
                    }
                    break;
                case 'string':
                    if (!is_string($data[$field])) {
                        $errors[] = "Campo {$field} debe ser texto";
                    }
                    break;
                case 'game_state':
                    if (strlen($data[$field]) !== 81) {
                        $errors[] = "Campo {$field} debe tener exactamente 81 caracteres";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

// ✅ ROUTING LIMPIO (SIN DUPLICADOS)
try {
    if ($method === 'GET' && preg_match('/^puzzle\/new\/(.+)$/', $path, $matches)) {
        // ✅ NUEVO PUZZLE
        $difficulty = $matches[1];
        
        $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
        if (!in_array($difficulty, $validDifficulties)) {
            error_log("❌ Dificultad inválida: $difficulty");
            respondJson(['error' => 'Dificultad inválida. Opciones: ' . implode(', ', $validDifficulties)], 400);
        }

        error_log("🔍 Buscando puzzle de dificultad: $difficulty");
        
        $stmt = $pdo->prepare("
            SELECT * FROM puzzles 
            WHERE difficulty_level = ? AND (is_valid = 1 OR is_valid IS NULL) 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute([$difficulty]);
        $puzzle = $stmt->fetch();

        if (!$puzzle) {
            error_log("❌ No hay puzzles disponibles para dificultad: $difficulty");
            respondJson(['error' => "No hay puzzles disponibles para dificultad: $difficulty"], 404);
        }

        $userId = getUserId($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO games (user_id, puzzle_id, current_state, initial_state, status, hints_used, mistakes_count, moves_count, started_at, last_played_at) 
            VALUES (?, ?, ?, ?, 'in_progress', 0, 0, 0, NOW(), NOW())
        ");
        $stmt->execute([$userId, $puzzle['id'], $puzzle['puzzle_string'], $puzzle['puzzle_string']]);
        $gameId = $pdo->lastInsertId();

        error_log("✅ Nuevo juego creado: Game ID $gameId, Puzzle ID {$puzzle['id']}");

        respondJson([
            'success' => true,
            'puzzle' => [
                'id' => $puzzle['id'],
                'puzzle_string' => $puzzle['puzzle_string'],
                'solution_string' => $puzzle['solution_string'],
                'difficulty_level' => $puzzle['difficulty_level'],
                'clues_count' => $puzzle['clues_count']
            ],
            'game_id' => $gameId
        ]);

    } elseif ($method === 'GET' && $path === 'game/current') {
        // ✅ JUEGO ACTUAL (QUERY CORREGIDA)
        $userId = getUserId($pdo);
        
        error_log("🔍 Buscando juego actual para usuario: $userId");
        
        $stmt = $pdo->prepare("
            SELECT g.*, p.puzzle_string, p.solution_string, p.difficulty_level
            FROM games g
            JOIN puzzles p ON g.puzzle_id = p.id
            WHERE g.user_id = ? AND g.status = 'in_progress'
            ORDER BY g.last_played_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $game = $stmt->fetch();

        if (!$game) {
            error_log("ℹ️ No hay juegos en progreso para usuario: $userId");
            respondJson(['message' => 'No hay juegos en progreso'], 404);
        }

        error_log("✅ Juego actual encontrado: Game ID {$game['id']}");
        respondJson(['success' => true, 'game' => $game]);

    } elseif ($method === 'POST' && $path === 'game/save') {
        // ✅ GUARDAR PROGRESO
        $input = json_decode(file_get_contents('php://input'), true);
        
        $errors = validateInput($input, [
            'game_id' => ['required' => true, 'type' => 'int'],
            'current_state' => ['required' => true, 'type' => 'game_state']
        ]);
        
        if (!empty($errors)) {
            respondJson(['error' => 'Errores de validación', 'details' => $errors], 400);
        }
        
        $gameId = $input['game_id'];
        $currentState = $input['current_state'];
        $timeSpent = $input['time_spent'] ?? 0;
        $movesCount = $input['moves_count'] ?? 0;
        $hintsUsed = $input['hints_used'] ?? 0;
        
        error_log("💾 Guardando progreso: Game ID $gameId");

        $stmt = $pdo->prepare("
            UPDATE games SET 
                current_state = ?, 
                time_spent = ?,
                moves_count = ?,
                hints_used = ?,
                last_played_at = NOW() 
            WHERE id = ?
        ");
        $result = $stmt->execute([$currentState, $timeSpent, $movesCount, $hintsUsed, $gameId]);
        
        if ($stmt->rowCount() === 0) {
            error_log("❌ No se pudo actualizar game ID: $gameId");
            respondJson(['error' => 'Juego no encontrado o sin cambios'], 404);
        }
        
        error_log("✅ Progreso guardado exitosamente: Game ID $gameId");
        respondJson(['success' => true, 'message' => 'Progreso guardado exitosamente']);

    } elseif ($method === 'POST' && $path === 'game/complete') {
        // ✅ COMPLETAR JUEGO
        $input = json_decode(file_get_contents('php://input'), true);
        
        $errors = validateInput($input, [
            'game_id' => ['required' => true, 'type' => 'int'],
            'current_state' => ['required' => true, 'type' => 'game_state']
        ]);
        
        if (!empty($errors)) {
            respondJson(['error' => 'Errores de validación', 'details' => $errors], 400);
        }
        
        $gameId = $input['game_id'];
        $currentState = $input['current_state'];
        $timeSpent = $input['time_spent'] ?? 0;
        $movesCount = $input['moves_count'] ?? 0;
        $hintsUsed = $input['hints_used'] ?? 0;
        $mistakesCount = $input['mistakes_count'] ?? 0;
        
        $userId = getUserId($pdo);
        
        error_log("🏁 Completando juego: Game ID $gameId, Usuario: $userId");

        $stmt = $pdo->prepare("
            UPDATE games SET 
                current_state = ?, 
                status = 'completed',
                time_spent = ?,
                moves_count = ?,
                hints_used = ?,
                mistakes_count = ?,
                completed_at = NOW(),
                last_played_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $result = $stmt->execute([
            $currentState, $timeSpent, $movesCount, 
            $hintsUsed, $mistakesCount, $gameId, $userId
        ]);
        
        if ($stmt->rowCount() === 0) {
            error_log("❌ No se pudo completar game ID: $gameId");
            respondJson(['error' => 'Juego no encontrado'], 404);
        }
        
        // ✅ VERIFICAR Y DESBLOQUEAR LOGROS
        $newAchievements = checkAndUnlockAchievements($pdo, $userId, $timeSpent, $movesCount, $hintsUsed, $mistakesCount);
        
        error_log("✅ Juego completado exitosamente: Game ID $gameId, Logros: " . count($newAchievements));
        
        respondJson([
            'success' => true,
            'message' => 'Juego completado exitosamente',
            'new_achievements' => $newAchievements
        ]);

    } elseif ($method === 'GET' && $path === 'stats') {
        // ✅ ESTADÍSTICAS (ÚNICA DEFINICIÓN)
        $userId = getUserId($pdo);
        
        error_log("📊 Obteniendo estadísticas para usuario: $userId");
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_games,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games,
                ROUND(AVG(CASE WHEN status = 'completed' THEN time_spent END), 2) as avg_completion_time,
                MIN(CASE WHEN status = 'completed' THEN time_spent END) as best_time,
                SUM(CASE WHEN status = 'completed' THEN time_spent ELSE 0 END) as total_time_played,
                SUM(moves_count) as total_moves,
                SUM(hints_used) as total_hints,
                SUM(mistakes_count) as total_mistakes,
                COUNT(CASE WHEN mistakes_count = 0 AND status = 'completed' THEN 1 END) as perfect_games
            FROM games 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        $defaultStats = [
            'total_games' => 0, 'completed_games' => 0, 'avg_completion_time' => 0,
            'best_time' => null, 'total_time_played' => 0, 'total_moves' => 0,
            'total_hints' => 0, 'total_mistakes' => 0, 'perfect_games' => 0
        ];
        
        error_log("✅ Estadísticas obtenidas: " . json_encode($stats ?: $defaultStats));
        
        respondJson([
            'success' => true,
            'stats' => $stats ?: $defaultStats,
            'user_id' => $userId
        ]);

    } elseif ($method === 'GET' && $path === 'achievements') {
        // ✅ LOGROS
        $userId = getUserId($pdo);
        
        error_log("🏆 Obteniendo logros para usuario: $userId");
        
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CASE WHEN ua.id IS NOT NULL THEN 1 ELSE 0 END as is_completed,
                   ua.unlocked_at
            FROM achievements a
            LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
            WHERE a.is_active = 1
            ORDER BY a.category, a.id
        ");
        $stmt->execute([$userId]);
        $achievements = $stmt->fetchAll();
        
        error_log("✅ Logros obtenidos: " . count($achievements) . " total");
        
        respondJson(['success' => true, 'achievements' => $achievements]);

    } elseif ($method === 'POST' && $path === 'hint') {
        // ✅ SISTEMA DE PISTAS
        $input = json_decode(file_get_contents('php://input'), true);
        
        $errors = validateInput($input, [
            'game_id' => ['required' => true, 'type' => 'int'],
            'current_state' => ['required' => true, 'type' => 'game_state']
        ]);
        
        if (!empty($errors)) {
            respondJson(['error' => 'Errores de validación', 'details' => $errors], 400);
        }
        
        $gameId = $input['game_id'];
        $currentState = $input['current_state'];
        
        $userId = getUserId($pdo);
        
        error_log("💡 Solicitando pista: Game ID $gameId, Usuario: $userId");
        
        $stmt = $pdo->prepare("SELECT hints_used, puzzle_id FROM games WHERE id = ? AND user_id = ?");
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            error_log("❌ Juego no encontrado: Game ID $gameId");
            respondJson(['error' => 'Juego no encontrado'], 404);
        }
        
        if ($game['hints_used'] >= 3) {
            error_log("❌ Límite de pistas alcanzado: Game ID $gameId");
            respondJson(['error' => 'Límite de pistas alcanzado (máximo 3 por juego)'], 403);
        }
        
        $stmt = $pdo->prepare("SELECT solution_string FROM puzzles WHERE id = ?");
        $stmt->execute([$game['puzzle_id']]);
        $puzzle = $stmt->fetch();
        
        if (!$puzzle) {
            error_log("❌ Puzzle no encontrado: Puzzle ID {$game['puzzle_id']}");
            respondJson(['error' => 'Puzzle no encontrado'], 404);
        }
        
        // Generar pista
        $hint = generateSmartHint($currentState, $puzzle['solution_string']);
        
        if (!$hint) {
            error_log("❌ No se pudo generar pista: Game ID $gameId");
            respondJson(['error' => 'No se pudo generar una pista válida'], 500);
        }
        
        $stmt = $pdo->prepare("UPDATE games SET hints_used = hints_used + 1 WHERE id = ?");
        $stmt->execute([$gameId]);
        
        error_log("✅ Pista generada: Game ID $gameId, Posición: ({$hint['row']}, {$hint['col']})");
        
        respondJson([
            'success' => true,
            'hint' => $hint,
            'hints_remaining' => 3 - ($game['hints_used'] + 1)
        ]);

    } else {
        error_log("❌ Endpoint no encontrado: $method $path");
        respondJson(['error' => "Endpoint no encontrado: $method $path"], 404);
    }

} catch (Exception $e) {
    error_log("❌ API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    respondJson(['error' => 'Error interno del servidor', 'debug' => $e->getMessage()], 500);
}

// ✅ FUNCIÓN OPTIMIZADA PARA GENERAR PISTAS
function generateSmartHint($currentState, $solution) {
    $current = str_split($currentState);
    $correct = str_split($solution);
    
    // Encontrar celdas vacías
    $emptyCells = [];
    for ($i = 0; $i < 81; $i++) {
        if ($current[$i] === '0') {
            $emptyCells[] = $i;
        }
    }
    
    if (empty($emptyCells)) {
        return null;
    }
    
    // Priorizar celdas con menos opciones (más inteligente)
    $bestCells = [];
    $minOptions = 10;
    
    foreach ($emptyCells as $index) {
        $row = intval($index / 9);
        $col = $index % 9;
        $board = stringToBoard($currentState);
        $possibleNumbers = getPossibleNumbers($board, $row, $col);
        $optionsCount = count($possibleNumbers);
        
        if ($optionsCount > 0 && $optionsCount < $minOptions) {
            $minOptions = $optionsCount;
            $bestCells = [$index];
        } elseif ($optionsCount === $minOptions) {
            $bestCells[] = $index;
        }
    }
    
    if (empty($bestCells)) {
        $bestCells = $emptyCells; // Fallback
    }
    
    $randomIndex = $bestCells[array_rand($bestCells)];
    $row = intval($randomIndex / 9);
    $col = $randomIndex % 9;
    
    return [
        'row' => $row,
        'col' => $col,
        'number' => $correct[$randomIndex],
        'explanation' => generateExplanation($row, $col, $correct[$randomIndex])
    ];
}

function stringToBoard($puzzleString) {
    $board = [];
    for ($i = 0; $i < 9; $i++) {
        $row = [];
        for ($j = 0; $j < 9; $j++) {
            $row[] = intval($puzzleString[$i * 9 + $j]);
        }
        $board[] = $row;
    }
    return $board;
}

function getPossibleNumbers($board, $row, $col) {
    $possible = [];
    
    for ($num = 1; $num <= 9; $num++) {
        if (isNumberValid($board, $row, $col, $num)) {
            $possible[] = $num;
        }
    }
    
    return $possible;
}

function isNumberValid($board, $row, $col, $num) {
    // Verificar fila
    for ($c = 0; $c < 9; $c++) {
        if ($board[$row][$c] == $num) return false;
    }
    
    // Verificar columna
    for ($r = 0; $r < 9; $r++) {
        if ($board[$r][$col] == $num) return false;
    }
    
    // Verificar subcuadro 3x3
    $startRow = floor($row / 3) * 3;
    $startCol = floor($col / 3) * 3;
    
    for ($r = $startRow; $r < $startRow + 3; $r++) {
        for ($c = $startCol; $c < $startCol + 3; $c++) {
            if ($board[$r][$c] == $num) return false;
        }
    }
    
    return true;
}

function generateExplanation($row, $col, $number) {
    $humanRow = $row + 1;
    $humanCol = $col + 1;
    
    $explanations = [
        "En la fila {$humanRow}, columna {$humanCol}, el número {$number} es la única opción válida.",
        "Analizando las restricciones de fila, columna y cuadrante, el número {$number} encaja en la posición ({$humanRow}, {$humanCol}).",
        "En la celda fila {$humanRow}, columna {$humanCol}, solo puede ir el número {$number}.",
        "Aplicando las reglas del Sudoku, la celda ({$humanRow}, {$humanCol}) debe contener el número {$number}."
    ];
    
    return $explanations[array_rand($explanations)];
}

// ✅ FUNCIÓN PARA VERIFICAR Y DESBLOQUEAR LOGROS
function checkAndUnlockAchievements($pdo, $userId, $timeSpent, $movesCount, $hintsUsed, $mistakesCount) {
    $newAchievements = [];
    
    try {
        // Obtener estadísticas del usuario
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_completed,
                COUNT(CASE WHEN hints_used = 0 THEN 1 END) as no_hints_games,
                COUNT(CASE WHEN mistakes_count = 0 THEN 1 END) as perfect_games,
                MIN(time_spent) as best_time
            FROM games 
            WHERE user_id = ? AND status = 'completed'
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        // Obtener logros disponibles
        $stmt = $pdo->prepare("SELECT * FROM achievements WHERE is_active = 1");
        $stmt->execute();
        $allAchievements = $stmt->fetchAll();
        
        // Obtener logros ya desbloqueados
        $stmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
        $stmt->execute([$userId]);
        $unlockedIds = array_column($stmt->fetchAll(), 'achievement_id');
        
        foreach ($allAchievements as $achievement) {
            if (in_array($achievement['id'], $unlockedIds)) {
                continue; // Ya desbloqueado
            }
            
            $shouldUnlock = false;
            
            switch ($achievement['key_name']) {
                case 'first_step':
                    $shouldUnlock = $stats['total_completed'] >= 1;
                    break;
                case 'puzzle_master':
                    $shouldUnlock = $stats['total_completed'] >= 10;
                    break;
                case 'speed_demon':
                    $shouldUnlock = $timeSpent <= 300; // 5 minutos
                    break;
                case 'lightning_fast':
                    $shouldUnlock = $timeSpent <= 180; // 3 minutos
                    break;
                case 'strategic_mind':
                    $shouldUnlock = $hintsUsed == 0;
                    break;
                case 'perfect_game':
                    $shouldUnlock = $mistakesCount == 0;
                    break;
                case 'efficient_solver':
                    $shouldUnlock = $movesCount <= 100;
                    break;
            }
            
            if ($shouldUnlock) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_achievements (user_id, achievement_id, unlocked_at, is_completed) 
                    VALUES (?, ?, NOW(), 1)
                ");
                $stmt->execute([$userId, $achievement['id']]);
                
                $newAchievements[] = [
                    'id' => $achievement['id'],
                    'key_name' => $achievement['key_name'],
                    'name' => $achievement['name'],
                    'description' => $achievement['description'],
                    'icon' => $achievement['icon'],
                    'category' => $achievement['category']
                ];
                
                error_log("🏆 Logro desbloqueado: {$achievement['name']} para usuario $userId");
            }
        }
        
    } catch (Exception $e) {
        error_log("❌ Error checking achievements: " . $e->getMessage());
    }
    
    return $newAchievements;
}
?>
