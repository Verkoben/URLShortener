<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: chrome-extension://*');
header('Access-Control-Allow-Credentials: true');

require_once '../includes/config.php';
require_once '../includes/db.php';

// Este archivo se ejecuta JUNTO con my-urls.php
// La extensión llama a my-urls.php para importar, y podemos detectar eso

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Registrar que el usuario usó la extensión
$db = getDB();
$stmt = $db->prepare("
    UPDATE users 
    SET last_extension_sync = NOW() 
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);

// Aquí podrías hacer más cosas como:
// - Marcar URLs como "sincronizadas"
// - Registrar estadísticas
// - Etc.

echo json_encode(['success' => true]);
?>
