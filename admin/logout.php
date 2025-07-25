<?php
// logout.php - Cerrar sesión de forma segura
// Iniciar sesión para poder destruirla
session_start();

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit();
?>
