<?php
// my-urls.php - CON AUTO-BLACKLIST POR INACTIVIDAD
session_start();

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Configuración
define('DB_HOST', 'localhost');
define('DB_NAME', 'url_shortener');
define('DB_USER', 'root');
define('DB_PASS', 'trapisonda');

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ========== AUTO-BLACKLIST POR INACTIVIDAD ==========
try {
    // Configuración de días de inactividad
    $dias_sin_clicks = 30;    // URLs nuevas sin ningún click
    $dias_inactivas = 60;     // URLs viejas sin actividad reciente
    
    // Configuración especial para usuarios específicos
    if ($user_id == 12) {  // Chino - más agresivo
        $dias_sin_clicks = 7;
        $dias_inactivas = 14;
    }
    
    // 1. Auto-blacklist URLs sin clicks después de X días de creación
    $stmt = $db->prepare("
        INSERT INTO url_blacklist (user_id, short_code)
        SELECT user_id, short_code 
        FROM urls 
        WHERE user_id = ? 
        AND clicks = 0 
        AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        AND short_code NOT IN (
            SELECT short_code FROM url_blacklist WHERE user_id = ?
        )
        ON DUPLICATE KEY UPDATE created_at = NOW()
    ");
    $stmt->execute([$user_id, $dias_sin_clicks, $user_id]);
    $nuevas_blacklist = $stmt->rowCount();
    
    // 2. Auto-blacklist URLs que no han sido accedidas recientemente
    $stmt = $db->prepare("
        INSERT INTO url_blacklist (user_id, short_code)
        SELECT user_id, short_code 
        FROM urls 
        WHERE user_id = ? 
        AND clicks > 0
        AND (
            last_accessed IS NULL 
            OR last_accessed < DATE_SUB(NOW(), INTERVAL ? DAY)
        )
        AND short_code NOT IN (
            SELECT short_code FROM url_blacklist WHERE user_id = ?
        )
        ON DUPLICATE KEY UPDATE created_at = NOW()
    ");
    $stmt->execute([$user_id, $dias_inactivas, $user_id]);
    $viejas_blacklist = $stmt->rowCount();
    
    // Log si se añadieron URLs a blacklist
    if ($nuevas_blacklist > 0 || $viejas_blacklist > 0) {
        error_log("AUTO-BLACKLIST Usuario $user_id: $nuevas_blacklist sin clicks, $viejas_blacklist inactivas");
    }
    
} catch (Exception $e) {
    error_log("Error en auto-blacklist: " . $e->getMessage());
}

// ========== QUERY PRINCIPAL - OBTENER URLs ==========
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.short_code,
            u.original_url,
            u.title,
            u.clicks,
            u.created_at,
            u.last_accessed,
            u.domain_id,
            cd.domain as custom_domain
        FROM urls u
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id
        WHERE u.user_id = ? 
        AND NOT EXISTS (
            SELECT 1 
            FROM url_blacklist bl 
            WHERE bl.short_code = u.short_code 
            AND bl.user_id = ?
        )
        ORDER BY u.created_at DESC
    ");
    
    $stmt->execute([$user_id, $user_id]);
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Construir respuesta
    $result = [];
    foreach ($urls as $url) {
        // Determinar dominio correcto
        if (!empty($url['custom_domain'])) {
            $short_url = 'https://' . $url['custom_domain'] . '/' . $url['short_code'];
        } else {
            $short_url = 'https://0ln.eu/' . $url['short_code'];
        }
        
        $result[] = [
            'id' => (int)$url['id'],
            'short_code' => $url['short_code'],
            'short_url' => $short_url,
            'original_url' => $url['original_url'],
            'title' => $url['title'] ?: 'Sin título',
            'clicks' => (int)$url['clicks'],
            'created_at' => $url['created_at'],
            'last_accessed' => $url['last_accessed']
        ];
    }
    
    // Modo debug (añadir ?debug=1 a la URL)
    if (isset($_GET['debug'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM url_blacklist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total_blacklist = $stmt->fetchColumn();
        
        $debug_info = [
            'urls' => $result,
            'stats' => [
                'total_urls' => count($result),
                'total_blacklisted' => $total_blacklist,
                'auto_blacklist_config' => [
                    'dias_sin_clicks' => $dias_sin_clicks,
                    'dias_inactivas' => $dias_inactivas
                ]
            ]
        ];
        
        echo json_encode($debug_info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("Error en my-urls.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
