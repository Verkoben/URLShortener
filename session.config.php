<?php
// session_config.php - Sistema de sesiones persistentes

// Definir duración de la sesión (15 días en segundos)
define('SESSION_DURATION', 15 * 24 * 60 * 60); // 1,296,000 segundos

// Función para iniciar sesión segura y persistente
function initSecureSession() {
    if (session_status() == PHP_SESSION_NONE) {
        // Configurar PHP para sesiones largas
        ini_set('session.gc_maxlifetime', SESSION_DURATION);
        ini_set('session.cookie_lifetime', SESSION_DURATION);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        
        // Si usas HTTPS (recomendado)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Configurar parámetros de la cookie de sesión
        session_set_cookie_params([
            'lifetime' => SESSION_DURATION,
            'path' => '/',
            'domain' => '', // Dejar vacío para usar el dominio actual
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // Directorio personalizado para sesiones (evita que el hosting las borre)
        $session_dir = __DIR__ . '/sessions';
        if (!file_exists($session_dir)) {
            mkdir($session_dir, 0777, true);
            // Crear archivo .htaccess para proteger el directorio
            file_put_contents($session_dir . '/.htaccess', 'Deny from all');
        }
        session_save_path($session_dir);
        
        // Iniciar sesión
        session_start();
        
        // Verificar y actualizar tiempo de actividad
        if (isset($_SESSION['admin_logged_in'])) {
            if (!isset($_SESSION['last_activity'])) {
                $_SESSION['last_activity'] = time();
            } else {
                // Verificar si ha pasado el tiempo límite
                if (time() - $_SESSION['last_activity'] > SESSION_DURATION) {
                    // Sesión expirada
                    session_destroy();
                    session_start();
                    return false;
                } else {
                    // Actualizar tiempo de actividad
                    $_SESSION['last_activity'] = time();
                }
            }
        }
    }
    return true;
}

// Sistema de "Remember Me" con cookies adicionales
function createRememberMeCookie($username) {
    $token = bin2hex(random_bytes(32));
    $expire = time() + SESSION_DURATION;
    
    // Crear cookie de remember me
    setcookie(
        'remember_me', 
        $token, 
        $expire, 
        '/', 
        '', 
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 
        true
    );
    
    // Guardar en archivo o base de datos
    $tokens_file = __DIR__ . '/sessions/remember_tokens.json';
    $tokens = [];
    
    if (file_exists($tokens_file)) {
        $tokens = json_decode(file_get_contents($tokens_file), true) ?: [];
    }
    
    // Limpiar tokens expirados
    foreach ($tokens as $user => $data) {
        if ($data['expire'] < time()) {
            unset($tokens[$user]);
        }
    }
    
    // Añadir nuevo token
    $tokens[$username] = [
        'token' => password_hash($token, PASSWORD_DEFAULT),
        'expire' => $expire
    ];
    
    file_put_contents($tokens_file, json_encode($tokens));
}

// Verificar cookie remember me
function checkRememberMeCookie() {
    if (!isset($_SESSION['admin_logged_in']) && isset($_COOKIE['remember_me'])) {
        $tokens_file = __DIR__ . '/sessions/remember_tokens.json';
        
        if (file_exists($tokens_file)) {
            $tokens = json_decode(file_get_contents($tokens_file), true) ?: [];
            
            foreach ($tokens as $username => $data) {
                if ($data['expire'] > time() && 
                    password_verify($_COOKIE['remember_me'], $data['token'])) {
                    // Cookie válida, iniciar sesión
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['last_activity'] = time();
                    return true;
                }
            }
        }
    }
    return false;
}

// Función para hacer login
function doLogin($username, $password) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        
        // Crear cookie remember me
        createRememberMeCookie($username);
        
        return true;
    }
    return false;
}

// Función para verificar si está logueado
function isLoggedIn() {
    initSecureSession();
    
    // Primero verificar sesión normal
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }
    
    // Si no, verificar cookie remember me
    return checkRememberMeCookie();
}

// Función para logout
function doLogout() {
    // Destruir sesión
    $_SESSION = array();
    
    // Borrar cookie de sesión
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Borrar cookie remember me
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
        
        // Eliminar token del archivo
        $tokens_file = __DIR__ . '/sessions/remember_tokens.json';
        if (file_exists($tokens_file) && isset($_SESSION['admin_username'])) {
            $tokens = json_decode(file_get_contents($tokens_file), true) ?: [];
            unset($tokens[$_SESSION['admin_username']]);
            file_put_contents($tokens_file, json_encode($tokens));
        }
    }
    
    session_destroy();
}
?>
