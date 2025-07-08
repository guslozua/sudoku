# 🎮 Sudoku Minimalista

Aplicación de Sudoku profesional con características avanzadas y auto-guardado inteligente.

![Version](https://img.shields.io/badge/version-2.0-blue) ![Status](https://img.shields.io/badge/status-Production%20Ready-green) ![Tech](https://img.shields.io/badge/tech-React%20%2B%20PHP%20%2B%20MySQL-orange) ![Puzzles](https://img.shields.io/badge/puzzles-107%20quality-purple)

## ✨ Características Principales

### 🎯 Funcionalidades Core
- 🎨 **Highlighting Inteligente** - Resalta números, filas, columnas y conflictos
- 🔄 **Puzzles Infinitos** - 5 dificultades con generación dinámica
- 🤖 **Validación Visual** - Errores en tiempo real con números rojos
- 💡 **Sistema de Pistas** - Explicaciones educativas inteligentes (máx. 3 por puzzle)
- 💾 **Auto-guardado** - Progreso guardado automáticamente cada 10 segundos
- 🔄 **Recuperación de Partidas** - Continúa exactamente donde lo dejaste
- 🏆 **Sistema de Logros** - 14 logros gamificados con celebraciones
- 🎵 **Sonidos Sutiles** - Feedback auditivo profesional para cada acción

### 🏆 Sistema de Logros Completo
- 🥇 **Logros de Completado** - Primer paso, 10 puzzles, 50 puzzles
- 🏃‍♂️ **Logros de Velocidad** - Demonio de velocidad, rayo veloz
- 🎯 **Logros de Dificultad** - Desafiante experto, conquistador maestro
- 🧠 **Logros de Estrategia** - Mente estratégica, sin pistas
- 💎 **Logros de Precisión** - Juego perfecto, solucionador eficiente
- 🎊 **Celebraciones Animadas** - Modales de logro con animaciones
- 📊 **Galería Visual** - Progreso completo con barras y estadísticas

### 🎵 Sistema de Sonidos Profesional
- 🔢 **Sonidos de Acción** - Colocar números, borrar, errores
- 💡 **Sonidos de Feedback** - Pistas, éxito, logros desbloqueados
- 🎹 **Síntesis Musical** - Frecuencias calculadas con Web Audio API
- 🎵 **Controles Completos** - Toggle on/off, control de volumen
- 💾 **Persistencia** - Preferencias guardadas en localStorage
- 🎼 **Botones de Prueba** - Escucha cada sonido individualmente

### 🎨 Experiencia de Usuario
- 🌙 **Modo Oscuro/Claro** - Diseño adaptativo
- 📱 **Responsive Design** - Perfecto en móvil y desktop
- ⌨️ **Controles de Teclado** - Navegación completa con teclado
- 📊 **Estadísticas en Tiempo Real** - Tiempo, movimientos, progreso
- 🎮 **Interfaz Minimalista** - Diseño limpio y profesional

### 🔧 Características Técnicas
- 📡 **APIs REST** - Backend robusto con PHP y MySQL
- 🗄️ **Base de Datos Optimizada** - Almacenamiento eficiente de puzzles y progreso
- 🔐 **Gestión de Sesiones** - Sistema de usuarios anónimos
- ⚡ **Performance Optimizado** - Carga rápida y experiencia fluida

## 🚀 Quick Start

### Requisitos
- XAMPP con Apache y MySQL
- PHP 7.4+ 
- Navegador moderno (Chrome, Firefox, Safari, Edge)

### Instalación
1. **Clonar repositorio**
   ```bash
   git clone https://github.com/guslozua/Sudoku.git
   cd Sudoku
   ```

2. **Configurar base de datos**
   - Crear base de datos `sudoku` en MySQL
   - Importar estructura de tablas (ver `/database/` para scripts)

3. **Iniciar servidor**
   - Asegurarse de que XAMPP esté corriendo
   - Acceder a `http://localhost/Sudoku/public`

## 🎮 Cómo Jugar

### 🎯 Controles Básicos
- **Click en celda** - Seleccionar celda
- **Números 1-9** - Colocar número
- **Backspace/Delete** - Borrar número
- **Flechas** - Navegar entre celdas
- **Tab** - Siguiente celda editable

### 💡 Sistema de Pistas
- Máximo 3 pistas por puzzle
- Explicaciones educativas detalladas
- Highlighting especial de la celda sugerida
- Auto-oculto después de 5 segundos

### 💾 Auto-guardado
- Guardado automático cada 10 segundos
- Modal de continuación al volver
- Restauración completa del estado
- Indicadores visuales de estado de guardado

## 🔄 API Endpoints

### **Puzzles:**
```
GET  /api/puzzle/new/{difficulty}    - Nuevo puzzle
```

### **Juego:**
```
POST /api/game/save                  - Guardar progreso
POST /api/game/complete              - Completar puzzle con verificación de logros
GET  /api/game/current               - Juego actual
```

### **Pistas:**
```
POST /api/hint                       - Obtener pista
```

### **Logros:**
```
GET  /api/achievements               - Obtener logros del usuario
```

## 🎨 Personalización

### **Colores Principales:**
```css
/* Modo Claro */
--primary-bg: #FFFFFF
--secondary-bg: #F8F9FA  
--accent-color: #0066CC
--success-color: #28A745
--error-color: #DC3545

/* Modo Oscuro */
--primary-bg: #1A1A1A
--secondary-bg: #2D2D2D
--accent-color: #4A9EFF
```

### **Tipografía:**
- **Fuente**: Inter (Google Fonts)
- **Tamaños**: 16px base, 24px números, 14px notas
- **Pesos**: 400 regular, 600 semi-bold, 700 bold

## 📊 Sistema de Estadísticas

### **Datos Guardados Automáticamente:**
- ⏱️ **Tiempo de juego**
- 🎯 **Número de movimientos**
- 💡 **Pistas utilizadas**
- 📈 **Progreso del puzzle**
- 🏆 **Partidas completadas**

## 🛠️ Tech Stack

- **Frontend**: React 18 + Tailwind CSS
- **Backend**: PHP 8 + MySQL
- **Session Management**: PHP Sessions
- **API**: REST endpoints
- **Database**: 107+ puzzles únicos de calidad garantizada

## 🏆 Roadmap

### **Versión 2.0 (Actual):**
- ✅ 🎲 **107 puzzles de calidad** - Base de datos expandida 6x
- ✅ 🔍 **Validación completa** - 0% puzzles inválidos garantizado
- ✅ 🎯 **Distribución perfecta** - Todas las dificultades bien pobladas
- ✅ 🤖 **Generador automático** - Puzzles únicos ilimitados
- ✅ 📊 **Dashboard completo** - Estadísticas y monitoreo
- ✅ 🛡️ **Sistema anti-frustración** - No más puzzles imposibles
- ✅ 🏆 **Sistema de logros** completo con 14 logros
- ✅ 🎊 **Celebraciones animadas** para logros desbloqueados
- ✅ 📊 **Galería visual** con progreso y estadísticas
- ✅ 🎮 **Gamificación** completa para mayor engagement
- ✅ 🎵 **Sonidos sutiles** con feedback auditivo profesional

### **Versión 2.1 (Próxima):**
- 📊 **Gráficos de progreso** detallados
- 🌐 **Multi-idioma** (español/inglés)
- 🎨 **Temas personalizables** con múltiples paletas

### **Versión 2.2 (Futura):**
- 👥 Modo multijugador cooperativo
- 🏁 Desafíos diarios con rankings
- 📱 PWA (Progressive Web App)
- 🔄 Sincronización en la nube

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

## 🙏 Agradecimientos

- React team por el excelente framework
- Tailwind CSS por el sistema de diseño
- Google Fonts por la tipografía Inter
- Comunidad de desarrolladores por la inspiración

---

**Desarrollado con ❤️ para la comunidad de Sudoku**

¿Encontraste un bug? ¿Tienes una sugerencia? [Abre un issue](https://github.com/guslozua/Sudoku/issues)
