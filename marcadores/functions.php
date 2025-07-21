<?php
// functions.php - Funciones para marcadores (SIN DUPLICADOS)
require_once 'config.php';

/**
 * Obtener ID del usuario actual
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            return (int)$_SESSION['user_id'];
        }
        
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return 1;
        }
        
        return null;
    }
}

/**
 * Obtener información del usuario actual
 */
if (!function_exists('getCurrentUserInfo')) {
    function getCurrentUserInfo() {
        $userId = getCurrentUserId();
        if (!$userId) {
            return null;
        }
        
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, created_at, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                return $user;
            }
            
            // FALLBACK: Si no existe en users, crear entrada básica
            if ($userId == 1) {
                return [
                    'id' => 1,
                    'username' => $_SESSION['username'] ?? 'admin',
                    'email' => 'admin@localhost',
                    'created_at' => date('Y-m-d H:i:s'),
                    'role' => 'admin'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error getting user info: " . $e->getMessage());
        }
        
        return null;
    }
}

/**
 * Verificar si el usuario está autenticado
 */
if (!function_exists('isUserLoggedIn')) {
    function isUserLoggedIn() {
        return getCurrentUserId() !== null;
    }
}

/**
 * Verificar si es administrador
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        $userId = getCurrentUserId();
        return $userId === 1 || isset($_SESSION['admin_logged_in']);
    }
}

/**
 * Formatear fecha para mostrar
 */
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('d/m/Y H:i', strtotime($date));
    }
}

/**
 * Formatear números (clicks, etc)
 */
if (!function_exists('formatNumber')) {
    function formatNumber($number) {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return number_format($number);
    }
}

/**
 * Limpiar URL para mostrar
 */
if (!function_exists('cleanUrl')) {
    function cleanUrl($url, $maxLength = 50) {
        if (strlen($url) <= $maxLength) {
            return $url;
        }
        return substr($url, 0, $maxLength) . '...';
    }
}

/**
 * Verificar si una URL es válida
 */
if (!function_exists('isValidUrl')) {
    function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

/**
 * Obtener dominio de una URL
 */
if (!function_exists('getDomainFromUrl')) {
    function getDomainFromUrl($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }
}

/**
 * Logging de errores específico para marcadores
 */
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        $logMessage = date('Y-m-d H:i:s') . " [MARCADORES] " . $message;
        if (!empty($context)) {
            $logMessage .= " Context: " . json_encode($context);
        }
        error_log($logMessage);
    }
}

/**
 * Verificar permisos de usuario para una URL
 */
if (!function_exists('canUserAccessUrl')) {
    function canUserAccessUrl($urlId, $userId = null) {
        if (!$userId) {
            $userId = getCurrentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM urls WHERE id = ?");
            $stmt->execute([$urlId]);
            $url = $stmt->fetch();
            
            return $url && ($url['user_id'] == $userId || $userId == 1);
        } catch (Exception $e) {
            logError("Error checking URL access", ['url_id' => $urlId, 'user_id' => $userId]);
            return false;
        }
    }
}

/**
 * Sanitizar input para prevenir XSS
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validar código corto
 */
if (!function_exists('validateShortCode')) {
    function validateShortCode($code) {
        return preg_match('/^[a-zA-Z0-9-_]+$/', $code) && 
               strlen($code) >= 1 && 
               strlen($code) <= 100;
    }
}

/**
 * Obtener estadísticas básicas del usuario
 */
if (!function_exists('getUserBasicStats')) {
    function getUserBasicStats($userId) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_urls,
                    SUM(clicks) as total_clicks,
                    AVG(clicks) as avg_clicks,
                    MAX(clicks) as max_clicks
                FROM urls 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Error getting user stats", ['user_id' => $userId, 'error' => $e->getMessage()]);
            return ['total_urls' => 0, 'total_clicks' => 0, 'avg_clicks' => 0, 'max_clicks' => 0];
        }
    }
}

/**
 * Construir URL corta completa
 */
if (!function_exists('buildShortUrl')) {
    function buildShortUrl($shortCode, $customDomain = null) {
        if ($customDomain) {
            return "https://{$customDomain}/{$shortCode}";
        }
        
        // Usar dominio por defecto del conf.php
        $defaultDomain = defined('BASE_URL') ? parse_url(BASE_URL, PHP_URL_HOST) : '0ln.org';
        return "https://{$defaultDomain}/{$shortCode}";
    }
}

/**
 * Verificar límites de rate limiting (básico)
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit($userId, $action = 'general', $limit = 100, $window = 3600) {
        // Implementación básica usando archivos temporales
        $limitFile = sys_get_temp_dir() . "/rate_limit_{$userId}_{$action}";
        
        if (file_exists($limitFile)) {
            $data = json_decode(file_get_contents($limitFile), true);
            $now = time();
            
            // Limpiar requests antiguos
            $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
            
            // Verificar límite
            if (count($data['requests']) >= $limit) {
                return false;
            }
            
            // Agregar request actual
            $data['requests'][] = $now;
        } else {
            $data = ['requests' => [time()]];
        }
        
        file_put_contents($limitFile, json_encode($data));
        return true;
    }
}

/**
 * Generar ID de sesión único para analytics
 */
if (!function_exists('generateSessionId')) {
    function generateSessionId() {
        if (!isset($_SESSION['analytics_session_id'])) {
            $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['analytics_session_id'];
    }
}

/**
 * Debug: Mostrar información de sesión (solo en desarrollo)
 */
if (!function_exists('debugSessionInfo')) {
    function debugSessionInfo() {
        if (defined('MARCADORES_DEBUG') && MARCADORES_DEBUG && isset($_GET['debug_session'])) {
            echo "<div style='background:#f0f0f0;padding:10px;margin:10px;border:1px solid #ccc;font-family:monospace;'>";
            echo "<h4>🔍 DEBUG SESIÓN:</h4>";
            echo "<strong>User ID:</strong> " . (getCurrentUserId() ?? 'NULL') . "<br>";
            echo "<strong>Username:</strong> " . ($_SESSION['username'] ?? 'NO SET') . "<br>";
            echo "<strong>Admin:</strong> " . ($_SESSION['admin_logged_in'] ?? 'NO SET') . "<br>";
            echo "<strong>Session ID:</strong> " . session_id() . "<br>";
            echo "<details><summary>Toda la sesión:</summary><pre>";
            print_r($_SESSION);
            echo "</pre></details>";
            echo "</div>";
        }
    }
}

// Log de carga exitosa
if (function_exists('logDebug')) {
    logDebug("Functions.php loaded successfully", [
        'functions_count' => count(get_defined_functions()['user']),
        'memory_usage' => memory_get_usage(true)
    ]);
}
?>
