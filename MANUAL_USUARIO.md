# 📱 MANUAL DE USUARIO - SUDOKU MINIMALISTA

## 🎯 Introducción

¡Bienvenido a **Sudoku Minimalista**! Una aplicación web moderna y completa para jugar Sudoku con funcionalidades avanzadas como analíticas, auto-guardado, sistema de pistas y mucho más.

---

## 🚀 Instalación y Configuración

### **Requisitos Previos**
- XAMPP instalado y funcionando
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Navegador web moderno (Chrome, Firefox, Edge, Safari)

### **Instalación Rápida**

1. **Ejecutar script de configuración**:
   ```batch
   # En Windows
   setup_complete.bat
   
   # En Linux/Mac
   ./setup_complete.sh
   ```

2. **Acceder a la aplicación**:
   - Abrir navegador en: `http://localhost/Sudoku`

### **Instalación Manual**

Si prefieres instalar paso a paso:

1. **Importar puzzles**:
   ```bash
   php public/import_puzzles.php
   ```

2. **Generar puzzles adicionales**:
   ```bash
   php public/generate_puzzles.php
   ```

3. **Verificar estadísticas**:
   - Visitar: `http://localhost/Sudoku/public/stats_puzzles.php`

---

## 🎮 Cómo Jugar

### **Controles Básicos**

#### **🖱️ Mouse/Touch**
- **Seleccionar celda**: Clic en cualquier celda editable
- **Colocar número**: Clic en botón numérico (1-9)
- **Borrar número**: Clic en botón "🗑️ Borrar"
- **Cambiar dificultad**: Selector en la esquina superior
- **Nuevo puzzle**: Botón "Nuevo"

#### **⌨️ Teclado**
- **Números 1-9**: Colocar número en celda seleccionada
- **0, Backspace, Delete**: Borrar número
- **Flechas (↑↓←→)**: Navegar entre celdas
- **Tab**: Siguiente celda editable

### **🎯 Objetivo del Juego**

Completa la cuadrícula 9×9 para que:
- Cada **fila** contenga los números 1-9 sin repetir
- Cada **columna** contenga los números 1-9 sin repetir  
- Cada **caja 3×3** contenga los números 1-9 sin repetir

---

## 🎨 Funcionalidades Avanzadas

### **🎨 Panel de Números Inteligente**

El panel lateral muestra contadores en tiempo real:

- **🟢 Verde**: 3+ números disponibles
- **🟠 Naranja**: ≤2 números disponibles
- **⚫ Gris**: Número completo (0 disponibles, botón deshabilitado)

### **🔍 Sistema de Highlighting**

La aplicación resalta inteligentemente:

- **🔵 Azul**: Celda seleccionada
- **🟦 Azul claro**: Misma fila/columna de la celda seleccionada
- **🔷 Azul intenso**: Números iguales al seleccionado
- **🔴 Rojo**: Celdas con errores (números duplicados)
- **🟡 Amarillo**: Celda con pista (parpadea por 5 segundos)

### **💡 Sistema de Pistas Inteligente**

- **3 pistas por puzzle** (límite justo y desafiante)
- **Algoritmo inteligente**: Busca la mejor celda para dar pista
- **Explicación clara**: Muestra por qué ese número va ahí
- **Highlighting visual**: Resalta la celda de la pista

**Cómo usar pistas:**
1. Clic en "💡 Pista (X/3)"
2. Leer la explicación en el popup
3. La celda se resalta en amarillo
4. El highlighting desaparece automáticamente

### **💾 Auto-Guardado Inteligente**

La aplicación guarda automáticamente:

- **Cada 10 segundos** (solo si hay cambios)
- **Al completar puzzle**
- **Al cerrar navegador** (recuperación automática)

**Indicadores visuales:**
- **💾 Guardando...**: Guardado en progreso
- **✅ Guardado**: Guardado exitoso
- **❌ Error**: Error de guardado (reintentar automático)
- **💾 hace X min**: Último guardado exitoso

### **🔴 Detección de Errores en Tiempo Real**

- **Detección automática**: Identifica números duplicados
- **Feedback visual**: Celdas en rojo con animación
- **Sonido de alerta**: Notificación auditiva (si está habilitado)
- **Banner informativo**: Aparece arriba del tablero

### **🌙 Modo Oscuro/Claro**

- **Toggle suave**: Transición animada entre modos
- **Persistencia**: Recuerda tu preferencia
- **Adaptación completa**: Todos los elementos se adaptan
- **Mejor para los ojos**: Modo oscuro para uso nocturno

