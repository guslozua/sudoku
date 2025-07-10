<?php
/**
 * Script de Verificación de Seguridad
 * Sudoku Minimalista v2.0
 * 
 * Ejecutar antes de cada despliegue a producción
 */

class SecurityChecker 
{
    private $issues = [];
    private $warnings = [];
    private $passed = [];
    
    public function runAllChecks() 
    {
        echo "🔒 VERIFICACIÓN DE SEGURIDAD - SUDOKU MINIMALISTA\n";
        echo "================================================\n\n";
        
        $this->checkPublicDirectory();
        $this->checkCSRFImplementation();
        $this->checkCORSConfiguration();
        $this->checkSessionSecurity();
        $this->checkFilePermissions();
        $this->checkEnvironmentConfig();
        
        $this->showResults();
    }
    
    private function checkPublicDirectory() 
    {
        echo "📁 Verificando directorio público...\n";
        
        $publicDir = __DIR__ . '/../public/';
        $dangerousFiles = [
            'debug_', 'test_', 'setup_', 'fix_', 'phpinfo',
            '.env', 'config.php', 'database.php'
        ];
        
        $files = scandir($publicDir);
        $foundDangerous = [];
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            foreach ($dangerousFiles as $dangerous) {
                if (strpos($file, $dangerous) !== false) {
                    $foundDangerous[] = $file;
                    break;
                }
            }
        }
        
        if (empty($foundDangerous)) {
            $this->passed[] = "✅ Directorio público limpio";
        } else {
            $this->issues[] = "❌ Archivos peligrosos en público: " . implode(', ', $foundDangerous);
        }
    }
    
    private function checkCSRFImplementation() 
    {
        echo "🛡️ Verificando implementación CSRF...\n";
        
        $csrfFile = __DIR__ . '/csrf.php';
        $indexFile = __DIR__ . '/../public/index.php';
        
        if (!file_exists($csrfFile)) {
            $this->issues[] = "❌ Archivo CSRF no encontrado";
            return;
        }
        
        $indexContent = file_get_contents($indexFile);
        if (strpos($indexContent, 'dummy-csrf-token') !== false) {
            $this->issues[] = "❌ Token CSRF dummy todavía presente";
        } elseif (strpos($indexContent, 'CSRFProtection::getToken') !== false) {
            $this->passed[] = "✅ CSRF implementado correctamente";
        } else {
            $this->warnings[] = "⚠️ CSRF: verificar implementación manualmente";
        }
    }
    
    private function checkCORSConfiguration() 
    {
        echo "🌐 Verificando configuración CORS...\n";
        
        $corsFile = __DIR__ . '/cors.php';
        $apiFile = __DIR__ . '/../api_router.php';
        
        if (!file_exists($corsFile)) {
            $this->issues[] = "❌ Archivo CORS no encontrado";
            return;
        }
        
        $apiContent = file_get_contents($apiFile);
        if (strpos($apiContent, 'Access-Control-Allow-Origin: *') !== false) {
            $this->issues[] = "❌ CORS muy permisivo detectado";
        } elseif (strpos($apiContent, 'CORSConfig::applyHeaders') !== false) {
            $this->passed[] = "✅ CORS configurado de forma segura";
        } else {
            $this->warnings[] = "⚠️ CORS: verificar configuración manualmente";
        }
    }
    
    private function checkSessionSecurity() 
    {
        echo "🔐 Verificando seguridad de sesiones...\n";
        
        // Verificar configuración de sesiones
        $httponly = ini_get('session.cookie_httponly');
        $secure = ini_get('session.cookie_secure');
        
        if (!$httponly) {
            $this->warnings[] = "⚠️ session.cookie_httponly no está activado";
        } else {
            $this->passed[] = "✅ Cookies de sesión HttpOnly activas";
        }
        
        if (!$secure && isset($_SERVER['HTTPS'])) {
            $this->warnings[] = "⚠️ session.cookie_secure no está activado en HTTPS";
        }
    }
    
    private function checkFilePermissions() 
    {
        echo "📋 Verificando permisos de archivos...\n";
        
        $sensitiveFiles = [
            __DIR__ . '/../.env',
            __DIR__ . '/../config/',
            __DIR__ . '/../logs/'
        ];
        
        foreach ($sensitiveFiles as $file) {
            if (file_exists($file)) {
                $perms = fileperms($file);
                $octal = substr(sprintf('%o', $perms), -4);
                
                if ($octal > '0644') {
                    $this->warnings[] = "⚠️ Permisos amplios en: $file ($octal)";
                } else {
                    $this->passed[] = "✅ Permisos correctos: " . basename($file);
                }
            }
        }
    }
    
    private function checkEnvironmentConfig() 
    {
        echo "⚙️ Verificando configuración de entorno...\n";
        
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            
            if (strpos($envContent, 'APP_DEBUG=true') !== false) {
                $this->warnings[] = "⚠️ Debug activado en .env";
            }
            
            if (strpos($envContent, 'DB_PASSWORD=') !== false && 
                strpos($envContent, 'DB_PASSWORD=""') === false &&
                strpos($envContent, 'DB_PASSWORD=') !== false) {
                $this->passed[] = "✅ Contraseña de BD configurada";
            } else {
                $this->warnings[] = "⚠️ Contraseña de BD vacía";
            }
        } else {
            $this->warnings[] = "⚠️ Archivo .env no encontrado";
        }
    }
    
    private function showResults() 
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 RESULTADOS DE LA VERIFICACIÓN\n";
        echo str_repeat("=", 50) . "\n\n";
        
        if (!empty($this->passed)) {
            echo "✅ CORRECTO (" . count($this->passed) . "):\n";
            foreach ($this->passed as $item) {
                echo "   $item\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️ ADVERTENCIAS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $item) {
                echo "   $item\n";
            }
            echo "\n";
        }
        
        if (!empty($this->issues)) {
            echo "❌ PROBLEMAS CRÍTICOS (" . count($this->issues) . "):\n";
            foreach ($this->issues as $item) {
                echo "   $item\n";
            }
            echo "\n";
        }
        
        // Evaluación general
        $totalChecks = count($this->passed) + count($this->warnings) + count($this->issues);
        $score = (count($this->passed) / $totalChecks) * 100;
        
        echo "🎯 PUNTUACIÓN DE SEGURIDAD: " . round($score, 1) . "%\n";
        
        if (empty($this->issues)) {
            echo "🚀 ESTADO: LISTO PARA PRODUCCIÓN\n";
        } else {
            echo "⛔ ESTADO: REQUIERE CORRECCIONES ANTES DE PRODUCCIÓN\n";
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
    }
}

// Ejecutar verificación si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $checker = new SecurityChecker();
    $checker->runAllChecks();
}
