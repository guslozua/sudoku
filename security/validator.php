<?php
/**
 * Validador de Entrada para APIs
 * Sudoku Minimalista v2.0
 */

class InputValidator 
{
    /**
     * Validar request de nuevo puzzle
     */
    public static function validateNewPuzzleRequest($data) 
    {
        $errors = [];
        
        if (!isset($data['difficulty'])) {
            $errors[] = 'Dificultad requerida';
        } else {
            $validDifficulties = ['easy', 'medium', 'hard', 'expert', 'master'];
            if (!in_array($data['difficulty'], $validDifficulties)) {
                $errors[] = 'Dificultad inválida';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validar request de guardado de juego
     */
    public static function validateSaveGameRequest($data) 
    {
        $errors = [];
        
        if (!isset($data['game_id']) || !is_numeric($data['game_id'])) {
            $errors[] = 'ID de juego inválido';
        }
        
        if (!isset($data['current_state'])) {
            $errors[] = 'Estado actual requerido';
        } else {
            if (!preg_match('/^[0-9.]{81}$/', $data['current_state'])) {
                $errors[] = 'Estado del juego inválido (debe ser 81 caracteres)';
            }
        }
        
        if (isset($data['moves_count']) && !is_numeric($data['moves_count'])) {
            $errors[] = 'Contador de movimientos inválido';
        }
        
        if (isset($data['hints_used']) && !is_numeric($data['hints_used'])) {
            $errors[] = 'Contador de pistas inválido';
        }
        
        return $errors;
    }
    
    /**
     * Validar request de completar juego
     */
    public static function validateCompleteGameRequest($data) 
    {
        $errors = [];
        
        if (!isset($data['game_id']) || !is_numeric($data['game_id'])) {
            $errors[] = 'ID de juego inválido';
        }
        
        if (!isset($data['solution'])) {
            $errors[] = 'Solución requerida';
        } else {
            if (!preg_match('/^[1-9]{81}$/', $data['solution'])) {
                $errors[] = 'Solución inválida (debe ser 81 números del 1-9)';
            }
        }
        
        if (!isset($data['completion_time']) || !is_numeric($data['completion_time'])) {
            $errors[] = 'Tiempo de completado inválido';
        }
        
        return $errors;
    }
    
    /**
     * Validar request de pista
     */
    public static function validateHintRequest($data) 
    {
        $errors = [];
        
        if (!isset($data['game_id']) || !is_numeric($data['game_id'])) {
            $errors[] = 'ID de juego inválido';
        }
        
        if (!isset($data['current_state'])) {
            $errors[] = 'Estado actual requerido';
        } else {
            if (!preg_match('/^[0-9.]{81}$/', $data['current_state'])) {
                $errors[] = 'Estado del juego inválido';
            }
        }
        
        if (isset($data['position'])) {
            $pos = intval($data['position']);
            if ($pos < 0 || $pos > 80) {
                $errors[] = 'Posición inválida (debe estar entre 0-80)';
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitizar datos de entrada
     */
    public static function sanitizeData($data) 
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Método genérico para validar request
     */
    public static function validateRequest($endpoint, $data) 
    {
        // Sanitizar datos primero
        $data = self::sanitizeData($data);
        
        switch ($endpoint) {
            case 'new_puzzle':
                return self::validateNewPuzzleRequest($data);
            case 'save_game':
                return self::validateSaveGameRequest($data);
            case 'complete_game':
                return self::validateCompleteGameRequest($data);
            case 'hint':
                return self::validateHintRequest($data);
            default:
                return ['Endpoint no reconocido'];
        }
    }
    
    /**
     * Validar que el usuario tenga permisos para el juego
     */
    public static function validateGameOwnership($gameId, $userId) 
    {
        try {
            // Verificar que el juego pertenece al usuario
            $stmt = "SELECT user_id FROM games WHERE id = ? LIMIT 1";
            // Aquí necesitarías implementar la conexión a BD
            // Por ahora devolvemos true
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Función helper para validación rápida
function validateAndSanitize($endpoint, $data) {
    $errors = InputValidator::validateRequest($endpoint, $data);
    
    if (!empty($errors)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => $errors,
            'message' => 'Datos de entrada inválidos'
        ]);
        exit;
    }
    
    return InputValidator::sanitizeData($data);
}
