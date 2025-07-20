<?php
// Al crear una URL, mostrar si necesita sincronizar
if ($createdUrl && $showImportHint): ?>
    <div class="result-box">
        <strong>🎉 Tu URL corta está lista:</strong>
        <div class="result-url" id="shortUrl"><?php echo htmlspecialchars($createdUrl); ?></div>
        <button class="copy-btn" onclick="copyToClipboard()">📋 Copiar URL</button>
    </div>
    
    <?php
    // Verificar última sincronización
    $stmt = $db->prepare("
        SELECT 
            last_extension_sync,
            TIMESTAMPDIFF(MINUTE, last_extension_sync, NOW()) as minutes_ago
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $sync = $stmt->fetch();
    
    if (!$sync['last_extension_sync'] || $sync['minutes_ago'] > 60):
    ?>
    <div class="extension-steps pulse">
        <h3>🚀 Actualiza tu extensión:</h3>
        <ol class="steps">
            <li>
                <span class="step-number">1</span>
                Abre la extensión "Gestor de URLs Cortas"
            </li>
            <li>
                <span class="step-number">2</span>
                Haz clic en "📥 Importar de <?php echo $_SERVER['HTTP_HOST']; ?>"
            </li>
            <li>
                <span class="step-number">3</span>
                ¡Listo! Tu nueva URL aparecerá
            </li>
        </ol>
        <p style="margin-top: 10px; font-size: 14px; opacity: 0.8;">
            <?php if ($sync['last_extension_sync']): ?>
                Última sincronización hace <?php echo $sync['minutes_ago']; ?> minutos
            <?php else: ?>
                Primera vez usando la extensión
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <div class="auto-sync-notice" style="
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
        text-align: center;
    ">
        <p>✅ Tu extensión está actualizada</p>
        <p style="font-size: 14px; margin-top: 5px;">
            Sincronizada hace <?php echo $sync['minutes_ago']; ?> minutos
        </p>
    </div>
    <?php endif; ?>
<?php endif; ?>
