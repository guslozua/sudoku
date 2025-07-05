<?php
// Script para probar las APIs y poblar la base de datos si es necesario

echo "<h2>🔍 PRUEBA DE APIS Y BASE DE DATOS</h2>";

// Verificar conexión a la base de datos
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=sudoku', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Conexión a base de datos exitosa</p>";
    
    // Verificar si hay puzzles
    $stmt = $pdo->query("SELECT COUNT(*) FROM puzzles");
    $puzzleCount = $stmt->fetchColumn();
    
    echo "<p>📊 Puzzles en base de datos: <strong>$puzzleCount</strong></p>";
    
    if ($puzzleCount === 0 || $puzzleCount < 10) {
        echo "<p>⚠️ Pocos puzzles encontrados. Agregando puzzles de ejemplo...</p>";
        
        // Puzzles de ejemplo para cada dificultad
        $samplePuzzles = [
            [
                'puzzle_string' => '530070000600195000098000060800060003400803001700020006060000280000419005000080079',
                'solution_string' => '534678912672195348198342567859761423426853791713924856961537284287419635345286179',
                'difficulty_level' => 'easy',
                'clues_count' => 36
            ],
            [
                'puzzle_string' => '400000805030000000000700000020000060000080400000010000000603070500200000104000000',
                'solution_string' => '417369825632158947958724316825437169791586432346912758289643571573291684164875293',
                'difficulty_level' => 'medium',
                'clues_count' => 28
            ],
            [
                'puzzle_string' => '200000006140600000000000200008020000900000001000060400002000000000007053600000008',
                'solution_string' => '275918346143652789689734512758123694924576831316869427437281965891347253562495178',
                'difficulty_level' => 'hard',
                'clues_count' => 22
            ],
            [
                'puzzle_string' => '000600000700020000000000001000000050006000800080000000400000000000090006000008000',
                'solution_string' => '581647329793825614426139871142368957356714982987251435615472193834593267269186543',
                'difficulty_level' => 'expert',
                'clues_count' => 18
            ],
            [
                'puzzle_string' => '800000000003600000070090200050007000000045700000100030001000068008500010090000400',
                'solution_string' => '812753649943682175675491283154237896236845791789169532521374968468529317397816425',
                'difficulty_level' => 'master',
                'clues_count' => 25
            ]
        ];
        
        // Insertar múltiples puzzles de cada dificultad
        foreach ($samplePuzzles as $basePuzzle) {
            for ($i = 0; $i < 3; $i++) { // 3 puzzles de cada dificultad
                $stmt = $pdo->prepare("INSERT INTO puzzles (puzzle_string, solution_string, difficulty_level, clues_count, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $basePuzzle['puzzle_string'],
                    $basePuzzle['solution_string'],
                    $basePuzzle['difficulty_level'],
                    $basePuzzle['clues_count']
                ]);
            }
        }
        
        echo "<p>✅ Se agregaron 15 puzzles de ejemplo (3 por dificultad)</p>";
    }
    
    // Mostrar estadísticas por dificultad
    echo "<h3>📊 Distribución de Puzzles:</h3>";
    $stmt = $pdo->query("SELECT difficulty_level, COUNT(*) as count FROM puzzles GROUP BY difficulty_level");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<p>• {$row['difficulty_level']}: <strong>{$row['count']} puzzles</strong></p>";
    }
    
    // Probar la nueva API
    echo "<h3>🧪 Prueba de API Nueva:</h3>";
    $testUrl = "http://localhost/Sudoku/public/api/puzzle/new/easy";
    echo "<p>Probando: <a href='$testUrl' target='_blank'>$testUrl</a></p>";
    
    // Usar cURL para probar la API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>Código de respuesta: <strong>$httpCode</strong></p>";
    
    if ($httpCode === 200) {
        echo "<p>✅ API funcionando correctamente</p>";
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "<p>🎮 Game ID generado: <strong>{$data['game_id']}</strong></p>";
            echo "<p>🧩 Puzzle ID: <strong>{$data['puzzle']['id']}</strong></p>";
            echo "<p>🎯 Dificultad: <strong>{$data['puzzle']['difficulty_level']}</strong></p>";
            echo "<p>📊 Pistas: <strong>{$data['puzzle']['clues_count']}</strong></p>";
            echo "<p style='font-family: monospace; font-size: 12px; background: #f0f0f0; padding: 10px;'>Puzzle String: {$data['puzzle']['puzzle_string']}</p>";
        } else {
            echo "<p>❌ Error en respuesta API:</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    } else {
        echo "<p>❌ Error en API (Código $httpCode):</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
    
    // Probar diferentes dificultades
    echo "<h3>🎯 Prueba de Todas las Dificultades:</h3>";
    $difficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
    foreach ($difficulties as $diff) {
        $testUrl = "http://localhost/Sudoku/public/api/puzzle/new/$diff";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && $data['success']) {
                echo "<p>✅ <strong>$diff</strong>: Game ID {$data['game_id']}, Puzzle ID {$data['puzzle']['id']}</p>";
            } else {
                echo "<p>❌ <strong>$diff</strong>: Error en respuesta</p>";
            }
        } else {
            echo "<p>❌ <strong>$diff</strong>: HTTP $httpCode</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='/Sudoku/public'>🔙 Volver al juego</a></p>";
?>