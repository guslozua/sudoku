<?php
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

            $maxAttempts = 10;
            $attempts = 0;
            $puzzle = null;

            do {
                $attempts++;
                
                // Obtener puzzle aleatorio de la dificultad solicitada
                $candidatePuzzle = DB::table('puzzles')
                    ->where('difficulty_level', $difficulty)
                    ->where(function($query) {
                        $query->where('is_valid', true)
                              ->orWhereNull('is_valid');
                    })
                    ->inRandomOrder()
                    ->first();

                if (!$candidatePuzzle) {
                    return response()->json(['error' => 'No hay puzzles disponibles'], 404);
                }
                
                if ($this->validatePuzzleBoard($candidatePuzzle->puzzle_string)) {
                    $puzzle = $candidatePuzzle;
                    break;
                } else {
                    error_log("Puzzle inválido detectado - ID: {$candidatePuzzle->id}, Intento: $attempts");
                }
                
            } while ($attempts < $maxAttempts);
            
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
                'validation_attempts' => $attempts
            ]);

        } catch (\Exception $e) {
            error_log("Error in getNewPuzzle: " . $e->getMessage());
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
     * Obtener logros del usuario
     */
    public function getAchievements(Request $request)
    {
        try {
            $userId = $this->getUserId($request);
            
            // Obtener logros del usuario
            $userAchievements = DB::select("
                SELECT a.*, ua.unlocked_at, ua.is_completed
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                ORDER BY a.category, a.id
            ", [$userId]);
            
            return response()->json([
                'success' => true,
                'achievements' => $userAchievements
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en getAchievements: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error cargando logros'
            ], 500);
        }
    }

    /**
     * Verificar logros del usuario
     */
    public function checkAchievements(Request $request)
    {
        try {
            $userId = $this->getUserId($request);
            
            // Obtener estadísticas del usuario
            $stats = $this->getUserStats($userId);
            
            $newAchievements = [];
            
            // Verificar logros de completado
            $completionAchievements = [
                ['games' => 1, 'key' => 'first_step'],
                ['games' => 10, 'key' => 'puzzle_master'],
                ['games' => 50, 'key' => 'sudoku_legend']
            ];
            
            foreach ($completionAchievements as $achievement) {
                if ($stats->completed_games >= $achievement['games']) {
                    $unlocked = $this->unlockAchievementByKey($userId, $achievement['key']);
                    if ($unlocked) {
                        $newAchievements[] = $unlocked;
                    }
                }
            }
            
            // Verificar logros de velocidad
            if ($stats->best_time && $stats->best_time <= 300) { // 5 minutos
                $unlocked = $this->unlockAchievementByKey($userId, 'speed_demon');
                if ($unlocked) {
                    $newAchievements[] = $unlocked;
                }
            }
            
            if ($stats->best_time && $stats->best_time <= 180) { // 3 minutos
                $unlocked = $this->unlockAchievementByKey($userId, 'lightning_fast');
                if ($unlocked) {
                    $newAchievements[] = $unlocked;
                }
            }
            
            return response()->json([
                'success' => true,
                'new_achievements' => $newAchievements
            ]);
            
        } catch (\Exception $e) {
            error_log('Error en checkAchievements: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error verificando logros'
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Obtener o crear usuario de sesión anónimo
     */
    private function getOrCreateSessionUser()
    {
        // ✅ VERIFICAR SESIÓN DE FORMA SEGURA
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionId = $_SESSION['sudoku_session_id'] ?? null;
        
        if (!$sessionId) {
            $sessionId = 'sudoku_' . uniqid() . '.' . mt_rand();
            $_SESSION['sudoku_session_id'] = $sessionId;
        }

        // ✅ USAR TRANSACCIÓN PARA EVITAR DUPLICADOS
        DB::beginTransaction();
        
        try {
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
                
                DB::commit();
                error_log("👤 Nuevo usuario creado: ID $userId, Session: $sessionId");
                return $userId;
            }

            DB::commit();
            error_log("👤 Usuario existente: ID {$user->id}, Session: $sessionId");
            return $user->id;
            
        } catch (\Exception $e) {
            DB::rollBack();
            error_log("❌ Error creando usuario: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener ID del usuario desde request
     */
    private function getUserId($request)
    {
        return $this->getOrCreateSessionUser();
    }

    /**
     * Validar que un puzzle no tenga errores
     */
    private function validatePuzzleBoard($puzzleString)
    {
        // ✅ VALIDACIÓN RÁPIDA DE FORMATO
        if (strlen($puzzleString) !== 81) {
            error_log("❌ Puzzle inválido: longitud incorrecta (" . strlen($puzzleString) . " != 81)");
            return false;
        }
        
        // ✅ OPTIMIZACIÓN: Solo verificar números existentes
        $board = [];
        $occupiedCells = [];
        
        for ($i = 0; $i < 9; $i++) {
            $row = [];
            for ($j = 0; $j < 9; $j++) {
                $value = intval($puzzleString[$i * 9 + $j]);
                $row[] = $value;
                
                // ✅ SOLO PROCESAR CELDAS OCUPADAS
                if ($value !== 0) {
                    $occupiedCells[] = ['row' => $i, 'col' => $j, 'value' => $value];
                }
            }
            $board[] = $row;
        }
        
        // ✅ VERIFICAR SOLO CELDAS OCUPADAS (MÁS EFICIENTE)
        foreach ($occupiedCells as $cell) {
            if ($this->hasConflictInBoard($board, $cell['row'], $cell['col'], $cell['value'])) {
                error_log("❌ Conflicto detectado en ({$cell['row']}, {$cell['col']}) con valor {$cell['value']}");
                return false;
            }
        }
        
        error_log("✅ Puzzle válido: {" . count($occupiedCells) . "} celdas verificadas");
        return true;
    }

    /**
     * Detectar conflictos en un tablero
     */
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

    /**
     * Desbloquear logro por clave
     */
    private function unlockAchievementByKey($userId, $achievementKey)
    {
        try {
            // Obtener el logro por su clave
            $achievement = DB::table('achievements')->where('key_name', $achievementKey)->first();
            
            if (!$achievement) {
                return null;
            }
            
            // Verificar si ya está desbloqueado
            $exists = DB::table('user_achievements')
                ->where('user_id', $userId)
                ->where('achievement_id', $achievement->id)
                ->exists();
            
            if (!$exists) {
                DB::table('user_achievements')->insert([
                    'user_id' => $userId,
                    'achievement_id' => $achievement->id,
                    'is_completed' => true,
                    'unlocked_at' => now(),
                    'current_progress' => $achievement->target_value
                ]);
                
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'category' => $achievement->category
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            error_log('Error desbloqueando logro: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener estadísticas del usuario
     */
    private function getUserStats($userId)
    {
        return DB::selectOne("
            SELECT 
                COUNT(*) as total_games,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games,
                MIN(CASE WHEN status = 'completed' THEN completion_time END) as best_time,
                AVG(CASE WHEN status = 'completed' THEN completion_time END) as avg_time
            FROM games 
            WHERE user_id = ?
        ", [$userId]);
    }
}