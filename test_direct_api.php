<?php
echo "<h2>🧪 PRUEBA DIRECTA DE API</h2>";

echo "<h3>🔗 Enlaces de Prueba:</h3>";
$difficulties = ['easy', 'medium', 'hard', 'expert', 'master'];

foreach ($difficulties as $diff) {
    $url = "http://localhost/Sudoku/public/api/puzzle/new/$diff";
    echo "<p><a href='$url' target='_blank'>🎯 $diff</a> - $url</p>";
}

echo "<hr>";
echo "<p><a href='/Sudoku/public'>🔙 Volver al juego</a></p>";
?>