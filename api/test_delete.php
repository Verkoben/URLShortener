<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

$input = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'NO USER',
    'code_received' => $input['code'] ?? 'NO CODE',
    'method' => $_SERVER['REQUEST_METHOD'],
    'cookies' => $_COOKIE
]);
?>
