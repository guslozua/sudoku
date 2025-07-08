<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Estadísticas de Puzzles - Sudoku</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card.easy { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.medium { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.hard { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.expert { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
        .stat-card.master { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .stat-number { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .stat-label { font-size: 0.9em; opacity: 0.9; }
        .progress-bar { background: #e0e0e0; border-radius: 10px; padding: 3px; margin: 10px 0; }
        .progress-fill { background: #4caf50; height: 20px; border-radius: 7px; transition: width 0.3s ease; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .status-valid { color: #28a745; font-weight: bold; }
        .status-invalid { color: #dc3545; font-weight: bold; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .alert-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Estadísticas de Puzzles Sudoku</h1>
        
        <?php
        // Configuración de la base de datos
        $host = 'localhost';
        $dbname = 'sudoku';
        $username = 'root';
        $password = '';

        try {
            // Conectar a la base de datos
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo '<div class="alert alert-success">✅ Conectado a la base de datos exitosamente</div>';
            
            // Obtener estadísticas por dificultad
            $stmt = $pdo->query("
                SELECT 
                    difficulty_level,
                    COUNT(*) as total,
                    COUNT(CASE WHEN is_valid IS NULL OR is_valid = TRUE THEN 1 END) as validos,
                    COUNT(CASE WHEN is_valid = FALSE THEN 1 END) as invalidos
                FROM puzzles 
                GROUP BY difficulty_level 
                ORDER BY 
                    CASE difficulty_level 
                        WHEN 'easy' THEN 1
                        WHEN 'medium' THEN 2 
                        WHEN 'hard' THEN 3
                        WHEN 'expert' THEN 4
                        WHEN 'master' THEN 5
                    END
            ");
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales
            $totalPuzzles = 0;
            $totalValidos = 0;
            $totalInvalidos = 0;
            
            foreach ($stats as $stat) {
                $totalPuzzles += $stat['total'];
                $totalValidos += $stat['validos'];
                $totalInvalidos += $stat['invalidos'];
            }
            
            echo '<h2>🎯 Resumen General</h2>';
            echo '<div class="stats-grid">';
            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . $totalPuzzles . '</div>';
            echo '<div class="stat-label">Total de Puzzles</div>';
            echo '</div>';
            echo '<div class="stat-card" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);">';
            echo '<div class="stat-number">' . $totalValidos . '</div>';
            echo '<div class="stat-label">Puzzles Válidos</div>';
            echo '</div>';
            echo '<div class="stat-card" style="background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);">';
            echo '<div class="stat-number">' . $totalInvalidos . '</div>';
            echo '<div class="stat-label">Puzzles Inválidos</div>';
            echo '</div>';
            echo '<div class="stat-card" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);">';
            echo '<div class="stat-number">' . round(($totalValidos / $totalPuzzles) * 100, 1) . '%</div>';
            echo '<div class="stat-label">Tasa de Validez</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<h2>📊 Disponibilidad por Dificultad</h2>';
            echo '<div class="stats-grid">';
            
            foreach ($stats as $stat) {
                $difficulty = $stat['difficulty_level'];
                $total = $stat['total'];
                $validos = $stat['validos'];
                $invalidos = $stat['invalidos'];
                $porcentaje = $total > 0 ? round(($validos / $total) * 100, 1) : 0;
                
                // Determinar el nombre en español y clase CSS
                $nombres = [
                    'easy' => 'Fácil',
                    'medium' => 'Medio', 
                    'hard' => 'Difícil',
                    'expert' => 'Experto',
                    'master' => 'Maestro'
                ];
                
                $nombre = $nombres[$difficulty] ?? ucfirst($difficulty);
                
                echo '<div class="stat-card ' . $difficulty . '">';
                echo '<div class="stat-label">' . $nombre . '</div>';
                echo '<div class="stat-number">' . $validos . '</div>';
                echo '<div class="stat-label">de ' . $total . ' disponibles</div>';
                echo '<div class="progress-bar">';
                echo '<div class="progress-fill" style="width: ' . $porcentaje . '%"></div>';
                echo '</div>';
                echo '<div class="stat-label">' . $porcentaje . '% válidos</div>';
                echo '</div>';
            }
            
            echo '</div>';
            
            // Análisis detallado
            echo '<h2>📋 Análisis Detallado</h2>';
            echo '<table>';
            echo '<thead>';
            echo '<tr><th>Dificultad</th><th>Total</th><th>Válidos</th><th>Inválidos</th><th>% Válidos</th><th>Estado</th></tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($stats as $stat) {
                $difficulty = $stat['difficulty_level'];
                $total = $stat['total'];
                $validos = $stat['validos'];
                $invalidos = $stat['invalidos'];
                $porcentaje = $total > 0 ? round(($validos / $total) * 100, 1) : 0;
                
                $nombres = [
                    'easy' => 'Fácil',
                    'medium' => 'Medio', 
                    'hard' => 'Difícil',
                    'expert' => 'Experto',
                    'master' => 'Maestro'
                ];
                
                $nombre = $nombres[$difficulty] ?? ucfirst($difficulty);
                
                // Determinar estado
                $estado = '';
                $estadoClass = '';
                if ($validos >= 10) {
                    $estado = '✅ Excelente';
                    $estadoClass = 'status-valid';
                } elseif ($validos >= 5) {
                    $estado = '⚠️ Suficiente';
                    $estadoClass = 'status-warning';
                } else {
                    $estado = '❌ Necesita más';
                    $estadoClass = 'status-invalid';
                }
                
                echo '<tr>';
                echo '<td><strong>' . $nombre . '</strong></td>';
                echo '<td>' . $total . '</td>';
                echo '<td class="status-valid">' . $validos . '</td>';
                echo '<td class="status-invalid">' . $invalidos . '</td>';
                echo '<td>' . $porcentaje . '%</td>';
                echo '<td class="' . $estadoClass . '">' . $estado . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            // Recomendaciones
            echo '<h2>💡 Recomendaciones</h2>';
            
            $needsMore = [];
            foreach ($stats as $stat) {
                if ($stat['validos'] < 10) {
                    $needsMore[] = $stat['difficulty_level'];
                }
            }
            
            if (empty($needsMore)) {
                echo '<div class="alert alert-success">';
                echo '<strong>🎉 ¡Excelente!</strong> Tienes suficientes puzzles válidos en todas las dificultades.';
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning">';
                echo '<strong>⚠️ Atención:</strong> Algunas dificultades necesitan más puzzles:';
                echo '<ul>';
                foreach ($needsMore as $diff) {
                    $nombres = ['easy' => 'Fácil', 'medium' => 'Medio', 'hard' => 'Difícil', 'expert' => 'Experto', 'master' => 'Maestro'];
                    echo '<li>' . ($nombres[$diff] ?? $diff) . ': ' . ($stats[array_search($diff, array_column($stats, 'difficulty_level'))]['validos'] ?? 0) . ' válidos (recomendado: 10+)</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            // Información sobre generación
            echo '<h2>🔧 Cómo se Generan los Puzzles</h2>';
            echo '<div class="alert alert-info">';
            echo '<h4>📋 Sistema Actual:</h4>';
            echo '<ol>';
            echo '<li><strong>Base de datos fija:</strong> Los puzzles están almacenados en la tabla <code>puzzles</code></li>';
            echo '<li><strong>Selección aleatoria:</strong> El sistema elige un puzzle aleatorio de la dificultad solicitada</li>';
            echo '<li><strong>Validación automática:</strong> Solo se entregan puzzles marcados como válidos</li>';
            echo '<li><strong>Sin generación dinámica:</strong> No se crean puzzles nuevos automáticamente</li>';
            echo '</ol>';
            
            echo '<h4>🚀 Para agregar más puzzles:</h4>';
            echo '<ul>';
            echo '<li>📥 <strong>Importar desde archivos:</strong> Cargar puzzles desde archivos .txt o .csv</li>';
            echo '<li>🌐 <strong>APIs externas:</strong> Usar servicios como SudokuAPI o generadores online</li>';
            echo '<li>🤖 <strong>Generador propio:</strong> Implementar algoritmo de generación</li>';
            echo '<li>📚 <strong>Bases de datos públicas:</strong> Importar colecciones existentes</li>';
            echo '</ul>';
            echo '</div>';
            
            // Últimos puzzles agregados
            echo '<h2>📅 Últimos Puzzles en la Base de Datos</h2>';
            $stmt = $pdo->query("
                SELECT id, difficulty_level, 
                       CASE WHEN is_valid IS NULL OR is_valid = TRUE THEN 'Válido' ELSE 'Inválido' END as estado,
                       LEFT(puzzle_string, 20) as preview
                FROM puzzles 
                ORDER BY id DESC 
                LIMIT 10
            ");
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table>';
            echo '<thead>';
            echo '<tr><th>ID</th><th>Dificultad</th><th>Estado</th><th>Preview</th></tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($recent as $puzzle) {
                $statusClass = $puzzle['estado'] === 'Válido' ? 'status-valid' : 'status-invalid';
                echo '<tr>';
                echo '<td>' . $puzzle['id'] . '</td>';
                echo '<td>' . ucfirst($puzzle['difficulty_level']) . '</td>';
                echo '<td class="' . $statusClass . '">' . $puzzle['estado'] . '</td>';
                echo '<td><code>' . $puzzle['preview'] . '...</code></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">❌ Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>
</body>
</html>
