-- ✅ SCRIPT PARA CORREGIR ESTRUCTURA DE BASE DE DATOS SUDOKU
-- Ejecutar en phpMyAdmin o línea de comandos MySQL

USE sudoku;

-- ✅ 1. TABLA USERS (Verificar y crear si no existe)
CREATE TABLE IF NOT EXISTS `users` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `session_id` varchar(255) NOT NULL,
    `is_anonymous` tinyint(1) DEFAULT 1,
    `is_premium` tinyint(1) DEFAULT 0,
    `preferred_difficulty` enum('easy','medium','hard','expert','master') DEFAULT 'medium',
    `theme_preference` enum('light','dark','auto') DEFAULT 'auto',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_id` (`session_id`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ✅ 2. TABLA PUZZLES (Verificar estructura)
CREATE TABLE IF NOT EXISTS `puzzles` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `puzzle_string` varchar(81) NOT NULL,
    `solution_string` varchar(81) NOT NULL,
    `difficulty_level` enum('easy','medium','hard','expert','master') NOT NULL,
    `clues_count` int(11) DEFAULT NULL,
    `is_valid` tinyint(1) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_difficulty_level` (`difficulty_level`),
    KEY `idx_is_valid` (`is_valid`),
    KEY `idx_difficulty_valid` (`difficulty_level`, `is_valid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ✅ 3. TABLA GAMES (Verificar todas las columnas necesarias)
CREATE TABLE IF NOT EXISTS `games` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `puzzle_id` bigint(20) unsigned NOT NULL,
    `current_state` varchar(81) NOT NULL,
    `initial_state` varchar(81) NOT NULL,
    `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress',
    `hints_used` int(11) DEFAULT 0,
    `mistakes_count` int(11) DEFAULT 0,
    `moves_count` int(11) DEFAULT 0,
    `time_spent` int(11) DEFAULT 0,
    `completion_time` int(11) DEFAULT NULL,
    `perfect_game` tinyint(1) DEFAULT 0,
    `notes` text,
    `started_at` timestamp NULL DEFAULT NULL,
    `completed_at` timestamp NULL DEFAULT NULL,
    `last_played_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_puzzle_id` (`puzzle_id`),
    KEY `idx_status` (`status`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_completed_at` (`completed_at`),
    KEY `idx_last_played_at` (`last_played_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`puzzle_id`) REFERENCES `puzzles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ✅ 4. TABLA ACHIEVEMENTS (Sistema de logros)
CREATE TABLE IF NOT EXISTS `achievements` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `key_name` varchar(100) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `icon` varchar(10) DEFAULT '🏆',
    `category` enum('completion','speed','difficulty','strategy','special') DEFAULT 'completion',
    `target_value` int(11) DEFAULT 1,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key_name` (`key_name`),
    KEY `idx_category` (`category`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ✅ 5. TABLA USER_ACHIEVEMENTS (Logros desbloqueados por usuario)
CREATE TABLE IF NOT EXISTS `user_achievements` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `achievement_id` bigint(20) unsigned NOT NULL,
    `is_completed` tinyint(1) DEFAULT 1,
    `current_progress` int(11) DEFAULT 0,
    `unlocked_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_achievement` (`user_id`,`achievement_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_achievement_id` (`achievement_id`),
    KEY `idx_unlocked_at` (`unlocked_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ✅ 6. INSERTAR LOGROS BÁSICOS
INSERT IGNORE INTO `achievements` (`key_name`, `name`, `description`, `icon`, `category`, `target_value`, `is_active`) VALUES
('first_step', 'Primer Paso', 'Completa tu primer puzzle de Sudoku', '🎯', 'completion', 1, 1),
('puzzle_master', 'Maestro de Puzzles', 'Completa 10 puzzles exitosamente', '🏆', 'completion', 10, 1),
('sudoku_legend', 'Leyenda del Sudoku', 'Completa 50 puzzles - ¡Eres una leyenda!', '👑', 'completion', 50, 1),
('speed_demon', 'Demonio de Velocidad', 'Completa un puzzle en menos de 5 minutos', '⚡', 'speed', 1, 1),
('lightning_fast', 'Rápido como el Rayo', 'Completa un puzzle en menos de 3 minutos', '🚀', 'speed', 1, 1),
('strategic_mind', 'Mente Estratégica', 'Completa un puzzle sin usar pistas', '🧠', 'strategy', 1, 1),
('perfect_game', 'Juego Perfecto', 'Completa un puzzle sin cometer errores', '💎', 'strategy', 1, 1),
('efficient_solver', 'Solucionador Eficiente', 'Completa un puzzle con menos de 100 movimientos', '🎱', 'strategy', 1, 1);

-- ✅ 7. INSERTAR PUZZLES DE EJEMPLO (si no existen)
INSERT IGNORE INTO `puzzles` (`id`, `puzzle_string`, `solution_string`, `difficulty_level`, `clues_count`) VALUES
(1, '530070000600195000098000060800060003400803001700020006060000280000419005000080079', '534678912672195348198342567859761423426853791713924856961537284287419635345286179', 'easy', 30),
(2, '000000000904607000076804100309701080008000300050308702007502610000109405000000000', '581239764924607153376854129329741586148956327657318742897562631263189475412375896', 'medium', 25),
(3, '800000000003600000070090200050007000000045700000100030001000068008500010090000400', '812753649943682571576491283251967834369845792784126935431279568628534917195368427', 'hard', 20),
(4, '020000000000600003074080000000003002080040070600500000000020540300009000000000010', '126437958895621743374985126457813692183246579629574381961728354348159267752364815', 'easy', 28),
(5, '000000907000420180000705026100904000050000040000507009920108000034059000507000000', '683241957971423185249765326158964372456382741372517609925138764834659217517896433', 'medium', 24);

-- ✅ 8. MOSTRAR ESTADÍSTICAS DE LA BASE DE DATOS
SELECT 
    'users' as tabla, COUNT(*) as registros FROM users
UNION ALL
SELECT 
    'puzzles' as tabla, COUNT(*) as registros FROM puzzles
UNION ALL
SELECT 
    'games' as tabla, COUNT(*) as registros FROM games
UNION ALL
SELECT 
    'achievements' as tabla, COUNT(*) as registros FROM achievements
UNION ALL
SELECT 
    'user_achievements' as tabla, COUNT(*) as registros FROM user_achievements;

-- ✅ FIN DEL SCRIPT
