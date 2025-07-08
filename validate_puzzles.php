<?php
/**
 * Script para validar y limpiar puzzles inválidos de la base de datos
 */

require_once 'C:/xampp2/htdocs/Sudoku/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "🔍 VALIDANDO PUZZLES EN LA BASE DE DATOS\n";
echo "==========================================\n\n";

// Función para validar un puzzle
function validatePuzzleBoard($puzzleString) {
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

try {
    // Obtener todos los puzzles
    $puzzles = DB::table('puzzles')->get();
    
    echo "📊 Total de puzzles en la base de datos: " . $puzzles->count() . "\n\n";
    
    $validCount = 0;
    $invalidCount = 0;
    $invalidPuzzles = [];
    
    foreach ($puzzles as $puzzle) {
        $isValid = validatePuzzleBoard($puzzle->puzzle_string);
        
        if ($isValid) {
            $validCount++;
            echo "✅ Puzzle ID {$puzzle->id} - {$puzzle->difficulty_level} - VÁLIDO\n";
        } else {
            $invalidCount++;
            $invalidPuzzles[] = $puzzle->id;
            echo "❌ Puzzle ID {$puzzle->id} - {$puzzle->difficulty_level} - INVÁLIDO\n";
        }
    }
    
    echo "\n==========================================\n";
    echo "📊 RESUMEN DE VALIDACIÓN:\n";
    echo "✅ Puzzles válidos: $validCount\n";
    echo "❌ Puzzles inválidos: $invalidCount\n";
    
    if ($invalidCount > 0) {
        echo "\n🗑️ PUZZLES INVÁLIDOS DETECTADOS:\n";
        foreach ($invalidPuzzles as $puzzleId) {
            echo "   - Puzzle ID: $puzzleId\n";
        }
        
        echo "\n¿Deseas marcar estos puzzles como inválidos? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) === 'y' || trim($line) === 'Y') {
            // Agregar columna is_valid si no existe
            try {
                DB::statement('ALTER TABLE puzzles ADD COLUMN is_valid BOOLEAN DEFAULT TRUE');
                echo "✅ Columna 'is_valid' agregada a la tabla puzzles\n";
            } catch (Exception $e) {
                echo "ℹ️ Columna 'is_valid' ya existe\n";
            }
            
            // Marcar puzzles inválidos
            $updated = DB::table('puzzles')
                ->whereIn('id', $invalidPuzzles)
                ->update(['is_valid' => false]);
            
            echo "✅ $updated puzzles marcados como inválidos\n";
            echo "💡 El sistema ahora evitará entregar estos puzzles a los usuarios\n";
        } else {
            echo "ℹ️ No se realizaron cambios en la base de datos\n";
        }
    } else {
        echo "🎉 ¡Todos los puzzles son válidos!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ VALIDACIÓN COMPLETADA\n";
?>
