<?php
// update_url_privacy.php - Actualizar privacidad de estadísticas
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$url_id = isset($_POST['url_id']) ? (int)$_POST['url_id'] : 0;
$public_stats = isset($_POST['public_stats']) ? 1 : 0;

if (!$url_id) {
    header('Location: index.php');
    exit;
}

try {
    // Verificar que el usuario es el propietario
    $stmt = $pdo->prepare("SELECT id FROM urls WHERE id = ? AND user_id = ?");
    $stmt->execute([$url_id, $user_id]);
    
    if ($stmt->fetch()) {
        // Verificar si existe la columna public_stats
        $columns = $pdo->query("SHOW COLUMNS FROM urls")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('public_stats', $columns)) {
            // Crear la columna si no existe
            $pdo->exec("ALTER TABLE urls ADD COLUMN public_stats BOOLEAN DEFAULT FALSE");
        }
        
        // Actualizar privacidad
        $stmt = $pdo->prepare("UPDATE urls SET public_stats = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$public_stats, $url_id, $user_id]);
        
        $_SESSION['flash_message'] = $public_stats 
            ? 'Las estadísticas ahora son públicas' 
            : 'Las estadísticas ahora son privadas';
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error al actualizar la configuración';
}

header("Location: analytics_url.php?url_id=" . $url_id);
exit;
?>
