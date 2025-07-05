# 🎮 Sudoku

Una aplicación de Sudoku profesional con características avanzadas y diseño minimalista.

![Sudoku Game](https://img.shields.io/badge/Status-Functional-brightgreen)
![Version](https://img.shields.io/badge/Version-1.0-blue)
![Tech](https://img.shields.io/badge/Tech-React%20%2B%20PHP-orange)

## ✨ Características Principales

### 🎨 **Highlighting Inteligente**
- **Números iguales**: Resalta automáticamente todas las celdas con el mismo número
- **Fila y columna**: Highlighting sutil de la fila y columna seleccionada
- **Highlighting híbrido**: Doble resaltado para números iguales en misma fila/columna

### 🎯 **Sistema de Puzzles**
- **5 dificultades**: Easy, Medium, Hard, Expert, Master
- **Puzzles infinitos**: Base de datos con múltiples puzzles por dificultad
- **APIs dinámicas**: Carga puzzles únicos desde el backend
- **Game tracking**: Cada partida tiene ID único para estadísticas

### 🎮 **Experiencia de Usuario**
- **Selección intuitiva**: Clic en cualquier celda para seleccionar
- **Auto-selección**: Al hacer clic en una celda con número, se auto-selecciona ese número
- **Borrado inteligente**: Solo permite borrar números que tú pusiste
- **Feedback visual**: Highlighting inmediato y animaciones suaves

### 🌙 **Diseño y UI**
- **Modo oscuro/claro**: Toggle suave entre temas
- **Responsive design**: Funciona perfectamente en móvil y desktop
- **Animaciones CSS**: Transiciones suaves y micro-interacciones
- **Contador de números**: Muestra cuántos números quedan por dificultad

### 📊 **Sistema de Estadísticas**
- **Timer de juego**: Cronómetro automático con pausa
- **Contador de movimientos**: Tracking de todas las acciones
- **Progreso visual**: Porcentaje de completado en tiempo real
- **Session management**: Usuarios anónimos con tracking

## 🛠️ Tech Stack

### **Frontend**
- **React 18**: Biblioteca principal de UI
- **Tailwind CSS**: Framework de estilos utilitarios
- **Babel**: Transpilación de JSX en tiempo real
- **Vanilla JS**: Sin dependencias adicionales

### **Backend**
- **PHP 8**: Lenguaje del servidor
- **MySQL**: Base de datos relacional
- **PDO**: Conexión segura a base de datos
- **REST APIs**: Endpoints JSON para comunicación

### **Arquitectura**
- **SPA (Single Page Application)**: React con estado local
- **API-driven**: Frontend consume APIs REST del backend
- **Session-based**: Usuarios anónimos con PHP sessions
- **Responsive**: Mobile-first design

## 🚀 Instalación y Configuración

### **Requisitos**
- XAMPP (Apache + MySQL + PHP 8+)
- Navegador moderno con soporte ES6+

### **Setup**
1. **Clonar repositorio**
   ```bash
   git clone https://github.com/guslozua/Sudoku.git
   cd Sudoku
   ```

2. **Configurar XAMPP**
   - Colocar proyecto en `C:\xampp\htdocs\Sudoku`
   - Iniciar Apache y MySQL

3. **Configurar Base de Datos**
   - Crear base de datos `sudoku` en phpMyAdmin
   - Importar el archivo SQL proporcionado
   - O ejecutar: `http://localhost/Sudoku/test_api.php` para auto-setup

4. **Acceder a la aplicación**
   ```
   http://localhost/Sudoku/public
   ```

## 📁 Estructura del Proyecto

```
Sudoku/
├── 📁 app/Http/Controllers/     # Controladores PHP
├── 📁 resources/views/sudoku/   # Vista principal React
├── 📁 routes/                   # Definición de rutas
├── 📁 public/                   # Punto de entrada web
├── 📄 api_router.php           # Router de APIs REST
├── 📄 test_api.php             # Script de testing y setup
└── 📄 README.md                # Este archivo
```

## 🎯 APIs Disponibles

### **Puzzles**
- `GET /api/puzzle/new/{difficulty}` - Obtener nuevo puzzle
- `POST /api/puzzle/validate` - Validar solución

### **Juegos**
- `POST /api/game/save` - Guardar progreso
- `POST /api/game/complete` - Marcar como completado

### **Estadísticas**
- `GET /api/stats` - Obtener estadísticas del usuario

## 🎮 Cómo Jugar

1. **Seleccionar celda**: Haz clic en cualquier celda del tablero
2. **Colocar número**: Haz clic en un número del panel lateral
3. **Observar highlighting**: Automáticamente se resaltan números iguales y fila/columna
4. **Borrar números**: Usa el botón borrar o tecla Backspace (solo en números que tú pusiste)
5. **Cambiar dificultad**: Usa el selector en la parte superior
6. **Nuevo puzzle**: Botón "Nuevo" para cargar un puzzle diferente

## 🎨 Características Visuales

### **Sistema de Highlighting**
- 🔵 **Celda seleccionada**: Anillo azul brillante + sombra
- 🟦 **Números iguales**: Fondo azul medio con animación pulse
- 🟦 **Fila/columna**: Highlighting sutil azul claro
- 🔷 **Híbrido**: Azul intenso para números iguales EN fila/columna

### **Estados de Celdas**
- 🔲 **Originales**: Celdas del puzzle (no editables)
- ✏️ **Editables**: Celdas donde puedes poner números
- 🔴 **Errores**: Números que violan reglas de Sudoku
- ✅ **Completadas**: Al finalizar el puzzle

## 📊 Base de Datos

### **Tablas Principales**
- `puzzles`: Almacena puzzles y soluciones por dificultad
- `games`: Tracking de partidas individuales
- `users`: Usuarios anónimos con sessions
- `stats`: Estadísticas y métricas de juego

### **Datos Incluidos**
- **21 puzzles únicos**: 3-6 puzzles por cada dificultad
- **Auto-poblado**: Script automático si no hay datos
- **Escalable**: Fácil agregar más puzzles

## 🔧 Debugging y Testing

### **Herramientas de Debug**
- Panel de debug temporal (removible para producción)
- Logs detallados en consola del navegador
- Script de testing de APIs: `/test_api.php`
- Botón de test de API integrado (🧪)

### **Testing**
```bash
# Probar APIs directamente
http://localhost/Sudoku/public/api/puzzle/new/easy

# Script de testing completo
http://localhost/Sudoku/test_api.php
```

## 🌟 Roadmap Futuro

### **Versión 1.1**
- [ ] 🤖 Validación de errores visual en tiempo real
- [ ] 💡 Sistema de pistas inteligente (máximo 3 por puzzle)
- [ ] 💾 Auto-guardado automático cada 10 segundos
- [ ] 🏆 Sistema de logros y badges

### **Versión 1.2**
- [ ] 🎵 Sonidos sutiles para feedback
- [ ] 📊 Gráficos de progreso detallados
- [ ] 🌐 Multi-idioma (español/inglés)
- [ ] 🎨 Temas personalizables

### **Versión 1.3**
- [ ] 👥 Modo multijugador cooperativo
- [ ] 🏁 Desafíos diarios con rankings
- [ ] 📱 PWA (Progressive Web App)
- [ ] 🤖 IA para análisis de patrones

## 🤝 Contribuciones

¡Las contribuciones son bienvenidas! Si tienes ideas para mejorar el juego:

1. Fork el repositorio
2. Crea una branch para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la branch (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto está bajo la Licencia MIT. Ver `LICENSE` para más detalles.

## 👤 Autor

**guslozua** - [GitHub](https://github.com/guslozua)

---

⭐ ¡Si te gusta este proyecto, dale una estrella en GitHub!

🎮 **¡Disfruta jugando Sudoku!**