---

## 📊 Sistema de Analíticas

### **🎯 Acceso a Analíticas**

Clic en **"📊 Analytics"** en el header para ver:

#### **📊 Dashboard**
- **Métricas principales**: Puzzles completados, mejor tiempo, juegos perfectos
- **Gráfico de barras**: Rendimiento por dificultad
- **Actividad reciente**: Últimos 5 puzzles completados

#### **📈 Progreso**
- **Rachas**: Actual y mejor racha de días consecutivos
- **Gráfico temporal**: Progreso de últimos 30 días
- **Análisis de tendencias**: Mejora en el tiempo

#### **📉 Tendencias** (Próximamente)
- Análisis avanzado de patrones
- Recomendaciones personalizadas
- Comparación con otros jugadores

### **📈 Métricas Disponibles**

- **⏱️ Tiempo promedio/mejor** por dificultad
- **🎯 Tasa de éxito** (puzzles completados/intentados)
- **🎮 Movimientos promedio** por puzzle
- **💡 Uso de pistas** promedio
- **🔥 Racha actual y mejor** racha
- **🏆 Juegos perfectos** (sin errores ni pistas)

---

## 🎵 Sistema de Sonidos

### **🔊 Controles de Sonido**

En el panel lateral:
- **🔊/🔇 Toggle**: Activar/desactivar sonidos
- **Control de volumen**: Slider de 0-100%
- **Botones de prueba**: Escuchar cada tipo de sonido

### **🎶 Tipos de Sonidos**

- **🔢 Colocar número**: Nota musical suave
- **❌ Error**: Sonido disonante pero sutil
- **💡 Pista**: Campanita ascendente
- **🎉 Éxito**: Acorde de celebración
- **🏆 Logro**: Fanfarria épica
- **🗑️ Borrar**: Sonido sutil de borrado

---

## 🎛️ Configuración y Personalización

### **⚙️ Configuración del Juego**

#### **Dificultades Disponibles:**
- **🟢 Fácil**: 36+ pistas (ideal para principiantes)
- **🟡 Medio**: 30-35 pistas (equilibrio perfecto)
- **🟠 Difícil**: 25-29 pistas (desafío moderado)
- **🔴 Experto**: 20-24 pistas (muy desafiante)
- **🟣 Maestro**: <20 pistas (extremadamente difícil)

#### **Personalización Visual:**
- **Modo oscuro/claro**: Automático según preferencia
- **Highlighting inteligente**: Siempre activo
- **Animaciones**: Suaves y optimizadas

#### **Preferencias de Sonido:**
- **Estado**: Habilitado/deshabilitado
- **Volumen**: Ajustable de 0-100%
- **Tipos**: Selectivos por categoría

---

## 🔧 Administración y Mantenimiento

### **👥 Para Administradores**

#### **📊 Monitoreo de Puzzles**
- **Estadísticas**: `http://localhost/Sudoku/public/stats_puzzles.php`
- **Validador**: `http://localhost/Sudoku/public/validate_puzzles.php`

#### **➕ Agregar Más Puzzles**
```bash
# Importar puzzles de calidad
php public/import_puzzles.php

# Generar puzzles únicos
php public/generate_puzzles.php
```

#### **🔍 Validar Puzzles Existentes**
```bash
# Script de validación completa
php validate_puzzles_simple.php
```

#### **🧹 Mantenimiento Regular**
```bash
# Limpiar caché de Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### **📈 Métricas de Rendimiento**

#### **🎯 KPIs Objetivo:**
- **Tiempo de sesión**: 8-12 minutos promedio
- **Puzzles por sesión**: 2-3 completados
- **Tasa de finalización**: >60%
- **Uso de pistas**: <2 por puzzle

#### **📊 Análisis de Uso:**
- **Dificultad más popular**: Medio (40-50%)
- **Tiempo promedio por dificultad**:
  - Fácil: 3-5 minutos
  - Medio: 5-8 minutos
  - Difícil: 8-15 minutos
  - Experto: 15-30 minutos
  - Maestro: 30+ minutos

---

## 🐛 Solución de Problemas

### **❌ Problemas Comunes**

#### **"No se cargan puzzles nuevos"**
**Causa**: Base de datos vacía o puzzles inválidos
**Solución**:
```bash
php public/import_puzzles.php
php public/generate_puzzles.php
```

#### **"Error CSRF Token Mismatch"**
**Causa**: Sesión expirada
**Solución**: Refrescar página (F5)

#### **"Auto-guardado no funciona"**
**Causa**: Problemas de conectividad con base de datos
**Solución**: Verificar XAMPP y conexión MySQL

#### **"Gráficos no se muestran"**
**Causa**: Librería Recharts no cargó
**Solución**: Los gráficos CSS se cargan automáticamente como fallback

#### **"Sonidos no funcionan"**
**Causa**: Navegador bloqueó audio
**Solución**: Hacer clic en cualquier parte de la página primero

### **🔧 Diagnóstico Avanzado**

#### **Verificar Conectividad:**
1. Acceder a: `http://localhost/Sudoku/public/stats_puzzles.php`
2. Si funciona → OK, si error → problema de Apache/PHP

