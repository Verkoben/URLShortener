<?php
// api/extension_simple.php - Versión que usa conf.php existente

// Evitar verificación de sesión
define('SKIP_AUTH', true);
define('API_MODE', true);

// Headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Incluir configuración principal
require_once '/var/www/html/conf.php';

// Verificar conexión
if (!isset($mysql)) {
    // Si usa PDO
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'message' => 'No hay conexión DB']));
    }
} else {
    // Si usa mysqli, convertir a PDO
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=url_shortener;charset=utf8mb4",
            'root',
            '' // Ajustar según tu configuración
        );
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'message' => 'Error DB']));
    }
}

// Resto del código
$apiKey = $_GET['api_key'] ?? '';

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE api_key = ?");
$stmt->execute([$apiKey]);
$user = $stmt->fetch();

if (!$user) {
    die(json_encode(['success' => false, 'message' => 'API key inválida']));
}

echo json_encode([
    'success' => true,
    'message' => 'API funcionando',
    'user' => $user['username']
]);
?>
