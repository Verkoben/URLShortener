<?php
// DEBUGGING para Twitter - Guardar log de accesos
$debug_file = __DIR__ . '/twitter_debug.log';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'No User Agent';
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$timestamp = date('Y-m-d H:i:s');

// Guardar TODOS los accesos
file_put_contents($debug_file, 
    "$timestamp | URI: $request_uri | UA: $user_agent\n", 
    FILE_APPEND
);

require_once 'conf.php';

// Obtener el código corto de la URL
$request_uri = $_SERVER['REQUEST_URI'];
$short_code = trim($request_uri, '/');
$short_code = preg_replace('/[^a-zA-Z0-9_-]/', '', $short_code); // Limpiar caracteres no válidos

// DEBUG: Código limpio
file_put_contents('bot_detection.log', 
    "$timestamp | URI Original: $request_uri | Code Limpio: $short_code\n", 
    FILE_APPEND
);

// Si no hay código, redirigir al index
if (empty($short_code)) {
    header('Location: index.php');
    exit();
}

// Conectar a la base de datos
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión");
}

// Obtener el dominio desde el que se está accediendo
$current_domain = $_SERVER['HTTP_HOST'];
$main_domain = parse_url(BASE_URL, PHP_URL_HOST);

// Buscar la URL con información del dominio asignado
$stmt = $pdo->prepare("
    SELECT u.*, cd.domain as assigned_domain, cd.user_id as domain_owner
    FROM urls u 
    LEFT JOIN custom_domains cd ON u.domain_id = cd.id
    WHERE u.short_code = ? AND u.active = 1
");
$stmt->execute([$short_code]);
$url = $stmt->fetch();

// DEBUG: URL encontrada
file_put_contents('bot_detection.log', 
    "$timestamp | URL encontrada: " . ($url ? "SÍ - " . $url['original_url'] : "NO") . "\n", 
    FILE_APPEND
);

if ($url) {
    // VERIFICACIÓN DE DOMINIO
    $can_redirect = false;
    
    // Si la URL tiene un dominio asignado
    if ($url['domain_id'] && $url['assigned_domain']) {
        // Solo permitir redirección desde el dominio asignado
        if ($current_domain === $url['assigned_domain']) {
            $can_redirect = true;
        }
    } else {
        // Si no tiene dominio asignado, solo funciona desde el dominio principal
        if ($current_domain === $main_domain) {
            $can_redirect = true;
        }
    }
    
    // Verificar si puede redirigir
    if (!$can_redirect) {
        // Mostrar error o redirigir al dominio correcto
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Dominio Incorrecto</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                    background: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .error-box {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                }
                .error-icon {
                    font-size: 4em;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #333;
                    margin-bottom: 20px;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 30px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    transition: background 0.3s;
                }
                .btn:hover {
                    background: #5a67d8;
                }
                .correct-url {
                    background: #f8f9fa;
                    padding: 10px;
                    border-radius: 5px;
                    margin: 20px 0;
                    font-family: monospace;
                    word-break: break-all;
                }
            </style>
        </head>
        <body>
            <div class="error-box">
                <div class="error-icon">🚫</div>
                <h1>Dominio Incorrecto</h1>
                <p>Esta URL corta no está disponible en este dominio.</p>
                
                <?php if ($url['assigned_domain']): ?>
                    <p>Esta URL solo funciona desde:</p>
                    <div class="correct-url">
                        https://<?php echo htmlspecialchars($url['assigned_domain']); ?>/<?php echo htmlspecialchars($short_code); ?>
                    </div>
                    <a href="https://<?php echo htmlspecialchars($url['assigned_domain']); ?>/<?php echo htmlspecialchars($short_code); ?>" class="btn">
                        Ir al dominio correcto
                    </a>
                <?php else: ?>
                    <p>Esta URL solo funciona desde el dominio principal:</p>
                    <div class="correct-url">
                        <?php echo rtrim(BASE_URL, '/'); ?>/<?php echo htmlspecialchars($short_code); ?>
                    </div>
                    <a href="<?php echo rtrim(BASE_URL, '/'); ?>/<?php echo htmlspecialchars($short_code); ?>" class="btn">
                        Ir al dominio principal
                    </a>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
    
    // Si llegamos aquí, el dominio es correcto, proceder con la redirección
    
    // DETECTAR BOTS DE REDES SOCIALES - MEJORADO
    $is_bot = false;
    
    // Guardar en log si detectamos Twitter
    if (stripos($user_agent, 'Twitter') !== false) {
        file_put_contents($debug_file, 
            "$timestamp | ¡¡¡TWITTER DETECTADO!!! | URL: {$url['original_url']}\n", 
            FILE_APPEND
        );
    }
    
    // Lista ampliada de bots
    $bot_patterns = [
        'bot',
        'crawl', 
        'spider',
        'Twitter',
        'facebook',
        'WhatsApp',
        'Telegram',
        'Slack',
        'Discord',
        'LinkedIn',
        'Pinterest',
        'Skype',
        'redditbot',
        'facebookexternalhit',
        'Facebot',
        'curl', // Para testing
        'wget'  // Para testing
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            $is_bot = true;
            break;
        }
    }
    
    // Forzar modo bot para testing
    if (isset($_GET['debug_meta']) || isset($_GET['twitter']) || isset($_GET['test'])) {
        $is_bot = true;
    }
    
    // DEBUG: Detección de bot
    file_put_contents('bot_detection.log', 
        "$timestamp | UA: $user_agent | is_bot: " . ($is_bot ? 'YES' : 'NO') . " | Code: $short_code\n", 
        FILE_APPEND
    );
    
    // SI ES UN BOT DE REDES SOCIALES, MOSTRAR META TAGS
    if ($is_bot) {
        // DEBUG: Confirmar que entramos aquí
        file_put_contents('bot_detection.log', 
            "$timestamp | ENTRANDO A GENERAR META TAGS para $short_code\n", 
            FILE_APPEND
        );
        
        // Extraer video ID antes de procesar meta tags
        $video_id = null;
        $video_platform = null;
        
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'youtube';
        } elseif (preg_match('/(?:dailymotion\.com\/video\/|dai\.ly\/)([a-zA-Z0-9]+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'dailymotion';
        }
        
        // Función mejorada para obtener meta tags
        function getMetaTags($url_to_fetch) {
            // DEBUG
            file_put_contents('bot_detection.log', 
                date('Y-m-d H:i:s') . " | getMetaTags llamado con: $url_to_fetch\n", 
                FILE_APPEND
            );
            
            $default = [
                'title' => 'Ver contenido',
                'description' => 'Haz clic para ver el contenido completo',
                'image' => '',
                'type' => 'website',
                'site_name' => '',
                'author' => ''
            ];
            
            // Crear contexto común para todas las peticiones
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'follow_location' => true,
                    'header' => [
                        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                        "Accept-Language: es-ES,es;q=0.9,en;q=0.8",
                        "Cache-Control: no-cache"
                    ]
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            // YOUTUBE - Detección mejorada
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url_to_fetch, $matches)) {
                $video_id = $matches[1];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | YouTube detectado: $video_id\n", 
                    FILE_APPEND
                );
                
                // Obtener página de YouTube
                $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
                
                if ($html) {
                    $result = $default;
                    
                    // Extraer título
                    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                        $title = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $result['title'] = str_replace(' - YouTube', '', $title);
                    }
                    
                    // Extraer descripción
                    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    // Imagen de YouTube
                    $result['image'] = "https://i.ytimg.com/vi/{$video_id}/maxresdefault.jpg";
                    
                    // Verificar si existe maxresdefault
                    $headers = @get_headers($result['image']);
                    if (!$headers || strpos($headers[0], '404') !== false) {
                        $result['image'] = "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg";
                    }
                    
                    $result['site_name'] = 'YouTube';
                    return $result;
                }
                
                // Si falla, usar datos por defecto para YouTube
                return [
                    'title' => 'Video de YouTube',
                    'description' => 'Mira este video en YouTube',
                    'image' => "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg",
                    'type' => 'website',
                    'site_name' => 'YouTube',
                    'author' => ''
                ];
            }
            
            // DAILYMOTION - USANDO API MEJORADA CON DEBUGGING COMPLETO
            if (preg_match('/(?:dailymotion\.com\/video\/|dai\.ly\/)([a-zA-Z0-9]+)/', $url_to_fetch, $matches)) {
                $video_id = $matches[1];
                
                // Debug inicial
                file_put_contents('dailymotion_debug.txt', 
                    date('Y-m-d H:i:s') . " | URL: $url_to_fetch | Video ID: $video_id\n", 
                    FILE_APPEND
                );
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Dailymotion detectado: $video_id\n", 
                    FILE_APPEND
                );
                
                // PRIMERO: Intentar con la API de Dailymotion
                $api_fields = 'title,description,thumbnail_720_url,thumbnail_480_url,thumbnail_360_url,owner.screenname';
                $api_url = "https://api.dailymotion.com/video/{$video_id}?fields={$api_fields}";
                
                $api_response = @file_get_contents($api_url, false, $context);
                
                if ($api_response) {
                    $api_data = json_decode($api_response, true);
                    
                    // Debug: guardar respuesta de API
                    file_put_contents('dailymotion_debug.txt', 
                        "API Response: " . json_encode($api_data) . "\n", 
                        FILE_APPEND
                    );
                    
                    if ($api_data && !isset($api_data['error'])) {
                        // Obtener la mejor imagen disponible
                        $image = '';
                        if (!empty($api_data['thumbnail_720_url'])) {
                            $image = $api_data['thumbnail_720_url'];
                        } elseif (!empty($api_data['thumbnail_480_url'])) {
                            $image = $api_data['thumbnail_480_url'];
                        } elseif (!empty($api_data['thumbnail_360_url'])) {
                            $image = $api_data['thumbnail_360_url'];
                        }
                        
                        // Asegurar HTTPS
                        $image = str_replace('http://', 'https://', $image);
                        
                        $result = [
                            'title' => $api_data['title'] ?? 'Video de Dailymotion',
                            'description' => $api_data['description'] ?? 'Ver este video en Dailymotion',
                            'image' => $image,
                            'type' => 'website',
                            'site_name' => 'Dailymotion',
                            'author' => $api_data['owner.screenname'] ?? ''
                        ];
                        
                        // DEBUG: Guardar resultado específico para Dailymotion
                        file_put_contents('dailymotion_meta_result.json', 
                            json_encode($result, JSON_PRETTY_PRINT)
                        );
                        
                        file_put_contents('dailymotion_debug.txt', 
                            "Resultado final: " . json_encode($result) . "\n\n", 
                            FILE_APPEND
                        );
                        
                        file_put_contents('bot_detection.log', 
                            date('Y-m-d H:i:s') . " | Dailymotion meta tags generados OK\n", 
                            FILE_APPEND
                        );
                        
                        return $result;
                    }
                }
                
                // SEGUNDO: Si la API falla, intentar con el HTML
                file_put_contents('dailymotion_debug.txt', 
                    "API falló, intentando con HTML...\n", 
                    FILE_APPEND
                );
                
                $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
                
                if ($html) {
                    $result = $default;
                    
                    // Buscar JSON-LD primero (más confiable)
                    if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $json_matches)) {
                        $json_data = json_decode($json_matches[1], true);
                        if ($json_data) {
                            if (isset($json_data['name'])) $result['title'] = $json_data['name'];
                            if (isset($json_data['description'])) $result['description'] = $json_data['description'];
                            if (isset($json_data['thumbnailUrl'])) $result['image'] = $json_data['thumbnailUrl'];
                        }
                    }
                    
                    // Fallback a meta tags
                    if (empty($result['title'])) {
                        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }
                    
                    if (empty($result['description'])) {
                        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }
                    
                    if (empty($result['image'])) {
                        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $result['image'] = $matches[1];
                        }
                    }
                    
                    // Asegurar HTTPS
                    if (!empty($result['image'])) {
                        $result['image'] = str_replace('http://', 'https://', $result['image']);
                    }
                    
                    $result['site_name'] = 'Dailymotion';
                    
                    if (!empty($result['title']) && !empty($result['image'])) {
                        return $result;
                    }
                }
                
                // Si todo falla, usar datos por defecto
                file_put_contents('dailymotion_debug.txt', 
                    "Todo falló, usando datos por defecto\n\n", 
                    FILE_APPEND
                );
                
                return [
                    'title' => 'Video de Dailymotion',
                    'description' => 'Ver este video en Dailymotion',
                    'image' => "https://www.dailymotion.com/thumbnail/video/{$video_id}",
                    'type' => 'website',
                    'site_name' => 'Dailymotion',
                    'author' => ''
                ];
            }
            
            // PARA CUALQUIER OTRA URL (periódicos, blogs, etc.)
            file_put_contents('bot_detection.log', 
                date('Y-m-d H:i:s') . " | URL genérica: $url_to_fetch\n", 
                FILE_APPEND
            );
            
            $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
            
            if (!$html) {
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | ERROR: No se pudo obtener HTML\n", 
                    FILE_APPEND
                );
                return $default;
            }
            
            $result = $default;
            
            // Extraer meta tags estándar
            // Título
            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $result['title'] = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            
            // Descripción
            if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<meta\s+name=["\']twitter:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            
            // Imagen
            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['image'] = $matches[1];
            } elseif (preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['image'] = $matches[1];
            }
            
            // Convertir imagen a URL absoluta si es necesario
            if (!empty($result['image']) && !filter_var($result['image'], FILTER_VALIDATE_URL)) {
                $parsed = parse_url($url_to_fetch);
                $base = $parsed['scheme'] . '://' . $parsed['host'];
                if (strpos($result['image'], '/') === 0) {
                    $result['image'] = $base . $result['image'];
                } else {
                    $result['image'] = $base . '/' . $result['image'];
                }
            }
            
            // Forzar HTTPS para Twitter
            if (!empty($result['image'])) {
                $result['image'] = str_replace('http://', 'https://', $result['image']);
            }
            
            // Nombre del sitio
            if (preg_match('/<meta\s+property=["\']og:site_name["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['site_name'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            
            // Limitar longitudes para Twitter
            if (strlen($result['title']) > 70) {
                $result['title'] = mb_substr($result['title'], 0, 67) . '...';
            }
            if (strlen($result['description']) > 200) {
                $result['description'] = mb_substr($result['description'], 0, 197) . '...';
            }
            
            file_put_contents('bot_detection.log', 
                date('Y-m-d H:i:s') . " | Meta tags extraídos: " . json_encode($result) . "\n", 
                FILE_APPEND
            );
            
            return $result;
        }
        
        // Intentar obtener meta tags con manejo de errores
        try {
            file_put_contents('bot_detection.log', 
                "$timestamp | Llamando a getMetaTags...\n", 
                FILE_APPEND
            );
            
            $meta_tags = getMetaTags($url['original_url']);
            
            file_put_contents('bot_detection.log', 
                "$timestamp | getMetaTags completado exitosamente\n", 
                FILE_APPEND
            );
            
            // Debug - guardar qué meta tags estamos enviando
            $debug_data = [
                'timestamp' => date('Y-m-d H:i:s'),
                'url_original' => $url['original_url'],
                'short_code' => $short_code,
                'meta_tags' => $meta_tags,
                'user_agent' => $user_agent,
                'is_bot' => $is_bot,
                'is_twitter' => stripos($user_agent, 'Twitter') !== false,
                'video_platform' => $video_platform,
                'video_id' => $video_id
            ];
            
            $json_result = file_put_contents('twitter_meta_debug.json', json_encode($debug_data, JSON_PRETTY_PRINT));
            
            file_put_contents('bot_detection.log', 
                "$timestamp | twitter_meta_debug.json escrito: " . ($json_result !== false ? "OK ($json_result bytes)" : "FALLO") . "\n", 
                FILE_APPEND
            );
            
        } catch (Exception $e) {
            file_put_contents('error_log.txt', 
                "$timestamp | Error en getMetaTags: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                FILE_APPEND
            );
            
            file_put_contents('bot_detection.log', 
                "$timestamp | EXCEPCIÓN en getMetaTags: " . $e->getMessage() . "\n", 
                FILE_APPEND
            );
            
            // Usar valores por defecto si hay error
            $meta_tags = [
                'title' => 'Ver contenido',
                'description' => 'Haz clic para ver el contenido completo',
                'image' => '',
                'type' => 'website',
                'site_name' => '',
                'author' => ''
            ];
        }
        
        // Construir la URL corta completa
        $short_url = 'https://' . $current_domain . '/' . $short_code;
        
        // Headers importantes
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        file_put_contents('bot_detection.log', 
            "$timestamp | Generando HTML con meta tags...\n", 
            FILE_APPEND
        );
        
        // IMPORTANTE: No debe haber NADA antes de <!DOCTYPE html>
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Twitter Card - TIPO PLAYER PARA VIDEOS -->
    <?php if ($video_platform === 'dailymotion' && $video_id): ?>
        <!-- Dailymotion Player Card -->
        <meta name="twitter:card" content="player" />
        <meta name="twitter:player" content="https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?>?autoplay=1" />
        <meta name="twitter:player:width" content="640" />
        <meta name="twitter:player:height" content="360" />
    <?php elseif ($video_platform === 'youtube' && $video_id): ?>
        <!-- YouTube Player Card -->
        <meta name="twitter:card" content="player" />
        <meta name="twitter:player" content="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?>?autoplay=1" />
        <meta name="twitter:player:width" content="640" />
        <meta name="twitter:player:height" content="360" />
    <?php else: ?>
        <!-- Summary Large Image para otros contenidos -->
        <meta name="twitter:card" content="summary_large_image" />
    <?php endif; ?>
    
    <meta name="twitter:title" content="<?php echo htmlspecialchars($meta_tags['title']); ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>" />
    <?php if (!empty($meta_tags['image'])): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($meta_tags['image']); ?>" />
    <?php endif; ?>
    <meta name="twitter:domain" content="<?php echo htmlspecialchars($current_domain); ?>" />
    <meta name="twitter:url" content="<?php echo htmlspecialchars($short_url); ?>" />
    
    <!-- Open Graph -->
    <meta property="og:type" content="<?php echo ($video_platform ? 'video.other' : 'website'); ?>" />
    <meta property="og:url" content="<?php echo htmlspecialchars($short_url); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($meta_tags['title']); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>" />
    <?php if (!empty($meta_tags['image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($meta_tags['image']); ?>" />
    <?php endif; ?>
    <?php if (!empty($meta_tags['site_name'])): ?>
    <meta property="og:site_name" content="<?php echo htmlspecialchars($meta_tags['site_name']); ?>" />
    <?php endif; ?>
    
    <?php if ($video_platform === 'dailymotion' && $video_id): ?>
    <meta property="og:video" content="https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?>" />
    <meta property="og:video:secure_url" content="https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?>" />
    <meta property="og:video:type" content="text/html" />
    <meta property="og:video:width" content="640" />
    <meta property="og:video:height" content="360" />
    <?php elseif ($video_platform === 'youtube' && $video_id): ?>
    <meta property="og:video" content="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?>" />
    <meta property="og:video:secure_url" content="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?>" />
    <meta property="og:video:type" content="text/html" />
    <meta property="og:video:width" content="640" />
    <meta property="og:video:height" content="360" />
    <?php endif; ?>
    
    <title><?php echo htmlspecialchars($meta_tags['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>" />
    
    <!-- REDIRECCIÓN OPTIMIZADA: Solo retrasar videos -->
    <?php if ($video_platform): ?>
        <!-- Videos: dar 2 segundos para cargar el player -->
        <meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($url['original_url']); ?>" />
    <?php else: ?>
        <!-- URLs normales (periódicos, blogs): redirección INMEDIATA -->
        <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($url['original_url']); ?>" />
    <?php endif; ?>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 40px 20px;
            background: #f5f5f5;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .preview-image {
            max-width: 100%;
            height: auto;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .debug-info {
            background: #f0f0f0;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .video-info {
            background: #e8f0ff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #4285f4;
        }
        .instant-redirect {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_GET['debug_meta'])): ?>
            <!-- Modo DEBUG - Mostrar toda la información -->
            <h1>🔍 Modo Debug - Meta Tags</h1>
            <div class="debug-info">
                <h3>Meta Tags Extraídos:</h3>
                <pre><?php echo htmlspecialchars(json_encode($meta_tags, JSON_PRETTY_PRINT)); ?></pre>
                
                <h3>Información de video:</h3>
                <p><strong>Plataforma:</strong> <?php echo htmlspecialchars($video_platform ?: 'No es video'); ?></p>
                <p><strong>Video ID:</strong> <?php echo htmlspecialchars($video_id ?: 'N/A'); ?></p>
                <?php if ($video_id && $video_platform === 'dailymotion'): ?>
                <p><strong>Embed URL:</strong> https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?></p>
                <?php elseif ($video_id && $video_platform === 'youtube'): ?>
                <p><strong>Embed URL:</strong> https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?></p>
                <?php endif; ?>
                
                <h3>Información de la petición:</h3>
                <p><strong>User Agent:</strong> <?php echo htmlspecialchars($user_agent); ?></p>
                <p><strong>Es Bot:</strong> <?php echo $is_bot ? 'SÍ' : 'NO'; ?></p>
                <p><strong>URL Original:</strong> <?php echo htmlspecialchars($url['original_url']); ?></p>
                <p><strong>URL Corta:</strong> <?php echo htmlspecialchars($short_url); ?></p>
                <p><strong>Tiempo de redirección:</strong> <?php echo $video_platform ? '2 segundos (video)' : '0 segundos (INMEDIATA)'; ?></p>
            </div>
            
            <?php if ($video_platform && $video_id): ?>
            <div class="video-info">
                <h3>✅ Video detectado - Twitter Player Card activo</h3>
                <p>Twitter debería incrustar el video directamente en el timeline.</p>
                <p>Redirección en 2 segundos para permitir carga del player.</p>
            </div>
            <?php else: ?>
            <div class="video-info" style="background: #d4edda; border-color: #28a745;">
                <h3 class="instant-redirect">⚡ Redirección INMEDIATA</h3>
                <p>Esta es una URL normal (no video). Los bots verán los meta tags pero la redirección es instantánea.</p>
            </div>
            <?php endif; ?>
            
            <h2>Vista Previa:</h2>
            <h1><?php echo htmlspecialchars($meta_tags['title']); ?></h1>
            <p><?php echo htmlspecialchars($meta_tags['description']); ?></p>
            <?php if (!empty($meta_tags['image'])): ?>
            <img src="<?php echo htmlspecialchars($meta_tags['image']); ?>" alt="Preview" class="preview-image">
            <?php else: ?>
            <p style="color: red;">⚠️ No se encontró imagen</p>
            <?php endif; ?>
        <?php else: ?>
            <!-- Contenido normal - Redirección según tipo -->
            <?php if (!$video_platform): ?>
                <!-- URLs normales: Mostrar mensaje rápido pero redirección instantánea -->
                <h1>Redirigiendo...</h1>
                <script>
                    // Redirección INMEDIATA para URLs normales
                    window.location.replace("<?php echo htmlspecialchars($url['original_url']); ?>");
                </script>
            <?php else: ?>
                <!-- Videos: Mostrar spinner por 2 segundos -->
                <div class="spinner"></div>
                <h1>Cargando video...</h1>
                <p>Serás redirigido en unos segundos.</p>
                <p><a href="<?php echo htmlspecialchars($url['original_url']); ?>" class="btn">Continuar ahora</a></p>
                <script>
                    // Redirección después de 2 segundos para videos
                    setTimeout(function() {
                        window.location.replace("<?php echo htmlspecialchars($url['original_url']); ?>");
                    }, 2000);
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html><?php
        
        file_put_contents('bot_detection.log', 
            "$timestamp | HTML generado y enviado | Tipo: " . ($video_platform ? "VIDEO ($video_platform)" : "URL NORMAL") . "\n", 
            FILE_APPEND
        );
        
        exit();
    }
    
    // PARA USUARIOS NORMALES (NO BOTS): Proceder con redirección normal
    file_put_contents('bot_detection.log', 
        "$timestamp | Usuario normal (no bot), redirigiendo...\n", 
        FILE_APPEND
    );
    
    // Incrementar contador de clicks
    $stmt = $pdo->prepare("UPDATE urls SET clicks = clicks + 1 WHERE id = ?");
    $stmt->execute([$url['id']]);
    
    // Registrar estadísticas detalladas
    try {
        // Obtener información de geolocalización
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $geo_info = ['country' => null, 'city' => null];
        
        // Intentar obtener geolocalización
        if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            $geo_url = "http://ip-api.com/json/{$ip}?fields=status,country,city";
            $geo_context = stream_context_create([
                'http' => ['timeout' => 2]
            ]);
            $geo_data = @file_get_contents($geo_url, false, $geo_context);
            
            if ($geo_data) {
                $geo_json = json_decode($geo_data, true);
                if ($geo_json && $geo_json['status'] === 'success') {
                    $geo_info['country'] = $geo_json['country'] ?? null;
                    $geo_info['city'] = $geo_json['city'] ?? null;
                }
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO click_stats (url_id, clicked_at, ip_address, user_agent, referer, country, city) 
            VALUES (?, NOW(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $url['id'],
            $ip,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? '',
            $geo_info['country'],
            $geo_info['city']
        ]);
    } catch (Exception $e) {
        // Si falla el registro de stats, continuar con la redirección
    }
    
    // Redirigir a la URL original
    header('Location: ' . $url['original_url']);
    exit();
    
} else {
    // URL no encontrada
    file_put_contents('bot_detection.log', 
        "$timestamp | URL NO ENCONTRADA: $short_code\n", 
        FILE_APPEND
    );
    
    header('HTTP/1.0 404 Not Found');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - URL no encontrada</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                background: #f5f5f5;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            h1 {
                color: #333;
                font-size: 48px;
                margin-bottom: 20px;
            }
            p {
                color: #666;
                font-size: 18px;
                margin-bottom: 30px;
            }
            a {
                display: inline-block;
                padding: 12px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background 0.3s;
            }
            a:hover {
                background: #5a67d8;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>404</h1>
            <p>La URL que buscas no existe o ha sido eliminada.</p>
            <a href="/">Volver al inicio</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
