<?php

namespace App\Http\Controllers;

// StatsController sin dependencias de Laravel
class StatsController extends Controller
{
    /**
     * Obtener estadísticas básicas del usuario
     */
    public function getUserStats($request = null)
    {
        try {
            error_log("📊 getUserStats llamado");
            
            $userId = $this->getCurrentUserId();
            error_log("📊 User ID obtenido: $userId");
            
            // Obtener estadísticas básicas desde la tabla games
            $stats = $this->getStatsFromGames($userId);
            
            return [
                'success' => true,
                'stats' => $stats,
                'user_id' => $userId,
                'debug' => 'getUserStats ejecutado correctamente'
            ];

        } catch (\Exception $e) {
            error_log("❌ Error en getUserStats: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error obteniendo estadísticas: ' . $e->getMessage(),
                'debug' => [
                    'method' => 'getUserStats',
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Obtener estadísticas del dashboard
     */
    public function getDashboardStats($request = null)
    {
        try {
            error_log("📊 getDashboardStats llamado");
            
            $userId = $this->getCurrentUserId();
            error_log("📊 Dashboard User ID: $userId");
            
            // Estadísticas generales
            $userStats = $this->getStatsFromGames($userId);
            
            // Estadísticas por dificultad
            $difficultyStats = $this->getDifficultyStats($userId);
            
            // Actividad reciente
            $recentActivity = $this->getRecentActivity($userId);
            
            return [
                'success' => true,
                'data' => [
                    'user_stats' => $userStats,
                    'difficulty_stats' => $difficultyStats,
                    'recent_activity' => $recentActivity,
                    'generated_at' => date('Y-m-d H:i:s')
                ],
                'debug' => 'getDashboardStats ejecutado correctamente'
            ];
            
        } catch (\Exception $e) {
            error_log("❌ Error en getDashboardStats: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error cargando dashboard: ' . $e->getMessage(),
                'debug' => [
                    'method' => 'getDashboardStats',
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * Obtener analíticas del dashboard
     */
    public function getDashboardAnalytics($request = null)
    {
        try {
            error_log("📊 getDashboardAnalytics llamado");
            
            $userId = $this->getCurrentUserId();
            
            // Estadísticas generales
            $userStats = $this->getStatsFromGames($userId);
            
            // Distribución por dificultad
            $difficultyStats = $this->getDifficultyStats($userId);
            
            // Progreso diario (últimos 30 días)
            $dailyProgress = $this->getDailyProgress($userId, 30);
            
            return [
                'success' => true,
                'data' => [
                    'user_stats' => $userStats,
                    'difficulty_stats' => $difficultyStats,
                    'daily_progress' => $dailyProgress,
                    'current_streak' => 0, // Simplificado por ahora
                    'best_streak' => 0,    // Simplificado por ahora
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("❌ Error en getDashboardAnalytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error cargando analíticas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener analíticas de progreso
     */
    public function getProgressAnalytics($request = null)
    {
        try {
            error_log("📊 getProgressAnalytics llamado");
            
            $userId = $this->getCurrentUserId();
            $days = 30;
            
            // Progreso diario
            $dailyProgress = $this->getDailyProgress($userId, $days);
            
            // Totales por día
            $dailyTotals = $this->getDailyTotals($userId, $days);
            
            return [
                'success' => true,
                'data' => [
                    'daily_progress' => $dailyProgress,
                    'daily_totals' => $dailyTotals,
                    'period_days' => $days,
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("❌ Error en getProgressAnalytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error cargando progreso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener tabla de líderes
     */
    public function getLeaderboard($request = null)
    {
        try {
            error_log("📊 getLeaderboard llamado");
            
            return [
                'success' => true,
                'leaderboard' => [
                    'top_completed' => [],
                    'top_speed' => []
                ],
                'message' => 'Leaderboard próximamente'
            ];

        } catch (\Exception $e) {
            error_log("❌ Error en getLeaderboard: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error cargando leaderboard: ' . $e->getMessage()
            ];
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Obtener estadísticas desde la tabla games
     */
    private function getStatsFromGames($userId)
    {
        try {
            // Usar conexión PDO directa para evitar problemas con Laravel
            $pdo = $this->getPDOConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_games,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games,
                    ROUND(AVG(CASE WHEN status = 'completed' THEN completion_time END), 2) as avg_completion_time,
                    MIN(CASE WHEN status = 'completed' THEN completion_time END) as best_time,
                    SUM(CASE WHEN status = 'completed' THEN time_spent ELSE 0 END) as total_time_played,
                    SUM(moves_count) as total_moves,
                    SUM(hints_used) as total_hints,
                    SUM(mistakes_count) as total_mistakes,
                    COUNT(CASE WHEN perfect_game = 1 THEN 1 END) as perfect_games
                FROM games 
                WHERE user_id = ?
            ");
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $result ?: [
                'total_games' => 0,
                'completed_games' => 0,
                'avg_completion_time' => 0,
                'best_time' => null,
                'total_time_played' => 0,
                'total_moves' => 0,
                'total_hints' => 0,
                'total_mistakes' => 0,
                'perfect_games' => 0
            ];
            
        } catch (\Exception $e) {
            error_log("❌ Error en getStatsFromGames: " . $e->getMessage());
            return [
                'total_games' => 0,
                'completed_games' => 0,
                'avg_completion_time' => 0,
                'best_time' => null,
                'total_time_played' => 0,
                'total_moves' => 0,
                'total_hints' => 0,
                'total_mistakes' => 0,
                'perfect_games' => 0
            ];
        }
    }

    /**
     * Obtener estadísticas por dificultad
     */
    private function getDifficultyStats($userId)
    {
        try {
            $pdo = $this->getPDOConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.difficulty_level,
                    COUNT(g.id) as total_games,
                    COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as completed,
                    ROUND(AVG(CASE WHEN g.status = 'completed' THEN g.completion_time END), 2) as avg_time,
                    MIN(CASE WHEN g.status = 'completed' THEN g.completion_time END) as best_time
                FROM games g
                JOIN puzzles p ON g.puzzle_id = p.id
                WHERE g.user_id = ?
                GROUP BY p.difficulty_level
                ORDER BY 
                    CASE p.difficulty_level 
                        WHEN 'easy' THEN 1
                        WHEN 'medium' THEN 2 
                        WHEN 'hard' THEN 3
                        WHEN 'expert' THEN 4
                        WHEN 'master' THEN 5
                    END
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("❌ Error en getDifficultyStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity($userId)
    {
        try {
            $pdo = $this->getPDOConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.difficulty_level,
                    g.completion_time,
                    g.moves_count,
                    g.hints_used,
                    g.mistakes_count,
                    g.completed_at,
                    g.perfect_game
                FROM games g
                JOIN puzzles p ON g.puzzle_id = p.id
                WHERE g.user_id = ? AND g.status = 'completed'
                ORDER BY g.completed_at DESC
                LIMIT 10
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("❌ Error en getRecentActivity: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener progreso diario
     */
    private function getDailyProgress($userId, $days = 30)
    {
        try {
            $pdo = $this->getPDOConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(g.completed_at) as date,
                    COUNT(*) as puzzles_completed,
                    ROUND(AVG(g.completion_time), 2) as avg_time,
                    MIN(g.completion_time) as best_time,
                    COUNT(CASE WHEN g.perfect_game = 1 THEN 1 END) as perfect_games
                FROM games g
                WHERE g.user_id = ? AND g.status = 'completed'
                    AND g.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(g.completed_at)
                ORDER BY date ASC
            ");
            
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("❌ Error en getDailyProgress: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener totales diarios
     */
    private function getDailyTotals($userId, $days = 30)
    {
        try {
            $pdo = $this->getPDOConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(g.completed_at) as date,
                    COUNT(*) as total_puzzles,
                    ROUND(AVG(g.completion_time), 2) as avg_time,
                    MIN(g.completion_time) as best_time,
                    SUM(g.time_spent) as total_time_spent,
                    COUNT(CASE WHEN g.perfect_game = 1 THEN 1 END) as perfect_games
                FROM games g
                WHERE g.user_id = ? 
                    AND g.status = 'completed'
                    AND g.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(g.completed_at)
                ORDER BY date DESC
            ");
            
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("❌ Error en getDailyTotals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener conexión PDO
     */
    private function getPDOConnection()
    {
        static $pdo = null;
        
        if ($pdo === null) {
            try {
                $host = 'localhost';
                $dbname = 'sudoku';
                $username = 'root';
                $password = '';
                
                $pdo = new \PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $username,
                    $password,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                    ]
                );
            } catch (\PDOException $e) {
                error_log("❌ Error conectando a BD: " . $e->getMessage());
                throw $e;
            }
        }
        
        return $pdo;
    }

    /**
     * Obtener ID del usuario actual
     */
    private function getCurrentUserId()
    {
        try {
            // Asegurar que la sesión esté iniciada
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $sessionId = $_SESSION['sudoku_session_id'] ?? null;
            
            if (!$sessionId) {
                // Generar nuevo session_id
                $sessionId = 'sudoku_' . uniqid() . '.' . mt_rand();
                $_SESSION['sudoku_session_id'] = $sessionId;
                error_log("🆔 Nuevo session_id generado: $sessionId");
            }
            
            $pdo = $this->getPDOConnection();
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Crear usuario si no existe
                $stmt = $pdo->prepare("
                    INSERT INTO users (session_id, is_anonymous, is_premium, preferred_difficulty, theme_preference, created_at, updated_at) 
                    VALUES (?, 1, 0, 'medium', 'auto', NOW(), NOW())
                ");
                $stmt->execute([$sessionId]);
                $userId = $pdo->lastInsertId();
                
                error_log("👤 Usuario creado con ID: $userId");
                return $userId;
            }
            
            error_log("👤 Usuario existente ID: " . $user['id']);
            return $user['id'];
            
        } catch (\Exception $e) {
            error_log("❌ Error obteniendo usuario: " . $e->getMessage());
            throw new \Exception('No se pudo obtener el usuario: ' . $e->getMessage());
        }
    }
}
