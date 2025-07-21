<!-- Actualizar la tabla para incluir data-url-id en cada fila -->
<table class="data-table">
    <thead>
        <tr>
            <th><input type="checkbox" onclick="toggleAll(this)"></th>
            <th>ID</th>
            <th>Código Corto</th>
            <th>Tipo</th>
            <th>URL Original</th>
            <th>Clicks</th>
            <th>Creado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($urls as $url): ?>
        <tr data-url-id="<?php echo $url['id']; ?>">
            <td>
                <input type="checkbox" name="url_ids[]" value="<?php echo $url['id']; ?>">
            </td>
            <td><?php echo $url['id']; ?></td>
            <td>
                <code><?php echo htmlspecialchars($url['short_code']); ?></code>
            </td>
            <td>
                <span class="badge <?php echo $url['is_custom'] ? 'badge-custom' : 'badge-auto'; ?>">
                    <?php echo $url['is_custom'] ? 'Personalizado' : 'Automático'; ?>
                </span>
            </td>
            <td class="url-cell">
                <a href="<?php echo htmlspecialchars($url['original_url']); ?>" target="_blank">
                    <?php echo htmlspecialchars(substr($url['original_url'], 0, 50)) . '...'; ?>
                </a>
            </td>
            <td><?php echo $url['clicks']; ?></td>
            <td><?php echo date('d/m/Y', strtotime($url['created_at'])); ?></td>
            <td>
                <div class="action-buttons">
                    <a href="edit-url.php?id=<?php echo $url['id']; ?>" 
                       class="btn btn-sm btn-primary">
                        ✏️ Editar
                    </a>
                    <a href="stats.php?code=<?php echo $url['short_code']; ?>" 
                       class="btn btn-sm btn-info">
                        📊 Stats
                    </a>
                    <button onclick="deleteUrl(<?php echo $url['id']; ?>)" 
                            class="btn btn-sm btn-danger">
                        🗑️ Eliminar
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Botón para eliminar seleccionadas -->
<div class="bulk-actions">
    <button onclick="deleteSelected()" class="btn btn-danger">
        🗑️ Eliminar seleccionadas
    </button>
</div>

<!-- Incluir JavaScript -->
<script src="../assets/js/admin.js"></script>

<!-- Opcional: SweetAlert2 para mejores confirmaciones -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
