<?php
/**
 * Monitor de Performance para Sudoku
 * Rastrea métricas de rendimiento y detecta cuellos de botella
 */

class PerformanceMonitor 
{
    private static $startTime;
    private static $queries = [];
    private static $cacheHits = 0;
    private static $cacheMisses = 0;
    private static $memoryUsage = [];
    
    public static function start() 
    {
        self::$startTime = microtime(true);
        self::$memoryUsage['start'] = memory_get_usage(true);
    }
    
    public static function end() 
    {
        $endTime = microtime(true);
        $executionTime = ($endTime - self::$startTime) * 1000; // en milisegundos
        self::$memoryUsage['end'] = memory_get_usage(true);
        self::$memoryUsage['peak'] = memory_get_peak_usage(true);
        
        return [
            'execution_time_ms' => round($executionTime, 2),
            'memory_used_mb' => round((self::$memoryUsage['end'] - self::$memoryUsage['start']) / 1024 / 1024, 2),
            'memory_peak_mb' => round(self::$memoryUsage['peak'] / 1024 / 1024, 2),
            'queries_count' => count(self::$queries),
            'cache_hits' => self::$cacheHits,
            'cache_misses' => self::$cacheMisses,
            'cache_hit_ratio' => self::getCacheHitRatio()
        ];
    }
    
    public static function logQuery($query, $time) 
    {
        self::$queries[] = [
            'query' => $query,
            'time_ms' => $time,
            'timestamp' => microtime(true)
        ];
    }
    
    public static function recordCacheHit() 
    {
        self::$cacheHits++;
    }
    
    public static function recordCacheMiss() 
    {
        self::$cacheMisses++;
    }
    
    private static function getCacheHitRatio() 
    {
        $total = self::$cacheHits + self::$cacheMisses;
        return $total > 0 ? round((self::$cacheHits / $total) * 100, 1) : 0;
    }
    
    public static function getDetailedReport() 
    {
        $slowQueries = array_filter(self::$queries, function($q) {
            return $q['time_ms'] > 100; // Queries que toman más de 100ms
        });
        
        return [
            'performance' => self::end(),
            'slow_queries' => $slowQueries,
            'all_queries' => self::$queries,
            'recommendations' => self::getRecommendations()
        ];
    }
    
    private static function getRecommendations() 
    {
        $recommendations = [];
        
        // Verificar cache hit ratio
        if (self::getCacheHitRatio() < 70) {
            $recommendations[] = "Cache hit ratio bajo (" . self::getCacheHitRatio() . "%). Considerar aumentar TTL o revisar estrategia de cache.";
        }
        
        // Verificar queries lentas
        $slowQueries = array_filter(self::$queries, function($q) {
            return $q['time_ms'] > 100;
        });
        
        if (count($slowQueries) > 0) {
            $recommendations[] = count($slowQueries) . " queries lentas detectadas. Revisar índices de base de datos.";
        }
        
        // Verificar uso de memoria
        $memoryUsedMB = (self::$memoryUsage['end'] - self::$memoryUsage['start']) / 1024 / 1024;
        if ($memoryUsedMB > 16) {
            $recommendations[] = "Alto uso de memoria ({$memoryUsedMB}MB). Considerar optimizar carga de datos.";
        }
        
        // Verificar número de queries
        if (count(self::$queries) > 10) {
            $recommendations[] = count(self::$queries) . " queries ejecutadas. Considerar consolidar o usar cache.";
        }
        
        return $recommendations;
    }
    
    public static function reset() 
    {
        self::$startTime = null;
        self::$queries = [];
        self::$cacheHits = 0;
        self::$cacheMisses = 0;
        self::$memoryUsage = [];
    }
}

/**
 * Clase de Cache Optimizada con Monitoreo
 */
class OptimizedSudokuCache extends SudokuCache 
{
    public static function get($key) 
    {
        $value = parent::get($key);
        
        if ($value !== null) {
            PerformanceMonitor::recordCacheHit();
        } else {
            PerformanceMonitor::recordCacheMiss();
        }
        
        return $value;
    }
    
    public static function remember($key, $ttl, $callback) 
    {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        // Cache miss - ejecutar callback y medir tiempo
        $startTime = microtime(true);
        $value = $callback();
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000;
        error_log("Cache miss para '$key' - Callback ejecutado en {$executionTime}ms");
        
        self::set($key, $value, $ttl);
        
        return $value;
    }
}

/**
 * Wrapper PDO con Monitoreo
 */
class MonitoredPDO extends PDO 
{
    public function prepare($statement, $driver_options = []) 
    {
        $stmt = parent::prepare($statement, $driver_options);
        return new MonitoredPDOStatement($stmt, $statement);
    }
    
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args) 
    {
        $startTime = microtime(true);
        $result = parent::query($statement, $mode, ...$fetch_mode_args);
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000;
        PerformanceMonitor::logQuery($statement, $executionTime);
        
        return $result;
    }
}

class MonitoredPDOStatement 
{
    private $stmt;
    private $query;
    
    public function __construct($stmt, $query) 
    {
        $this->stmt = $stmt;
        $this->query = $query;
    }
    
    public function execute($input_parameters = null) 
    {
        $startTime = microtime(true);
        $result = $this->stmt->execute($input_parameters);
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000;
        PerformanceMonitor::logQuery($this->query, $executionTime);
        
        return $result;
    }
    
    // Delegar todos los otros métodos al statement original
    public function __call($method, $args) 
    {
        return call_user_func_array([$this->stmt, $method], $args);
    }
}

/**
 * Middleware de Performance para APIs
 */
class PerformanceMiddleware 
{
    public static function before() 
    {
        PerformanceMonitor::start();
    }
    
    public static function after($includeInResponse = false) 
    {
        $metrics = PerformanceMonitor::end();
        
        // Log metrics
        error_log("Performance: " . json_encode($metrics));
        
        // Incluir en response si se solicita
        if ($includeInResponse) {
            header('X-Performance-Time: ' . $metrics['execution_time_ms'] . 'ms');
            header('X-Performance-Memory: ' . $metrics['memory_used_mb'] . 'MB');
            header('X-Performance-Queries: ' . $metrics['queries_count']);
            header('X-Performance-Cache-Ratio: ' . $metrics['cache_hit_ratio'] . '%');
        }
        
        // Log warnings si hay problemas de performance
        if ($metrics['execution_time_ms'] > 1000) {
            error_log("WARNING: Slow response - " . $metrics['execution_time_ms'] . "ms");
        }
        
        if ($metrics['cache_hit_ratio'] < 50 && ($metrics['cache_hits'] + $metrics['cache_misses']) > 0) {
            error_log("WARNING: Low cache hit ratio - " . $metrics['cache_hit_ratio'] . "%");
        }
        
        return $metrics;
    }
}
