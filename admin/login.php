<?php
// login.php - Sistema completo con recuperación de contraseña por email
session_start();
require_once '../conf.php';
require_once '../email_config.php'; // Configuración de email

// Cargar PHPMailer
require_once '../libraries/PHPMailer/PHPMailer.php';
require_once '../libraries/PHPMailer/SMTP.php';
require_once '../libraries/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// SEGURIDAD: Verificar que se está accediendo desde el dominio principal
$allowed_domain = parse_url(BASE_URL, PHP_URL_HOST);
$current_domain = $_SERVER['HTTP_HOST'];

// Si no está accediendo desde el dominio principal, denegar acceso
if ($current_domain !== $allowed_domain) {
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
$show_forgot = isset($_GET['forgot']);
$show_reset = isset($_GET['reset']) && isset($_GET['token']);

// Crear tabla de reset tokens si no existe
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )
    ");
} catch (PDOException $e) {
    // Tabla ya existe o error
}

// Limpiar tokens expirados
$db->exec("DELETE FROM password_resets WHERE expires_at < NOW() OR used = TRUE");

// Verificar si hay mensaje de error por dominio incorrecto
if (isset($_GET['error']) && $_GET['error'] === 'wrong_domain') {
    $error = '⚠️ Por seguridad, el acceso al panel solo está permitido desde el dominio principal.';
}

// Función para enviar email con PHPMailer
function sendResetEmail($email, $username, $resetLink) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Debug (quitar en producción)
        $mail->SMTPDebug = SMTP_DEBUG;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer: $str");
        };
        
        // Configuración adicional para evitar problemas
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinatarios
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $username);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '🔐 Recuperar contraseña - URL Shortener';
        
        // Crear un diseño bonito para el email
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background-color: #f4f4f4;
                    color: #333333;
                }
                .email-wrapper {
                    width: 100%;
                    background-color: #f4f4f4;
                    padding: 40px 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .email-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #ffffff;
                    padding: 40px 30px;
                    text-align: center;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 28px;
                    font-weight: 600;
                }
                .email-header p {
                    margin: 10px 0 0 0;
                    font-size: 16px;
                    opacity: 0.9;
                }
                .email-body {
                    padding: 40px 30px;
                }
                .email-body h2 {
                    color: #333;
                    font-size: 20px;
                    margin-bottom: 20px;
                }
                .email-body p {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 20px;
                }
                .button-container {
                    text-align: center;
                    margin: 30px 0;
                }
                .reset-button {
                    display: inline-block;
                    padding: 14px 40px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 16px;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                    transition: transform 0.3s;
                }
                .reset-button:hover {
                    transform: translateY(-2px);
                }
                .link-container {
                    background-color: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 20px 0;
                    word-break: break-all;
                }
                .link-container p {
                    margin: 5px 0;
                    font-size: 14px;
                }
                .email-footer {
                    background-color: #f8f9fa;
                    padding: 30px;
                    text-align: center;
                    border-top: 1px solid #e9ecef;
                }
                .email-footer p {
                    color: #999;
                    font-size: 14px;
                    margin: 5px 0;
                }
                .warning-box {
                    background-color: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-container">
                    <div class="email-header">
                        <div class="icon">🔐</div>
                        <h1>Recuperación de Contraseña</h1>
                        <p>URL Shortener</p>
                    </div>
                    
                    <div class="email-body">
                        <h2>Hola ' . htmlspecialchars($username) . ',</h2>
                        
                        <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en URL Shortener.</p>
                        
                        <p>Si realizaste esta solicitud, haz clic en el botón de abajo para crear una nueva contraseña:</p>
                        
                        <div class="button-container">
                            <a href="' . $resetLink . '" class="reset-button">Restablecer mi Contraseña</a>
                        </div>
                        
                        <div class="link-container">
                            <p><strong>¿No funciona el botón?</strong> Copia y pega este enlace en tu navegador:</p>
                            <p style="color: #667eea; font-size: 12px;">' . $resetLink . '</p>
                        </div>
                        
                        <div class="warning-box">
                            <p style="margin: 0;"><strong>⏰ Importante:</strong> Este enlace expirará en 1 hora por razones de seguridad.</p>
                        </div>
                        
                        <p>Si no solicitaste restablecer tu contraseña, puedes ignorar este correo de forma segura. Tu contraseña actual permanecerá sin cambios.</p>
                    </div>
                    
                    <div class="email-footer">
                        <p><strong>¿Necesitas ayuda?</strong></p>
                        <p>Responde a este correo si tienes alguna pregunta.</p>
                        <p style="margin-top: 20px; color: #999;">
                            © ' . date('Y') . ' URL Shortener. Todos los derechos reservados.<br>
                            Este es un correo automático, por favor no respondas directamente.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->Body = $message;
        
        // Versión de texto plano
        $mail->AltBody = "
Hola {$username},

Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en URL Shortener.

Para restablecer tu contraseña, visita el siguiente enlace:
{$resetLink}

Este enlace expirará en 1 hora por razones de seguridad.

Si no solicitaste restablecer tu contraseña, puedes ignorar este correo.

Saludos,
URL Shortener
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar email: {$mail->ErrorInfo}");
        // En desarrollo, mostrar el error
        if (SMTP_DEBUG > 0) {
            throw new Exception("Error al enviar email: {$mail->ErrorInfo}");
        }
        return false;
    }
}

