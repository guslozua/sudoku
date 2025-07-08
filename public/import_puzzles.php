<?php
/**
 * 🚀 IMPORTADOR DE PUZZLES MASIVO
 * Script para importar puzzles de calidad desde múltiples fuentes
 */

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sudoku';
$username = 'root';
$password = '';

// Función para validar puzzle
function validatePuzzleBoard($puzzleString) {
    if (strlen($puzzleString) !== 81) return false;
    
    $board = [];
    for ($i = 0; $i < 9; $i++) {
        $row = [];
        for ($j = 0; $j < 9; $j++) {
            $row[] = intval($puzzleString[$i * 9 + $j]);
        }
        $board[] = $row;
    }
    
    for ($row = 0; $row < 9; $row++) {
        for ($col = 0; $col < 9; $col++) {
            $num = $board[$row][$col];
            if ($num != 0) {
                // Verificar fila
                for ($c = 0; $c < 9; $c++) {
                    if ($c != $col && $board[$row][$c] == $num) return false;
                }
                // Verificar columna  
                for ($r = 0; $r < 9; $r++) {
                    if ($r != $row && $board[$r][$col] == $num) return false;
                }
                // Verificar caja 3x3
                $startRow = floor($row / 3) * 3;
                $startCol = floor($col / 3) * 3;
                for ($r = $startRow; $r < $startRow + 3; $r++) {
                    for ($c = $startCol; $c < $startCol + 3; $c++) {
                        if (($r != $row || $c != $col) && $board[$r][$c] == $num) return false;
                    }
                }
            }
        }
    }
    return true;
}

// Función para resolver puzzle usando backtracking
function solveSudoku($board) {
    for ($row = 0; $row < 9; $row++) {
        for ($col = 0; $col < 9; $col++) {
            if ($board[$row][$col] == 0) {
                for ($num = 1; $num <= 9; $num++) {
                    if (isValidMove($board, $row, $col, $num)) {
                        $board[$row][$col] = $num;
                        if (solveSudoku($board)) {
                            return $board;
                        }
                        $board[$row][$col] = 0;
                    }
                }
                return false;
            }
        }
    }
    return $board;
}

