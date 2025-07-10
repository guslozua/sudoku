<?php
namespace App\Http\Controllers;

/**
 * SudokuController Optimizado
 * Combina funcionalidad de ambos controladores + optimizaciones
 * Sudoku Minimalista v2.0
 */

// Cargar sistema de cache
require_once __DIR__ . '/../../../optimization/cache.php';

class SudokuControllerOptimized extends Controller
{
    private $pdo;
    
    public function __construct()
    {
        try {
            $this->pdo = new \PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_PERSISTENT => true  // Conexiones persistentes para mejor performance
            ]);
        } catch (\PDOException $e) {
            error_log("Error de conexión BD: " . $e->getMessage());
            throw new \Exception('Error de conexión a base de datos');
        }
    }
    
    /**
     * Obtener nuevo puzzle (OPTIMIZADO con cache)
     */
    public function getNewPuzzle($request = null, $difficulty = null)
    {
        try {
            // Manejar diferentes tipos de entrada
            if (is_string($request)) {
                $difficulty = $request;
            } elseif ($request && method_exists($request, 'input')) {
                $difficulty = $request->input('difficulty', $difficulty);
            }
            
            // Validar dificultad
            $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
            if (!in_array($difficulty, $validDifficulties)) {
                return $this->jsonResponse(['error' => 'Dificultad inválida'], 400);
            }

            // OPTIMIZACIÓN: Usar cache para obtener puzzles disponibles
            $puzzles = SudokuCache::getPuzzlesByDifficulty($difficulty);
            
            if (empty($puzzles)) {
                return $this->jsonResponse(['error' => 'No hay puzzles disponibles'], 404);
            }
            
            // Seleccionar puzzle aleatorio del cache
            $puzzle = $puzzles[array_rand($puzzles)];
            
            // Validar puzzle (con cache de validación)
            $validationKey = "puzzle_valid_" . $puzzle['id'];
            $isValid = SimpleCache::remember($validationKey, 3600, function() use ($puzzle) {
                return $this->validatePuzzleBoard($puzzle['puzzle_string']);
            });
            
            if (!$isValid) {
                // Invalidar cache y reintentar
                SudokuCache::invalidatePuzzleCache($difficulty);
                return $this->jsonResponse(['error' => 'Puzzle inválido detectado, intenta de nuevo'], 500);
            }
            
            // Obtener usuario (con cache)
            $userId = $this->getOrCreateSessionUser();

            // Crear nuevo juego
            $gameId = $this->createNewGame($userId, $puzzle);

            return $this->jsonResponse([
                'success' => true,
                'puzzle' => [
                    'id' => $puzzle['id'],
                    'puzzle_string' => $puzzle['puzzle_string'],
                    'solution_string' => $puzzle['solution_string'],
                    'difficulty_level' => $puzzle['difficulty_level'],
                    'clues_count' => $puzzle['clues_count']
                ],
                'game_id' => $gameId,
                'cached' => true
            ]);

        } catch (\Exception $e) {
            error_log("Error en getNewPuzzle: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Error interno del servidor'], 500);
        }
    }
    
    /**
     * Crear nuevo juego (OPTIMIZADO)
     */
    private function createNewGame($userId, $puzzle)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO games (
                user_id, puzzle_id, current_state, initial_state, 
                status, hints_used, mistakes_count, moves_count, 
                started_at, last_played_at
            ) VALUES (?, ?, ?, ?, 'in_progress', 0, 0, 0, NOW(), NOW())
        ");
        
        $stmt->execute([
            $userId,
            $puzzle['id'],
            $puzzle['puzzle_string'],
            $puzzle['puzzle_string']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Guardar progreso (OPTIMIZADO)
     */
    public function saveProgress($request = null)
    {
        try {
            $data = $this->getRequestData($request);
            
            // Validar datos requeridos
            if (!isset($data['game_id']) || !isset($data['current_state'])) {
                return $this->jsonResponse(['error' => 'Datos requeridos faltantes'], 400);
            }
            
            if (strlen($data['current_state']) !== 81) {
                return $this->jsonResponse(['error' => 'Estado del juego inválido'], 400);
            }
            
            // Actualizar con prepared statement optimizado
            $stmt = $this->pdo->prepare("
                UPDATE games SET 
                    current_state = ?, 
                    notes = ?, 
                    moves_count = ?, 
                    last_played_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $userId = $this->getCurrentUserId();
            $updated = $stmt->execute([
                $data['current_state'],
                $data['notes'] ?? '[]',
                $data['moves_count'] ?? 0,
                $data['game_id'],
                $userId
            ]);
            
            if ($stmt->rowCount() === 0) {
                return $this->jsonResponse(['error' => 'Juego no encontrado o sin permisos'], 404);
            }
            
            // Invalidar cache de usuario
            SudokuCache::invalidateUserCache($userId);
            
            return $this->jsonResponse(['success' => true, 'message' => 'Progreso guardado']);

        } catch (\Exception $e) {
            error_log("Error en saveProgress: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Error guardando progreso'], 500);
        }
    }
    
    /**
     * Completar juego (OPTIMIZADO)
     */
    public function completeGame($request = null)
    {
        try {
            $data = $this->getRequestData($request);
            
            // Validar datos
            if (!isset($data['game_id']) || !isset($data['solution']) || !isset($data['completion_time'])) {
                return $this->jsonResponse(['error' => 'Datos requeridos faltantes'], 400);
            }
            
            if (!preg_match('/^[1-9]{81}$/', $data['solution'])) {
                return $this->jsonResponse(['error' => 'Solución inválida'], 400);
            }
            
            $userId = $this->getCurrentUserId();
            
            // Verificar que el juego pertenece al usuario
            $stmt = $this->pdo->prepare("SELECT puzzle_id FROM games WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['game_id'], $userId]);
            $game = $stmt->fetch();
            
            if (!$game) {
                return $this->jsonResponse(['error' => 'Juego no encontrado'], 404);
            }
            
            // Actualizar juego como completado
            $stmt = $this->pdo->prepare("
                UPDATE games SET 
                    status = 'completed',
                    completion_time = ?,
                    completed_at = NOW(),
                    final_state = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['completion_time'],
                $data['solution'],
                $data['game_id']
            ]);
            
            // Invalidar caches relacionados
            SudokuCache::invalidateUserCache($userId);
            SimpleCache::forget("leaderboard_general_10");
            
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Juego completado exitosamente',
                'completion_time' => $data['completion_time']
            ]);

        } catch (\Exception $e) {
            error_log("Error en completeGame: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Error completando juego'], 500);
        }
    }
    
    /**
     * Obtener juego actual (OPTIMIZADO)
     */
    public function getCurrentGame($request = null)
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // Buscar juego en progreso (con cache)
            $cacheKey = "current_game_$userId";
            $game = SimpleCache::remember($cacheKey, 300, function() use ($userId) {
                $stmt = $this->pdo->prepare("
                    SELECT g.*, p.solution_string, p.difficulty_level 
                    FROM games g 
                    JOIN puzzles p ON g.puzzle_id = p.id 
                    WHERE g.user_id = ? AND g.status = 'in_progress' 
                    ORDER BY g.last_played_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                return $stmt->fetch();
            });
            
            if (!$game) {
                return $this->jsonResponse(['success' => false, 'message' => 'No hay juego en progreso']);
            }
            
            return $this->jsonResponse([
                'success' => true,
                'game' => $game
            ]);

        } catch (\Exception $e) {
            error_log("Error en getCurrentGame: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Error obteniendo juego actual'], 500);
        }
    }
    
    /**
     * Validar puzzle board (OPTIMIZADO con memoización)
     */
    private function validatePuzzleBoard($puzzleString)
    {
        // Cache de validación por hash del puzzle
        $hash = md5($puzzleString);
        $cacheKey = "validation_$hash";
        
        return SimpleCache::remember($cacheKey, 86400, function() use ($puzzleString) {
            // Lógica de validación optimizada
            if (strlen($puzzleString) !== 81) {
                return false;
            }
            
            // Verificar que solo contenga números válidos y puntos
            if (!preg_match('/^[0-9.]*$/', $puzzleString)) {
                return false;
            }
            
            // Validación básica de Sudoku
            $grid = str_split($puzzleString);
            
            // Verificar filas, columnas y cajas 3x3
            for ($i = 0; $i < 9; $i++) {
                if (!$this->isValidGroup($this->getRow($grid, $i)) ||
                    !$this->isValidGroup($this->getColumn($grid, $i)) ||
                    !$this->isValidGroup($this->getBox($grid, $i))) {
                    return false;
                }
            }
            
            return true;
        });
    }
    
    /**
     * Obtener datos de request (COMPATIBLE con diferentes formatos)
     */
    private function getRequestData($request)
    {
        if (is_array($request)) {
            return $request;
        } elseif ($request && method_exists($request, 'all')) {
            return $request->all();
        } else {
            // Obtener de $_POST o JSON
            $input = file_get_contents('php://input');
            $json = json_decode($input, true);
            return $json ?: $_POST;
        }
    }
    
    /**
     * Obtener usuario actual (OPTIMIZADO con cache)
     */
    public function getCurrentUserId()
    {
        if (!session_id()) {
            session_start();
        }
        
        $sessionId = $_SESSION['sudoku_session_id'] ?? null;
        
        if ($sessionId) {
            $cacheKey = "user_id_$sessionId";
            $userId = SimpleCache::remember($cacheKey, 1800, function() use ($sessionId) {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                return $user ? $user['id'] : null;
            });
            
            if ($userId) {
                return $userId;
            }
        }
        
        return $this->getOrCreateSessionUser();
    }
    
    /**
     * Crear o obtener usuario de sesión (OPTIMIZADO)
     */
    private function getOrCreateSessionUser()
    {
        if (!session_id()) {
            session_start();
        }
        
        $sessionId = $_SESSION['sudoku_session_id'] ?? null;
        
        if (!$sessionId) {
            $sessionId = uniqid('sudoku_', true);
            $_SESSION['sudoku_session_id'] = $sessionId;
        }

        // Buscar usuario existente
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Crear nuevo usuario
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    session_id, is_anonymous, is_premium, preferred_difficulty, 
                    theme_preference, created_at, updated_at, last_activity
                ) VALUES (?, 1, 0, 'medium', 'auto', NOW(), NOW(), NOW())
            ");
            $stmt->execute([$sessionId]);
            $userId = $this->pdo->lastInsertId();
            
            // Actualizar cache
            $cacheKey = "user_id_$sessionId";
            SimpleCache::set($cacheKey, $userId, 1800);
            
            return $userId;
        }

        return $user['id'];
    }
    
    /**
     * Respuesta JSON estandarizada
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        if (is_array($data)) {
            return json_encode($data);
        } else {
            return json_encode(['message' => $data]);
        }
    }
    
    // Métodos auxiliares para validación de Sudoku
    private function getRow($grid, $row) 
    {
        $result = [];
        for ($col = 0; $col < 9; $col++) {
            $result[] = $grid[$row * 9 + $col];
        }
        return $result;
    }
    
    private function getColumn($grid, $col) 
    {
        $result = [];
        for ($row = 0; $row < 9; $row++) {
            $result[] = $grid[$row * 9 + $col];
        }
        return $result;
    }
    
    private function getBox($grid, $box) 
    {
        $result = [];
        $startRow = intval($box / 3) * 3;
        $startCol = ($box % 3) * 3;
        
        for ($row = $startRow; $row < $startRow + 3; $row++) {
            for ($col = $startCol; $col < $startCol + 3; $col++) {
                $result[] = $grid[$row * 9 + $col];
            }
        }
        return $result;
    }
    
    private function isValidGroup($group) 
    {
        $seen = [];
        foreach ($group as $cell) {
            if ($cell !== '.' && $cell !== '0') {
                if (isset($seen[$cell])) {
                    return false;
                }
                $seen[$cell] = true;
            }
        }
        return true;
    }
}
