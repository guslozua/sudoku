<?php
/**
 * 🎯 API SIMPLE SUDOKU - Versión 1.0
 * Manejo básico de rutas sin Laravel completo
 */

// Headers para JSON y CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// Manejo de preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de base de datos
$host = '127.0.0.1';
$dbName = 'sudoku';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
    exit();
}

// Iniciar sesión si no existe
if (!session_id()) {
    session_start();
}

// Obtener la ruta de la URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Limpiar la ruta
$path = str_replace('/Sudoku/public/api', '', $path);
$path = trim($path, '/');

// Router simple
$method = $_SERVER['REQUEST_METHOD'];

// Función para responder JSON
function respondJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Función para obtener usuario de sesión
function getUserId($pdo) {
    $sessionId = $_SESSION['sudoku_session_id'] ?? null;
    
    if (!$sessionId) {
        $sessionId = uniqid('sudoku_', true);
        $_SESSION['sudoku_session_id'] = $sessionId;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (session_id, is_anonymous, is_premium, preferred_difficulty, theme_preference, created_at, updated_at) VALUES (?, 1, 0, 'medium', 'auto', NOW(), NOW())");
        $stmt->execute([$sessionId]);
        return $pdo->lastInsertId();
    }

    return $user['id'];
}

// Routing
if ($method === 'GET' && preg_match('/^puzzle\/new\/(.+)$/', $path, $matches)) {
    // Nuevo puzzle
    $difficulty = $matches[1];
    
    $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
    if (!in_array($difficulty, $validDifficulties)) {
        respondJson(['error' => 'Dificultad inválida'], 400);
    }

    try {
        // Obtener puzzle aleatorio
        $stmt = $pdo->prepare("SELECT * FROM puzzles WHERE difficulty_level = ? AND is_valid = 1 ORDER BY RAND() LIMIT 1");
        $stmt->execute([$difficulty]);
        $puzzle = $stmt->fetch();

        if (!$puzzle) {
            respondJson(['error' => 'No hay puzzles disponibles'], 404);
        }

        // Crear juego
        $userId = getUserId($pdo);
        $stmt = $pdo->prepare("INSERT INTO games (user_id, puzzle_id, current_state, initial_state, status, hints_used, mistakes_count, moves_count, started_at, last_played_at) VALUES (?, ?, ?, ?, 'in_progress', 0, 0, 0, NOW(), NOW())");
        $stmt->execute([$userId, $puzzle['id'], $puzzle['puzzle_string'], $puzzle['puzzle_string']]);
        $gameId = $pdo->lastInsertId();

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

    } catch (Exception $e) {
        respondJson(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'GET' && $path === 'game/current') {
    // Juego actual
    try {
        $userId = getUserId($pdo);
        
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
            respondJson(['message' => 'No hay juegos en progreso'], 404);
        }

        respondJson(['success' => true, 'game' => $game]);

    } catch (Exception $e) {
        respondJson(['error' => 'Error: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'POST' && $path === 'game/save') {
    // Guardar progreso
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $gameId = $input['game_id'] ?? null;
        $currentState = $input['current_state'] ?? null;
        
        if (!$gameId || !$currentState) {
            respondJson(['error' => 'Datos faltantes'], 400);
        }

        $stmt = $pdo->prepare("UPDATE games SET current_state = ?, last_played_at = NOW() WHERE id = ?");
        $stmt->execute([$currentState, $gameId]);
        
        respondJson(['success' => true, 'message' => 'Progreso guardado']);

    } catch (Exception $e) {
        respondJson(['error' => 'Error: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'GET' && $path === 'achievements') {
    // Logros
    try {
        $userId = getUserId($pdo);
        
        $stmt = $pdo->prepare("
            SELECT a.*, CASE WHEN ua.id IS NOT NULL THEN 1 ELSE 0 END as is_completed
            FROM achievements a
            LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
            ORDER BY a.category, a.id
        ");
        $stmt->execute([$userId]);
        $achievements = $stmt->fetchAll();
        
        respondJson(['success' => true, 'achievements' => $achievements]);

    } catch (Exception $e) {
        respondJson(['error' => 'Error: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'GET' && $path === 'analytics/dashboard') {
    // Dashboard analítico
    try {
        $userId = getUserId($pdo);
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_games,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games
            FROM games 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $userStats = $stmt->fetch();
        
        respondJson([
            'success' => true,
            'data' => [
                'user_stats' => $userStats,
                'difficulty_stats' => []
            ]
        ]);

    } catch (Exception $e) {
        respondJson(['error' => 'Error: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'GET' && $path === 'analytics/progress') {
    // Progreso analítico
    try {
        respondJson([
            'success' => true,
            'data' => [
                'daily_totals' => [],
                'current_streak' => 0,
                'best_streak' => 0,
                'period_days' => 30
            ]
        ]);

    } catch (Exception $e) {
        respondJson(['error' => 'Error: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'POST' && $path === 'hint') {
    // Sistema de pistas
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $gameId = $input['game_id'] ?? null;
        $currentState = $input['current_state'] ?? null;
        
        if (!$gameId || !$currentState) {
            respondJson(['error' => 'Datos faltantes: game_id y current_state requeridos'], 400);
        }
        
        $userId = getUserId($pdo);
        
        // Verificar que el juego existe y pertenece al usuario
        $stmt = $pdo->prepare("SELECT hints_used, puzzle_id FROM games WHERE id = ? AND user_id = ?");
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            respondJson(['error' => 'Juego no encontrado'], 404);
        }
        
        // Verificar límite de pistas (máximo 3 por juego)
        if ($game['hints_used'] >= 3) {
            respondJson(['error' => 'Límite de pistas alcanzado (máximo 3 por juego)'], 403);
        }
        
        // Obtener la solución del puzzle
        $stmt = $pdo->prepare("SELECT solution_string FROM puzzles WHERE id = ?");
        $stmt->execute([$game['puzzle_id']]);
        $puzzle = $stmt->fetch();
        
        if (!$puzzle) {
            respondJson(['error' => 'Puzzle no encontrado'], 404);
        }
        
        // Generar pista inteligente
        $hint = generateSmartHint($currentState, $puzzle['solution_string']);
        
        if (!$hint) {
            respondJson(['error' => 'No se pudo generar una pista válida'], 500);
        }
        
        // Incrementar contador de pistas usadas
        $stmt = $pdo->prepare("UPDATE games SET hints_used = hints_used + 1 WHERE id = ?");
        $stmt->execute([$gameId]);
        
        respondJson([
            'success' => true,
            'hint' => $hint,
            'hints_remaining' => 3 - ($game['hints_used'] + 1)
        ]);
        
    } catch (Exception $e) {
        respondJson(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }

} elseif ($method === 'POST' && $path === 'game/complete') {
    // Completar juego
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $gameId = $input['game_id'] ?? null;
        $currentState = $input['current_state'] ?? null;
        $timeSpent = $input['time_spent'] ?? 0;
        $movesCount = $input['moves_count'] ?? 0;
        $hintsUsed = $input['hints_used'] ?? 0;
        $mistakesCount = $input['mistakes_count'] ?? 0;
        
        if (!$gameId || !$currentState) {
            respondJson(['error' => 'Datos faltantes: game_id y current_state requeridos'], 400);
        }
        
        $userId = getUserId($pdo);
        
        // Verificar que el juego existe y pertenece al usuario
        $stmt = $pdo->prepare("SELECT puzzle_id FROM games WHERE id = ? AND user_id = ?");
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            respondJson(['error' => 'Juego no encontrado'], 404);
        }
        
        // Actualizar el juego como completado
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
            WHERE id = ?
        ");
        $stmt->execute([
            $currentState,
            $timeSpent,
            $movesCount,
            $hintsUsed,
            $mistakesCount,
            $gameId
        ]);
        
        // 🏆 VERIFICAR Y DESBLOQUEAR LOGROS
        $newAchievements = checkAndUnlockAchievements($pdo, $userId, $timeSpent, $movesCount, $hintsUsed, $mistakesCount);
        
        respondJson([
            'success' => true,
            'message' => 'Juego completado exitosamente',
            'new_achievements' => $newAchievements
        ]);
        
    } catch (Exception $e) {
        respondJson(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }

} else {
    // 404
    respondJson(['error' => 'Endpoint no encontrado: ' . $method . ' ' . $path], 404);
}

/**
 * Generar pista inteligente
 * @param string $currentState - Estado actual del tablero (string de 81 caracteres)
 * @param string $solution - Solución completa del puzzle (string de 81 caracteres)
 * @return array|null - Array con la pista o null si no se puede generar
 */
function generateSmartHint($currentState, $solution) {
    // Convertir strings a arrays 2D
    $current = stringToArray($currentState);
    $correct = stringToArray($solution);
    
    // Encontrar todas las celdas vacías
    $emptyCells = [];
    for ($row = 0; $row < 9; $row++) {
        for ($col = 0; $col < 9; $col++) {
            if ($current[$row][$col] == 0) {
                $emptyCells[] = ['row' => $row, 'col' => $col];
            }
        }
    }
    
    if (empty($emptyCells)) {
        return null; // No hay celdas vacías
    }
    
    // Priorizar celdas con menos opciones posibles (estrategia más inteligente)
    $bestCells = [];
    $minOptions = 10;
    
    foreach ($emptyCells as $cell) {
        $row = $cell['row'];
        $col = $cell['col'];
        $possibleNumbers = getPossibleNumbers($current, $row, $col);
        $optionsCount = count($possibleNumbers);
        
        if ($optionsCount > 0 && $optionsCount < $minOptions) {
            $minOptions = $optionsCount;
            $bestCells = [$cell];
        } elseif ($optionsCount === $minOptions) {
            $bestCells[] = $cell;
        }
    }
    
    if (empty($bestCells)) {
        // Fallback: usar una celda aleatoria vacía
        $bestCells = $emptyCells;
    }
    
    // Seleccionar una celda aleatoria de las mejores opciones
    $selectedCell = $bestCells[array_rand($bestCells)];
    $row = $selectedCell['row'];
    $col = $selectedCell['col'];
    
    // Obtener el número correcto de la solución
    $correctNumber = $correct[$row][$col];
    
    // Generar explicación inteligente
    $explanation = generateExplanation($current, $row, $col, $correctNumber);
    
    return [
        'row' => $row,
        'col' => $col,
        'number' => $correctNumber,
        'explanation' => $explanation
    ];
}

/**
 * Convertir string de 81 caracteres a array 2D de 9x9
 */
function stringToArray($puzzleString) {
    $array = [];
    for ($i = 0; $i < 9; $i++) {
        $row = [];
        for ($j = 0; $j < 9; $j++) {
            $row[] = intval($puzzleString[$i * 9 + $j]);
        }
        $array[] = $row;
    }
    return $array;
}

/**
 * Obtener números posibles para una celda
 */
function getPossibleNumbers($board, $row, $col) {
    $possible = [];
    
    for ($num = 1; $num <= 9; $num++) {
        if (isNumberValid($board, $row, $col, $num)) {
            $possible[] = $num;
        }
    }
    
    return $possible;
}

/**
 * Verificar si un número es válido en una posición
 */
function isNumberValid($board, $row, $col, $num) {
    // Verificar fila
    for ($c = 0; $c < 9; $c++) {
        if ($board[$row][$c] == $num) {
            return false;
        }
    }
    
    // Verificar columna
    for ($r = 0; $r < 9; $r++) {
        if ($board[$r][$col] == $num) {
            return false;
        }
    }
    
    // Verificar subcuadro 3x3
    $startRow = floor($row / 3) * 3;
    $startCol = floor($col / 3) * 3;
    
    for ($r = $startRow; $r < $startRow + 3; $r++) {
        for ($c = $startCol; $c < $startCol + 3; $c++) {
            if ($board[$r][$c] == $num) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Generar explicación inteligente para la pista
 */
function generateExplanation($board, $row, $col, $number) {
    $possibleNumbers = getPossibleNumbers($board, $row, $col);
    $optionsCount = count($possibleNumbers);
    
    // Coordenadas humanas (1-indexed)
    $humanRow = $row + 1;
    $humanCol = $col + 1;
    $quadrant = getQuadrantName($row, $col);
    
    if ($optionsCount == 1) {
        return "En la celda fila {$humanRow}, columna {$humanCol} ({$quadrant}), solo el número {$number} es posible según las reglas del Sudoku.";
    } elseif ($optionsCount <= 3) {
        $possibleStr = implode(', ', $possibleNumbers);
        return "En la celda fila {$humanRow}, columna {$humanCol} ({$quadrant}), las opciones son limitadas: {$possibleStr}. El número correcto es {$number}.";
    } else {
        $reason = getHintReason($board, $row, $col, $number);
        return "En la celda fila {$humanRow}, columna {$humanCol} ({$quadrant}), {$reason} El número correcto es {$number}.";
    }
}

/**
 * Obtener nombre del cuadrante para hacer la explicación más clara
 */
function getQuadrantName($row, $col) {
    $quadrantRow = floor($row / 3);
    $quadrantCol = floor($col / 3);
    
    $names = [
        '0,0' => 'cuadrante superior izquierdo',
        '0,1' => 'cuadrante superior central',
        '0,2' => 'cuadrante superior derecho',
        '1,0' => 'cuadrante central izquierdo', 
        '1,1' => 'cuadrante central',
        '1,2' => 'cuadrante central derecho',
        '2,0' => 'cuadrante inferior izquierdo',
        '2,1' => 'cuadrante inferior central',
        '2,2' => 'cuadrante inferior derecho'
    ];
    
    return $names["$quadrantRow,$quadrantCol"] ?? 'cuadrante';
}

/**
 * Generar razón específica para la pista
 */
function getHintReason($board, $row, $col, $number) {
    // Analizar por qué este número es óptimo
    
    // Verificar si completaría una fila/columna/cuadrante
    $rowCount = 0;
    $colCount = 0;
    for ($i = 0; $i < 9; $i++) {
        if ($board[$row][$i] != 0) $rowCount++;
        if ($board[$i][$col] != 0) $colCount++;
    }
    
    if ($rowCount >= 7) {
        return "esta fila está casi completa y este número encaja perfectamente.";
    }
    
    if ($colCount >= 7) {
        return "esta columna está casi completa y este número es necesario.";
    }
    
    // Verificar cuadrante
    $startRow = floor($row / 3) * 3;
    $startCol = floor($col / 3) * 3;
    $quadrantCount = 0;
    
    for ($r = $startRow; $r < $startRow + 3; $r++) {
        for ($c = $startCol; $c < $startCol + 3; $c++) {
            if ($board[$r][$c] != 0) $quadrantCount++;
        }
    }
    
    if ($quadrantCount >= 7) {
        return "este cuadrante está casi completo.";
    }
    
    return "analizando las restricciones de fila, columna y cuadrante, esta es la mejor opción.";
}

/**
 * 🏆 VERIFICAR Y DESBLOQUEAR LOGROS
 * @param PDO $pdo - Conexión a la base de datos
 * @param int $userId - ID del usuario
 * @param int $timeSpent - Tiempo en segundos
 * @param int $movesCount - Número de movimientos
 * @param int $hintsUsed - Pistas utilizadas
 * @param int $mistakesCount - Errores cometidos
 * @return array - Array de nuevos logros desbloqueados
 */
function checkAndUnlockAchievements($pdo, $userId, $timeSpent, $movesCount, $hintsUsed, $mistakesCount) {
    $newAchievements = [];
    
    try {
        // Obtener estadísticas del usuario
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_completed,
                COUNT(CASE WHEN hints_used = 0 THEN 1 END) as no_hints_games,
                COUNT(CASE WHEN mistakes_count = 0 THEN 1 END) as perfect_games,
                MIN(time_spent) as best_time,
                MIN(moves_count) as fewest_moves
            FROM games 
            WHERE user_id = ? AND status = 'completed'
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        // Obtener todos los logros disponibles y activos
        $stmt = $pdo->prepare("SELECT * FROM achievements WHERE is_active = 1 ORDER BY id");
        $stmt->execute();
        $allAchievements = $stmt->fetchAll();
        
        // Obtener logros ya desbloqueados por el usuario
        $stmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
        $stmt->execute([$userId]);
        $unlockedIds = array_column($stmt->fetchAll(), 'achievement_id');
        
        // Obtener datos del último juego para verificar dificultad
        $stmt = $pdo->prepare("
            SELECT p.difficulty_level 
            FROM games g 
            JOIN puzzles p ON g.puzzle_id = p.id 
            WHERE g.user_id = ? AND g.status = 'completed'
            ORDER BY g.completed_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $lastGame = $stmt->fetch();
        $currentDifficulty = $lastGame['difficulty_level'] ?? 'easy';
        
        // Obtener estadísticas por dificultad
        $stmt = $pdo->prepare("
            SELECT 
                p.difficulty_level,
                COUNT(*) as count
            FROM games g 
            JOIN puzzles p ON g.puzzle_id = p.id 
            WHERE g.user_id = ? AND g.status = 'completed'
            GROUP BY p.difficulty_level
        ");
        $stmt->execute([$userId]);
        $difficultyStats = [];
        while ($row = $stmt->fetch()) {
            $difficultyStats[$row['difficulty_level']] = $row['count'];
        }
        
        foreach ($allAchievements as $achievement) {
            // Saltar si ya está desbloqueado
            if (in_array($achievement['id'], $unlockedIds)) {
                continue;
            }
            
            $shouldUnlock = false;
            
            // Verificar condiciones según el key_name del logro
            switch ($achievement['key_name']) {
                case 'first_step':
                    $shouldUnlock = $stats['total_completed'] >= 1;
                    break;
                    
                case 'puzzle_master':
                    $shouldUnlock = $stats['total_completed'] >= 10;
                    break;
                    
                case 'sudoku_legend':
                    $shouldUnlock = $stats['total_completed'] >= 50;
                    break;
                    
                case 'speed_demon':
                    $shouldUnlock = $timeSpent <= 300; // 5 minutos
                    break;
                    
                case 'lightning_fast':
                    $shouldUnlock = $timeSpent <= 180; // 3 minutos
                    break;
                    
                case 'easy_champion':
                    $shouldUnlock = ($difficultyStats['easy'] ?? 0) >= 5;
                    break;
                    
                case 'expert_challenger':
                    $shouldUnlock = ($difficultyStats['expert'] ?? 0) >= 1;
                    break;
                    
                case 'master_conqueror':
                    $shouldUnlock = ($difficultyStats['master'] ?? 0) >= 1;
                    break;
                    
                case 'strategic_mind':
                    $shouldUnlock = $hintsUsed == 0;
                    break;
                    
                case 'hint_seeker':
                    // Total de pistas usadas en todos los juegos
                    $stmt = $pdo->prepare("
                        SELECT SUM(hints_used) as total_hints 
                        FROM games 
                        WHERE user_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$userId]);
                    $totalHints = $stmt->fetchColumn() ?: 0;
                    $shouldUnlock = $totalHints >= 10;
                    break;
                    
                case 'winning_streak':
                    // Verificar racha de 3 juegos (simplificado)
                    $shouldUnlock = $stats['total_completed'] >= 3;
                    break;
                    
                case 'unstoppable':
                    // Verificar racha de 5 juegos (simplificado)
                    $shouldUnlock = $stats['total_completed'] >= 5;
                    break;
                    
                case 'perfect_game':
                    $shouldUnlock = $mistakesCount == 0;
                    break;
                    
                case 'efficient_solver':
                    $shouldUnlock = $movesCount <= 100;
                    break;
            }
            
            // Desbloquear logro si se cumple la condición
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
                
                // Log para debugging
                error_log("🏆 Logro desbloqueado: {$achievement['name']} para usuario $userId");
            }
        }
        
    } catch (Exception $e) {
        // Log error but don't fail the game completion
        error_log("❌ Error checking achievements: " . $e->getMessage());
    }
    
    return $newAchievements;
}

?>
