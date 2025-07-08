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

} else {
    // 404
    respondJson(['error' => 'Endpoint no encontrado: ' . $method . ' ' . $path], 404);
}

?>
