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
    
    // Lista ampliada de bots - AGREGAR WHATSAPP
// Lista ampliada de bots - MEJORADA
$bot_patterns = [
		     'bot',
		     'crawl', 
		     'spider',
		     'Twitter',
		     'facebook',
		     'WhatsApp',           // WhatsApp genérico
		     'WhatsAppBot',        // WhatsApp bot
		     'facebookexternalhit', // Facebook (dueño de WhatsApp)
		     'Telegram',
		     'Slack',
		     'Discord',
		     'LinkedIn',
		     'Pinterest',
		     'Skype',
		     'redditbot',
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
    if (isset($_GET['debug_meta']) || isset($_GET['twitter']) || isset($_GET['test']) || isset($_GET['whatsapp'])) {
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
        $spotify_type = null; // Variable para el tipo de contenido de Spotify
        
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'youtube';
        } elseif (preg_match('/(?:dailymotion\.com\/video\/|dai\.ly\/)([a-zA-Z0-9]+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'dailymotion';
        } elseif (preg_match('/(?:tiktok\.com\/@[\w\.-]+\/video\/|tiktok\.com\/v\/|vm\.tiktok\.com\/)(\d+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'tiktok';
        } elseif (preg_match('/(?:instagram\.com\/p\/|instagram\.com\/reel\/)([A-Za-z0-9_-]+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'instagram';
        } elseif (preg_match('/(?:vimeo\.com\/)(\d+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'vimeo';
        } elseif (preg_match('/(?:pinterest\.com\/pin\/|pin\.it\/)([A-Za-z0-9]+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'pinterest';
        } elseif (preg_match('/(?:discord\.gg\/|discord\.com\/invite\/)([a-zA-Z0-9]+)/', $url['original_url'], $matches)) {
            $video_id = $matches[1];
            $video_platform = 'discord';
        } elseif (preg_match('/(?:open\.spotify\.com\/)(track|album|playlist|episode|artist)\/([a-zA-Z0-9]+)/', $url['original_url'], $matches)) {
            $spotify_type = $matches[1];
            $video_id = $matches[2];
            $video_platform = 'spotify';
        }
        
        // DEBUG: Ver qué plataforma detectó
        file_put_contents('platform_debug.log', 
            date('Y-m-d H:i:s') . " | URL: {$url['original_url']} | Platform: $video_platform | ID: $video_id | Spotify Type: $spotify_type\n", 
            FILE_APPEND
        );
        
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
                    
                    // Extraer autor/canal
                    if (preg_match('/<link\s+itemprop=["\']name["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['author'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    // Imagen de YouTube
                    $result['image'] = "https://i.ytimg.com/vi/{$video_id}/maxresdefault.jpg";
                    
                    // Verificar si existe maxresdefault
                    $headers = @get_headers($result['image']);
                    if (!$headers || strpos($headers[0], '404') !== false) {
                        $result['image'] = "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg";
                    }
                    
                    $result['site_name'] = 'YouTube';
                    
                    // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                    $result['title'] = "▶️ " . $result['title'];
                    if (!empty($result['author'])) {
                        $result['description'] = "🎥 Video de " . $result['author'] . " • YouTube • Toca para ver";
                    } else {
                        $result['description'] = "🎥 Ver video en YouTube • Toca para reproducir";
                    }
                    
                    return $result;
                }
                
                // Si falla, usar datos por defecto para YouTube
                return [
                    'title' => '▶️ Video de YouTube',
                    'description' => '🎥 Ver video en YouTube • Toca para reproducir',
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
                
                // MÉTODO 1: Usar imagen directa de CDN (más confiable para WhatsApp)
                $cdn_images = [
                    "https://s2.dmcdn.net/v/{$video_id}/x720",
                    "https://s1.dmcdn.net/v/{$video_id}/x720", 
                    "https://s2.dmcdn.net/v/{$video_id}/x480",
                    "https://s1.dmcdn.net/v/{$video_id}/x480",
                    "https://www.dailymotion.com/thumbnail/video/{$video_id}"
                ];
                
                $working_image = '';
                foreach ($cdn_images as $img_url) {
                    $headers = @get_headers($img_url);
                    if ($headers && strpos($headers[0], '200') !== false) {
                        $working_image = $img_url;
                        file_put_contents('dailymotion_debug.txt', 
                            "CDN imagen encontrada: $img_url\n", 
                            FILE_APPEND
                        );
                        break;
                    }
                }
                
                // MÉTODO 2: Si CDN falla, intentar con la API
                if (empty($working_image)) {
                    $api_fields = 'title,description,thumbnail_720_url,thumbnail_480_url,thumbnail_360_url,thumbnail_240_url,thumbnail_180_url,owner.screenname';
                    $api_url = "https://api.dailymotion.com/video/{$video_id}?fields={$api_fields}";
                    
                    $api_response = @file_get_contents($api_url, false, $context);
                    
                    if ($api_response) {
                        $api_data = json_decode($api_response, true);
                        
                        file_put_contents('dailymotion_debug.txt', 
                            "API Response: " . json_encode($api_data) . "\n", 
                            FILE_APPEND
                        );
                        
                        if ($api_data && !isset($api_data['error'])) {
                            // Obtener título y descripción
                            $title = $api_data['title'] ?? 'Video de Dailymotion';
                            $description = $api_data['description'] ?? 'Ver este video en Dailymotion';
                            $author = $api_data['owner.screenname'] ?? '';
                            
                            // Buscar la mejor imagen disponible
                            $image_fields = [
                                'thumbnail_720_url',
                                'thumbnail_480_url', 
                                'thumbnail_360_url',
                                'thumbnail_240_url',
                                'thumbnail_180_url'
                            ];
                            
                            foreach ($image_fields as $field) {
                                if (!empty($api_data[$field])) {
                                    $working_image = $api_data[$field];
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // MÉTODO 3: Si todo falla, scraping del HTML
                if (empty($working_image)) {
                    $html = @file_get_contents($url_to_fetch, false, $context, 0, 300000);
                    
                    if ($html) {
                        // Buscar en el HTML
                        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $working_image = $matches[1];
                        }
                        
                        // Buscar JSON-LD
                        if (empty($working_image) && preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $json_matches)) {
                            $json_data = json_decode($json_matches[1], true);
                            if ($json_data && isset($json_data['thumbnailUrl'])) {
                                $working_image = $json_data['thumbnailUrl'];
                            }
                        }
                        
                        // Extraer título si no lo tenemos
                        if (!isset($title)) {
                            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                                $title = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            }
                        }
                    }
                }
                
                // MÉTODO 4: Imagen por defecto garantizada
                if (empty($working_image)) {
                    // Usar la imagen estática de Dailymotion que siempre funciona
                    $working_image = "https://static1.dmcdn.net/images/dailymotion-logo-ogtag.png";
                }
                
                // Asegurar HTTPS y limpiar URL
                $working_image = str_replace('http://', 'https://', $working_image);
                $working_image = str_replace('&amp;', '&', $working_image);
                
                // Construir resultado
                $result = [
                    'title' => isset($title) ? $title : 'Video de Dailymotion',
                    'description' => isset($description) ? $description : 'Ver video en Dailymotion',
                    'image' => $working_image,
                    'type' => 'website',
                    'site_name' => 'Dailymotion',
                    'author' => isset($author) ? $author : ''
                ];
                
                // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                $result['title'] = "▶️ " . $result['title'];
                if (!empty($result['author'])) {
                    $result['description'] = "🎬 Video de " . $result['author'] . " • Dailymotion • Toca para ver";
                } else {
                    $result['description'] = "🎬 Ver video en Dailymotion • Toca para reproducir";
                }
                
                // DEBUG: Guardar resultado final
                file_put_contents('dailymotion_meta_result.json', 
                    json_encode($result, JSON_PRETTY_PRINT)
                );
                
                file_put_contents('dailymotion_debug.txt', 
                    "Resultado final: " . json_encode($result) . "\n\n", 
                    FILE_APPEND
                );
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Dailymotion meta tags generados OK | Imagen: " . $result['image'] . "\n", 
                    FILE_APPEND
                );
                
                return $result;
            }
            
            // TIKTOK - DETECCIÓN Y EXTRACCIÓN MEJORADA PARA WHATSAPP
            if (preg_match('/(?:tiktok\.com\/@([\w\.-]+)\/video\/|tiktok\.com\/v\/|vm\.tiktok\.com\/)(\d+)/', $url_to_fetch, $matches)) {
                $username = isset($matches[1]) ? $matches[1] : '';
                $video_id = isset($matches[2]) ? $matches[2] : $matches[1];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | TikTok detectado: Video ID: $video_id, Usuario: $username\n", 
                    FILE_APPEND
                );
                
                // Variables para guardar resultados
                $working_thumbnail = '';
                $title = "@{$username} en TikTok";
                $description = 'Mira este video en TikTok';
                $author = "@{$username}";
                
                // MÉTODO 1: Usar oEmbed API de TikTok (MÁS CONFIABLE)
                $oembed_url = "https://www.tiktok.com/oembed?url=" . urlencode($url_to_fetch);
                
                // Contexto especial para TikTok
                $tiktok_context = stream_context_create([
                    'http' => [
                        'timeout' => 15,
                        'user_agent' => 'Mozilla/5.0 (compatible; WhatsApp/2.0; +http://www.whatsapp.com/contact)',
                        'follow_location' => true,
                        'header' => [
                            "Accept: application/json",
                            "Accept-Language: es-ES,es;q=0.9,en;q=0.8",
                            "Cache-Control: no-cache",
                            "Referer: https://www.tiktok.com/"
                        ]
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                
                $oembed_response = @file_get_contents($oembed_url, false, $tiktok_context);
                
                if ($oembed_response) {
                    $oembed_data = json_decode($oembed_response, true);
                    
                    file_put_contents('tiktok_debug.txt', 
                        date('Y-m-d H:i:s') . " | oEmbed response: " . json_encode($oembed_data) . "\n", 
                        FILE_APPEND
                    );
                    
                    if ($oembed_data && isset($oembed_data['thumbnail_url'])) {
                        $working_thumbnail = $oembed_data['thumbnail_url'];
                        
                        // Actualizar título y autor si están disponibles
                        if (isset($oembed_data['title'])) {
                            $title = $oembed_data['title'];
                        }
                        if (isset($oembed_data['author_name'])) {
                            $author = $oembed_data['author_name'];
                            if (strpos($author, '@') !== 0) {
                                $author = '@' . $author;
                            }
                        }
                        
                        file_put_contents('tiktok_debug.txt', 
                            date('Y-m-d H:i:s') . " | oEmbed thumbnail encontrado: $working_thumbnail\n", 
                            FILE_APPEND
                        );
                    }
                }
                
                // MÉTODO 2: Si oEmbed falla, usar Web Scraping
                if (empty($working_thumbnail)) {
                    file_put_contents('tiktok_debug.txt', 
                        date('Y-m-d H:i:s') . " | oEmbed falló, intentando scraping...\n", 
                        FILE_APPEND
                    );
                    
                    // Contexto para scraping
                    $scraping_context = stream_context_create([
                        'http' => [
                            'timeout' => 20,
                            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'follow_location' => true,
                            'header' => [
                                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
                                "Accept-Language: es-ES,es;q=0.9,en;q=0.8",
                                "Accept-Encoding: gzip, deflate, br",
                                "DNT: 1",
                                "Connection: keep-alive",
                                "Upgrade-Insecure-Requests: 1"
                            ]
                        ],
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false
                        ]
                    ]);
                    
                    $html = @file_get_contents($url_to_fetch, false, $scraping_context);
                    
                    if ($html) {
                        // Si está comprimido con gzip
                        if (substr($html, 0, 2) === "\x1f\x8b") {
                            $html = gzdecode($html);
                        }
                        
                        // Buscar Open Graph image
                        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $working_thumbnail = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            file_put_contents('tiktok_debug.txt', 
                                date('Y-m-d H:i:s') . " | OG image encontrada: $working_thumbnail\n", 
                                FILE_APPEND
                            );
                        }
                        
                        // Buscar título
                        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $title = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                        
                        // Buscar descripción
                        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                            $og_desc = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            // Extraer el username de la descripción si es posible
                            if (preg_match('/@([\w\.-]+)/', $og_desc, $user_matches)) {
                                $author = '@' . $user_matches[1];
                            }
                        }
                    }
                }
                
                // MÉTODO 3: URLs de CDN directas (último recurso)
                if (empty($working_thumbnail) && $video_id) {
                    $cdn_patterns = [
                        "https://p16-sign-sg.tiktokcdn.com/obj/tos-alisg-p-0037/{$video_id}~c5_300x400.jpeg",
                        "https://p16-sign-va.tiktokcdn.com/obj/tos-maliva-p-0068/{$video_id}~c5_300x400.jpeg",
                        "https://p16-sign.tiktokcdn-us.com/obj/tos-useast5-p-0068/{$video_id}~c5_300x400.jpeg",
                        "https://p77-sign-va.tiktokcdn.com/obj/{$video_id}~tplv-photomode-image.jpeg",
                        "https://p16-sign-va.tiktokcdn.com/tos-maliva-p-0068/{$video_id}~tplv-dmt-logom:tos-maliva-p-0000/image.jpeg"
                    ];
                    
                    foreach ($cdn_patterns as $cdn_url) {
                        $headers = @get_headers($cdn_url);
                        if ($headers && strpos($headers[0], '200') !== false) {
                            $working_thumbnail = $cdn_url;
                            file_put_contents('tiktok_debug.txt', 
                                date('Y-m-d H:i:s') . " | CDN thumbnail encontrado: $cdn_url\n", 
                                FILE_APPEND
                            );
                            break;
                        }
                    }
                }
                
                // MÉTODO 4: Si todo falla, usar imagen por defecto de TikTok
                if (empty($working_thumbnail)) {
                    // Logo de TikTok como fallback
                    $working_thumbnail = "https://p16-tiktokcdn-com.akamaized.net/obj/tiktok-obj/1663270619667490.png";
                    file_put_contents('tiktok_debug.txt', 
                        date('Y-m-d H:i:s') . " | Usando imagen por defecto\n", 
                        FILE_APPEND
                    );
                }
                
                // Asegurar HTTPS y limpiar URL
                // Asegurar HTTPS y limpiar URL
                $working_thumbnail = str_replace('http://', 'https://', $working_thumbnail);
		$working_thumbnail = str_replace('&amp;', '&', $working_thumbnail);
		$working_thumbnail = html_entity_decode($working_thumbnail); // <-- AÑADE ESTA LÍNEA
                
                // Si es una URL relativa, hacerla absoluta
                if (strpos($working_thumbnail, '//') === 0) {
                    $working_thumbnail = 'https:' . $working_thumbnail;
                }
                
                // Preparar resultado
                $result = [
                    'title' => $title,
                    'description' => $description,
                    'image' => $working_thumbnail,
                    'type' => 'video',
                    'site_name' => 'TikTok',
                    'author' => $author
                ];
                
                // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                $result['title'] = "▶️ " . $result['title'];
                $result['description'] = "🎵 Video de " . $author . " • TikTok • Toca para ver";
                
                // Debug final
                file_put_contents('tiktok_meta_result.json', 
                    json_encode($result, JSON_PRETTY_PRINT)
                );
                
                file_put_contents('tiktok_debug.txt', 
                    date('Y-m-d H:i:s') . " | TikTok resultado final: " . json_encode($result) . "\n\n", 
                    FILE_APPEND
                );
                
                return $result;
            }
            
            // INSTAGRAM - DETECCIÓN Y EXTRACCIÓN
            if (preg_match('/(?:instagram\.com\/p\/|instagram\.com\/reel\/)([A-Za-z0-9_-]+)/', $url_to_fetch, $matches)) {
                $post_id = $matches[1];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Instagram detectado: Post ID: $post_id\n", 
                    FILE_APPEND
                );
                
                // Intentar con oEmbed de Instagram
                $oembed_url = "https://api.instagram.com/oembed?url=" . urlencode($url_to_fetch) . "&omitscript=true";
                $oembed_response = @file_get_contents($oembed_url, false, $context);
                
                if ($oembed_response) {
                    $oembed_data = json_decode($oembed_response, true);
                    if ($oembed_data) {
                        $result = [
                            'title' => $oembed_data['title'] ?? 'Post de Instagram',
                            'description' => $oembed_data['author_name'] ? 'Por @' . $oembed_data['author_name'] . ' en Instagram' : 'Ver en Instagram',
                            'image' => $oembed_data['thumbnail_url'] ?? '',
                            'type' => 'video',
                            'site_name' => 'Instagram',
                            'author' => $oembed_data['author_name'] ?? ''
                        ];
                        
                        // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                        if (strpos($url_to_fetch, '/reel/') !== false) {
                            $result['title'] = "▶️ " . $result['title'];
                            $result['description'] = "🎬 Reel de @" . $result['author'] . " • Instagram • Toca para ver";
                        } else {
                            $result['description'] = "📷 Post de @" . $result['author'] . " • Instagram • Toca para ver";
                        }
                        
                        file_put_contents('instagram_debug.txt', 
                            date('Y-m-d H:i:s') . " | Instagram OEmbed exitoso: " . json_encode($result) . "\n", 
                            FILE_APPEND
                        );
                        
                        return $result;
                    }
                }
                
                // Si OEmbed falla, intentar scraping básico
                $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
                
                if ($html) {
                    $result = $default;
                    
                    // Buscar meta tags de Open Graph
                    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['image'] = $matches[1];
                    }
                    
                    $result['site_name'] = 'Instagram';
                    
                    // MEJORAR DESCRIPCIÓN
                    if (strpos($url_to_fetch, '/reel/') !== false) {
                        $result['title'] = "▶️ " . $result['title'];
                        $result['description'] = "🎬 Ver Reel en Instagram • Toca para abrir";
                    } else {
                        $result['description'] = "📷 Ver en Instagram • Toca para abrir";
                    }
                    
                    return $result;
                }
                
                // Si falla, datos por defecto
                return [
                    'title' => strpos($url_to_fetch, '/reel/') !== false ? '▶️ Reel de Instagram' : 'Post de Instagram',
                    'description' => strpos($url_to_fetch, '/reel/') !== false ? '🎬 Ver Reel en Instagram • Toca para abrir' : '📷 Ver en Instagram • Toca para abrir',
                    'image' => '',
                    'type' => 'video',
                    'site_name' => 'Instagram',
                    'author' => ''
                ];
            }
            
            // VIMEO - DETECCIÓN Y EXTRACCIÓN
            if (preg_match('/(?:vimeo\.com\/)(\d+)/', $url_to_fetch, $matches)) {
                $video_id = $matches[1];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Vimeo detectado: Video ID: $video_id\n", 
                    FILE_APPEND
                );
                
                // Usar oEmbed API de Vimeo
                $oembed_url = "https://vimeo.com/api/oembed.json?url=" . urlencode($url_to_fetch);
                $oembed_response = @file_get_contents($oembed_url, false, $context);
                
                if ($oembed_response) {
                    $oembed_data = json_decode($oembed_response, true);
                    if ($oembed_data) {
                        $result = [
                            'title' => $oembed_data['title'] ?? 'Video de Vimeo',
                            'description' => $oembed_data['description'] ?? 'Por ' . ($oembed_data['author_name'] ?? 'Vimeo'),
                            'image' => $oembed_data['thumbnail_url'] ?? '',
                            'type' => 'video',
                            'site_name' => 'Vimeo',
                            'author' => $oembed_data['author_name'] ?? ''
                        ];
                        
                        // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                        $result['title'] = "▶️ " . $result['title'];
                        if (!empty($result['author'])) {
                            $result['description'] = "🎞️ Video de " . $result['author'] . " • Vimeo • Toca para ver";
                        } else {
                            $result['description'] = "🎞️ Ver video en Vimeo • Toca para reproducir";
                        }
                        
                        file_put_contents('vimeo_debug.txt', 
                            date('Y-m-d H:i:s') . " | Vimeo OEmbed exitoso: " . json_encode($result) . "\n", 
                            FILE_APPEND
                        );
                        
                        return $result;
                    }
                }
                
                // Si falla, usar datos por defecto
                return [
                    'title' => '▶️ Video de Vimeo',
                    'description' => '🎞️ Ver video en Vimeo • Toca para reproducir',
                    'image' => "https://i.vimeocdn.com/video/{$video_id}_1280x720.jpg",
                    'type' => 'video',
                    'site_name' => 'Vimeo',
                    'author' => ''
                ];
            }
            
            // PINTEREST - DETECCIÓN Y EXTRACCIÓN CORREGIDA
            if (preg_match('/(?:pinterest\.com\/pin\/|pin\.it\/)([A-Za-z0-9]+)/', $url_to_fetch, $matches)) {
                $pin_id = $matches[1];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Pinterest detectado: Pin ID: $pin_id\n", 
                    FILE_APPEND
                );
                
                // Obtener el HTML de Pinterest
                $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
                
                if ($html) {
                    $result = $default;
                    
                    // Pinterest usa meta tags estructurados
                    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['image'] = $matches[1];
                    }
                    
                    // Obtener autor si está disponible
                    if (preg_match('/<meta\s+property=["\']article:author["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['author'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    $result['site_name'] = 'Pinterest';
                    $result['type'] = 'article'; // Pinterest usa article type
                    
                    // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                    $result['description'] = "📌 " . mb_substr($result['description'], 0, 100) . "... • Pinterest • Toca para ver";
                    
                    file_put_contents('pinterest_debug.txt', 
                        date('Y-m-d H:i:s') . " | Pinterest meta tags: " . json_encode($result) . "\n", 
                        FILE_APPEND
                    );
                    
                    return $result;
                }
                
                // Si falla, usar datos por defecto
                return [
                    'title' => 'Pin de Pinterest',
                    'description' => '📌 Ver este pin en Pinterest • Toca para abrir',
                    'image' => '',
                    'type' => 'article',
                    'site_name' => 'Pinterest',
                    'author' => ''
                ];
            }
            
            // DISCORD - DETECCIÓN Y EXTRACCIÓN
            if (preg_match('/(?:discord\.gg\/|discord\.com\/invite\/)([a-zA-Z0-9]+)/', $url_to_fetch, $matches)) {
                $invite_code = $matches[1];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Discord detectado: Invite Code: $invite_code\n", 
                    FILE_APPEND
                );
                
                // Discord tiene una API para obtener información de invitaciones
                $api_url = "https://discord.com/api/v9/invites/{$invite_code}?with_counts=true&with_expiration=true";
                
                // Configurar contexto para la API de Discord
                $discord_context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'Mozilla/5.0 (compatible; URLShortener/1.0)',
                        'header' => [
                            "Accept: application/json",
                            "Cache-Control: no-cache"
                        ]
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                
                $api_response = @file_get_contents($api_url, false, $discord_context);
                
                if ($api_response) {
                    $api_data = json_decode($api_response, true);
                    
                    if ($api_data && !isset($api_data['code']) && isset($api_data['guild'])) {
                        $guild = $api_data['guild'];
                        
                        // Construir URL de la imagen del servidor
                        $image_url = '';
                        if (isset($guild['icon']) && $guild['icon']) {
                            $image_url = "https://cdn.discordapp.com/icons/{$guild['id']}/{$guild['icon']}.png?size=512";
                        } else {
                            // Imagen por defecto de Discord
                            $image_url = "https://discord.com/assets/2c21aeda16de354ba5334551a883b481.png";
                        }
                        
                        $member_count = isset($api_data['approximate_member_count']) ? 
                            number_format($api_data['approximate_member_count']) : 'varios';
                        
                        $result = [
                            'title' => $guild['name'] ?? 'Servidor de Discord',
                            'description' => isset($guild['description']) && $guild['description'] ? 
                                $guild['description'] : 
                                "Únete a {$guild['name']} en Discord - {$member_count} miembros",
                            'image' => $image_url,
                            'type' => 'website',
                            'site_name' => 'Discord',
                            'author' => ''
                        ];
                        
                        // MEJORAR DESCRIPCIÓN PARA WHATSAPP
                        $result['title'] = "💬 " . $result['title'];
                        $result['description'] = $result['description'] . " • Toca para unirte";
                        
                        file_put_contents('discord_debug.txt', 
                            date('Y-m-d H:i:s') . " | Discord API exitosa: " . json_encode($result) . "\n", 
                            FILE_APPEND
                        );
                        
                        return $result;
                    }
                }
                
                // Si la API falla, intentar scraping del HTML
                $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
                
                if ($html) {
                    $result = $default;
                    
                    // Discord usa meta tags Open Graph
                    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['image'] = $matches[1];
                    }
                    
                    $result['site_name'] = 'Discord';
                    
                    // MEJORAR DESCRIPCIÓN
                    $result['title'] = "💬 " . $result['title'];
                    $result['description'] = $result['description'] . " • Toca para unirte";
                    
                    return $result;
                }
                
                // Si todo falla, usar datos por defecto
                return [
                    'title' => '💬 Invitación a Discord',
                    'description' => 'Únete a este servidor en Discord • Toca para unirte',
                    'image' => 'https://discord.com/assets/2c21aeda16de354ba5334551a883b481.png',
                    'type' => 'website',
                    'site_name' => 'Discord',
                    'author' => ''
                ];
            }
            
            // SPOTIFY - DETECCIÓN Y EXTRACCIÓN MEJORADA
            if (preg_match('/(?:open\.spotify\.com\/)(track|album|playlist|episode|artist)\/([a-zA-Z0-9]+)/', $url_to_fetch, $matches)) {
                $spotify_type = $matches[1];
                $spotify_id = $matches[2];
                
                file_put_contents('bot_detection.log', 
                    date('Y-m-d H:i:s') . " | Spotify detectado: Tipo: $spotify_type, ID: $spotify_id\n", 
                    FILE_APPEND
                );
                
                // Spotify tiene oEmbed API
                $oembed_url = "https://open.spotify.com/oembed?url=" . urlencode($url_to_fetch);
                $oembed_response = @file_get_contents($oembed_url, false, $context);
                
                if ($oembed_response) {
                    $oembed_data = json_decode($oembed_response, true);
                    
                    if ($oembed_data) {
                        // Spotify devuelve HTML con el iframe, necesitamos extraer info
                        $title = $oembed_data['title'] ?? 'Contenido de Spotify';
                        
                        // Mejorar el formato del título y descripción
                        $description = '';
                        $author = '';
                        
                        if ($spotify_type === 'track') {
                            // Para canciones, el título viene como "Song by Artist"
                            if (strpos($title, ' by ') !== false) {
                                list($song, $artist) = explode(' by ', $title, 2);
                                $title = $song; // Solo el nombre de la canción
                                $author = $artist;
                                $description = "$artist • Canción • Spotify";
                            } else {
                                $description = "Canción • Spotify";
                            }
                        } elseif ($spotify_type === 'album') {
                            // Para álbumes, agregar info del tipo
                            if (strpos($title, ' by ') !== false) {
                                list($album, $artist) = explode(' by ', $title, 2);
                                $title = $album;
                                $author = $artist;
                                $description = "$artist • Álbum • Spotify";
                            } else {
                                $description = "Álbum • Spotify";
                            }
                        } elseif ($spotify_type === 'playlist') {
                            $description = "Playlist • Spotify";
                        } elseif ($spotify_type === 'episode') {
                            $description = "Podcast • Spotify";
                        } elseif ($spotify_type === 'artist') {
                            $author = $title;
                            $description = "Artista • Spotify";
                        }
                        
                        $result = [
                            'title' => $title,
                            'description' => $description,
                            'image' => $oembed_data['thumbnail_url'] ?? '',
                            'type' => 'music',
                            'site_name' => 'open.spotify.com',
                            'author' => $author
                        ];
                        
                        // MEJORAR DESCRIPCIÓN SEGÚN TIPO PARA WHATSAPP
                        if ($spotify_type === 'track') {
                            $result['description'] = "🎵 " . $result['description'] . " • Toca para escuchar";
                            $result['title'] = "♫ " . $result['title'];
                        } elseif ($spotify_type === 'album') {
                            $result['description'] = "💿 " . $result['description'] . " • Toca para escuchar";
                            $result['title'] = "💿 " . $result['title'];
                        } elseif ($spotify_type === 'playlist') {
                            $result['description'] = "📝 " . $result['description'] . " • Toca para abrir";
                            $result['title'] = "🎶 " . $result['title'];
                        } elseif ($spotify_type === 'episode') {
                            $result['description'] = "🎙️ " . $result['description'] . " • Toca para escuchar";
                            $result['title'] = "🎙️ " . $result['title'];
                        } elseif ($spotify_type === 'artist') {
                            $result['description'] = "👤 " . $result['description'] . " • Toca para ver";
                            $result['title'] = "🎤 " . $result['title'];
                        }
                        
                        file_put_contents('spotify_debug.txt', 
                            date('Y-m-d H:i:s') . " | Spotify oEmbed exitoso: " . json_encode($result) . "\n", 
                            FILE_APPEND
                        );
                        
                        return $result;
                    }
                }
                
                // Si oEmbed falla, intentar con el HTML
                $html = @file_get_contents($url_to_fetch, false, $context, 0, 200000);
                
                if ($html) {
                    $result = $default;
                    
                    // Spotify usa meta tags Open Graph
                    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['image'] = $matches[1];
                    }
                    
                    // Spotify específico
                    if (preg_match('/<meta\s+property=["\']music:musician["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                        $result['author'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    
                    $result['site_name'] = 'open.spotify.com';
                    $result['type'] = 'music';
                    
                    // Mejorar descripción basada en tipo
                    if ($spotify_type === 'track' && !empty($result['author'])) {
                        $result['description'] = "🎵 " . $result['author'] . ' • Canción • Spotify • Toca para escuchar';
                        $result['title'] = "♫ " . $result['title'];
                    } elseif ($spotify_type === 'album' && !empty($result['author'])) {
                        $result['description'] = "💿 " . $result['author'] . ' • Álbum • Spotify • Toca para escuchar';
                        $result['title'] = "💿 " . $result['title'];
                    } elseif ($spotify_type === 'playlist') {
                        $result['description'] = '📝 Playlist • Spotify • Toca para abrir';
                        $result['title'] = "🎶 " . $result['title'];
                    } elseif ($spotify_type === 'episode') {
                        $result['description'] = '🎙️ Podcast • Spotify • Toca para escuchar';
                        $result['title'] = "🎙️ " . $result['title'];
                    } elseif ($spotify_type === 'artist') {
                        $result['description'] = '👤 Artista • Spotify • Toca para ver';
                        $result['title'] = "🎤 " . $result['title'];
                    }
                    
                    return $result;
                }
                
                // Si todo falla, usar datos por defecto mejorados
                $type_names = [
                    'track' => 'Canción',
                    'album' => 'Álbum',
                    'playlist' => 'Playlist',
                    'episode' => 'Podcast',
                    'artist' => 'Artista'
                ];
                
                $type_emojis = [
                    'track' => ['title' => '♫', 'desc' => '🎵'],
                    'album' => ['title' => '💿', 'desc' => '💿'],
                    'playlist' => ['title' => '🎶', 'desc' => '📝'],
                    'episode' => ['title' => '🎙️', 'desc' => '🎙️'],
                    'artist' => ['title' => '🎤', 'desc' => '👤']
                ];
                
                $emoji = $type_emojis[$spotify_type] ?? ['title' => '🎵', 'desc' => '🎵'];
                
                return [
                    'title' => $emoji['title'] . ' ' . ($type_names[$spotify_type] ?? 'Contenido') . ' de Spotify',
                    'description' => $emoji['desc'] . ' ' . ($type_names[$spotify_type] ?? 'Contenido') . ' • Spotify • Toca para escuchar',
                    'image' => 'https://developer.spotify.com/images/guidelines/design/icon3@2x.png',
                    'type' => 'music',
'site_name' => 'open.spotify.com',
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
           
           // MEJORAR DESCRIPCIÓN PARA WHATSAPP - URLs GENÉRICAS
           // Limitar descripción y agregar call to action
           if (strlen($result['description']) > 120) {
               $result['description'] = mb_substr($result['description'], 0, 117) . "...";
           }
           
           // Agregar indicador según el tipo de sitio
           $domain_lower = strtolower($url_to_fetch);
           
           // Detectar tipo de contenido por dominio/título
           if (stripos($result['title'], 'news') !== false || 
               stripos($result['site_name'], 'news') !== false ||
               stripos($result['site_name'], 'times') !== false ||
               stripos($domain_lower, 'elpais') !== false ||
               stripos($domain_lower, 'elmundo') !== false ||
               stripos($domain_lower, 'lavanguardia') !== false ||
               stripos($domain_lower, 'abc.es') !== false ||
               stripos($domain_lower, 'bbc.com') !== false ||
               stripos($domain_lower, 'cnn.com') !== false) {
               $result['title'] = "📰 " . $result['title'];
               $result['description'] .= " • " . ($result['site_name'] ?: "Noticias") . " • Leer más";
           } elseif (stripos($domain_lower, 'github.com') !== false) {
               $result['title'] = "💻 " . $result['title'];
               $result['description'] .= " • GitHub • Ver código";
           } elseif (stripos($domain_lower, 'stackoverflow.com') !== false) {
               $result['title'] = "💡 " . $result['title'];
               $result['description'] .= " • Stack Overflow • Ver respuesta";
           } elseif (stripos($domain_lower, 'wikipedia.org') !== false) {
               $result['title'] = "📚 " . $result['title'];
               $result['description'] .= " • Wikipedia • Leer artículo";
           } elseif (stripos($domain_lower, 'reddit.com') !== false) {
               $result['title'] = "🗨️ " . $result['title'];
               $result['description'] .= " • Reddit • Ver discusión";
           } elseif (stripos($domain_lower, 'medium.com') !== false || 
                    stripos($domain_lower, 'blog') !== false ||
                    stripos($result['title'], 'blog') !== false) {
               $result['title'] = "📝 " . $result['title'];
               $result['description'] .= " • " . ($result['site_name'] ?: "Blog") . " • Leer artículo";
           } elseif (stripos($domain_lower, 'amazon') !== false ||
                    stripos($domain_lower, 'ebay') !== false ||
                    stripos($domain_lower, 'aliexpress') !== false ||
                    stripos($domain_lower, 'mercadolibre') !== false) {
               $result['title'] = "🛒 " . $result['title'];
               $result['description'] .= " • " . ($result['site_name'] ?: "Tienda") . " • Ver producto";
           } elseif (!empty($result['site_name'])) {
               $result['description'] .= " • " . $result['site_name'] . " • Toca para ver";
           } else {
               $result['description'] .= " • Toca para leer más";
           }
           
           // Limitar longitudes para Twitter
           if (strlen($result['title']) > 70) {
               // Preservar emoji si existe
               if (preg_match('/^([\x{1F300}-\x{1F9FF}])/u', $result['title'], $emoji_match)) {
                   $emoji = $emoji_match[1];
                   $title_without_emoji = trim(str_replace($emoji, '', $result['title']));
                   $result['title'] = $emoji . ' ' . mb_substr($title_without_emoji, 0, 64) . '...';
               } else {
                   $result['title'] = mb_substr($result['title'], 0, 67) . '...';
               }
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
               'is_whatsapp' => stripos($user_agent, 'WhatsApp') !== false,
               'video_platform' => $video_platform,
               'video_id' => $video_id,
               'spotify_type' => $spotify_type
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
   
   <!-- WHATSAPP TAGS PRIMERO -->
   <meta property="og:title" content="<?php echo htmlspecialchars($meta_tags['title']); ?>" />
   <meta property="og:description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>" />
   <?php if (!empty($meta_tags['image'])): ?>
   <meta property="og:image" content="<?php echo htmlspecialchars($meta_tags['image']); ?>" />
   <meta property="og:image:width" content="1200" />
   <meta property="og:image:height" content="630" />
   <?php endif; ?>
   <meta property="og:url" content="<?php echo htmlspecialchars($short_url); ?>" />
   <meta property="og:type" content="<?php echo ($video_platform && !in_array($video_platform, ['pinterest', 'discord']) ? ($video_platform === 'spotify' ? 'music.song' : 'video.other') : 'website'); ?>" />
   <?php if (!empty($meta_tags['site_name'])): ?>
   <meta property="og:site_name" content="<?php echo htmlspecialchars($meta_tags['site_name']); ?>" />
   <?php endif; ?>
   
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
   <?php elseif ($video_platform === 'tiktok' && $video_id): ?>
       <!-- TikTok Player Card - URL del player directo -->
       <meta name="twitter:card" content="player" />
       <meta name="twitter:player" content="https://www.tiktok.com/player/v1/<?php echo htmlspecialchars($video_id); ?>?music_info=1&description=1&autoplay=1" />
       <meta name="twitter:player:width" content="325" />
       <meta name="twitter:player:height" content="575" />
   <?php elseif ($video_platform === 'instagram' && $video_id): ?>
       <!-- Instagram - Intentar con embed -->
       <meta name="twitter:card" content="player" />
       <meta name="twitter:player" content="https://www.instagram.com/p/<?php echo htmlspecialchars($video_id); ?>/embed/" />
       <meta name="twitter:player:width" content="400" />
       <meta name="twitter:player:height" content="500" />
   <?php elseif ($video_platform === 'vimeo' && $video_id): ?>
       <!-- Vimeo Player Card -->
       <meta name="twitter:card" content="player" />
       <meta name="twitter:player" content="https://player.vimeo.com/video/<?php echo htmlspecialchars($video_id); ?>?autoplay=1" />
       <meta name="twitter:player:width" content="640" />
       <meta name="twitter:player:height" content="360" />
   <?php elseif ($video_platform === 'spotify' && $video_id): ?>
       <!-- Spotify - Optimizado para summary_large_image con descripciones mejoradas -->
       <meta name="twitter:card" content="summary_large_image" />
       <meta name="twitter:site" content="@spotify" />
       <!-- Labels adicionales para enriquecer la preview -->
       <meta name="twitter:label1" content="Escuchar en" />
       <meta name="twitter:data1" content="Spotify" />
       <?php if (!empty($meta_tags['author'])): ?>
       <meta name="twitter:label2" content="Artista" />
       <meta name="twitter:data2" content="<?php echo htmlspecialchars($meta_tags['author']); ?>" />
       <?php endif; ?>
   <?php elseif ($video_platform === 'pinterest' && $video_id): ?>
       <!-- Pinterest - Usar summary_large_image -->
       <meta name="twitter:card" content="summary_large_image" />
   <?php elseif ($video_platform === 'discord' && $video_id): ?>
       <!-- Discord - Usar summary_large_image -->
       <meta name="twitter:card" content="summary_large_image" />
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
   
   <!-- WhatsApp específico -->
   <meta property="og:image:type" content="image/jpeg" />
   <meta property="og:locale" content="es_ES" />
   
   <!-- Video tags adicionales para WhatsApp si es video -->
   <?php if ($video_platform && $video_id && !in_array($video_platform, ['pinterest', 'discord', 'spotify'])): ?>
       <?php if ($video_platform === 'dailymotion'): ?>
       <meta property="og:video" content="https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?>" />
       <meta property="og:video:secure_url" content="https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?>" />
       <meta property="og:video:type" content="text/html" />
       <meta property="og:video:width" content="640" />
       <meta property="og:video:height" content="360" />
       <?php elseif ($video_platform === 'youtube'): ?>
       <meta property="og:video" content="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?>" />
       <meta property="og:video:secure_url" content="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?>" />
       <meta property="og:video:type" content="text/html" />
       <meta property="og:video:width" content="640" />
       <meta property="og:video:height" content="360" />
       <?php elseif ($video_platform === 'tiktok'): ?>
       <meta property="og:video" content="https://www.tiktok.com/player/v1/<?php echo htmlspecialchars($video_id); ?>?music_info=1&description=1&autoplay=1" />
       <meta property="og:video:secure_url" content="https://www.tiktok.com/player/v1/<?php echo htmlspecialchars($video_id); ?>?music_info=1&description=1&autoplay=1" />
       <meta property="og:video:type" content="text/html" />
       <meta property="og:video:width" content="325" />
       <meta property="og:video:height" content="575" />
       <?php elseif ($video_platform === 'instagram'): ?>
       <meta property="og:video" content="https://www.instagram.com/p/<?php echo htmlspecialchars($video_id); ?>/embed/" />
       <meta property="og:video:secure_url" content="https://www.instagram.com/p/<?php echo htmlspecialchars($video_id); ?>/embed/" />
       <meta property="og:video:type" content="text/html" />
       <meta property="og:video:width" content="400" />
       <meta property="og:video:height" content="500" />
       <?php elseif ($video_platform === 'vimeo'): ?>
       <meta property="og:video" content="https://player.vimeo.com/video/<?php echo htmlspecialchars($video_id); ?>" />
       <meta property="og:video:secure_url" content="https://player.vimeo.com/video/<?php echo htmlspecialchars($video_id); ?>" />
       <meta property="og:video:type" content="text/html" />
       <meta property="og:video:width" content="640" />
       <meta property="og:video:height" content="360" />
       <?php endif; ?>
   <?php elseif ($video_platform === 'spotify' && $video_id): ?>
       <!-- Spotify optimizado con metadata adicional -->
       <meta property="og:audio" content="<?php echo htmlspecialchars($url['original_url']); ?>" />
       <meta property="og:audio:type" content="audio/vnd.facebook.bridge" />
       <?php if (!empty($meta_tags['author'])): ?>
       <meta property="music:musician" content="<?php echo htmlspecialchars($meta_tags['author']); ?>" />
       <?php endif; ?>
       <?php if ($spotify_type === 'album'): ?>
       <meta property="music:album" content="<?php echo htmlspecialchars($meta_tags['title']); ?>" />
       <?php endif; ?>
   <?php endif; ?>
   
   <title><?php echo htmlspecialchars($meta_tags['title']); ?></title>
   <meta name="description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>" />
   
   <!-- REDIRECCIÓN OPTIMIZADA: Solo retrasar videos, Discord es instantáneo -->
   <?php if ($video_platform && !in_array($video_platform, ['discord'])): ?>
       <!-- Videos y Spotify: dar 2 segundos para cargar el player -->
       <meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($url['original_url']); ?>" />
   <?php else: ?>
       <!-- URLs normales, Discord, etc: redirección INMEDIATA -->
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
       .platform-notice {
           background: #fff3cd;
           color: #856404;
           padding: 10px;
           border-radius: 5px;
           margin: 10px 0;
           font-size: 14px;
       }
       .whatsapp-notice {
           background: #d4edda;
           color: #155724;
           padding: 15px;
           border-radius: 8px;
           border: 1px solid #c3e6cb;
           margin: 20px 0;
       }
       /* Estilos para preview de video */
       .video-preview {
           margin: 20px 0;
           border-radius: 8px;
           overflow: hidden;
           box-shadow: 0 4px 15px rgba(0,0,0,0.2);
           display: inline-block;
           background: #000;
       }
       .video-preview iframe {
           display: block;
           max-width: 100%;
           height: auto;
       }
       .pinterest-preview {
           background: #fff;
           padding: 0;
       }
       .discord-preview {
           max-width: 400px;
           margin: 0 auto;
           background: #2f3136;
           padding: 20px;
           border-radius: 8px;
           color: white;
           text-align: left;
       }
       .discord-header {
           display: flex;
           align-items: center;
           gap: 15px;
           margin-bottom: 15px;
       }
       .discord-icon {
           width: 64px;
           height: 64px;
           border-radius: 50%;
           object-fit: cover;
       }
       .discord-title {
           margin: 0;
           color: white;
           font-size: 20px;
       }
       .discord-subtitle {
           margin: 5px 0 0 0;
           color: #b9bbbe;
           font-size: 14px;
       }
       .discord-description {
           color: #dcddde;
           margin: 0;
           line-height: 1.5;
       }
       .spotify-preview {
           background: #191414;
           padding: 20px;
           border-radius: 8px;
           max-width: 400px;
           margin: 0 auto;
       }
       /* Estilos específicos para Spotify */
       .spotify-card {
           background: #121212;
           border-radius: 8px;
           overflow: hidden;
           max-width: 400px;
           margin: 20px auto;
           box-shadow: 0 4px 15px rgba(0,0,0,0.3);
       }
       .spotify-card-image {
           width: 100%;
           height: 400px;
           object-fit: cover;
       }
       .spotify-card-content {
           padding: 20px;
           background: #181818;
       }
       .spotify-card-title {
           color: white;
           font-size: 24px;
           font-weight: bold;
           margin: 0 0 8px 0;
       }
       .spotify-card-description {
           color: #b3b3b3;
           font-size: 14px;
           margin: 0;
       }
       .spotify-play-button {
           display: inline-flex;
           align-items: center;
           gap: 10px;
           background: #1db954;
           color: white;
           padding: 12px 24px;
           border-radius: 500px;
           text-decoration: none;
           font-weight: bold;
           margin-top: 16px;
           transition: transform 0.2s, background 0.2s;
       }
       .spotify-play-button:hover {
           background: #1ed760;
           transform: scale(1.05);
       }
       @media (max-width: 600px) {
           .video-preview iframe {
               width: 100%;
               height: 200px;
           }
           .spotify-card-image {
               height: 300px;
           }
       }
   </style>
</head>
<body>
   <div class="container">
       <?php if (isset($_GET['debug_meta']) || isset($_GET['whatsapp'])): ?>
           <!-- Modo DEBUG - Mostrar toda la información -->
           <h1>🔍 Modo Debug - Meta Tags</h1>
           
           <?php if (stripos($user_agent, 'WhatsApp') !== false || isset($_GET['whatsapp'])): ?>
           <div class="whatsapp-notice">
               <h3>✅ WhatsApp Detectado</h3>
               <p>Los meta tags están optimizados para WhatsApp. La imagen debe ser de al menos 300x200px.</p>
           </div>
           <?php endif; ?>
           
           <div class="debug-info">
               <h3>Meta Tags Extraídos:</h3>
               <pre><?php echo htmlspecialchars(json_encode($meta_tags, JSON_PRETTY_PRINT)); ?></pre>
               
               <h3>Información de video:</h3>
               <p><strong>Plataforma:</strong> <?php echo htmlspecialchars($video_platform ?: 'No es video'); ?></p>
               <p><strong>Video ID:</strong> <?php echo htmlspecialchars($video_id ?: 'N/A'); ?></p>
               <?php if ($video_id && $video_platform === 'spotify'): ?>
               <p><strong>Spotify ID:</strong> <?php echo htmlspecialchars($video_id); ?></p>
               <p><strong>Tipo:</strong> <?php echo htmlspecialchars($spotify_type ?? 'track'); ?></p>
               <div class="platform-notice">
                   🎵 Spotify: WhatsApp y Twitter mostrarán imagen grande con descripciones enriquecidas.
               </div>
               <?php elseif ($video_id && $video_platform === 'tiktok'): ?>
               <p><strong>TikTok ID:</strong> <?php echo htmlspecialchars($video_id); ?></p>
               <div class="platform-notice">
                   🎥 TikTok: Usando múltiples métodos para obtener miniatura. Player card activo.
               </div>
               <?php endif; ?>
               
               <h3>Información de la petición:</h3>
               <p><strong>User Agent:</strong> <?php echo htmlspecialchars($user_agent); ?></p>
               <p><strong>Es Bot:</strong> <?php echo $is_bot ? 'SÍ' : 'NO'; ?></p>
               <p><strong>Es WhatsApp:</strong> <?php echo (stripos($user_agent, 'WhatsApp') !== false) ? 'SÍ' : 'NO'; ?></p>
               <p><strong>URL Original:</strong> <?php echo htmlspecialchars($url['original_url']); ?></p>
               <p><strong>URL Corta:</strong> <?php echo htmlspecialchars($short_url); ?></p>
               <p><strong>Tiempo de redirección:</strong> <?php echo ($video_platform && !in_array($video_platform, ['discord'])) ? '2 segundos (video/música)' : '0 segundos (INMEDIATA)'; ?></p>
           </div>
           
           <?php if ($video_platform && $video_id): ?>
           <div class="video-info">
               <h3>✅ <?php echo in_array($video_platform, ['discord']) ? 'Contenido' : 'Video/Media'; ?> detectado - Cards activas</h3>
               <?php if ($video_platform === 'spotify'): ?>
               <p><strong>Spotify:</strong> WhatsApp mostrará imagen grande con título y descripción mejorados.</p>
               <?php elseif ($video_platform === 'tiktok'): ?>
               <p><strong>TikTok:</strong> WhatsApp mostrará la miniatura del video con emojis.</p>
               <?php elseif ($video_platform === 'youtube'): ?>
               <p><strong>YouTube:</strong> Twitter mostrará player card, WhatsApp mostrará miniatura con emoji ▶️.</p>
               <?php elseif ($video_platform === 'dailymotion'): ?>
               <p><strong>Dailymotion:</strong> Twitter mostrará player card, WhatsApp mostrará miniatura con emoji ▶️.</p>
               <?php elseif ($video_platform === 'vimeo'): ?>
               <p><strong>Vimeo:</strong> Twitter mostrará player card, WhatsApp mostrará miniatura con emoji ▶️.</p>
               <?php endif; ?>
               <?php if (!in_array($video_platform, ['discord'])): ?>
               <p>Redirección en 2 segundos para permitir carga del contenido.</p>
               <?php endif; ?>
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
           <?php if (!$video_platform || in_array($video_platform, ['discord'])): ?>
               <!-- URLs normales y Discord: Mostrar mensaje rápido pero redirección instantánea -->
               <h1>Redirigiendo...</h1>
               <script>
                   // Redirección INMEDIATA para URLs normales y Discord
                   window.location.replace("<?php echo htmlspecialchars($url['original_url']); ?>");
               </script>
           <?php else: ?>
               <!-- Videos: Mostrar spinner y preview por 2 segundos -->
               <div class="spinner"></div>
               <h1>Cargando contenido...</h1>
               
               <!-- PREVIEW DEL VIDEO -->
               <?php if ($video_platform && $video_id): ?>
               <div class="video-preview <?php echo in_array($video_platform, ['pinterest', 'spotify']) ? $video_platform . '-preview' : ''; ?>">
                   <?php if ($video_platform === 'youtube'): ?>
                       <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_id); ?>?autoplay=0&mute=1" 
                               width="560" height="315" frameborder="0" allowfullscreen></iframe>
                   <?php elseif ($video_platform === 'dailymotion'): ?>
                       <iframe src="https://www.dailymotion.com/embed/video/<?php echo htmlspecialchars($video_id); ?>?autoplay=0&mute=1" 
                               width="560" height="315" frameborder="0" allowfullscreen></iframe>
                   <?php elseif ($video_platform === 'vimeo'): ?>
                       <iframe src="https://player.vimeo.com/video/<?php echo htmlspecialchars($video_id); ?>?autoplay=0&muted=1" 
                               width="560" height="315" frameborder="0" allowfullscreen></iframe>
                   <?php elseif ($video_platform === 'tiktok'): ?>
                       <iframe src="https://www.tiktok.com/player/v1/<?php echo htmlspecialchars($video_id); ?>?music_info=1&description=1&autoplay=0" 
                               width="325" height="575" frameborder="0" allowfullscreen></iframe>
                   <?php elseif ($video_platform === 'instagram'): ?>
                       <iframe src="https://www.instagram.com/p/<?php echo htmlspecialchars($video_id); ?>/embed/" 
                               width="400" height="500" frameborder="0" allowfullscreen></iframe>
                   <?php elseif ($video_platform === 'pinterest'): ?>
                       <!-- Pinterest Widget -->
                       <div style="max-width: 600px; margin: 0 auto;">
                           <a data-pin-do="embedPin" 
                              data-pin-width="large"
                              data-pin-terse="true"
                              href="<?php echo htmlspecialchars($url['original_url']); ?>"></a>
                       </div>
                       <script async defer src="//assets.pinterest.com/js/pinit.js"></script>
                   <?php elseif ($video_platform === 'discord'): ?>
                       <!-- Discord Preview -->
                       <div class="discord-preview">
                           <div class="discord-header">
                               <?php if (!empty($meta_tags['image'])): ?>
                                   <img src="<?php echo htmlspecialchars($meta_tags['image']); ?>" 
                                        class="discord-icon" 
                                        alt="Server icon">
                               <?php endif; ?>
                               <div>
                                   <h3 class="discord-title"><?php echo htmlspecialchars($meta_tags['title']); ?></h3>
                                   <p class="discord-subtitle">Servidor de Discord</p>
                               </div>
                           </div>
                           <p class="discord-description"><?php echo htmlspecialchars($meta_tags['description']); ?></p>
                       </div>
                   <?php elseif ($video_platform === 'spotify'): ?>
                       <!-- Spotify Preview mejorada estilo oficial -->
                       <div class="spotify-card">
                           <?php if (!empty($meta_tags['image'])): ?>
                               <img src="<?php echo htmlspecialchars($meta_tags['image']); ?>" 
                                    alt="<?php echo htmlspecialchars($meta_tags['title']); ?>" 
                                    class="spotify-card-image">
                           <?php endif; ?>
                           <div class="spotify-card-content">
                               <h2 class="spotify-card-title"><?php echo htmlspecialchars($meta_tags['title']); ?></h2>
                               <p class="spotify-card-description"><?php echo htmlspecialchars($meta_tags['description']); ?></p>
                               <a href="<?php echo htmlspecialchars($url['original_url']); ?>" class="spotify-play-button">
                                   ▶ Escuchar en Spotify
                               </a>
                           </div>
                       </div>
                   <?php endif; ?>
               </div>
               <?php endif; ?>
               
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
           "$timestamp | HTML generado y enviado | Tipo: " . ($video_platform ? "PLATAFORMA ($video_platform)" : "URL NORMAL") . " | WhatsApp: " . (stripos($user_agent, 'WhatsApp') !== false ? 'SÍ' : 'NO') . "\n", 
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
