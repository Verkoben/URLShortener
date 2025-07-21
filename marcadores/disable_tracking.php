<?php
// disable_tracking.php - Parar tracking temporalmente
require_once 'config.php';

echo "<h2>🛑 Disable Tracking</h2>";

if ($_POST['action'] === 'disable') {
    // Crear archivo flag para deshabilitar tracking
    file_put_contents('tracking_disabled.flag', date('Y-m-d H:i:s'));
    echo "<div style='background:#d4edda;padding:10px;border-radius:5px;'>✅ Tracking DESHABILITADO</div>";
}

if ($_POST['action'] === 'enable') {
    // Remover archivo flag
    if (file_exists('tracking_disabled.flag')) {
        unlink('tracking_disabled.flag');
    }
    echo "<div style='background:#d4edda;padding:10px;border-radius:5px;'>✅ Tracking HABILITADO</div>";
}

if ($_POST['action'] === 'clean_last_hour') {
    // Limpiar datos de la última hora (clicks falsos)
    try {
        $stmt = $pdo->prepare("DELETE FROM url_analytics WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $deleted = $stmt->execute();
        $count = $stmt->rowCount();
        
        echo "<div style='background:#fff3cd;padding:10px;border-radius:5px;'>🧹 {$count} registros de la última hora eliminados</div>";
    } catch (Exception $e) {
        echo "<div style='background:#f8d7da;padding:10px;border-radius:5px;'>❌ Error: {$e->getMessage()}</div>";
    }
}

$is_disabled = file_exists('tracking_disabled.flag');
?>

<style>
body { font-family: Arial; margin: 20px; }
.btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin: 5px; cursor: pointer; }
.btn-danger { background: #dc3545; }
.btn-warning { background: #ffc107; color: black; }
</style>

<h3>Estado actual: <?= $is_disabled ? '🛑 DESHABILITADO' : '✅ HABILITADO' ?></h3>

<form method="POST">
    <?php if ($is_disabled): ?>
        <button type="submit" name="action" value="enable" class="btn">✅ Habilitar Tracking</button>
    <?php else: ?>
        <button type="submit" name="action" value="disable" class="btn btn-danger">🛑 Deshabilitar Tracking</button>
    <?php endif; ?>
    
    <button type="submit" name="action" value="clean_last_hour" class="btn btn-warning" 
            onclick="return confirm('¿Eliminar registros de la última hora?')">
        🧹 Limpiar Última Hora
    </button>
</form>

<br><br>
<a href="debug_tracking.php">🔍 Ver Debug</a> | 
<a href="index.php">← Volver</a>
