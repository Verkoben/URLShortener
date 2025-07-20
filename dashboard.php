<?php
// En tu dashboard principal, mostrar el estado
$stmt = $db->prepare("
    SELECT 
        last_extension_sync,
        extension_sync_count,
        (SELECT COUNT(*) FROM urls WHERE user_id = ? AND active = 1) as total_urls
    FROM users 
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$syncInfo = $stmt->fetch();
?>

<!-- Widget en el dashboard -->
<div class="sync-status-widget" style="
    background: <?php echo $syncInfo['last_extension_sync'] ? '#d4edda' : '#fff3cd'; ?>;
    border: 1px solid <?php echo $syncInfo['last_extension_sync'] ? '#c3e6cb' : '#ffeaa7'; ?>;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
">
    <h4>🔄 Estado de Sincronización con Extensión</h4>
    <?php if ($syncInfo['last_extension_sync']): ?>
        <p>✅ Última sincronización: <?php echo date('d/m/Y H:i', strtotime($syncInfo['last_extension_sync'])); ?></p>
        <p>📊 Total de URLs: <?php echo $syncInfo['total_urls']; ?></p>
        <p>🔄 Sincronizaciones: <?php echo $syncInfo['extension_sync_count']; ?> veces</p>
    <?php else: ?>
        <p>⚠️ No has sincronizado tu extensión aún</p>
        <p>Abre tu extensión y haz clic en "📥 Importar de <?php echo $_SERVER['HTTP_HOST']; ?>"</p>
    <?php endif; ?>
</div>
