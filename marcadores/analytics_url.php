<?php
// analytics_url.php - Visualización de estadísticas con permisos de superadmin
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar archivos necesarios
$required_files = ['config.php', 'functions.php', 'analytics.php'];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Error: No se encuentra el archivo {$file}");
    }
}

require_once 'config.php';
require_once 'functions.php';
require_once 'analytics.php';

if (!isset($pdo)) {
    die("Error: No hay conexión a la base de datos");
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$url_id = isset($_GET['url_id']) ? (int)$_GET['url_id'] : 0;
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Definir quién es superadmin (puedes ajustar según tu sistema)
$is_superadmin = false;
// Opción 1: Por ID de usuario (usuario 1 es superadmin)
if ($user_id == 1) {
    $is_superadmin = true;
}
// Opción 2: Por rol en sesión
if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin') {
    $is_superadmin = true;
}
// Opción 3: Verificar en base de datos
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    if ($user_data && ($user_data['role'] == 'admin' || $user_data['role'] == 'superadmin')) {
        $is_superadmin = true;
    }
} catch (Exception $e) {
    // Si no existe columna role, ignorar
}

if (!$url_id) {
    die("Error: No se especificó ID de URL. Use: analytics_url.php?url_id=X");
}

// Obtener información básica de la URL
try {
    // Si es superadmin, puede ver cualquier URL
    if ($is_superadmin) {
        $stmt = $pdo->prepare("
            SELECT u.*, cd.domain, us.username as owner_username, us.email as owner_email
            FROM urls u 
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
            LEFT JOIN users us ON u.user_id = us.id
            WHERE u.id = ?
        ");
        $stmt->execute([$url_id]);
    } else {
        // Usuario normal solo puede ver sus propias URLs
        $stmt = $pdo->prepare("
            SELECT u.*, cd.domain 
            FROM urls u 
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
            WHERE u.id = ? AND u.user_id = ?
        ");
        $stmt->execute([$url_id, $user_id]);
    }
    
    $url = $stmt->fetch();
    
    if (!$url) {
        // Verificar si la URL existe pero no tiene permisos
        $check_stmt = $pdo->prepare("SELECT id, user_id FROM urls WHERE id = ?");
        $check_stmt->execute([$url_id]);
        $check_url = $check_stmt->fetch();
        
        if ($check_url) {
            die("Error: No tienes permisos para ver las estadísticas de esta URL.");
        } else {
            die("Error: La URL solicitada no existe.");
        }
    }
    
} catch (Exception $e) {
    die("Error al buscar URL: " . $e->getMessage());
}

// Verificar si existe la tabla url_analytics
$analytics_table_exists = false;
try {
    $result = $pdo->query("SHOW TABLES LIKE 'url_analytics'");
    if ($result->fetch()) {
        $analytics_table_exists = true;
    }
} catch (Exception $e) {
    // Tabla no existe
}

// Inicializar estadísticas
$stats = null;
$has_detailed_analytics = false;

// Si existe la tabla url_analytics, intentar obtener datos detallados
if ($analytics_table_exists) {
    $analytics = new UrlAnalytics($pdo);
    try {
        // Usar el user_id del propietario real de la URL, no del viewer
        $stats = $analytics->getUrlStats($url_id, $url['user_id'], $days);
        if ($stats && ($stats['general']['total_clicks'] ?? 0) > 0) {
            $has_detailed_analytics = true;
        }
    } catch (Exception $e) {
        // Error al obtener analytics
    }
}

// Si no hay analytics detallados, crear estructura con datos básicos
if (!$has_detailed_analytics) {
    // Obtener clicks básicos de la tabla urls
    $basic_clicks = (int)($url['clicks'] ?? 0);
    
    // Intentar obtener algunos datos básicos de click_stats si existe
    $click_stats_data = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(clicked_at) as date,
                COUNT(*) as clicks
            FROM click_stats
            WHERE url_id = ? 
            AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(clicked_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$url_id, $days]);
        $click_stats_data = $stmt->fetchAll();
    } catch (Exception $e) {
        // Tabla click_stats no existe o error
    }
    
    // Construir estructura de stats con datos básicos
    $stats = [
        'url_info' => $url,
        'general' => [
            'total_clicks' => $basic_clicks,
            'unique_visitors' => 0,
            'unique_ips' => 0,
            'first_click' => null,
            'last_click' => null
        ],
        'daily_clicks' => $click_stats_data,
        'hourly_clicks' => [],
        'referrers' => [],
        'countries' => [],
        'period_days' => $days
    ];
}

