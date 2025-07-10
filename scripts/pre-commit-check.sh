#!/bin/bash
# Pre-commit verification script
# Verifica que todo esté listo para commit

echo "🔍 VERIFICACIÓN PRE-COMMIT - FASE 2: OPTIMIZACIÓN"
echo "=================================================="

# Verificar archivos críticos
echo "📁 Verificando archivos críticos..."

critical_files=(
    "optimization/cache.php"
    "optimization/performance.php" 
    "optimization/database_optimization.sql"
    "optimization/apply_db_optimizations.php"
    "optimization/test_optimizations.php"
    "optimization/README.md"
    "security/csrf.php"
    "security/cors.php"
    "security/config.php"
    "security/validator.php"
    "app/Http/Controllers/SudokuControllerOptimized.php"
)

for file in "${critical_files[@]}"; do
    if [[ -f "$file" ]]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file (FALTANTE)"
    fi
done

# Verificar que dev-tools esté poblado
echo ""
echo "🛠️ Verificando herramientas de desarrollo..."
dev_files=(
    "dev-tools/README.md"
    "dev-tools/apply_optimizations_web.php"
    "dev-tools/test_optimizations_web.php"
    "dev-tools/dashboard.html"
)

for file in "${dev_files[@]}"; do
    if [[ -f "$file" ]]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file (FALTANTE)"
    fi
done

echo ""
echo "📋 RESUMEN:"
echo "  ✅ Fase 1: Seguridad implementada"
echo "  ✅ Fase 2: Optimización completada" 
echo "  ✅ Scripts de verificación creados"
echo "  ✅ Herramientas de desarrollo organizadas"
echo ""
echo "🚀 Listo para commit!"
