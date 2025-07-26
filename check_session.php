<?php
// check_session.php - Para incluir en todas las páginas protegidas
require_once 'conf.php';

// Función para inicializar sesión segura
function initSecureSession() {
    if (session_status() == PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', 1296000); // 15 días
        ini_set('session.cookie_lifetime', 1296000); // 15 días
        session_start();
    }
}

// Función mejorada para verificar login con tu sistema
function checkUserLogin() {
    // Inicializar sesión segura
    initSecureSession();
    
    // Verificar si hay sesión activa
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        // Actualizar actividad
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // Si no hay sesión, verificar cookie remember_token
    if (isset($_COOKIE['remember_token'])) {
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $token = $_COOKIE['remember_token'];
            
            // Buscar en la tabla users directamente
            $stmt = $db->prepare("
                SELECT * 
                FROM users
                WHERE remember_token = ? 
                AND token_expires > NOW()
                AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Auto-login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['logged_in'] = true;
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Renovar el token por otros 15 días
                $newToken = bin2hex(random_bytes(32));
                $expires = time() + (15 * 24 * 60 * 60);
                
                $stmt = $db->prepare("UPDATE users SET remember_token = ?, token_expires = FROM_UNIXTIME(?) WHERE id = ?");
                $stmt->execute([$newToken, $expires, $user['id']]);
                
                // Actualizar cookie
                setcookie('remember_token', $newToken, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                return true;
            } else {
                // Token inválido, eliminar cookie
                setcookie('remember_token', '', time() - 3600, '/');
            }
        } catch (Exception $e) {
            error_log("Check session error: " . $e->getMessage());
        }
    }
    
    return false;
}

// Función para compatibilidad con el sistema simple
function isLoggedIn() {
    return checkUserLogin();
}

// Función auxiliar si la necesitas
function checkRememberMeCookie() {
    return false; // Ya está integrado arriba
}
?>
