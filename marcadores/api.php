<?php
// api.php - API endpoints COMPLETOS con analytics_summary CORREGIDO
header('Content-Type: application/json');
require_once 'config.php';
require_once 'functions.php';

// Verificar que sea una petición válida
if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción requerida']);
    exit;
}

$action = $_GET['action'];

// =================================================
// ENDPOINTS DE BOOKMARKS/URLs
// =================================================

// Exportar bookmarks del usuario (API formato original)
if ($action === 'export_bookmarks') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.short_code,
                u.title,
                u.original_url,
                u.clicks,
                u.created_at,
                u.is_public,
                cd.domain as custom_domain
            FROM urls u
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id
            WHERE u.user_id = ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $urls = $stmt->fetchAll();
        
        // Construir URLs completas
        foreach ($urls as &$url) {
            $domain = $url['custom_domain'] ?? '0ln.org';
            $url['short_url'] = "https://{$domain}/{$url['short_code']}";
            $url['created_date'] = date('Y-m-d H:i:s', strtotime($url['created_at']));
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($urls),
            'urls' => $urls,
            'exported_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error exportando: ' . $e->getMessage()]);
    }
    exit;
}

// Exportar en formato JSON estructurado (nueva acción)
if ($action === 'export_json') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.short_code,
                u.title,
                u.original_url,
                u.clicks,
                u.created_at,
                u.updated_at,
                u.is_public,
                u.user_id,
                cd.domain as custom_domain
            FROM urls u
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id
            WHERE u.user_id = ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $urls = $stmt->fetchAll();
        
        // Construir URLs completas y agregar metadatos
        foreach ($urls as &$url) {
            $domain = $url['custom_domain'] ?? '0ln.org';
            $url['short_url'] = "https://{$domain}/{$url['short_code']}";
            $url['created_date'] = date('Y-m-d H:i:s', strtotime($url['created_at']));
            $url['created_timestamp'] = strtotime($url['created_at']);
            
            // Agregar información adicional
            $url['url_length'] = strlen($url['original_url']);
            $url['code_length'] = strlen($url['short_code']);
            $url['is_custom_code'] = !preg_match('/^[A-Za-z0-9]{6}$/', $url['short_code']);
            $url['domain_type'] = $url['custom_domain'] ? 'custom' : 'default';
        }
        
        // Obtener estadísticas del usuario
        $userInfo = getCurrentUserInfo();
        
        // Stats adicionales
        $totalClicks = array_sum(array_column($urls, 'clicks'));
        $activeUrls = count(array_filter($urls, function($url) { return $url['clicks'] > 0; }));
        $customCodes = count(array_filter($urls, function($url) { return $url['is_custom_code']; }));
        
        // Retornar JSON estructurado completo
        echo json_encode([
            'success' => true,
            'export_metadata' => [
                'user_id' => $user_id,
                'username' => $userInfo['username'] ?? 'Unknown',
                'exported_at' => date('Y-m-d H:i:s'),
                'export_timestamp' => time(),
                'total_urls' => count($urls),
                'format' => 'structured_json',
                'version' => '1.0'
            ],
            'statistics' => [
                'total_clicks' => $totalClicks,
                'active_urls' => $activeUrls,
                'inactive_urls' => count($urls) - $activeUrls,
                'custom_codes' => $customCodes,
                'generated_codes' => count($urls) - $customCodes,
                'average_clicks_per_url' => count($urls) > 0 ? round($totalClicks / count($urls), 2) : 0
            ],
            'urls' => $urls
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error exportando JSON: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener URLs del usuario (para el gestor con paginación)
if ($action === 'get_urls') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(10, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    
    try {
        // Construir query con búsqueda opcional
        $whereClause = "WHERE u.user_id = ?";
        $params = [$user_id];
        
        if (!empty($search)) {
            $whereClause .= " AND (u.short_code LIKE ? OR u.original_url LIKE ? OR u.title LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Contar total
        $countQuery = "SELECT COUNT(*) FROM urls u " . $whereClause;
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Obtener URLs
        $dataQuery = "
            SELECT 
                u.*,
                cd.domain as custom_domain
            FROM urls u
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id
            {$whereClause}
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($dataQuery);
        $stmt->execute($params);
        $urls = $stmt->fetchAll();
        
        // Agregar URLs completas
        foreach ($urls as &$url) {
            $domain = $url['custom_domain'] ?? '0ln.org';
            $url['short_url'] = "https://{$domain}/{$url['short_code']}";
        }
        
        echo json_encode([
            'success' => true,
            'urls' => $urls,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_prev' => $page > 1
            ],
            'search' => $search
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================
// ENDPOINTS DE ANALYTICS (SOLO LECTURA)
// =================================================

// Obtener estadísticas generales del usuario
if ($action === 'get_user_stats') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    $days = $_GET['days'] ?? 30;
    $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;
    
    try {
        require_once 'analytics.php';
        $analytics = new UrlAnalytics($pdo);
        $stats = $analytics->getUserStats($user_id, $days);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'period_days' => $days,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener estadísticas de una URL específica
if ($action === 'get_url_stats') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    $url_id = $_GET['url_id'] ?? null;
    $days = $_GET['days'] ?? 30;
    
    if (!$url_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'URL ID requerido']);
        exit;
    }
    
    try {
        require_once 'analytics.php';
        $analytics = new UrlAnalytics($pdo);
        $stats = $analytics->getUrlStats($url_id, $user_id, $days);
        
        if (!$stats) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'URL no encontrada']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'period_days' => $days,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener resumen rápido de analytics (para el widget del index) - CORREGIDO
if ($action === 'analytics_summary') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    try {
        // PRIMERO: Intentar obtener datos de url_analytics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_clicks,
                COUNT(DISTINCT session_id) as unique_visitors,
                COUNT(DISTINCT url_id) as urls_clicked
            FROM url_analytics 
            WHERE user_id = ? 
            AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$user_id]);
        $analytics_summary = $stmt->fetch();
        
        // FALLBACK: Si no hay datos de analytics, usar datos de la tabla urls
        if ($analytics_summary['total_clicks'] == 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(clicks) as total_clicks,
                    COUNT(*) as urls_clicked,
                    COUNT(CASE WHEN clicks > 0 THEN 1 END) as active_urls
                FROM urls 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $urls_summary = $stmt->fetch();
            
            // Usar datos de URLs si no hay analytics
            $summary = [
                'total_clicks' => (int)($urls_summary['total_clicks'] ?? 0),
                'unique_visitors' => (int)($urls_summary['total_clicks'] ?? 0), // Aproximación
                'urls_clicked' => (int)($urls_summary['urls_clicked'] ?? 0)
            ];
            
            $source = 'urls_table';
        } else {
            $summary = [
                'total_clicks' => (int)$analytics_summary['total_clicks'],
                'unique_visitors' => (int)$analytics_summary['unique_visitors'],
                'urls_clicked' => (int)$analytics_summary['urls_clicked']
            ];
            
            $source = 'analytics_table';
        }
        
        // Top URL del mes (intentar analytics primero, luego urls)
        $topUrl = null;
        try {
            if ($source === 'analytics_table') {
                $stmt = $pdo->prepare("
                    SELECT 
                        u.short_code, 
                        u.title, 
                        COUNT(*) as clicks
                    FROM url_analytics ua
                    JOIN urls u ON ua.url_id = u.id
                    WHERE ua.user_id = ? 
                    AND ua.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY ua.url_id
                    ORDER BY clicks DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $topUrl = $stmt->fetch();
            }
            
            // Fallback: usar datos de tabla urls
            if (!$topUrl) {
                $stmt = $pdo->prepare("
                    SELECT 
                        short_code, 
                        title, 
                        clicks
                    FROM urls 
                    WHERE user_id = ? 
                    AND clicks > 0
                    ORDER BY clicks DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $topUrl = $stmt->fetch();
            }
        } catch (Exception $e) {
            // Ignorar errores de top URL
        }
        
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'top_url' => $topUrl,
            'period' => 'Últimos 30 días',
            'data_source' => $source,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage(),
            'fallback_data' => [
                'total_clicks' => 0,
                'unique_visitors' => 0, 
                'urls_clicked' => 0
            ]
        ]);
    }
    exit;
}

// =================================================
// ENDPOINTS DE ADMINISTRACIÓN
// =================================================

// Limpiar datos de analytics (solo admin)
if ($action === 'clean_analytics') {
    $user_id = getCurrentUserId();
    if (!$user_id || $user_id != 1) { // Solo superadmin
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado - Solo administradores']);
        exit;
    }
    
    $hours = max(1, min(48, intval($_GET['hours'] ?? 2)));
    
    try {
        require_once 'analytics.php';
        $analytics = new UrlAnalytics($pdo);
        $deleted = $analytics->cleanSpamClicks($hours);
        
        echo json_encode([
            'success' => true,
            'message' => "Limpieza completada: {$deleted} registros eliminados",
            'deleted_count' => $deleted,
            'hours_cleaned' => $hours,
            'cleaned_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener estado del sistema
if ($action === 'system_status') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    try {
        // Verificar estado del tracking
        $tracking_disabled = file_exists('tracking_disabled.flag');
        
        // Stats generales del sistema
        $stmt = $pdo->query("SELECT COUNT(*) FROM urls");
        $total_urls = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM url_analytics");
        $total_analytics = $stmt->fetchColumn();
        
        // Stats del usuario actual
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM urls WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_urls = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'system' => [
                'tracking_enabled' => !$tracking_disabled,
                'total_urls' => (int)$total_urls,
                'total_analytics' => (int)$total_analytics,
                'user_urls' => (int)$user_urls,
                'server_time' => date('Y-m-d H:i:s'),
                'server_timestamp' => time(),
                'php_version' => PHP_VERSION,
                'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn()
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================
// ENDPOINTS ESPECIALES
// =================================================

// Buscar URLs
if ($action === 'search_urls') {
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    $query = $_GET['q'] ?? '';
    $limit = max(1, min(50, intval($_GET['limit'] ?? 10)));
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query debe tener al menos 2 caracteres']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.short_code,
                u.title,
                u.original_url,
                u.clicks,
                cd.domain as custom_domain
            FROM urls u
            LEFT JOIN custom_domains cd ON u.domain_id = cd.id
            WHERE u.user_id = ? 
            AND (u.short_code LIKE ? OR u.original_url LIKE ? OR u.title LIKE ?)
            ORDER BY u.clicks DESC, u.created_at DESC
            LIMIT ?
        ");
        
        $searchTerm = "%{$query}%";
        $stmt->execute([$user_id, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll();
        
        // Agregar URLs completas
        foreach ($results as &$result) {
            $domain = $result['custom_domain'] ?? '0ln.org';
            $result['short_url'] = "https://{$domain}/{$result['short_code']}";
        }
        
        echo json_encode([
            'success' => true,
            'query' => $query,
            'results' => $results,
            'count' => count($results),
            'searched_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error en búsqueda: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================
// ENDPOINT DESHABILITADO: track_click
// =================================================

// ⚠️ DESHABILITADO: Track manual de click (CAUSA PROBLEMAS)
if ($action === 'track_click') {
    http_response_code(410);
    echo json_encode([
        'success' => false, 
        'message' => 'Endpoint deshabilitado para prevenir tracking automático',
        'note' => 'El tracking se hace solo en redirects reales',
        'alternative' => 'Usa las estadísticas existentes en analytics_summary'
    ]);
    exit;
}

// =================================================
// ACCIÓN NO ENCONTRADA
// =================================================

http_response_code(404);
echo json_encode([
    'success' => false, 
    'message' => 'Acción no encontrada: ' . $action,
    'available_actions' => [
        'export_bookmarks',
        'export_json',
        'get_urls', 
        'search_urls',
        'get_user_stats',
        'get_url_stats',
        'analytics_summary',
        'system_status',
        'clean_analytics'
    ],
    'api_version' => '1.0',
    'documentation' => 'Consulta la documentación para más detalles sobre cada endpoint'
], JSON_PRETTY_PRINT);
?>
