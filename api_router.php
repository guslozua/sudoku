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
     * Obtener o crear usuario de sesión anónimo (método público)
     */
    public function getOrCreateSessionUserPublic()
    {
        return $this->getOrCreateSessionUser();
    }
    
    /**
     * Obtener conexión PDO (método público)
     */
    public function getPdo()
    {
        return $this->pdo;
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
    
    /**
     * Generar pista inteligente
     */
    public function generateIntelligentHint($boardString)
    {
        try {
            // Convertir string a array 2D
            $board = [];
            for ($i = 0; $i < 9; $i++) {
                $row = [];
                for ($j = 0; $j < 9; $j++) {
                    $row[] = intval($boardString[$i * 9 + $j]);
                }
                $board[] = $row;
            }
            
            // Buscar celdas vacías
            $emptyCells = [];
            for ($row = 0; $row < 9; $row++) {
                for ($col = 0; $col < 9; $col++) {
                    if ($board[$row][$col] === 0) {
                        $emptyCells[] = ['row' => $row, 'col' => $col];
                    }
                }
            }
            
            if (empty($emptyCells)) {
                return null; // No hay celdas vacías
            }
            
            // Buscar una celda con pocas posibilidades (más inteligente)
            $bestCell = null;
            $minOptions = 10;
            
            foreach ($emptyCells as $cell) {
                $options = $this->getPossibleNumbers($board, $cell['row'], $cell['col']);
                if (count($options) > 0 && count($options) < $minOptions) {
                    $minOptions = count($options);
                    $bestCell = $cell;
                    $bestCell['options'] = $options;
                }
            }
            
            // Si no encontramos una celda con pocas opciones, usar una aleatoria
            if (!$bestCell) {
                $randomCell = $emptyCells[array_rand($emptyCells)];
                $options = $this->getPossibleNumbers($board, $randomCell['row'], $randomCell['col']);
                if (!empty($options)) {
                    $bestCell = $randomCell;
                    $bestCell['options'] = $options;
                }
            }
            
            if (!$bestCell || empty($bestCell['options'])) {
                return null;
            }
            
            // Seleccionar el primer número válido
            $number = $bestCell['options'][0];
            
            // Generar explicación educativa
            $explanation = $this->generateExplanation($board, $bestCell['row'], $bestCell['col'], $number, count($bestCell['options']));
            
            return [
                'row' => $bestCell['row'],
                'col' => $bestCell['col'],
                'number' => $number,
                'explanation' => $explanation
            ];
            
        } catch (Exception $e) {
            error_log("❌ Error generando pista: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener números posibles para una celda
     */
    private function getPossibleNumbers($board, $row, $col)
    {
        $possible = [];
        
        for ($num = 1; $num <= 9; $num++) {
            if ($this->isValidPlacement($board, $row, $col, $num)) {
                $possible[] = $num;
            }
        }
        
        return $possible;
    }
    
    /**
     * Verificar si un número es válido en una posición
     */
    private function isValidPlacement($board, $row, $col, $num)
    {
        // Verificar fila
        for ($c = 0; $c < 9; $c++) {
            if ($board[$row][$c] === $num) {
                return false;
            }
        }
        
        // Verificar columna
        for ($r = 0; $r < 9; $r++) {
            if ($board[$r][$col] === $num) {
                return false;
            }
        }
        
        // Verificar subcuadro 3x3
        $startRow = intval($row / 3) * 3;
        $startCol = intval($col / 3) * 3;
        
        for ($r = $startRow; $r < $startRow + 3; $r++) {
            for ($c = $startCol; $c < $startCol + 3; $c++) {
                if ($board[$r][$c] === $num) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Generar explicación educativa
     */
    private function generateExplanation($board, $row, $col, $number, $optionsCount)
    {
        $explanations = [
            "En la celda fila " . ($row + 1) . ", columna " . ($col + 1) . ", solo puede ir el número $number.",
            "Observa la fila " . ($row + 1) . " y columna " . ($col + 1) . ": el número $number es la única opción válida.",
            "En el subcuadro correspondiente, el número $number solo puede ubicarse en esta posición.",
            "Aplicando la técnica de eliminación, esta celda solo puede contener el número $number."
        ];
        
        if ($optionsCount === 1) {
            $explanations[] = "Esta celda tiene una única opción posible: el número $number. ¡Es una elección fácil!";
        } else {
            $explanations[] = "Esta celda tiene $optionsCount opciones posibles, pero el $number es una buena elección para continuar.";
        }
        
        return $explanations[array_rand($explanations)];
    }
    
    private function respondSuccess($data)
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true] + $data);
        exit;
    }
    
    /**
     * Verificar y desbloquear logros del usuario
     */
    public function checkAndUnlockAchievements($userId, $gameData)
    {
        try {
            $newAchievements = [];
            
            // Obtener estadísticas del usuario
            $stats = $this->getUserStats($userId);
            
            // Verificar cada tipo de logro
            $newAchievements = array_merge($newAchievements, $this->checkCompletionAchievements($userId, $stats));
            $newAchievements = array_merge($newAchievements, $this->checkSpeedAchievements($userId, $gameData));
            $newAchievements = array_merge($newAchievements, $this->checkDifficultyAchievements($userId, $gameData));
            $newAchievements = array_merge($newAchievements, $this->checkStrategyAchievements($userId, $gameData));
            $newAchievements = array_merge($newAchievements, $this->checkPrecisionAchievements($userId, $gameData));
            
            return $newAchievements;
        } catch (Exception $e) {
            error_log("❌ Error checking achievements: " . $e->getMessage());
            return [];
        }
    }
    
    private function respondError($message, $code = 400)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message, 'success' => false]);
        exit;
    }
    
    /**
     * Obtener estadísticas del usuario
     */
    private function getUserStats($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_completed,
                COUNT(CASE WHEN p.difficulty_level = 'easy' THEN 1 END) as easy_completed,
                COUNT(CASE WHEN p.difficulty_level = 'medium' THEN 1 END) as medium_completed,
                COUNT(CASE WHEN p.difficulty_level = 'hard' THEN 1 END) as hard_completed,
                COUNT(CASE WHEN p.difficulty_level = 'expert' THEN 1 END) as expert_completed,
                COUNT(CASE WHEN p.difficulty_level = 'master' THEN 1 END) as master_completed,
                SUM(g.hints_used) as total_hints_used,
                MIN(g.completion_time) as best_time
            FROM games g
            JOIN puzzles p ON g.puzzle_id = p.id
            WHERE g.user_id = ? AND g.status = 'completed'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Verificar logros de completado
     */
    private function checkCompletionAchievements($userId, $stats)
    {
        $achievements = [];
        
        // Primer puzzle completado
        if ($stats['total_completed'] >= 1) {
            $achievements[] = $this->unlockAchievement($userId, 'first_step');
        }
        
        // 10 puzzles completados
        if ($stats['total_completed'] >= 10) {
            $achievements[] = $this->unlockAchievement($userId, 'puzzle_master');
        }
        
        // 50 puzzles completados
        if ($stats['total_completed'] >= 50) {
            $achievements[] = $this->unlockAchievement($userId, 'sudoku_legend');
        }
        
        // 5 puzzles fáciles
        if ($stats['easy_completed'] >= 5) {
            $achievements[] = $this->unlockAchievement($userId, 'easy_champion');
        }
        
        return array_filter($achievements);
    }
    
    /**
     * Verificar logros de velocidad
     */
    private function checkSpeedAchievements($userId, $gameData)
    {
        $achievements = [];
        
        if (isset($gameData['completion_time'])) {
            // Menos de 5 minutos (300 segundos)
            if ($gameData['completion_time'] <= 300) {
                $achievements[] = $this->unlockAchievement($userId, 'speed_demon');
            }
            
            // Menos de 3 minutos (180 segundos)
            if ($gameData['completion_time'] <= 180) {
                $achievements[] = $this->unlockAchievement($userId, 'lightning_fast');
            }
        }
        
        return array_filter($achievements);
    }
    
    /**
     * Verificar logros de dificultad
     */
    private function checkDifficultyAchievements($userId, $gameData)
    {
        $achievements = [];
        
        if (isset($gameData['difficulty'])) {
            // Primer Expert
            if ($gameData['difficulty'] === 'expert') {
                $achievements[] = $this->unlockAchievement($userId, 'expert_challenger');
            }
            
            // Primer Master
            if ($gameData['difficulty'] === 'master') {
                $achievements[] = $this->unlockAchievement($userId, 'master_conqueror');
            }
        }
        
        return array_filter($achievements);
    }
    
    /**
     * Verificar logros de estrategia
     */
    private function checkStrategyAchievements($userId, $gameData)
    {
        $achievements = [];
        
        // Sin pistas
        if (isset($gameData['hints_used']) && $gameData['hints_used'] == 0) {
            $achievements[] = $this->unlockAchievement($userId, 'strategic_mind');
        }
        
        return array_filter($achievements);
    }
    
    /**
     * Verificar logros de precisión
     */
    private function checkPrecisionAchievements($userId, $gameData)
    {
        $achievements = [];
        
        // Sin errores
        if (isset($gameData['mistakes_count']) && $gameData['mistakes_count'] == 0) {
            $achievements[] = $this->unlockAchievement($userId, 'perfect_game');
        }
        
        // Menos de 100 movimientos
        if (isset($gameData['moves_count']) && $gameData['moves_count'] < 100) {
            $achievements[] = $this->unlockAchievement($userId, 'efficient_solver');
        }
        
        return array_filter($achievements);
    }
    
    /**
     * Desbloquear un logro específico
     */
    private function unlockAchievement($userId, $achievementKey)
    {
        try {
            // Verificar si ya tiene el logro
            $stmt = $this->pdo->prepare("
                SELECT ua.id 
                FROM user_achievements ua 
                JOIN achievements a ON ua.achievement_id = a.id 
                WHERE ua.user_id = ? AND a.key_name = ? AND ua.is_completed = 1
            ");
            $stmt->execute([$userId, $achievementKey]);
            
            if ($stmt->fetch()) {
                return null; // Ya lo tiene
            }
            
            // Obtener el logro
            $stmt = $this->pdo->prepare("SELECT * FROM achievements WHERE key_name = ?");
            $stmt->execute([$achievementKey]);
            $achievement = $stmt->fetch();
            
            if (!$achievement) {
                return null;
            }
            
            // Desbloquear el logro
            $stmt = $this->pdo->prepare("
                INSERT INTO user_achievements (user_id, achievement_id, is_completed, unlocked_at) 
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE is_completed = 1, unlocked_at = NOW()
            ");
            $stmt->execute([$userId, $achievement['id']]);
            
            error_log("🏆 Logro desbloqueado: {$achievement['name']} para usuario $userId");
            
            return $achievement;
        } catch (Exception $e) {
            error_log("❌ Error unlocking achievement: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener todos los logros del usuario
     */
    public function getUserAchievements($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.*,
                    ua.is_completed,
                    ua.unlocked_at,
                    ua.current_progress
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                ORDER BY ua.is_completed DESC, a.category, a.id
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Error getting user achievements: " . $e->getMessage());
            return [];
        }
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
        
        // GET /achievements
        if ($requestUri === '/achievements') {
            error_log("🏆 Ruta de logros detectada: /achievements");
            
            try {
                // Obtener ID de usuario de sesión directamente
                $sessionId = $_SESSION['sudoku_session_id'] ?? null;
                if (!$sessionId) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'No hay sesión activa'
                    ]);
                    exit;
                }
                
                // Buscar usuario por session_id
                $stmt = $controller->getPdo()->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario no encontrado'
                    ]);
                    exit;
                }
                
                $userId = $user['id'];
                
                // Obtener logros del usuario
                $achievements = $controller->getUserAchievements($userId);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'achievements' => $achievements
                ]);
                exit;
            } catch (Exception $e) {
                error_log("❌ Error obteniendo logros: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error al obtener logros: ' . $e->getMessage(), 'success' => false]);
                exit;
            }
        }
        
        // GET /analytics/dashboard
        if ($requestUri === '/analytics/dashboard') {
            error_log("📊 Ruta de dashboard analítico detectada: /analytics/dashboard");
            
            try {
                $sessionId = $_SESSION['sudoku_session_id'] ?? null;
                if (!$sessionId) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'No hay sesión activa']);
                    exit;
                }
                
                $stmt = $controller->getPdo()->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                    exit;
                }
                
                $userId = $user['id'];
                
                // Obtener estadísticas del dashboard
                $dashboardData = $controller->getDashboardAnalytics($userId);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => $dashboardData
                ]);
                exit;
            } catch (Exception $e) {
                error_log("❌ Error obteniendo analytics: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error al obtener analytics: ' . $e->getMessage(), 'success' => false]);
                exit;
            }
        }
        
        // GET /analytics/progress
        if ($requestUri === '/analytics/progress') {
            error_log("📈 Ruta de progreso detectada: /analytics/progress");
            
            try {
                $sessionId = $_SESSION['sudoku_session_id'] ?? null;
                if (!$sessionId) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'No hay sesión activa']);
                    exit;
                }
                
                $stmt = $controller->getPdo()->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                    exit;
                }
                
                $userId = $user['id'];
                $days = $_GET['days'] ?? 30; // Por defecto 30 días
                
                // Obtener datos de progreso
                $progressData = $controller->getProgressAnalytics($userId, $days);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'data' => $progressData
                ]);
                exit;
            } catch (Exception $e) {
                error_log("❌ Error obteniendo progreso: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error al obtener progreso: ' . $e->getMessage(), 'success' => false]);
                exit;
            }
        }
        
        // GET /game/current
        if ($requestUri === '/game/current') {
            error_log("💾 Ruta de juego actual detectada: /game/current");
            
            try {
                // Obtener ID de usuario de sesión directamente
                $sessionId = $_SESSION['sudoku_session_id'] ?? null;
                if (!$sessionId) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'No hay sesión activa'
                    ]);
                    exit;
                }
                
                // Buscar usuario por session_id
                $stmt = $controller->getPdo()->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario no encontrado'
                    ]);
                    exit;
                }
                
                $userId = $user['id'];
                
                // Buscar el juego más reciente en progreso
                $stmt = $controller->getPdo()->prepare("
                    SELECT g.*, p.puzzle_string, p.solution_string, p.difficulty_level, p.clues_count 
                    FROM games g 
                    LEFT JOIN puzzles p ON g.puzzle_id = p.id 
                    WHERE g.user_id = ? AND g.status = 'in_progress' 
                    ORDER BY g.last_played_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $game = $stmt->fetch();
                
                if ($game) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'game' => [
                            'id' => $game['id'],
                            'current_state' => $game['current_state'],
                            'initial_state' => $game['initial_state'],
                            'status' => $game['status'],
                            'time_spent' => $game['time_spent'],
                            'moves_count' => $game['moves_count'],
                            'hints_used' => $game['hints_used'],
                            'last_played_at' => $game['last_played_at'],
                            'puzzle' => [
                                'difficulty_level' => $game['difficulty_level'],
                                'clues_count' => $game['clues_count']
                            ]
                        ]
                    ]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'No hay juegos en progreso'
                    ]);
                }
                exit;
            } catch (Exception $e) {
                error_log("❌ Error obteniendo juego actual: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error al obtener juego actual: ' . $e->getMessage(), 'success' => false]);
                exit;
            }
        }
    }
    
    if ($method === 'POST') {
        // POST /hint - Nueva ruta para pistas
        if ($requestUri === '/hint') {
            error_log("💡 Ruta de pista detectada: /hint");
            
            // Leer datos del POST
            $input = json_decode(file_get_contents('php://input'), true);
            $gameId = $input['game_id'] ?? null;
            $currentState = $input['current_state'] ?? null;
            
            if (!$gameId || !$currentState) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Faltan parámetros requeridos', 'success' => false]);
                exit;
            }
            
            // Generar pista inteligente
            $hint = $controller->generateIntelligentHint($currentState);
            
            if (!$hint) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No se pudo generar una pista válida', 'success' => false]);
                exit;
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'hint' => $hint,
                'hints_remaining' => 2
            ]);
            exit;
        }
        
        // POST /game/complete
        if ($requestUri === '/game/complete') {
            error_log("🏆 Ruta de completar puzzle detectada: /game/complete");
            
            // Leer datos del POST
            $input = json_decode(file_get_contents('php://input'), true);
            $gameId = $input['game_id'] ?? null;
            $currentState = $input['current_state'] ?? null;
            $timeSpent = $input['time_spent'] ?? 0;
            $movesCount = $input['moves_count'] ?? 0;
            $hintsUsed = $input['hints_used'] ?? 0;
            $mistakesCount = $input['mistakes_count'] ?? 0;
            
            if (!$gameId || !$currentState) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Faltan parámetros requeridos', 'success' => false]);
                exit;
            }
            
            try {
                // Obtener usuario actual
                $sessionId = $_SESSION['sudoku_session_id'] ?? null;
                if (!$sessionId) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'No hay sesión activa', 'success' => false]);
                    exit;
                }
                
                $stmt = $controller->getPdo()->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Usuario no encontrado', 'success' => false]);
                    exit;
                }
                
                $userId = $user['id'];
                
                // Obtener información del juego y puzzle
                $stmt = $controller->getPdo()->prepare("
                    SELECT g.*, p.difficulty_level, p.solution_string 
                    FROM games g 
                    JOIN puzzles p ON g.puzzle_id = p.id 
                    WHERE g.id = ? AND g.user_id = ?
                ");
                $stmt->execute([$gameId, $userId]);
                $game = $stmt->fetch();
                
                if (!$game) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Juego no encontrado', 'success' => false]);
                    exit;
                }
                
                // Marcar juego como completado
                $stmt = $controller->getPdo()->prepare("
                    UPDATE games SET 
                        current_state = ?, 
                        time_spent = ?, 
                        moves_count = ?, 
                        hints_used = ?,
                        mistakes_count = ?,
                        status = 'completed',
                        completion_time = ?,
                        completed_at = NOW(),
                        last_played_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$currentState, $timeSpent, $movesCount, $hintsUsed, $mistakesCount, $timeSpent, $gameId]);
                
                // Verificar logros
                $gameData = [
                    'completion_time' => $timeSpent,
                    'moves_count' => $movesCount,
                    'hints_used' => $hintsUsed,
                    'mistakes_count' => $mistakesCount,
                    'difficulty' => $game['difficulty_level']
                ];
                
                $newAchievements = $controller->checkAndUnlockAchievements($userId, $gameData);
                
                error_log("🏆 Logros verificados - Nuevos logros: " . count($newAchievements));
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Puzzle completado exitosamente',
                    'completion_time' => $timeSpent,
                    'new_achievements' => $newAchievements,
                    'completed_at' => date('Y-m-d H:i:s')
                ]);
                exit;
            } catch (Exception $e) {
                error_log("❌ Error completando puzzle: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error al completar puzzle: ' . $e->getMessage(), 'success' => false]);
                exit;
            }
        }
        
        // POST /game/save
        if ($requestUri === '/game/save') {
            error_log("💾 Ruta de guardado detectada: /game/save");
            
            // Leer datos del POST
            $input = json_decode(file_get_contents('php://input'), true);
            $gameId = $input['game_id'] ?? null;
            $currentState = $input['current_state'] ?? null;
            $timeSpent = $input['time_spent'] ?? 0;
            $movesCount = $input['moves_count'] ?? 0;
            $hintsUsed = $input['hints_used'] ?? 0;
            
            if (!$gameId || !$currentState) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Faltan parámetros requeridos', 'success' => false]);
                exit;
            }
            
            try {
                // Verificar que el juego existe y pertenece al usuario actual
                $sessionId = $_SESSION['sudoku_session_id'] ?? null;
                error_log("💾 [SAVE] Session ID: " . ($sessionId ?: 'NULL'));
                
                if (!$sessionId) {
                    error_log("💾 [SAVE] ERROR: No hay sesión activa");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'No hay sesión activa', 'success' => false]);
                    exit;
                }
                
                // Buscar usuario
                $stmt = $controller->getPdo()->prepare("SELECT id FROM users WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $user = $stmt->fetch();
                
                error_log("💾 [SAVE] Usuario encontrado: " . ($user ? "ID {$user['id']}" : 'NO ENCONTRADO'));
                
                if (!$user) {
                    error_log("💾 [SAVE] ERROR: Usuario no encontrado para session: $sessionId");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Usuario no encontrado', 'success' => false]);
                    exit;
                }
                
                // Verificar que el juego pertenece al usuario
                $stmt = $controller->getPdo()->prepare("SELECT id, user_id FROM games WHERE id = ?");
                $stmt->execute([$gameId]);
                $gameExists = $stmt->fetch();
                
                error_log("💾 [SAVE] Juego verificado - Game ID: $gameId");
                if ($gameExists) {
                    error_log("💾 [SAVE] Juego encontrado - Pertenece a User ID: {$gameExists['user_id']}, Usuario actual: {$user['id']}");
                } else {
                    error_log("💾 [SAVE] ERROR: Juego $gameId no existe");
                }
                
                if (!$gameExists) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Juego no encontrado', 'success' => false]);
                    exit;
                }
                
                if ($gameExists['user_id'] != $user['id']) {
                    error_log("💾 [SAVE] ERROR: Juego pertenece a otro usuario");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Sin permisos para este juego', 'success' => false]);
                    exit;
                }
                
                error_log("💾 [SAVE] Ejecutando UPDATE...");
                
                // Actualizar el juego en la base de datos
                $stmt = $controller->getPdo()->prepare("UPDATE games SET current_state = ?, time_spent = ?, moves_count = ?, hints_used = ?, last_played_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$currentState, $timeSpent, $movesCount, $hintsUsed, $gameId]);
                
                error_log("💾 [SAVE] Resultado UPDATE: " . ($result ? 'EXITOSO' : 'FALLIDO'));
                error_log("💾 [SAVE] Filas afectadas: " . $stmt->rowCount());
                
                if ($result && $stmt->rowCount() > 0) {
                    error_log("💾 [SAVE] ✅ Guardado exitoso");
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Juego guardado exitosamente',
                        'saved_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    error_log("💾 [SAVE] ❌ Error: No se afectaron filas");
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'No se pudo actualizar el juego', 'success' => false]);
                }
                exit;
            } catch (Exception $e) {
                error_log("❌ Error guardando juego: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error al guardar el juego', 'success' => false]);
                exit;
            }
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
