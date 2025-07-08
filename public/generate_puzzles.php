<?php
/**
 * 🎲 GENERADOR DE PUZZLES SUDOKU AVANZADO
 * Crea puzzles únicos y válidos usando algoritmos de generación
 */

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sudoku';
$username = 'root';
$password = '';

class SudokuGenerator {
    private $board;
    private $solution;
    
    public function __construct() {
        $this->board = array_fill(0, 9, array_fill(0, 9, 0));
        $this->solution = array_fill(0, 9, array_fill(0, 9, 0));
    }
    
    // Generar una solución completa válida
    public function generateComplete() {
        $this->board = array_fill(0, 9, array_fill(0, 9, 0));
        $this->fillBoard();
        $this->solution = $this->board;
        return $this->board;
    }
    
    // Llenar el tablero usando backtracking
    private function fillBoard() {
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                if ($this->board[$row][$col] == 0) {
                    $numbers = range(1, 9);
                    shuffle($numbers); // Aleatorizar para variedad
                    
                    foreach ($numbers as $num) {
                        if ($this->isValidMove($row, $col, $num)) {
                            $this->board[$row][$col] = $num;
                            if ($this->fillBoard()) {
                                return true;
                            }
                            $this->board[$row][$col] = 0;
                        }
                    }
                    return false;
                }
            }
        }
        return true;
    }
    
    // Verificar si un movimiento es válido
    private function isValidMove($row, $col, $num) {
        // Verificar fila
        for ($c = 0; $c < 9; $c++) {
            if ($this->board[$row][$c] == $num) return false;
        }
        
        // Verificar columna
        for ($r = 0; $r < 9; $r++) {
            if ($this->board[$r][$col] == $num) return false;
        }
        
        // Verificar caja 3x3
        $startRow = floor($row / 3) * 3;
        $startCol = floor($col / 3) * 3;
        for ($r = $startRow; $r < $startRow + 3; $r++) {
            for ($c = $startCol; $c < $startCol + 3; $c++) {
                if ($this->board[$r][$c] == $num) return false;
            }
        }
        
        return true;
    }
    
    // Crear puzzle removiendo números según dificultad
    public function createPuzzle($difficulty) {
        $this->generateComplete();
        
        // Definir cuántos números remover según dificultad
        $removeCount = [
            'easy' => rand(40, 45),      // Dejar 36-41 pistas
            'medium' => rand(46, 51),    // Dejar 30-35 pistas
            'hard' => rand(52, 56),      // Dejar 25-29 pistas
            'expert' => rand(57, 61),    // Dejar 20-24 pistas
            'master' => rand(62, 66)     // Dejar 15-19 pistas
        ];
        
        $toRemove = $removeCount[$difficulty] ?? 50;
        
        // Crear lista de posiciones
        $positions = [];
        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $positions[] = [$r, $c];
            }
        }
        shuffle($positions);
        
        // Remover números manteniendo solución única
        $removed = 0;
        foreach ($positions as $pos) {
            if ($removed >= $toRemove) break;
            
            $row = $pos[0];
            $col = $pos[1];
            $backup = $this->board[$row][$col];
            $this->board[$row][$col] = 0;
            
            // Verificar que aún tenga solución única
            if ($this->hasUniqueSolution()) {
                $removed++;
            } else {
                $this->board[$row][$col] = $backup; // Restaurar
            }
        }
        
        return $this->board;
    }
    
    // Verificar si el puzzle tiene solución única (simplificado)
    private function hasUniqueSolution() {
        $copy = $this->board;
        $solutions = 0;
        $this->countSolutions($copy, $solutions);
        return $solutions == 1;
    }
    
    // Contar número de soluciones
    private function countSolutions(&$board, &$count) {
        if ($count > 1) return; // Optimización
        
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                if ($board[$row][$col] == 0) {
                    for ($num = 1; $num <= 9; $num++) {
                        if ($this->isValidInBoard($board, $row, $col, $num)) {
                            $board[$row][$col] = $num;
                            $this->countSolutions($board, $count);
                            $board[$row][$col] = 0;
                        }
                    }
                    return;
                }
            }
        }
        $count++;
    }
    
    // Verificar validez en un tablero específico
    private function isValidInBoard($board, $row, $col, $num) {
        // Verificar fila
        for ($c = 0; $c < 9; $c++) {
            if ($board[$row][$c] == $num) return false;
        }
        
        // Verificar columna
        for ($r = 0; $r < 9; $r++) {
            if ($board[$r][$col] == $num) return false;
        }
        
        // Verificar caja 3x3
        $startRow = floor($row / 3) * 3;
        $startCol = floor($col / 3) * 3;
        for ($r = $startRow; $r < $startRow + 3; $r++) {
            for ($c = $startCol; $c < $startCol + 3; $c++) {
                if ($board[$r][$c] == $num) return false;
            }
        }
        
        return true;
    }
    
    // Convertir tablero a string
    public function boardToString($board = null) {
        $board = $board ?? $this->board;
        $string = '';
        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $string .= $board[$r][$c];
            }
        }
        return $string;
    }
    
    // Obtener la solución
    public function getSolution() {
        return $this->boardToString($this->solution);
    }
}

