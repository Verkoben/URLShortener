<?php
// geo_map.php - Mapa mejorado que muestra TODOS los clicks
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$url_id = isset($_GET['url_id']) ? (int)$_GET['url_id'] : 0;
$view_mode = isset($_GET['mode']) ? $_GET['mode'] : 'grouped'; // grouped o individual

// Obtener datos de la URL
$stmt = $pdo->prepare("SELECT * FROM urls WHERE id = ?");
$stmt->execute([$url_id]);
$url = $stmt->fetch();

if (!$url) {
    die("URL no encontrada");
}

$total_clicks = $url['clicks'] ?? 0;
$locations = [];
$debug_info = [];

// Analizar datos en url_analytics
try {
    // Primero, veamos cuántos clicks hay en total
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM url_analytics WHERE url_id = ?");
    $stmt->execute([$url_id]);
    $result = $stmt->fetch();
    $total_in_analytics = $result['total'];
    $debug_info['total_in_analytics'] = $total_in_analytics;
    
    // Cuántos tienen geolocalización
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as with_geo 
        FROM url_analytics 
        WHERE url_id = ? 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL 
        AND latitude != 0 
        AND longitude != 0
    ");
    $stmt->execute([$url_id]);
    $result = $stmt->fetch();
    $with_geo = $result['with_geo'];
    $debug_info['with_geo'] = $with_geo;
    
    // Cuántos NO tienen geolocalización
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as without_geo 
        FROM url_analytics 
        WHERE url_id = ? 
        AND (latitude IS NULL OR longitude IS NULL OR latitude = 0 OR longitude = 0)
    ");
    $stmt->execute([$url_id]);
    $result = $stmt->fetch();
    $without_geo = $result['without_geo'];
    $debug_info['without_geo'] = $without_geo;
    
    // Obtener clicks con geolocalización
    if ($view_mode == 'individual') {
        // Vista individual - cada click es un punto
        $stmt = $pdo->prepare("
            SELECT 
                latitude, 
                longitude, 
                country, 
                city,
                ip_address,
                clicked_at,
                referer,
                user_agent
            FROM url_analytics
            WHERE url_id = ? 
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
            AND latitude != 0 
            AND longitude != 0
            ORDER BY clicked_at DESC
            LIMIT 5000
        ");
        $stmt->execute([$url_id]);
        $results = $stmt->fetchAll();
        
        foreach ($results as $loc) {
            $locations[] = [
                'city' => $loc['city'] ?? 'Desconocido',
                'country' => $loc['country'] ?? 'Desconocido',
                'lat' => (float)$loc['latitude'],
                'lng' => (float)$loc['longitude'],
                'clicks' => 1,
                'ip' => $loc['ip_address'],
                'time' => $loc['clicked_at'],
                'source' => $loc['referer'] ? parse_url($loc['referer'], PHP_URL_HOST) : 'Directo'
            ];
        }
    } else {
        // Vista agrupada por ciudad
        $stmt = $pdo->prepare("
            SELECT 
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                country, 
                city, 
                COUNT(*) as clicks,
                COUNT(DISTINCT ip_address) as unique_visitors,
                MIN(clicked_at) as first_click,
                MAX(clicked_at) as last_click
            FROM url_analytics
            WHERE url_id = ? 
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
            AND latitude != 0 
            AND longitude != 0
            GROUP BY country, city
            ORDER BY clicks DESC
        ");
        $stmt->execute([$url_id]);
        $results = $stmt->fetchAll();
        
        foreach ($results as $loc) {
            $locations[] = [
                'city' => $loc['city'] ?? 'Desconocido',
                'country' => $loc['country'] ?? 'Desconocido',
                'lat' => (float)$loc['lat'],
                'lng' => (float)$loc['lng'],
                'clicks' => (int)$loc['clicks'],
                'unique' => (int)$loc['unique_visitors'],
                'first' => $loc['first_click'],
                'last' => $loc['last_click']
            ];
        }
    }
    
    // Si hay clicks sin geolocalización, intentar geolocalizarlos por IP
    if ($without_geo > 0) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT ip_address, COUNT(*) as clicks
            FROM url_analytics 
            WHERE url_id = ? 
            AND (latitude IS NULL OR latitude = 0)
            AND ip_address IS NOT NULL
            GROUP BY ip_address
            LIMIT 20
        ");
        $stmt->execute([$url_id]);
        $ips_without_geo = $stmt->fetchAll();
        $debug_info['sample_ips_without_geo'] = array_slice($ips_without_geo, 0, 5);
    }
    
} catch (Exception $e) {
    $debug_info['error'] = $e->getMessage();
}

// Si faltan muchos datos de geolocalización, agregar estimaciones
if ($with_geo < $total_clicks * 0.5 && $total_clicks > 0) {
    $missing_clicks = $total_clicks - $with_geo;
    $debug_info['estimated_clicks'] = $missing_clicks;
    
    // Distribución estimada para clicks faltantes
    $estimated_distribution = [
        ['Madrid', 'España', 40.4168, -3.7038, 0.15],
        ['Barcelona', 'España', 41.3851, 2.1734, 0.10],
        ['Ciudad de México', 'México', 19.4326, -99.1332, 0.12],
        ['Buenos Aires', 'Argentina', -34.6037, -58.3816, 0.08],
        ['Bogotá', 'Colombia', 4.7110, -74.0721, 0.06],
        ['Santiago', 'Chile', -33.4489, -70.6693, 0.05],
        ['Lima', 'Perú', -12.0464, -77.0428, 0.05],
        ['Caracas', 'Venezuela', 10.4806, -66.9036, 0.04],
        ['Montevideo', 'Uruguay', -34.9011, -56.1645, 0.03],
        ['Quito', 'Ecuador', -0.1807, -78.4678, 0.03],
        ['Valencia', 'España', 39.4699, -0.3763, 0.03],
        ['Sevilla', 'España', 37.3891, -5.9845, 0.03],
        ['Bilbao', 'España', 43.2630, -2.9350, 0.02],
        ['Zaragoza', 'España', 41.6488, -0.8891, 0.02],
        ['Miami', 'Estados Unidos', 25.7617, -80.1918, 0.03],
        ['Nueva York', 'Estados Unidos', 40.7128, -74.0060, 0.02],
        ['Los Angeles', 'Estados Unidos', 34.0522, -118.2437, 0.02],
        ['Lisboa', 'Portugal', 38.7223, -9.1393, 0.02],
        ['París', 'Francia', 48.8566, 2.3522, 0.02],
        ['Londres', 'Reino Unido', 51.5074, -0.1278, 0.02]
    ];
    
    foreach ($estimated_distribution as $city) {
        $est_clicks = round($missing_clicks * $city[4]);
        if ($est_clicks > 0) {
            $locations[] = [
                'city' => $city[0],
                'country' => $city[1],
                'lat' => $city[2],
                'lng' => $city[3],
                'clicks' => $est_clicks,
                'estimated' => true
            ];
        }
    }
}

$show_debug = isset($_GET['debug']) && $_SESSION['user_id'] == 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de clicks - <?php echo htmlspecialchars($url['title'] ?: $url['short_code']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <style>
        body {
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        #map {
            height: 600px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        
        .map-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        
        .data-notice {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .data-notice.warning {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
        }
        
        .data-notice.info {
            background: #dbeafe;
            border: 1px solid #60a5fa;
            color: #1e40af;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #4f46e5;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .debug-info {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
        }
        
        .legend {
            background: white;
            padding: 10px;
            border-radius: 8px;
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        
        .view-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 5px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        
        .marker-cluster-small {
            background-color: rgba(16, 185, 129, 0.6);
        }
        .marker-cluster-small div {
            background-color: rgba(16, 185, 129, 0.8);
        }
        .marker-cluster-medium {
            background-color: rgba(245, 158, 11, 0.6);
        }
        .marker-cluster-medium div {
            background-color: rgba(245, 158, 11, 0.8);
        }
        .marker-cluster-large {
            background-color: rgba(239, 68, 68, 0.6);
        }
        .marker-cluster-large div {
            background-color: rgba(239, 68, 68, 0.8);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="map-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">
                    <i class="bi bi-geo-alt-fill text-primary"></i>
                    Mapa de clicks: <?php echo htmlspecialchars($url['title'] ?: $url['short_code']); ?>
                </h1>
                <div>
                    <?php if ($_SESSION['user_id'] == 1 && !$show_debug): ?>
                    <a href="?url_id=<?php echo $url_id; ?>&debug=1" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-bug"></i> Debug
                    </a>
                    <?php endif; ?>
                    <a href="analytics_url.php?url_id=<?php echo $url_id; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>
        
        <?php if ($show_debug): ?>
        <div class="debug-info">
            <h5>Debug Info:</h5>
            <pre><?php print_r($debug_info); ?></pre>
        </div>
        <?php endif; ?>
        
        <?php if ($without_geo > 0): ?>
        <div class="data-notice warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Atención:</strong> 
            <?php echo number_format($with_geo); ?> clicks tienen geolocalización, 
            pero <?php echo number_format($without_geo); ?> clicks no tienen datos de ubicación.
            <?php if ($debug_info['estimated_clicks'] ?? 0 > 0): ?>
            Se han añadido <?php echo number_format($debug_info['estimated_clicks']); ?> ubicaciones estimadas.
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_clicks); ?></div>
                <div class="stat-label">Clicks totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($with_geo ?? 0); ?></div>
                <div class="stat-label">Con geolocalización</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($locations); ?></div>
                <div class="stat-label">Ubicaciones en mapa</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $total_map_clicks = array_sum(array_column($locations, 'clicks'));
                    echo number_format($total_map_clicks);
                    ?>
                </div>
                <div class="stat-label">Clicks mostrados</div>
            </div>
        </div>
        
        <?php if (!empty($locations)): ?>
        <div style="position: relative;">
            <div class="view-toggle">
                <div class="btn-group btn-group-sm">
                    <a href="?url_id=<?php echo $url_id; ?>&mode=grouped" 
                       class="btn <?php echo $view_mode == 'grouped' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Agrupado
                    </a>
                    <a href="?url_id=<?php echo $url_id; ?>&mode=individual" 
                       class="btn <?php echo $view_mode == 'individual' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Individual
                    </a>
                </div>
            </div>
            
            <div id="map"></div>
            
            <div class="legend">
                <h6 style="margin: 0 0 10px 0; font-size: 14px;">Leyenda</h6>
                <div style="display: flex; align-items: center; margin-bottom: 5px; font-size: 12px;">
                    <div style="width: 12px; height: 12px; border-radius: 50%; background: #10b981; margin-right: 8px;"></div>
                    <span>Datos reales</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 5px; font-size: 12px;">
                    <div style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; margin-right: 8px;"></div>
                    <span>Datos estimados</span>
                </div>
                <div style="display: flex; align-items: center; font-size: 12px;">
                    <div style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444; margin-right: 8px;"></div>
                    <span>50+ clicks</span>
                </div>
            </div>
        </div>
        
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
        <script>
        // Inicializar mapa
        const map = L.map('map').setView([20, -10], 3);
        
        // Capa de mapa
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Datos
        const locations = <?php echo json_encode($locations); ?>;
        const viewMode = '<?php echo $view_mode; ?>';
        
        // Crear grupo de marcadores
        const markers = L.markerClusterGroup({
            chunkedLoading: true,
            spiderfyOnMaxZoom: true,
            maxClusterRadius: viewMode === 'individual' ? 40 : 60,
            iconCreateFunction: function(cluster) {
                const count = cluster.getChildCount();
                let size = 'small';
                if (count > 50) size = 'large';
                else if (count > 10) size = 'medium';
                
                return new L.DivIcon({
                    html: '<div><span>' + count + '</span></div>',
                    className: 'marker-cluster marker-cluster-' + size,
                    iconSize: new L.Point(40, 40)
                });
            }
        });
        
        // Función para obtener color
        function getColor(loc) {
            if (loc.estimated) return '#f59e0b';
            if (loc.clicks > 50) return '#ef4444';
            if (loc.clicks > 10) return '#3b82f6';
            return '#10b981';
        }
        
        // Agregar marcadores
        locations.forEach(loc => {
            const size = Math.min(8 + Math.log(loc.clicks) * 3, 25);
            
            const marker = L.circleMarker([loc.lat, loc.lng], {
                radius: viewMode === 'individual' ? 6 : size,
                fillColor: getColor(loc),
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: loc.estimated ? 0.5 : 0.8
            });
            
            // Popup diferente según el modo
            let popupContent = '';
            if (viewMode === 'individual') {
                popupContent = `
                    <strong>${loc.city}, ${loc.country}</strong><br>
                    <i class="bi bi-clock"></i> ${new Date(loc.time).toLocaleString('es-ES')}<br>
                    <i class="bi bi-globe"></i> Desde: ${loc.source}<br>
                    <small>IP: ${loc.ip}</small>
                `;
            } else {
                popupContent = `
                    <strong>${loc.city}, ${loc.country}</strong><br>
                    <i class="bi bi-cursor-fill"></i> ${loc.clicks} clicks<br>
                    ${loc.unique ? `<i class="bi bi-people"></i> ${loc.unique} visitantes únicos<br>` : ''}
                    ${loc.estimated ? '<em>Datos estimados</em>' : ''}
                `;
            }
            
            marker.bindPopup(popupContent);
            markers.addLayer(marker);
        });
        
        map.addLayer(markers);
        
        // Ajustar vista
        if (locations.length > 0) {
            setTimeout(() => {
                map.fitBounds(markers.getBounds().pad(0.1));
            }, 100);
        }
        
        // Control de escala
        L.control.scale({
            imperial: false,
            metric: true
        }).addTo(map);
        </script>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-geo-alt" style="font-size: 3rem; color: #e2e8f0;"></i>
            <h3 class="mt-3">No hay datos de ubicación</h3>
            <p class="text-muted">No se encontraron datos de geolocalización para esta URL.</p>
        </div>
        <?php endif; ?>
        
        <?php if ($without_geo > 10 && $_SESSION['user_id'] == 1): ?>
        <div class="data-notice info mt-3">
            <i class="bi bi-lightbulb"></i>
            <strong>Tip para admin:</strong> 
            Puedes ejecutar <code>update_countries.php</code> para intentar geolocalizar las IPs faltantes.
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
