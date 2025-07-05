<?php

// Router simplificado para las APIs del Sudoku - VERSIÓN AUTOCONTENIDA

// Inicializar sesión
if (!session_id()) {
    session_start();
}

// Controlador simplificado integrado
class SudokuController
{
    private $pdo;
    
    public function __construct()
    {
        try {
            $this->pdo = new PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            $this->respondError('Error de conexión a base de datos: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener un nuevo puzzle según la dificultad
     */
    public function getNewPuzzle($difficulty = 'easy')
    {
        try {
            // Log para debugging
            error_log("🎯 SudokuController::getNewPuzzle called with difficulty: $difficulty");
            
            // Validar dificultad
            $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
            if (!in_array($difficulty, $validDifficulties)) {
                return $this->respondError('Dificultad inválida', 400);
            }

            // Obtener puzzle aleatorio de la dificultad solicitada
            $stmt = $this->pdo->prepare("SELECT * FROM puzzles WHERE difficulty_level = ? ORDER BY RAND() LIMIT 1");
            $stmt->execute([$difficulty]);
            $puzzle = $stmt->fetch();

            if (!$puzzle) {
                return $this->respondError('No hay puzzles disponibles para esta dificultad', 404);
            }

            // Obtener o crear usuario de sesión
            $userId = $this->getOrCreateSessionUser();

            // Crear nuevo juego
            $stmt = $this->pdo->prepare("INSERT INTO games (user_id, puzzle_id, current_state, initial_state, status, hints_used, mistakes_count, moves_count, started_at, last_played_at) VALUES (?, ?, ?, ?, 'in_progress', 0, 0, 0, NOW(), NOW())");
            $stmt->execute([
                $userId,
                $puzzle['id'],
                $puzzle['puzzle_string'],
                $puzzle['puzzle_string']
            ]);
            
            $gameId = $this->pdo->lastInsertId();
            
            error_log("✅ Puzzle creado exitosamente. Game ID: $gameId");

            return $this->respondSuccess([
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
            error_log("❌ Error en getNewPuzzle: " . $e->getMessage());
            return $this->respondError('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtener o crear usuario de sesión anónimo
     */
    private function getOrCreateSessionUser()
    {
        $sessionId = $_SESSION['sudoku_session_id'] ?? null;
        
        if (!$sessionId) {
            $sessionId = uniqid('sudoku_', true);
            $_SESSION['sudoku_session_id'] = $sessionId;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $stmt = $this->pdo->prepare("INSERT INTO users (session_id, is_anonymous, is_premium, preferred_difficulty, theme_preference, created_at, updated_at) VALUES (?, 1, 0, 'medium', 'auto', NOW(), NOW())");
            $stmt->execute([$sessionId]);
            return $this->pdo->lastInsertId();
        }

        return $user['id'];
    }
    
    private function respondSuccess($data)
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true] + $data);
        exit;
    }
    
    private function respondError($message, $code = 400)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message, 'success' => false]);
        exit;
    }
}

// Obtener la ruta solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remover query parameters
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// Log para debugging
error_log("🌐 API Request: $method $requestUri");

// Remover prefijos
$requestUri = preg_replace('#^/Sudoku/public/api#', '', $requestUri);
$requestUri = rtrim($requestUri, '/');

// Headers CORS y JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, Accept');
header('Content-Type: application/json');

// Manejar OPTIONS request (CORS preflight)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Instanciar controlador
    $controller = new SudokuController();
    
    // Routing básico
    if ($method === 'GET') {
        // GET /puzzle/new/{difficulty}
        if (preg_match('#^/puzzle/new/(\w+)$#', $requestUri, $matches)) {
            $difficulty = $matches[1];
            error_log("🎯 Ruta detectada: puzzle/new/$difficulty");
            $controller->getNewPuzzle($difficulty);
            exit;
        }
        
        // GET /puzzle/{id}
        if (preg_match('#^/puzzle/(\d+)$#', $requestUri, $matches)) {
            $puzzleId = $matches[1];
            // Implementar si es necesario
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Endpoint no implementado aún']);
            exit;
        }
    }
    
    if ($method === 'POST') {
        // POST /puzzle/validate
        if ($requestUri === '/puzzle/validate') {
            // Implementar si es necesario
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Endpoint no implementado aún']);
            exit;
        }
        
        // POST /game/save
        if ($requestUri === '/game/save') {
            // Implementar si es necesario
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Endpoint no implementado aún']);
            exit;
        }
    }
    
    // Ruta no encontrada
    error_log("❌ Ruta no encontrada: $requestUri");
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Endpoint no encontrado', 'requested_path' => $requestUri]);
    
} catch (Exception $e) {
    error_log("💥 Error crítico en API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error interno del servidor', 'message' => $e->getMessage()]);
}
