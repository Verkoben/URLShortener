<?php
// API Extension - Versión completa funcional
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Conexión directa con la contraseña correcta
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=url_shortener;charset=utf8mb4',
        'root',
        'trapisonda'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Función de respuesta
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Obtener API key
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$apiKey = $headers['x-api-key'] ?? $_GET['api_key'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    sendResponse(false, 'API key required');
}

// Verificar usuario
try {
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        sendResponse(false, 'Invalid API key');
    }
} catch (Exception $e) {
    http_response_code(500);
    sendResponse(false, 'Auth error');
}

// Router
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        try {
            // Verificar si existe la tabla bookmarks
            $tables = $pdo->query("SHOW TABLES LIKE 'bookmarks'")->fetch();
            
            if (!$tables) {
                // Crear la tabla si no existe
                $pdo->exec("
                    CREATE TABLE bookmarks (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        url VARCHAR(2048) NOT NULL,
                        title VARCHAR(255),
                        description TEXT,
                        tags VARCHAR(500),
                        category VARCHAR(100) DEFAULT 'general',
                        is_favorite BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id)
                    )
                ");
            }
            
            // Obtener bookmarks
            $stmt = $pdo->prepare("
                SELECT id, url, title, description, tags, category, is_favorite, created_at
                FROM bookmarks 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 100
            ");
            $stmt->execute([$user['id']]);
            $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse(true, 'Bookmarks retrieved', [
                'bookmarks' => $bookmarks,
                'total' => count($bookmarks)
            ]);
            
        } catch (Exception $e) {
            sendResponse(true, 'Bookmarks retrieved', [
                'bookmarks' => [],
                'total' => 0
            ]);
        }
        break;
        
    case 'add':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['url'])) {
            sendResponse(false, 'URL required');
        }
        
        try {
            // Crear tabla si no existe
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS bookmarks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    url VARCHAR(2048) NOT NULL,
                    title VARCHAR(255),
                    description TEXT,
                    tags VARCHAR(500),
                    category VARCHAR(100) DEFAULT 'general',
                    is_favorite BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id)
                )
            ");
            
            // Insertar bookmark
            $stmt = $pdo->prepare("
                INSERT INTO bookmarks (user_id, url, title, description, tags, category)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user['id'],
                $input['url'],
                $input['title'] ?? parse_url($input['url'], PHP_URL_HOST),
                $input['description'] ?? '',
                $input['tags'] ?? '',
                $input['category'] ?? 'general'
            ]);
            
            sendResponse(true, 'Bookmark created', [
                'id' => $pdo->lastInsertId()
            ]);
            
        } catch (Exception $e) {
            sendResponse(false, 'Error: ' . $e->getMessage());
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            sendResponse(false, 'ID required');
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user['id']]);
            
            sendResponse(true, 'Bookmark deleted');
        } catch (Exception $e) {
            sendResponse(false, 'Error: ' . $e->getMessage());
        }
        break;
        
    default:
        sendResponse(false, 'Unknown action: ' . $action);
}
?>
