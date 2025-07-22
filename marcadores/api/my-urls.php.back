<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// VERIFICACIÓN OBLIGATORIA DE AUTENTICACIÓN
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'AUTHENTICATION_REQUIRED',
        'message' => 'User must be logged in to access URLs',
        'authenticated' => false,
        'data' => []
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Log de acceso (opcional, para debugging)
$access_log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'file' => 'my-urls.php',
    'user_id' => $user_id,
    'method' => $_SERVER['REQUEST_METHOD'],
    'authenticated' => true
];
file_put_contents('my_urls_access.log', json_encode($access_log) . "\n", FILE_APPEND);

require_once '../conf.php';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar que el usuario existe y está activo
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        // Usuario no existe o está inactivo
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'error' => 'USER_INVALID',
            'message' => 'User not found or inactive',
            'authenticated' => false,
            'data' => []
        ]);
        exit;
    }
    
    // SOLO obtener URLs del usuario autenticado
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
        'success' => true
    ];
    file_put_contents('my_urls_success.log', json_encode($success_log) . "\n", FILE_APPEND);
    
    echo json_encode($formatted);
    
} catch (Exception $e) {
    // Log de error
    $error_log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => 'my-urls.php',
        'user_id' => $user_id,
        'error' => $e->getMessage(),
        'success' => false
    ];
    file_put_contents('my_urls_errors.log', json_encode($error_log) . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor', 'message' => $e->getMessage()]);
}
?>
