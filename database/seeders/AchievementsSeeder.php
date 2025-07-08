<?php

/**
 * 🏆 SEEDER DE LOGROS PARA SUDOKU
 * Popula la tabla achievements con logros predefinidos
 */

// Configuración de base de datos
$host = '127.0.0.1';
$dbName = 'sudoku';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔌 Conectado a la base de datos\n";
    
    // Limpiar logros existentes
    echo "🧹 Limpiando logros existentes...\n";
    $pdo->exec("DELETE FROM achievements");
    $pdo->exec("ALTER TABLE achievements AUTO_INCREMENT = 1");
    
    // Definir logros
    $achievements = [
        // 🎯 LOGROS DE COMPLETACIÓN
        [
            'name' => 'Primer Paso',
            'description' => 'Completa tu primer puzzle de Sudoku',
            'icon' => '🎯',
            'category' => 'completion',
            'condition_type' => 'games_completed',
            'condition_value' => 1,
            'points' => 10
        ],
        [
            'name' => 'Aficionado',
            'description' => 'Completa 10 puzzles de Sudoku',
            'icon' => '📚',
            'category' => 'completion',
            'condition_type' => 'games_completed',
            'condition_value' => 10,
            'points' => 25
        ],
        [
            'name' => 'Experto',
            'description' => 'Completa 50 puzzles de Sudoku',
            'icon' => '🎓',
            'category' => 'completion',
            'condition_type' => 'games_completed',
            'condition_value' => 50,
            'points' => 100
        ],
        [
            'name' => 'Maestro Sudoku',
            'description' => 'Completa 100 puzzles de Sudoku',
            'icon' => '👑',
            'category' => 'completion',
            'condition_type' => 'games_completed',
            'condition_value' => 100,
            'points' => 250
        ],
        
        // ⚡ LOGROS DE VELOCIDAD
        [
            'name' => 'Rápido como el Rayo',
            'description' => 'Completa un puzzle en menos de 3 minutos',
            'icon' => '⚡',
            'category' => 'speed',
            'condition_type' => 'completion_time_under',
            'condition_value' => 180, // 3 minutos
            'points' => 30
        ],
        [
            'name' => 'Solucionador Eficiente',
            'description' => 'Completa un puzzle con menos de 100 movimientos',
            'icon' => '🎯',
            'category' => 'efficiency',
            'condition_type' => 'moves_under',
            'condition_value' => 100,
            'points' => 25
        ],
        [
            'name' => 'Mente Estratégica',
            'description' => 'Completa un puzzle sin usar pistas',
            'icon' => '🧠',
            'category' => 'strategy',
            'condition_type' => 'no_hints',
            'condition_value' => 0,
            'points' => 40
        ],
        [
            'name' => 'Juego Perfecto',
            'description' => 'Completa un puzzle sin errores',
            'icon' => '💎',
            'category' => 'perfection',
            'condition_type' => 'no_mistakes',
            'condition_value' => 0,
            'points' => 50
        ],
        
        // 🏔️ LOGROS DE DIFICULTAD
        [
            'name' => 'Principiante',
            'description' => 'Completa tu primer puzzle fácil',
            'icon' => '🌱',
            'category' => 'difficulty',
            'condition_type' => 'difficulty_completed',
            'condition_value' => 1, // easy
            'points' => 5
        ],
        [
            'name' => 'Intermedio',
            'description' => 'Completa tu primer puzzle medio',
            'icon' => '🌿',
            'category' => 'difficulty',
            'condition_type' => 'difficulty_completed',
            'condition_value' => 2, // medium
            'points' => 15
        ],
        [
            'name' => 'Avanzado',
            'description' => 'Completa tu primer puzzle difícil',
            'icon' => '🌳',
            'category' => 'difficulty',
            'condition_type' => 'difficulty_completed',
            'condition_value' => 3, // hard
            'points' => 30
        ],
        [
            'name' => 'Experto Extremo',
            'description' => 'Completa tu primer puzzle experto',
            'icon' => '🏔️',
            'category' => 'difficulty',
            'condition_type' => 'difficulty_completed',
            'condition_value' => 4, // expert
            'points' => 75
        ],
        
        // 🔥 LOGROS ESPECIALES
        [
            'name' => 'Racha Caliente',
            'description' => 'Completa 5 puzzles consecutivos',
            'icon' => '🔥',
            'category' => 'streak',
            'condition_type' => 'consecutive_wins',
            'condition_value' => 5,
            'points' => 35
        ],
        [
            'name' => 'Dedicación Diaria',
            'description' => 'Juega durante 7 días consecutivos',
            'icon' => '📅',
            'category' => 'dedication',
            'condition_type' => 'daily_streak',
            'condition_value' => 7,
            'points' => 60
        ]
    ];
    
    // Insertar logros
    echo "🏆 Insertando " . count($achievements) . " logros...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO achievements (
            name, description, icon, category, 
            condition_type, condition_value, points,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    foreach ($achievements as $achievement) {
        $stmt->execute([
            $achievement['name'],
            $achievement['description'],
            $achievement['icon'],
            $achievement['category'],
            $achievement['condition_type'],
            $achievement['condition_value'],
            $achievement['points']
        ]);
        echo "  ✅ " . $achievement['icon'] . " " . $achievement['name'] . "\n";
    }
    
    echo "\n🎉 ¡Logros insertados exitosamente!\n";
    echo "📊 Total de logros: " . count($achievements) . "\n";
    
    // Mostrar resumen por categoría
    $categories = array_count_values(array_column($achievements, 'category'));
    echo "\n📈 Resumen por categoría:\n";
    foreach ($categories as $category => $count) {
        echo "  • $category: $count logros\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🚀 Seeder completado. ¡Ahora los logros deberían aparecer correctamente en el juego!\n";
?>
