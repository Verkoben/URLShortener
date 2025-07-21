<?php
// migrate_clicks.php - Migración de clicks históricos a url_analytics (CORREGIDO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que se ejecute desde CLI o con permisos
if (php_sapi_name() !== 'cli' && !isset($_GET['confirm'])) {
    die('⚠️ Este script debe ejecutarse desde la línea de comandos o con ?confirm=yes');
}

require_once 'config.php';

echo "🚀 MIGRACIÓN DE CLICKS HISTÓRICOS\n";
echo "==================================\n\n";

// Verificar conexión
if (!isset($pdo)) {
    die("❌ Error: No hay conexión a la base de datos\n");
}

// Verificar que existe la tabla url_analytics
try {
    $result = $pdo->query("SHOW TABLES LIKE 'url_analytics'");
    if (!$result->fetch()) {
        die("❌ Error: La tabla url_analytics no existe\n");
    }
} catch (Exception $e) {
    die("❌ Error verificando tabla: " . $e->getMessage() . "\n");
}

// Función para distribuir clicks en el tiempo
function distributeClicks($total_clicks, $start_date, $end_date) {
    $clicks_distribution = [];
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date);
    $days_diff = max(1, floor(($end_timestamp - $start_timestamp) / 86400));
    
    // Distribuir clicks de manera más realista (más clicks en días recientes)
    $remaining_clicks = $total_clicks;
    
    for ($i = 0; $i < $days_diff && $remaining_clicks > 0; $i++) {
        $current_date = date('Y-m-d', $start_timestamp + ($i * 86400));
        
        // Distribución con peso hacia días más recientes
        $weight = ($i + 1) / $days_diff;
        $daily_clicks = min(
            $remaining_clicks,
            max(1, round($total_clicks * $weight * 0.1))
        );
        
        if ($daily_clicks > 0) {
            $clicks_distribution[$current_date] = $daily_clicks;
            $remaining_clicks -= $daily_clicks;
        }
    }
    
    // Distribuir clicks restantes en los últimos días
    if ($remaining_clicks > 0) {
        $distribution_keys = array_keys($clicks_distribution);
        $last_days = array_slice($distribution_keys, -7);
        
        foreach ($last_days as $date) {
            if ($remaining_clicks <= 0) break;
            $extra_clicks = min($remaining_clicks, ceil($remaining_clicks / count($last_days)));
            if (isset($clicks_distribution[$date])) {
                $clicks_distribution[$date] += $extra_clicks;
            } else {
                $clicks_distribution[$date] = $extra_clicks;
            }
            $remaining_clicks -= $extra_clicks;
        }
    }
    
    // Si aún quedan clicks, ponerlos en el último día
    if ($remaining_clicks > 0) {
        $all_dates = array_keys($clicks_distribution);
        if (!empty($all_dates)) {
            $last_date = end($all_dates);
            $clicks_distribution[$last_date] += $remaining_clicks;
        } else {
            // Si no hay distribución, poner todos los clicks en la fecha de inicio
            $clicks_distribution[$start_date] = $total_clicks;
        }
    }
    
    return $clicks_distribution;
}