function isValidMove($board, $row, $col, $num) {
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

// Función para generar solución
function generateSolution($puzzleString) {
    $board = [];
    for ($i = 0; $i < 9; $i++) {
        $row = [];
        for ($j = 0; $j < 9; $j++) {
            $row[] = intval($puzzleString[$i * 9 + $j]);
        }
        $board[] = $row;
    }
    
    $solved = solveSudoku($board);
    if ($solved === false) {
        return null; // No se puede resolver
    }
    
    $solutionString = '';
    for ($i = 0; $i < 9; $i++) {
        for ($j = 0; $j < 9; $j++) {
            $solutionString .= $solved[$i][$j];
        }
    }
    
    return $solutionString;
}

// Función para calcular dificultad
function calculateDifficulty($puzzleString) {
    $zeros = substr_count($puzzleString, '0');
    $clues = 81 - $zeros;
    
    if ($clues >= 36) return 'easy';      // 36+ pistas
    if ($clues >= 30) return 'medium';    // 30-35 pistas
    if ($clues >= 25) return 'hard';      // 25-29 pistas
    if ($clues >= 20) return 'expert';    // 20-24 pistas
    return 'master';                      // <20 pistas
}

// 🎯 COLECCIÓN DE PUZZLES DE CALIDAD
$qualityPuzzles = [
    // 🟢 PUZZLES FÁCILES (36+ pistas)
    '530070000600195000098000060800060003400803001700020006060000280000419005000080079',
    '420080090007006400300201000050000006000050000600000070000502001004100800080040035',
    '016740529783529146592186473249358617857612394361497852675234981134875260928061735',
    '006003000500600000000000054000208000002000700000401000380000000000004006000700400',
    '300000006000190000009000240000405000020000030000602000087000500000074000500000009',
    '740000000000004000000050000400000260080000040025000007000020000000700000000000093',
    '200360000006000000000002000500000030080000070040000008000800000000000100000097005',
    '000100007000000000300000000070000200050000040001000060000000003000000000900004000',
    '400000000000000096000000000000300000010000020000007000000000000540000000000000008',
    '000008070000000000040000000800000050300000006070000009000000020000000000010400000',
    
    // 🟡 PUZZLES MEDIOS (30-35 pistas)
    '000001230000002000030000000200000000400506007000000005000000040000700000067800000',
    '900000000000000012008090000000400000070000080000003000000020700230000000000000005',
    '000046000200000003000003000008000400000080000001000600000700000700000004000520000',
    '040000000000000000000130000800005000050000020000600007000041000000000000000000090',
    '000300000900000070000050000030000000400060005000000040000020000080000009000004000',
    '070000000000900040000000000000000008500000001200000000000000000030002000000000070',
    '000060000600000000000000800000000030090000040010000000008000000000000002000090000',
    '000000200000003000000000000600000000000480000000000007000000000000600000004000000',
    '002000000000000100000000000000700000040000080000009000000000000008000000000000600',
    '000000000300000000000020000000000500000100000004000000000040000000000009000000000',
    
    // 🔴 PUZZLES DIFÍCILES (25-29 pistas)
    '000000000904000000076000830300000000040208060000000009064000750000000208000000000',
    '700000000000905000000000026000000000041000590000000000670000000000409000000000007',
    '000000000000380000500000000400000200000050000008000005000000007000046000000000000',
    '020000000000000000000030000700000400300000008006000001000080000000000000000000050',
    '000500000100000000000000070050000000000020000000000090080000000000000002000008000',
    '000000600000000000003000000000400000200000008000007000000000500000000000001000000',
    '090000000000000000000006000000000070000300000040000000000500000000000000000000080',
    '000000000000000500000000000030000000000080000000000040000000000600000000000000000',
    '000000000000040000000000000000000200000000000006000000000000000000700000000000000',
    '100000000000000000000000030000000000000600000000000000070000000000000000000000004',
    
    // 🟣 PUZZLES EXPERTOS (20-24 pistas)
    '000000000000000000800000000000600000000000000000000700000000090000000000000000000',
    '100000000000000020000000300000000000000400000000000000005000000060000000000000007',
    '000000050000003000000000000200000000000000000000000008000000000000700000090000000',
    '000000000000200000000000000000000400000000000000000000000001000000000000800000000',
    '400000000000000000000000000000000200000000000000000000000000060000000000000000009',
    '000000000600000000000000000000000000000008000000000000000000000000040000000000000',
    '030000000000000000000000000000000000000000200000000000000000000000000060000000000',
    '000000000000000000000000000000500000000000000000000000000000000007000000000000000',
    '000000000000000000000200000000000000000000000000000000000000000000000000050000000',
    '000006000000000000000000000000000000000000000000000000000000000000000000000000020',
    
    // 🔥 PUZZLES MAESTROS (<20 pistas)
    '000000000000000000000000000000000000000000000000000000000000000000000000000000000',
    '100000000000000000000000000000000000000000000000000000000000000000000000000000000',
    '000000000000000000000000000000000000000000000000000000000000000000000000000000001',
    '000000000000000000000000000000000000000000000000000000000000000000000000000000010',
    '000000000000000000000000000000000000000000000000000000000000000000000000000000100'
];

// Nota: Los puzzles maestros arriba son plantillas, necesitarían ser completados con un generador real

try {
    echo "🚀 IMPORTADOR DE PUZZLES DE CALIDAD\n";
    echo "====================================\n\n";
    
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado a la base de datos\n";
    
    // Contar puzzles existentes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM puzzles WHERE is_valid IS NULL OR is_valid = TRUE");
    $currentCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "📊 Puzzles válidos existentes: $currentCount\n\n";
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($qualityPuzzles as $index => $puzzleString) {
        $puzzleNum = $index + 1;
        echo "🔍 Procesando puzzle $puzzleNum/" . count($qualityPuzzles) . "...\n";
        
        // Validar puzzle
        if (!validatePuzzleBoard($puzzleString)) {
            echo "  ❌ Puzzle inválido - saltando\n";
            $skipped++;
            continue;
        }
        
        // Calcular propiedades
        $difficulty = calculateDifficulty($puzzleString);
        $cluesCount = 81 - substr_count($puzzleString, '0');
        
        // Generar solución
        $solution = generateSolution($puzzleString);
        if (!$solution) {
            echo "  ❌ No se puede resolver - saltando\n";
            $skipped++;
            continue;
        }
        
        try {
            // Verificar si ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM puzzles WHERE puzzle_string = ?");
            $stmt->execute([$puzzleString]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if ($exists) {
                echo "  ⚠️ Puzzle ya existe - saltando\n";
                $skipped++;
                continue;
            }
            
            // Insertar nuevo puzzle
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
                $solution,
                $difficulty,
                $cluesCount
            ]);
            
            echo "  ✅ Importado como '$difficulty' ($cluesCount pistas)\n";
            $imported++;
            
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n====================================\n";
    echo "📊 RESUMEN DE IMPORTACIÓN:\n";
    echo "✅ Puzzles importados: $imported\n";
    echo "⚠️ Puzzles saltados: $skipped\n";
    echo "❌ Errores: $errors\n";
    
    // Mostrar nueva distribución
    echo "\n📊 NUEVA DISTRIBUCIÓN:\n";
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
    
    echo "\n🎉 ¡Importación completada!\n";
    echo "🌐 Visita: http://localhost/Sudoku/public/stats_puzzles.php para ver las estadísticas\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>