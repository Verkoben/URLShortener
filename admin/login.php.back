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
                // Buscar usuario en la tabla users
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                // Verificación especial para admin usando conf.php
                if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                    // Login con credenciales de conf.php
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $user ? $user['id'] : 1;
                    $_SESSION['username'] = ADMIN_USERNAME;
                    $_SESSION['role'] = 'admin';
                    
                    // Actualizar último login si existe en BD
                    if ($user) {
                        $db->exec("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                    }
                    
                    header('Location: panel_simple.php');
                    exit();
                } 
                // Para otros usuarios, usar la verificación normal de la BD
                elseif ($user && $user['username'] !== ADMIN_USERNAME && password_verify($password, $user['password'])) {
                    // Login normal para otros usuarios
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Actualizar último login
                    $db->exec("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                    
                    // Redirigir según el rol
                    if ($user['role'] === 'admin') {
                        header('Location: panel_simple.php');
                    } else {
                        header('Location: ../index.php?welcome=1');
                    }
                    exit();
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
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        // Si no se proporciona full_name, usar el username
        if (empty($full_name)) {
            $full_name = $username;
        }
        
        // Validaciones
        if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
            $error = 'Por favor, completa todos los campos obligatorios';
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
                    
                    // Verificar qué columnas existen en la tabla
                    $columns = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Construir la consulta dinámicamente
                    if (in_array('full_name', $columns)) {
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password, full_name, role, status, created_at) 
                            VALUES (?, ?, ?, ?, 'user', 'active', NOW())
                        ");
                        $stmt->execute([$username, $email, $hashed_password, $full_name]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password, role, status, created_at) 
                            VALUES (?, ?, ?, 'user', 'active', NOW())
                        ");
                        $stmt->execute([$username, $email, $hashed_password]);
                    }
                    
                    // Auto-login después del registro exitoso
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'user';
                    
                    // Redirigir al index con mensaje de bienvenida
                    header('Location: ../index.php?welcome=1');
                    exit();
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
    <title><?php echo $show_register ? 'Registro' : 'Login Admin'; ?> - URL Shortener</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Elementos decorativos animados */
        .bg-decoration {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }
        
        .decoration-1 {
            width: 300px;
            height: 300px;
            top: -150px;
            left: -150px;
            animation-delay: 0s;
        }
        
        .decoration-2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            right: -100px;
            animation-delay: 5s;
        }
        
        .decoration-3 {
            width: 150px;
            height: 150px;
            top: 50%;
            right: 10%;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(30px) rotate(240deg); }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            text-align: center;
            position: relative;
        }
        
        .login-header h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            margin-top: 10px;
            font-size: 1.1rem;
        }
        
        .login-body {
            padding: 40px;
        }
        
        .form-floating > label {
            color: #6c757d;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px 0 rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: #764ba2;
            text-decoration: none;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
        
        /* Animación de entrada */
        .login-card {
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .optional-label {
            color: #6c757d;
            font-size: 0.85em;
            font-weight: normal;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .login-header h1 {
                font-size: 2rem;
            }
            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Elementos decorativos -->
    <div class="bg-decoration decoration-1"></div>
    <div class="bg-decoration decoration-2"></div>
    <div class="bg-decoration decoration-3"></div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-5 col-xl-4">
                <div class="login-card">
                    <div class="login-header">
                        <div class="mb-3">
                            <i class="bi bi-link-45deg" style="font-size: 4rem; color: white;"></i>
                        </div>
                        <h1>URL Shortener</h1>
                        <p><?php echo $show_register ? 'Crear Cuenta Gratis' : 'Panel de Administración'; ?></p>
                    </div>
                    
                    <div class="login-body">
                        <!-- Información del dominio actual -->
                        <div class="domain-info">
                            🌐 Dominio: <?php echo htmlspecialchars($current_domain); ?>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$show_register): ?>
                        <!-- Formulario de Login -->
                        <form method="POST">
                            <input type="hidden" name="login" value="1">
                            
                            <div class="mb-4">
                                <label for="username" class="form-label text-muted mb-2">
                                    <i class="bi bi-person-circle"></i> Usuario
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Ingresa tu usuario"
                                       required 
                                       autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label text-muted mb-2">
                                    <i class="bi bi-shield-lock"></i> Contraseña
                                </label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Ingresa tu contraseña"
                                       required>
                            </div>
                            
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <div class="switch-form">
                            ¿No tienes cuenta? <a href="?register=1">Regístrate gratis aquí</a>
                        </div>
                        <?php else: ?>
                        <!-- Formulario de Registro -->
                        <form method="POST">
                            <input type="hidden" name="register" value="1">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label text-muted mb-2">
                                    <i class="bi bi-person"></i> Usuario <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Elige un nombre de usuario"
                                       pattern="[a-zA-Z0-9_-]{3,20}"
                                       title="3-20 caracteres, solo letras, números, guiones y guiones bajos"
                                       required 
                                       autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label text-muted mb-2">
                                    <i class="bi bi-person-badge"></i> Nombre Completo <span class="optional-label">(opcional)</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="full_name" 
                                       name="full_name" 
                                       placeholder="Tu nombre completo">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label text-muted mb-2">
                                    <i class="bi bi-envelope"></i> Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       placeholder="tu@email.com"
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label text-muted mb-2">
                                    <i class="bi bi-shield-lock"></i> Contraseña <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Mínimo 6 caracteres"
                                       minlength="6"
                                       required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password_confirm" class="form-label text-muted mb-2">
                                    <i class="bi bi-shield-check"></i> Confirmar Contraseña <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirm" 
                                       name="password_confirm" 
                                       placeholder="Repite la contraseña"
                                       required>
                            </div>
                            
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="bi bi-person-plus me-2"></i>
                                    Crear Cuenta Gratis
                                </button>
                            </div>
                            
                            <p class="text-center text-muted small">
                                Al registrarte aceptas nuestros términos y condiciones
                            </p>
                        </form>
                        
                        <div class="switch-form">
                            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
                        </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="../" class="back-link">
                                <i class="bi bi-arrow-left me-1"></i>
                                Volver al inicio
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <small class="text-white opacity-75">
                        © <?php echo date('Y'); ?> URL Shortener - Todos los derechos reservados
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efecto de onda al hacer clic en el botón
        document.querySelector('.btn-login').addEventListener('click', function(e) {
            let ripple = document.createElement('span');
            ripple.classList.add('ripple');
            this.appendChild(ripple);
            
            let x = e.clientX - e.target.offsetLeft;
            let y = e.clientY - e.target.offsetTop;
            
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
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
