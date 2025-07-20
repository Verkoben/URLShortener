<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: chrome-extension://*');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight de CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/db.php';

$response = ['success' => false];

try {
    // Verificar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No autenticado');
    }
    
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    $shortCode = trim($input['code'] ?? '');
    
    if (empty($shortCode)) {
        throw new Exception('Código no proporcionado');
    }
    
    // Sanitizar el código
    $shortCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $shortCode);
    
    // Conectar a la base de datos
    $db = getDB();
    
    // Verificar que la URL existe y pertenece al usuario
    $stmt = $db->prepare("
        SELECT id, user_id 
        FROM urls 
        WHERE short_code = ? AND active = 1
    ");
    $stmt->execute([$shortCode]);
    $url = $stmt->fetch();
    
    if (!$url) {
        throw new Exception('URL no encontrada');
    }
    
    // Verificar permisos - el usuario debe ser el dueño o admin
    if ($url['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        throw new Exception('No tienes permiso para eliminar esta URL');
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Eliminar estadísticas asociadas
        $stmt = $db->prepare("DELETE FROM click_stats WHERE url_id = ?");
        $stmt->execute([$url['id']]);
        
        // Opción 1: Eliminar físicamente
        $stmt = $db->prepare("DELETE FROM urls WHERE id = ?");
        $stmt->execute([$url['id']]);
        
        // Opción 2: Eliminar lógicamente (recomendado)
        // $stmt = $db->prepare("UPDATE urls SET active = 0, deleted_at = NOW() WHERE id = ?");
        // $stmt->execute([$url['id']]);
        
        // Confirmar transacción
        $db->commit();
        
        $response['success'] = true;
        $response['message'] = 'URL eliminada correctamente';
        
        // Log de actividad (opcional)
        logUserActivity('delete_url', [
            'user_id' => $_SESSION['user_id'],
            'short_code' => $shortCode,
            'url_id' => $url['id']
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('Error al eliminar la URL');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    
    // Log de errores
    error_log("Error en delete-url.php: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response);

// Función auxiliar para registrar actividad (opcional)
function logUserActivity($action, $data) {
    global $db;
    
    try {
        // Si tienes una tabla de logs
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, data, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['user_id'],
            $action,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silenciar errores de log para no afectar la operación principal
        error_log("Error al registrar actividad: " . $e->getMessage());
    }
}
?>
