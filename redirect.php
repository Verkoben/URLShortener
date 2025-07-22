<?php
// redirect.php - CON TRACKING, GEOLOCALIZACIÓN Y METADATOS PARA REDES SOCIALES
require_once 'conf.php';

// Función mejorada de geolocalización
function getGeoLocation($ip) {
    // IPs privadas/locales
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return [
            'country' => 'Local Network',
            'country_code' => 'XX',
            'city' => 'Private',
            'region' => null,
            'lat' => null,
            'lon' => null
        ];
    }
    
    // Intentar con múltiples servicios de geolocalización
    $services = [
        // ip-api.com (100 requests per minute free)
        function($ip) {
            $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,lat,lon";
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 3]
            ]));
            
            if ($response) {
                $data = json_decode($response, true);
                if ($data && $data['status'] == 'success') {
                    return [
                        'country' => $data['country'] ?? 'Unknown',
                        'country_code' => $data['countryCode'] ?? null,
                        'city' => $data['city'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null
                    ];
                }
            }
            return false;
        },
        
        // ipapi.co (1000 requests per day free)
        function($ip) {
            $url = "https://ipapi.co/{$ip}/json/";
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'header' => "User-Agent: PHP\r\n"
                ]
            ]));
            
            if ($response) {
                $data = json_decode($response, true);
                if ($data && !isset($data['error'])) {
                    return [
                        'country' => $data['country_name'] ?? 'Unknown',
                        'country_code' => $data['country_code'] ?? null,
                        'city' => $data['city'] ?? null,
                        'region' => $data['region'] ?? null,
                        'lat' => $data['latitude'] ?? null,
                        'lon' => $data['longitude'] ?? null
                    ];
                }
            }
            return false;
        }
    ];
    
    // Intentar con cada servicio
    foreach ($services as $service) {
        $result = $service($ip);
        if ($result !== false) {
            return $result;
        }
    }
    
    // Si todos fallan
    return [
        'country' => 'Unknown',
        'country_code' => null,
        'city' => null,
        'region' => null,
        'lat' => null,
        'lon' => null
    ];
}

// Obtener el código de la URL
$request_uri = $_SERVER['REQUEST_URI'];
$code = trim($request_uri, '/');

// Limpiar código de parámetros
if (strpos($code, '?') !== false) {
    $code = substr($code, 0, strpos($code, '?'));
}

// Validar código
if (empty($code) || !preg_match('/^[a-zA-Z0-9\-_]+$/', $code)) {
    header('Location: ' . BASE_URL);
    exit;
}

