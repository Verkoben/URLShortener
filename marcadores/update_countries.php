<?php
// update_countries_enhanced.php - Actualización mejorada con múltiples APIs
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("Acceso denegado. Solo administradores.");
}

// Configuración
$BATCH_SIZE = 50; // Menos IPs por lote para evitar límites
$SLEEP_TIME = 3; // Más tiempo entre lotes

echo "<!DOCTYPE html>
<html>
<head>
    <title>Actualización Mejorada de Geolocalización</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px; 
            background: #f0f4f8; 
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .progress-container {
            background: #e9ecef;
            border-radius: 10px;
            padding: 3px;
            margin: 20px 0;
        }
        .progress-bar {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: white;
            text-align: center;
            line-height: 30px;
            border-radius: 8px;
            transition: width 0.3s ease;
            min-width: 50px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .log {
            background: #f1f3f4;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
<div class='container'>
<h1>🌍 Actualización Mejorada de Geolocalización</h1>
<p>Como un detective digital que rastrea de dónde vienen tus visitantes...</p>
";

flush();

// Cache de IPs
$ip_cache = [];
$stats = [
    'total' => 0,
    'processed' => 0,
    'updated' => 0,
    'cached' => 0,
    'api_calls' => 0,
    'errors' => 0
];

// Función mejorada con múltiples servicios
function getGeoLocationEnhanced($ip, &$stats) {
    global $ip_cache;
    
    // Verificar cache
    if (isset($ip_cache[$ip])) {
        $stats['cached']++;
        return $ip_cache[$ip];
    }
    
    // Verificar si es IP privada
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $result = [
            'country' => 'Private Network',
            'country_code' => 'XX',
            'city' => 'Local',
            'region' => 'Private',
            'lat' => null,
            'lon' => null,
            'source' => 'local'
        ];
        $ip_cache[$ip] = $result;
        return $result;
    }
    
    // Lista de servicios de geolocalización
    $services = [
        // 1. ip-api.com
        [
            'name' => 'ip-api.com',
            'url' => "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,lat,lon",
            'parse' => function($data) {
                if ($data && $data['status'] == 'success') {
                    return [
                        'country' => $data['country'] ?? 'Unknown',
                        'country_code' => strtoupper($data['countryCode'] ?? ''),
                        'city' => $data['city'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null
                    ];
                }
                return false;
            }
        ],
        
        // 2. ipapi.co
        [
            'name' => 'ipapi.co',
            'url' => "https://ipapi.co/{$ip}/json/",
            'parse' => function($data) {
                if ($data && !isset($data['error'])) {
                    return [
                        'country' => $data['country_name'] ?? 'Unknown',
                        'country_code' => strtoupper($data['country_code'] ?? ''),
                        'city' => $data['city'] ?? null,
                        'region' => $data['region'] ?? null,
                        'lat' => $data['latitude'] ?? null,
                        'lon' => $data['longitude'] ?? null
                    ];
                }
                return false;
            }
        ],
        
        // 3. ip-api.io
        [
            'name' => 'ip-api.io',
            'url' => "https://ip-api.io/json/{$ip}",
            'parse' => function($data) {
                if ($data && isset($data['country_name'])) {
                    return [
                        'country' => $data['country_name'] ?? 'Unknown',
                        'country_code' => strtoupper($data['country_code'] ?? ''),
                        'city' => $data['city'] ?? null,
                        'region' => $data['region_name'] ?? null,
                        'lat' => $data['latitude'] ?? null,
                        'lon' => $data['longitude'] ?? null
                    ];
                }
                return false;
            }
        ]
    ];
    
    // Intentar con cada servicio
    foreach ($services as $service) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n"
                ]
            ]);
            
            $response = @file_get_contents($service['url'], false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                $result = $service['parse']($data);
                
                if ($result !== false) {
                    $result['source'] = $service['name'];
                    $ip_cache[$ip] = $result;
                    $stats['api_calls']++;
                    
                    echo "<script>
                        document.getElementById('log').innerHTML += '<div class=\"success\">✓ {$ip} → {$result['country']} ({$service['name']})</div>';
                        document.getElementById('log').scrollTop = document.getElementById('log').scrollHeight;
                    </script>";
                    flush();
                    
                    return $result;
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    // Si todos fallan
    $stats['errors']++;
    $result = [
        'country' => 'Unknown',
        'country_code' => null,
        'city' => null,
        'region' => null,
        'lat' => null,
        'lon' => null,
        'source' => 'failed'
    ];
    
    $ip_cache[$ip] = $result;
    return $result;
}

try {
    // Contar total de IPs únicas sin geolocalización
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT ip_address) as total
        FROM url_analytics 
        WHERE (country = 'Unknown' OR country IS NULL OR country = '')
        AND ip_address IS NOT NULL
        AND ip_address != ''
    ");
    $stats['total'] = $stmt->fetchColumn();
    
    if ($stats['total'] == 0) {
        echo "<div class='alert alert-success'>✅ Todas las IPs ya están geolocalizadas!</div>";
        exit;
    }
    
    echo "<div class='stats-grid'>
        <div class='stat-card'>
            <div class='stat-value' id='stat-total'>" . number_format($stats['total']) . "</div>
            <div>IPs totales</div>
        </div>
        <div class='stat-card'>
            <div class='stat-value' id='stat-processed'>0</div>
            <div>Procesadas</div>
        </div>
        <div class='stat-card'>
            <div class='stat-value' id='stat-updated'>0</div>
            <div>Actualizadas</div>
        </div>
        <div class='stat-card'>
            <div class='stat-value' id='stat-api'>0</div>
            <div>Llamadas API</div>
        </div>
    </div>";
    
    echo "<div class='progress-container'>
        <div class='progress-bar' id='progress' style='width: 0%'>0%</div>
    </div>";
    
    echo "<div class='log' id='log'></div>";
    
    flush();
    
    // Obtener IPs únicas
    $stmt = $pdo->query("
        SELECT DISTINCT ip_address
        FROM url_analytics 
        WHERE (country = 'Unknown' OR country IS NULL OR country = '')
        AND ip_address IS NOT NULL
        AND ip_address != ''
        ORDER BY ip_address
        LIMIT 5000
    ");
    
    $ips = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $batches = array_chunk($ips, $BATCH_SIZE);
    
    foreach ($batches as $batch_index => $batch) {
        $batch_start = microtime(true);
        
        foreach ($batch as $ip) {
            $geo = getGeoLocationEnhanced($ip, $stats);
            
            // Actualizar en base de datos
            if ($geo['country'] != 'Unknown') {
                $update = $pdo->prepare("
                    UPDATE url_analytics 
                    SET country = :country,
                        country_code = :country_code,
                        city = :city,
                        region = :region,
                        latitude = :lat,
                        longitude = :lon
                    WHERE ip_address = :ip
                    AND (country = 'Unknown' OR country IS NULL OR country = '')
                ");
                
                $update->execute([
                    ':country' => $geo['country'],
                    ':country_code' => $geo['country_code'],
                    ':city' => $geo['city'],
                    ':region' => $geo['region'],
                    ':lat' => $geo['lat'],
                    ':lon' => $geo['lon'],
                    ':ip' => $ip
                ]);
                
                $stats['updated'] += $update->rowCount();
            }
            
            $stats['processed']++;
            
            // Actualizar estadísticas en pantalla cada 5 IPs
            if ($stats['processed'] % 5 == 0) {
                $percent = round(($stats['processed'] / $stats['total']) * 100, 1);
                echo "<script>
                    document.getElementById('progress').style.width = '{$percent}%';
                    document.getElementById('progress').textContent = '{$percent}%';
                    document.getElementById('stat-processed').textContent = '" . number_format($stats['processed']) . "';
                    document.getElementById('stat-updated').textContent = '" . number_format($stats['updated']) . "';
                    document.getElementById('stat-api').textContent = '" . number_format($stats['api_calls']) . "';
                </script>";
                flush();
            }
        }
        
        // Pausa entre lotes
        if ($batch_index < count($batches) - 1) {
            echo "<script>
                document.getElementById('log').innerHTML += '<div class=\"warning\">⏸ Pausando {$SLEEP_TIME} segundos...</div>';
            </script>";
            flush();
            sleep($SLEEP_TIME);
        }
    }
    
    // Resumen final
    echo "<h2 class='success'>✅ Proceso completado!</h2>";
    
    // Top países
    $stmt = $pdo->query("
        SELECT 
            country,
            country_code,
            COUNT(*) as clicks,
            COUNT(DISTINCT ip_address) as unique_ips,
            COUNT(DISTINCT city) as cities
        FROM url_analytics
        WHERE country != 'Unknown' AND country IS NOT NULL
        GROUP BY country, country_code
        ORDER BY clicks DESC
        LIMIT 15
    ");
    
    echo "<h3>🏆 Top 15 Países</h3>";
    echo "<table class='table table-striped'>
        <tr>
            <th>País</th>
            <th>Código</th>
            <th>Clicks</th>
            <th>IPs únicas</th>
            <th>Ciudades</th>
        </tr>";
    
    while ($row = $stmt->fetch()) {
        $flag = $row['country_code'] ? 
            "<img src='https://flagcdn.com/24x18/" . strtolower($row['country_code']) . ".png' 
                 style='margin-right: 8px;' onerror='this.style.display=\"none\"'>" : "";
        echo "<tr>
            <td>{$flag}{$row['country']}</td>
            <td>{$row['country_code']}</td>
            <td>" . number_format($row['clicks']) . "</td>
            <td>" . number_format($row['unique_ips']) . "</td>
            <td>" . number_format($row['cities']) . "</td>
        </tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "
<br>
<a href='setup_migration.php' class='btn btn-secondary'>← Volver</a>
</div>
</body>
</html>";
?>
