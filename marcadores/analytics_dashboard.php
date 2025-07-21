<?php
// analytics_dashboard.php - Dashboard de analytics MEJORADO
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

$user_id = getCurrentUserId();
if (!$user_id) {
    header('Location: ../admin/login.php');
    exit;
}

$userInfo = getCurrentUserInfo();
$days = (int)($_GET['days'] ?? 30);

// Validar días
$allowedDays = [7, 30, 90, 180, 365];
if (!in_array($days, $allowedDays)) {
    $days = 30;
}

// Debug mode
$debug = isset($_GET['debug']);

try {
    // SISTEMA MEJORADO: Verificar qué datos tenemos disponibles
    $useAnalyticsTable = false;
    $analytics_data = [];
    $summary = ['total_clicks' => 0, 'unique_visitors' => 0, 'urls_clicked' => 0, 'active_days' => 0];
    
    // VERIFICACIÓN 1: ¿Existe la tabla url_analytics?
    $table_exists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'url_analytics'");
        if ($check->fetch()) {
            $table_exists = true;
        }
    } catch (Exception $e) {
        $table_exists = false;
    }
    
    if ($debug) {
        echo "<!-- DEBUG: Tabla url_analytics existe: " . ($table_exists ? 'SÍ' : 'NO') . " -->\n";
    }
    
    // VERIFICACIÓN 2: Si existe la tabla, ¿tiene datos para este usuario?
    $analytics_count = 0;
    if ($table_exists) {
        try {
            // Primero verificar si hay CUALQUIER dato en analytics
            $stmt = $pdo->query("SELECT COUNT(*) FROM url_analytics");
            $total_analytics = $stmt->fetchColumn();
            
            if ($debug) {
                echo "<!-- DEBUG: Total registros en url_analytics: {$total_analytics} -->\n";
            }
            
            // Luego verificar datos del usuario (sin restricción de fecha primero)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM url_analytics WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user_analytics_total = $stmt->fetchColumn();
            
            if ($debug) {
                echo "<!-- DEBUG: Registros del usuario {$user_id}: {$user_analytics_total} -->\n";
            }
            
            // Ahora sí, con restricción de fecha
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM url_analytics 
                WHERE user_id = ? 
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$user_id, $days]);
            $analytics_count = $stmt->fetchColumn();
            
            if ($debug) {
                echo "<!-- DEBUG: Registros en período de {$days} días: {$analytics_count} -->\n";
            }
            
            // Si no hay datos en el período pero sí hay datos totales, ampliar el rango
            if ($analytics_count == 0 && $user_analytics_total > 0) {
                // Buscar el rango real de datos
                $stmt = $pdo->prepare("
                    SELECT 
                        MIN(clicked_at) as first_click,
                        MAX(clicked_at) as last_click,
                        COUNT(*) as total
                    FROM url_analytics 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                $date_range = $stmt->fetch();
                
                if ($debug && $date_range) {
                    echo "<!-- DEBUG: Rango real de datos: {$date_range['first_click']} a {$date_range['last_click']} ({$date_range['total']} clicks) -->\n";
                }
                
                // Usar todos los datos disponibles
                $analytics_count = $user_analytics_total;
                $use_all_data = true;
            }
            
        } catch (Exception $e) {
            if ($debug) {
                echo "<!-- DEBUG: Error verificando analytics: " . $e->getMessage() . " -->\n";
            }
            $analytics_count = 0;
        }
    }
    
    // PASO 3: Si hay datos en analytics, usarlos
    if ($analytics_count > 0) {
        $useAnalyticsTable = true;
        
        // Ajustar query según disponibilidad de datos
        $date_condition = isset($use_all_data) && $use_all_data 
            ? "" // Sin restricción de fecha
            : "AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        // Obtener resumen de analytics
        $sql = "
            SELECT 
                COUNT(*) as total_clicks,
                COUNT(DISTINCT session_id) as unique_visitors,
                COUNT(DISTINCT url_id) as urls_clicked,
                COUNT(DISTINCT DATE(clicked_at)) as active_days
            FROM url_analytics 
            WHERE user_id = ? 
            {$date_condition}
        ";
        
        $stmt = $pdo->prepare($sql);
        if (isset($use_all_data) && $use_all_data) {
            $stmt->execute([$user_id]);
        } else {
            $stmt->execute([$user_id, $days]);
        }
        $summary = $stmt->fetch();
        
        // Obtener datos detallados
        $sql = "
            SELECT 
                ua.*,
                u.short_code,
                u.original_url,
                u.title
            FROM url_analytics ua
            JOIN urls u ON ua.url_id = u.id
            WHERE ua.user_id = ? 
            {$date_condition}
            ORDER BY ua.clicked_at DESC
            LIMIT 100
        ";
        
        $stmt = $pdo->prepare($sql);
        if (isset($use_all_data) && $use_all_data) {
            $stmt->execute([$user_id]);
        } else {
            $stmt->execute([$user_id, $days]);
        }
        $analytics_data = $stmt->fetchAll();
    }
    
    // PASO 4: FALLBACK - Usar datos de tabla URLs si no hay analytics
    if (!$useAnalyticsTable) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_urls,
                SUM(clicks) as total_clicks,
                COUNT(CASE WHEN clicks > 0 THEN 1 END) as active_urls,
                AVG(clicks) as avg_clicks
            FROM urls 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $urls_summary = $stmt->fetch();
        
        $summary = [
            'total_clicks' => (int)($urls_summary['total_clicks'] ?? 0),
            'unique_visitors' => (int)($urls_summary['total_clicks'] ?? 0),
            'urls_clicked' => (int)($urls_summary['active_urls'] ?? 0),
            'active_days' => min($days, 30)
        ];
    }
    
    // Obtener top URLs (siempre de tabla urls)
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.short_code,
            u.original_url,
            u.title,
            u.clicks,
            u.created_at,
            cd.domain as custom_domain
        FROM urls u
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id
        WHERE u.user_id = ?
        ORDER BY u.clicks DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $topUrls = $stmt->fetchAll();
    
    // Obtener datos para gráficos por día
    $dailyStats = [];
    if ($useAnalyticsTable) {
        $sql = "
            SELECT 
                DATE(clicked_at) as date,
                COUNT(*) as clicks,
                COUNT(DISTINCT session_id) as unique_visitors
            FROM url_analytics 
            WHERE user_id = ? 
            " . (isset($use_all_data) && $use_all_data ? "" : "AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)") . "
            GROUP BY DATE(clicked_at)
            ORDER BY date DESC
            LIMIT 30
        ";
        
        $stmt = $pdo->prepare($sql);
        if (isset($use_all_data) && $use_all_data) {
            $stmt->execute([$user_id]);
        } else {
            $stmt->execute([$user_id, $days]);
        }
        $dailyStats = $stmt->fetchAll();
        
        // Invertir para mostrar cronológicamente
        $dailyStats = array_reverse($dailyStats);
    }
    
    // Obtener países
    $countries = [];
    if ($useAnalyticsTable) {
        $sql = "
            SELECT 
                COALESCE(country, 'Desconocido') as country,
                COUNT(*) as clicks,
                COUNT(DISTINCT session_id) as unique_visitors
            FROM url_analytics 
            WHERE user_id = ? 
            " . (isset($use_all_data) && $use_all_data ? "" : "AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)") . "
            GROUP BY country
            ORDER BY clicks DESC
            LIMIT 10
        ";
        
        $stmt = $pdo->prepare($sql);
        if (isset($use_all_data) && $use_all_data) {
            $stmt->execute([$user_id]);
        } else {
            $stmt->execute([$user_id, $days]);
        }
        $countries = $stmt->fetchAll();
    }
    
    // Obtener dispositivos
    $devices = [];
    if ($useAnalyticsTable) {
        $sql = "
            SELECT 
                COALESCE(device_type, 'Desconocido') as device_type,
                COUNT(*) as clicks
            FROM url_analytics 
            WHERE user_id = ? 
            " . (isset($use_all_data) && $use_all_data ? "" : "AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)") . "
            GROUP BY device_type
            ORDER BY clicks DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        if (isset($use_all_data) && $use_all_data) {
            $stmt->execute([$user_id]);
        } else {
            $stmt->execute([$user_id, $days]);
        }
        $devices = $stmt->fetchAll();
    }
    
    // Si estamos usando todos los datos, calcular el período real
    if (isset($use_all_data) && $use_all_data && isset($date_range)) {
        $real_days = ceil((time() - strtotime($date_range['first_click'])) / 86400);
        $data_notice = "Mostrando todos los datos históricos ({$real_days} días)";
    } else {
        $data_notice = null;
    }
    
} catch (Exception $e) {
    $error = "Error obteniendo datos: " . $e->getMessage();
    if ($debug) {
        echo "<!-- DEBUG: Error general: " . $e->getMessage() . " -->\n";
    }
    $summary = ['total_clicks' => 0, 'unique_visitors' => 0, 'urls_clicked' => 0, 'active_days' => 0];
    $topUrls = [];
    $dailyStats = [];
    $countries = [];
    $devices = [];
    $useAnalyticsTable = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Analytics Dashboard - <?= htmlspecialchars($userInfo['username']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
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
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn-header {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .dashboard-title {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .period-selector {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .period-btn {
            padding: 8px 16px;
            border: 2px solid #e9ecef;
            background: white;
            color: #495057;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .period-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .period-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        
        .data-source {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #495057;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        
        .data-source.warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .chart-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.5em;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .btn-analytics {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85em;
            transition: all 0.3s;
        }
        
        .btn-analytics:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px 25px;
            text-align: left;
            border-bottom: 1px solid #f1f3f5;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .url-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .url-short {
            font-family: monospace;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .clicks-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .debug-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #1565c0;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 0.85em;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .dashboard-title {
                font-size: 2em;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="../" class="logo">
                <span>📊</span>
                <span>Analytics Dashboard</span>
            </a>
            <div class="header-actions">
                <a href="../" class="btn-header">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <a href="index.php" class="btn-header">
                    <i class="fas fa-link"></i> Gestor URLs
                </a>
                <?php if ($debug): ?>
                <a href="?days=<?= $days ?>" class="btn-header">
                    <i class="fas fa-bug"></i> Salir Debug
                </a>
                <?php else: ?>
                <a href="?days=<?= $days ?>&debug=1" class="btn-header">
                    <i class="fas fa-bug"></i> Debug
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <span>📊</span>
                Analytics - <?= htmlspecialchars($userInfo['username']) ?>
            </h1>
            <p style="color: #6c757d; font-size: 1.1em;">
                Análisis detallado de tus URLs acortadas
            </p>
            
            <div class="period-selector">
                <?php foreach ($allowedDays as $periodDays): ?>
                <a href="?days=<?= $periodDays ?><?= $debug ? '&debug=1' : '' ?>" 
                   class="period-btn <?= $days == $periodDays ? 'active' : '' ?>">
                    <?= $periodDays ?> días
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($debug): ?>
        <div class="debug-info">
            <strong>🐛 DEBUG MODE</strong><br>
            User ID: <?= $user_id ?><br>
            Analytics disponible: <?= $useAnalyticsTable ? 'SÍ' : 'NO' ?><br>
            Total clicks encontrados: <?= $summary['total_clicks'] ?><br>
            Período solicitado: <?= $days ?> días
        </div>
        <?php endif; ?>
        
        <!-- Data Source Info -->
        <div class="data-source <?= isset($data_notice) ? 'warning' : '' ?>">
            <strong>ℹ️ Fuente de datos:</strong> 
            <?php if ($useAnalyticsTable): ?>
                Analytics detallado 
                <?= isset($data_notice) ? "- {$data_notice}" : "(últimos {$days} días)" ?>
            <?php else: ?>
                Datos básicos de URLs (para ver analytics detallado, activa el tracking)
            <?php endif; ?>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👆</div>
                <div class="stat-value"><?= number_format($summary['total_clicks']) ?></div>
                <div class="stat-label">Total Clicks</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?= number_format($summary['unique_visitors']) ?></div>
                <div class="stat-label">Visitantes Únicos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔗</div>
                <div class="stat-value"><?= number_format($summary['urls_clicked']) ?></div>
                <div class="stat-label">URLs Clickeadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= number_format($summary['active_days']) ?></div>
                <div class="stat-label">Días Activos</div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <h2 class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Clicks por Día
                </h2>
                <?php if (!empty($dailyStats)): ?>
                <canvas id="dailyChart" width="400" height="200"></canvas>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No hay datos para mostrar</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($countries)): ?>
            <div class="chart-container">
                <h2 class="chart-title">
                    <i class="fas fa-globe"></i>
                    Top Países
                </h2>
                <canvas id="countriesChart" width="400" height="200"></canvas>
            </div>
            <?php elseif (!empty($devices)): ?>
            <div class="chart-container">
                <h2 class="chart-title">
                    <i class="fas fa-mobile-alt"></i>
                    Dispositivos
                </h2>
                <canvas id="devicesChart" width="400" height="200"></canvas>
            </div>
            <?php else: ?>
            <div class="chart-container">
                <h2 class="chart-title">
                    <i class="fas fa-info-circle"></i>
                    Analytics Detallado
                </h2>
                <div class="no-data">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Datos limitados</h3>
                    <p>Active el tracking para ver estadísticas detalladas de países, dispositivos y más.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Top URLs Table -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">🏆 Top URLs por Clicks</h2>
            </div>
            
            <?php if (!empty($topUrls)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Posición</th>
                        <th>Código</th>
                        <th>URL Original</th>
                        <th>Clicks</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topUrls as $index => $url): ?>
                    <tr>
                        <td><strong>#<?= $index + 1 ?></strong></td>
                        <td>
                            <span class="url-short"><?= htmlspecialchars($url['short_code']) ?></span>
                        </td>
                        <td class="url-cell" title="<?= htmlspecialchars($url['original_url']) ?>">
                            <?= htmlspecialchars($url['original_url']) ?>
                        </td>
                        <td>
                            <span class="clicks-badge"><?= number_format($url['clicks']) ?></span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($url['created_at'])) ?></td>
                        <td>
                            <a href="analytics_url.php?url_id=<?= $url['id'] ?>" class="btn-analytics">
                                <i class="fas fa-chart-line"></i> Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-link"></i>
                <h3>No hay URLs</h3>
                <p>Crea tu primera URL desde la página principal.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($analytics_data)): ?>
        <!-- Recent Activity -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">📋 Actividad Reciente</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Código</th>
                        <th>País</th>
                        <th>Dispositivo</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($analytics_data, 0, 20) as $activity): ?>
                    <tr>
                        <td><?= date('d/m H:i', strtotime($activity['clicked_at'])) ?></td>
                        <td><span class="url-short"><?= htmlspecialchars($activity['short_code']) ?></span></td>
                        <td><?= htmlspecialchars($activity['country'] ?? 'Desconocido') ?></td>
                        <td><?= htmlspecialchars($activity['device_type'] ?? 'Desconocido') ?></td>
                        <td><?= htmlspecialchars($activity['browser'] ?? 'Desconocido') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Chart.js para gráficos
        const dailyData = <?= json_encode($dailyStats) ?>;
        const countriesData = <?= json_encode($countries) ?>;
        const devicesData = <?= json_encode($devices) ?>;
        
        // Gráfico de clicks diarios
        if (dailyData.length > 0 && document.getElementById('dailyChart')) {
            const ctx = document.getElementById('dailyChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailyData.map(d => {
                        const date = new Date(d.date);
                        return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
                    }),
                    datasets: [{
                        label: 'Clicks',
                        data: dailyData.map(d => d.clicks),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Visitantes únicos',
                        data: dailyData.map(d => d.unique_visitors || 0),
                        borderColor: '#764ba2',
                        backgroundColor: 'rgba(118, 75, 162, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Gráfico de países
        if (countriesData.length > 0 && document.getElementById('countriesChart')) {
            const ctx2 = document.getElementById('countriesChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: countriesData.map(c => c.country),
                    datasets: [{
                        data: countriesData.map(c => c.clicks),
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#f5576c',
                            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
                            '#fa709a', '#fee140'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Gráfico de dispositivos
        if (devicesData.length > 0 && document.getElementById('devicesChart')) {
            const ctx3 = document.getElementById('devicesChart').getContext('2d');
            new Chart(ctx3, {
                type: 'pie',
                data: {
                    labels: devicesData.map(d => d.device_type),
                    datasets: [{
                        data: devicesData.map(d => d.clicks),
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#f5576c'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        console.log('📊 Analytics Dashboard cargado');
        console.log('Datos de analytics:', <?= $useAnalyticsTable ? 'true' : 'false' ?>);
        console.log('Total clicks:', <?= $summary['total_clicks'] ?>);
    </script>
</body>
</html>