// Procesar solicitud de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Por favor, ingresa tu email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } else {
        try {
            // Buscar usuario por email
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // No permitir reset para el superadmin configurado en conf.php
                if ($user['username'] === ADMIN_USERNAME) {
                    $success = '✅ Si el email existe en nuestro sistema, recibirás instrucciones para recuperar tu contraseña en breve.';
                } else {
                    // Generar token único
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Guardar token en BD
                    $stmt = $db->prepare("
                        INSERT INTO password_resets (user_id, token, expires_at) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $token, $expires]);
                    
                    // Generar link de reset
                    $resetLink = rtrim(BASE_URL, '/') . '/admin/login.php?reset=1&token=' . $token;
                    
                    // Enviar email
                    try {
                        if (sendResetEmail($email, $user['username'], $resetLink)) {
                            $success = '✅ Te hemos enviado un email con instrucciones para recuperar tu contraseña. Revisa tu bandeja de entrada.';
                        } else {
                            $error = '❌ Error al enviar el email. Por favor, contacta al administrador.';
                        }
                    } catch (Exception $e) {
                        $error = '❌ Error al enviar el email: ' . $e->getMessage();
                    }
                }
            } else {
                // No revelar si el email existe o no (seguridad)
                $success = '✅ Si el email existe en nuestro sistema, recibirás instrucciones para recuperar tu contraseña en breve.';
            }
        } catch (PDOException $e) {
            $error = 'Error al procesar la solicitud: ' . $e->getMessage();
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($token) || empty($password) || empty($password_confirm)) {
        $error = 'Por favor, completa todos los campos';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            // Verificar token válido
            $stmt = $db->prepare("
                SELECT pr.*, u.username 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = ? 
                AND pr.expires_at > NOW() 
                AND pr.used = FALSE
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if ($reset) {
                // Actualizar contraseña
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $reset['user_id']]);
                
                // Marcar token como usado
                $stmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
                $stmt->execute([$token]);
                
                $success = '✅ ¡Contraseña actualizada exitosamente! Ya puedes iniciar sesión con tu nueva contraseña.';
                $show_reset = false;
            } else {
                $error = '❌ El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.';
            }
        } catch (PDOException $e) {
            $error = 'Error al actualizar la contraseña: ' . $e->getMessage();
        }
    }
}

// Verificar token en GET para mostrar formulario de reset
if ($show_reset) {
    $token = $_GET['token'];
    try {
        $stmt = $db->prepare("
            SELECT pr.*, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? 
            AND pr.expires_at > NOW() 
            AND pr.used = FALSE
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            $error = '❌ El enlace de recuperación es inválido o ha expirado.';
            $show_reset = false;
        }
    } catch (PDOException $e) {
        $error = 'Error al verificar el token';
        $show_reset = false;
    }
}

