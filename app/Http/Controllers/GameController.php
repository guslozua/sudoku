<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class GameController extends Controller
{
    /**
     * Guardar progreso del juego
     */
    public function saveProgress(Request $request)
    {
        try {
            $gameId = $request->input('game_id');
            $currentState = $request->input('current_state');
            $notes = $request->input('notes', '[]');
            $movesCount = $request->input('moves_count', 0);

            // Validar datos
            if (!$gameId || !$currentState) {
                return response()->json(['error' => 'Datos requeridos faltantes'], 400);
            }

            if (strlen($currentState) !== 81) {
                return response()->json(['error' => 'Estado del juego inválido'], 400);
            }

            // Actualizar el juego
            $updated = DB::table('games')
                ->where('id', $gameId)
                ->update([
                    'current_state' => $currentState,
                    'notes' => $notes,
                    'moves_count' => $movesCount,
                    'last_played_at' => now()
                ]);

            if (!$updated) {
                return response()->json(['error' => 'Juego no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Progreso guardado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Completar puzzle
     */
    public function completePuzzle(Request $request)
    {
        try {
            $gameId = $request->input('game_id');
            $completionTime = $request->input('completion_time');
            $currentState = $request->input('current_state');

            // Obtener el juego
            $game = DB::table('games')->find($gameId);
            if (!$game) {
                return response()->json(['error' => 'Juego no encontrado'], 404);
            }

            // Verificar que el puzzle esté realmente completo
            if (str_contains($currentState ?: $game->current_state, '0')) {
                return response()->json(['error' => 'El puzzle no está completo'], 400);
            }

            // Actualizar el juego como completado
            DB::table('games')
                ->where('id', $gameId)
                ->update([
                    'status' => 'completed',
                    'completion_time' => $completionTime,
                    'completed_at' => now(),
                    'current_state' => $currentState ?: $game->current_state
                ]);

            // Obtener información del puzzle para las estadísticas
            $puzzle = DB::table('puzzles')->find($game->puzzle_id);
            
            // Actualizar estadísticas del usuario
            $this->updateUserStats($game->user_id, $puzzle->difficulty_level, $completionTime, $game->hints_used, $game->mistakes_count);

            return response()->json([
                'success' => true,
                'message' => 'Puzzle completado exitosamente',
                'completion_time' => $completionTime,
                'difficulty' => $puzzle->difficulty_level
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener juego actual en progreso
     */
    public function getCurrentGame(Request $request)
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $game = DB::table('games')
                ->join('puzzles', 'games.puzzle_id', '=', 'puzzles.id')
                ->where('games.user_id', $userId)
                ->where('games.status', 'in_progress')
                ->select(
                    'games.*',
                    'puzzles.puzzle_string',
                    'puzzles.solution_string',
                    'puzzles.difficulty_level'
                )
                ->orderBy('games.last_played_at', 'desc')
                ->first();

            if (!$game) {
                return response()->json(['message' => 'No hay juegos en progreso'], 404);
            }

            return response()->json([
                'success' => true,
                'game' => $game
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener historial de juegos
     */
    public function getGameHistory(Request $request)
    {
        try {
            $userId = $this->getCurrentUserId();
            $limit = $request->input('limit', 10);
            
            $games = DB::table('games')
                ->join('puzzles', 'games.puzzle_id', '=', 'puzzles.id')
                ->where('games.user_id', $userId)
                ->where('games.status', 'completed')
                ->select(
                    'games.id',
                    'games.completion_time',
                    'games.hints_used',
                    'games.mistakes_count',
                    'games.completed_at',
                    'puzzles.difficulty_level'
                )
                ->orderBy('games.completed_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'games' => $games
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener configuración del usuario
     */
    public function getUserSettings()
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $user = DB::table('users')->find($userId);
            if (!$user) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'settings' => [
                    'preferred_difficulty' => $user->preferred_difficulty,
                    'theme_preference' => $user->theme_preference,
                    'is_premium' => $user->is_premium
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Actualizar configuración del usuario
     */
    public function updateUserSettings(Request $request)
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $validThemes = ['light', 'dark', 'auto'];
            $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
            
            $updateData = [];
            
            if ($request->has('theme_preference') && in_array($request->theme_preference, $validThemes)) {
                $updateData['theme_preference'] = $request->theme_preference;
            }
            
            if ($request->has('preferred_difficulty') && in_array($request->preferred_difficulty, $validDifficulties)) {
                $updateData['preferred_difficulty'] = $request->preferred_difficulty;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                
                DB::table('users')
                    ->where('id', $userId)
                    ->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Actualizar estadísticas del usuario
     */
    private function updateUserStats($userId, $difficulty, $completionTime, $hintsUsed, $mistakes)
    {
        // Buscar estadísticas existentes para esta dificultad
        $stats = DB::table('user_stats')
            ->where('user_id', $userId)
            ->where('difficulty_level', $difficulty)
            ->first();

        if ($stats) {
            // Actualizar estadísticas existentes
            $newPuzzlesCompleted = $stats->puzzles_completed + 1;
            $newTotalTime = $stats->total_time_played + $completionTime;
            $newAverageTime = $newTotalTime / $newPuzzlesCompleted;
            $newBestTime = $stats->best_time ? min($stats->best_time, $completionTime) : $completionTime;
            $newCurrentStreak = $stats->current_streak + 1;
            $newBestStreak = max($stats->best_streak, $newCurrentStreak);
            $newSuccessRate = ($newPuzzlesCompleted / ($stats->puzzles_attempted + 1)) * 100;

            DB::table('user_stats')
                ->where('id', $stats->id)
                ->update([
                    'puzzles_completed' => $newPuzzlesCompleted,
                    'puzzles_attempted' => $stats->puzzles_attempted + 1,
                    'total_time_played' => $newTotalTime,
                    'best_time' => $newBestTime,
                    'average_time' => $newAverageTime,
                    'current_streak' => $newCurrentStreak,
                    'best_streak' => $newBestStreak,
                    'total_hints_used' => $stats->total_hints_used + $hintsUsed,
                    'total_mistakes' => $stats->total_mistakes + $mistakes,
                    'success_rate' => $newSuccessRate,
                    'updated_at' => now()
                ]);
        } else {
            // Crear nuevas estadísticas
            DB::table('user_stats')->insert([
                'user_id' => $userId,
                'difficulty_level' => $difficulty,
                'puzzles_completed' => 1,
                'puzzles_attempted' => 1,
                'total_time_played' => $completionTime,
                'best_time' => $completionTime,
                'average_time' => $completionTime,
                'current_streak' => 1,
                'best_streak' => 1,
                'total_hints_used' => $hintsUsed,
                'total_mistakes' => $mistakes,
                'success_rate' => 100.00,
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Obtener ID del usuario actual
     */
    private function getCurrentUserId()
    {
        $sessionId = Session::get('sudoku_session_id');
        
        if (!$sessionId) {
            throw new \Exception('Sesión no encontrada');
        }

        $user = DB::table('users')->where('session_id', $sessionId)->first();
        
        if (!$user) {
            throw new \Exception('Usuario no encontrado');
        }

        return $user->id;
    }
}