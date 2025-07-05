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

            // Obtener puzzle aleatorio de la dificultad solicitada
            $puzzle = DB::table('puzzles')
                ->where('difficulty_level', $difficulty)
                ->inRandomOrder()
                ->first();

            if (!$puzzle) {
                return response()->json(['error' => 'No hay puzzles disponibles'], 404);
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
                'game_id' => $gameId
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
}