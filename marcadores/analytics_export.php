<?php
// analytics_export.php - Export de analytics CORREGIDO para PHP 8+
require_once 'config.php';
require_once 'functions.php';

$user_id = getCurrentUserId();
if (!$user_id) {
    header('Location: ../admin/login.php');
    exit;
}

$userInfo = getCurrentUserInfo();
$format = $_GET['format'] ?? 'csv';
$days = (int)($_GET['days'] ?? 30);
$download = isset($_GET['download']);

// Validar formato
$allowedFormats = ['csv', 'json', 'html'];
if (!in_array($format, $allowedFormats)) {
    $format = 'csv';
}

// Validar días
$allowedDays = [7, 30, 90, 180, 365];
if (!in_array($days, $allowedDays)) {
    $days = 30;
}

try {
    // Obtener datos básicos del usuario
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_urls,
            SUM(clicks) as total_clicks
        FROM urls 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $basicStats = $stmt->fetch();
    
    // Intentar obtener analytics (puede estar vacío)
    $analytics = [];
    $summary = ['total_clicks' => 0, 'unique_visitors' => 0, 'urls_clicked' => 0, 'active_days' => 0];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ua.clicked_at,
                ua.ip_address,
                ua.country,
                ua.city,
                ua.device_type,
                ua.browser,
                u.short_code,
                u.original_url
            FROM url_analytics ua
            JOIN urls u ON ua.url_id = u.id
            WHERE ua.user_id = ? 
            AND ua.clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY ua.clicked_at DESC
            LIMIT 1000
        ");
        $stmt->execute([$user_id, $days]);
        $analytics = $stmt->fetchAll();
        
        // Calcular resumen si hay datos
        if (!empty($analytics)) {
            $summary['total_clicks'] = count($analytics);
            $summary['unique_visitors'] = count(array_unique(array_column($analytics, 'ip_address')));
            $summary['urls_clicked'] = count(array_unique(array_column($analytics, 'short_code')));
            $summary['active_days'] = count(array_unique(array_map(function($row) { 
                return date('Y-m-d', strtotime($row['clicked_at'])); 
            }, $analytics)));
        }
        
    } catch (Exception $e) {
        // Si falla analytics, usar datos básicos de URLs
        $summary['total_clicks'] = $basicStats['total_clicks'] ?? 0;
    }
    
    // Obtener top URLs basado en clicks de la tabla urls
    $stmt = $pdo->prepare("
        SELECT 
            short_code,
            original_url,
            clicks,
            created_at
        FROM urls
        WHERE user_id = ?
        ORDER BY clicks DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $topUrls = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Error obteniendo datos: " . $e->getMessage());
}

// Función helper para CSV compatible con PHP 8+
function csvWrite($handle, $fields) {
    return fputcsv($handle, $fields, ',', '"', '\\');
}

// Generar nombre de archivo
$timestamp = date('Y-m-d_H-i-s');
$username = $userInfo['username'];
$filename = "analytics_{$username}_{$days}days_{$timestamp}";

