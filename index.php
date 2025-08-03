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
    header('Location: ' . rtrim(BASE_URL, '/') . '/admin/login.php');
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
$custom_code = ''; // Para mantener el código después del redirect

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
            
            // Continuar solo si no hay errores previos
            if ($messageType !== 'danger') {
                $code_created = false;
                
                // Generar código si no se proporcionó uno personalizado
                if (empty($custom_code)) {
                    // Generar código automático
                    do {
                        $custom_code = generateShortCode();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
                        $stmt->execute([$custom_code]);
                    } while ($stmt->fetchColumn() > 0);
                    $code_created = true;
                } else {
                    // VALIDACIÓN MEJORADA del código personalizado
                    if (!preg_match('/^[a-zA-Z0-9-_]+$/', $custom_code)) {
                        $message = '❌ El código solo puede contener letras, números, guiones y guiones bajos.';
                        $messageType = 'danger';
                    } elseif (strlen($custom_code) > 100) {
                        $message = '❌ El código no puede tener más de 100 caracteres.';
                        $messageType = 'danger';
                    } else {
                        // Verificar que el código personalizado no existe
                        $stmt = $db->prepare("SELECT id FROM urls WHERE short_code = ?");
                        $stmt->execute([$custom_code]);
                        if ($stmt->fetch()) {
                            $message = '❌ Ese código ya está en uso. Por favor, elige otro.';
                            $messageType = 'danger';
                        } else {
                            $code_created = true;
                        }
                    }
                }
                
                // Si el código está listo, crear la URL
                if ($code_created && $messageType !== 'danger') {
                    try {
                        // Insertar la URL con el user_id correcto
                        $stmt = $db->prepare("
                            INSERT INTO urls (short_code, original_url, user_id, domain_id, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$custom_code, $original_url, $user_id, $domain_id]);
                        
                        // IMPORTANTE: Guardar datos en sesión para mostrarlos después del redirect
                        $_SESSION['last_shortened_code'] = $custom_code;
                        $_SESSION['last_shortened_domain_id'] = $domain_id;
                        $_SESSION['success_message'] = '✅ ¡URL acortada con éxito!';
                        
                        // REDIRECT PARA EVITAR REENVÍO DEL FORMULARIO
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                        exit();
                        
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            $message = '❌ Ese código ya está en uso. Por favor, elige otro.';
                        } else {
                            $message = '❌ Error al crear la URL corta: ' . $e->getMessage();
                        }
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// PROCESAR EL ÉXITO DESPUÉS DEL REDIRECT
if (isset($_GET['success']) && isset($_SESSION['last_shortened_code'])) {
    $custom_code = $_SESSION['last_shortened_code'];
    $domain_id = $_SESSION['last_shortened_domain_id'] ?? null;
    
    // Construir la URL corta
    if ($domain_id) {
        $stmt = $db->prepare("SELECT domain FROM custom_domains WHERE id = ?");
        $stmt->execute([$domain_id]);
        $result = $stmt->fetch();
        $custom_domain = $result ? $result['domain'] : null;
        if ($custom_domain) {
            $shortened_url = "https://" . $custom_domain . "/" . $custom_code;
        } else {
            $shortened_url = rtrim(BASE_URL, '/') . '/' . $custom_code;
        }
    } else {
        $shortened_url = rtrim(BASE_URL, '/') . '/' . $custom_code;
    }
    
    $message = $_SESSION['success_message'] ?? '✅ ¡URL acortada con éxito!';
    $messageType = 'success';
    
    // Limpiar las variables de sesión
    unset($_SESSION['last_shortened_code']);
    unset($_SESSION['last_shortened_domain_id']);
    unset($_SESSION['success_message']);
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
    <title>0ln.eu - Acortador de URLs Gratis | Enlaces Cortos con Estadísticas</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Font Awesome (para mantener compatibilidad) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- FAVICON -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/favicon.png">
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Acorta URLs gratis con 0ln.eu. Crea enlaces cortos personalizados, rastrea clics en tiempo real, genera códigos QR. Compatible con Twitter, Facebook y WhatsApp. ¡Sin registro!">
    <meta name="keywords" content="acortador url, url corta, acortar enlaces, short url, link shortener, 0ln.eu, enlaces cortos gratis, estadísticas url, qr code generator">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://0ln.eu/">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@tu_usuario_twitter">
    <meta name="twitter:title" content="0ln.eu - El Mejor Acortador de URLs 🚀">
    <meta name="twitter:description" content="Acorta URLs gratis ⚡ Estadísticas en tiempo real 📊 Compatible con todas las redes sociales 🔗 ¡Ya somos <?php echo number_format($total_urls); ?> URLs creadas!">
    <meta name="twitter:image" content="https://0ln.eu/assets/twitter-card3.png?v=<?php echo time(); ?>">
    
    <!-- Open Graph -->
    <meta property="og:url" content="https://0ln.eu">
    <meta property="og:type" content="website">
    <meta property="og:title" content="0ln.eu - Acortador de URLs Profesional">
    <meta property="og:description" content="Crea enlaces cortos personalizados con estadísticas detalladas. Perfecto para marketing digital.">
    <meta property="og:image" content="https://0ln.eu/assets/og-image.png">
    
    <!-- Custom CSS -->
    <style>
        /* Variables CSS */
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        /* Body and general styles */
        body {
            background: var(--gradient);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        /* Navbar customization */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
        }
        
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: white !important;
            font-weight: 500;
        }
        
        .navbar-custom .nav-link:hover {
            opacity: 0.8;
        }
        
        .navbar-custom .dropdown-menu {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Hero section */
        .hero-section {
            color: white;
            text-align: center;
            padding: 3rem 0;
            animation: fadeInDown 0.8s ease-out;
        }
        
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .hero-section .lead {
            font-size: 1.3rem;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Cards and panels */
        .main-card {
            background: white;
            border-radius: 1.25rem;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Analytics widget */
        .analytics-summary {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            animation: fadeInDown 0.6s ease-out;
        }
        
        .analytics-card {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }
        
        .analytics-card:hover {
            transform: translateY(-2px);
            background: rgba(102, 126, 234, 0.15);
        }
        
        .analytics-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Stat cards */
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        /* Feature cards */
        .feature-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
        }
        
        /* Form enhancements */
        .url-input-group {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .url-input-group .form-control {
            border: 2px solid #e9ecef;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
        }
        
        .url-input-group .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Buttons */
        .btn-gradient {
            background: var(--gradient);
            border: none;
            color: white;
            font-weight: 600;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-gradient:disabled {
            opacity: 0.6;
            transform: none;
        }
        
        /* Result box */
        .result-box {
            background: #f8f9fa;
            border: 2px solid var(--primary-color);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }
        
        .shortened-url-display {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1.5rem 0;
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
        }
        
        /* User stats alert */
        .user-stats-alert {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 0.75rem;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .hero-section .lead {
                font-size: 1.1rem;
            }
            
            .main-card {
                padding: 1.5rem;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .analytics-number {
                font-size: 1.5rem;
            }
        }
        
        /* Loading spinner */
        .spinner-border-custom {
            color: var(--primary-color);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="/">
                <span class="fs-3">🚀</span>
                <span>URL Shortener</span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Características</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#stats">Estadísticas</a>
                    </li>
                    
                    <?php if ($is_logged_in): ?>
                    <li class="nav-item dropdown ms-3">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li>
                                <a class="dropdown-item" href="marcadores/">
                                    <i class="bi bi-graph-up me-2"></i>Gestor URLs
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/panel_simple.php">
                                    <i class="bi bi-speedometer2 me-2"></i>Panel Admin
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item ms-3">
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/login.php" class="btn btn-light rounded-pill px-4">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            Iniciar Sesión
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Container -->
    <div class="container-fluid px-3 px-lg-5" style="padding-top: 100px;">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="container">
                <h1 class="mb-4">Acorta tus URLs en segundos</h1>
                <p class="lead">
                    Convierte enlaces largos en URLs cortas y fáciles de compartir. 
                    <?php if (REQUIRE_LOGIN_TO_SHORTEN): ?>
                        Servicio exclusivo para usuarios registrados.
                    <?php else: ?>
                        Gratis, rápido y con estadísticas en tiempo real.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Analytics Summary Widget -->
        <?php if ($is_logged_in): ?>
        <div class="container mb-4">
            <div class="analytics-summary" id="analyticsSummary" style="display: none;">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-lg-3">
                        <div class="analytics-card">
                            <div class="analytics-number" id="totalClicks">0</div>
                            <div class="text-muted small">Total Clicks</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="analytics-card">
                            <div class="analytics-number" id="uniqueVisitors">0</div>
                            <div class="text-muted small">Visitantes Únicos</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="analytics-card">
                            <div class="analytics-number" id="urlsClicked">0</div>
                            <div class="text-muted small">URLs Clickeadas</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="analytics-card">
                            <div class="analytics-number" id="topUrlClicks">0</div>
                            <div class="text-muted small text-truncate" id="topUrlLabel">Top URL</div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="marcadores/analytics_dashboard.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-chart-line me-1"></i>Dashboard Completo
                    </a>
                    <a href="marcadores/export_bookmarks.php?format=html&download=1" class="btn btn-secondary btn-sm">
                        <i class="fas fa-bookmark me-1"></i>Exportar Marcadores
                    </a>
                    <button onclick="refreshAnalytics()" class="btn btn-secondary btn-sm" id="refreshBtn">
                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="container mb-5">
            <div class="row g-4" id="stats">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon">🔗</div>
                        <div class="stat-value"><?php echo number_format($total_urls); ?></div>
                        <div class="text-muted">URLs Creadas</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon">👆</div>
                        <div class="stat-value"><?php echo number_format($total_clicks); ?></div>
                        <div class="text-muted">Clicks Totales</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-value">100%</div>
                        <div class="text-muted">Uptime</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Card -->
        <div class="container mb-5">
            <div class="main-card">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($is_logged_in && $user_stats): ?>
                <div class="alert user-stats-alert mb-4">
                    <h5 class="alert-heading mb-3">
                        <i class="bi bi-person-circle me-2"></i>Tus Estadísticas
                    </h5>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h3 mb-0 text-primary"><?php echo number_format($user_stats['total_urls'] ?? 0); ?></div>
                            <small class="text-muted">URLs creadas</small>
                        </div>
                        <div class="col-6">
                            <div class="h3 mb-0 text-primary"><?php echo number_format($user_stats['total_clicks'] ?? 0); ?></div>
                            <small class="text-muted">Clicks totales</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in): ?>
                <div class="alert alert-warning text-center py-4">
                    <h4 class="alert-heading mb-3">
                        <i class="bi bi-lock-fill me-2"></i>Inicio de sesión requerido
                    </h4>
                    <p>Para crear URLs cortas necesitas una cuenta.</p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/login.php" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-key-fill me-2"></i>Iniciar Sesión
                        </a>
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/login.php?register=1" class="btn btn-success rounded-pill px-4">
                            <i class="bi bi-stars me-2"></i>Crear Cuenta Gratis
                        </a>
                    </div>
                    <p class="mt-3 mb-0 text-muted small">
                        ¡Registro gratuito en 30 segundos! Sin tarjeta de crédito.
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (empty($shortened_url)): ?>
                <!-- URL Form -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-5 mb-3">
                            <i class="bi bi-link-45deg me-2"></i>Pega tu URL larga aquí
                        </label>
                        <div class="input-group input-group-lg url-input-group">
                            <input type="url" 
                                   name="url" 
                                   class="form-control" 
                                   placeholder="https://ejemplo.com/pagina-muy-larga-que-quieres-acortar" 
                                   required 
                                   autofocus
                                   <?php echo (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in) ? 'disabled' : ''; ?>>
                            <button type="submit" 
                                    class="btn btn-gradient px-4"
                                    <?php echo (REQUIRE_LOGIN_TO_SHORTEN && !$is_logged_in) ? 'disabled' : ''; ?>>
                                Acortar URL
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!REQUIRE_LOGIN_TO_SHORTEN || $is_logged_in): ?>
                    <!-- Advanced Options -->
                    <div class="mt-4">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedOptions">
                            <i class="bi bi-gear-fill me-2"></i>Opciones avanzadas
                            <i class="bi bi-chevron-down ms-1"></i>
                        </button>                        
                        <div class="collapse mt-3" id="advancedOptions">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-bullseye me-1"></i>Código personalizado (opcional)
                                    </label>
                                    <input type="text" 
                                           name="custom_code" 
                                           class="form-control" 
                                           placeholder="mi-codigo-personal" 
                                           pattern="[a-zA-Z0-9-_]+"
                                           maxlength="100">
                                    <small class="text-muted">
                                        Solo letras, números, guiones y guiones bajos (máx. 100)
                                    </small>
                                </div>
                                
                                <?php if (!empty($available_domains)): ?>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-globe me-1"></i>Dominio personalizado
                                    </label>
                                    <select name="domain_id" class="form-select">
                                        <option value="">Dominio principal (<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?>)</option>
                                        <?php foreach ($available_domains as $domain): ?>
                                        <option value="<?php echo $domain['id']; ?>">
                                            <?php echo htmlspecialchars($domain['domain']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!$is_superadmin && $is_logged_in): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>Solo ves dominios disponibles para ti
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                <!-- Result Box -->
                <div class="result-box">
                    <h3 class="text-primary mb-4">
                        <i class="bi bi-check-circle-fill me-2"></i>¡Tu URL ha sido acortada!
                    </h3>
                    <div class="input-group input-group-lg mb-4">
                        <input type="text" 
                               value="<?php echo htmlspecialchars($shortened_url); ?>" 
                               id="shortened-url" 
                               class="form-control" 
                               readonly>
                        <button class="btn btn-primary" onclick="copyUrl()">
                            <i class="bi bi-clipboard me-2"></i>Copiar
                        </button>
                    </div>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="stats.php?code=<?php echo urlencode($custom_code); ?>" class="btn btn-info">
                            <i class="bi bi-graph-up me-2"></i>Ver Estadísticas
                        </a>
                        <a href="qr.php?code=<?php echo urlencode($custom_code); ?>&view=1" class="btn btn-success">
                            <i class="bi bi-qr-code me-2"></i>Generar QR
                        </a>
                        <a href="/" class="btn btn-secondary">
                            <i class="bi bi-plus-circle me-2"></i>Acortar otra URL
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Features -->
        <div class="container mb-5">
            <div class="row g-4" id="features">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">⚡</div>
                        <h3 class="h5">Rápido y Sencillo</h3>
                        <p class="text-muted"><?php echo REQUIRE_LOGIN_TO_SHORTEN ? 'Acorta URLs de forma segura con tu cuenta.' : 'Acorta tus URLs en segundos. Sin complicaciones.'; ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3 class="h5">Estadísticas Detalladas</h3>
                        <p class="text-muted">Rastrea clicks, ubicaciones, dispositivos y más en tiempo real.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3 class="h5">URLs Personalizadas</h3>
                        <p class="text-muted">Crea códigos cortos memorables o deja que generemos uno por ti.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3 class="h5">Códigos QR</h3>
                        <p class="text-muted">Genera códigos QR para tus URLs cortas instantáneamente.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3 class="h5">Seguro y Confiable</h3>
                        <p class="text-muted"><?php echo REQUIRE_LOGIN_TO_SHORTEN ? 'Acceso exclusivo para usuarios registrados.' : 'Enlaces permanentes con redirección HTTPS segura.'; ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">🌐</div>
                        <h3 class="h5">Dominios Personalizados</h3>
                        <p class="text-muted">Usa tu propio dominio para URLs más profesionales.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEO Content Section -->
        <div class="container mb-5">
            <div class="main-card">
                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        <h2 class="text-center mb-4">¿Por qué elegir 0ln.eu para acortar URLs?</h2>
                        
                        <div class="row g-4 mb-5">
                            <div class="col-md-4 text-center">
                                <div class="display-3 mb-3">🌍</div>
                                <h4>Alcance Global</h4>
                                <p class="text-muted">Accesible desde cualquier parte del mundo con servidores rápidos y confiables.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="display-3 mb-3">🛡️</div>
                                <h4>100% Seguro</h4>
                                <p class="text-muted">Todas las URLs usan HTTPS y protección contra malware automática.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="display-3 mb-3">🎨</div>
                                <h4>Vista Previa Rica</h4>
                                <p class="text-muted">Optimizado para Twitter, Facebook y WhatsApp con miniaturas automáticas.</p>
                            </div>
                        </div>
                        
                        <div class="bg-light p-4 rounded-3 mb-4">
                            <h4 class="mb-3">
                                <i class="bi bi-rocket-takeoff me-2"></i>Cómo acortar URLs con 0ln.eu
                            </h4>
                            <ol class="mb-0">
                                <li class="mb-2"><strong>Pega tu URL larga</strong> - Copia cualquier enlace largo que quieras acortar</li>
                                <li class="mb-2"><strong>Personaliza (opcional)</strong> - Crea un código corto memorable como 0ln.eu/mi-link</li>
                                <li><strong>¡Comparte!</strong> - Tu nueva URL corta está lista para usar en cualquier lugar</li>
                            </ol>
                        </div>
                        
                        <div class="mb-4">
                            <h4 class="mb-3">
                                <i class="bi bi-briefcase-fill me-2"></i>Acortador de URLs profesional para empresas
                            </h4>
                            <p class="text-muted mb-3">
                                0ln.eu es el acortador de URLs preferido por profesionales del marketing digital, community managers y empresas que necesitan 
                                compartir enlaces de forma elegante y rastreable. Nuestro servicio de acortamiento de URLs es perfecto para:
                            </p>
                            <ul class="text-muted">
                                <li>Campañas de marketing en redes sociales (Twitter, LinkedIn, Instagram)</li>
                                <li>Enlaces en newsletters y email marketing</li>
                                <li>Códigos QR para eventos, restaurantes y tarjetas de presentación</li>
                                <li>Tracking de conversiones y análisis de tráfico</li>
                                <li>Enlaces cortos para SMS y WhatsApp Business</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-info mb-0">
                            <h5 class="alert-heading">
                                <i class="bi bi-lightbulb-fill me-2"></i>¿Sabías que...?
                            </h5>
                            <p class="mb-0">
                                Los enlaces acortados reciben hasta <strong>39% más clics</strong> que las URLs largas. 
                                Además, con 0ln.eu puedes ver exactamente quién, cuándo y desde dónde hacen clic en tus enlaces, 
                                información invaluable para optimizar tus campañas de marketing digital.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent URLs -->
        <?php if (!empty($recent_urls)): ?>
        <div class="container mb-5">
            <div class="main-card">
                <h2 class="mb-4">
                    <i class="bi bi-clock-history me-2"></i>URLs Recientes <?php echo (!$is_logged_in ? '(Públicas)' : ''); ?>
                </h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>URL Original</th>
                                <th>URL Corta</th>
                                <th>Clicks</th>
                                <th>Creada</th>
                                <?php if ($is_logged_in): ?>
                                <th>Analytics</th>
                                <?php endif; ?>
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
                                <td>
                                    <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($url['original_url']); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($url['original_url']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <code class="bg-light p-2 rounded"><?php echo htmlspecialchars($short_url); ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo number_format($url['clicks'] ?? 0); ?> clicks
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <small><?php echo date('d/m H:i', strtotime($url['created_at'])); ?></small>
                                </td>
                                <?php if ($is_logged_in): ?>
                                <td>
                                    <a href="marcadores/analytics_url.php?url_id=<?php echo $url['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Ver Analytics">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="text-center text-white py-5 mt-5">
        <div class="container">
            <p class="mb-0">
                © <?php echo date('Y'); ?> URL Shortener | 
                <a href="/privacy" class="text-white text-decoration-none">Privacidad</a> | 
                <a href="https://chromewebstore.google.com/search/gestor%20URLs%20cortas" class="text-white text-decoration-none">Extensión</a> |
                <a href="/demo.html" class="text-white text-decoration-none">Demo</a> | 
                <a href="/Aclaraciones" class="text-white text-decoration-none">Aclaraciones</a> |
                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin" class="text-white text-decoration-none">Admin</a>
            </p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Analytics Integration
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_logged_in): ?>
            loadAnalyticsSummary();
            <?php endif; ?>
        });

        async function loadAnalyticsSummary() {
            try {
                const response = await fetch('marcadores/api.php?action=analytics_summary');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('analyticsSummary').style.display = 'block';
                    document.getElementById('totalClicks').textContent = formatNumber(data.summary.total_clicks);
                    document.getElementById('uniqueVisitors').textContent = formatNumber(data.summary.unique_visitors);
                    document.getElementById('urlsClicked').textContent = formatNumber(data.summary.urls_clicked);
                    
                    if (data.top_url) {
                        document.getElementById('topUrlClicks').textContent = formatNumber(data.top_url.clicks);
                        document.getElementById('topUrlLabel').textContent = `${data.top_url.short_code} (${data.top_url.clicks} clicks)`;
                    }
                    
                    console.log('✅ Analytics summary loaded');
                } else {
                    console.log('No analytics data available');
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
            }
        }

        async function refreshAnalytics() {
            const btn = document.getElementById('refreshBtn');
            const originalHTML = btn.innerHTML;
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cargando...';
            btn.disabled = true;
            
            await loadAnalyticsSummary();
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }, 1000);
        }

        function formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }
        
        function copyUrl() {
            const input = document.getElementById('shortened-url');
            input.select();
            document.execCommand('copy');
            
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>¡Copiado!';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-primary');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.add('btn-primary');
                btn.classList.remove('btn-success');
            }, 2000);
        }
        
        // Auto-focus result URL
        <?php if (!empty($shortened_url)): ?>
        window.addEventListener('load', function() {
            document.getElementById('shortened-url').select();
        });
        <?php endif; ?>
        
        // Clean URL
        <?php if (isset($_GET['success'])): ?>
        if (window.history.replaceState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
        <?php endif; ?>
        
        // Smooth scroll for anchor links
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
        
        // Add navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(102, 126, 234, 0.95)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.1)';
            }
        });
    </script>
</body>
</html>
