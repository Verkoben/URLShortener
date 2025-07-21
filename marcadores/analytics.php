<?php
// analytics.php - Sistema completo de analytics CON PROTECCIONES ANTI-SPAM
require_once 'config.php';
require_once 'functions.php';

class UrlAnalytics {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // ========================================
    // CAPTURA DE DATOS CON PROTECCIONES
    // ========================================
    
    public function trackClick($url_id, $short_code, $user_id = null) {
        // 🛑 PROTECCIÓN 1: Flag de emergencia
        if (file_exists('tracking_disabled.flag')) {
            error_log("🛑 EMERGENCY: Tracking deshabilitado para {$short_code}");
            return false;
        }
        
        // 🛑 PROTECCIÓN 2: Verificar que no sea una llamada interna
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($referer, 'analytics') !== false || 
            strpos($referer, 'marcadores') !== false ||
            strpos($referer, 'dashboard') !== false ||
            strpos($referer, '0ln.eu/marcadores') !== false) {
            error_log("🚨 BLOCKED: Click desde sistema interno - {$referer}");
            return false;
        }
        
        // 🛑 PROTECCIÓN 3: Prevenir clicks demasiado rápidos del mismo IP
        $ip = $this->getRealIpAddr();
        if ($ip) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as recent_clicks 
                    FROM url_analytics 
                    WHERE ip_address = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
                ");
                $stmt->execute([$ip]);
                $recent = $stmt->fetch()['recent_clicks'];
                
