<?php
// Script temporal para ejecutar setup_analytics.php
echo "📊 Ejecutando configuración de analíticas...\n";

try {
    // Incluir y ejecutar el setup
    include 'public/setup_analytics.php';
    
    echo "\n✅ Setup completado exitosamente!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error en setup: " . $e->getMessage() . "\n";
}
?>
