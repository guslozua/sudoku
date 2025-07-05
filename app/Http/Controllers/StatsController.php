<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class StatsController extends Controller
{
    /**
     * Obtener estadísticas del usuario actual
     */
    public function getUserStats()
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $stats = DB::table('user_stats')
                ->where('user_id', $userId)
                ->get()
                ->keyBy('difficulty_level');

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error obteniendo estadísticas'], 500);
        }
    }

    /**
     * Actualizar estadísticas (placeholder)
     */
    public function updateStats(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Stats updated'
        ]);
    }

    /**
     * Obtener tabla de líderes (placeholder)
     */
    public function getLeaderboard()
    {
        return response()->json([
            'success' => true,
            'leaderboard' => [],
            'message' => 'Leaderboard coming soon'
        ]);
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