// Procesar login (sin cambios)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($current_domain !== $allowed_domain) {
        $error = '❌ Acceso denegado. Use el dominio principal.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error = 'Por favor, completa todos los campos';
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $user ? $user['id'] : 1;
                    $_SESSION['username'] = ADMIN_USERNAME;
                    $_SESSION['role'] = 'admin';
                    
                    if ($user) {
                        $db->exec("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                    }
                    
                    header('Location: panel_simple.php');
                    exit();
                } 
                elseif ($user && $user['username'] !== ADMIN_USERNAME && password_verify($password, $user['password'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    $db->exec("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                    
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

// Procesar registro (sin cambios)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if ($current_domain !== $allowed_domain) {
        $error = '❌ El registro solo está permitido desde el dominio principal.';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        if (empty($full_name)) {
            $full_name = $username;
        }
        
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
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'El usuario o email ya existe';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $columns = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                    
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
                    
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'user';
                    
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
    <title><?php 
        if ($show_reset) echo 'Restablecer Contraseña';
        elseif ($show_forgot) echo 'Recuperar Contraseña';
        elseif ($show_register) echo 'Registro';
        else echo 'Login Admin';
    ?> - URL Shortener</title>
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
            animation: slideIn 0.5s;
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
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 15px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9em;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
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
                        <p><?php 
                            if ($show_reset) echo 'Restablecer Contraseña';
                            elseif ($show_forgot) echo 'Recuperar Contraseña';
                            elseif ($show_register) echo 'Crear Cuenta Gratis';
                            else echo 'Panel de Administración';
                        ?></p>
                    </div>
                    
                    <div class="login-body">
                        <!-- Información del dominio actual -->
                        <?php if (!$show_reset): ?>
                        <div class="domain-info">
                            🌐 Dominio: <?php echo htmlspecialchars($current_domain); ?>
                        </div>
                        <?php endif; ?>
                        
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
                        
                        <?php if ($show_reset): ?>
                        <!-- Formulario de Reset de Contraseña -->
                        <form method="POST">
                            <input type="hidden" name="reset_password" value="1">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                            
                            <div class="mb-3">
                                <p class="text-muted">
                                    <i class="bi bi-envelope me-2"></i>
                                    Restableciendo contraseña para: <strong><?php echo htmlspecialchars($reset['email'] ?? ''); ?></strong>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label text-muted mb-2">
                                    <i class="bi bi-shield-lock"></i> Nueva Contraseña
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Mínimo 6 caracteres"
                                       minlength="6"
                                       required 
                                       autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password_confirm" class="form-label text-muted mb-2">
                                    <i class="bi bi-shield-check"></i> Confirmar Nueva Contraseña
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
                                    <i class="bi bi-key me-2"></i>
                                    Cambiar Contraseña
                                </button>
                            </div>
                        </form>
                        
                        <?php elseif ($show_forgot): ?>
                        <!-- Formulario de Recuperación -->
                        <form method="POST">
                            <input type="hidden" name="forgot_password" value="1">
                            
                            <div class="mb-4">
                                <p class="text-muted">
                                    Ingresa el email asociado a tu cuenta y te enviaremos un enlace para restablecer tu contraseña.
                                </p>
                            </div>
                            
                            <div class="mb-4">
                                <label for="email" class="form-label text-muted mb-2">
                                    <i class="bi bi-envelope"></i> Email
                                </label>
                                <input type="email" 
                                       class="form-control form-control-lg" 
                                       id="email" 
                                       name="email" 
                                       placeholder="tu@email.com"
                                       required 
                                       autofocus>
                            </div>
                            
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="bi bi-envelope-check me-2"></i>
                                    Enviar Enlace de Recuperación
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <a href="login.php" class="back-link">
                                    <i class="bi bi-arrow-left me-1"></i>
                                    Volver al login
                                </a>
                            </div>
                        </form>
                        
                        <?php elseif (!$show_register): ?>
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
                            
                            <div class="mb-3">
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
                            
                            <div class="forgot-password">
                                <a href="?forgot=1">¿Olvidaste tu contraseña?</a>
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
                        
                        <?php if (!$show_forgot && !$show_reset): ?>
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="../" class="back-link">
                                <i class="bi bi-arrow-left me-1"></i>
                                Volver al inicio
                            </a>
                        </div>
                        <?php endif; ?>
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
        
        // Validación en tiempo real para registro y reset
        <?php if ($show_register || $show_reset): ?>
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