#### **Verificar Base de Datos:**
```sql
-- Conectar a phpMyAdmin
-- Ejecutar: SELECT COUNT(*) FROM puzzles WHERE is_valid = TRUE;
-- Resultado esperado: >50 puzzles
```

#### **Verificar Logs:**
- **Laravel**: `storage/logs/laravel.log`
- **Apache**: `C:\xampp\apache\logs\error.log`
- **MySQL**: `C:\xampp\mysql\data\*.err`

---

## 🚀 Roadmap de Funcionalidades

### **🔜 Versión 1.1 (Próximamente)**
- **🏆 Sistema de logros expandido**
- **📊 Comparativas sociales**
- **🎨 Temas visuales adicionales**
- **📱 Mejoras para móvil**

### **🔮 Versión 1.2 (Futuro)**
- **👥 Modo multijugador**
- **🏁 Desafíos diarios**
- **🌐 Sincronización en la nube**
- **📚 Tutorial interactivo**

### **🎯 Versión 1.3 (Largo Plazo)**
- **🤖 IA para análisis de patrones**
- **🧩 Variaciones de Sudoku**
- **📊 Analytics avanzadas**
- **🎮 Gamificación completa**

---

## 📞 Soporte y Contacto

### **🆘 Para Ayuda Técnica:**
1. **Revisar esta guía** - Cubre el 95% de los casos
2. **Verificar logs** - Información detallada de errores
3. **Reiniciar servicios** - XAMPP Apache/MySQL
4. **Script de reparación** - `setup_complete.bat`

### **💡 Para Sugerencias:**
- Las funcionalidades están diseñadas para ser intuitivas
- La aplicación se actualiza automáticamente
- Nuevas características se añaden regularmente

---

## 🏆 Logros y Reconocimientos

### **🎯 Meta de la Aplicación**
Crear la **mejor experiencia de Sudoku web** con:
- ✅ **Rendimiento fluido** en cualquier dispositivo
- ✅ **Funcionalidades profesionales** comparables con apps premium
- ✅ **Diseño minimalista** que no distrae del juego
- ✅ **Analíticas profundas** para mejorar como jugador

### **🌟 Características Únicas**
- **Validación en tiempo real** - No más puzzles imposibles
- **Highlighting inteligente** - Reduce errores visuales
- **Auto-guardado robusto** - Nunca pierdas tu progreso
- **Gráficos adaptativos** - Funcionan incluso sin librerías externas
- **Sonidos sutiles** - Feedback sin ser molesto

---

## 📚 Anexos

### **🎮 Comandos de Teclado Completos**
| Tecla | Acción |
|-------|--------|
| `1-9` | Colocar número |
| `0`, `Backspace`, `Delete` | Borrar |
| `↑↓←→` | Navegar |
| `Tab` | Siguiente celda |
| `Shift+Tab` | Celda anterior |
| `Escape` | Deseleccionar |

### **🎨 Códigos de Color**
| Color | Significado |
|-------|-------------|
| 🔵 Azul | Celda seleccionada |
| 🟦 Azul claro | Fila/columna relacionada |
| 🔷 Azul intenso | Mismo número |
| 🔴 Rojo | Error/conflicto |
| 🟡 Amarillo | Pista activa |
| 🟢 Verde | Número disponible |
| 🟠 Naranja | Pocos disponibles |
| ⚫ Gris | Completado |

### **📊 Fórmulas de Cálculo**
- **Tasa de éxito**: `(Completados / Intentados) × 100`
- **Tiempo promedio**: `Suma de tiempos / Puzzles completados`
- **Eficiencia**: `Celdas completadas / Movimientos realizados`
- **Puntuación**: `Base × Dificultad × (1 - Errores/100) × (1 - Pistas/10)`

---

**🎉 ¡Disfruta jugando Sudoku Minimalista!**

*Tu aplicación de Sudoku más avanzada y completa.*