                if ($recent > 5) {
                    error_log("🚨 BLOCKED: Demasiados clicks rápidos desde {$ip} ({$recent} en 30s)");
                    return false;
                }
            } catch (Exception $e) {
                error_log("Error verificando clicks rápidos: " . $e->getMessage());
            }
        }
        
        // 🛑 PROTECCIÓN 4: Prevenir clicks duplicados de la misma sesión
        $sessionId = session_id() ?: uniqid();
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as session_clicks 
                FROM url_analytics 
                WHERE session_id = ? 
                AND url_id = ?
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute([$sessionId, $url_id]);
            $session_recent = $stmt->fetch()['session_clicks'];
            
            if ($session_recent > 2) {
                error_log("🚨 BLOCKED: Demasiados clicks de la misma sesión {$sessionId}");
                return false;
            }
        } catch (Exception $e) {
            error_log("Error verificando sesión: " . $e->getMessage());
        }
        
        // 🛑 PROTECCIÓN 5: Verificar User Agent válido
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) || strlen($userAgent) < 10) {
            error_log("🚨 BLOCKED: User Agent sospechoso o vacío");
            return false;
        }
        
        // 🛑 PROTECCIÓN 6: No trackear bots conocidos del sistema
        if (preg_match('/curl|wget|python|bot|spider|crawler/i', $userAgent)) {
            error_log("🚨 BLOCKED: Bot detectado - {$userAgent}");
            return false;
        }
        
        try {
            // Detectar dispositivo y browser
            $deviceInfo = $this->detectDevice($userAgent);
            
            // Obtener geolocalización (con cache para evitar muchas llamadas)
            $geoInfo = $this->getGeolocation($ip);
            
            // ✅ INSERTAR TRACKING (solo si pasó todas las protecciones)
            $stmt = $this->pdo->prepare("
                INSERT INTO url_analytics (
                    url_id, user_id, short_code, ip_address, user_agent, referer,
                    country, country_code, city, device_type, browser, os, session_id
                ) VALUES (
                    :url_id, :user_id, :short_code, :ip_address, :user_agent, :referer,
                    :country, :country_code, :city, :device_type, :browser, :os, :session_id
                )
            ");
            
            $result = $stmt->execute([
                ':url_id' => $url_id,
                ':user_id' => $user_id,
                ':short_code' => $short_code,
                ':ip_address' => $ip,
                ':user_agent' => $userAgent,
                ':referer' => $referer,
                ':country' => $geoInfo['country'] ?? null,
                ':country_code' => $geoInfo['country_code'] ?? null,
                ':city' => $geoInfo['city'] ?? null,
                ':device_type' => $deviceInfo['device_type'],
                ':browser' => $deviceInfo['browser'],
                ':os' => $deviceInfo['os'],
                ':session_id' => $sessionId
            ]);
            
            // Actualizar contador en tabla urls
            if ($result) {
                $this->pdo->prepare("UPDATE urls SET clicks = clicks + 1 WHERE id = ?")->execute([$url_id]);
                
                // Actualizar estadísticas diarias si existe la tabla
                $this->updateDailyStats($url_id, $user_id, $deviceInfo['device_type']);
                
                error_log("✅ VALID CLICK: {$short_code} desde {$ip} - {$deviceInfo['device_type']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("❌ Error tracking click: " . $e->getMessage());
            return false;
        }
    }
    
    // ========================================
    // ESTADÍSTICAS GENERALES
    // ========================================
    
    public function getUserStats($user_id, $days = 30) {
        try {
            $since = date('Y-m-d', strtotime("-{$days} days"));
            
            // Stats generales
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_clicks,
                    COUNT(DISTINCT session_id) as unique_visitors,
                    COUNT(DISTINCT url_id) as urls_clicked,
                    COUNT(DISTINCT DATE(clicked_at)) as active_days
                FROM url_analytics 
                WHERE user_id = ? AND clicked_at >= ?
            ");
            $stmt->execute([$user_id, $since]);
            $general = $stmt->fetch();
            
            // Si no hay datos, devolver estructura vacía
            if (!$general || $general['total_clicks'] == 0) {
                return [
                    'general' => [
                        'total_clicks' => 0,
                        'unique_visitors' => 0,
                        'urls_clicked' => 0,
                        'active_days' => 0
                    ],
                    'top_urls' => [],
                    'daily_clicks' => [],
                    'top_countries' => [],
                    'devices' => [],
                    'browsers' => [],
                    'period_days' => $days
                ];
            }
            
            // Top URLs
            $stmt = $this->pdo->prepare("
                SELECT 
                    ua.url_id,
                    u.short_code,
                    u.title,
                    u.original_url,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT ua.session_id) as unique_visitors
                FROM url_analytics ua
                JOIN urls u ON ua.url_id = u.id
                WHERE ua.user_id = ? AND ua.clicked_at >= ?
                GROUP BY ua.url_id
                ORDER BY clicks DESC
                LIMIT 10
            ");
            $stmt->execute([$user_id, $since]);
            $topUrls = $stmt->fetchAll();
            
            // Clicks por día
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(clicked_at) as date,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM url_analytics 
                WHERE user_id = ? AND clicked_at >= ?
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$user_id, $since]);
            $dailyClicks = $stmt->fetchAll();
            
            // Países top
            $stmt = $this->pdo->prepare("
                SELECT 
                    country,
                    country_code,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM url_analytics 
                WHERE user_id = ? AND clicked_at >= ? AND country IS NOT NULL
                GROUP BY country, country_code
                ORDER BY clicks DESC
                LIMIT 10
            ");
            $stmt->execute([$user_id, $since]);
            $topCountries = $stmt->fetchAll();
            
            // Dispositivos
            $stmt = $this->pdo->prepare("
                SELECT 
                    device_type,
                    COUNT(*) as clicks,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM url_analytics WHERE user_id = ? AND clicked_at >= ?), 2) as percentage
                FROM url_analytics 
                WHERE user_id = ? AND clicked_at >= ?
                GROUP BY device_type
                ORDER BY clicks DESC
            ");
            $stmt->execute([$user_id, $since, $user_id, $since]);
            $devices = $stmt->fetchAll();
            
            // Browsers
            $stmt = $this->pdo->prepare("
                SELECT 
                    browser,
                    COUNT(*) as clicks,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM url_analytics WHERE user_id = ? AND clicked_at >= ?), 2) as percentage
                FROM url_analytics 
                WHERE user_id = ? AND clicked_at >= ? AND browser IS NOT NULL
                GROUP BY browser
                ORDER BY clicks DESC
                LIMIT 10
            ");
            $stmt->execute([$user_id, $since, $user_id, $since]);
            $browsers = $stmt->fetchAll();
            
            return [
                'general' => $general,
                'top_urls' => $topUrls,
                'daily_clicks' => $dailyClicks,
                'top_countries' => $topCountries,
                'devices' => $devices,
                'browsers' => $browsers,
                'period_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Error getting user stats: " . $e->getMessage());
            return null;
        }
    }
    
    // ========================================
    // ESTADÍSTICAS POR URL
    // ========================================
    
    public function getUrlStats($url_id, $user_id, $days = 30) {
        try {
            $since = date('Y-m-d', strtotime("-{$days} days"));
            
            // Info básica de la URL
            $stmt = $this->pdo->prepare("
                SELECT u.*, cd.domain 
                FROM urls u 
                LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
                WHERE u.id = ? AND u.user_id = ?
            ");
            $stmt->execute([$url_id, $user_id]);
            $urlInfo = $stmt->fetch();
            
            if (!$urlInfo) {
                return null;
            }
            
            // Stats de la URL
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_clicks,
                    COUNT(DISTINCT session_id) as unique_visitors,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    MIN(clicked_at) as first_click,
                    MAX(clicked_at) as last_click
                FROM url_analytics 
                WHERE url_id = ? AND clicked_at >= ?
            ");
            $stmt->execute([$url_id, $since]);
            $general = $stmt->fetch();
            
            // Clicks por día
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(clicked_at) as date,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM url_analytics 
                WHERE url_id = ? AND clicked_at >= ?
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$url_id, $since]);
            $dailyClicks = $stmt->fetchAll();
            
            // Clicks por hora (últimos 7 días)
            $stmt = $this->pdo->prepare("
                SELECT 
                    HOUR(clicked_at) as hour,
                    COUNT(*) as clicks
                FROM url_analytics 
                WHERE url_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY HOUR(clicked_at)
                ORDER BY hour ASC
            ");
            $stmt->execute([$url_id]);
            $hourlyClicks = $stmt->fetchAll();
            
            // Referrers
            $stmt = $this->pdo->prepare("
                SELECT 
                    CASE 
                        WHEN referer IS NULL OR referer = '' THEN 'Directo'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '://', -1), '/', 1)
                    END as referer_domain,
                    COUNT(*) as clicks
                FROM url_analytics 
                WHERE url_id = ? AND clicked_at >= ?
                GROUP BY referer_domain
                ORDER BY clicks DESC
                LIMIT 10
            ");
            $stmt->execute([$url_id, $since]);
            $referrers = $stmt->fetchAll();
            
            // Países
            $stmt = $this->pdo->prepare("
                SELECT 
                    country,
                    country_code,
                    COUNT(*) as clicks
                FROM url_analytics 
                WHERE url_id = ? AND clicked_at >= ? AND country IS NOT NULL
                GROUP BY country, country_code
                ORDER BY clicks DESC
                LIMIT 15
            ");
            $stmt->execute([$url_id, $since]);
            $countries = $stmt->fetchAll();
            
            return [
                'url_info' => $urlInfo,
                'general' => $general,
                'daily_clicks' => $dailyClicks,
                'hourly_clicks' => $hourlyClicks,
                'referrers' => $referrers,
                'countries' => $countries,
                'period_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Error getting URL stats: " . $e->getMessage());
            return null;
        }
    }
    
    // ========================================
    // FUNCIÓN DE LIMPIEZA
    // ========================================
    
    public function cleanSpamClicks($hours = 2) {
        try {
            // Limpiar clicks de la misma IP en los últimos X minutos (más de 10)
            $stmt = $this->pdo->prepare("
                DELETE FROM url_analytics 
                WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND ip_address IN (
                    SELECT ip_address FROM (
                        SELECT ip_address 
                        FROM url_analytics 
                        WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                        GROUP BY ip_address 
                        HAVING COUNT(*) > 10
                    ) as spam_ips
                )
            ");
            $stmt->execute([$hours, $hours]);
            $deleted = $stmt->rowCount();
            
            error_log("🧹 Limpieza automática: {$deleted} clicks spam eliminados");
            return $deleted;
            
        } catch (Exception $e) {
            error_log("Error en limpieza automática: " . $e->getMessage());
            return 0;
        }
    }
    
    // ========================================
    // UTILIDADES PRIVADAS
    // ========================================
    
    private function getRealIpAddr() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Limpiar IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
        
        // No guardar IPs locales en producción
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) {
            return null;
        }
        
        return $ip;
    }
    
    private function detectDevice($userAgent) {
        $device_type = 'desktop';
        $browser = 'Unknown';
        $os = 'Unknown';
        
        // Detectar bots (ya se bloquean antes, pero por si acaso)
        if (preg_match('/bot|crawl|slurp|spider|curl|wget/i', $userAgent)) {
            $device_type = 'bot';
        }
        // Detectar móvil
        elseif (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
            $device_type = preg_match('/ipad|tablet/i', $userAgent) ? 'tablet' : 'mobile';
        }
        
        // Detectar browser
        if (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/edge/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/opera/i', $userAgent)) $browser = 'Opera';
        
        // Detectar OS
        if (preg_match('/windows/i', $userAgent)) $os = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'macOS';
        elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
        elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
        elseif (preg_match('/iphone|ipad/i', $userAgent)) $os = 'iOS';
        
        return [
            'device_type' => $device_type,
            'browser' => $browser,
            'os' => $os
        ];
    }
    
    private function getGeolocation($ip) {
        if (!$ip || $ip === '0.0.0.0') {
            return [];
        }
        
        // Cache simple para evitar muchas llamadas
        $cache_file = "geo_cache_{$ip}.tmp";
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
            $cached = file_get_contents($cache_file);
            return json_decode($cached, true) ?: [];
        }
        
        try {
            // Usar ip-api.com (gratis, 1000 requests/month)
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'method' => 'GET'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && $data['status'] === 'success') {
                    $result = [
                        'country' => $data['country'],
                        'country_code' => $data['countryCode'],
                        'city' => $data['city']
                    ];
                    
                    // Guardar en cache
                    file_put_contents($cache_file, json_encode($result));
                    
                    return $result;
                }
            }
        } catch (Exception $e) {
            error_log("Error getting geolocation: " . $e->getMessage());
        }
        
        return [];
    }
    
    private function updateDailyStats($url_id, $user_id, $device_type) {
        try {
            // Verificar si existe la tabla daily_stats
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'daily_stats'");
            if (!$stmt->fetch()) {
                return;
            }
            
            $today = date('Y-m-d');
            
            $stmt = $this->pdo->prepare("
                INSERT INTO daily_stats (url_id, user_id, date, total_clicks, desktop_clicks, mobile_clicks, tablet_clicks)
                VALUES (?, ?, ?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                total_clicks = total_clicks + 1,
                desktop_clicks = desktop_clicks + ?,
                mobile_clicks = mobile_clicks + ?,
                tablet_clicks = tablet_clicks + ?
            ");
            
            $desktop = $device_type === 'desktop' ? 1 : 0;
            $mobile = $device_type === 'mobile' ? 1 : 0;
            $tablet = $device_type === 'tablet' ? 1 : 0;
            
            $stmt->execute([
                $url_id, $user_id, $today,
                $desktop, $mobile, $tablet,
                $desktop, $mobile, $tablet
            ]);
            
        } catch (Exception $e) {
            error_log("Error updating daily stats: " . $e->getMessage());
        }
    }
}

// Instancia global solo si existe PDO
if (isset($pdo)) {
    $analytics = new UrlAnalytics($pdo);
    
    // Limpieza automática ocasional (1% de probabilidad)
    if (rand(1, 100) === 1) {
        $analytics->cleanSpamClicks(2);
    }
    
    error_log("✅ analytics.php CON PROTECCIONES cargado - " . date('Y-m-d H:i:s'));
} else {
    error_log("❌ analytics.php: PDO no disponible");
}
?>
