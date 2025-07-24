<?php
session_start();
setcookie(session_name(), session_id(), time() + 12960000, '/');
// NO detectar subdirectorios - usar rutas absolutas desde la raíz
require_once __DIR__ . '/conf.php';

// Obtener el código corto
$shortCode = $_GET['code'] ?? '';
if (empty($shortCode)) {
    header('Location: /index.php');  // Ruta absoluta desde la raíz
    exit();
}

// Conectar a la base de datos
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Detectar desde qué dominio se está accediendo
$current_domain = $_SERVER['HTTP_HOST'];
$accessing_from_custom = false;

// Verificar si es un dominio personalizado
$stmt = $pdo->prepare("SELECT * FROM custom_domains WHERE domain = ? AND status = 'active'");
$stmt->execute([$current_domain]);
$custom_domain_info = $stmt->fetch();

if ($custom_domain_info) {
    $accessing_from_custom = true;
}

// Obtener información de la URL con el dominio personalizado
try {
    $stmt = $pdo->prepare("
        SELECT u.*, cd.domain as custom_domain 
        FROM urls u 
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
        WHERE u.short_code = ? AND u.active = 1
    ");
    $stmt->execute([$shortCode]);
    $url_data = $stmt->fetch();
} catch (PDOException $e) {
    // Si falla (no existe columna domain_id), usar consulta simple
    $stmt = $pdo->prepare("SELECT * FROM urls WHERE short_code = ? AND active = 1");
    $stmt->execute([$shortCode]);
    $url_data = $stmt->fetch();
}

if (!$url_data) {
    die("URL no encontrada");
}

// Determinar qué dominio usar para mostrar
if ($accessing_from_custom && $custom_domain_info['user_id'] == $url_data['user_id']) {
    $short_url_display = "https://" . $current_domain . "/" . $shortCode;
    $domain_used = $current_domain;
} elseif (!empty($url_data['custom_domain'])) {
    $short_url_display = "https://" . $url_data['custom_domain'] . "/" . $shortCode;
    $domain_used = $url_data['custom_domain'];
} else {
    // CORRECCIÓN: Usar la URL sin subdirectorios
    if (defined('BASE_URL')) {
        $short_url_display = rtrim(BASE_URL, '/') . '/' . $shortCode;
        $domain_used = parse_url(BASE_URL, PHP_URL_HOST);
    } else {
        // Si no hay BASE_URL definido, usar el dominio actual sin subdirectorios
        $short_url_display = 'https://0ln.eu/' . $shortCode;
        $domain_used = '0ln.eu';
    }
}

// Verificar si el usuario puede ver estadísticas completas
$can_view_full_stats = false;
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_id'] == $url_data['user_id'] || $_SESSION['user_id'] == 1) {
        $can_view_full_stats = true;
    }
}

// Obtener estadísticas básicas
$total_clicks = min($url_data['clicks'], 999999);

