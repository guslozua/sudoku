<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador de Puzzles - Sudoku</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .valid { color: #28a745; }
        .invalid { color: #dc3545; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        button { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        button.warning { background: #ffc107; color: #212529; }
        button.warning:hover { background: #e0a800; }
        .puzzle-info { font-family: monospace; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Validador de Puzzles Sudoku</h1>
        
        <?php
        // Función para validar un puzzle
        function validatePuzzleBoard($puzzleString) {
            if (strlen($puzzleString) !== 81) {
                return false;
            }
            
            // Convertir string a array 2D
            $board = [];
            for ($i = 0; $i < 9; $i++) {
                $row = [];
                for ($j = 0; $j < 9; $j++) {
                    $row[] = intval($puzzleString[$i * 9 + $j]);
                }
                $board[] = $row;
            }
            
            // Verificar cada celda con número
            for ($row = 0; $row < 9; $row++) {
                for ($col = 0; $col < 9; $col++) {
                    $num = $board[$row][$col];
                    if ($num != 0) {
                        if (hasConflictInBoard($board, $row, $col, $num)) {
                            return false; // Puzzle inválido
                        }
                    }
                }
            }
            
            return true; // Puzzle válido
        }

        // Función para detectar conflictos
        function hasConflictInBoard($board, $row, $col, $num) {
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

        // Configuración de la base de datos
        $host = 'localhost';
        $dbname = 'sudoku';
        $username = 'root';
        $password = '';

        try {
            // Conectar a la base de datos
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo '<p class="valid">✅ Conectado a la base de datos exitosamente</p>';
            
            // Procesar acciones
            if (isset($_POST['action'])) {
                $invalidPuzzles = json_decode($_POST['invalid_puzzles'], true);
                
                switch ($_POST['action']) {
                    case 'mark_invalid':
                        try {
                            // Verificar si la columna is_valid existe
                            $stmt = $pdo->query("SHOW COLUMNS FROM puzzles LIKE 'is_valid'");
                            if ($stmt->rowCount() == 0) {
                                $pdo->exec("ALTER TABLE puzzles ADD COLUMN is_valid BOOLEAN DEFAULT TRUE");
                                echo '<p class="info">✅ Columna "is_valid" agregada a la tabla puzzles</p>';
                            }
                            
                            // Marcar puzzles inválidos
                            if (!empty($invalidPuzzles)) {
                                $placeholders = str_repeat('?,', count($invalidPuzzles) - 1) . '?';
                                $stmt = $pdo->prepare("UPDATE puzzles SET is_valid = FALSE WHERE id IN ($placeholders)");
                                $stmt->execute($invalidPuzzles);
                                
                                echo '<p class="valid">✅ ' . count($invalidPuzzles) . ' puzzles marcados como inválidos</p>';
                                echo '<p class="info">💡 El sistema ahora evitará entregar estos puzzles a los usuarios</p>';
                            }
                        } catch (Exception $e) {
                            echo '<p class="invalid">❌ Error al marcar puzzles: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        }
                        break;
                        
                    case 'delete_invalid':
                        try {
                            if (!empty($invalidPuzzles)) {
                                $placeholders = str_repeat('?,', count($invalidPuzzles) - 1) . '?';
                                $stmt = $pdo->prepare("DELETE FROM puzzles WHERE id IN ($placeholders)");
                                $stmt->execute($invalidPuzzles);
                                
                                echo '<p class="valid">✅ ' . count($invalidPuzzles) . ' puzzles eliminados permanentemente</p>';
                            }
                        } catch (Exception $e) {
                            echo '<p class="invalid">❌ Error al eliminar puzzles: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        }
                        break;
                }
                
                echo '<hr>';
            }
            
            // Obtener todos los puzzles
            $stmt = $pdo->query("SELECT id, puzzle_string, difficulty_level FROM puzzles ORDER BY id");
            $puzzles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<h2>📊 Análisis de Puzzles</h2>';
            echo '<p><strong>Total de puzzles:</strong> ' . count($puzzles) . '</p>';
            
            $validCount = 0;
            $invalidCount = 0;
            $invalidPuzzles = [];
            $validPuzzles = [];
            
            echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
            
            foreach ($puzzles as $puzzle) {
                $isValid = validatePuzzleBoard($puzzle['puzzle_string']);
                
                if ($isValid) {
                    $validCount++;
                    $validPuzzles[] = $puzzle['id'];
                    echo '<div class="valid">✅ Puzzle ID ' . $puzzle['id'] . ' - ' . $puzzle['difficulty_level'] . ' - VÁLIDO</div>';
                } else {
                    $invalidCount++;
                    $invalidPuzzles[] = $puzzle['id'];
                    echo '<div class="invalid">❌ Puzzle ID ' . $puzzle['id'] . ' - ' . $puzzle['difficulty_level'] . ' - INVÁLIDO</div>';
                    echo '<div class="puzzle-info">   📋 ' . $puzzle['puzzle_string'] . '</div>';
                }
            }
            
            echo '</div>';
            
            echo '<h3>📊 Resumen</h3>';
            echo '<p class="valid"><strong>✅ Puzzles válidos:</strong> ' . $validCount . '</p>';
            echo '<p class="invalid"><strong>❌ Puzzles inválidos:</strong> ' . $invalidCount . '</p>';
            
            if ($invalidCount > 0) {
                echo '<h3>🛠️ Acciones disponibles</h3>';
                echo '<p class="warning">Se encontraron ' . $invalidCount . ' puzzles inválidos. ¿Qué deseas hacer?</p>';
                
                echo '<form method="post" style="margin: 10px 0;">';
                echo '<input type="hidden" name="invalid_puzzles" value="' . htmlspecialchars(json_encode($invalidPuzzles)) . '">';
                echo '<button type="submit" name="action" value="mark_invalid" class="warning">🏷️ Marcar como inválidos (recomendado)</button>';
                echo '<button type="submit" name="action" value="delete_invalid" class="danger" onclick="return confirm(\'¿Estás seguro de eliminar permanentemente estos puzzles?\')">🗑️ Eliminar permanentemente</button>';
                echo '</form>';
                
                echo '<p class="info"><strong>Recomendación:</strong> Es mejor marcar como inválidos en lugar de eliminar, así mantienes un registro para análisis.</p>';
            } else {
                echo '<p class="valid">🎉 ¡Todos los puzzles son válidos! No se requiere ninguna acción.</p>';
            }
            
        } catch (PDOException $e) {
            echo '<p class="invalid">❌ Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p class="info">💡 Verifica que XAMPP esté ejecutándose y las credenciales sean correctas</p>';
        }
        ?>
        
        <hr>
        <p class="info"><strong>💡 Instrucciones:</strong></p>
        <ul>
            <li>Este validador revisa todos los puzzles en la base de datos</li>
            <li>Identifica puzzles con números duplicados en filas, columnas o cajas 3x3</li>
            <li>Puedes marcar los inválidos para que el sistema los evite</li>
            <li>O eliminarlos permanentemente si prefieres</li>
        </ul>
    </div>
</body>
</html>
