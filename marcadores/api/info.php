<?php
include_once __DIR__ . '/log.php';
// api/info.php - Información del usuario
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

session_start();
require_once '../conf.php';

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Conectar a BD
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

// Obtener información del usuario
$user_id = $_SESSION['user_id'] ?? 1;

try {
    // Estadísticas del usuario
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_urls, 
               COALESCE(SUM(clicks), 0) as total_clicks 
        FROM urls 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Responder con información
    echo json_encode([
        'user' => [
            'id' => $user_id,
            'username' => $_SESSION['username'] ?? 'Usuario',
            'role' => $_SESSION['role'] ?? 'user'
        ],
        'stats' => [
            'total_urls' => (int)$stats['total_urls'],
            'total_clicks' => (int)$stats['total_clicks']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch user info']);
}
?>
