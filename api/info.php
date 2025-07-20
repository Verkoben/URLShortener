<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';
require_once '../includes/db.php';

$code = $_GET['code'] ?? '';
$code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);

if (!$code) {
    echo json_encode(['error' => 'Código no válido']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT original_url, clicks 
    FROM urls 
    WHERE short_code = ? AND active = 1
");
$stmt->execute([$code]);
$url = $stmt->fetch();

if ($url) {
    echo json_encode([
        'original_url' => $url['original_url'],
        'clicks' => intval($url['clicks']),
        'title' => parse_url($url['original_url'], PHP_URL_HOST)
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'No encontrado']);
}
?>
