<?php
// track.php - Endpoint de tracking para redirect con analytics
require_once 'config.php';
require_once 'analytics.php';

// Obtener código de la URL
$shortCode = $_GET['code'] ?? '';
$shortCode = trim($shortCode);

if (empty($shortCode)) {
    http_response_code(404);
    die('Código no encontrado');
}

try {
    // Buscar URL en base de datos
    $stmt = $pdo->prepare("
        SELECT u.*, cd.domain 
        FROM urls u 
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
        WHERE u.short_code = ? AND u.active = 1
    ");
    $stmt->execute([$shortCode]);
    $url = $stmt->fetch();
    
    if (!$url) {
        http_response_code(404);
        include '404.html'; // Crear página 404 personalizada
        exit;
    }
    
    // Track del click con analytics
    $analytics->trackClick($url['id'], $shortCode, $url['user_id']);
    
    // Redirect a URL original
    header("Location: " . $url['original_url'], true, 302);
    exit;
    
} catch (Exception $e) {
    error_log("Error en track.php: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}
?>
