<?php
// auth_check.php - Incluir al inicio de cada página protegida
session_start();
require_once 'conf.php';

function checkAuth() {
    // Si ya está autenticado por sesión
    if (isset($_SESSION['user_id'])) {
        // Verificar timeout de inactividad (opcional)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
            // Última actividad hace más de 1 hora, pero mantener si tiene cookie
            if (!isset($_COOKIE['remember_token'])) {
                session_destroy();
                header('Location: login.php');
                exit();
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // Si no hay sesión, verificar cookie remember_token
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Buscar usuario con este token válido
            $stmt = $pdo->prepare("
                SELECT id, username 
                FROM users 
                WHERE remember_token = ? 
                AND token_expires > NOW() 
                AND active = 1
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Token válido, restaurar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                
                // Renovar el token por otros 15 días
                $newToken = bin2hex(random_bytes(32));
                $expires = time() + (15 * 24 * 60 * 60);
                
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expires = FROM_UNIXTIME(?) WHERE id = ?");
                $stmt->execute([$newToken, $expires, $user['id']]);
                
                // Actualizar cookie
                setcookie('remember_token', $newToken, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                return true;
            } else {
                // Token inválido o expirado, eliminar cookie
                setcookie('remember_token', '', time() - 3600, '/');
            }
        } catch (PDOException $e) {
            // Error de BD, continuar sin autenticación
        }
    }
    
    // No autenticado, redirigir a login
    header('Location: login.php');
    exit();
}

// Llamar a esta función
checkAuth();
?>
