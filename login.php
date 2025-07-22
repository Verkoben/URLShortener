<?php
session_start();
require_once '../conf.php';

// SEGURIDAD: Verificar que se está accediendo desde el dominio principal
$allowed_domain = parse_url(BASE_URL, PHP_URL_HOST);
$current_domain = $_SERVER['HTTP_HOST'];

// Si no está accediendo desde el dominio principal, denegar acceso
if ($current_domain !== $allowed_domain) {
    // Redirigir al dominio principal con mensaje
    $main_login_url = rtrim(BASE_URL, '/') . '/admin/login.php';
    header("Location: $main_login_url?error=wrong_domain");
    exit();
}

// Si ya está logueado, redirigir al panel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: panel_simple.php');
    exit();
}

// Conexión a la base de datos
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$error = '';
$success = '';
$show_register = isset($_GET['register']);

// Verificar si hay mensaje de error por dominio incorrecto
if (isset($_GET['error']) && $_GET['error'] === 'wrong_domain') {
    $error = '⚠️ Por seguridad, el acceso al panel solo está permitido desde el dominio principal.';
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // DOBLE VERIFICACIÓN: Asegurar que sigue siendo el dominio correcto
    if ($current_domain !== $allowed_domain) {
        $error = '❌ Acceso denegado. Use el dominio principal.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error = 'Por favor, completa todos los campos';
        } else {
            try {
                // Verificar credenciales
                $stmt = $db->prepare("SELECT id, username, password, role, status FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                    } else {
                        // Login exitoso
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Registrar el login
                        try {
                            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $stmt->execute([$user['id']]);
                        } catch (Exception $e) {
                            // Ignorar si falla
                        }
                        
                        // Redirigir según el rol
                        if ($user['role'] === 'admin') {
                            header('Location: panel_simple.php');
                        } else {
                            header('Location: ../index.php');
                        }
                        exit();
                    }
                } else {
                    $error = 'Usuario o contraseña incorrectos';
                }
            } catch (PDOException $e) {
                $error = 'Error al procesar el login: ' . $e->getMessage();
            }
        }
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // VERIFICACIÓN: No permitir registro desde dominios personalizados
    if ($current_domain !== $allowed_domain) {
        $error = '❌ El registro solo está permitido desde el dominio principal.';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        // Validaciones
        if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
            $error = 'Por favor, completa todos los campos';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no es válido';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden';
        } else {
            try {
                // Verificar si el usuario ya existe
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'El usuario o email ya existe';
                } else {
                    // Crear el usuario
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, role, status, created_at) 
                        VALUES (?, ?, ?, 'user', 'active', NOW())
                    ");
                    $stmt->execute([$username, $email, $hashed_password]);
                    
                    $success = '✅ Cuenta creada exitosamente. Ya puedes iniciar sesión.';
                    $show_register = false;
                }
            } catch (PDOException $e) {
                $error = 'Error al crear la cuenta: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_register ? 'Registro' : 'Login'; ?> - URL Shortener</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 0.95em;
        }
        
        .logo {
            font-size: 3em;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 0.95em;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #adb5bd;
            font-size: 0.9em;
        }
        
        .switch-form {
            text-align: center;
            margin-top: 20px;
        }
        
        .switch-form a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .switch-form a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9em;
        }
        
        .back-link a:hover {
            color: #495057;
        }
        
        .password-requirements {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .password-requirements ul {
            margin: 5px 0 0 20px;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            vertical-align: middle;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .domain-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9em;
        }
        
        .domain-warning strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .domain-info {
            background: #e0e7ff;
            color: #3730a3;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85em;
            text-align: center;
            margin-bottom: 20px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .login-header h2 {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">🔐</div>
            <h2><?php echo $show_register ? 'Crear Cuenta' : 'Iniciar Sesión'; ?></h2>
            <p>Accede al panel de administración</p>
        </div>
        
        <!-- Información del dominio actual -->
        <div class="domain-info">
            🌐 Dominio: <?php echo htmlspecialchars($current_domain); ?>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$show_register): ?>
        <!-- Formulario de Login -->
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="login" value="1">
            
            <div class="form-group">
                <label class="form-label">Usuario</label>
                <input type="text" name="username" class="form-control" 
                       placeholder="Tu nombre de usuario" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" 
                       placeholder="Tu contraseña" required>
            </div>
            
            <button type="submit" class="btn btn-primary" id="loginBtn">
                Iniciar Sesión
            </button>
        </form>
        
        <div class="divider">— o —</div>
        
        <div class="switch-form">
            ¿No tienes cuenta? <a href="?register=1">Regístrate aquí</a>
        </div>
        <?php else: ?>
        <!-- Formulario de Registro -->
        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="register" value="1">
            
            <div class="form-group">
                <label class="form-label">Usuario</label>
                <input type="text" name="username" class="form-control" 
                       placeholder="Elige un nombre de usuario" required autofocus
                       pattern="[a-zA-Z0-9_-]{3,20}"
                       title="3-20 caracteres, solo letras, números, guiones y guiones bajos">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" 
                       placeholder="tu@email.com" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" 
                       placeholder="Mínimo 6 caracteres" required minlength="6">
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirmar Contraseña</label>
                <input type="password" name="password_confirm" class="form-control" 
                       placeholder="Repite la contraseña" required>
            </div>
            
            <div class="password-requirements">
                <strong>Requisitos de la contraseña:</strong>
                <ul>
                    <li>Mínimo 6 caracteres</li>
                    <li>Recomendamos usar mayúsculas, minúsculas y números</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary" id="registerBtn">
                Crear Cuenta
            </button>
        </form>
        
        <div class="divider">— o —</div>
        
        <div class="switch-form">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
        </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="../">← Volver al inicio</a>
        </div>
    </div>
    
    <script>
        // Prevenir doble envío del formulario
        document.getElementById('<?php echo $show_register ? 'registerForm' : 'loginForm'; ?>').addEventListener('submit', function(e) {
            const btn = document.getElementById('<?php echo $show_register ? 'registerBtn' : 'loginBtn'; ?>');
            btn.disabled = true;
            btn.innerHTML = '<?php echo $show_register ? 'Creando cuenta' : 'Iniciando sesión'; ?><span class="loading"></span>';
        });
        
        // Validación en tiempo real para registro
        <?php if ($show_register): ?>
        const password = document.querySelector('input[name="password"]');
        const passwordConfirm = document.querySelector('input[name="password_confirm"]');
        
        passwordConfirm.addEventListener('input', function() {
            if (this.value !== password.value) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
        
        password.addEventListener('input', function() {
            if (passwordConfirm.value && this.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('Las contraseñas no coinciden');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        });
        <?php endif; ?>
        
        // Mostrar advertencia si se detecta un intento desde dominio no permitido
        <?php if ($current_domain !== $allowed_domain): ?>
        setTimeout(function() {
            alert('⚠️ ADVERTENCIA: Estás intentando acceder desde un dominio no autorizado.\n\nSerás redirigido al dominio principal.');
            window.location.href = '<?php echo rtrim(BASE_URL, '/') . '/admin/login.php'; ?>';
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>