// Determinar la URL completa
if (!empty($url['domain'])) {
    $short_url = "https://" . $url['domain'] . "/" . $url['short_code'];
} else {
    $base_url = defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST'];
    $short_url = rtrim($base_url, '/') . '/' . $url['short_code'];
}

// Preparar datos para gráficos
$daily_labels = [];
$daily_data = [];
$daily_unique = [];

if (!empty($stats['daily_clicks'])) {
    foreach ($stats['daily_clicks'] as $day) {
        $daily_labels[] = date('d M', strtotime($day['date']));
        $daily_data[] = (int)$day['clicks'];
        $daily_unique[] = (int)($day['unique_visitors'] ?? 0);
    }
}

// Si no hay datos diarios pero sí clicks totales, crear datos simulados
if (empty($daily_data) && $stats['general']['total_clicks'] > 0) {
    // Mostrar al menos que hay clicks acumulados
    $today = date('d M');
    $daily_labels = [$today];
    $daily_data = [$stats['general']['total_clicks']];
    $daily_unique = [0];
}

// Preparar datos por hora
$hourly_labels = [];
$hourly_data = [];

// Inicializar todas las horas con 0
for ($i = 0; $i < 24; $i++) {
    $hourly_labels[] = sprintf('%02d:00', $i);
    $hourly_data[$i] = 0;
}

// Llenar con datos reales
if (!empty($stats['hourly_clicks'])) {
    foreach ($stats['hourly_clicks'] as $hour) {
        $hourly_data[(int)$hour['hour']] = (int)$hour['clicks'];
    }
}

// Verificar si hay datos geográficos para el mapa
$has_geo_data = false;
if ($has_detailed_analytics) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM url_analytics 
        WHERE url_id = ? 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
    ");
    $stmt->execute([$url_id]);
    $has_geo_data = $stmt->fetchColumn() > 0;
}