// Obtener estadísticas detalladas
$stmt = $pdo->prepare("
    SELECT 
        DATE(clicked_at) as click_date,
        COUNT(*) as daily_clicks
    FROM click_stats 
    WHERE url_id = ?
    GROUP BY DATE(clicked_at)
    ORDER BY click_date DESC
    LIMIT 30
");
$stmt->execute([$url_data['id']]);
$daily_stats = $stmt->fetchAll();

foreach ($daily_stats as &$stat) {
    $stat['daily_clicks'] = min((int)$stat['daily_clicks'], 10000);
}

// Estadísticas por dispositivo
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN user_agent LIKE '%Mobile%' THEN 'Móvil'
            WHEN user_agent LIKE '%Tablet%' THEN 'Tablet'
            ELSE 'Desktop'
        END as device_type,
        COUNT(*) as count
    FROM click_stats 
    WHERE url_id = ?
    GROUP BY device_type
    ORDER BY count DESC
");
$stmt->execute([$url_data['id']]);
$device_stats = $stmt->fetchAll();

// Estadísticas por hora del día
$stmt = $pdo->prepare("
    SELECT 
        HOUR(clicked_at) as hour,
        COUNT(*) as clicks
    FROM click_stats 
    WHERE url_id = ?
    GROUP BY HOUR(clicked_at)
    ORDER BY hour
");
$stmt->execute([$url_data['id']]);
$hourly_stats = $stmt->fetchAll();

foreach ($hourly_stats as &$stat) {
    $stat['clicks'] = min((int)$stat['clicks'], 5000);
}

// Obtener los últimos clicks
$stmt = $pdo->prepare("
    SELECT clicked_at, ip_address, user_agent
    FROM click_stats 
    WHERE url_id = ?
    ORDER BY clicked_at DESC
    LIMIT 20
");
$stmt->execute([$url_data['id']]);
$recent_clicks = $stmt->fetchAll();

// Preparar datos para gráficos
$dates = [];
$clicks = [];
foreach (array_reverse($daily_stats) as $stat) {
    $dates[] = date('d/m', strtotime($stat['click_date']));
    $clicks[] = (int)$stat['daily_clicks'];
}

$devices = [];
$device_counts = [];
foreach ($device_stats as $stat) {
    $devices[] = $stat['device_type'];
    $device_counts[] = min((int)$stat['count'], 999999);
}

// Debug info
$debug = isset($_GET['debug']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - <?php echo htmlspecialchars($shortCode); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .stats-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        .stat-card h3 {
            color: #007bff;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .url-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            position: relative;
            min-height: 300px;
        }
        .domain-badge {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
            margin-top: 10px;
        }
        .copy-btn {
            position: relative;
        }
        .copied-tooltip {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .copied-tooltip.show {
            opacity: 1;
        }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .device-icon {
            font-size: 1.2em;
            margin-right: 5px;
        }
        .click-row {
            transition: background-color 0.2s;
        }
        .click-row:hover {
            background-color: #f8f9fa;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        .url-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
            margin-bottom: 10px;
        }
        .domain-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        .access-indicator {
            background: #d1ecf1;
            color: #0c5460;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
        }
        canvas {
            max-height: 400px !important;
        }
    </style>
</head>
<body>
    <?php if (file_exists(__DIR__ . '/menu.php')) include __DIR__ . '/menu.php'; ?>
    
    <div class="stats-header">
        <div class="container text-center">
            <h1>📊 Estadísticas de URL</h1>
            <p class="mb-0">Análisis detallado del rendimiento de tu enlace</p>
        </div>
    </div>
    
    <div class="container">
        <?php if ($debug): ?>
        <div class="debug-info">
            <strong>DEBUG INFO:</strong><br>
            Dominio actual (HTTP_HOST): <?php echo htmlspecialchars($current_domain); ?><br>
            Es dominio personalizado: <?php echo $accessing_from_custom ? 'Sí' : 'No'; ?><br>
            Dominio guardado en URL: <?php echo htmlspecialchars($url_data['custom_domain'] ?? 'Ninguno'); ?><br>
            Dominio mostrado: <?php echo htmlspecialchars($domain_used); ?><br>
            Domain ID: <?php echo htmlspecialchars($url_data['domain_id'] ?? 'NULL'); ?>
        </div>
        <?php endif; ?>
        
        <!-- Información de la URL -->
        <div class="url-info">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="info-label">URL Original:</div>
                    <div class="url-display">
                        <a href="<?php echo htmlspecialchars($url_data['original_url']); ?>" target="_blank">
                            <?php echo htmlspecialchars($url_data['original_url']); ?>
                        </a>
                    </div>
                    
                    <div class="info-label">URL Corta:</div>
                    <div class="input-group mb-3" style="max-width: 500px;">
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($short_url_display); ?>" 
                               id="shortUrlInput" readonly>
                        <button class="btn btn-primary copy-btn" onclick="copyToClipboard()">
                            <i class="bi bi-clipboard"></i> Copiar
                            <span class="copied-tooltip" id="copiedTooltip">¡Copiado!</span>
                        </button>
                    </div>
                    
                    <div class="domain-info">
                        <div class="domain-badge">
                            <i class="bi bi-globe"></i> <?php echo htmlspecialchars($domain_used); ?>
                        </div>
                        <?php if ($accessing_from_custom): ?>
                        <div class="access-indicator">
                            <i class="bi bi-check-circle"></i> Accediendo desde dominio personalizado
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($url_data['custom_domain']) && $url_data['custom_domain'] != $domain_used): ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            También disponible en: 
                            <a href="https://<?php echo htmlspecialchars($url_data['custom_domain']); ?>/<?php echo htmlspecialchars($shortCode); ?>">
                                <?php echo htmlspecialchars($url_data['custom_domain']); ?>
                            </a>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-center">
                    <div id="qrcode"></div>
                    <button class="btn btn-sm btn-secondary mt-2" onclick="downloadQR()">
                        <i class="bi bi-download"></i> Descargar QR
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas básicas -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-cursor-fill" style="font-size: 2rem; color: #007bff;"></i>
                    <h3><?php echo number_format($total_clicks); ?></h3>
                    <p class="mb-0">Clicks Totales</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-calendar-check" style="font-size: 2rem; color: #28a745;"></i>
                    <h3><?php echo count($daily_stats); ?></h3>
                    <p class="mb-0">Días Activos</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-graph-up" style="font-size: 2rem; color: #ffc107;"></i>
                    <h3>
                        <?php 
                        $avg_clicks = count($daily_stats) > 0 ? round($total_clicks / count($daily_stats), 1) : 0;
                        echo number_format($avg_clicks, 1);
                        ?>
                    </h3>
                    <p class="mb-0">Promedio/Día</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-clock" style="font-size: 2rem; color: #dc3545;"></i>
                    <h3>
                        <?php 
                        $days_since = (strtotime('now') - strtotime($url_data['created_at'])) / 86400;
                        echo round($days_since);
                        ?>
                    </h3>
                    <p class="mb-0">Días Activo</p>
                </div>
            </div>
        </div>
        
        <?php if ($can_view_full_stats): ?>
        <!-- Gráficos detallados -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <h5>📈 Clicks por día (últimos 30 días)</h5>
                    <canvas id="dailyChart" style="max-height: 350px;"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h5>📱 Dispositivos</h5>
                    <canvas id="deviceChart" style="max-height: 200px;"></canvas>
                    <div class="mt-3">
                        <?php foreach ($device_stats as $device): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <?php if ($device['device_type'] === 'Móvil'): ?>
                                    <i class="bi bi-phone device-icon"></i>
                                <?php elseif ($device['device_type'] === 'Tablet'): ?>
                                    <i class="bi bi-tablet device-icon"></i>
                                <?php else: ?>
                                    <i class="bi bi-laptop device-icon"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($device['device_type']); ?>
                            </span>
                            <span class="badge bg-primary"><?php echo number_format($device['count']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="chart-container">
                    <h5>⏰ Distribución por hora del día</h5>
                    <canvas id="hourlyChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tabla de clicks recientes -->
        <div class="chart-container">
            <h5>🕐 Últimos 20 clicks</h5>
            
            <?php if ($recent_clicks): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>IP (Parcial)</th>
                            <th>Dispositivo</th>
                            <th>Navegador</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_clicks as $click): ?>
                        <tr class="click-row">
                            <td>
                                <i class="bi bi-clock text-muted"></i>
                                <?php echo date('d/m/Y H:i:s', strtotime($click['clicked_at'])); ?>
                            </td>
                            <td>
                                <?php 
                                $ip_parts = explode('.', $click['ip_address']);
                                if (count($ip_parts) >= 4) {
                                    echo htmlspecialchars($ip_parts[0] . '.' . $ip_parts[1] . '.***.' . $ip_parts[3]);
                                } else {
                                    echo 'IP no válida';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (strpos($click['user_agent'], 'Mobile') !== false) {
                                    echo '<i class="bi bi-phone text-success"></i> Móvil';
                                } elseif (strpos($click['user_agent'], 'Tablet') !== false) {
                                    echo '<i class="bi bi-tablet text-info"></i> Tablet';
                                } else {
                                    echo '<i class="bi bi-laptop text-primary"></i> Desktop';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $ua = $click['user_agent'];
                                if (strpos($ua, 'Chrome') !== false) {
                                    echo 'Chrome';
                                } elseif (strpos($ua, 'Firefox') !== false) {
                                    echo 'Firefox';
                                } elseif (strpos($ua, 'Safari') !== false) {
                                    echo 'Safari';
                                } elseif (strpos($ua, 'Edge') !== false) {
                                    echo 'Edge';
                                } else {
                                    echo 'Otro';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted text-center py-4">No hay clicks registrados todavía</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Vista limitada para usuarios no autorizados -->
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <strong>Estadísticas limitadas</strong><br>
            Para ver estadísticas detalladas, gráficos y análisis completos, inicia sesión con tu cuenta.
            <a href="/admin/login.php" class="btn btn-sm btn-primary float-end">
                <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Botones de acción - TODOS CORREGIDOS PARA APUNTAR A /admin/ -->
        <div class="text-center mt-4 mb-5">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/admin/panel_simple.php?section=urls" class="btn btn-primary">
                    <i class="bi bi-speedometer2"></i> Panel Admin
                </a>
            <?php else: ?>
                <a href="/index.php" class="btn btn-primary">
                    <i class="bi bi-house"></i> Volver al inicio
                </a>
            <?php endif; ?>
            
            <?php if ($can_view_full_stats): ?>
            <!-- ELIMINADO EL BOTÓN DUPLICADO "Panel de Control" -->
            <a href="/marcadores/analytics_url.php?url_id=<?php echo $url_data['id']; ?>" class="btn btn-warning">
                <i class="bi bi-graph-up"></i> Analytics Completo
            </a>
            <button class="btn btn-info" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($short_url_display); ?>" target="_blank" class="btn btn-success">
                <i class="bi bi-box-arrow-up-right"></i> Abrir URL
            </a>
        </div>
    </div>
    
    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">
                URL Shortener © <?php echo date('Y'); ?> | 
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/admin/panel_simple.php">Panel Admin</a> | 
                    <a href="/admin/logout.php">Salir</a>
                <?php else: ?>
                    <a href="/admin/login.php">Entrar</a>
                <?php endif; ?>
            </p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    
    <script>
        Chart.defaults.animation.duration = 1000;
        Chart.defaults.animation.loop = false;
        
        // Generar código QR
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo $short_url_display; ?>",
            width: 180,
            height: 180,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        
        // Función para copiar URL
        function copyToClipboard() {
            const input = document.getElementById('shortUrlInput');
            input.select();
            document.execCommand('copy');
            
            const tooltip = document.getElementById('copiedTooltip');
            tooltip.classList.add('show');
            setTimeout(() => {
                tooltip.classList.remove('show');
            }, 2000);
        }
        
        // Función para descargar QR
        function downloadQR() {
            const canvas = document.querySelector('#qrcode canvas');
            const link = document.createElement('a');
            link.download = 'qr-<?php echo $shortCode; ?>.png';
            link.href = canvas.toDataURL();
            link.click();
        }
        
        <?php if ($can_view_full_stats && count($dates) > 0): ?>
        // Gráficos
        if (window.dailyChart) window.dailyChart.destroy();
        if (window.deviceChart) window.deviceChart.destroy();
        if (window.hourlyChart) window.hourlyChart.destroy();
        
        // Gráfico de clicks por día
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        window.dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Clicks',
                    data: <?php echo json_encode($clicks); ?>,
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: 'rgb(102, 126, 234)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    loop: false,
                    onComplete: function() {
                        this.options.animation = false;
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: Math.max(...<?php echo json_encode($clicks); ?>) * 1.2,
                        ticks: {
                            stepSize: Math.ceil(Math.max(...<?php echo json_encode($clicks); ?>) / 10),
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Gráfico de dispositivos
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        window.deviceChart = new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($devices); ?>,
                datasets: [{
                    data: <?php echo json_encode($device_counts); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    loop: false,
                    animateRotate: true,
                    animateScale: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                }
            }
        });
        
        // Gráfico por horas
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyData = new Array(24).fill(0);
        <?php foreach ($hourly_stats as $stat): ?>
        hourlyData[<?php echo (int)$stat['hour']; ?>] = <?php echo (int)$stat['clicks']; ?>;
        <?php endforeach; ?>
        
        const maxHourlyClicks = Math.max(...hourlyData, 1);
        
        window.hourlyChart = new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Clicks',
                    data: hourlyData,
                    backgroundColor: 'rgba(102, 126, 234, 0.5)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    loop: false,
                    onComplete: function() {
                        this.options.animation = false;
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            title: function(context) {
                                return 'Hora: ' + context[0].label;
                            },
                            label: function(context) {
                                return 'Clicks: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: maxHourlyClicks * 1.2,
                        ticks: {
                            stepSize: Math.ceil(maxHourlyClicks / 10),
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11
                            },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (window.dailyChart) window.dailyChart.options.animation = false;
                if (window.deviceChart) window.deviceChart.options.animation = false;
                if (window.hourlyChart) window.hourlyChart.options.animation = false;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
