<?php
/**
 * Sistema CSRF Seguro para Sudoku
 * Reemplaza el token dummy por uno real
 */

class CSRFProtection 
{
    /**
     * Generar token CSRF único
     */
    public static function generateToken() 
    {
        if (!session_id()) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Obtener token CSRF actual o generar uno nuevo
     */
    public static function getToken() 
    {
        if (!session_id()) {
            session_start();
        }
        
        // Si no existe token o es muy viejo (1 hora), generar nuevo
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            return self::generateToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validar token CSRF
     */
    public static function validateToken($token) 
    {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Verificar que el token no sea muy viejo (1 hora)
        if ((time() - $_SESSION['csrf_token_time']) > 3600) {
            return false;
        }
        
        // Comparación segura de tokens
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Middleware para validar CSRF en requests POST
     */
    public static function validateRequest() 
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!$token || !self::validateToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Token CSRF inválido o expirado',
                    'code' => 'CSRF_INVALID'
                ]);
                exit;
            }
        }
    }
}
