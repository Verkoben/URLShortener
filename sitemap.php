<?php
header('Content-Type: application/xml; charset=utf-8');
require_once 'conf.php';

// Conectar a BD
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Error');
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
    
    <!-- Página principal -->
    <url>
        <loc><?php echo BASE_URL; ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <!-- URLs públicas más populares (opcional) -->
    <?php
    // Solo incluir URLs públicas y activas con muchos clicks
    $stmt = $pdo->prepare("
        SELECT short_code, DATE(created_at) as created_date 
        FROM urls 
        WHERE active = 1 
        AND clicks > 100 
        AND user_id IS NULL
        ORDER BY clicks DESC 
        LIMIT 100
    ");
    $stmt->execute();
    
    while ($url = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<url>\n";
        echo "    <loc>" . BASE_URL . $url['short_code'] . "</loc>\n";
        echo "    <lastmod>" . $url['created_date'] . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.5</priority>\n";
        echo "</url>\n";
    }
    ?>
</urlset>