// Obtener URLs con clicks pero sin analytics detallados
try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.short_code,
            u.user_id,
            u.clicks as total_clicks,
            u.created_at,
            COUNT(ua.id) as existing_analytics,
            MAX(ua.clicked_at) as last_analytics_date
        FROM urls u
        LEFT JOIN url_analytics ua ON u.id = ua.url_id
        WHERE u.clicks > 0
        GROUP BY u.id
        HAVING existing_analytics < u.clicks
        ORDER BY u.clicks DESC
    ");
    
    $urls_to_migrate = $stmt->fetchAll();
    
    echo "📊 URLs encontradas para migrar: " . count($urls_to_migrate) . "\n\n";
    
    if (count($urls_to_migrate) == 0) {
        echo "✅ No hay URLs que necesiten migración.\n";
        exit;
    }
    
    // Mostrar resumen
    $total_clicks_to_migrate = 0;
    foreach ($urls_to_migrate as $url) {
        $clicks_to_migrate = $url['total_clicks'] - $url['existing_analytics'];
        $total_clicks_to_migrate += $clicks_to_migrate;
        echo "📌 {$url['short_code']}: {$clicks_to_migrate} clicks por migrar (de {$url['total_clicks']} totales)\n";
    }
    
    echo "\n💾 Total de clicks a migrar: " . number_format($total_clicks_to_migrate) . "\n\n";
    
    // Confirmar
    if (php_sapi_name() === 'cli') {
        echo "¿Continuar con la migración? (s/n): ";
        $confirm = trim(fgets(STDIN));
        if (strtolower($confirm) !== 's') {
            echo "❌ Migración cancelada.\n";
            exit;
        }
    }
    
    echo "\n🔄 Iniciando migración...\n\n";
    
    // Preparar statement para insertar analytics
    $insert_stmt = $pdo->prepare("
        INSERT INTO url_analytics (
            url_id, user_id, short_code, ip_address, user_agent, 
            device_type, browser, os, clicked_at, session_id
        ) VALUES (
            :url_id, :user_id, :short_code, :ip_address, :user_agent,
            :device_type, :browser, :os, :clicked_at, :session_id
        )
    ");
    
    $migrated_count = 0;
    $error_count = 0;
    $total_records_inserted = 0;
    
    // Migrar cada URL
    foreach ($urls_to_migrate as $url) {
        $clicks_to_migrate = $url['total_clicks'] - $url['existing_analytics'];
        
        if ($clicks_to_migrate <= 0) continue;
        
        echo "🔄 Migrando {$url['short_code']} ({$clicks_to_migrate} clicks)... ";
        
        // Determinar rango de fechas
        $start_date = substr($url['created_at'], 0, 10); // Solo fecha
        $end_date = date('Y-m-d'); // Hoy
        
        if (!empty($url['last_analytics_date'])) {
            $end_date = substr($url['last_analytics_date'], 0, 10);
        }
        
        // Si la diferencia es muy pequeña, extender el rango
        if (strtotime($end_date) - strtotime($start_date) < 86400) {
            $start_date = date('Y-m-d', strtotime($end_date) - (30 * 86400));
        }
        
        // Distribuir clicks
        $distribution = distributeClicks($clicks_to_migrate, $start_date, $end_date);
        
        // Datos simulados para clicks históricos
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Chrome', 'Chrome']; // Chrome más común
        $devices = ['desktop', 'mobile', 'desktop', 'desktop', 'mobile', 'tablet'];
        $os_list = ['Windows', 'Android', 'macOS', 'iOS', 'Windows', 'Linux'];
        
        try {
            $pdo->beginTransaction();
            
            $url_records_inserted = 0;
            
            foreach ($distribution as $date => $daily_clicks) {
                for ($i = 0; $i < $daily_clicks; $i++) {
                    // Generar hora aleatoria del día
                    $hour = rand(8, 22); // Más tráfico en horario diurno
                    $minute = rand(0, 59);
                    $second = rand(0, 59);
                    $clicked_at = $date . ' ' . sprintf('%02d:%02d:%02d', $hour, $minute, $second);
                    
                    // Seleccionar datos aleatorios pero realistas
                    $browser = $browsers[array_rand($browsers)];
                    $device = $devices[array_rand($devices)];
                    $os = $os_list[array_rand($os_list)];
                    
                    // User agent simulado
                    $user_agent = "Mozilla/5.0 (Compatible; HistoricalClick/{$browser})";
                    
                    // IP simulada (privada para no interferir con geo)
                    $ip = "10." . rand(0, 255) . "." . rand(0, 255) . "." . rand(1, 254);
                    
                    // Session ID único
                    $session_id = 'historical_' . md5($url['id'] . '_' . $clicked_at . '_' . $i);
                    
                    $insert_stmt->execute([
                        ':url_id' => $url['id'],
                        ':user_id' => $url['user_id'],
                        ':short_code' => $url['short_code'],
                        ':ip_address' => $ip,
                        ':user_agent' => $user_agent,
                        ':device_type' => $device,
                        ':browser' => $browser,
                        ':os' => $os,
                        ':clicked_at' => $clicked_at,
                        ':session_id' => $session_id
                    ]);
                    
                    $url_records_inserted++;
                }
            }
            
            $pdo->commit();
            echo "✅ OK ({$url_records_inserted} registros)\n";
            $migrated_count++;
            $total_records_inserted += $url_records_inserted;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            $error_count++;
        }
    }
    
    echo "\n📊 RESUMEN DE MIGRACIÓN\n";
    echo "=======================\n";
    echo "✅ URLs migradas exitosamente: {$migrated_count}\n";
    echo "❌ URLs con errores: {$error_count}\n";
    echo "📝 Total registros insertados: " . number_format($total_records_inserted) . "\n";
    echo "💾 Total clicks esperados: " . number_format($total_clicks_to_migrate) . "\n";
    
    // Verificar integridad
    echo "\n🔍 Verificando integridad de datos...\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT url_id) as urls_with_analytics,
            COUNT(*) as total_analytics_records,
            MIN(clicked_at) as oldest_click,
            MAX(clicked_at) as newest_click
        FROM url_analytics
        WHERE ip_address LIKE '10.%'
    ");
    
    $verification = $stmt->fetch();
    
    echo "📈 URLs con analytics históricos: " . $verification['urls_with_analytics'] . "\n";
    echo "📊 Total registros históricos: " . number_format($verification['total_analytics_records']) . "\n";
    
    if ($verification['oldest_click'] && $verification['newest_click']) {
        echo "📅 Rango de fechas: " . date('d/m/Y', strtotime($verification['oldest_click'])) . 
             " a " . date('d/m/Y', strtotime($verification['newest_click'])) . "\n";
    }
    
    // Verificar algunos URLs específicos
    echo "\n🔎 Verificación de URLs migradas:\n";
    $check_stmt = $pdo->query("
        SELECT 
            u.short_code,
            u.clicks as total_clicks,
            COUNT(ua.id) as analytics_count
        FROM urls u
        LEFT JOIN url_analytics ua ON u.id = ua.url_id
        WHERE u.clicks > 0
        GROUP BY u.id
        ORDER BY u.clicks DESC
        LIMIT 5
    ");
    
    while ($check = $check_stmt->fetch()) {
        $status = ($check['total_clicks'] == $check['analytics_count']) ? '✅' : '⚠️';
        echo "{$status} {$check['short_code']}: {$check['analytics_count']}/{$check['total_clicks']} clicks\n";
    }
    
    echo "\n✅ ¡Migración completada!\n\n";
    
    echo "💡 NOTA: Los clicks históricos se han marcado con:\n";
    echo "   - IPs privadas (10.x.x.x) para distinguirlos\n";
    echo "   - Session IDs con prefijo 'historical_'\n";
    echo "   - Distribución temporal realista\n";
    echo "   - Datos de dispositivo/navegador simulados pero realistas\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit(1);
}

// Opción para limpiar datos históricos si es necesario
if (php_sapi_name() === 'cli') {
    echo "\n¿Deseas ver comandos útiles post-migración? (s/n): ";
    $show_commands = trim(fgets(STDIN));
    if (strtolower($show_commands) === 's') {
        echo "\n📝 COMANDOS ÚTILES:\n";
        echo "================\n";
        echo "-- Ver resumen de migración:\n";
        echo "SELECT COUNT(*) as historical_clicks FROM url_analytics WHERE session_id LIKE 'historical_%';\n\n";
        echo "-- Revertir migración (si es necesario):\n";
        echo "DELETE FROM url_analytics WHERE ip_address LIKE '10.%' AND session_id LIKE 'historical_%';\n\n";
        echo "-- Ver distribución por día:\n";
        echo "SELECT DATE(clicked_at) as day, COUNT(*) as clicks FROM url_analytics WHERE session_id LIKE 'historical_%' GROUP BY day ORDER BY day;\n";
    }
}
?>
