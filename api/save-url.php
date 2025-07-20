<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: chrome-extension://*');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$response = ['success' => false];

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No autenticado');
    }
    
    // Obtener datos
    $input = json_decode(file_get_contents('php://input'), true);
    $shortUrl = trim($input['short_url'] ?? '');
    $originalUrl = $input['original_url'] ?? null;
    $title = $input['title'] ?? null;
    
    if (!$shortUrl) {
        throw new Exception('URL corta requerida');
    }
    
    // Extraer el código y dominio de la URL corta
    $urlParts = parse_url($shortUrl);
    $domain = $urlParts['host'] ?? '';
    $shortCode = trim($urlParts['path'] ?? '', '/');
    
    if (!$shortCode) {
        throw new Exception('Código no válido');
    }
    
    // Verificar que el dominio sea válido
    $validDomains = ['0ln.eu', 'www.0ln.eu', $_SERVER['HTTP_HOST']];
    if (!in_array($domain, $validDomains)) {
        throw new Exception('Dominio no permitido');
    }
    
    $db = getDB();
    
    // Verificar si ya existe
    $stmt = $db->prepare("SELECT id, user_id FROM urls WHERE short_code = ?");
    $stmt->execute([$shortCode]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Si ya existe y es del mismo usuario, actualizar
        if ($existing['user_id'] == $_SESSION['user_id']) {
            if ($originalUrl) {
                $stmt = $db->prepare("
                    UPDATE urls 
                    SET original_url = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$originalUrl, $existing['id']]);
            }
            $response['success'] = true;
            $response['message'] = 'URL actualizada';
        } else {
            // Si es de otro usuario, crear una "referencia" o denegar
            throw new Exception('Esta URL pertenece a otro usuario');
        }
    } else {
        // No existe, intentar crearla
        // Determinar si es código personalizado (más de 6 caracteres normalmente)
        $isCustom = strlen($shortCode) > 6;
        
        // Insertar nueva URL
        $stmt = $db->prepare("
            INSERT INTO urls (user_id, short_code, is_custom, original_url, created_at, active) 
            VALUES (?, ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $shortCode,
            $isCustom,
            $originalUrl
        ]);
        
        $response['success'] = true;
        $response['message'] = 'URL guardada en el servidor';
        $response['data'] = [
            'id' => $db->lastInsertId(),
            'short_code' => $shortCode
        ];
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Error en save-url.php: " . $e->getMessage());
}

echo json_encode($response);
?>
