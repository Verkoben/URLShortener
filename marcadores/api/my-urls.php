<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

require_once '../conf.php';

// Función de autenticación combinada (sesión + token)
function authenticateUser($db) {
    // 1. Verificar sesión PHP
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return [
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'method' => 'session'
        ];
    }
    
    // 2. Verificar token en headers
    $headers = getallheaders();
    $token = null;
    
    // Authorization: Bearer TOKEN
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    // X-API-Token: TOKEN
    if (!$token && isset($headers['X-API-Token'])) {
        $token = $headers['X-API-Token'];
    }
    
    // Token en query string (menos seguro)
    if (!$token && isset($_GET['api_token'])) {
        $token = $_GET['api_token'];
    }
    
    if ($token) {
        try {
            $stmt = $db->prepare("
                SELECT user_id FROM api_tokens 
                WHERE token = ? AND is_active = 1
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch();
            
            if ($tokenData) {
                // Actualizar último uso
                $updateStmt = $db->prepare("UPDATE api_tokens SET last_used = NOW() WHERE token = ?");
                $updateStmt->execute([$token]);
                
                return [
                    'authenticated' => true,
                    'user_id' => $tokenData['user_id'],
                    'method' => 'token'
                ];
            }
        } catch (Exception $e) {
            // Log error si necesario
        }
    }
    
    return ['authenticated' => false];
}

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Autenticar usuario
    $auth = authenticateUser($db);
    
    if (!$auth['authenticated']) {
        http_response_code(401);
        echo json_encode([
            'error' => 'AUTHENTICATION_REQUIRED',
            'message' => 'User must be logged in to access URLs',
            'authenticated' => false,
            'data' => []
        ]);
        exit;
    }
    
    $user_id = $auth['user_id'];
    
    // Verificar que el usuario existe y está activo
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        http_response_code(401);
        echo json_encode([
            'error' => 'USER_INVALID',
            'message' => 'User not found or inactive',
            'authenticated' => false,
            'data' => []
        ]);
        exit;
    }
    
    // Obtener URLs del usuario
    $stmt = $db->prepare("
        SELECT u.short_code, u.original_url, u.clicks, u.created_at, cd.domain
        FROM urls u
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id
        WHERE u.user_id = ? AND u.active = 1
        ORDER BY u.created_at DESC
        LIMIT 500
    ");
    $stmt->execute([$user_id]);
    
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear URLs
    $formatted = array_map(function($url) {
        $domain = $url['domain'] ?? parse_url(BASE_URL, PHP_URL_HOST);
        return [
            'short_code' => $url['short_code'],
            'short_url' => 'https://' . $domain . '/' . $url['short_code'],
            'original_url' => $url['original_url'],
            'clicks' => (int)$url['clicks'],
            'created_at' => $url['created_at'],
            'domain' => $domain
        ];
    }, $urls);
    
    // Log de éxito
    $success_log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => 'my-urls.php',
        'user_id' => $user_id,
        'urls_returned' => count($formatted),
        'auth_method' => $auth['method'],
        'success' => true
    ];
    file_put_contents('my_urls_success.log', json_encode($success_log) . "\n", FILE_APPEND);
    
    echo json_encode($formatted);
    
} catch (Exception $e) {
    // Log de error
    $error_log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => 'my-urls.php',
        'error' => $e->getMessage(),
        'success' => false
    ];
    file_put_contents('my_urls_errors.log', json_encode($error_log) . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor', 'message' => $e->getMessage()]);
}
?>
