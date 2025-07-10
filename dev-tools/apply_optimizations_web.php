<?php
/**
 * Aplicador Web de Optimizaciones BD
 * Ejecuta las optimizaciones desde el navegador
 */

// Headers para mostrar en navegador
header('Content-Type: text/html; charset=utf-8');

echo '<html><head><title>Aplicar Optimizaciones BD</title>';
echo '<style>
body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
.success { color: #00ff00; }
.error { color: #ff6b6b; }
.warning { color: #ffa726; }
.info { color: #42a5f5; }
</style></head><body>';

echo '<h1>🚀 Aplicando Optimizaciones de Base de Datos</h1>';
echo '<pre>';

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<span class="success">✅ Conexión a base de datos establecida</span>' . "\n";
    
    // Crear índices
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_puzzles_difficulty_valid ON puzzles(difficulty_level, is_valid)" => "Búsquedas por dificultad y validez",
        "CREATE INDEX IF NOT EXISTS idx_puzzles_clues ON puzzles(clues_count)" => "Analíticas de pistas",
        "CREATE INDEX IF NOT EXISTS idx_games_user_status ON games(user_id, status)" => "Consultas de juegos por usuario",
        "CREATE INDEX IF NOT EXISTS idx_games_puzzle ON games(puzzle_id)" => "Búsquedas por puzzle",
        "CREATE INDEX IF NOT EXISTS idx_games_dates ON games(started_at, last_played_at)" => "Ordenamiento por fechas",
        "CREATE INDEX IF NOT EXISTS idx_games_completion_time ON games(completion_time, status)" => "Estadísticas de tiempo"
    ];
    
    echo "\n" . '<span class="info">📊 Creando índices optimizados...</span>' . "\n";
    foreach ($indexes as $sql => $description) {
        try {
            $pdo->exec($sql);
            echo '<span class="success">  ✅ ' . $description . '</span>' . "\n";
        } catch (PDOException $e) {
            echo '<span class="error">  ❌ Error en ' . $description . ': ' . $e->getMessage() . '</span>' . "\n";
        }
    }
    
    // Crear vistas
    echo "\n" . '<span class="info">👁️ Creando vistas optimizadas...</span>' . "\n";
    
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
    
    try {
        $pdo->exec($userStatsView);
        echo '<span class="success">  ✅ Vista de estadísticas de usuario</span>' . "\n";
    } catch (PDOException $e) {
        echo '<span class="error">  ❌ Error en vista de usuario: ' . $e->getMessage() . '</span>' . "\n";
    }
    
    // Verificar índices creados
    $stmt = $pdo->query("
        SELECT TABLE_NAME, INDEX_NAME, CARDINALITY 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = 'sudoku' 
        AND INDEX_NAME LIKE 'idx_%'
        ORDER BY TABLE_NAME, INDEX_NAME
    ");
    $indexes = $stmt->fetchAll();
    
    echo "\n" . '<span class="info">📈 Índices creados:</span>' . "\n";
    foreach ($indexes as $index) {
        echo '<span class="success">  - ' . $index['TABLE_NAME'] . '.' . $index['INDEX_NAME'] . ' (cardinalidad: ' . $index['CARDINALITY'] . ')</span>' . "\n";
    }
    
    // Test de performance
    echo "\n" . '<span class="info">⚡ Probando performance...</span>' . "\n";
    
    $start = microtime(true);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM puzzles WHERE difficulty_level = ? AND (is_valid = 1 OR is_valid IS NULL)");
    $stmt->execute(['easy']);
    $end = microtime(true);
    
    $queryTime = ($end - $start) * 1000;
    if ($queryTime < 50) {
        echo '<span class="success">  ✅ Query optimizada rápida: ' . round($queryTime, 2) . 'ms</span>' . "\n";
    } else {
        echo '<span class="warning">  ⚠️ Query lenta: ' . round($queryTime, 2) . 'ms (esperado <50ms)</span>' . "\n";
    }
    
    echo "\n" . '<span class="success">🎉 ¡Optimizaciones aplicadas exitosamente!</span>' . "\n";
    echo "\n" . '<span class="info">📋 Próximos pasos:</span>' . "\n";
    echo '<span class="info">  1. Probar sistema de cache</span>' . "\n";
    echo '<span class="info">  2. Verificar performance de APIs</span>' . "\n";
    echo '<span class="info">  3. Revisar métricas en headers HTTP</span>' . "\n";
    
} catch (PDOException $e) {
    echo '<span class="error">❌ Error de conexión: ' . $e->getMessage() . '</span>' . "\n";
}

echo '</pre>';
echo '<p><a href="../public/" style="color: #42a5f5;">← Volver al juego</a> | ';
echo '<a href="test_optimizations_web.php" style="color: #42a5f5;">Probar optimizaciones →</a></p>';
echo '</body></html>';
?>
