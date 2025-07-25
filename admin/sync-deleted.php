<?php
session_start();
if (!isset($_SESSION['user_id'])) die('No autorizado');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['existing_codes'])) {
    $db = new PDO("mysql:host=localhost;dbname=url_shortener", 'root', 'trapisonda');
    $user_id = $_SESSION['user_id'];
    
    // Códigos que el usuario dice tener en la extensión
    $existing = array_map('trim', explode("\n", $_POST['existing_codes']));
    
    // Obtener todos los códigos del usuario
    $stmt = $db->prepare("SELECT short_code FROM urls WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $all_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Los que no están en la lista = añadir a blacklist
    $to_blacklist = array_diff($all_codes, $existing);
    
    foreach ($to_blacklist as $code) {
        $stmt = $db->prepare("INSERT INTO url_blacklist (user_id, short_code) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()");
        $stmt->execute([$user_id, $code]);
    }
    
    echo "✅ Añadidas a blacklist: " . count($to_blacklist) . " URLs";
}
?>

<h2>Sincronizar con Extensión</h2>
<p>Pega aquí los códigos cortos que VES en tu extensión (uno por línea):</p>
<form method="POST">
    <textarea name="existing_codes" rows="10" cols="30" placeholder="TEST123
rnkqLk
etc"></textarea><br>
    <button type="submit">Sincronizar</button>
</form>
