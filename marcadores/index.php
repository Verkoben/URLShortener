<?php
// marcadores/index.php - Gestor de URLs
require_once 'config.php';
require_once 'functions.php';

$user_id = getCurrentUserId();
if (!$user_id) {
    header('Location: ../admin/login.php');
    exit;
}

$userInfo = getCurrentUserInfo();
if (!$userInfo) {
    $userInfo = [
        'id' => $user_id,
        'username' => $_SESSION['username'] ?? 'Usuario',
        'email' => 'user@localhost'
    ];
}

// Parámetros de paginación y filtros
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(10, min(100, intval($_GET['limit'] ?? 20)));
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

$offset = ($page - 1) * $limit;

// Construir query con filtros
$whereClause = "WHERE u.user_id = ?";
$params = [$user_id];

if (!empty($search)) {
    $whereClause .= " AND (u.short_code LIKE ? OR u.original_url LIKE ? OR u.title LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Validar campo de ordenación
$validSorts = ['created_at', 'clicks', 'short_code', 'title'];
$sort = in_array($sort, $validSorts) ? $sort : 'created_at';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

try {
    // Contar total
    $countQuery = "SELECT COUNT(*) FROM urls u " . $whereClause;
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Obtener URLs
    $dataQuery = "
        SELECT 
            u.*,
            cd.domain as custom_domain
        FROM urls u
        LEFT JOIN custom_domains cd ON u.domain_id = cd.id
        {$whereClause}
        ORDER BY u.{$sort} {$order}
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($dataQuery);
    $stmt->execute($params);
    $urls = $stmt->fetchAll();
    
    // Agregar URLs completas
    foreach ($urls as &$url) {
        $domain = $url['custom_domain'] ?? '0ln.org';
        $url['short_url'] = "https://{$domain}/{$url['short_code']}";
    }
    
    // Calcular stats del usuario
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_urls,
            SUM(clicks) as total_clicks,
            AVG(clicks) as avg_clicks,
            MAX(clicks) as max_clicks
        FROM urls 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $userStats = $stmt->fetch();
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $urls = [];
    $total = 0;
    $userStats = ['total_urls' => 0, 'total_clicks' => 0, 'avg_clicks' => 0, 'max_clicks' => 0];
}

$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Gestor de URLs - <?= htmlspecialchars($userInfo['username']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8em;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn-header {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        
        .btn-header.primary {
            background: #28a745;
        }
        
        .btn-header.primary:hover {
            background: #218838;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .dashboard-title {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .controls {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .controls-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.5em;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f3f5;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
            position: relative;
        }
        
        th.sortable {
            cursor: pointer;
            user-select: none;
        }
        
        th.sortable:hover {
            background: #e9ecef;
        }
        
        .sort-icon {
            margin-left: 5px;
            opacity: 0.5;
        }
        
        .sort-icon.active {
            opacity: 1;
            color: #667eea;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .url-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .url-short {
            font-family: monospace;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .url-original {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .clicks-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .date-cell {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .actions-cell {
            white-space: nowrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 16px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            text-decoration: none;
            color: #495057;
            background: white;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .export-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .export-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 180px;
            overflow: hidden;
        }
        
        .export-menu a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .export-menu a:last-child {
            border-bottom: none;
        }
        
        .export-menu a:hover {
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .dashboard-title {
                font-size: 2em;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-wrapper {
                font-size: 14px;
            }
            
            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="../" class="logo">
                <span>📊</span>
                <span>Gestor de URLs</span>
            </a>
            <div class="header-actions">
                <a href="../" class="btn-header">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <a href="analytics_dashboard.php" class="btn-header">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
                <div class="export-dropdown">
                    <button onclick="toggleExportMenu()" class="btn-header primary">
                        <i class="fas fa-download"></i> Exportar ▼
                    </button>
                    <div id="exportMenu" class="export-menu">
                        <a href="export_bookmarks.php?format=html&download=1">
                            <i class="fas fa-bookmark"></i> Favoritos HTML
                        </a>
                        <a href="export_bookmarks.php?format=csv&download=1">
                            <i class="fas fa-file-csv"></i> Archivo CSV
                        </a>
                        <a href="export_bookmarks.php?format=json&download=1">
                            <i class="fas fa-code"></i> Datos JSON
                        </a>
                        <a href="api.php?action=export_json">
                            <i class="fas fa-database"></i> API JSON
                        </a>
                    </div>
                </div>
                <a href="../admin/panel_simple.php" class="btn-header">
                    <i class="fas fa-cog"></i> Admin
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <span>👤</span>
                URLs de <?= htmlspecialchars($userInfo['username']) ?>
            </h1>
            <p style="color: #6c757d; font-size: 1.1em;">
                Gestiona y analiza todas tus URLs acortadas
            </p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($userStats['total_urls']) ?></div>
                    <div class="stat-label">URLs Totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($userStats['total_clicks']) ?></div>
                    <div class="stat-label">Clicks Totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= round($userStats['avg_clicks'], 1) ?></div>
                    <div class="stat-label">Promedio Clicks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($userStats['max_clicks']) ?></div>
                    <div class="stat-label">Máximo Clicks</div>
                </div>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="controls">
            <form method="GET" class="controls-row">
                <div class="search-box">
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Buscar por código, URL o título..." 
                           class="search-input">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Fecha</option>
                    <option value="clicks" <?= $sort === 'clicks' ? 'selected' : '' ?>>Clicks</option>
                    <option value="short_code" <?= $sort === 'short_code' ? 'selected' : '' ?>>Código</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Título</option>
                </select>
                
                <select name="order" class="filter-select" onchange="this.form.submit()">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Descendente</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascendente</option>
                </select>
                
                <select name="limit" class="filter-select" onchange="this.form.submit()">
                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20 por página</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 por página</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 por página</option>
                </select>
                
                <?php if (!empty($search)): ?>
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpiar
                </a>
                <?php endif; ?>
                
                <input type="hidden" name="page" value="1">
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">
                    📋 URLs (<?= number_format($total) ?> total<?= !empty($search) ? ', filtradas' : '' ?>)
                </h2>
                <div>
                    <a href="../" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nueva URL
                    </a>
                </div>
            </div>
            
            <?php if (!empty($urls)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>URL Destino</th>
                            <th>Clicks</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($urls as $url): ?>
                        <tr>
                            <td>
                                <div class="url-short"><?= htmlspecialchars($url['short_code']) ?></div>
                                <div style="font-size: 0.8em; color: #6c757d; margin-top: 4px;">
                                    <?= htmlspecialchars($url['short_url']) ?>
                                </div>
                            </td>
                            <td class="url-cell">
                                <div class="url-original" title="<?= htmlspecialchars($url['original_url']) ?>">
                                    <?= htmlspecialchars($url['original_url']) ?>
                                </div>
                                <?php if ($url['title']): ?>
                                <div style="font-size: 0.8em; color: #495057; margin-top: 4px;">
                                    📝 <?= htmlspecialchars($url['title']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="clicks-badge"><?= number_format($url['clicks']) ?></span>
                            </td>
                            <td class="date-cell">
                                <?= date('d/m/Y H:i', strtotime($url['created_at'])) ?>
                            </td>
                            <td class="actions-cell">
                                <a href="<?= htmlspecialchars($url['short_url']) ?>" 
                                   target="_blank" 
                                   class="btn btn-sm" 
                                   title="Abrir URL">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="analytics_url.php?url_id=<?= $url['id'] ?>" 
                                   class="btn btn-sm" 
                                   title="Ver Analytics">
                                    <i class="fas fa-chart-bar"></i>
                                </a>
                                <button onclick="copyToClipboard('<?= htmlspecialchars($url['short_url']) ?>')" 
                                        class="btn btn-sm btn-secondary" 
                                        title="Copiar URL">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&limit=<?= $limit ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&limit=<?= $limit ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&limit=<?= $limit ?>">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&limit=<?= $limit ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&limit=<?= $limit ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-link"></i>
                <h3>No hay URLs<?= !empty($search) ? ' que coincidan con tu búsqueda' : '' ?></h3>
                <p><?= !empty($search) ? 'Intenta con otros términos de búsqueda.' : 'Crea tu primera URL desde la página principal.' ?></p>
                <?php if (empty($search)): ?>
                <a href="../" class="btn" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Crear Primera URL
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }

        document.addEventListener('click', function(event) {
            const exportBtn = document.querySelector('.export-dropdown button');
            const exportMenu = document.getElementById('exportMenu');
            
            if (exportBtn && exportMenu && !exportBtn.contains(event.target) && !exportMenu.contains(event.target)) {
                exportMenu.style.display = 'none';
            }
        });
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.style.background = '#28a745';
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.style.background = '';
                }, 1500);
            }).catch(function(err) {
                alert('Error al copiar: ' + err);
            });
        }
        
        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    </script>
</body>
</html>
