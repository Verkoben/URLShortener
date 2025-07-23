<?php
// analytics.php - Sistema de analytics mejorado con detección dinámica de columnas

class UrlAnalytics {
    private $db;
    private $available_columns = [];
    
    public function __construct($pdo) {
        $this->db = $pdo;
        $this->detectAvailableColumns();
    }
    
    /**
     * Detectar qué columnas están disponibles en url_analytics
     */
    private function detectAvailableColumns() {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM url_analytics");
            $this->available_columns = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {
            $this->available_columns = [];
        }
    }
    
    /**
     * Verificar si una columna existe
     */
    private function hasColumn($column) {
        return isset($this->available_columns[$column]);
    }
    
    /**
     * Registrar un click con toda la información disponible
     */
    public function trackClick($url_id, $user_id = null) {
        try {
            // Obtener información del visitante
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $referer = $_SERVER['HTTP_REFERER'] ?? 'direct';
            
            // Generar session_id si no existe
            if (!isset($_SESSION['analytics_session'])) {
                $_SESSION['analytics_session'] = uniqid('sess_', true);
            }
            $session_id = $_SESSION['analytics_session'];
            
            // Parsear user agent
            $browser_info = $this->parseUserAgent($user_agent);
            
            // Obtener información geográfica
            $geo_info = $this->getGeoInfo($ip);
            
            // Construir consulta dinámicamente
            $fields = ['url_id', 'ip_address', 'clicked_at', 'created_at'];
            $values = ['?', '?', 'NOW()', 'NOW()'];
            $params = [$url_id, $ip];
            
            if ($user_id && $this->hasColumn('user_id')) {
                $fields[] = 'user_id';
                $values[] = '?';
                $params[] = $user_id;
            }
            
            if ($this->hasColumn('session_id')) {
                $fields[] = 'session_id';
                $values[] = '?';
                $params[] = $session_id;
            }
            
            if ($this->hasColumn('user_agent')) {
                $fields[] = 'user_agent';
                $values[] = '?';
                $params[] = $user_agent;
            }
            
            if ($this->hasColumn('referer')) {
                $fields[] = 'referer';
                $values[] = '?';
                $params[] = $referer;
            }
            
            // Información del navegador
            if ($this->hasColumn('browser')) {
                $fields[] = 'browser';
                $values[] = '?';
                $params[] = $browser_info['browser'];
            }
            
            if ($this->hasColumn('os')) {
                $fields[] = 'os';
                $values[] = '?';
                $params[] = $browser_info['os'];
            }
            
            if ($this->hasColumn('device')) {
                $fields[] = 'device';
                $values[] = '?';
                $params[] = $browser_info['device'];
            }
            
            // Información geográfica
            if ($geo_info) {
                if ($this->hasColumn('country') && isset($geo_info['country'])) {
                    $fields[] = 'country';
                    $values[] = '?';
                    $params[] = $geo_info['country'];
                }
                
                if ($this->hasColumn('country_code') && isset($geo_info['country_code'])) {
                    $fields[] = 'country_code';
                    $values[] = '?';
                    $params[] = $geo_info['country_code'];
                }
                
                if ($this->hasColumn('city') && isset($geo_info['city'])) {
                    $fields[] = 'city';
                    $values[] = '?';
                    $params[] = $geo_info['city'];
                }
                
                if ($this->hasColumn('region') && isset($geo_info['region'])) {
                    $fields[] = 'region';
                    $values[] = '?';
                    $params[] = $geo_info['region'];
                }
                
                if ($this->hasColumn('latitude') && isset($geo_info['latitude'])) {
                    $fields[] = 'latitude';
                    $values[] = '?';
                    $params[] = $geo_info['latitude'];
                }
                
                if ($this->hasColumn('longitude') && isset($geo_info['longitude'])) {
                    $fields[] = 'longitude';
                    $values[] = '?';
                    $params[] = $geo_info['longitude'];
                }
            }
            
            $sql = "INSERT INTO url_analytics (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Actualizar contador en tabla urls
            $stmt = $this->db->prepare("UPDATE urls SET clicks = clicks + 1 WHERE id = ?");
            $stmt->execute([$url_id]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error tracking click: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estadísticas completas de una URL
     */
    public function getUrlStats($url_id, $user_id = null, $days = 30) {
        try {
            // Verificar permisos
            if ($user_id) {
                $stmt = $this->db->prepare("SELECT id FROM urls WHERE id = ? AND user_id = ?");
                $stmt->execute([$url_id, $user_id]);
                if (!$stmt->fetch()) {
                    // Verificar si es admin
                    $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    if (!$user || ($user['role'] != 'admin' && $user['role'] != 'superadmin')) {
                        return null;
                    }
                }
            }
            
            // Obtener información de la URL
            $stmt = $this->db->prepare("SELECT * FROM urls WHERE id = ?");
            $stmt->execute([$url_id]);
            $url_info = $stmt->fetch();
            
            if (!$url_info) {
                return null;
            }
            
            return [
                'url_info' => $url_info,
                'general' => $this->getGeneralStats($url_id, $days),
                'daily_clicks' => $this->getDailyClicks($url_id, $days),
                'hourly_clicks' => $this->getHourlyClicks($url_id, 7), // Últimos 7 días para distribución horaria
                'referrers' => $this->getReferrerStats($url_id, $days),
                'countries' => $this->getCountryStats($url_id, $days),
                'cities' => $this->getCityStats($url_id, $days),
                'browsers' => $this->getBrowserStats($url_id, $days),
                'devices' => $this->getDeviceStats($url_id, $days),
                'os' => $this->getOSStats($url_id, $days),
                'recent_clicks' => $this->getRecentClicks($url_id, 20),
                'period_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Error getting URL stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener estadísticas generales
     */
    private function getGeneralStats($url_id, $days) {
        try {
            $stats = [];
            
            // Total de clicks
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_clicks
                FROM url_analytics
                WHERE url_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$url_id, $days]);
            $result = $stmt->fetch();
            $stats['total_clicks'] = $result['total_clicks'] ?? 0;
            
            // Visitantes únicos (por session_id si existe, sino por IP)
            if ($this->hasColumn('session_id')) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT session_id) as unique_visitors
                    FROM url_analytics
                    WHERE url_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT ip_address) as unique_visitors
                    FROM url_analytics
                    WHERE url_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
            }
            $stmt->execute([$url_id, $days]);
            $result = $stmt->fetch();
            $stats['unique_visitors'] = $result['unique_visitors'] ?? 0;
            
            // IPs únicas
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ip_address) as unique_ips
                FROM url_analytics
                WHERE url_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$url_id, $days]);
            $result = $stmt->fetch();
            $stats['unique_ips'] = $result['unique_ips'] ?? 0;
            
