<?php
session_start();
require_once 'conf.php';
// CONFIGURACIÓN DE SEGURIDAD - Cambia esto según necesites
define('REQUIRE_LOGIN_TO_SHORTEN', true); // true = requiere login, false = público
define('ALLOW_ANONYMOUS_VIEW', true);      // true = permite ver la página sin login
// Verificar si el usuario está logueado
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['admin_logged_in']);
$user_id = $_SESSION['user_id'] ?? 1;
$username = $_SESSION['username'] ?? 'Invitado';

// Verificar si es superadmin
$is_superadmin = ($user_id == 1);

// Si se requiere login y no está logueado, redirigir
if (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in && !ALLOW_ANONYMOUS_VIEW) {
    header('Location: admin/login.php');
    exit;
}
// Conexión a la base de datos
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
$message = '';
$messageType = 'info';
$shortened_url = '';
// Procesar el formulario solo si está logueado o no se requiere login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    // Verificar nuevamente el login si es requerido
    if (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in) {
        $message = '❌ Debes iniciar sesión para acortar URLs';
        $messageType = 'danger';
    } else {
        $original_url = trim($_POST['url']);
        $custom_code = isset($_POST['custom_code']) ? trim($_POST['custom_code']) : '';
        $domain_id = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : null;
        
        // Validar URL
        if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
            $message = '❌ Por favor, introduce una URL válida';
            $messageType = 'danger';
        } else {
            // NUEVA VALIDACIÓN: Verificar que el usuario puede usar el dominio seleccionado
            if ($domain_id && !$is_superadmin) {
                $stmt = $db->prepare("
                    SELECT id FROM custom_domains 
                    WHERE id = ? AND status = 'active' 
                    AND (user_id = ? OR user_id IS NULL)
                ");
                $stmt->execute([$domain_id, $user_id]);
                if (!$stmt->fetch()) {
                    $message = '❌ No tienes permiso para usar este dominio';
                    $messageType = 'danger';
                    $domain_id = null; // Resetear a dominio principal
                }
            }
            
            // Continuar solo si no hay errores
            if ($messageType !== 'danger') {
                // Generar código si no se proporcionó uno personalizado
                if (empty($custom_code)) {
                    do {
                        $custom_code = generateShortCode();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
                        $stmt->execute([$custom_code]);
                    } while ($stmt->fetchColumn() > 0);
                } else {
                    // Verificar que el código personalizado no existe
                    $stmt = $db->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
                    $stmt->execute([$custom_code]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = '❌ Ese código ya está en uso. Por favor, elige otro.';
                        $messageType = 'danger';
                        $custom_code = '';
                    }
                }
                
                if (!empty($custom_code)) {
                    try {
                        // Insertar la URL con el user_id correcto
                        $stmt = $db->prepare("
                            INSERT INTO urls (short_code, original_url, user_id, domain_id, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$custom_code, $original_url, $user_id, $domain_id]);
                        
                        // Obtener dominio si se seleccionó uno personalizado
                        if ($domain_id) {
                            $stmt = $db->prepare("SELECT domain FROM custom_domains WHERE id = ?");
                            $stmt->execute([$domain_id]);
                            $custom_domain = $stmt->fetch()['domain'];
                            $shortened_url = "https://" . $custom_domain . "/" . $custom_code;
                        } else {
                            $shortened_url = rtrim(BASE_URL, '/') . '/' . $custom_code;
                        }
                        
                        $message = '✅ ¡URL acortada con éxito!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = '❌ Error al crear la URL corta: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}
// CONSULTA MODIFICADA: Obtener dominios disponibles según el usuario
$available_domains = [];
if ($is_logged_in) {
    try {
        if ($is_superadmin) {
            // El superadmin ve todos los dominios activos
            $stmt = $db->query("SELECT id, domain FROM custom_domains WHERE status = 'active' ORDER BY domain");
        } else {
            // Los usuarios normales solo ven dominios asignados a ellos o sin asignar
            $stmt = $db->prepare("
                SELECT id, domain 
                FROM custom_domains 
                WHERE status = 'active' 
                AND (user_id = ? OR user_id IS NULL) 
                ORDER BY domain
            ");
            $stmt->execute([$user_id]);
        }
        $available_domains = $stmt->fetchAll();
    } catch (Exception $e) {
        // Ignorar si no existe la tabla
    }
}
// Obtener estadísticas generales
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM urls");
    $total_urls = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT SUM(clicks) as total FROM urls");
    $total_clicks = $stmt->fetch()['total'] ?? 0;
    
    // URLs recientes (solo públicas si no está logueado)
    if ($is_logged_in) {
        $stmt = $db->query("
            SELECT u.*, cd.domain as custom_domain 
            FROM urls u 
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
            ORDER BY u.created_at DESC 
            LIMIT 5
        ");
    } else {
        $stmt = $db->query("
            SELECT u.*, cd.domain as custom_domain 
            FROM urls u 
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
            WHERE u.is_public = 1 
            ORDER BY u.created_at DESC 
            LIMIT 5
        ");
    }
    $recent_urls = $stmt->fetchAll();
} catch (Exception $e) {
    $total_urls = 0;
    $total_clicks = 0;
    $recent_urls = [];
}
// Si el usuario está logueado, obtener sus estadísticas
$user_stats = null;
if ($is_logged_in && $user_id > 1) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_urls,
                SUM(clicks) as total_clicks
            FROM urls 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user_stats = $stmt->fetch();
    } catch (Exception $e) {
        // Ignorar errores
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Acortador de URLs - Acorta y Comparte</title>
    <meta name="description" content="Acorta tus URLs largas de forma rápida y gratuita. Estadísticas en tiempo real, códigos personalizados y más.">
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
            color: #333;
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8em;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .btn-login {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info span {
            opacity: 0.9;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Container principal */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 20px 40px;
        }
        
        /* Hero Section */
        .hero {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }
        
        .hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
            font-weight: 800;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .hero p {
            font-size: 1.3em;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }
        
        /* Main Card */
        .main-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            margin-bottom: 40px;
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
        
        /* Login Required Message */
        .login-required {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-required h3 {
            margin-bottom: 10px;
        }
        
        .login-required a {
            color: #667eea;
            font-weight: bold;
            text-decoration: none;
        }
        
        .login-required a:hover {
            text-decoration: underline;
        }
        
        /* User Stats */
        .user-stats {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid #e9ecef;
        }
        
        .user-stats h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .user-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .user-stat-item {
            text-align: center;
        }
        
        .user-stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .user-stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        /* Formulario */
        .url-form {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .input-group {
            display: flex;
            gap: 15px;
            align-items: stretch;
            flex-wrap: wrap;
        }
        
        .form-control {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            min-width: 250px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .form-select {
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-shorten {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-shorten:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .btn-shorten:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .btn-shorten:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Advanced Options */
        .advanced-options {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .advanced-toggle {
            color: #667eea;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .advanced-content {
            display: none;
            animation: slideDown 0.3s ease-out;
        }
        
        .advanced-content.show {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Domain select info */
        .domain-info {
            background: #e3f2fd;
            color: #1565c0;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.85em;
            margin-top: 5px;
            display: inline-block;
        }
        
        /* Result Box */
        .result-box {
            background: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }
        
        .result-box h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .shortened-url {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .shortened-url input {
            flex: 1;
            border: none;
            background: none;
            font-size: 18px;
            color: #495057;
            font-family: monospace;
        }
        
        .btn-copy {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-copy:hover {
            background: #5a67d8;
        }
        
        .btn-copy.copied {
            background: #28a745;
        }
        
        .result-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-stats {
            background: #17a2b8;
            color: white;
        }
        
        .btn-stats:hover {
            background: #138496;
        }
        
        .btn-qr {
            background: #28a745;
            color: white;
        }
        
        .btn-qr:hover {
            background: #218838;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        /* Recent URLs */
        .recent-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .recent-section h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 2em;
        }
        
        .recent-urls {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .url-original {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #6c757d;
        }
        
        .url-short {
            font-family: monospace;
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 5px;
            color: #495057;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #2196f3;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Features */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }
        
        .feature {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            font-size: 3em;
            margin-bottom: 20px;
        }
        
        .feature h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .feature p {
            color: #6c757d;
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 40px 20px;
            color: white;
            opacity: 0.9;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Mobile */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .hero h1 {
                font-size: 2.5em;
            }
            
            .hero p {
                font-size: 1.1em;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .form-control {
                min-width: 100%;
            }
            
            .btn-shorten {
                width: 100%;
            }
            
            .advanced-content.show {
                grid-template-columns: 1fr;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
            
            .recent-section {
                padding: 20px;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo">
                <span>🚀</span>
                <span>URL Shortener</span>
            </a>
            <nav class="nav-links">
                <a href="#features">Características</a>
                <a href="#stats">Estadísticas</a>
                <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <span>👤 <?php echo htmlspecialchars($username); ?></span>
                    <a href="/admin/panel_simple.php" class="btn-login">Panel Admin</a>
                    <a href="/admin/logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
                <?php else: ?>
                <a href="/admin/login.php" class="btn-login">Iniciar Sesión</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <!-- Main Container -->
    <div class="container">
        <!-- Hero Section -->
        <div class="hero">
            <h1>Acorta tus URLs en segundos</h1>
            <p>Convierte enlaces largos en URLs cortas y fáciles de compartir. 
            <?php if (REQUIRE_LOGIN_TO_SHORTEN): ?>
                Servicio exclusivo para usuarios registrados.
            <?php else: ?>
                Gratis, rápido y con estadísticas en tiempo real.
            <?php endif; ?>
            </p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid" id="stats">
            <div class="stat-card">
                <div class="stat-icon">🔗</div>
                <div class="stat-value"><?php echo number_format($total_urls); ?></div>
                <div class="stat-label">URLs Creadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👆</div>
                <div class="stat-value"><?php echo number_format($total_clicks); ?></div>
                <div class="stat-label">Clicks Totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚡</div>
                <div class="stat-value">100%</div>
                <div class="stat-label">Uptime</div>
            </div>
        </div>
        
        <!-- Main Card -->
        <div class="main-card">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($is_logged_in && $user_stats): ?>
            <div class="user-stats">
                <h3>👤 Tus Estadísticas</h3>
                <div class="user-stats-grid">
                    <div class="user-stat-item">
                        <div class="user-stat-value"><?php echo number_format($user_stats['total_urls'] ?? 0); ?></div>
                        <div class="user-stat-label">URLs creadas</div>
                    </div>
                    <div class="user-stat-item">
                        <div class="user-stat-value"><?php echo number_format($user_stats['total_clicks'] ?? 0); ?></div>
                        <div class="user-stat-label">Clicks totales</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in): ?>
            <div class="login-required">
                <h3>🔒 Inicio de sesión requerido</h3>
                <p>Para crear URLs cortas necesitas iniciar sesión.</p>
                <p><a href="/admin/login.php">Iniciar Sesión</a> o <a href="/admin/login.php?register=1">Registrarse</a></p>
            </div>
            <?php endif; ?>
            
            <?php if (empty($shortened_url)): ?>
            <!-- URL Form -->
            <form method="POST" class="url-form">
                <div class="form-group">
                    <label class="form-label">🔗 Pega tu URL larga aquí</label>
                    <div class="input-group">
                        <input type="url" 
                               name="url" 
                               class="form-control" 
                               placeholder="https://ejemplo.com/pagina-muy-larga-que-quieres-acortar" 
                               required 
                               autofocus
                               <?php echo (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in) ? 'disabled' : ''; ?>>
                        <button type="submit" 
                                class="btn-shorten"
                                <?php echo (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in) ? 'disabled' : ''; ?>>
                            Acortar URL
                        </button>
                    </div>
                </div>
                
                <?php if (!REQUIRE_LOGIN_TO_SHORTEN || $is_logged_in): ?>
                <!-- Advanced Options -->
                <div class="advanced-options">
                    <div class="advanced-toggle" onclick="toggleAdvanced()">
                        <span>⚙️ Opciones avanzadas</span>
                        <span id="toggle-icon">▼</span>
                    </div>
                    <div class="advanced-content" id="advanced-content">
                        <div class="form-group">
                            <label class="form-label">🎯 Código personalizado (opcional)</label>
                            <input type="text" 
                                   name="custom_code" 
                                   class="form-control" 
                                   placeholder="mi-codigo-personal" 
                                   pattern="[a-zA-Z0-9-_]+">
                            <small style="color: #6c757d; display: block; margin-top: 5px;">
                                Solo letras, números, guiones y guiones bajos
                            </small>
                        </div>
                        <?php if (!empty($available_domains)): ?>
                        <div class="form-group">
                            <label class="form-label">🌐 Dominio personalizado</label>
                            <select name="domain_id" class="form-select">
                                <option value="">Dominio principal (<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?>)</option>
                                <?php foreach ($available_domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>">
                                    <?php echo htmlspecialchars($domain['domain']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$is_superadmin && $is_logged_in): ?>
                            <div class="domain-info">
                                ℹ️ Solo ves dominios disponibles para ti
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </form>
            <?php else: ?>
            <!-- Result Box -->
            <div class="result-box">
                <h3>✅ ¡Tu URL ha sido acortada!</h3>
                <div class="shortened-url">
                    <input type="text" 
                           value="<?php echo htmlspecialchars($shortened_url); ?>" 
                           id="shortened-url" 
                           readonly>
                    <button class="btn-copy" onclick="copyUrl()">
                        📋 Copiar
                    </button>
                </div>
                <div class="result-actions">
                    <a href="stats.php?code=<?php echo urlencode($custom_code); ?>" class="btn-action btn-stats">
                        📊 Ver Estadísticas
                    </a>
                    <a href="qr.php?code=<?php echo urlencode($custom_code); ?>&view=1" class="btn-action btn-qr">
                        📱 Generar QR
                    </a>
                    <a href="/" class="btn-action btn-stats">
                        ➕ Acortar otra URL
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Features -->
        <div class="features" id="features">
            <div class="feature">
                <div class="feature-icon">⚡</div>
                <h3>Rápido y Sencillo</h3>
                <p><?php echo REQUIRE_LOGIN_TO_SHORTEN ? 'Acorta URLs de forma segura con tu cuenta.' : 'Acorta tus URLs en segundos. Sin complicaciones.'; ?></p>
            </div>
            <div class="feature">
                <div class="feature-icon">📊</div>
                <h3>Estadísticas Detalladas</h3>
                <p>Rastrea clicks, ubicaciones, dispositivos y más en tiempo real.</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🎯</div>
                <h3>URLs Personalizadas</h3>
                <p>Crea códigos cortos memorables o deja que generemos uno por ti.</p>
            </div>
            <div class="feature">
                <div class="feature-icon">📱</div>
                <h3>Códigos QR</h3>
                <p>Genera códigos QR para tus URLs cortas instantáneamente.</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🔒</div>
                <h3>Seguro y Confiable</h3>
                <p><?php echo REQUIRE_LOGIN_TO_SHORTEN ? 'Acceso exclusivo para usuarios registrados.' : 'Enlaces permanentes con redirección HTTPS segura.'; ?></p>
            </div>
            <div class="feature">
                <div class="feature-icon">🌐</div>
                <h3>Dominios Personalizados</h3>
                <p>Usa tu propio dominio para URLs más profesionales.</p>
            </div>
        </div>
        
        <!-- Recent URLs -->
        <?php if (!empty($recent_urls)): ?>
        <div class="recent-section">
            <h2>🔗 URLs Recientes <?php echo (!$is_logged_in ? '(Públicas)' : ''); ?></h2>
            <div class="recent-urls">
                <table>
                    <thead>
                        <tr>
                            <th>URL Original</th>
                            <th>URL Corta</th>
                            <th>Clicks</th>
                            <th>Creada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_urls as $url): ?>
                        <?php
                        if (!empty($url['custom_domain'])) {
                            $short_url = "https://" . $url['custom_domain'] . "/" . $url['short_code'];
                        } else {
                            $short_url = rtrim(BASE_URL, '/') . '/' . $url['short_code'];
                        }
                        ?>
                        <tr>
                            <td class="url-original" title="<?php echo htmlspecialchars($url['original_url']); ?>">
                                <?php echo htmlspecialchars($url['original_url']); ?>
                            </td>
                            <td>
                                <span class="url-short"><?php echo htmlspecialchars($short_url); ?></span>
                            </td>
                            <td>
                                <span class="badge badge-primary">
                                    <?php echo number_format($url['clicks'] ?? 0); ?> clicks
                                </span>
                            </td>
                            <td><?php echo date('d/m H:i', strtotime($url['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <p>
            © <?php echo date('Y'); ?> URL Shortener | 
            <a href="/privacy">Privacidad</a> | 
            <a href="/terms">Términos</a> | 
            <a href="/admin">Admin</a>
        </p>
    </footer>
    
    <script>
        // Toggle advanced options
        function toggleAdvanced() {
            const content = document.getElementById('advanced-content');
            const icon = document.getElementById('toggle-icon');
            
            if (content.classList.contains('show')) {
                content.classList.remove('show');
                icon.textContent = '▼';
            } else {
                content.classList.add('show');
                icon.textContent = '▲';
            }
        }
        
        // Copy URL to clipboard
        function copyUrl() {
            const input = document.getElementById('shortened-url');
            input.select();
            document.execCommand('copy');
            
            const btn = event.target;
            btn.textContent = '✅ Copiado!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.textContent = '📋 Copiar';
                btn.classList.remove('copied');
            }, 2000);
        }
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Auto-focus result URL
        <?php if (!empty($shortened_url)): ?>
        window.addEventListener('load', function() {
            document.getElementById('shortened-url').select();
        });
        <?php endif; ?>
    </script>
</body>
</html>