$pageTitle = "Estadísticas: " . ($url['title'] ?: $url['short_code']);
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Marcadores';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Fix global para prevenir overflow */
        * {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark: #1e293b;
            --light: #f1f5f9;
        }
        
        body {
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
        }
        
        .navbar {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,.08);
            padding: 1rem 0;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            overflow-x: hidden;
        }
        
        /* Header de URL */
        .url-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        
        .url-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .url-original {
            color: #64748b;
            font-size: 0.9rem;
            word-break: break-all;
            margin-bottom: 1rem;
        }
        
        .url-short {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .url-short-link {
            font-family: monospace;
            font-size: 1.1rem;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .url-short-link:hover {
            color: var(--secondary-color);
        }
        
        /* Badge de admin */
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .owner-info {
            background: #f0f4f8;
            border-left: 4px solid var(--info-color);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon.blue { background: #dbeafe; color: #3b82f6; }
        .stat-icon.green { background: #d1fae5; color: #10b981; }
        .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-icon.orange { background: #fed7aa; color: #ea580c; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        /* Charts - CORREGIDO */
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            position: relative;
            overflow: hidden;
        }
        
        .chart-container canvas {
            max-height: 300px !important;
            width: 100% !important;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Tablas - CORREGIDO */
        .data-table {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .table-custom {
            margin: 0;
            table-layout: fixed;
            width: 100%;
        }
        
        .table-custom th {
            border-bottom: 2px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .table-custom td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Primera columna más ancha */
        .table-custom th:first-child,
        .table-custom td:first-child {
            width: 50%;
            white-space: normal;
            word-break: break-word;
        }
        
        .country-flag {
            width: 24px;
            height: 18px;
            margin-right: 0.5rem;
            border-radius: 4px;
            object-fit: cover;
        }
        
        /* Progress bars - CORREGIDO */
        .progress-custom {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
            position: relative;
            width: 100%;
        }
        
        .progress-bar-custom {
            background: var(--primary-color);
            height: 100%;
            transition: width 0.3s ease;
            position: absolute;
            top: 0;
            left: 0;
            border-radius: 4px;
        }
        
        /* Contenedor de porcentaje */
        .percentage-cell {
            position: relative;
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .limited-data-notice {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .url-short {
                flex-direction: column;
                text-align: center;
            }
            
            .table-custom {
                font-size: 0.875rem;
            }
            
            .table-custom th,
            .table-custom td {
                padding: 0.5rem;
            }
        }
        
        /* Filters */
        .filters {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        
        /* Map iframe */
        .map-iframe {
            width: 100%;
            height: 400px;
            border: none;
            border-radius: 8px;
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-bookmark-star-fill text-primary"></i> <?php echo $siteName; ?>
            </a>
            <div class="ms-auto">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>
    
    <div class="main-container">
        <?php if ($is_superadmin && $url['user_id'] != $user_id): ?>
        <!-- Badge de superadmin viendo URL de otro usuario -->
        <div class="admin-badge">
            <i class="bi bi-shield-check"></i>
            <span>Vista de Superadmin</span>
        </div>
        
        <!-- Información del propietario -->
        <div class="owner-info">
            <strong>Propietario:</strong> 
            <?php if (isset($url['owner_username'])): ?>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($url['owner_username']); ?>
                (<?php echo htmlspecialchars($url['owner_email']); ?>)
            <?php else: ?>
                Usuario ID: <?php echo $url['user_id']; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Header de URL -->
        <div class="url-header">
            <h1 class="url-title">
                <?php echo htmlspecialchars($url['title'] ?: 'Sin título'); ?>
            </h1>
            <div class="url-original">
                <i class="bi bi-link-45deg"></i>
                <?php echo htmlspecialchars($url['original_url']); ?>
            </div>
            <div class="url-short">
                <div>
                    <a href="<?php echo $short_url; ?>" target="_blank" class="url-short-link">
                        <?php echo $short_url; ?>
                    </a>
                </div>
                <div>
                    <button class="btn btn-sm btn-primary" onclick="copyToClipboard('<?php echo $short_url; ?>')">
                        <i class="bi bi-clipboard"></i> Copiar
                    </button>
                    <a href="<?php echo $short_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right"></i> Abrir
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filtros de período -->
        <div class="filters">
            <div>
                <div class="btn-group" role="group">
                    <a href="?url_id=<?php echo $url_id; ?>&days=7" 
                       class="btn btn-sm <?php echo $days == 7 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        7 días
                    </a>
                    <a href="?url_id=<?php echo $url_id; ?>&days=30" 
                       class="btn btn-sm <?php echo $days == 30 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        30 días
                    </a>
                    <a href="?url_id=<?php echo $url_id; ?>&days=90" 
                       class="btn btn-sm <?php echo $days == 90 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        90 días
                    </a>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <?php if ($has_geo_data): ?>
                <a href="geo_map.php?url_id=<?php echo $url_id; ?>" 
                   class="btn btn-sm btn-success" 
                   target="_blank"
                   title="Ver mapa mundial de clicks">
                    <i class="bi bi-geo-alt-fill"></i> Ver Mapa Mundial
                </a>
                <?php endif; ?>
                
                <?php if ($is_superadmin): ?>
                <a href="admin_dashboard.php" 
                   class="btn btn-sm btn-warning" 
                   title="Panel de administración">
                    <i class="bi bi-speedometer2"></i> Admin Panel
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!$has_detailed_analytics && $stats['general']['total_clicks'] > 0): ?>
        <!-- Aviso de datos limitados -->
        <div class="limited-data-notice">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong>Datos limitados disponibles</strong><br>
                <small>Se muestran estadísticas básicas. El tracking detallado comenzará con los próximos clicks.</small>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-cursor-fill"></i>
                </div>
                <div class="stat-value">
                    <?php echo number_format($stats['general']['total_clicks'] ?? 0); ?>
                </div>
                <div class="stat-label">Clicks totales</div>
                <?php if ($stats['general']['total_clicks'] > 0 && !$has_detailed_analytics): ?>
                <small class="text-warning">
                    <i class="bi bi-exclamation-circle"></i> Contador histórico
                </small>
                <?php endif; ?>
            </div>
            
            <?php if ($has_detailed_analytics): ?>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-value">
                    <?php echo number_format($stats['general']['unique_visitors'] ?? 0); ?>
                </div>
                <div class="stat-label">Visitantes únicos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-globe2"></i>
                </div>
                <div class="stat-value">
                    <?php echo number_format($stats['general']['unique_ips'] ?? 0); ?>
                </div>
                <div class="stat-label">IPs únicas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    $avg_daily = count($stats['daily_clicks']) > 0 
                        ? round(($stats['general']['total_clicks'] ?? 0) / count($stats['daily_clicks'])) 
                        : 0;
                    echo number_format($avg_daily);
                    ?>
                </div>
                <div class="stat-label">Promedio diario</div>
            </div>
            <?php else: ?>
            <!-- Mostrar placeholders cuando no hay datos detallados -->
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-value">-</div>
                <div class="stat-label">Visitantes únicos</div>
                <small class="text-muted">Sin datos</small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-globe2"></i>
                </div>
                <div class="stat-value">-</div>
                <div class="stat-label">IPs únicas</div>
                <small class="text-muted">Sin datos</small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-value">
                    <?php echo date('d/m/Y', strtotime($url['created_at'])); ?>
                </div>
                <div class="stat-label">Fecha de creación</div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($has_detailed_analytics && !empty($stats['daily_clicks'])): ?>
        
        <!-- Gráfico de clicks por día -->
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-graph-up text-primary"></i>
                    Clicks por día
                </h3>
            </div>
            <canvas id="dailyChart" style="max-height: 300px;"></canvas>
        </div>
        
        <?php if (!empty($stats['hourly_clicks'])): ?>
        <!-- Gráfico de clicks por hora -->
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-clock text-primary"></i>
                    Distribución por hora (últimos 7 días)
                </h3>
            </div>
            <canvas id="hourlyChart" style="max-height: 250px;"></canvas>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Referrers -->
            <div class="col-lg-6 mb-4">
                <div class="data-table h-100">
                    <h3 class="chart-title mb-3">
                        <i class="bi bi-box-arrow-in-right text-primary"></i>
                        Fuentes de tráfico
                    </h3>
                    <?php if (!empty($stats['referrers'])): ?>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Fuente</th>
                                    <th style="width: 80px;">Clicks</th>
                                    <th style="width: 120px;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($stats['referrers'], 0, 10) as $ref): ?>
                                <?php 
                                $percentage = ($ref['clicks'] / $stats['general']['total_clicks']) * 100;
                                ?>
                                <tr>
                                    <td style="white-space: normal; word-break: break-word;">
                                        <i class="bi bi-globe2 text-muted me-2"></i>
                                        <?php echo htmlspecialchars($ref['referer_domain']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($ref['clicks']); ?>
                                        </span>
                                    </td>
                                    <td class="percentage-cell">
                                        <div style="position: relative;">
                                            <div class="progress-custom">
                                                <div class="progress-bar-custom" 
                                                     style="width: <?php echo min($percentage, 100); ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted" style="display: block; margin-top: 4px;">
                                                <?php echo round($percentage, 1); ?>%
                                            </small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No hay datos de referencia disponibles</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Países -->
            <div class="col-lg-6 mb-4">
                <div class="data-table h-100">
                    <h3 class="chart-title mb-3">
                        <i class="bi bi-geo-alt text-primary"></i>
                        Ubicaciones
                    </h3>
                    <?php if (!empty($stats['countries'])): ?>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>País</th>
                                    <th style="width: 80px;">Clicks</th>
                                    <th style="width: 120px;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($stats['countries'], 0, 10) as $country): ?>
                                <?php 
                                $percentage = ($country['clicks'] / $stats['general']['total_clicks']) * 100;
                                ?>
                                <tr>
                                    <td style="white-space: normal;">
                                        <?php if (!empty($country['country_code'])): ?>
                                        <img src="https://flagcdn.com/24x18/<?php echo strtolower($country['country_code']); ?>.png" 
                                             alt="<?php echo $country['country_code']; ?>"
                                             class="country-flag"
                                             onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($country['country'] ?: 'Desconocido'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($country['clicks']); ?>
                                        </span>
                                    </td>
                                    <td class="percentage-cell">
                                        <div style="position: relative;">
                                            <div class="progress-custom">
                                                <div class="progress-bar-custom" 
                                                     style="width: <?php echo min($percentage, 100); ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted" style="display: block; margin-top: 4px;">
                                                <?php echo round($percentage, 1); ?>%
                                            </small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No hay datos de ubicación disponibles</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($has_geo_data): ?>
        <!-- Sección del Mapa -->
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="bi bi-globe-americas text-primary"></i>
                    Mapa de Visitantes
                </h3>
                <a href="geo_map.php?url_id=<?php echo $url_id; ?>" 
                   class="btn btn-sm btn-outline-primary"
                   target="_blank">
                    <i class="bi bi-arrows-fullscreen"></i> Ver completo
                </a>
            </div>
            
            <!-- Mini mapa embebido -->
            <div style="position: relative;">
                <iframe 
                    src="geo_map.php?url_id=<?php echo $url_id; ?>&embed=1" 
                    class="map-iframe"
                    loading="lazy">
                </iframe>
            </div>
        </div>
        <?php endif; ?>
        
        <?php elseif ($stats['general']['total_clicks'] > 0): ?>
        
        <!-- Vista simplificada cuando solo hay contador básico -->
        <div class="chart-container">
            <div class="text-center py-5">
                <i class="bi bi-graph-up" style="font-size: 3rem; color: #e2e8f0;"></i>
                <h4 class="mt-3">Estadísticas detalladas no disponibles</h4>
                <p class="text-muted">
                    Esta URL tiene <strong><?php echo number_format($stats['general']['total_clicks']); ?> clicks acumulados</strong>.<br>
                    Las estadísticas detalladas estarán disponibles con los nuevos clicks.
                </p>
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- Estado vacío -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="bi bi-bar-chart"></i>
            </div>
            <h3>No hay datos de estadísticas todavía</h3>
            <p>Las estadísticas aparecerán cuando esta URL reciba clicks.</p>
            <div class="mt-4">
                <button class="btn btn-primary" onclick="copyToClipboard('<?php echo $short_url; ?>')">
                    <i class="bi bi-share"></i> Compartir URL
                </button>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('URL copiada al portapapeles');
            });
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showNotification('URL copiada al portapapeles');
            } catch (error) {
                console.error('Error al copiar:', error);
            }
            document.body.removeChild(textArea);
        }
    }
    
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-success position-fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.innerHTML = `<i class="bi bi-check-circle me-2"></i>${message}`;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    <?php if ($has_detailed_analytics && !empty($stats['daily_clicks'])): ?>
    // Gráficos solo si hay datos detallados
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    
    // Configuración para limitar altura
    const chartConfig = {
        responsive: true,
        maintainAspectRatio: false,
        onResize: function(chart, size) {
            if (size.height > 300) {
                chart.canvas.parentNode.style.height = '300px';
            }
        }
    };
    
    // Gráfico de clicks por día
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($daily_labels); ?>,
            datasets: [{
                label: 'Clicks totales',
                data: <?php echo json_encode($daily_data); ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#4f46e5',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }<?php if (array_sum($daily_unique) > 0): ?>, {
                label: 'Visitantes únicos',
                data: <?php echo json_encode($daily_unique); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }<?php endif; ?>]
        },
        options: {
            ...chartConfig,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    <?php if (!empty($stats['hourly_clicks'])): ?>
    // Gráfico de clicks por hora
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($hourly_labels); ?>,
            datasets: [{
                label: 'Clicks',
                data: <?php echo json_encode(array_values($hourly_data)); ?>,
                backgroundColor: '#4f46e5',
                borderRadius: 6,
                maxBarThickness: 30
            }]
        },
        options: {
            ...chartConfig,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' clicks';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    <?php endif; ?>
    <?php endif; ?>
    </script>
</body>
</html>
<?php
function timeAgo($datetime) {
    if (empty($datetime)) return '';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' segundos';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' ' . ($mins == 1 ? 'minuto' : 'minutos');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hora' : 'horas');
    } else {
        $days = floor($diff / 86400);
        return $days . ' ' . ($days == 1 ? 'día' : 'días');
    }
}
?>
