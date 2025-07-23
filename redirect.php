<?php
require_once 'conf.php';

// Obtener el código corto de la URL
$request_uri = $_SERVER['REQUEST_URI'];
$short_code = trim($request_uri, '/');

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

// NUEVA VERIFICACIÓN: Obtener el dominio desde el que se está accediendo
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
    
    // NUEVO: DETECTAR BOTS DE REDES SOCIALES
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_bot = false;
    
    // Lista de bots de redes sociales
    $social_bots = [
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
        'Slackbot',
        'Discord',
        'Applebot',
        'Pinterestbot',
        'Skype'
    ];
    
    foreach ($social_bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            $is_bot = true;
            break;
        }
    }
    
    // SI ES UN BOT DE REDES SOCIALES, MOSTRAR META TAGS
    if ($is_bot) {
        // Función para obtener meta tags
        function getMetaTags($url_to_fetch) {
            $default = [
                'title' => 'Ver contenido',
                'description' => 'Haz clic para ver el contenido completo',
                'image' => ''
            ];
            
            // Configurar contexto con timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Mozilla/5.0 (compatible; URLShortener/1.0)',
                    'follow_location' => true
                ]
            ]);
            
            // Obtener contenido (solo primeros 50KB para no cargar todo)
            $html = @file_get_contents($url_to_fetch, false, $context, 0, 50000);
            
            if (!$html) {
                return $default;
            }
            
            $result = $default;
            
            // Obtener título
            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['title'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $result['title'] = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES, 'UTF-8');
            }
            
            // Obtener descripción
            if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['description'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            } elseif (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['description'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            }
            
            // Obtener imagen
            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $result['image'] = $matches[1];
                // Si la imagen es relativa, convertirla a absoluta
                if (!filter_var($result['image'], FILTER_VALIDATE_URL)) {
                    $parsed = parse_url($url_to_fetch);
                    $base = $parsed['scheme'] . '://' . $parsed['host'];
                    if (strpos($result['image'], '/') === 0) {
                        $result['image'] = $base . $result['image'];
                    } else {
                        $result['image'] = $base . '/' . $result['image'];
                    }
                }
            }
            
            return $result;
        }
        
        // Obtener meta tags del sitio original
        $meta_tags = getMetaTags($url['original_url']);
        
        // Construir la URL corta completa
        $short_url = 'https://' . $current_domain . '/' . $short_code;
        
        // Mostrar página con meta tags para el bot
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($meta_tags['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>">
    <?php if (!empty($meta_tags['image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($meta_tags['image']); ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($short_url); ?>">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="<?php echo !empty($meta_tags['image']) ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($meta_tags['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_tags['description']); ?>">
    <?php if (!empty($meta_tags['image'])): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($meta_tags['image']); ?>">
    <?php endif; ?>
    
    <!-- Redirección automática para usuarios normales (por si acaso) -->
    <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($url['original_url']); ?>">
    
    <title><?php echo htmlspecialchars($meta_tags['title']); ?></title>
</head>
<body>
    <p>Redirigiendo...</p>
    <script>
        // Redirección por JavaScript como backup
        window.location.href = "<?php echo htmlspecialchars($url['original_url']); ?>";
    </script>
</body>
</html>
        <?php
        exit();
    }
    
    // PARA USUARIOS NORMALES (NO BOTS): Proceder con redirección normal
    
    // Incrementar contador de clicks
    $stmt = $pdo->prepare("UPDATE urls SET clicks = clicks + 1 WHERE id = ?");
    $stmt->execute([$url['id']]);
    
    // Registrar estadísticas detalladas
    try {
        $stmt = $pdo->prepare("
            INSERT INTO click_stats (url_id, clicked_at, ip_address, user_agent, referer) 
            VALUES (?, NOW(), ?, ?, ?)
        ");
        $stmt->execute([
            $url['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    } catch (Exception $e) {
        // Si falla el registro de stats, continuar con la redirección
    }
    
    // Redirigir a la URL original
    header('Location: ' . $url['original_url']);
    exit();
    
} else {
    // URL no encontrada
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit();
}
?>
