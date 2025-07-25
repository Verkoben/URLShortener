<?php
// admin/logout.php - Cerrar sesión de forma segura desde el panel admin
session_start();

// Limpiar remember token si existe
if (isset($_SESSION['user_id'])) {
    require_once '../conf.php'; // Nota: ../ porque está en subcarpeta
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Limpiar remember token en la BD
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Continuar con el logout aunque falle la BD
    }
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir la cookie remember_token
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php'); // Sin ../ porque login.php está en admin/
exit();
?>
