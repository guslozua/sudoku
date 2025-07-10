-- Script de Optimización de Base de Datos
-- Sudoku Minimalista v2.0
-- Mejora el rendimiento mediante índices optimizados

-- ========================================
-- ÍNDICES PARA TABLA PUZZLES
-- ========================================

-- Índice compuesto para búsquedas por dificultad y validez
CREATE INDEX IF NOT EXISTS idx_puzzles_difficulty_valid 
ON puzzles(difficulty_level, is_valid);

-- Índice para conteo de pistas (usado en analíticas)
CREATE INDEX IF NOT EXISTS idx_puzzles_clues 
ON puzzles(clues_count);

-- Índice para timestamp de creación
CREATE INDEX IF NOT EXISTS idx_puzzles_created 
ON puzzles(created_at);

-- ========================================
-- ÍNDICES PARA TABLA GAMES
-- ========================================

-- Índice compuesto principal para consultas frecuentes
CREATE INDEX IF NOT EXISTS idx_games_user_status 
ON games(user_id, status);

-- Índice para búsquedas por puzzle
CREATE INDEX IF NOT EXISTS idx_games_puzzle 
ON games(puzzle_id);

-- Índice para ordenamiento por fecha
CREATE INDEX IF NOT EXISTS idx_games_dates 
ON games(started_at, last_played_at);

-- Índice específico para juegos completados (analíticas)
CREATE INDEX IF NOT EXISTS idx_games_completed 
ON games(user_id, status, completion_time) 
WHERE status = 'completed';

-- Índice para estadísticas de tiempo
CREATE INDEX IF NOT EXISTS idx_games_completion_time 
ON games(completion_time, status);

-- ========================================
-- ÍNDICES PARA TABLA USERS (SI EXISTE)
-- ========================================

-- Índice para sesiones activas
CREATE INDEX IF NOT EXISTS idx_users_session 
ON users(session_id);

-- Índice para fecha de creación
CREATE INDEX IF NOT EXISTS idx_users_created 
ON users(created_at);

-- ========================================
-- VIEWS OPTIMIZADAS PARA CONSULTAS FRECUENTES
-- ========================================

-- Vista para estadísticas de usuario (cache en SQL)
CREATE OR REPLACE VIEW user_stats_view AS
SELECT 
    u.id as user_id,
    u.session_id,
    COUNT(g.id) as total_games,
    COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as completed_games,
    COUNT(CASE WHEN g.status = 'in_progress' THEN 1 END) as active_games,
    AVG(CASE WHEN g.status = 'completed' THEN g.completion_time END) as avg_completion_time,
    MIN(CASE WHEN g.status = 'completed' THEN g.completion_time END) as best_time,
    SUM(g.hints_used) as total_hints_used,
    SUM(g.mistakes_count) as total_mistakes,
    MAX(g.last_played_at) as last_activity
FROM users u
LEFT JOIN games g ON u.id = g.user_id
GROUP BY u.id, u.session_id;

-- Vista para estadísticas de puzzles
CREATE OR REPLACE VIEW puzzle_stats_view AS
SELECT 
    p.difficulty_level,
    COUNT(*) as total_puzzles,
    COUNT(CASE WHEN p.is_valid = 1 THEN 1 END) as valid_puzzles,
    AVG(p.clues_count) as avg_clues,
    MIN(p.clues_count) as min_clues,
    MAX(p.clues_count) as max_clues,
    COUNT(g.id) as times_played,
    COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as times_completed,
    AVG(CASE WHEN g.status = 'completed' THEN g.completion_time END) as avg_solve_time
FROM puzzles p
LEFT JOIN games g ON p.id = g.puzzle_id
WHERE p.is_valid = 1 OR p.is_valid IS NULL
GROUP BY p.difficulty_level;

-- ========================================
-- PROCEDIMIENTOS ALMACENADOS PARA CONSULTAS COMPLEJAS
-- ========================================

DELIMITER //

-- Procedimiento para obtener puzzle aleatorio optimizado
CREATE PROCEDURE GetRandomPuzzle(IN difficulty VARCHAR(10))
BEGIN
    DECLARE puzzle_count INT;
    DECLARE random_offset INT;
    
    -- Contar puzzles disponibles
    SELECT COUNT(*) INTO puzzle_count 
    FROM puzzles 
    WHERE difficulty_level = difficulty AND (is_valid = 1 OR is_valid IS NULL);
    
    -- Si hay puzzles disponibles
    IF puzzle_count > 0 THEN
        -- Generar offset aleatorio
        SET random_offset = FLOOR(RAND() * puzzle_count);
        
        -- Obtener puzzle usando LIMIT con OFFSET (más eficiente que ORDER BY RAND())
        SELECT id, puzzle_string, solution_string, difficulty_level, clues_count
        FROM puzzles 
        WHERE difficulty_level = difficulty AND (is_valid = 1 OR is_valid IS NULL)
        LIMIT 1 OFFSET random_offset;
    ELSE
        -- No hay puzzles disponibles
        SELECT NULL as id, NULL as puzzle_string, NULL as solution_string, 
               NULL as difficulty_level, NULL as clues_count;
    END IF;
END //

-- Procedimiento para estadísticas rápidas de usuario
CREATE PROCEDURE GetUserQuickStats(IN userId INT)
BEGIN
    SELECT 
        COUNT(*) as total_games,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_games,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as active_games,
        AVG(CASE WHEN status = 'completed' THEN completion_time END) as avg_time,
        MIN(CASE WHEN status = 'completed' THEN completion_time END) as best_time,
        SUM(hints_used) as total_hints,
        MAX(last_played_at) as last_activity
    FROM games 
    WHERE user_id = userId;
END //

DELIMITER ;

-- ========================================
-- MANTENIMIENTO Y LIMPIEZA
-- ========================================

-- Evento para limpieza automática de sesiones viejas (ejecutar semanalmente)
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_old_sessions
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Eliminar juegos de sesiones inactivas por más de 30 días
    DELETE g FROM games g
    INNER JOIN users u ON g.user_id = u.id
    WHERE u.last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND g.status = 'abandoned';
    
    -- Eliminar usuarios sin actividad por más de 60 días
    DELETE FROM users 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 60 DAY)
    AND id NOT IN (SELECT DISTINCT user_id FROM games WHERE status = 'completed');
END //
DELIMITER ;

-- ========================================
-- CONFIGURACIONES RECOMENDADAS
-- ========================================
/*
En my.cnf o my.ini añadir:

[mysqld]
# Optimizaciones para Sudoku
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
query_cache_size = 64M
query_cache_type = 1
tmp_table_size = 32M
max_heap_table_size = 32M

# Índices
key_buffer_size = 32M
read_buffer_size = 2M
read_rnd_buffer_size = 4M
sort_buffer_size = 2M

# Conexiones
max_connections = 100
connect_timeout = 10
wait_timeout = 600
*/