            // Primer y último click
            $stmt = $this->db->prepare("
                SELECT 
                    MIN(clicked_at) as first_click,
                    MAX(clicked_at) as last_click
                FROM url_analytics
                WHERE url_id = ?
            ");
            $stmt->execute([$url_id]);
            $result = $stmt->fetch();
            $stats['first_click'] = $result['first_click'];
            $stats['last_click'] = $result['last_click'];
            
            return $stats;
            
        } catch (Exception $e) {
            return [
                'total_clicks' => 0,
                'unique_visitors' => 0,
                'unique_ips' => 0,
                'first_click' => null,
                'last_click' => null
            ];
        }
    }
    
    /**
     * Obtener clicks diarios
     */
    private function getDailyClicks($url_id, $days) {
        try {
            $unique_field = $this->hasColumn('session_id') ? 'session_id' : 'ip_address';
            
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(clicked_at) as date,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT {$unique_field}) as unique_visitors
                FROM url_analytics
                WHERE url_id = ? 
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$url_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener distribución horaria
     */
    private function getHourlyClicks($url_id, $days) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    HOUR(clicked_at) as hour,
                    COUNT(*) as clicks
                FROM url_analytics
                WHERE url_id = ? 
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY HOUR(clicked_at)
                ORDER BY hour ASC
            ");
            $stmt->execute([$url_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de referentes
     */
    private function getReferrerStats($url_id, $days) {
        try {
            if (!$this->hasColumn('referer')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN referer = 'direct' OR referer = '' THEN 'direct'
                        WHEN referer LIKE '%google%' THEN 'google.com'
                        WHEN referer LIKE '%facebook%' THEN 'facebook.com'
                        WHEN referer LIKE '%twitter%' THEN 'twitter.com'
                        WHEN referer LIKE '%linkedin%' THEN 'linkedin.com'
                        WHEN referer LIKE '%instagram%' THEN 'instagram.com'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(referer, 'www.', ''), '/', 3), '/', -1)
                    END as referer_domain,
                    COUNT(*) as clicks
                FROM url_analytics
                WHERE url_id = ? 
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY referer_domain
                ORDER BY clicks DESC
                LIMIT 10
            ");
            $stmt->execute([$url_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas por país
     */
    private function getCountryStats($url_id, $days) {
        try {
            if (!$this->hasColumn('country')) {
                return [];
            }
            
            $country_code_field = $this->hasColumn('country_code') ? 'country_code' : "NULL as country_code";
            
            $stmt = $this->db->prepare("
                SELECT 
                    country,
                    {$country_code_field},
                    COUNT(*) as clicks,
                    COUNT(DISTINCT ip_address) as unique_visitors
                FROM url_analytics
                WHERE url_id = ? 
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND country IS NOT NULL
                GROUP BY country, country_code
                ORDER BY clicks DESC
                LIMIT 20
            ");
            $stmt->execute([$url_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas por ciudad
     */
    private function getCityStats($url_id, $days) {
        try {
            if (!$this->hasColumn('city') || !$this->hasColumn('country')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    city,
                    country,
                    COUNT(*) as clicks
                FROM url_analytics
                WHERE url_id = ? 
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND city IS NOT NULL
                GROUP BY city, country
                ORDER BY clicks DESC
                LIMIT 20
            ");
            $stmt->execute([$url_id, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de navegadores
     */
    private function getBrowserStats($url_id, $days) {
        try {
            if ($this->hasColumn('browser')) {
                $stmt = $this->db->prepare("
                    SELECT 
                        browser,
                        COUNT(*) as clicks
                    FROM url_analytics
                    WHERE url_id = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND browser IS NOT NULL
                    GROUP BY browser
                    ORDER BY clicks DESC
                ");
                $stmt->execute([$url_id, $days]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else if ($this->hasColumn('user_agent')) {
                // Extraer navegador del user_agent
                $stmt = $this->db->prepare("
                    SELECT 
                        CASE 
                            WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edge%' THEN 'Chrome'
                            WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
                            WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                            WHEN user_agent LIKE '%Edge%' THEN 'Edge'
                            WHEN user_agent LIKE '%Opera%' OR user_agent LIKE '%OPR%' THEN 'Opera'
                            ELSE 'Other'
                        END as browser,
                        COUNT(*) as clicks
                    FROM url_analytics
                    WHERE url_id = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY browser
                    ORDER BY clicks DESC
                ");
                $stmt->execute([$url_id, $days]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return [];
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de dispositivos
     */
    private function getDeviceStats($url_id, $days) {
        try {
            if ($this->hasColumn('device')) {
                $stmt = $this->db->prepare("
                    SELECT 
                        device,
                        COUNT(*) as clicks
                    FROM url_analytics
                    WHERE url_id = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND device IS NOT NULL
                    GROUP BY device
                    ORDER BY clicks DESC
                ");
                $stmt->execute([$url_id, $days]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else if ($this->hasColumn('user_agent')) {
                // Extraer dispositivo del user_agent
                $stmt = $this->db->prepare("
                    SELECT 
                        CASE 
                            WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'mobile'
                            WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'tablet'
                            ELSE 'desktop'
                        END as device,
                        COUNT(*) as clicks
                    FROM url_analytics
                    WHERE url_id = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY device
                    ORDER BY clicks DESC
                ");
                $stmt->execute([$url_id, $days]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                return [];
            }
            
            // Agregar iconos a los dispositivos
            foreach ($results as &$device) {
                switch($device['device']) {
                    case 'mobile':
                        $device['icon'] = 'phone';
                        $device['device'] = 'Mobile';
                        break;
                    case 'tablet':
                        $device['icon'] = 'tablet';
                        $device['device'] = 'Tablet';
                        break;
                    case 'desktop':
                    default:
                        $device['icon'] = 'laptop';
                        $device['device'] = 'Desktop';
                        break;
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de sistemas operativos
     */
    private function getOSStats($url_id, $days) {
        try {
            if ($this->hasColumn('os')) {
                $stmt = $this->db->prepare("
                    SELECT 
                        os,
                        COUNT(*) as clicks
                    FROM url_analytics
                    WHERE url_id = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND os IS NOT NULL
                    GROUP BY os
                    ORDER BY clicks DESC
                ");
                $stmt->execute([$url_id, $days]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else if ($this->hasColumn('user_agent')) {
                // Extraer OS del user_agent
                $stmt = $this->db->prepare("
                    SELECT 
                        CASE 
                            WHEN user_agent LIKE '%Windows NT 10%' THEN 'Windows 10'
                            WHEN user_agent LIKE '%Windows NT 6.3%' THEN 'Windows 8.1'
                            WHEN user_agent LIKE '%Windows NT 6.2%' THEN 'Windows 8'
                            WHEN user_agent LIKE '%Windows NT 6.1%' THEN 'Windows 7'
                            WHEN user_agent LIKE '%Windows%' THEN 'Windows'
                            WHEN user_agent LIKE '%Mac OS X%' THEN 'Mac OS X'
                            WHEN user_agent LIKE '%Android%' THEN 'Android'
                            WHEN user_agent LIKE '%iOS%' OR user_agent LIKE '%iPhone%' OR user_agent LIKE '%iPad%' THEN 'iOS'
                            WHEN user_agent LIKE '%Linux%' THEN 'Linux'
                            ELSE 'Other'
                        END as os,
                        COUNT(*) as clicks
                    FROM url_analytics
                    WHERE url_id = ? 
                    AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY os
                    ORDER BY clicks DESC
                ");
                $stmt->execute([$url_id, $days]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return [];
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener clicks recientes
     */
    private function getRecentClicks($url_id, $limit = 20) {
        try {
            $fields = ['clicked_at', 'ip_address'];
            
            if ($this->hasColumn('country')) $fields[] = 'country';
            if ($this->hasColumn('city')) $fields[] = 'city';
            if ($this->hasColumn('browser')) $fields[] = 'browser';
            if ($this->hasColumn('device')) $fields[] = 'device';
            if ($this->hasColumn('referer')) $fields[] = 'referer';
            
            $sql = "SELECT " . implode(', ', $fields) . " 
                    FROM url_analytics 
                    WHERE url_id = ? 
                    ORDER BY clicked_at DESC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$url_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Parsear User Agent
     */
    private function parseUserAgent($user_agent) {
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'desktop';
        
        // Detectar navegador
        if (preg_match('/Chrome\/([0-9.]+)/', $user_agent) && !preg_match('/Edge/', $user_agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari\/([0-9.]+)/', $user_agent) && !preg_match('/Chrome/', $user_agent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $user_agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Edge\/([0-9.]+)/', $user_agent) || preg_match('/Edg\/([0-9.]+)/', $user_agent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Opera|OPR/', $user_agent)) {
            $browser = 'Opera';
        }
        
        // Detectar SO
        if (preg_match('/Windows NT 10/', $user_agent)) {
            $os = 'Windows 10';
        } elseif (preg_match('/Windows NT 11/', $user_agent)) {
            $os = 'Windows 11';
        } elseif (preg_match('/Windows/', $user_agent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/', $user_agent)) {
            $os = 'Mac OS X';
        } elseif (preg_match('/Android/', $user_agent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/', $user_agent)) {
            $os = 'iOS';
        } elseif (preg_match('/Linux/', $user_agent)) {
            $os = 'Linux';
        }
        
        // Detectar dispositivo
        if (preg_match('/Mobile|Android|iPhone/', $user_agent)) {
            $device = 'mobile';
        } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
            $device = 'tablet';
        }
        
        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device
        ];
    }
    
    /**
     * Obtener información geográfica de una IP
     */
    private function getGeoInfo($ip) {
        try {
            // Verificar si es IP local
            if ($this->isLocalIP($ip)) {
                return [
                    'country' => 'Local',
                    'country_code' => 'LO',
                    'city' => 'Localhost',
                    'region' => 'Local',
                    'latitude' => 0,
                    'longitude' => 0
                ];
            }
            
            // Primero intentar con ip-api.com (gratis, sin API key)
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,regionName,lat,lon";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $response) {
                $data = json_decode($response, true);
                if ($data && $data['status'] == 'success') {
                    return [
                        'country' => $data['country'] ?? 'Unknown',
                        'country_code' => $data['countryCode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'latitude' => $data['lat'] ?? 0,
                        'longitude' => $data['lon'] ?? 0
                    ];
                }
            }
            
            // Si falla, intentar con ipinfo.io
            $url = "https://ipinfo.io/{$ip}/json";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if ($data && !isset($data['error'])) {
                    $loc = isset($data['loc']) ? explode(',', $data['loc']) : [0, 0];
                    return [
                        'country' => $data['country'] ?? 'Unknown',
                        'country_code' => $data['country'] ?? '',
                        'city' => $data['city'] ?? '',
                        'region' => $data['region'] ?? '',
                        'latitude' => $loc[0] ?? 0,
                        'longitude' => $loc[1] ?? 0
                    ];
                }
            }
            
            // Si todo falla, retornar valores por defecto
            return [
                'country' => 'Unknown',
                'country_code' => '',
                'city' => '',
                'region' => '',
                'latitude' => 0,
                'longitude' => 0
            ];
            
        } catch (Exception $e) {
            error_log("Error getting geo info: " . $e->getMessage());
            return [
                'country' => 'Unknown',
                'country_code' => '',
                'city' => '',
                'region' => '',
                'latitude' => 0,
                'longitude' => 0
            ];
        }
    }
    
    /**
     * Verificar si es una IP local
     */
    private function isLocalIP($ip) {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost']) || 
               preg_match('/^192\.168\./', $ip) ||
               preg_match('/^10\./', $ip) ||
               preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip);
    }
    
    /**
     * Obtener estadísticas para mapa geográfico
     */
    public function getGeoStats($url_id = null, $user_id = null, $days = 30) {
        try {
            $where = [];
            $params = [];
            
            if ($url_id) {
                $where[] = "ua.url_id = ?";
                $params[] = $url_id;
            }
            
            if ($user_id) {
                $where[] = "u.user_id = ?";
                $params[] = $user_id;
            }
            
            if ($days) {
                $where[] = "ua.clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $params[] = $days;
            }
            
            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
            
            // Verificar columnas disponibles para geo
            $geo_fields = [];
            if ($this->hasColumn('country')) $geo_fields[] = 'country';
            if ($this->hasColumn('country_code')) $geo_fields[] = 'country_code';
            if ($this->hasColumn('city')) $geo_fields[] = 'city';
            if ($this->hasColumn('region')) $geo_fields[] = 'region';
            if ($this->hasColumn('latitude')) $geo_fields[] = 'latitude';
            if ($this->hasColumn('longitude')) $geo_fields[] = 'longitude';
            
            if (empty($geo_fields)) {
                return [];
            }
            
            $sql = "
                SELECT 
                    " . implode(', ', $geo_fields) . ",
                    COUNT(*) as clicks,
                    COUNT(DISTINCT ua.ip_address) as unique_visitors
                FROM url_analytics ua
                LEFT JOIN urls u ON ua.url_id = u.id
                {$whereClause}
                GROUP BY " . implode(', ', $geo_fields) . "
                HAVING " . (in_array('latitude', $geo_fields) ? "latitude IS NOT NULL AND longitude IS NOT NULL" : "1=1") . "
                ORDER BY clicks DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting geo stats: " . $e->getMessage());
            return [];
        }
    }
}
?>
