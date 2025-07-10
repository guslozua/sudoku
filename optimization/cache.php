<?php
/**
 * Sistema de Cache Simple para Sudoku
 * Optimiza consultas frecuentes y mejora performance
 */

class SimpleCache 
{
    private static $cache = [];
    private static $cacheFile = null;
    private static $ttl = 300; // 5 minutos por defecto
    
    public static function init() 
    {
        if (!self::$cacheFile) {
            $cacheDir = __DIR__ . '/../cache/';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            self::$cacheFile = $cacheDir . 'sudoku_cache.json';
            self::loadFromFile();
        }
    }
    
    /**
     * Obtener valor del cache
     */
    public static function get($key) 
    {
        self::init();
        
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $item = self::$cache[$key];
        
        // Verificar si expiró
        if (isset($item['expires']) && time() > $item['expires']) {
            unset(self::$cache[$key]);
            self::saveToFile();
            return null;
        }
        
        return $item['data'];
    }
    
    /**
     * Guardar valor en cache
     */
    public static function set($key, $data, $ttl = null) 
    {
        self::init();
        
        $ttl = $ttl ?? self::$ttl;
        
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        self::saveToFile();
    }
    
    /**
     * Verificar si existe en cache
     */
    public static function has($key) 
    {
        return self::get($key) !== null;
    }
    
    /**
     * Eliminar del cache
     */
    public static function forget($key) 
    {
        self::init();
        unset(self::$cache[$key]);
        self::saveToFile();
    }
    
    /**
     * Limpiar todo el cache
     */
    public static function clear() 
    {
        self::init();
        self::$cache = [];
        self::saveToFile();
    }
    
    /**
     * Cache con callback (get or set)
     */
    public static function remember($key, $ttl, $callback) 
    {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Obtener estadísticas del cache
     */
    public static function stats() 
    {
        self::init();
        
        $total = count(self::$cache);
        $expired = 0;
        $totalSize = 0;
        
        foreach (self::$cache as $item) {
            if (isset($item['expires']) && time() > $item['expires']) {
                $expired++;
            }
            $totalSize += strlen(serialize($item['data']));
        }
        
        return [
            'total_items' => $total,
            'expired_items' => $expired,
            'active_items' => $total - $expired,
            'cache_size_bytes' => $totalSize,
            'cache_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
    
    /**
     * Limpiar items expirados
     */
    public static function cleanup() 
    {
        self::init();
        
        $cleaned = 0;
        foreach (self::$cache as $key => $item) {
            if (isset($item['expires']) && time() > $item['expires']) {
                unset(self::$cache[$key]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            self::saveToFile();
        }
        
        return $cleaned;
    }
    
    /**
     * Cargar cache desde archivo
     */
    private static function loadFromFile() 
    {
        if (file_exists(self::$cacheFile)) {
            $content = file_get_contents(self::$cacheFile);
            $data = json_decode($content, true);
            if ($data) {
                self::$cache = $data;
            }
        }
    }
    
    /**
     * Guardar cache a archivo
     */
    private static function saveToFile() 
    {
        $content = json_encode(self::$cache, JSON_PRETTY_PRINT);
        file_put_contents(self::$cacheFile, $content, LOCK_EX);
    }
}

/**
 * Cache específico para Sudoku
 */
class SudokuCache extends SimpleCache 
{
    // TTL específicos para diferentes tipos de datos
    const TTL_PUZZLES = 3600;     // 1 hora - puzzles cambian poco
    const TTL_STATS = 300;        // 5 minutos - stats cambian más
    const TTL_USER_DATA = 600;    // 10 minutos - datos de usuario
    const TTL_LEADERBOARD = 900;  // 15 minutos - leaderboard
    
    /**
     * Cache para puzzles por dificultad
     */
    public static function getPuzzlesByDifficulty($difficulty) 
    {
        $key = "puzzles_$difficulty";
        return self::remember($key, self::TTL_PUZZLES, function() use ($difficulty) {
            // Esta función se ejecutará solo si no está en cache
            try {
                $pdo = new PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '');
                $stmt = $pdo->prepare("SELECT id, puzzle_string, solution_string, difficulty_level, clues_count FROM puzzles WHERE difficulty_level = ? AND is_valid = 1");
                $stmt->execute([$difficulty]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return [];
            }
        });
    }
    
    /**
     * Cache para estadísticas de usuario
     */
    public static function getUserStats($userId) 
    {
        $key = "user_stats_$userId";
        return self::remember($key, self::TTL_USER_DATA, function() use ($userId) {
            try {
                $pdo = new PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '');
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_games,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_games,
                        AVG(CASE WHEN status = 'completed' THEN completion_time ELSE NULL END) as avg_time
                    FROM games WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return ['total_games' => 0, 'completed_games' => 0, 'avg_time' => 0];
            }
        });
    }
    
    /**
     * Invalidar cache relacionado con usuario
     */
    public static function invalidateUserCache($userId) 
    {
        self::forget("user_stats_$userId");
        self::forget("user_games_$userId");
        self::forget("user_achievements_$userId");
    }
    
    /**
     * Invalidar cache cuando se agregan nuevos puzzles
     */
    public static function invalidatePuzzleCache($difficulty = null) 
    {
        if ($difficulty) {
            self::forget("puzzles_$difficulty");
        } else {
            // Invalidar todas las dificultades
            $difficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
            foreach ($difficulties as $diff) {
                self::forget("puzzles_$diff");
            }
        }
    }
}
