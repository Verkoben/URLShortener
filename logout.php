<?php
session_start();
require_once 'conf.php';

// Si hay usuario logueado, limpiar su token
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Limpiar remember token
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Continuar con el logout aunque falle la BD
    }
}

// Destruir sesión
session_destroy();

// Eliminar cookie remember_token
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirigir a login
header('Location: login.php');
exit();
?>
