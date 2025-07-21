<?php
// api/shorten.php - Crear URLs cortas via API
session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../conf.php';

// Función de autenticación (la misma que en my-urls.php)
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
                SELECT user_id, permissions FROM api_tokens 
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
                    'method' => 'token',
                    'permissions' => $tokenData['permissions'] ?? 'read'
                ];
            }
        } catch (Exception $e) {
            error_log("Token auth error: " . $e->getMessage());
        }
    }
    
    return ['authenticated' => false];
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only POST method is allowed'
    ]);
    exit;
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
            'message' => 'Authentication required to create short URLs'
        ]);
        exit;
    }
    
    // Verificar permisos si es token
    if ($auth['method'] === 'token' && $auth['permissions'] === 'read') {
        http_response_code(403);
        echo json_encode([
            'error' => 'INSUFFICIENT_PERMISSIONS',
            'message' => 'Token does not have write permissions'
        ]);
        exit;
    }
    
    $user_id = $auth['user_id'];
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $original_url = trim($input['url'] ?? $input['original_url'] ?? '');
    $custom_code = trim($input['custom_code'] ?? $input['code'] ?? '');
    $domain_id = isset($input['domain_id']) ? (int)$input['domain_id'] : null;
    
    // Validar URL
    if (empty($original_url)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'URL_REQUIRED',
            'message' => 'URL is required'
        ]);
        exit;
    }
    
    if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'INVALID_URL',
            'message' => 'Invalid URL format'
        ]);
        exit;
    }
    
    // Verificar permisos de dominio si se especificó uno
    if ($domain_id) {
        $stmt = $db->prepare("
            SELECT id FROM custom_domains 
            WHERE id = ? AND status = 'active' 
            AND (user_id = ? OR user_id IS NULL OR ? = 1)
        ");
        $stmt->execute([$domain_id, $user_id, $user_id]); // El tercer parámetro es para superadmin (user_id = 1)
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode([
                'error' => 'DOMAIN_ACCESS_DENIED',
                'message' => 'You do not have permission to use this domain'
            ]);
            exit;
        }
    }
    
    // Generar o validar código
// Generar o validar código
if (!empty($custom_code)) {
    // VALIDACIÓN ACTUALIZADA: incluye longitud máxima
    if (strlen($custom_code) > 100) {
        http_response_code(400);
        echo json_encode([
            'error' => 'CODE_TOO_LONG',
            'message' => 'Code cannot be longer than 100 characters'
        ]);
        exit;
    }
    
    // Validar formato del código personalizado
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $custom_code)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'INVALID_CODE_FORMAT',
            'message' => 'Code must contain only letters, numbers, hyphens and underscores'
        ]);
        exit;
    }
    
    // Verificar que no existe
    $stmt = $db->prepare("SELECT id FROM urls WHERE short_code = ?");
    $stmt->execute([$custom_code]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'error' => 'CODE_ALREADY_EXISTS',
            'message' => 'This short code is already in use'
        ]);
        exit;
    }
} else {
    // Generar código automáticamente (mantiene 6 caracteres)
    do {
        $custom_code = generateShortCode();
        $stmt = $db->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
        $stmt->execute([$custom_code]);
    } while ($stmt->fetchColumn() > 0);
}
    // Insertar URL
    $stmt = $db->prepare("
        INSERT INTO urls (short_code, original_url, user_id, domain_id, created_at, active) 
        VALUES (?, ?, ?, ?, NOW(), 1)
    ");
    $stmt->execute([$custom_code, $original_url, $user_id, $domain_id]);
    
    $url_id = $db->lastInsertId();
    
    // Obtener información del dominio si existe
    $domain = null;
    if ($domain_id) {
        $stmt = $db->prepare("SELECT domain FROM custom_domains WHERE id = ?");
        $stmt->execute([$domain_id]);
        $result = $stmt->fetch();
        $domain = $result['domain'] ?? null;
    }
    
    // Construir URL completa
    if ($domain) {
        $short_url = "https://" . $domain . "/" . $custom_code;
    } else {
        $short_url = rtrim(BASE_URL, '/') . '/' . $custom_code;
    }
    
    // Log de éxito
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'create_url',
        'user_id' => $user_id,
        'url_id' => $url_id,
        'short_code' => $custom_code,
        'auth_method' => $auth['method']
    ];
    file_put_contents('api_activity.log', json_encode($log) . "\n", FILE_APPEND);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'id' => $url_id,
        'short_code' => $custom_code,
        'short_url' => $short_url,
        'original_url' => $original_url,
        'domain' => $domain ?? parse_url(BASE_URL, PHP_URL_HOST),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Log de error
    error_log("API Shorten Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred while creating the short URL'
    ]);
}
?>
