<?php
// api/extension_standalone.php - API independiente sin dependencias

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración directa
$db_config = [
    'host' => 'localhost',
    'dbname' => 'url_shortener',
    'user' => 'root',
    'pass' => 'TU_PASSWORD_MYSQL' // <-- CAMBIAR
];

// Conectar a DB
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['user'],
        $db_config['pass']
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

// Obtener API key
$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $_GET['api_key'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'API key requerida']);
    exit;
}

// Validar API key
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE api_key = ?");
$stmt->execute([$apiKey]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'API key inválida']);
    exit;
}

// Router simple
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        echo json_encode([
            'success' => true,
            'message' => 'API funcionando',
            'data' => [
                'bookmarks' => [],
                'total' => 0,
                'user' => $user['username']
            ]
        ]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Acción no encontrada']);
}
?>
