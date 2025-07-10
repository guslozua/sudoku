<?php
/**
 * Script para Aplicar Optimizaciones de Base de Datos
 * Sudoku Minimalista v2.0
 * 
 * Ejecuta las optimizaciones de BD de forma segura
 */

class DatabaseOptimizer 
{
    private $pdo;
    private $results = [];
    
    public function __construct() 
    {
        try {
            $this->pdo = new PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            echo "✅ Conexión a base de datos establecida\n";
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage() . "\n");
        }
    }
    
    public function runOptimizations() 
    {
        echo "🚀 INICIANDO OPTIMIZACIONES DE BASE DE DATOS\n";
        echo str_repeat("=", 50) . "\n\n";
        
        $this->createIndexes();
        $this->createViews();
        $this->createProcedures();
        $this->analyzePerformance();
        $this->showResults();
    }
    
    private function createIndexes() 
    {
        echo "📊 Creando índices optimizados...\n";
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_puzzles_difficulty_valid ON puzzles(difficulty_level, is_valid)" => "Búsquedas por dificultad y validez",
            "CREATE INDEX IF NOT EXISTS idx_puzzles_clues ON puzzles(clues_count)" => "Analíticas de pistas",
            "CREATE INDEX IF NOT EXISTS idx_games_user_status ON games(user_id, status)" => "Consultas de juegos por usuario",
            "CREATE INDEX IF NOT EXISTS idx_games_puzzle ON games(puzzle_id)" => "Búsquedas por puzzle",
            "CREATE INDEX IF NOT EXISTS idx_games_dates ON games(started_at, last_played_at)" => "Ordenamiento por fechas",
            "CREATE INDEX IF NOT EXISTS idx_games_completion_time ON games(completion_time, status)" => "Estadísticas de tiempo"
        ];
        
        foreach ($indexes as $sql => $description) {
            try {
                $this->pdo->exec($sql);
                echo "  ✅ $description\n";
                $this->results['indexes']['success'][] = $description;
            } catch (PDOException $e) {
                echo "  ❌ Error en $description: " . $e->getMessage() . "\n";
                $this->results['indexes']['errors'][] = "$description: " . $e->getMessage();
            }
        }
        echo "\n";
    }
    
    private function createViews() 
    {
        echo "👁️ Creando vistas optimizadas...\n";
        
        // Vista de estadísticas de usuario
        $userStatsView = "
        CREATE OR REPLACE VIEW user_stats_view AS
        SELECT 
            u.id as user_id,
            u.session_id,
            COUNT(g.id) as total_games,
            COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as completed_games,
            COUNT(CASE WHEN g.status = 'in_progress' THEN 1 END) as active_games,
            AVG(CASE WHEN g.status = 'completed' THEN g.completion_time END) as avg_completion_time,
            MIN(CASE WHEN g.status = 'completed' THEN g.completion_time END) as best_time,
            SUM(g.hints_used) as total_hints_used,
            SUM(g.mistakes_count) as total_mistakes,
            MAX(g.last_played_at) as last_activity
        FROM users u
        LEFT JOIN games g ON u.id = g.user_id
        GROUP BY u.id, u.session_id
        ";
        
        // Vista de estadísticas de puzzles
        $puzzleStatsView = "
        CREATE OR REPLACE VIEW puzzle_stats_view AS
        SELECT 
            p.difficulty_level,
            COUNT(*) as total_puzzles,
            COUNT(CASE WHEN p.is_valid = 1 THEN 1 END) as valid_puzzles,
            AVG(p.clues_count) as avg_clues,
            MIN(p.clues_count) as min_clues,
            MAX(p.clues_count) as max_clues,
            COUNT(g.id) as times_played,
            COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as times_completed,
            AVG(CASE WHEN g.status = 'completed' THEN g.completion_time END) as avg_solve_time
        FROM puzzles p
        LEFT JOIN games g ON p.id = g.puzzle_id
        WHERE p.is_valid = 1 OR p.is_valid IS NULL
        GROUP BY p.difficulty_level
        ";
        
        $views = [
            $userStatsView => "Vista de estadísticas de usuario",
            $puzzleStatsView => "Vista de estadísticas de puzzles"
        ];
        
        foreach ($views as $sql => $description) {
            try {
                $this->pdo->exec($sql);
                echo "  ✅ $description\n";
                $this->results['views']['success'][] = $description;
            } catch (PDOException $e) {
                echo "  ❌ Error en $description: " . $e->getMessage() . "\n";
                $this->results['views']['errors'][] = "$description: " . $e->getMessage();
            }
        }
        echo "\n";
    }
    
    private function createProcedures() 
    {
        echo "⚙️ Creando procedimientos almacenados...\n";
        
        // Procedimiento para puzzle aleatorio optimizado
        $randomPuzzleProc = "
        DROP PROCEDURE IF EXISTS GetRandomPuzzle;
        CREATE PROCEDURE GetRandomPuzzle(IN difficulty VARCHAR(10))
        BEGIN
            DECLARE puzzle_count INT;
            DECLARE random_offset INT;
            
            SELECT COUNT(*) INTO puzzle_count 
            FROM puzzles 
            WHERE difficulty_level = difficulty AND (is_valid = 1 OR is_valid IS NULL);
            
            IF puzzle_count > 0 THEN
                SET random_offset = FLOOR(RAND() * puzzle_count);
                
                SELECT id, puzzle_string, solution_string, difficulty_level, clues_count
                FROM puzzles 
                WHERE difficulty_level = difficulty AND (is_valid = 1 OR is_valid IS NULL)
                LIMIT 1 OFFSET random_offset;
            ELSE
                SELECT NULL as id, NULL as puzzle_string, NULL as solution_string, 
                       NULL as difficulty_level, NULL as clues_count;
            END IF;
        END
        ";
        
        // Procedimiento para estadísticas rápidas
        $quickStatsProc = "
        DROP PROCEDURE IF EXISTS GetUserQuickStats;
        CREATE PROCEDURE GetUserQuickStats(IN userId INT)
        BEGIN
            SELECT 
                COUNT(*) as total_games,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as active_games,
                AVG(CASE WHEN status = 'completed' THEN completion_time END) as avg_time,
                MIN(CASE WHEN status = 'completed' THEN completion_time END) as best_time,
                SUM(hints_used) as total_hints,
                MAX(last_played_at) as last_activity
            FROM games 
            WHERE user_id = userId;
        END
        ";
        
        $procedures = [
            $randomPuzzleProc => "Procedimiento para puzzle aleatorio",
            $quickStatsProc => "Procedimiento para estadísticas rápidas"
        ];
        
        foreach ($procedures as $sql => $description) {
            try {
                $this->pdo->exec($sql);
                echo "  ✅ $description\n";
                $this->results['procedures']['success'][] = $description;
            } catch (PDOException $e) {
                echo "  ❌ Error en $description: " . $e->getMessage() . "\n";
                $this->results['procedures']['errors'][] = "$description: " . $e->getMessage();
            }
        }
        echo "\n";
    }
    
    private function analyzePerformance() 
    {
        echo "📈 Analizando performance...\n";
        
        try {
            // Verificar índices creados
            $stmt = $this->pdo->query("
                SELECT TABLE_NAME, INDEX_NAME, CARDINALITY 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = 'sudoku' 
                AND INDEX_NAME LIKE 'idx_%'
                ORDER BY TABLE_NAME, INDEX_NAME
            ");
            $indexes = $stmt->fetchAll();
            
            echo "  📊 Índices creados: " . count($indexes) . "\n";
            foreach ($indexes as $index) {
                echo "    - {$index['TABLE_NAME']}.{$index['INDEX_NAME']} (cardinalidad: {$index['CARDINALITY']})\n";
            }
            
            // Verificar vistas
            $stmt = $this->pdo->query("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.VIEWS 
                WHERE TABLE_SCHEMA = 'sudoku'
            ");
            $views = $stmt->fetchAll();
            
            echo "  👁️ Vistas creadas: " . count($views) . "\n";
            foreach ($views as $view) {
                echo "    - {$view['TABLE_NAME']}\n";
            }
            
            // Verificar procedimientos
            $stmt = $this->pdo->query("
                SELECT ROUTINE_NAME 
                FROM INFORMATION_SCHEMA.ROUTINES 
                WHERE ROUTINE_SCHEMA = 'sudoku' 
                AND ROUTINE_TYPE = 'PROCEDURE'
            ");
            $procedures = $stmt->fetchAll();
            
            echo "  ⚙️ Procedimientos creados: " . count($procedures) . "\n";
            foreach ($procedures as $proc) {
                echo "    - {$proc['ROUTINE_NAME']}\n";
            }
            
        } catch (PDOException $e) {
            echo "  ❌ Error analizando performance: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function showResults() 
    {
        echo str_repeat("=", 50) . "\n";
        echo "📊 RESUMEN DE OPTIMIZACIONES\n";
        echo str_repeat("=", 50) . "\n";
        
        $totalSuccess = 0;
        $totalErrors = 0;
        
        foreach ($this->results as $category => $results) {
            echo "\n" . strtoupper($category) . ":\n";
            
            if (isset($results['success'])) {
                echo "  ✅ Exitosos: " . count($results['success']) . "\n";
                $totalSuccess += count($results['success']);
            }
            
            if (isset($results['errors'])) {
                echo "  ❌ Errores: " . count($results['errors']) . "\n";
                $totalErrors += count($results['errors']);
                foreach ($results['errors'] as $error) {
                    echo "    - $error\n";
                }
            }
        }
        
        echo "\n" . str_repeat("-", 30) . "\n";
        echo "TOTAL EXITOSOS: $totalSuccess\n";
        echo "TOTAL ERRORES: $totalErrors\n";
        
        $successRate = $totalSuccess + $totalErrors > 0 ? 
            round(($totalSuccess / ($totalSuccess + $totalErrors)) * 100, 1) : 0;
        
        echo "TASA DE ÉXITO: {$successRate}%\n";
        
        if ($totalErrors === 0) {
            echo "\n🎉 ¡TODAS LAS OPTIMIZACIONES APLICADAS EXITOSAMENTE!\n";
        } elseif ($successRate >= 70) {
            echo "\n✅ Optimizaciones mayormente exitosas\n";
        } else {
            echo "\n⚠️ Revisar errores antes de continuar\n";
        }
        
        echo "\n📝 Próximos pasos:\n";
        echo "  1. Ejecutar ANALYZE TABLE en tablas principales\n";
        echo "  2. Monitorear performance con PerformanceMonitor\n";
        echo "  3. Ajustar configuración de MySQL según recomendaciones\n";
    }
}

// Ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $optimizer = new DatabaseOptimizer();
    $optimizer->runOptimizations();
}
