<?php
// emergency_stop.php - PARAR TODO AHORA
require_once 'config.php';

// DESHABILITAR TRACKING INMEDIATAMENTE
file_put_contents('tracking_disabled.flag', 'EMERGENCY STOP - ' . date('Y-m-d H:i:s'));

// LIMPIAR DATOS DE LAS ÚLTIMAS 2 HORAS
try {
    $stmt = $pdo->prepare("DELETE FROM url_analytics WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $deleted = $stmt->execute();
    $count = $stmt->rowCount();
    
    echo "<h2 style='color: red;'>🛑 EMERGENCY STOP ACTIVADO</h2>";
    echo "<p style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "✅ Tracking DESHABILITADO<br>";
    echo "🧹 {$count} registros de las últimas 2 horas eliminados<br>";
    echo "⏰ " . date('Y-m-d H:i:s');
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='index.php'>← Volver al gestor</a>";
?>
