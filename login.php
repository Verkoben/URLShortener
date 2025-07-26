<?php
session_start();
require_once 'conf.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false; // Checkbox "Recordarme"
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Buscar usuario
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ? AND active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login exitoso
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['last_activity'] = time();
            
            // Si marcó "Recordarme", crear cookie persistente
            if ($remember) {
                // Generar token único
                $token = bin2hex(random_bytes(32));
                $expires = time() + (15 * 24 * 60 * 60); // 15 días
                
                // Guardar token en la base de datos
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expires = FROM_UNIXTIME(?) WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                // Crear cookie segura
                setcookie('remember_token', $token, [
                    'expires' => $expires,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true, // Solo HTTPS
                    'httponly' => true, // No accesible por JavaScript
                    'samesite' => 'Lax'
                ]);
            }
            
            // Registrar actividad de login
            $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, login_time) VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } catch (PDOException $e) {
        $error = 'Error al conectar con la base de datos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - URL Shortener</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group.checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group.checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-group.checkbox label {
            margin-bottom: 0;
            cursor: pointer;
            user-select: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-login:hover {
            background: #5a67d8;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: #667eea;
            border-radius: 10px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">URL</div>
            <h1>Iniciar Sesión</h1>
            <p>Accede a tu panel de control</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group checkbox">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">Mantener sesión iniciada por 15 días</label>
            </div>
            
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
