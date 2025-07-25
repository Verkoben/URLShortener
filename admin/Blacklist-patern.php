<?php
require_once 'session_check.php';
if (!isset($_SESSION['admin_logged_in'])) die('No autorizado');

if (isset($_POST['add_pattern'])) {
    $db = new PDO("mysql:host=localhost;dbname=url_shortener", 'root', 'trapisonda');
    $pattern = $_POST['pattern'];
    $user_id = $_SESSION['user_id'];
    
    // Añadir todas las URLs que coincidan con el patrón
    $stmt = $db->prepare("
        INSERT INTO url_blacklist (user_id, short_code)
        SELECT user_id, short_code 
        FROM urls 
        WHERE user_id = ? 
        AND original_url LIKE ?
        ON DUPLICATE KEY UPDATE created_at = NOW()
    ");
    $stmt->execute([$user_id, '%' . $pattern . '%']);
    
    echo "✅ Añadidas " . $stmt->rowCount() . " URLs a blacklist";
}
?>

<h2>Blacklist por Patrón</h2>
<form method="POST">
    <label>URLs que contengan:</label>
    <input type="text" name="pattern" placeholder="ej: facebook.com" required>
    <button type="submit" name="add_pattern">Añadir a Blacklist</button>
</form>

<h3>Ejemplos útiles:</h3>
<ul>
    <li><code>facebook.com</code> - Todas las URLs de Facebook</li>
    <li><code>utm_</code> - URLs con parámetros de tracking</li>
    <li><code>localhost</code> - URLs de desarrollo</li>
</ul>
