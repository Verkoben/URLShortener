<?php
// VERSIÓN MÍNIMA QUE FUNCIONA
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug
error_log("DELETE-URL called. Session: " . json_encode($_SESSION));

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

if (!$code) {
    echo json_encode(['success' => false, 'error' => 'No code']);
    exit;
}

// Conectar directamente
$pdo = new PDO("mysql:host=localhost;dbname=url_shortener", 'root', 'trapisonda');

// Si hay sesión, usar user_id. Si no, buscar el dueño
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // Con sesión: eliminar solo del usuario
    $stmt = $pdo->prepare("DELETE FROM urls WHERE short_code = ? AND user_id = ?");
    $result = $stmt->execute([$code, $user_id]);
    $deleted = $stmt->rowCount();
} else {
    // Sin sesión: buscar de quién es y eliminar
    $stmt = $pdo->prepare("SELECT user_id FROM urls WHERE short_code = ?");
    $stmt->execute([$code]);
    $owner = $stmt->fetch();
    
    if ($owner) {
        $stmt = $pdo->prepare("DELETE FROM urls WHERE short_code = ?");
        $result = $stmt->execute([$code]);
        $deleted = $stmt->rowCount();
    } else {
        $deleted = 0;
    }
}

error_log("DELETE-URL: Code=$code, Deleted=$deleted");

echo json_encode([
    'success' => $deleted > 0,
    'deleted' => $deleted,
    'code' => $code,
    'session_user' => $user_id
]);
?>
