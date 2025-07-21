<?php
// track_detective.php - Encontrar QUÉ está causando el tracking
require_once 'config.php';

echo "<h2>🕵️ Detective de Tracking</h2>";
echo "<style>body{font-family:Arial;margin:20px;} .suspect{background:#ffebee;padding:10px;margin:10px 0;border-radius:5px;} .safe{background:#e8f5e9;padding:10px;margin:10px 0;border-radius:5px;}</style>";

// 1. Ver últimos 50 registros con detalles
echo "<h3>🔍 Últimos 50 registros sospechosos:</h3>";

try {
    $stmt = $pdo->query("
        SELECT 
            clicked_at,
            short_code,
            ip_address,
            device_type,
            browser,
            SUBSTRING(user_agent, 1, 100) as user_agent_short,
            CASE 
                WHEN referer IS NULL THEN 'Directo'
                ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '://', -1)
            END as referer_clean
        FROM url_analytics 
        ORDER BY clicked_at DESC 
        LIMIT 50
    ");
    
    $records = $stmt->fetchAll();
    
    if ($records) {
        echo "<table border='1' style='border-collapse:collapse; width:100%; font-size:12px;'>";
        echo "<tr style='background:#333;color:white;'>";
        echo "<th>Hora</th><th>Código</th><th>IP</th><th>Dispositivo</th><th>Browser</th><th>User Agent</th><th>Referrer</th>";
        echo "</tr>";
        
        $current_time = time();
        
        foreach ($records as $record) {
            $time = strtotime($record['clicked_at']);
            $ago = $current_time - $time;
            
            // Marcar como sospechoso si es muy reciente (menos de 1 minuto)
            $suspicious = $ago < 60;
            $rowClass = $suspicious ? "background:#ffebee;" : "";
            
            echo "<tr style='{$rowClass}'>";
            echo "<td>" . date('H:i:s', $time) . " ({$ago}s)</td>";
            echo "<td>{$record['short_code']}</td>";
            echo "<td>{$record['ip_address']}</td>";
            echo "<td>{$record['device_type']}</td>";
            echo "<td>{$record['browser']}</td>";
            echo "<td>{$record['user_agent_short']}...</td>";
            echo "<td>{$record['referer_clean']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Análisis de patrones
        echo "<h3>📊 Análisis de Patrones:</h3>";
        
        // Contar por minuto
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(clicked_at, '%H:%i') as minute,
                COUNT(*) as count
            FROM url_analytics 
            WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY minute
            ORDER BY minute DESC
        ");
        
        $patterns = $stmt->fetchAll();
        
        foreach ($patterns as $pattern) {
            $class = $pattern['count'] > 5 ? 'suspect' : 'safe';
            echo "<div class='{$class}'>";
            echo "⏰ {$pattern['minute']} - {$pattern['count']} clicks " . ($pattern['count'] > 5 ? "🚨 SOSPECHOSO" : "✅ Normal");
            echo "</div>";
        }
        
    } else {
        echo "<p>No hay registros</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// 2. Verificar archivos que pueden estar haciendo tracking
echo "<h3>🔍 Archivos sospechosos:</h3>";

$files = [
    'index.php' => 'Página principal',
    'analytics_url.php' => 'Analytics de URL',
    'analytics_dashboard.php' => 'Dashboard',
    'track.php' => 'Tracking redirect',
    'api.php' => 'API endpoints'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Buscar llamadas sospechosas
        $has_track = strpos($content, 'trackClick') !== false;
        $has_fetch = strpos($content, 'fetch(') !== false;
        $has_interval = strpos($content, 'setInterval') !== false;
        $has_auto_refresh = strpos($content, 'location.reload') !== false;
        
        $suspicious = $has_track || $has_interval || $has_auto_refresh;
        
        echo "<div class='" . ($suspicious ? 'suspect' : 'safe') . "'>";
        echo "<strong>{$file}</strong> - {$desc}<br>";
        if ($has_track) echo "⚠️ Contiene trackClick()<br>";
        if ($has_fetch) echo "ℹ️ Contiene fetch()<br>";
        if ($has_interval) echo "🚨 Contiene setInterval()<br>";
        if ($has_auto_refresh) echo "🚨 Contiene auto-refresh<br>";
        if (!$suspicious) echo "✅ Parece seguro<br>";
        echo "</div>";
    }
}

// 3. Estado del tracking
echo "<h3>🎛️ Estado del Sistema:</h3>";
echo "<div class='safe'>";
echo "🛡️ Tracking disabled flag: " . (file_exists('tracking_disabled.flag') ? "✅ ACTIVO" : "❌ INACTIVO") . "<br>";
if (file_exists('tracking_disabled.flag')) {
    echo "📅 Desde: " . file_get_contents('tracking_disabled.flag') . "<br>";
}
echo "</div>";

echo "<br><br>";
echo "<a href='emergency_stop.php' style='background:red;color:white;padding:10px;text-decoration:none;border-radius:5px;'>🛑 EMERGENCY STOP</a> ";
echo "<a href='index.php'>← Volver al gestor</a>";
?>
