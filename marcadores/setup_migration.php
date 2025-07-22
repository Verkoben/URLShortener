<?php
// setup_migration.php - Configuración y ejecución controlada de migraciones
require_once 'config.php';

// Verificar si el usuario es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("Acceso denegado. Solo administradores.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup de Migración de Analytics</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .step {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .step h3 { margin-top: 0; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .status { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 5px; 
        }
        .status.info { background: #d1ecf1; color: #0c5460; }
        .status.success { background: #d4edda; color: #155724; }
        .status.warning { background: #fff3cd; color: #856404; }
        .status.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Setup de Migración de Analytics</h1>
        
        <?php
        // Verificar estado actual
        $has_analytics_table = false;
        $has_click_stats_table = false;
        $total_clicks = 0;
        $migrated_clicks = 0;
        $missing_countries = 0;
        
        try {
            // Verificar tabla click_stats
            $stmt = $pdo->query("SHOW TABLES LIKE 'click_stats'");
            $has_click_stats_table = $stmt->fetch() ? true : false;
            
            // Verificar tabla url_analytics
            $stmt = $pdo->query("SHOW TABLES LIKE 'url_analytics'");
            $has_analytics_table = $stmt->fetch() ? true : false;
            
            if ($has_click_stats_table) {
                // Contar clicks totales
                $stmt = $pdo->query("SELECT COUNT(*) FROM click_stats");
                $total_clicks = $stmt->fetchColumn();
            }
            
            if ($has_analytics_table) {
                // Contar clicks migrados
                $stmt = $pdo->query("SELECT COUNT(*) FROM url_analytics");
                $migrated_clicks = $stmt->fetchColumn();
                
                // Contar sin país
                $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM url_analytics WHERE country = 'Unknown' OR country IS NULL OR country = ''");
                $missing_countries = $stmt->fetchColumn();
            }
        } catch (Exception $e) {
            echo "<div class='status error'>Error al verificar estado: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
        
        <div class="status info">
            <h3>📊 Estado Actual</h3>
            <ul>
                <li>Tabla click_stats: <?php echo $has_click_stats_table ? '✅ Existe' : '❌ No existe'; ?></li>
                <li>Tabla url_analytics: <?php echo $has_analytics_table ? '✅ Existe' : '❌ No existe'; ?></li>
                <li>Clicks totales en sistema: <?php echo number_format($total_clicks); ?></li>
                <li>Clicks migrados: <?php echo number_format($migrated_clicks); ?></li>
                <li>IPs sin información de país: <?php echo number_format($missing_countries); ?></li>
            </ul>
        </div>
        
        <?php if (!$has_click_stats_table): ?>
        <div class="status warning">
            ⚠️ No se encontró la tabla click_stats. Es posible que no tengas datos históricos para migrar.
        </div>
        <?php endif; ?>
        
        <div class="step">
            <h3>Paso 1: Crear tabla url_analytics</h3>
            <p>Crea la estructura de la tabla para almacenar analytics detallados.</p>
            <?php if (!$has_analytics_table): ?>
                <a href="setup_analytics.php" class="btn">Ejecutar Setup</a>
            <?php else: ?>
                <div class="status success">✅ Tabla ya existe</div>
            <?php endif; ?>
        </div>
        
        <div class="step">
            <h3>Paso 2: Migrar clicks históricos</h3>
            <p>Migra los datos de click_stats a url_analytics con información enriquecida.</p>
            <?php if ($has_click_stats_table && $has_analytics_table): ?>
                <?php if ($total_clicks > $migrated_clicks): ?>
                    <p>Clicks pendientes: <strong><?php echo number_format($total_clicks - $migrated_clicks); ?></strong></p>
                    <a href="migrate_clicks.php?dry_run=1" class="btn btn-warning">Simular (Dry Run)</a>
                    <a href="migrate_clicks.php" class="btn btn-success">Ejecutar Migración</a>
                <?php else: ?>
                    <div class="status success">✅ Todos los clicks están migrados</div>
                <?php endif; ?>
            <?php elseif (!$has_click_stats_table): ?>
                <div class="status warning">No hay tabla click_stats para migrar</div>
            <?php else: ?>
                <div class="status warning">Primero debes crear la tabla url_analytics</div>
            <?php endif; ?>
        </div>
        
        <div class="step">
            <h3>Paso 3: Actualizar información de países</h3>
            <p>Obtiene información geográfica para las IPs usando API externa.</p>
            <?php if ($has_analytics_table && $migrated_clicks > 0): ?>
                <?php if ($missing_countries > 0): ?>
                    <p>IPs sin geolocalizar: <strong><?php echo number_format($missing_countries); ?></strong></p>
                    <a href="update_countries.php" class="btn btn-success">Actualizar Países</a>
                <?php else: ?>
                    <div class="status success">✅ Todas las IPs están geolocalizadas</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="status warning">Primero debes migrar los clicks</div>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <h2>⚙️ Configuración Recomendada</h2>
        <ul>
            <li><strong>BATCH_SIZE:</strong> 500 registros (ajustar según servidor)</li>
            <li><strong>SLEEP_TIME:</strong> 1-2 segundos entre lotes</li>
            <li><strong>Memoria:</strong> 256MB mínimo</li>
            <li><strong>Tiempo:</strong> Sin límite (set_time_limit(0))</li>
        </ul>
        
        <h2>📝 Notas Importantes</h2>
        <ul>
            <li>Ejecuta las migraciones en horas de baja actividad</li>
            <li>El proceso puede tardar varios minutos dependiendo del volumen</li>
            <li>La API de geolocalización tiene límite de 45 requests/minuto</li>
            <li>Siempre haz backup antes de ejecutar migraciones</li>
        </ul>
        
        <hr>
        <a href="index.php" class="btn">← Volver al inicio</a>
    </div>
</body>
</html>
