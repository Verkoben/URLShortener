<?php
// admin/session_check.php - Verificación de sesión para todos los archivos
function checkSession() {
    // Configurar sesiones de 15 días
    ini_set('session.gc_maxlifetime', 1296000);
    ini_set('session.cookie_lifetime', 1296000);
    
    // Iniciar sesión si no está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si está logueado
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
    
    // Extender la cookie en cada visita
    setcookie(session_name(), session_id(), time() + 1296000, '/');
    
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
}

// Llamar automáticamente a la función
checkSession();
?>
