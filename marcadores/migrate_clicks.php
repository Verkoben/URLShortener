<?php
// migrate_clicks.php - Migrar clicks históricos a analytics detallados (CORREGIDO)
session_start();
require_once 'config.php';

// Solo superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("Acceso denegado");
}

$url_id = isset($_GET['url_id']) ? (int)$_GET['url_id'] : 0;

if (!$url_id) {
    die("URL ID requerido");
}

try {
    // Obtener información de la URL
    $stmt = $pdo->prepare("SELECT * FROM urls WHERE id = ?");
    $stmt->execute([$url_id]);
    $url = $stmt->fetch();
    
    if (!$url) {
        die("URL no encontrada");
    }
    
    // Verificar qué columnas existen en url_analytics
    $stmt = $pdo->query("SHOW COLUMNS FROM url_analytics");
    $existing_columns = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    
    echo "<h3>Columnas detectadas en url_analytics:</h3>";
    echo "<pre>" . print_r(array_keys($existing_columns), true) . "</pre>";
    
    // Países y ciudades para distribución realista
    $locations = [
        ['España', 'ES', 'Madrid', 'Madrid', 40.4168, -3.7038, 0.20],
        ['España', 'ES', 'Barcelona', 'Cataluña', 41.3851, 2.1734, 0.15],
        ['México', 'MX', 'Ciudad de México', 'CDMX', 19.4326, -99.1332, 0.15],
        ['Argentina', 'AR', 'Buenos Aires', 'Buenos Aires', -34.6037, -58.3816, 0.10],
        ['Estados Unidos', 'US', 'Miami', 'Florida', 25.7617, -80.1918, 0.08],
        ['Colombia', 'CO', 'Bogotá', 'Cundinamarca', 4.7110, -74.0721, 0.07],
        ['Chile', 'CL', 'Santiago', 'Metropolitana', -33.4489, -70.6693, 0.06],
        ['Perú', 'PE', 'Lima', 'Lima', -12.0464, -77.0428, 0.05],
        ['Estados Unidos', 'US', 'Nueva York', 'Nueva York', 40.7128, -74.0060, 0.05],
        ['Venezuela', 'VE', 'Caracas', 'Distrito Capital', 10.4806, -66.9036, 0.04],
        ['Ecuador', 'EC', 'Quito', 'Pichincha', -0.1807, -78.4678, 0.03],
        ['Uruguay', 'UY', 'Montevideo', 'Montevideo', -34.9011, -56.1645, 0.02]
    ];
    
    $browsers = [
        ['Chrome', 'Windows 10', 'desktop', 0.50],
        ['Chrome', 'Android', 'mobile', 0.20],
        ['Safari', 'Mac OS X', 'desktop', 0.10],
        ['Safari', 'iOS', 'mobile', 0.08],
        ['Firefox', 'Windows 10', 'desktop', 0.06],
        ['Edge', 'Windows 10', 'desktop', 0.04],
        ['Chrome', 'Linux', 'desktop', 0.02]
    ];
    
    $referrers = [
        ['direct', 0.40],
        ['https://google.com', 0.25],
        ['https://facebook.com', 0.15],
        ['https://twitter.com', 0.10],
        ['https://linkedin.com', 0.05],
        ['https://instagram.com', 0.05]
    ];
    
    $total_clicks = (int)$url['clicks'];
    $days_since_creation = min(90, floor((time() - strtotime($url['created_at'])) / 86400));
    
    echo "<h2>Migrando {$total_clicks} clicks históricos...</h2>";
    
    // Construir consulta dinámicamente basada en columnas existentes
    $fields = ['url_id', 'user_id', 'ip_address', 'clicked_at', 'created_at'];
    $values = ['?', '?', '?', '?', '?'];
    
    // Agregar campos opcionales si existen
    if (isset($existing_columns['session_id'])) {
        $fields[] = 'session_id';
        $values[] = '?';
    }
    if (isset($existing_columns['user_agent'])) {
        $fields[] = 'user_agent';
        $values[] = '?';
    }
    if (isset($existing_columns['referer'])) {
        $fields[] = 'referer';
        $values[] = '?';
    }
    if (isset($existing_columns['country'])) {
        $fields[] = 'country';
        $values[] = '?';
    }
    if (isset($existing_columns['country_code'])) {
        $fields[] = 'country_code';
        $values[] = '?';
    }
    if (isset($existing_columns['city'])) {
        $fields[] = 'city';
        $values[] = '?';
    }
    if (isset($existing_columns['region'])) {
        $fields[] = 'region';
        $values[] = '?';
    }
    if (isset($existing_columns['latitude'])) {
        $fields[] = 'latitude';
        $values[] = '?';
    }
    if (isset($existing_columns['longitude'])) {
        $fields[] = 'longitude';
        $values[] = '?';
    }
    if (isset($existing_columns['browser'])) {
        $fields[] = 'browser';
        $values[] = '?';
    }
    if (isset($existing_columns['os'])) {
        $fields[] = 'os';
        $values[] = '?';
    }
    
    $sql = "INSERT INTO url_analytics (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    echo "<p>Query preparada: <code>{$sql}</code></p>";
    
    $migrated = 0;
    $errors = 0;
    
    // Distribuir clicks en el tiempo
    for ($i = 0; $i < $total_clicks; $i++) {
        try {
            // Fecha aleatoria dentro del período
            $days_ago = rand(0, max(1, $days_since_creation - 1));
            $hours = rand(0, 23);
            $minutes = rand(0, 59);
            $clicked_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days -{$hours} hours -{$minutes} minutes"));
            
            // Seleccionar ubicación basada en probabilidad
            $location = selectByProbability($locations);
            
            // Seleccionar navegador/dispositivo
            $browser_data = selectByProbability($browsers);
            
            // Seleccionar referrer
            $referrer_data = selectByProbability($referrers);
            
            // Generar IP simulada
            $ip = generateRealisticIP($location[1]);
            
            // Preparar datos para insertar
            $data = [
                $url_id,
                $url['user_id'],
                $ip,
                $clicked_at,
                $clicked_at
            ];
            
            // Agregar datos opcionales en el mismo orden que los campos
            if (isset($existing_columns['session_id'])) {
                $data[] = uniqid('hist_', true);
            }
            if (isset($existing_columns['user_agent'])) {
                $data[] = generateUserAgent($browser_data[0], $browser_data[1]);
            }
            if (isset($existing_columns['referer'])) {
                $data[] = $referrer_data[0];
            }
            if (isset($existing_columns['country'])) {
                $data[] = $location[0];
            }
            if (isset($existing_columns['country_code'])) {
                $data[] = $location[1];
            }
            if (isset($existing_columns['city'])) {
                $data[] = $location[2];
            }
            if (isset($existing_columns['region'])) {
                $data[] = $location[3];
            }
            if (isset($existing_columns['latitude'])) {
                $data[] = $location[4];
            }
            if (isset($existing_columns['longitude'])) {
                $data[] = $location[5];
            }
            if (isset($existing_columns['browser'])) {
                $data[] = $browser_data[0];
            }
            if (isset($existing_columns['os'])) {
                $data[] = $browser_data[1];
            }
            
            // Ejecutar inserción
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            $migrated++;
            
            if ($migrated % 10 == 0) {
                echo "Migrados: {$migrated}/{$total_clicks}<br>";
                flush();
            }
            
        } catch (Exception $e) {
            $errors++;
            echo "<small style='color: red;'>Error en click {$i}: " . $e->getMessage() . "</small><br>";
        }
    }
    
    echo "<h3 style='color: green;'>✅ Migración completada:</h3>";
    echo "<ul>";
    echo "<li>Clicks migrados exitosamente: <strong>{$migrated}</strong></li>";
    echo "<li>Errores: <strong>{$errors}</strong></li>";
    echo "</ul>";
    echo "<a href='analytics_url.php?url_id={$url_id}' class='btn btn-primary' style='display: inline-block; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px;'>Ver estadísticas actualizadas</a>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

function selectByProbability($items) {
    $rand = mt_rand() / mt_getrandmax();
    $cumulative = 0;
    
    foreach ($items as $item) {
        $cumulative += $item[count($item) - 1];
        if ($rand <= $cumulative) {
            return $item;
        }
    }
    
    return $items[0];
}

function generateRealisticIP($country_code) {
    // IPs típicas por país (simuladas)
    $ip_ranges = [
        'ES' => ['83.', '84.', '85.', '213.'],
        'MX' => ['187.', '189.', '201.', '200.'],
        'AR' => ['181.', '186.', '190.', '200.'],
        'US' => ['72.', '98.', '173.', '24.'],
        'CO' => ['181.', '186.', '190.', '191.'],
        'CL' => ['181.', '186.', '190.', '200.'],
        'PE' => ['181.', '186.', '190.', '200.'],
        'VE' => ['186.', '190.', '200.', '201.'],
        'EC' => ['181.', '186.', '190.', '200.'],
        'UY' => ['181.', '186.', '190.', '200.']
    ];
    
    $prefix = isset($ip_ranges[$country_code]) 
        ? $ip_ranges[$country_code][array_rand($ip_ranges[$country_code])]
        : '192.';
    
    return $prefix . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
}

function generateUserAgent($browser, $os) {
    $versions = [
        'Chrome' => ['96', '97', '98', '99', '100', '101', '102'],
        'Safari' => ['14', '15', '16'],
        'Firefox' => ['95', '96', '97', '98', '99', '100'],
        'Edge' => ['96', '97', '98', '99', '100']
    ];
    
    $version = isset($versions[$browser]) 
        ? $versions[$browser][array_rand($versions[$browser])]
        : '100';
    
    // Generar user agents realistas
    if ($browser == 'Chrome' && $os == 'Windows 10') {
        return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$version}.0.0.0 Safari/537.36";
    } elseif ($browser == 'Chrome' && $os == 'Android') {
        return "Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$version}.0.0.0 Mobile Safari/537.36";
    } elseif ($browser == 'Safari' && $os == 'Mac OS X') {
        return "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/{$version}.0 Safari/605.1.15";
    } elseif ($browser == 'Safari' && $os == 'iOS') {
        return "Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/{$version}.0 Mobile/15E148 Safari/604.1";
    } else {
        return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$version}.0.0.0 Safari/537.36";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migración de Clicks</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
        code { background: #e9ecef; padding: 2px 5px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
</body>
</html>
