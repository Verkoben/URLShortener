<?php
// api/my-urls.php
session_start();

// Headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verificar login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Incluir config con ruta absoluta
require_once '/var/www/html/conf.php';

// Conexión DB
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=url_shortener;charset=utf8mb4",
        'root',
        'trapisonda'
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}

// Obtener URLs
try {
    $stmt = $pdo->prepare("
        SELECT short_code, original_url, title, clicks, created_at
        FROM urls 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($urls as $url) {
        $result[] = [
            'short_code' => $url['short_code'],
            'short_url' => 'https://0ln.eu/' . $url['short_code'],
            'original_url' => $url['original_url'],
            'title' => $url['title'] ?: 'Sin título',
            'clicks' => (int)$url['clicks'],
            'created_at' => $url['created_at']
        ];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query error']);
}
?>
