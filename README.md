# 🎯 Sudoku Minimalista

Un juego de Sudoku moderno y elegante construido con React y PHP, diseñado para ofrecer una experiencia de usuario excepcional con funcionalidades avanzadas como sistema de logros, analíticas y auto-guardado.

![Sudoku Demo](https://img.shields.io/badge/Estado-Funcional-green?style=for-the-badge)
![PHP Version](https://img.shields.io/badge/PHP-8.1+-blue?style=for-the-badge)
![React Version](https://img.shields.io/badge/React-18.3.1-61DAFB?style=for-the-badge&logo=react)

## ✨ Características Principales

### 🎮 **Experiencia de Juego**
- **5 Niveles de Dificultad**: Easy, Medium, Hard, Expert, Master
- **Interfaz Intuitiva**: Diseño minimalista y responsive
- **Highlighting Inteligente**: Resaltado automático de números y conflictos
- **Sistema de Pistas**: Hasta 3 pistas por juego con explicaciones educativas
- **Validación en Tiempo Real**: Detección automática de errores

### 🏆 **Sistema de Logros**
- **24 Logros Diferentes**: Desde primer paso hasta maestro conquistador
- **Categorías Variadas**: Completado, velocidad, dificultad, estrategia y especiales
- **Notificaciones Visuales**: Alertas animadas al desbloquear logros
- **Progreso Persistente**: Seguimiento automático del progreso del jugador

### 📊 **Analíticas Avanzadas**
- **Dashboard Completo**: Estadísticas detalladas de rendimiento
- **Gráficos Interactivos**: Visualización de progreso y tendencias
- **Métricas Detalladas**: Tiempo promedio, mejor tiempo, puzzles perfectos
- **Historial de Partidas**: Seguimiento completo de la actividad

### 💾 **Funcionalidades Técnicas**
- **Auto-guardado**: Progreso guardado automáticamente cada 60 segundos
- **Gestión de Sesiones**: Sistema robusto de usuarios anónimos
- **API RESTful**: Endpoints organizados y documentados
- **Base de Datos Optimizada**: Consultas eficientes con índices apropiados

## 🚀 Instalación Rápida

### Prerrequisitos
- **XAMPP** (PHP 8.1+, MySQL, Apache)
- **Navegador moderno** con soporte para ES6+

### Pasos de Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/tu-usuario/sudoku-minimalista.git
   cd sudoku-minimalista
   ```

2. **Configurar base de datos**
   ```bash
   # Abrir phpMyAdmin: http://localhost/phpmyadmin
   # Crear base de datos 'sudoku'
   # Importar: docs/database_setup.sql
   ```

3. **Configurar entorno**
   ```bash
   cp .env.example .env
   # Editar .env con tus configuraciones
   ```

4. **Instalar dependencias** (opcional)
   ```bash
   composer install  # Solo si usas Composer
   ```

5. **Acceder al juego**
   ```
   http://localhost/sudoku-minimalista/public
   ```

## 🏗️ Estructura del Proyecto

```
📁 sudoku-minimalista/
├── 📁 app/
│   └── 📁 Http/Controllers/     # Controladores principales
├── 📁 public/
│   ├── 📁 api/                  # API RESTful
│   └── index.php                # Aplicación React
├── 📁 docs/
│   └── database_setup.sql       # Script de base de datos
├── 📁 resources/views/
│   └── sudoku/index.blade.php   # Vista principal
├── 📁 routes/                   # Definición de rutas
├── 📁 logs/                     # Archivos de log
├── .env.example                 # Configuración de ejemplo
└── README.md                    # Este archivo
```

## 🔌 API Endpoints

### Puzzles
- `GET /api/puzzle/new/{difficulty}` - Obtener nuevo puzzle
- `POST /api/puzzle/validate` - Validar solución

### Juegos
- `GET /api/game/current` - Obtener juego actual
- `POST /api/game/save` - Guardar progreso
- `POST /api/game/complete` - Completar juego

### Estadísticas
- `GET /api/stats` - Estadísticas del usuario
- `GET /api/achievements` - Logros del usuario

### Pistas
- `POST /api/hint` - Obtener pista inteligente

## 🎨 Tecnologías Utilizadas

### Frontend
- **React 18.3.1** - Biblioteca de interfaz de usuario
- **Tailwind CSS** - Framework de estilos utilitarios
- **Babel** - Transpilador JavaScript
- **Recharts** - Biblioteca de gráficos (con fallbacks CSS)

### Backend
- **PHP 8.1+** - Lenguaje de servidor
- **MySQL** - Base de datos relacional
- **Laravel Components** - Componentes seleccionados de Laravel

### Herramientas
- **XAMPP** - Entorno de desarrollo local
- **Git** - Control de versiones
- **Composer** - Gestor de dependencias PHP

## 🔧 Configuración Avanzada

### Variables de Entorno (.env)
```env
APP_NAME=SudokuMinimalista
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sudoku
DB_USERNAME=root
DB_PASSWORD=
```

## 🤝 Contribución

### Cómo Contribuir

1. **Fork** el repositorio
2. **Crear** una rama para tu feature: `git checkout -b feature/nueva-funcionalidad`
3. **Commit** tus cambios: `git commit -m 'Añadir nueva funcionalidad'`
4. **Push** a la rama: `git push origin feature/nueva-funcionalidad`
5. **Abrir** un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

## 🙏 Agradecimientos

- **React Team** - Por la increíble biblioteca de UI
- **Tailwind CSS** - Por el framework de estilos
- **Laravel** - Por los componentes robustos
- **Comunidad Open Source** - Por la inspiración y herramientas

---

<div align="center">
  <p><strong>🎯 Sudoku Minimalista - Donde la lógica se encuentra con la elegancia</strong></p>
  <p>Hecho con ❤️ y mucho ☕</p>
</div>
