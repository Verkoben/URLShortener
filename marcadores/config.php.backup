<?php
// config.php - Configuración para el sistema de marcadores
// Mostrar errores para debug (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuración principal
require_once '../conf.php';

// Configuración de zona horaria
date_default_timezone_set('Europe/Madrid');

// Conexión a la base de datos
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    error_log("Error de conexión DB en marcadores: " . $e->getMessage());
    die("Error de conexión a la base de datos. Contacta al administrador.");
}

// Headers de seguridad
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Configuraciones del sistema de marcadores
define('MARCADORES_VERSION', '1.0.0');
define('MARCADORES_DEBUG', true); // Cambiar a false en producción

// Configuraciones de paginación por defecto
define('DEFAULT_ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// Configuraciones de exportación
define('EXPORT_MAX_ITEMS', 10000);
define('EXPORT_FORMATS', ['html', 'csv', 'json']);

// Configuraciones de analytics
define('ANALYTICS_RETENTION_DAYS', 365);
define('ANALYTICS_BATCH_SIZE', 1000);

// Funciones de utilidad globales
if (!function_exists('h')) {
    /**
     * Escapar HTML para evitar XSS
     */
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('isAjaxRequest')) {
    /**
     * Verificar si es una petición AJAX
     */
    function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('jsonResponse')) {
    /**
     * Enviar respuesta JSON
     */
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('redirectTo')) {
    /**
     * Redireccionar con headers apropiados
     */
    function redirectTo($url, $statusCode = 302) {
        if (!headers_sent()) {
            header("Location: $url", true, $statusCode);
        } else {
            echo "<script>window.location.href='$url';</script>";
        }
        exit;
    }
}

if (!function_exists('csrfToken')) {
    /**
     * Generar token CSRF
     */
    function csrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Verificar token CSRF
     */
    function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Configuración de límites y validaciones
define('MAX_URL_LENGTH', 2048);
define('MAX_TITLE_LENGTH', 255);
define('MAX_SHORT_CODE_LENGTH', 100);
define('MIN_SHORT_CODE_LENGTH', 1);

// Patrones de validación
define('SHORT_CODE_PATTERN', '/^[a-zA-Z0-9-_]+$/');
define('EMAIL_PATTERN', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

// Configuraciones de cache (si se implementa)
define('CACHE_ENABLED', false);
define('CACHE_TTL', 3600);

// Logging personalizado
if (!function_exists('logDebug')) {
    /**
     * Log de debug específico para marcadores
     */
    function logDebug($message, $context = []) {
        if (MARCADORES_DEBUG) {
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            error_log("[$timestamp] [MARCADORES] $message$contextStr");
        }
    }
}

// Verificación de integridad del sistema
if (!function_exists('checkSystemIntegrity')) {
    /**
     * Verificar que el sistema esté correctamente configurado
     */
    function checkSystemIntegrity() {
        $checks = [];
        
        // Verificar conexión DB
        global $pdo;
        $checks['database'] = $pdo instanceof PDO;
        
        // Verificar tablas esenciales
        try {
            $tables = ['urls', 'users'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $checks["table_$table"] = $stmt->rowCount() > 0;
            }
        } catch (Exception $e) {
            $checks['tables'] = false;
        }
        
        // Verificar permisos de escritura (si es necesario)
        $checks['writable'] = is_writable(__DIR__);
        
        // Verificar extensiones PHP necesarias
        $checks['php_pdo'] = extension_loaded('pdo');
        $checks['php_pdo_mysql'] = extension_loaded('pdo_mysql');
        $checks['php_json'] = extension_loaded('json');
        $checks['php_mbstring'] = extension_loaded('mbstring');
        
        return $checks;
    }
}

// Auto-verificación en debug mode
if (MARCADORES_DEBUG && !isAjaxRequest()) {
    $integrity = checkSystemIntegrity();
    $failed = array_filter($integrity, function($check) { return !$check; });
    
    if (!empty($failed)) {
        logDebug("System integrity check failed", $failed);
    }
}

// Configuración de manejo de errores
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorMsg = "Error [$severity]: $message in $file on line $line";
    logDebug($errorMsg);
    
    // En producción, mostrar error genérico
    if (!MARCADORES_DEBUG) {
        $message = "Ha ocurrido un error interno. Contacta al administrador.";
    }
    
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Configuración de manejo de excepciones
set_exception_handler(function($exception) {
    $errorMsg = "Uncaught exception: " . $exception->getMessage() . 
                " in " . $exception->getFile() . 
                " on line " . $exception->getLine();
    
    logDebug($errorMsg);
    
    if (isAjaxRequest()) {
        jsonResponse([
            'success' => false,
            'message' => MARCADORES_DEBUG ? $exception->getMessage() : 'Error interno del servidor'
        ], 500);
    } else {
        if (MARCADORES_DEBUG) {
            echo "<h3>Error:</h3><pre>$errorMsg</pre>";
        } else {
            echo "<h3>Error interno del servidor</h3><p>Contacta al administrador.</p>";
        }
    }
});

// Registro de carga del sistema
logDebug("Marcadores system loaded successfully", [
    'version' => MARCADORES_VERSION,
    'php_version' => PHP_VERSION,
    'session_id' => session_id(),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Verificar que no haya output antes de headers (solo en debug)
if (MARCADORES_DEBUG && headers_sent($file, $line)) {
    logDebug("Headers already sent", ['file' => $file, 'line' => $line]);
}
?>
