# 🛠️ Herramientas de Desarrollo - Sudoku Minimalista

Este directorio contiene todas las herramientas de desarrollo y archivos que NO deben estar en producción.

## 📁 Estructura

### `/debug/` - Herramientas de Debug
- `debug_achievements.php` - Debug del sistema de logros
- `debug_analytics_complete.html` - Debug de analíticas completas
- `debug_api.php` - Debug general de APIs

### `/tests/` - Archivos de Prueba
- `test_api.php` - Pruebas de APIs PHP
- `test_api_basico.html` - Pruebas básicas en HTML
- `test_quick_fix.html` - Pruebas de corrección rápida
- `test_stats_routes.html` - Pruebas de rutas de estadísticas

### `/setup/` - Scripts de Configuración
- `setup_achievements.php` - Configurar sistema de logros
- `setup_analytics.php` - Configurar analíticas
- `fix_analytics_complete.html` - Correcciones de analíticas

### `/` - Herramientas Generales
- `generate_puzzles.php` - Generador de puzzles
- `import_puzzles.php` - Importador de puzzles
- `stats_puzzles.php` - Estadísticas de puzzles
- `validate_puzzles.php` - Validador de puzzles
- `generate_test_data.php` - Generador de datos de prueba
- `favicon-generator.html` - Generador de favicons

## ⚠️ IMPORTANTE

**NUNCA mover estos archivos de vuelta a `/public/`**

Estos archivos contienen:
- Información sensible de desarrollo
- Herramientas que podrían ser explotadas
- Scripts de configuración que solo deben ejecutarse una vez
- Datos de debug que no deben ser públicos

## 🔧 Uso en Desarrollo

Para usar estas herramientas durante el desarrollo:

1. **Scripts PHP**: Ejecutar desde línea de comandos
   ```bash
   php dev-tools/generate_puzzles.php
   php dev-tools/validate_puzzles.php
   ```

2. **Archivos HTML**: Abrir directamente en navegador
   ```
   file:///ruta/completa/dev-tools/tests/test_api_basico.html
   ```

3. **Scripts de Setup**: Solo ejecutar UNA vez
   ```bash
   php dev-tools/setup/setup_achievements.php
   ```

## 🚀 Despliegue a Producción

Antes de subir a producción, verificar que:
- [ ] `/public/` solo contiene archivos necesarios
- [ ] Ningún archivo de `/dev-tools/` está en `/public/`
- [ ] Variables de entorno están configuradas para producción
- [ ] Logs de debug están desactivados

---

**📝 Nota**: Si necesitas acceso temporal a estas herramientas en producción, créalas en un subdirectorio protegido por contraseña, nunca en `/public/`.
