<?php
/**
 * Configuración Central de Seguridad
 * Sudoku Minimalista v2.0
 */

class SecurityConfig 
{
    // Configuración CSRF
    const CSRF_TOKEN_LIFETIME = 3600; // 1 hora
    const CSRF_HEADER_NAME = 'X-CSRF-Token';
    
    // Configuración CORS
    const ALLOWED_ORIGINS = [
        'http://localhost',
        'http://localhost:3000',
        'http://127.0.0.1',
        'http://localhost:8000',
        // Añadir dominio de producción aquí
    ];
    
    // Configuración de sesiones
    const SESSION_LIFETIME = 86400; // 24 horas
    const SESSION_NAME = 'SUDOKU_SESSION';
    
    // Rate limiting básico
    const MAX_REQUESTS_PER_MINUTE = 60;
    const MAX_FAILED_ATTEMPTS = 5;
    
    /**
     * Configurar sesiones seguras
     */
    public static function configureSecureSessions() 
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuración segura de sesiones
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
            
            session_name(self::SESSION_NAME);
            session_start();
            
            // Regenerar ID de sesión periódicamente
            if (!isset($_SESSION['last_regeneration']) || 
                (time() - $_SESSION['last_regeneration']) > 300) { // 5 minutos
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Validar input básico
     */
    public static function sanitizeInput($input, $type = 'string') 
    {
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
            case 'sudoku_grid':
                // Validar que sea una cadena de 81 caracteres con solo números y puntos
                return preg_match('/^[0-9.]{81}$/', $input) ? $input : false;
            case 'difficulty':
                $allowed = ['easy', 'medium', 'hard', 'expert', 'master'];
                return in_array($input, $allowed) ? $input : false;
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Rate limiting básico
     */
    public static function checkRateLimit($identifier = null) 
    {
        if (!$identifier) {
            $identifier = $_SERVER['REMOTE_ADDR'];
        }
        
        $key = "rate_limit_$identifier";
        $current = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
        
        // Reset counter si ha pasado un minuto
        if ((time() - $current['time']) > 60) {
            $current = ['count' => 0, 'time' => time()];
        }
        
        $current['count']++;
        $_SESSION[$key] = $current;
        
        return $current['count'] <= self::MAX_REQUESTS_PER_MINUTE;
    }
    
    /**
     * Log de seguridad
     */
    public static function logSecurityEvent($event, $details = []) 
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $logLine = json_encode($logData) . "\n";
        file_put_contents(__DIR__ . '/../logs/security.log', $logLine, FILE_APPEND | LOCK_EX);
    }
}
