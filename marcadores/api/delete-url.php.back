<?php
// api/delete-url.php - Eliminar URLs via API
session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
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

// Solo permitir DELETE o POST
if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
    http_response_code(405);
    echo json_encode([
        'error' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only DELETE or POST methods are allowed'
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
            'message' => 'Authentication required to delete URLs'
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
    
    // Obtener ID de la URL a eliminar
    $url_id = null;
    $short_code = null;
    
    // Primero intentar obtener del body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    // Buscar por ID o código
    $url_id = $input['id'] ?? $input['url_id'] ?? $_GET['id'] ?? null;
    $short_code = $input['code'] ?? $input['short_code'] ?? $_GET['code'] ?? null;
    
    if (!$url_id && !$short_code) {
        http_response_code(400);
        echo json_encode([
            'error' => 'MISSING_IDENTIFIER',
            'message' => 'URL ID or short code is required'
        ]);
        exit;
    }
    
    // Buscar la URL
    if ($url_id) {
        $stmt = $db->prepare("
            SELECT id, short_code, user_id 
            FROM urls 
            WHERE id = ?
        ");
        $stmt->execute([$url_id]);
    } else {
        $stmt = $db->prepare("
            SELECT id, short_code, user_id 
            FROM urls 
            WHERE short_code = ?
        ");
        $stmt->execute([$short_code]);
    }
    
    $url = $stmt->fetch();
    
    if (!$url) {
        http_response_code(404);
        echo json_encode([
            'error' => 'URL_NOT_FOUND',
            'message' => 'URL not found'
        ]);
        exit;
    }
    
    // Verificar permisos (solo el dueño o admin puede eliminar)
    $is_admin = $user_id == 1; // Superadmin
    if ($url['user_id'] != $user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode([
            'error' => 'ACCESS_DENIED',
            'message' => 'You do not have permission to delete this URL'
        ]);
        exit;
    }
    
    // Eliminar estadísticas asociadas primero
    $stmt = $db->prepare("DELETE FROM click_stats WHERE url_id = ?");
    $stmt->execute([$url['id']]);
    
    // Eliminar la URL
    $stmt = $db->prepare("DELETE FROM urls WHERE id = ?");
    $stmt->execute([$url['id']]);
    
    if ($stmt->rowCount() == 0) {
        http_response_code(500);
        echo json_encode([
            'error' => 'DELETE_FAILED',
            'message' => 'Failed to delete URL'
        ]);
        exit;
    }
    
    // Log de éxito
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'delete_url',
        'user_id' => $user_id,
        'deleted_url_id' => $url['id'],
        'deleted_short_code' => $url['short_code'],
        'auth_method' => $auth['method']
    ];
    file_put_contents('api_activity.log', json_encode($log) . "\n", FILE_APPEND);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'URL deleted successfully',
        'deleted' => [
            'id' => $url['id'],
            'short_code' => $url['short_code']
        ]
    ]);
    
} catch (Exception $e) {
    // Log de error
    error_log("API Delete Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred while deleting the URL'
    ]);
}
?>
