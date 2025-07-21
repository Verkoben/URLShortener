<?php
// export_bookmarks.php - Exportar a favoritos HTML
require_once 'config.php';
require_once 'functions.php';

$user_id = getCurrentUserId();
if (!$user_id) {
    die('❌ No autenticado - <a href="../admin/login.php">Iniciar sesión</a>');
}

// Obtener formato de export
$format = $_GET['format'] ?? 'html';
$download = isset($_GET['download']);

try {
    // Obtener URLs del usuario
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
    
    $userInfo = getCurrentUserInfo();
    $timestamp = date('Y-m-d_H-i-s');
    
    // Construir URLs completas
    foreach ($urls as &$url) {
        $domain = $url['custom_domain'] ?? '0ln.org';
        $url['short_url'] = "https://{$domain}/{$url['short_code']}";
        $url['display_title'] = $url['title'] ?: $url['short_code'];
    }
    
    // =============================================
    // FORMATO HTML BOOKMARKS (compatible navegadores)
    // =============================================
    if ($format === 'html') {
        if ($download) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="mis_urls_acortadas_' . $timestamp . '.html"');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        echo generateBookmarksHTML($urls, $userInfo['username']);
        exit;
    }
    
    // =============================================
    // FORMATO CSV
    // =============================================
    elseif ($format === 'csv') {
        if ($download) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="mis_urls_acortadas_' . $timestamp . '.csv"');
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
        }
        
        echo generateCSV($urls);
        exit;
    }
    
    // =============================================
    // FORMATO JSON ESTRUCTURADO
    // =============================================
    elseif ($format === 'json') {
        if ($download) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="mis_urls_acortadas_' . $timestamp . '.json"');
        } else {
            header('Content-Type: application/json; charset=UTF-8');
        }
        
        echo json_encode([
            'export_info' => [
                'user' => $userInfo['username'],
                'exported_at' => date('Y-m-d H:i:s'),
                'total_urls' => count($urls),
                'format' => 'structured_bookmarks'
            ],
            'bookmarks' => $urls
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // =============================================
    // PÁGINA DE SELECCIÓN DE FORMATO
    // =============================================
    else {
        showExportPage($urls, $userInfo);
    }
    
} catch (Exception $e) {
    die('❌ Error: ' . $e->getMessage());
}

// =============================================
// FUNCIONES
// =============================================

function generateBookmarksHTML($urls, $username) {
    $html = '<!DOCTYPE NETSCAPE-Bookmark-file-1>
<!-- This is an automatically generated file.
     It will be read and overwritten.
     DO NOT EDIT! -->
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>URLs Acortadas - ' . htmlspecialchars($username) . '</TITLE>
<H1>Favoritos</H1>

<DL><p>
    <DT><H3 ADD_DATE="' . time() . '" LAST_MODIFIED="' . time() . '">📎 URLs Acortadas (' . count($urls) . ')</H3>
    <DL><p>';
    
    foreach ($urls as $url) {
        $addDate = strtotime($url['created_at']);
        $title = htmlspecialchars($url['display_title']);
        $originalUrl = htmlspecialchars($url['original_url']);
        $shortUrl = htmlspecialchars($url['short_url']);
        $clicks = $url['clicks'];
        
        // Crear descripción con info adicional
        $description = "🔗 URL Corta: {$shortUrl} | 👆 Clicks: {$clicks} | 📅 Creado: " . date('d/m/Y', $addDate);
        
        $html .= '        <DT><A HREF="' . $originalUrl . '" ADD_DATE="' . $addDate . '" PRIVATE="0">' . $title . '</A>
        <DD>' . htmlspecialchars($description) . "\n";
    }
    
    $html .= '    </DL><p>
</DL><p>';
    
    return $html;
}

function generateCSV($urls) {
    $csv = "Título,URL Original,URL Corta,Clicks,Fecha Creación,Público\n";
    
    foreach ($urls as $url) {
        $csv .= '"' . str_replace('"', '""', $url['display_title']) . '",';
        $csv .= '"' . str_replace('"', '""', $url['original_url']) . '",';
        $csv .= '"' . str_replace('"', '""', $url['short_url']) . '",';
        $csv .= $url['clicks'] . ',';
        $csv .= '"' . date('Y-m-d H:i:s', strtotime($url['created_at'])) . '",';
        $csv .= ($url['is_public'] ? 'Sí' : 'No') . "\n";
    }
    
    return $csv;
}

function showExportPage($urls, $userInfo) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📥 Exportar URLs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; padding: 20px; color: #333;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .card { 
            background: white; border-radius: 15px; padding: 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 20px; 
        }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 2.5em; color: #2c3e50; margin-bottom: 10px; }
        .subtitle { color: #6c757d; font-size: 1.1em; }
        .export-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .option-card { 
            border: 2px solid #e9ecef; border-radius: 10px; padding: 20px; 
            text-align: center; transition: all 0.3s; cursor: pointer; 
        }
        .option-card:hover { border-color: #667eea; transform: translateY(-2px); }
        .option-icon { font-size: 3em; margin-bottom: 15px; }
        .option-title { font-size: 1.3em; color: #2c3e50; margin-bottom: 10px; }
        .option-desc { color: #6c757d; font-size: 0.9em; margin-bottom: 15px; }
        .btn { 
            background: #667eea; color: white; padding: 10px 20px; 
            text-decoration: none; border-radius: 8px; font-weight: 500; 
            transition: all 0.3s; display: inline-block; 
        }
        .btn:hover { background: #5a67d8; transform: translateY(-1px); }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .stats { 
            background: #f8f9fa; border-radius: 10px; padding: 20px; 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center; 
        }
        .stat-number { font-size: 2em; font-weight: bold; color: #667eea; }
        .stat-label { color: #6c757d; font-size: 0.9em; }
        .preview { 
            background: #f8f9fa; border-radius: 10px; padding: 20px; 
            max-height: 200px; overflow-y: auto; margin-top: 15px; 
        }
        .preview h4 { margin-bottom: 10px; color: #495057; }
        .preview-item { 
            padding: 8px; background: white; border-radius: 5px; 
            margin-bottom: 5px; font-size: 0.9em; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1 class="title">📥 Exportar URLs</h1>
                <p class="subtitle">Descarga tus URLs en diferentes formatos</p>
            </div>
            
            <div class="stats">
                <div>
                    <div class="stat-number"><?= count($urls) ?></div>
                    <div class="stat-label">URLs Totales</div>
                </div>
                <div>
                    <div class="stat-number"><?= array_sum(array_column($urls, 'clicks')) ?></div>
                    <div class="stat-label">Clicks Totales</div>
                </div>
                <div>
                    <div class="stat-number"><?= htmlspecialchars($userInfo['username']) ?></div>
                    <div class="stat-label">Usuario</div>
                </div>
            </div>
        </div>
        
        <div class="export-options">
            <!-- HTML Bookmarks -->
            <div class="option-card">
                <div class="option-icon">🌐</div>
                <h3 class="option-title">Favoritos HTML</h3>
                <p class="option-desc">Archivo .html que puedes importar en cualquier navegador (Chrome, Firefox, Safari, Edge)</p>
                <a href="?format=html&download=1" class="btn">📥 Descargar HTML</a>
                <a href="?format=html" class="btn btn-secondary">👀 Previsualizar</a>
                
                <div class="preview">
                    <h4>Vista previa:</h4>
                    <?php foreach (array_slice($urls, 0, 3) as $url): ?>
                    <div class="preview-item">
                        🔗 <strong><?= htmlspecialchars($url['display_title']) ?></strong><br>
                        <small><?= htmlspecialchars($url['original_url']) ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($urls) > 3): ?>
                    <div class="preview-item">... y <?= count($urls) - 3 ?> más</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- CSV -->
            <div class="option-card">
                <div class="option-icon">📊</div>
                <h3 class="option-title">Archivo CSV</h3>
                <p class="option-desc">Hoja de cálculo compatible con Excel, Google Sheets y otras aplicaciones</p>
                <a href="?format=csv&download=1" class="btn">📥 Descargar CSV</a>
                <a href="?format=csv" class="btn btn-secondary">👀 Previsualizar</a>
                
                <div class="preview">
                    <h4>Columnas incluidas:</h4>
                    <div class="preview-item">📋 Título, URL Original, URL Corta, Clicks, Fecha, Público</div>
                </div>
            </div>
            
            <!-- JSON -->
            <div class="option-card">
                <div class="option-icon">💾</div>
                <h3 class="option-title">Datos JSON</h3>
                <p class="option-desc">Formato estructurado para desarrolladores y aplicaciones</p>
                <a href="?format=json&download=1" class="btn">📥 Descargar JSON</a>
                <a href="?format=json" class="btn btn-secondary">👀 Previsualizar</a>
                
                <div class="preview">
                    <h4>Incluye:</h4>
                    <div class="preview-item">🔍 Metadatos completos, estadísticas y estructura para APIs</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 15px;">ℹ️ Instrucciones de Importación</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <h4>Chrome</h4>
                    <p style="font-size: 0.9em; color: #6c757d;">Configuración → Favoritos → Importar favoritos y configuración</p>
                </div>
                <div>
                    <h4>Firefox</h4>
                    <p style="font-size: 0.9em; color: #6c757d;">Marcadores → Mostrar todos → Importar y respaldar → Importar HTML</p>
                </div>
                <div>
                    <h4>Safari</h4>
                    <p style="font-size: 0.9em; color: #6c757d;">Archivo → Importar desde → Archivo de marcadores HTML</p>
                </div>
                <div>
                    <h4>Edge</h4>
                    <p style="font-size: 0.9em; color: #6c757d;">Configuración → Importar datos del navegador → Archivo HTML</p>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="btn btn-secondary">← Volver al Gestor</a>
        </div>
    </div>
</body>
</html>
<?php
}
?>
