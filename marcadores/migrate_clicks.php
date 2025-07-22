<?php
// migrate_clicks.php - Migración de clicks con control de recursos
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once 'config.php';

// Verificar autenticación admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("Acceso denegado. Solo administradores pueden ejecutar este script.");
}

// Configuración
$BATCH_SIZE = 500; // Procesar de 500 en 500 registros
$SLEEP_TIME = 1; // Pausar 1 segundo entre lotes
$DRY_RUN = isset($_GET['dry_run']) ? true : false;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Migración de Clicks</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        .info { color: blue; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .progress { 
            width: 100%; 
            height: 20px; 
            background: #ddd; 
            border-radius: 10px; 
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-bar {
            height: 100%;
            background: #4CAF50;
            width: 0%;
            transition: width 0.3s;
            text-align: center;
            color: white;
            line-height: 20px;
        }
    </style>
</head>
<body>
<h1>Migración de Clicks a url_analytics</h1>
";

if ($DRY_RUN) {
    echo "<p class='warning'>⚠️ MODO DRY RUN - No se realizarán cambios</p>";
}

flush();

try {
    // Verificar si existe la tabla url_analytics
    $stmt = $pdo->query("SHOW TABLES LIKE 'url_analytics'");
    if (!$stmt->fetch()) {
        echo "<p class='error'>❌ La tabla url_analytics no existe. Ejecuta setup_analytics.php primero.</p>";
        echo "<br><a href='setup_migration.php'>← Volver</a>";
        exit;
    }
    
    // Verificar si existe la tabla click_stats
    $stmt = $pdo->query("SHOW TABLES LIKE 'click_stats'");
    if (!$stmt->fetch()) {
        echo "<p class='warning'>⚠️ La tabla click_stats no existe. No hay datos para migrar.</p>";
        echo "<br><a href='setup_migration.php'>← Volver</a>";
        exit;
    }
    
    // Contar total de clicks a migrar
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM click_stats cs
        LEFT JOIN url_analytics ua ON cs.id = ua.click_stats_id
        WHERE ua.id IS NULL
    ");
    $total = $stmt->fetch()['total'];
    
    if ($total == 0) {
        echo "<p class='success'>✅ No hay clicks pendientes de migrar.</p>";
        echo "<br><a href='setup_migration.php'>← Volver</a>";
        exit;
    }
    
    echo "<p class='info'>📊 Total de clicks a migrar: <strong>" . number_format($total) . "</strong></p>";
    echo "<div class='progress'><div class='progress-bar' id='progress'>0%</div></div>";
    echo "<div id='status'></div>";
    
    flush();
    
    $processed = 0;
    $errors = 0;
    $offset = 0;
    
    // Procesar en lotes
    while ($offset < $total) {
        $stmt = $pdo->prepare("
            SELECT 
                cs.*,
                u.user_id,
                u.short_code,
                u.original_url
            FROM click_stats cs
            JOIN urls u ON cs.url_id = u.id
            LEFT JOIN url_analytics ua ON cs.id = ua.click_stats_id
            WHERE ua.id IS NULL
            ORDER BY cs.id
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':limit', $BATCH_SIZE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($clicks)) {
            break;
        }
        
        $batch_start = microtime(true);
        
        // Comenzar transacción
        if (!$DRY_RUN) {
            $pdo->beginTransaction();
        }
        
        foreach ($clicks as $click) {
            try {
                // Parsear user agent
                $browser = 'Unknown';
                $os = 'Unknown';
                $device = 'desktop';
                
                if (!empty($click['user_agent'])) {
                    $ua = strtolower($click['user_agent']);
                    
                    // Detectar navegador
                    if (strpos($ua, 'firefox') !== false) $browser = 'Firefox';
                    elseif (strpos($ua, 'edg') !== false) $browser = 'Edge';
                    elseif (strpos($ua, 'chrome') !== false) $browser = 'Chrome';
                    elseif (strpos($ua, 'safari') !== false) $browser = 'Safari';
                    elseif (strpos($ua, 'opera') !== false || strpos($ua, 'opr') !== false) $browser = 'Opera';
                    
                    // Detectar OS
                    if (strpos($ua, 'windows') !== false) $os = 'Windows';
                    elseif (strpos($ua, 'mac') !== false) $os = 'macOS';
                    elseif (strpos($ua, 'linux') !== false) $os = 'Linux';
                    elseif (strpos($ua, 'android') !== false) $os = 'Android';
                    elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) $os = 'iOS';
                    
                    // Detectar dispositivo
                    if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
                        $device = 'mobile';
                    } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
                        $device = 'tablet';
                    }
                }
                
                // Generar session_id si no existe
                $session_id = !empty($click['session_id']) ? $click['session_id'] : 
                    md5($click['ip_address'] . $click['user_agent'] . date('Y-m-d', strtotime($click['clicked_at'])));
                
                // Parsear referer
                $referer_domain = 'direct';
                if (!empty($click['referer']) && $click['referer'] != 'direct') {
                    $parsed = parse_url($click['referer']);
                    $referer_domain = isset($parsed['host']) ? $parsed['host'] : 'unknown';
                }
                
                if (!$DRY_RUN) {
                    // Insertar en url_analytics
                    $insert = $pdo->prepare("
                        INSERT INTO url_analytics (
                            url_id, user_id, clicked_at, ip_address, country, country_code,
                            city, user_agent, referer, referer_domain, browser, os, device,
                            session_id, click_stats_id
                        ) VALUES (
                            :url_id, :user_id, :clicked_at, :ip_address, :country, :country_code,
                            :city, :user_agent, :referer, :referer_domain, :browser, :os, :device,
                            :session_id, :click_stats_id
                        )
                    ");
                    
                    $insert->execute([
                        ':url_id' => $click['url_id'],
                        ':user_id' => $click['user_id'],
                        ':clicked_at' => $click['clicked_at'],
                        ':ip_address' => $click['ip_address'],
                        ':country' => !empty($click['country']) ? $click['country'] : 'Unknown',
                        ':country_code' => $click['country_code'] ?? null,
                        ':city' => $click['city'] ?? null,
                        ':user_agent' => $click['user_agent'],
                        ':referer' => $click['referer'],
                        ':referer_domain' => $referer_domain,
                        ':browser' => $browser,
                        ':os' => $os,
                        ':device' => $device,
                        ':session_id' => $session_id,
                        ':click_stats_id' => $click['id']
                    ]);
                }
                
                $processed++;
                
            } catch (Exception $e) {
                $errors++;
                echo "<script>document.getElementById('status').innerHTML += '<div class=\"error\">Error en click ID " . $click['id'] . ": " . addslashes($e->getMessage()) . "</div>';</script>";
            }
        }
        
        // Confirmar transacción
        if (!$DRY_RUN) {
            $pdo->commit();
        }
        
        $batch_time = round(microtime(true) - $batch_start, 2);
        $percent = round(($processed / $total) * 100, 1);
        
        // Actualizar progreso
        echo "<script>
            document.getElementById('progress').style.width = '{$percent}%';
            document.getElementById('progress').textContent = '{$percent}%';
            document.getElementById('status').innerHTML = '<div class=\"info\">Procesados: " . number_format($processed) . " / " . number_format($total) . " (Último lote: {$batch_time}s)</div>';
        </script>";
        
        flush();
        
        $offset += $BATCH_SIZE;
        
        // Pausar para no saturar el servidor
        if ($offset < $total) {
            sleep($SLEEP_TIME);
        }
    }
    
    echo "<h2 class='success'>✅ Migración completada</h2>";
    echo "<ul>";
    echo "<li>Clicks procesados: <strong>" . number_format($processed) . "</strong></li>";
    echo "<li>Errores: <strong>" . number_format($errors) . "</strong></li>";
    echo "</ul>";
    
    if (!$DRY_RUN) {
        echo "<p class='info'>💡 Ahora ejecuta <a href='update_countries.php'>update_countries.php</a> para actualizar la información de países.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error fatal: " . htmlspecialchars($e->getMessage()) . "</p>";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "
<br>
<a href='setup_migration.php'>← Volver al setup</a>
</body>
</html>";
?>
