<?php
// geo_map.php - Mapa visual de clicks con permisos de superadmin
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$url_id = isset($_GET['url_id']) ? (int)$_GET['url_id'] : 0;
$embed = isset($_GET['embed']) ? true : false;

// Verificar si es superadmin
$is_superadmin = false;
// Opción 1: Por ID de usuario
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

// Verificar permisos
if ($url_id) {
    if ($is_superadmin) {
        // Superadmin puede ver cualquier URL
        $stmt = $pdo->prepare("
            SELECT u.*, us.username as owner_username, us.email as owner_email 
            FROM urls u
            LEFT JOIN users us ON u.user_id = us.id
            WHERE u.id = ?
        ");
        $stmt->execute([$url_id]);
    } else {
        // Usuario normal solo sus URLs
        $stmt = $pdo->prepare("SELECT * FROM urls WHERE id = ? AND user_id = ?");
        $stmt->execute([$url_id, $user_id]);
    }
    
    $url_info = $stmt->fetch();
    if (!$url_info) {
        die("<!DOCTYPE html>
        <html>
        <head>
            <title>Sin permisos</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    background: #f5f5f5;
                }
                .error-container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .error-icon {
                    font-size: 48px;
                    color: #dc3545;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #333;
                    margin-bottom: 10px;
                }
                p {
                    color: #666;
                    margin-bottom: 20px;
                }
                a {
                    color: #007bff;
                    text-decoration: none;
                }
                a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <div class='error-icon'>🔒</div>
                <h1>Sin permisos</h1>
                <p>No tienes permisos para ver este mapa.</p>
                <a href='index.php'>← Volver al inicio</a>
            </div>
        </body>
        </html>");
    }
}

// Obtener datos geográficos
$where = $url_id ? "ua.url_id = ?" : "ua.user_id = ?";
$param = $url_id ?: $user_id;

// Si es superadmin viendo una URL específica de otro usuario, usar el user_id del propietario
if ($url_id && $is_superadmin && isset($url_info['user_id'])) {
    $owner_id = $url_info['user_id'];
} else {
    $owner_id = $user_id;
}

$stmt = $pdo->prepare("
    SELECT 
        country,
        country_code,
        city,
        region,
        latitude,
        longitude,
        COUNT(*) as clicks,
        COUNT(DISTINCT ip_address) as unique_visitors,
        COUNT(DISTINCT session_id) as sessions
    FROM url_analytics ua
    WHERE {$where}
    AND latitude IS NOT NULL 
    AND longitude IS NOT NULL
    GROUP BY country, country_code, city, region, latitude, longitude
    ORDER BY clicks DESC
");
$stmt->execute([$param]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas generales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT country) as total_countries,
        COUNT(DISTINCT city) as total_cities,
        COUNT(DISTINCT ip_address) as total_ips,
        COUNT(*) as total_clicks
    FROM url_analytics
    WHERE {$where}
    AND country != 'Unknown'
");
$stmt->execute([$param]);
$stats = $stmt->fetch();

// Título de la página
if ($url_id) {
    $pageTitle = "Mapa: " . ($url_info['title'] ?: $url_info['short_code']);
    if ($is_superadmin && $url_info['user_id'] != $user_id) {
        $pageTitle .= " (Usuario: " . ($url_info['owner_username'] ?? 'ID ' . $url_info['user_id']) . ")";
    }
} else {
    $pageTitle = "Mapa de todos tus clicks";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.1/dist/leaflet.markercluster.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        #map { 
            height: <?php echo $embed ? '100vh' : 'calc(100vh - 60px)'; ?>; 
            width: 100%; 
        }
        .header {
            height: 60px;
            background: white;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            padding: 0 20px;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .stats {
            display: flex;
            gap: 30px;
        }
        .stat {
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #666;
            margin-top: 2px;
        }
        .popup-content {
            min-width: 200px;
        }
        .popup-content h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        .popup-city {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .popup-stat {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 14px;
        }
        .popup-stat-label {
            color: #666;
        }
        .popup-stat-value {
            font-weight: 600;
            color: #333;
        }
        .legend {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .legend h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin: 8px 0;
            font-size: 12px;
        }
        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #333;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .back-link {
            color: #007bff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border: 1px solid #007bff;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .back-link:hover {
            background: #007bff;
            color: white;
        }
        /* Heat map legend gradient */
        .heat-gradient {
            width: 100%;
            height: 20px;
            background: linear-gradient(to right, #FFEDA0, #FEB24C, #FD8D3C, #FC4E2A, #E31A1C, #BD0026, #800026);
            border-radius: 4px;
            margin: 10px 0 5px 0;
        }
        .heat-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php if (!$embed): ?>
    <div class="header">
        <h1>
            🌍 <?php echo htmlspecialchars($pageTitle); ?>
            <?php if ($is_superadmin && $url_id && isset($url_info['user_id']) && $url_info['user_id'] != $user_id): ?>
            <span class="admin-badge">Vista Admin</span>
            <?php endif; ?>
        </h1>
        <div class="stats">
            <div class="stat">
                <div class="stat-value"><?php echo number_format($stats['total_countries']); ?></div>
                <div class="stat-label">Países</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo number_format($stats['total_cities']); ?></div>
                <div class="stat-label">Ciudades</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo number_format($stats['total_ips']); ?></div>
                <div class="stat-label">IPs únicas</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo number_format($stats['total_clicks']); ?></div>
                <div class="stat-label">Clicks totales</div>
            </div>
        </div>
        <a href="analytics_url.php?url_id=<?php echo $url_id; ?>" class="back-link">
            ← Volver a estadísticas
        </a>
    </div>
    <?php endif; ?>
    
    <div id="map"></div>
    
    <?php if (!$embed): ?>
    <div class="legend">
        <h4>Intensidad de clicks</h4>
        <div class="heat-gradient"></div>
        <div class="heat-labels">
            <span>Menos</span>
            <span>Más</span>
        </div>
        <div style="margin-top: 15px;">
            <div class="legend-item">
                <div class="legend-color" style="background: #ff7800; width: 30px; height: 30px;"></div>
                <span>Mayor tamaño = Más clicks</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Inicializar mapa
    var map = L.map('map', {
        center: [20, 0],
        zoom: 2,
        minZoom: 2,
        maxZoom: 18,
        worldCopyJump: true
    });
    
    // Capa de mapa con estilo diferente para modo embed
    <?php if ($embed): ?>
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    <?php else: ?>
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors | © OpenStreetMap'
    }).addTo(map);
    <?php endif; ?>
    
    // Datos de ubicaciones
    var locations = <?php echo json_encode($locations); ?>;
    
    // Crear cluster de marcadores
    var markers = L.markerClusterGroup({
        chunkedLoading: true,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 50,
        iconCreateFunction: function(cluster) {
            var childCount = cluster.getChildCount();
            var c = ' marker-cluster-';
            if (childCount < 10) {
                c += 'small';
            } else if (childCount < 100) {
                c += 'medium';
            } else {
                c += 'large';
            }
            return new L.DivIcon({
                html: '<div><span>' + childCount + '</span></div>',
                className: 'marker-cluster' + c,
                iconSize: new L.Point(40, 40)
            });
        }
    });
    
    // Función para determinar el color según clicks (heat map)
    function getColor(clicks) {
        return clicks > 1000 ? '#800026' :
               clicks > 500  ? '#BD0026' :
               clicks > 200  ? '#E31A1C' :
               clicks > 100  ? '#FC4E2A' :
               clicks > 50   ? '#FD8D3C' :
               clicks > 20   ? '#FEB24C' :
               clicks > 10   ? '#FED976' :
                               '#FFEDA0';
    }
    
    // Calcular tamaño del marcador basado en clicks
    function getRadius(clicks) {
        // Escala logarítmica para mejor distribución visual
        return Math.min(30, Math.max(5, 5 + Math.log(clicks + 1) * 3));
    }
    
    // Agregar marcadores
    locations.forEach(function(loc) {
        if (loc.latitude && loc.longitude) {
            var radius = getRadius(loc.clicks);
            
            var marker = L.circleMarker([loc.latitude, loc.longitude], {
                radius: radius,
                fillColor: getColor(loc.clicks),
                color: '#000',
                weight: 1,
                opacity: 1,
                fillOpacity: 0.8
            });
            
            // Contenido del popup mejorado
            var popupContent = `
                <div class="popup-content">
                    <h4>
                        ${loc.country_code ? 
                            '<img src="https://flagcdn.com/24x18/' + loc.country_code.toLowerCase() + '.png" ' +
                            'style="margin-right: 8px; vertical-align: middle; border-radius: 2px;" ' +
                            'onerror="this.style.display=\'none\'">' 
                            : ''}
                        ${loc.country}
                    </h4>
                    ${loc.city ? '<div class="popup-city">📍 ' + loc.city + 
                        (loc.region && loc.region !== loc.city ? ', ' + loc.region : '') + 
                        '</div>' : ''}
                    
                    <div class="popup-stat">
                        <span class="popup-stat-label">Clicks totales:</span>
                        <span class="popup-stat-value">${loc.clicks.toLocaleString()}</span>
                    </div>
                    <div class="popup-stat">
                        <span class="popup-stat-label">Visitantes únicos:</span>
                        <span class="popup-stat-value">${loc.unique_visitors.toLocaleString()}</span>
                    </div>
                    ${loc.sessions ? 
                    '<div class="popup-stat">' +
                        '<span class="popup-stat-label">Sesiones:</span>' +
                        '<span class="popup-stat-value">' + loc.sessions.toLocaleString() + '</span>' +
                    '</div>' : ''}
                </div>
            `;
            
            marker.bindPopup(popupContent, {
                maxWidth: 300,
                className: 'custom-popup'
            });
            
            // Evento hover para resaltar
            marker.on('mouseover', function(e) {
                this.setStyle({
                    weight: 3,
                    opacity: 1
                });
            });
            
            marker.on('mouseout', function(e) {
                this.setStyle({
                    weight: 1,
                    opacity: 1
                });
            });
            
            markers.addLayer(marker);
        }
    });
    
    map.addLayer(markers);
    
    // Ajustar vista para mostrar todos los marcadores
    if (locations.length > 0) {
        setTimeout(function() {
            map.fitBounds(markers.getBounds(), {
                padding: [50, 50],
                maxZoom: 12
            });
        }, 100);
    } else {
        // Si no hay datos, mostrar vista global
        map.setView([20, 0], 2);
    }
    
    // Control de zoom
    L.control.zoom({
        position: 'topleft'
    }).addTo(map);
    
    // Escala
    L.control.scale({
        imperial: false,
        position: 'bottomleft'
    }).addTo(map);
    
    <?php if ($embed): ?>
    // Si está embebido, ajustar altura del mapa
    map.invalidateSize();
    
    // Recargar tamaño cuando cambie la ventana
    window.addEventListener('resize', function() {
        map.invalidateSize();
    });
    <?php endif; ?>
    
    // Función para buscar ubicación
    <?php if (!$embed): ?>
    var searchControl = L.Control.extend({
        options: {
            position: 'topright'
        },
        onAdd: function(map) {
            var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            container.style.background = 'white';
            container.style.padding = '5px';
            container.innerHTML = '<input type="text" placeholder="Buscar país o ciudad..." style="width: 200px; padding: 5px; border: 1px solid #ccc; border-radius: 4px;">';
            
            var input = container.querySelector('input');
            
            L.DomEvent.disableClickPropagation(container);
            
            input.addEventListener('input', function(e) {
                var searchTerm = e.target.value.toLowerCase();
                
                if (searchTerm.length < 2) {
                    markers.clearLayers();
                    locations.forEach(function(loc) {
                        if (loc.latitude && loc.longitude) {
                            var marker = createMarker(loc);
                            markers.addLayer(marker);
                        }
                    });
                    return;
                }
                
                markers.clearLayers();
                var hasResults = false;
                
                locations.forEach(function(loc) {
                    if (loc.latitude && loc.longitude) {
                        var matches = 
                            (loc.country && loc.country.toLowerCase().includes(searchTerm)) ||
                            (loc.city && loc.city.toLowerCase().includes(searchTerm)) ||
                            (loc.region && loc.region.toLowerCase().includes(searchTerm));
                        
                        if (matches) {
                            var marker = createMarker(loc);
                            markers.addLayer(marker);
                            hasResults = true;
                        }
                    }
                });
                
                if (hasResults && markers.getLayers().length > 0) {
                    map.fitBounds(markers.getBounds(), {
                        padding: [50, 50],
                        maxZoom: 10
                    });
                }
            });
            
            return container;
        }
    });
    
    // Función auxiliar para crear marcadores
    function createMarker(loc) {
        var radius = getRadius(loc.clicks);
        
        var marker = L.circleMarker([loc.latitude, loc.longitude], {
            radius: radius,
            fillColor: getColor(loc.clicks),
            color: '#000',
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8
        });
        
        var popupContent = `
            <div class="popup-content">
                <h4>
                    ${loc.country_code ? 
                        '<img src="https://flagcdn.com/24x18/' + loc.country_code.toLowerCase() + '.png" ' +
                        'style="margin-right: 8px; vertical-align: middle; border-radius: 2px;" ' +
                        'onerror="this.style.display=\'none\'">' 
                        : ''}
                    ${loc.country}
                </h4>
                ${loc.city ? '<div class="popup-city">📍 ' + loc.city + 
                    (loc.region && loc.region !== loc.city ? ', ' + loc.region : '') + 
                    '</div>' : ''}
                
                <div class="popup-stat">
                    <span class="popup-stat-label">Clicks totales:</span>
                    <span class="popup-stat-value">${loc.clicks.toLocaleString()}</span>
                </div>
                <div class="popup-stat">
                    <span class="popup-stat-label">Visitantes únicos:</span>
                    <span class="popup-stat-value">${loc.unique_visitors.toLocaleString()}</span>
                </div>
                ${loc.sessions ? 
                '<div class="popup-stat">' +
                    '<span class="popup-stat-label">Sesiones:</span>' +
                    '<span class="popup-stat-value">' + loc.sessions.toLocaleString() + '</span>' +
                '</div>' : ''}
            </div>
        `;
        
        marker.bindPopup(popupContent, {
            maxWidth: 300,
            className: 'custom-popup'
        });
        
        marker.on('mouseover', function(e) {
            this.setStyle({
                weight: 3,
                opacity: 1
            });
        });
        
        marker.on('mouseout', function(e) {
            this.setStyle({
                weight: 1,
                opacity: 1
            });
        });
        
        return marker;
    }
    
    // Añadir control de búsqueda
    map.addControl(new searchControl());
    <?php endif; ?>
    </script>
</body>
</html>
