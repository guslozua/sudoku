    // 🎯 FUNCIÓN PARA VALIDAR QUE UN PUZZLE NO TENGA ERRORES
    private function validatePuzzleBoard($puzzleString)
    {
        // Convertir string a array 2D
        $board = [];
        for ($i = 0; $i < 9; $i++) {
            $row = [];
            for ($j = 0; $j < 9; $j++) {
                $row[] = intval($puzzleString[$i * 9 + $j]);
            }
            $board[] = $row;
        }
        
        // Verificar cada celda con número
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                $num = $board[$row][$col];
                if ($num != 0) {
                    if ($this->hasConflictInBoard($board, $row, $col, $num)) {
                        return false; // Puzzle inválido
                    }
                }
            }
        }
        
        return true; // Puzzle válido
    }
    
    // 🔍 DETECTAR CONFLICTOS EN UN TABLERO
    private function hasConflictInBoard($board, $row, $col, $num)
    {
        // Verificar fila
        for ($c = 0; $c < 9; $c++) {
            if ($c != $col && $board[$row][$c] == $num) {
                return true;
            }
        }
        
        // Verificar columna
        for ($r = 0; $r < 9; $r++) {
            if ($r != $row && $board[$r][$col] == $num) {
                return true;
            }
        }
        
        // Verificar subcuadro 3x3
        $startRow = floor($row / 3) * 3;
        $startCol = floor($col / 3) * 3;
        
        for ($r = $startRow; $r < $startRow + 3; $r++) {
            for ($c = $startCol; $c < $startCol + 3; $c++) {
                if (($r != $row || $c != $col) && $board[$r][$c] == $num) {
                    return true;
                }
            }
        }
        
        return false;
    }<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class SudokuController extends Controller
{
    /**
     * Obtener un nuevo puzzle según la dificultad
     */
    public function getNewPuzzle($request = null, $difficulty = null)
    {
        try {
            // Si $request es un string, entonces es la dificultad (llamada desde rutas)
            if (is_string($request)) {
                $difficulty = $request;
            } elseif ($request && method_exists($request, 'input')) {
                // Si es un objeto Request
                $difficulty = $request->input('difficulty', $difficulty);
            }
            
            // Validar dificultad
            $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
            if (!in_array($difficulty, $validDifficulties)) {
                return response()->json(['error' => 'Dificultad inválida'], 400);
            }

            $maxAttempts = 10; // Máximo intentos para encontrar un puzzle válido
            $attempts = 0;
            $puzzle = null;

            do {
                $attempts++;
                
                // Obtener puzzle aleatorio de la dificultad solicitada (solo los válidos)
                $candidatePuzzle = DB::table('puzzles')
                    ->where('difficulty_level', $difficulty)
                    ->where(function($query) {
                        $query->where('is_valid', true)
                              ->orWhereNull('is_valid'); // Incluir puzzles sin validar aún
                    })
                    ->inRandomOrder()
                    ->first();

                if (!$candidatePuzzle) {
                    return response()->json(['error' => 'No hay puzzles disponibles'], 404);
                }
                
                // 🛡️ VALIDAR QUE EL PUZZLE NO TENGA ERRORES
                if ($this->validatePuzzleBoard($candidatePuzzle->puzzle_string)) {
                    $puzzle = $candidatePuzzle;
                    break; // Puzzle válido encontrado
                } else {
                    // Log del puzzle inválido para debugging
                    error_log("Puzzle inválido detectado - ID: {$candidatePuzzle->id}, Intento: $attempts");
                }
                
            } while ($attempts < $maxAttempts);
            
            // Si no encontramos un puzzle válido
            if (!$puzzle) {
                return response()->json([
                    'error' => 'No se pudo generar un puzzle válido. Inténtalo de nuevo.',
                    'attempts' => $attempts
                ], 500);
            }

            // Obtener o crear usuario de sesión
            $userId = $this->getOrCreateSessionUser();

            // Crear nuevo juego
            $gameId = DB::table('games')->insertGetId([
                'user_id' => $userId,
                'puzzle_id' => $puzzle->id,
                'current_state' => $puzzle->puzzle_string,
                'initial_state' => $puzzle->puzzle_string,
                'status' => 'in_progress',
                'hints_used' => 0,
                'mistakes_count' => 0,
                'moves_count' => 0,
                'started_at' => now(),
                'last_played_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'puzzle' => [
                    'id' => $puzzle->id,
                    'puzzle_string' => $puzzle->puzzle_string,
                    'solution_string' => $puzzle->solution_string,
                    'difficulty_level' => $puzzle->difficulty_level,
                    'clues_count' => $puzzle->clues_count
                ],
                'game_id' => $gameId,
                'validation_attempts' => $attempts // Para debugging
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener un puzzle específico por ID
     */
    public function getPuzzle($id)
    {
        try {
            $puzzle = DB::table('puzzles')->find($id);
            
            if (!$puzzle) {
                return response()->json(['error' => 'Puzzle no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'puzzle' => $puzzle
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener pista para el puzzle actual
     */
    public function getHint(Request $request)
    {
        try {
            $gameId = $request->input('game_id');
            $currentState = $request->input('current_state');
            
            // Obtener el juego actual
            $game = DB::table('games')->find($gameId);
            if (!$game) {
                return response()->json(['error' => 'Juego no encontrado'], 404);
            }

            // Obtener el puzzle y su solución
            $puzzle = DB::table('puzzles')->find($game->puzzle_id);
            if (!$puzzle) {
                return response()->json(['error' => 'Puzzle no encontrado'], 404);
            }

            // Verificar límite de pistas (máximo 3)
            if ($game->hints_used >= 3) {
                return response()->json(['error' => 'Límite de pistas alcanzado'], 403);
            }

            // Encontrar una celda vacía para dar pista
            $hint = $this->generateHint($currentState, $puzzle->solution_string);
            
            if (!$hint) {
                return response()->json(['error' => 'No se pueden generar más pistas'], 400);
            }

            // Actualizar contador de pistas
            DB::table('games')
                ->where('id', $gameId)
                ->increment('hints_used');

            return response()->json([
                'success' => true,
                'hint' => $hint,
                'hints_remaining' => 2 - $game->hints_used
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Validar solución del Sudoku
     */
    public function validateSolution(Request $request)
    {
        try {
            $boardString = $request->input('board_string');
            
            if (strlen($boardString) !== 81) {
                return response()->json(['error' => 'Formato de tablero inválido'], 400);
            }

            $isValid = $this->isValidSudokuSolution($boardString);
            
            return response()->json([
                'success' => true,
                'is_valid' => $isValid,
                'is_complete' => !str_contains($boardString, '0')
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener o crear usuario de sesión anónimo
     */
    private function getOrCreateSessionUser()
    {
        $sessionId = Session::get('sudoku_session_id');
        
        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            Session::put('sudoku_session_id', $sessionId);
        }

        $user = DB::table('users')->where('session_id', $sessionId)->first();
        
        if (!$user) {
            $userId = DB::table('users')->insertGetId([
                'session_id' => $sessionId,
                'is_anonymous' => true,
                'is_premium' => false,
                'preferred_difficulty' => 'medium',
                'theme_preference' => 'auto',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return $userId;
        }

        return $user->id;
    }

    /**
     * Generar pista inteligente
     */
    private function generateHint($currentState, $solution)
    {
        // Convertir strings a arrays para facilitar el trabajo
        $current = str_split($currentState);
        $solved = str_split($solution);
        
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
        
        // Seleccionar una celda aleatoria vacía
        $randomIndex = $emptyCells[array_rand($emptyCells)];
        $row = intval($randomIndex / 9);
        $col = $randomIndex % 9;
        
        return [
            'row' => $row,
            'col' => $col,
            'number' => $solved[$randomIndex],
            'explanation' => $this->generateHintExplanation($row, $col, $solved[$randomIndex])
        ];
    }

    /**
     * Generar explicación educativa para la pista
     */
    private function generateHintExplanation($row, $col, $number)
    {
        $explanations = [
            "En la celda fila " . ($row + 1) . ", columna " . ($col + 1) . ", solo puede ir el número $number.",
            "Observa la fila " . ($row + 1) . " y columna " . ($col + 1) . ": el número $number es la única opción válida.",
            "En el subcuadro correspondiente, el número $number solo puede ubicarse en esta posición.",
            "Aplicando la técnica de eliminación, esta celda solo puede contener el número $number."
        ];
        
        return $explanations[array_rand($explanations)];
    }

    /**
     * Validar si una solución de Sudoku es correcta
     */
    private function isValidSudokuSolution($boardString)
    {
        $board = [];
        for ($i = 0; $i < 9; $i++) {
            $board[$i] = [];
            for ($j = 0; $j < 9; $j++) {
                $board[$i][$j] = intval($boardString[$i * 9 + $j]);
            }
        }

        // Verificar filas
        for ($row = 0; $row < 9; $row++) {
            $seen = [];
            for ($col = 0; $col < 9; $col++) {
                $num = $board[$row][$col];
                if ($num !== 0) {
                    if (in_array($num, $seen) || $num < 1 || $num > 9) {
                        return false;
                    }
                    $seen[] = $num;
                }
            }
        }

        // Verificar columnas
        for ($col = 0; $col < 9; $col++) {
            $seen = [];
            for ($row = 0; $row < 9; $row++) {
                $num = $board[$row][$col];
                if ($num !== 0) {
                    if (in_array($num, $seen) || $num < 1 || $num > 9) {
                        return false;
                    }
                    $seen[] = $num;
                }
            }
        }

        // Verificar subcuadros 3x3
        for ($boxRow = 0; $boxRow < 3; $boxRow++) {
            for ($boxCol = 0; $boxCol < 3; $boxCol++) {
                $seen = [];
                for ($row = $boxRow * 3; $row < ($boxRow + 1) * 3; $row++) {
                    for ($col = $boxCol * 3; $col < ($boxCol + 1) * 3; $col++) {
                        $num = $board[$row][$col];
                        if ($num !== 0) {
                            if (in_array($num, $seen) || $num < 1 || $num > 9) {
                                return false;
                            }
                            $seen[] = $num;
                        }
                    }
                }
            }
        }

        return true;
    }

    // 📊 ENDPOINT DE DASHBOARD ANALYTICS
    public function getDashboardAnalytics(Request $request)
    {
        try {
            $userId = $this->getUserId($request);
            
            // Estadísticas generales del usuario
            $userStats = DB::selectOne("
                SELECT 
                    COUNT(*) as total_games,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games,
                    AVG(CASE WHEN status = 'completed' THEN completion_time END) as avg_completion_time,
                    MIN(CASE WHEN status = 'completed' THEN completion_time END) as best_time,
                    SUM(CASE WHEN status = 'completed' THEN time_spent ELSE 0 END) as total_time_played,
                    SUM(moves_count) as total_moves,
                    SUM(hints_used) as total_hints,
                    SUM(mistakes_count) as total_mistakes,
                    COUNT(CASE WHEN perfect_game = 1 THEN 1 END) as perfect_games
                FROM games 
                WHERE user_id = ?
            ", [$userId]);
            
            // Estadísticas por dificultad
            $difficultyStats = DB::select("
                SELECT 
                    p.difficulty_level,
                    COUNT(g.id) as count,
                    AVG(g.completion_time) as avg_time,
                    MIN(g.completion_time) as best_time
                FROM games g
                JOIN puzzles p ON g.puzzle_id = p.id
                WHERE g.user_id = ? AND g.status = 'completed'
                GROUP BY p.difficulty_level
                ORDER BY 
                    CASE p.difficulty_level 
                        WHEN 'easy' THEN 1
                        WHEN 'medium' THEN 2 
                        WHEN 'hard' THEN 3
                        WHEN 'expert' THEN 4
                        WHEN 'master' THEN 5
                    END
            ", [$userId]);
            
            // Actividad reciente
            $recentActivity = DB::select("
                SELECT 
                    p.difficulty_level,
                    g.completion_time,
                    g.moves_count,
                    g.hints_used,
                    g.mistakes_count,
                    g.completed_at
                FROM games g
                JOIN puzzles p ON g.puzzle_id = p.id
                WHERE g.user_id = ? AND g.status = 'completed'
                ORDER BY g.completed_at DESC
                LIMIT 10
            ", [$userId]);
            
            // Progreso semanal
            $weeklyProgress = DB::select("
                SELECT 
                    DATE(g.completed_at) as date,
                    COUNT(*) as puzzles_completed,
                    AVG(g.completion_time) as avg_time,
                    MIN(g.completion_time) as best_time
                FROM games g
                WHERE g.user_id = ? AND g.status = 'completed'
                    AND g.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(g.completed_at)
                ORDER BY date DESC
            ", [$userId]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user_stats' => $userStats,
                    'difficulty_stats' => $difficultyStats,
                    'recent_activity' => $recentActivity,
                    'weekly_progress' => $weeklyProgress
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Error in getDashboardAnalytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error cargando analíticas: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // 📈 ENDPOINT DE PROGRESS ANALYTICS
    public function getProgressAnalytics(Request $request)
    {
        try {
            $userId = $this->getUserId($request);
            $days = $request->get('days', 30);
            
            // Totales diarios
            $dailyTotals = DB::select("
                SELECT 
                    DATE(g.completed_at) as date,
                    COUNT(*) as total_puzzles,
                    AVG(g.completion_time) as avg_time,
                    MIN(g.completion_time) as best_time,
                    SUM(g.time_spent) as total_time_spent,
                    COUNT(CASE WHEN g.perfect_game = 1 THEN 1 END) as perfect_games
                FROM games g
                WHERE g.user_id = ? AND g.status = 'completed'
                    AND g.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(g.completed_at)
                ORDER BY date ASC
            ", [$userId, $days]);
            
            // Calcular rachas
            $currentStreak = $this->calculateCurrentStreak($userId);
            $bestStreak = $this->calculateBestStreak($userId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'daily_totals' => $dailyTotals,
                    'current_streak' => $currentStreak,
                    'best_streak' => $bestStreak,
                    'period_days' => $days
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Error in getProgressAnalytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error cargando progreso: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // 📊 FUNCIONES DE ANALÍTICAS AVANZADAS
    
    /**
     * Obtener datos del dashboard analítico
     */
    public function getDashboardAnalytics($userId)
    {
        error_log("📊 Obteniendo analytics del dashboard para usuario: $userId");
        
        try {
            // Estadísticas generales del usuario
            $stmt = $this->pdo->prepare("
                SELECT 
                    total_games,
                    completed_games,
                    ROUND(avg_completion_time, 2) as avg_completion_time,
                    best_time,
                    total_time_played,
                    total_moves,
                    total_hints,
                    total_mistakes,
                    easy_completed,
                    medium_completed,
                    hard_completed,
                    expert_completed,
                    master_completed,
                    perfect_games
                FROM user_statistics 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $userStats = $stmt->fetch() ?: [];
            
            // Estadísticas de los últimos 7 días
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(completed_at) as date,
                    COUNT(*) as puzzles_completed,
                    AVG(completion_time) as avg_time,
                    MIN(completion_time) as best_time
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed' 
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(completed_at)
                ORDER BY date DESC
            ");
            $stmt->execute([$userId]);
            $weeklyProgress = $stmt->fetchAll();
            
            // Distribución por dificultad (últimos 30 días)
            $stmt = $this->pdo->prepare("
                SELECT 
                    difficulty_level,
                    COUNT(*) as count,
                    AVG(completion_time) as avg_time,
                    MIN(completion_time) as best_time
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed' 
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY difficulty_level
            ");
            $stmt->execute([$userId]);
            $difficultyStats = $stmt->fetchAll();
            
            // Progreso de logros
            $stmt = $this->pdo->prepare("
                SELECT 
                    achievement_type,
                    COUNT(*) as unlocked_count
                FROM user_achievements 
                WHERE user_id = ?
                GROUP BY achievement_type
            ");
            $stmt->execute([$userId]);
            $achievementProgress = $stmt->fetchAll();
            
            // Actividad reciente (últimos 5 juegos)
            $stmt = $this->pdo->prepare("
                SELECT 
                    difficulty_level,
                    completion_time,
                    moves_count,
                    hints_used,
                    mistakes_count,
                    completed_at
                FROM games 
                WHERE user_id = ? AND status = 'completed'
                ORDER BY completed_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $recentActivity = $stmt->fetchAll();
            
            // Tendencias (comparar esta semana vs semana anterior)
            $stmt = $this->pdo->prepare("
                SELECT 
                    'this_week' as period,
                    COUNT(*) as puzzles,
                    AVG(completion_time) as avg_time,
                    SUM(time_spent) as total_time
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                UNION ALL
                SELECT 
                    'last_week' as period,
                    COUNT(*) as puzzles,
                    AVG(completion_time) as avg_time,
                    SUM(time_spent) as total_time
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                    AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$userId, $userId]);
            $trends = $stmt->fetchAll();
            
            $dashboardData = [
                'user_stats' => $userStats,
                'weekly_progress' => $weeklyProgress,
                'difficulty_stats' => $difficultyStats,
                'achievement_progress' => $achievementProgress,
                'recent_activity' => $recentActivity,
                'trends' => $trends,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            error_log("✅ Dashboard analytics generado exitosamente");
            return $dashboardData;
            
        } catch (Exception $e) {
            error_log("❌ Error generando dashboard analytics: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener datos de progreso temporal
     */
    public function getProgressAnalytics($userId, $days = 30)
    {
        error_log("📈 Obteniendo analytics de progreso para usuario: $userId (últimos $days días)");
        
        try {
            // Progreso diario detallado
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(completed_at) as date,
                    difficulty_level,
                    COUNT(*) as puzzles_completed,
                    AVG(completion_time) as avg_completion_time,
                    MIN(completion_time) as best_time,
                    SUM(moves_count) as total_moves,
                    AVG(moves_count) as avg_moves,
                    SUM(hints_used) as total_hints,
                    SUM(mistakes_count) as total_mistakes,
                    COUNT(CASE WHEN perfect_game = TRUE THEN 1 END) as perfect_games
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(completed_at), difficulty_level
                ORDER BY date DESC, difficulty_level
            ");
            $stmt->execute([$userId, $days]);
            $dailyProgress = $stmt->fetchAll();
            
            // Agregado por día (suma de todas las dificultades)
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(completed_at) as date,
                    COUNT(*) as total_puzzles,
                    AVG(completion_time) as avg_time,
                    MIN(completion_time) as best_time,
                    SUM(time_spent) as total_time_spent,
                    AVG(moves_count) as avg_moves,
                    SUM(hints_used) as hints_used,
                    COUNT(CASE WHEN perfect_game = TRUE THEN 1 END) as perfect_games
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(completed_at)
                ORDER BY date DESC
            ");
            $stmt->execute([$userId, $days]);
            $dailyTotals = $stmt->fetchAll();
            
            // Heatmap de actividad (hora del día)
            $stmt = $this->pdo->prepare("
                SELECT 
                    HOUR(completed_at) as hour_of_day,
                    COUNT(*) as puzzles_count,
                    AVG(completion_time) as avg_performance
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY HOUR(completed_at)
                ORDER BY hour_of_day
            ");
            $stmt->execute([$userId, $days]);
            $hourlyActivity = $stmt->fetchAll();
            
            // Progreso por dificultad a lo largo del tiempo
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(completed_at) as date,
                    difficulty_level,
                    AVG(completion_time) as avg_time,
                    COUNT(*) as count
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(completed_at), difficulty_level
                ORDER BY date ASC
            ");
            $stmt->execute([$userId, $days]);
            $difficultyTrends = $stmt->fetchAll();
            
            // Racha actual y mejor racha
            $stmt = $this->pdo->prepare("
                SELECT 
                    completed_at,
                    DATE(completed_at) as completion_date
                FROM games 
                WHERE user_id = ? AND status = 'completed'
                ORDER BY completed_at DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $recentCompletions = $stmt->fetchAll();
            
            // Calcular rachas
            $currentStreak = $this->calculateCurrentStreak($recentCompletions);
            $bestStreak = $this->calculateBestStreak($userId, $days);
            
            $progressData = [
                'daily_progress' => $dailyProgress,
                'daily_totals' => $dailyTotals,
                'hourly_activity' => $hourlyActivity,
                'difficulty_trends' => $difficultyTrends,
                'current_streak' => $currentStreak,
                'best_streak' => $bestStreak,
                'period_days' => $days,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            error_log("✅ Progress analytics generado exitosamente");
            return $progressData;
            
        } catch (Exception $e) {
            error_log("❌ Error generando progress analytics: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calcular racha actual de días consecutivos
     */
    private function calculateCurrentStreak($recentCompletions)
    {
        if (empty($recentCompletions)) {
            return 0;
        }
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $streak = 0;
        
        // Agrupar por fecha
        $dateGroups = [];
        foreach ($recentCompletions as $completion) {
            $date = $completion['completion_date'];
            if (!isset($dateGroups[$date])) {
                $dateGroups[$date] = 0;
            }
            $dateGroups[$date]++;
        }
        
        // Verificar si hay actividad hoy o ayer para iniciar la racha
        $hasActivityToday = isset($dateGroups[$today]);
        $hasActivityYesterday = isset($dateGroups[$yesterday]);
        
        if (!$hasActivityToday && !$hasActivityYesterday) {
            return 0;
        }
        
        // Comenzar desde hoy si hay actividad, si no desde ayer
        $startDate = $hasActivityToday ? $today : $yesterday;
        
        // Contar días consecutivos hacia atrás
        $currentDate = $startDate;
        while (isset($dateGroups[$currentDate])) {
            $streak++;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
        }
        
        return $streak;
    }
    
    /**
     * Calcular la mejor racha en el período
     */
    private function calculateBestStreak($userId, $days)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(completed_at) as completion_date
                FROM games 
                WHERE user_id = ? 
                    AND status = 'completed'
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(completed_at)
                ORDER BY completion_date ASC
            ");
            $stmt->execute([$userId, $days]);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($dates)) {
                return 0;
            }
            
            $maxStreak = 0;
            $currentStreak = 1;
            
            for ($i = 1; $i < count($dates); $i++) {
                $prevDate = new DateTime($dates[$i-1]);
                $currDate = new DateTime($dates[$i]);
                $daysDiff = $currDate->diff($prevDate)->days;
                
                if ($daysDiff === 1) {
                    $currentStreak++;
                } else {
                    $maxStreak = max($maxStreak, $currentStreak);
                    $currentStreak = 1;
                }
            }
            
            return max($maxStreak, $currentStreak);
            
        } catch (Exception $e) {
            error_log("❌ Error calculando mejor racha: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Registrar métricas de rendimiento
     */
    public function recordPerformanceMetric($userId, $metricType, $value, $difficulty = null, $metadata = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_metrics 
                (user_id, metric_type, metric_value, difficulty_level, metadata) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $metadataJson = $metadata ? json_encode($metadata) : null;
            $stmt->execute([$userId, $metricType, $value, $difficulty, $metadataJson]);
            
            error_log("📊 Métrica registrada: $metricType = $value para usuario $userId");
        } catch (Exception $e) {
            error_log("❌ Error registrando métrica: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar estadísticas diarias
     */
    public function updateDailyStats($userId, $gameData)
    {
        try {
            $today = date('Y-m-d');
            
            // Obtener estadísticas actuales del día
            $stmt = $this->pdo->prepare("
                SELECT * FROM daily_stats 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$userId, $today]);
            $dailyStats = $stmt->fetch();
            
            $difficultyColumn = $gameData['difficulty_level'] . '_completed';
            
            if ($dailyStats) {
                // Actualizar estadísticas existentes
                $stmt = $this->pdo->prepare("
                    UPDATE daily_stats SET 
                        puzzles_completed = puzzles_completed + 1,
                        total_time_spent = total_time_spent + ?,
                        total_moves = total_moves + ?,
                        total_hints_used = total_hints_used + ?,
                        total_mistakes = total_mistakes + ?,
                        average_completion_time = (total_time_spent + ?) / (puzzles_completed + 1),
                        best_completion_time = CASE 
                            WHEN best_completion_time IS NULL OR ? < best_completion_time 
                            THEN ? ELSE best_completion_time END,
                        $difficultyColumn = $difficultyColumn + 1,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND date = ?
                ");
                
                $stmt->execute([
                    $gameData['completion_time'],
                    $gameData['moves_count'],
                    $gameData['hints_used'],
                    $gameData['mistakes_count'],
                    $gameData['completion_time'],
                    $gameData['completion_time'],
                    $gameData['completion_time'],
                    $userId,
                    $today
                ]);
            } else {
                // Crear nuevas estadísticas diarias
                $stmt = $this->pdo->prepare("
                    INSERT INTO daily_stats (
                        user_id, date, puzzles_completed, total_time_spent, 
                        total_moves, total_hints_used, total_mistakes,
                        average_completion_time, best_completion_time,
                        $difficultyColumn
                    ) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $userId,
                    $today,
                    $gameData['completion_time'],
                    $gameData['moves_count'],
                    $gameData['hints_used'],
                    $gameData['mistakes_count'],
                    $gameData['completion_time'],
                    $gameData['completion_time']
                ]);
            }
            
            error_log("📊 Estadísticas diarias actualizadas para usuario $userId");
        } catch (Exception $e) {
            error_log("❌ Error actualizando estadísticas diarias: " . $e->getMessage());
        }
    }
}