try {
    // Conexión a base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar URL con más información para metadatos
    $stmt = $pdo->prepare("
        SELECT u.*, cd.domain 
        FROM urls u 
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
        WHERE u.short_code = ? 
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $url = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($url) {
        // Detectar bots de redes sociales
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = false;
        $bot_patterns = [
            'facebookexternalhit',
            'Facebot',
            'Twitterbot',
            'LinkedInBot',
            'WhatsApp',
            'TelegramBot',
            'Slackbot',
            'Discordbot',
            'Pinterest',
            'Applebot'
        ];
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                $is_bot = true;
                break;
            }
        }
        
        // Si es un bot, mostrar página con metadatos
        if ($is_bot) {
            // Generar URL completa
            $short_url = !empty($url['domain']) 
                ? "https://" . $url['domain'] . "/" . $url['short_code']
                : BASE_URL . $url['short_code'];
            
            // Preparar metadatos
            $title = !empty($url['title']) ? htmlspecialchars($url['title']) : 'Enlace compartido';
            $description = !empty($url['description']) ? htmlspecialchars($url['description']) : 'Visita este enlace';
            $image = !empty($url['og_image']) ? htmlspecialchars($url['og_image']) : BASE_URL . 'assets/og-default.png';
            
            // Mostrar página HTML con metadatos
            ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    
    <!-- Metadatos Open Graph para Facebook -->
    <meta property="og:title" content="<?php echo $title; ?>" />
    <meta property="og:description" content="<?php echo $description; ?>" />
    <meta property="og:url" content="<?php echo $short_url; ?>" />
    <meta property="og:image" content="<?php echo $image; ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>" />
    
    <!-- Metadatos para Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo $title; ?>" />
    <meta name="twitter:description" content="<?php echo $description; ?>" />
    <meta name="twitter:image" content="<?php echo $image; ?>" />
    <meta name="twitter:url" content="<?php echo $short_url; ?>" />
    
    <!-- Metadatos adicionales -->
    <meta name="description" content="<?php echo $description; ?>" />
    <link rel="canonical" href="<?php echo $short_url; ?>" />
    
    <!-- Redirección para navegadores normales -->
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($url['original_url']); ?>">
    <script>window.location.href = "<?php echo htmlspecialchars($url['original_url']); ?>";</script>
</head>
<body>
    <p>Redirigiendo a <a href="<?php echo htmlspecialchars($url['original_url']); ?>"><?php echo $title; ?></a>...</p>
</body>
</html>
            <?php
            exit;
        }
        
        // Para usuarios normales, continuar con el tracking
        
        // Obtener IP real del visitante
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        
        // Obtener información de geolocalización
        $geo = getGeoLocation($ip);
        
        // Obtener otros datos
        $referer = $_SERVER['HTTP_REFERER'] ?? 'direct';
        
        // Parsear referer domain
        $referer_domain = 'direct';
        if ($referer != 'direct') {
            $parsed = parse_url($referer);
            $referer_domain = isset($parsed['host']) ? $parsed['host'] : 'unknown';
        }
        
        // Detectar browser, OS y device
        $ua_lower = strtolower($user_agent);
        
        // Browser
        $browser = 'Unknown';
        if (strpos($ua_lower, 'firefox') !== false) $browser = 'Firefox';
        elseif (strpos($ua_lower, 'edg') !== false) $browser = 'Edge';
        elseif (strpos($ua_lower, 'chrome') !== false) $browser = 'Chrome';
        elseif (strpos($ua_lower, 'safari') !== false) $browser = 'Safari';
        elseif (strpos($ua_lower, 'opera') !== false || strpos($ua_lower, 'opr') !== false) $browser = 'Opera';
        
        // OS
        $os = 'Unknown';
        if (strpos($ua_lower, 'windows') !== false) $os = 'Windows';
        elseif (strpos($ua_lower, 'mac') !== false) $os = 'macOS';
        elseif (strpos($ua_lower, 'linux') !== false) $os = 'Linux';
        elseif (strpos($ua_lower, 'android') !== false) $os = 'Android';
        elseif (strpos($ua_lower, 'iphone') !== false || strpos($ua_lower, 'ipad') !== false) $os = 'iOS';
        
        // Device
        $device = 'desktop';
        if (strpos($ua_lower, 'mobile') !== false || strpos($ua_lower, 'android') !== false || strpos($ua_lower, 'iphone') !== false) {
            $device = 'mobile';
        } elseif (strpos($ua_lower, 'tablet') !== false || strpos($ua_lower, 'ipad') !== false) {
            $device = 'tablet';
        }
        
        // Generar session ID
        $session_id = md5($ip . $user_agent . date('Y-m-d'));
        
        // Actualizar estadísticas básicas
        $stmt = $pdo->prepare("
            UPDATE urls 
            SET clicks = clicks + 1, 
                last_accessed = NOW() 
            WHERE short_code = ?
        ");
        $stmt->execute([$code]);
        
        // Registrar click detallado en click_stats (tabla legacy)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO click_stats 
                (url_id, ip_address, user_agent, referer, clicked_at, country, country_code, city) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            $stmt->execute([
                $url['id'], 
                $ip, 
                $user_agent, 
                $referer,
                $geo['country'],
                $geo['country_code'],
                $geo['city']
            ]);
            $click_stats_id = $pdo->lastInsertId();
        } catch (Exception $e) {
            $click_stats_id = null;
            error_log("Error en click_stats: " . $e->getMessage());
        }
        
        // Insertar en url_analytics (nueva tabla con geolocalización completa)
        try {
            // Verificar si existe la tabla
            $tableExists = $pdo->query("SHOW TABLES LIKE 'url_analytics'")->rowCount() > 0;
            
            if ($tableExists) {
                $stmt = $pdo->prepare("
                    INSERT INTO url_analytics (
                        url_id, user_id, clicked_at, ip_address, 
                        country, country_code, city, region, latitude, longitude,
                        user_agent, referer, referer_domain, 
                        browser, os, device, session_id, click_stats_id
                    ) VALUES (
                        ?, ?, NOW(), ?, 
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, 
                        ?, ?, ?, ?, ?
                    )
                ");
                
                $stmt->execute([
                    $url['id'],
                    $url['user_id'],
                    $ip,
                    $geo['country'],
                    $geo['country_code'],
                    $geo['city'],
                    $geo['region'],
                    $geo['lat'],
                    $geo['lon'],
                    $user_agent,
                    $referer,
                    $referer_domain,
                    $browser,
                    $os,
                    $device,
                    $session_id,
                    $click_stats_id
                ]);
            }
        } catch (Exception $e) {
            error_log("Error en url_analytics: " . $e->getMessage());
        }
        
        // Redirigir
        header("Location: " . $url['original_url'], true, 301);
        exit;
        
    } else {
        // URL no encontrada
        header('Location: ' . BASE_URL . '?error=not_found');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error en redirect.php: " . $e->getMessage());
    header('Location: ' . BASE_URL . '?error=server');
    exit;
}
?>