if ($format === 'csv') {
    // EXPORTAR CSV
    if ($download) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    } else {
        header('Content-Type: text/plain; charset=utf-8');
    }
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Información del export - CORREGIDO con parámetro escape
    csvWrite($output, ['# Analytics Export']);
    csvWrite($output, ['# Usuario:', $username]);
    csvWrite($output, ['# Período:', "$days días"]);
    csvWrite($output, ['# Exportado:', date('Y-m-d H:i:s')]);
    csvWrite($output, ['# Total URLs:', count($topUrls)]);
    csvWrite($output, ['# Total clicks:', $summary['total_clicks']]);
    csvWrite($output, []);
    
    // SECCIÓN 1: Resumen
    csvWrite($output, ['=== RESUMEN ===']);
    csvWrite($output, ['Métrica', 'Valor']);
    csvWrite($output, ['Total URLs', count($topUrls)]);
    csvWrite($output, ['Total Clicks', $summary['total_clicks']]);
    csvWrite($output, ['Analytics Records', count($analytics)]);
    csvWrite($output, []);
    
    // SECCIÓN 2: Top URLs
    csvWrite($output, ['=== TOP URLs ===']);
    csvWrite($output, ['Código', 'URL Original', 'Clicks', 'Creado']);
    foreach ($topUrls as $url) {
        csvWrite($output, [
            $url['short_code'],
            $url['original_url'],
            $url['clicks'],
            date('Y-m-d H:i', strtotime($url['created_at']))
        ]);
    }
    csvWrite($output, []);
    
    // SECCIÓN 3: Analytics detallados (si hay)
    if (!empty($analytics)) {
        csvWrite($output, ['=== CLICKS DETALLADOS ===']);
        csvWrite($output, [
            'Fecha/Hora',
            'Código URL', 
            'URL Original',
            'País', 
            'Ciudad',
            'Dispositivo',
            'Navegador',
            'IP'
        ]);
        
        foreach ($analytics as $click) {
            csvWrite($output, [
                date('Y-m-d H:i:s', strtotime($click['clicked_at'])),
                $click['short_code'],
                $click['original_url'],
                $click['country'] ?: 'Desconocido',
                $click['city'] ?: 'Desconocida',
                $click['device_type'] ?: 'Desconocido',
                $click['browser'] ?: 'Desconocido',
                $click['ip_address']
            ]);
        }
    } else {
        csvWrite($output, ['=== NOTA ===']);
        csvWrite($output, ['No hay datos de analytics detallados para este período.']);
        csvWrite($output, ['Los datos mostrados provienen de la tabla de URLs.']);
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'json') {
    // EXPORTAR JSON
    $exportData = [
        'export_info' => [
            'user' => $username,
            'user_id' => $user_id,
            'period_days' => $days,
            'exported_at' => date('Y-m-d H:i:s'),
            'has_analytics' => !empty($analytics)
        ],
        'summary' => $summary,
        'top_urls' => $topUrls,
        'detailed_analytics' => $analytics
    ];
    
    if ($download) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    } else {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
    
} else {
    // Vista HTML (preview)
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>📊 Analytics Export - <?= htmlspecialchars($username) ?></title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
            .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .section h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-top: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #f8f9fa; font-weight: bold; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
            .btn:hover { background: #0056b3; }
            .btn-success { background: #28a745; }
            .btn-success:hover { background: #1e7e34; }
            .alert { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📊 Analytics Export - <?= htmlspecialchars($username) ?></h1>
            <p><strong>Período:</strong> Últimos <?= $days ?> días | <strong>Exportado:</strong> <?= date('Y-m-d H:i:s') ?></p>
            
            <div>
                <a href="?format=csv&days=<?= $days ?>&download=1" class="btn btn-success">📥 Descargar CSV</a>
                <a href="?format=json&days=<?= $days ?>&download=1" class="btn">📄 Descargar JSON</a>
                <a href="analytics_dashboard.php" class="btn">📊 Dashboard</a>
                <a href="index.php" class="btn">🏠 Gestor URLs</a>
            </div>
        </div>
        
        <?php if (empty($analytics)): ?>
        <div class="alert">
            <strong>ℹ️ Información:</strong> No hay datos de analytics detallados para este período. 
            Los datos mostrados provienen de la tabla de URLs (clicks totales).
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>📈 Resumen</h2>
            <table>
                <tr><td><strong>Total URLs</strong></td><td><?= number_format(count($topUrls)) ?></td></tr>
                <tr><td><strong>Total Clicks</strong></td><td><?= number_format($summary['total_clicks']) ?></td></tr>
                <tr><td><strong>Registros Analytics</strong></td><td><?= number_format(count($analytics)) ?></td></tr>
                <?php if (!empty($analytics)): ?>
                <tr><td><strong>Visitantes Únicos</strong></td><td><?= number_format($summary['unique_visitors']) ?></td></tr>
                <tr><td><strong>Días Activos</strong></td><td><?= number_format($summary['active_days']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="section">
            <h2>🏆 Top URLs por Clicks</h2>
            <table>
                <thead>
                    <tr><th>Código</th><th>URL Original</th><th>Clicks</th><th>Creado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topUrls as $url): ?>
                    <tr>
                        <td><?= htmlspecialchars($url['short_code']) ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($url['original_url']) ?></td>
                        <td><strong><?= number_format($url['clicks']) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($url['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($analytics)): ?>
        <div class="section">
            <h2>📋 Analytics Recientes (últimos 20)</h2>
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Código</th><th>País</th><th>Dispositivo</th><th>Navegador</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($analytics, 0, 20) as $click): ?>
                    <tr>
                        <td><?= date('d/m H:i', strtotime($click['clicked_at'])) ?></td>
                        <td><?= htmlspecialchars($click['short_code']) ?></td>
                        <td><?= htmlspecialchars($click['country'] ?: 'Desconocido') ?></td>
                        <td><?= htmlspecialchars($click['device_type'] ?: 'Desconocido') ?></td>
                        <td><?= htmlspecialchars($click['browser'] ?: 'Desconocido') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <p style="text-align: center; color: #6c757d; margin-top: 40px;">
            <small>Analytics generados en <?= date('Y-m-d H:i:s') ?> | Total URLs: <?= count($topUrls) ?> | Analytics records: <?= count($analytics) ?></small>
        </p>
    </body>
    </html>
    <?php
}
?>
