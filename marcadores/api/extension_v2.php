<?php
// API v2 - Sin dependencias problemáticas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Copiar aquí las constantes que encontraste
define('DB_HOST', 'localhost');
define('DB_NAME', 'url_shortener');
define('DB_USER', 'root');
define('DB_PASS', ''); // <- Poner la contraseña correcta

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
} catch (Exception $e) {
    die(json_encode(['error' => 'DB error']));
}

// Verificar API key
$apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE api_key = ?");
$stmt->execute([$apiKey]);
$user = $stmt->fetch();

echo json_encode([
    'success' => !!$user,
    'message' => $user ? 'OK' : 'Invalid API key',
    'user' => $user['username'] ?? null
]);
?>
