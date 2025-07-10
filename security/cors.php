<?php
/**
 * Configuración CORS Segura para Sudoku
 */

class CORSConfig 
{
    // Dominios permitidos para producción
    private static $allowedOrigins = [
        'http://localhost',
        'http://localhost:3000',
        'http://127.0.0.1',
        'https://tu-dominio-produccion.com'  // Cambiar por tu dominio real
    ];
    
    /**
     * Aplicar headers CORS seguros
     */
    public static function applyHeaders() 
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Solo permitir orígenes específicos
        if (in_array($origin, self::$allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
        
        // Headers específicos y seguros
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 horas
        
        // Headers de seguridad adicionales
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Manejar preflight requests
     */
    public static function handlePreflight() 
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::applyHeaders();
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Validar que el request viene de un origen permitido
     */
    public static function validateOrigin() 
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Para requests sin origin (como Postman), verificar referer
        if (empty($origin)) {
            foreach (self::$allowedOrigins as $allowedOrigin) {
                if (strpos($referer, $allowedOrigin) === 0) {
                    return true;
                }
            }
            return false;
        }
        
        return in_array($origin, self::$allowedOrigins);
    }
}