try {
    echo "🎲 GENERADOR DE PUZZLES SUDOKU\n";
    echo "==============================\n\n";
    
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado a la base de datos\n";
    
    // Configuración de generación
    $puzzlesToGenerate = [
        'easy' => 15,
        'medium' => 12,
        'hard' => 10,
        'expert' => 8,
        'master' => 5
    ];
    
    $generator = new SudokuGenerator();
    $totalGenerated = 0;
    
    foreach ($puzzlesToGenerate as $difficulty => $count) {
        echo "\n🎯 Generando $count puzzles de dificultad '$difficulty'...\n";
        
        for ($i = 1; $i <= $count; $i++) {
            echo "  Generando puzzle $i/$count...";
            
            try {
                // Generar puzzle
                $puzzle = $generator->createPuzzle($difficulty);
                $puzzleString = $generator->boardToString($puzzle);
                $solutionString = $generator->getSolution();
                $cluesCount = 81 - substr_count($puzzleString, '0');
                
                // Verificar que no existe
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM puzzles WHERE puzzle_string = ?");
                $stmt->execute([$puzzleString]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if ($exists) {
                    echo " ⚠️ Ya existe\n";
                    continue;
                }
                
                // Insertar en la base de datos
                $stmt = $pdo->prepare("
                    INSERT INTO puzzles (
                        puzzle_string, 
                        solution_string, 
                        difficulty_level, 
                        clues_count,
                        is_valid,
                        created_at, 
                        updated_at
                    ) VALUES (?, ?, ?, ?, TRUE, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $puzzleString,
                    $solutionString,
                    $difficulty,
                    $cluesCount
                ]);
                
                echo " ✅ Generado ($cluesCount pistas)\n";
                $totalGenerated++;
                
            } catch (Exception $e) {
                echo " ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n==============================\n";
    echo "📊 RESUMEN DE GENERACIÓN:\n";
    echo "✅ Total generados: $totalGenerated puzzles\n";
    
    // Mostrar distribución final
    echo "\n📊 DISTRIBUCIÓN FINAL:\n";
    $stmt = $pdo->query("
        SELECT 
            difficulty_level,
            COUNT(*) as total,
            COUNT(CASE WHEN is_valid IS NULL OR is_valid = TRUE THEN 1 END) as validos
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
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $status = $row['validos'] >= 10 ? '✅' : ($row['validos'] >= 5 ? '⚠️' : '❌');
        echo "$status {$row['difficulty_level']}: {$row['validos']} válidos\n";
    }
    
    echo "\n🎉 ¡Generación completada!\n";
    echo "🌐 Visita: http://localhost/Sudoku/public/stats_puzzles.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>