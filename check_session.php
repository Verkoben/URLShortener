<?php
// check_session.php - Para incluir en todas las páginas protegidas

require_once 'conf.php';

// Función mejorada para verificar login con tu sistema
function checkUserLogin() {
    // Inicializar sesión segura
    initSecureSession();
    
    // Verificar si hay sesión activa
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        // Verificar tiempo de inactividad
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_DURATION) {
                // Sesión expirada
                session_destroy();
                return false;
            }
            // Actualizar actividad
            $_SESSION['last_activity'] = time();
        }
        return true;
    }
    
    // Si no hay sesión, verificar cookie remember me
    if (isset($_COOKIE['remember_token'])) {
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $token = $_COOKIE['remember_token'];
            $hashed_token = hash('sha256', $token);
            
            $stmt = $db->prepare("
                SELECT u.* 
                FROM users u
                JOIN remember_tokens rt ON u.id = rt.user_id
                WHERE rt.token = ? 
                AND rt.expires_at > NOW()
                AND u.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$hashed_token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Auto-login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Check session error: " . $e->getMessage());
        }
    }
    
    // También verificar el sistema simple de admin
    if (checkRememberMeCookie()) {
        return true;
    }
    
    return false;
}

// Función para compatibilidad con el sistema simple
function isLoggedIn() {
    return checkUserLogin();
}
?>
