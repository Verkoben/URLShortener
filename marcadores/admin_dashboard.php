<?php
// admin_dashboard.php - Panel de administración para superadmin (COMPLETO FINAL)
require_once 'config.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Verificar si es superadmin
$is_superadmin = false;
if ($user_id == 1 || (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin')) {
    $is_superadmin = true;
}

// Verificar en BD
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    if ($user_data && ($user_data['role'] == 'admin' || $user_data['role'] == 'superadmin')) {
        $is_superadmin = true;
    }
} catch (Exception $e) {
    // Ignorar si no existe columna role
}

if (!$is_superadmin) {
    die("<!DOCTYPE html>
    <html>
    <head>
        <title>Acceso Denegado</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                background: #f8fafc;
                margin: 0;
            }
            .error-container {
                text-align: center;
                padding: 3rem;
                background: white;
                border-radius: 1rem;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                max-width: 400px;
            }
            .error-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='error-icon'>🔒</div>
            <h2>Acceso Denegado</h2>
            <p class='text-muted'>Solo los administradores pueden acceder a esta sección.</p>
            <a href='index.php' class='btn btn-primary mt-3'>Volver al inicio</a>
        </div>
    </body>
    </html>");
}

// Procesar acciones
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'clean_logs':
            // Limpiar logs antiguos (más de 90 días)
            try {
                $stmt = $pdo->prepare("DELETE FROM url_analytics WHERE clicked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                header("Location: admin_dashboard.php?msg=Eliminados $deleted registros antiguos");
                exit;
            } catch (Exception $e) {
                header("Location: admin_dashboard.php?error=Error al limpiar logs");
                exit;
            }
            break;
            
        case 'export_stats':
            // Exportar estadísticas (implementar según necesidades)
            header("Location: admin_dashboard.php?msg=Función de exportación en desarrollo");
            exit;
            break;
    }
}

// Obtener período seleccionado
$period = isset($_GET['period']) ? $_GET['period'] : '7';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Estadísticas generales del sistema
$stats = [];

// Total de usuarios
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Total de URLs
$stmt = $pdo->query("SELECT COUNT(*) FROM urls");
$stats['total_urls'] = $stmt->fetchColumn();

// Total de clicks
$stmt = $pdo->query("SELECT COALESCE(SUM(clicks), 0) FROM urls");
$stats['total_clicks'] = $stmt->fetchColumn();

// URLs creadas en el período
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM urls 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->execute([$period]);
$stats['new_urls'] = $stmt->fetchColumn();

// Clicks en el período (de url_analytics)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM url_analytics 
        WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$period]);
    $stats['period_clicks'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $stats['period_clicks'] = 0;
}

// Top usuarios por URLs (CORREGIDO)
$stmt = $pdo->prepare("
    SELECT 
        u.id, 
        u.username, 
        u.email,
        u.created_at,
        COUNT(urls.id) as url_count,
        COALESCE(SUM(urls.clicks), 0) as total_clicks
    FROM users u
    LEFT JOIN urls ON u.id = urls.user_id
    GROUP BY u.id, u.username, u.email, u.created_at
    ORDER BY total_clicks DESC
    LIMIT 10
");
$stmt->execute();
$top_users = $stmt->fetchAll();

// URLs más populares
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(urls.title LIKE ? OR urls.short_code LIKE ? OR urls.original_url LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($user_filter) {
    $where_conditions[] = "urls.user_id = ?";
    $params[] = $user_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $pdo->prepare("
    SELECT 
        urls.*,
        users.username,
        cd.domain,
        (SELECT COUNT(*) FROM url_analytics WHERE url_id = urls.id AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) as period_clicks
    FROM urls
    LEFT JOIN users ON urls.user_id = users.id
    LEFT JOIN custom_domains cd ON urls.domain_id = cd.id
    $where_clause
    ORDER BY urls.clicks DESC
    LIMIT 20
");
array_unshift($params, $period);
$stmt->execute($params);
$top_urls = $stmt->fetchAll();

// Verificar qué columnas existen en url_analytics
$columns_exist = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM url_analytics");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $columns_exist = array_flip($columns);
} catch (Exception $e) {
    // Tabla no existe
}

// Construir consulta de actividad reciente según columnas disponibles
$activity_fields = [
    'ua.clicked_at',
    'ua.ip_address',
    'ua.country',
    'ua.city',
    'urls.short_code',
    'urls.title',
    'users.username'
];

// Añadir campos opcionales si existen
if (isset($columns_exist['browser'])) {
    $activity_fields[] = 'ua.browser';
} else {
    $activity_fields[] = "'Unknown' as browser";
}

if (isset($columns_exist['device'])) {
    $activity_fields[] = 'ua.device';
} else {
    $activity_fields[] = "'Unknown' as device";
}

if (isset($columns_exist['os'])) {
    $activity_fields[] = 'ua.os';
} else {
    $activity_fields[] = "'Unknown' as os";
}

// Actividad reciente
$activity_query = "
    SELECT 
        " . implode(', ', $activity_fields) . "
    FROM url_analytics ua
    JOIN urls ON ua.url_id = urls.id
    JOIN users ON urls.user_id = users.id
    ORDER BY ua.clicked_at DESC
    LIMIT 50
";

try {
    $stmt = $pdo->prepare($activity_query);
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_activity = [];
}

// Lista de usuarios para filtro
$stmt = $pdo->query("SELECT id, username, email FROM users ORDER BY username");
$all_users = $stmt->fetchAll();

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Marcadores';

// Mensajes de feedback
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark: #1e293b;
        }
        
        body {
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,.08);
            padding: 1rem 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .admin-header h1 {
            margin: 0;
            font-weight: 700;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .data-table {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            margin-bottom: 2rem;
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        
        .activity-item:hover {
            background: #f8fafc;
            margin: 0 -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #64748b;
            font-size: 0.875rem;
            min-width: 150px;
        }
        
        .activity-details {
            flex: 1;
            margin-left: 1rem;
        }
        
        .badge-device {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Colores para iconos */
        .stat-icon.blue { background: #dbeafe; color: #3b82f6; }
        .stat-icon.green { background: #d1fae5; color: #10b981; }
        .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-icon.orange { background: #fed7aa; color: #ea580c; }
        .stat-icon.red { background: #fee2e2; color: #ef4444; }
        
        /* Alertas mejoradas */
        .alert-custom {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Tabla mejorada */
        .table > tbody > tr {
            transition: background-color 0.2s;
        }
        
        .table > tbody > tr:hover {
            background-color: #f8fafc;
        }
        
        /* Animación de carga */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .loading {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-bookmark-star-fill text-primary"></i> <?php echo $siteName; ?>
            </a>
            <div class="ms-auto d-flex gap-2">
                <a href="geo_map.php?all=1" class="btn btn-sm btn-outline-success" title="Mapa global">
                    <i class="bi bi-globe"></i>
                </a>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Header Admin -->
    <div class="admin-header">
        <div class="container">
            <h1><i class="bi bi-speedometer2"></i> Panel de Administración</h1>
            <p class="mb-0 opacity-75">Gestión completa del sistema de enlaces</p>
        </div>
    </div>
    
    <div class="container">
        <?php if ($msg): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i>
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filter-bar">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-calendar3"></i> Período
                    </label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="1" <?php echo $period == '1' ? 'selected' : ''; ?>>Últimas 24 horas</option>
                        <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>Últimos 7 días</option>
                        <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>Últimos 30 días</option>
                        <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>Últimos 90 días</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-person"></i> Filtrar por usuario
                    </label>
                    <select name="user" class="form-select" onchange="this.form.submit()">
                        <option value="0">Todos los usuarios</option>
                        <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username'] ?: $user['email']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-search"></i> Buscar URL
                    </label>
                    <input type="text" name="search" class="form-control" placeholder="Título, código o URL..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Usuarios totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-link-45deg"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_urls']); ?></div>
                <div class="stat-label">URLs totales</div>
                <small class="text-success">
                    <i class="bi bi-arrow-up"></i> +<?php echo $stats['new_urls']; ?> nuevas
                </small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-cursor-fill"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_clicks']); ?></div>
                <div class="stat-label">Clicks totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['period_clicks']); ?></div>
                <div class="stat-label">Clicks en período</div>
            </div>
        </div>
        
        <div class="row">
            <!-- Top Usuarios -->
            <div class="col-lg-6">
                <div class="data-table">
                    <h3 class="table-title">
                        <i class="bi bi-trophy text-warning"></i> Top Usuarios
                    </h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th class="text-center">URLs</th>
                                    <th class="text-center">Clicks</th>
                                    <th>Desde</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_users as $user): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['username'] ?: 'Usuario ' . $user['id']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo number_format($user['url_count'] ?? 0); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo number_format($user['total_clicks'] ?? 0); ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- URLs más populares -->
            <div class="col-lg-6">
                <div class="data-table">
                    <h3 class="table-title">
                        <i class="bi bi-fire text-danger"></i> URLs Más Populares
                    </h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Usuario</th>
                                    <th class="text-center">Clicks</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_urls as $url): ?>
                                <tr>
                                    <td>
                                        <div style="max-width: 250px;">
                                            <strong class="d-block text-truncate">
                                                <?php echo htmlspecialchars($url['title'] ?: $url['short_code']); ?>
                                            </strong>
                                            <small class="text-muted">
                                                <?php 
                                                $short_url = $url['domain'] 
                                                    ? $url['domain'] . '/' . $url['short_code']
                                                    : $url['short_code'];
                                                echo htmlspecialchars($short_url); 
                                                ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($url['username'] ?: 'Usuario ' . $url['user_id']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div>
                                            <span class="badge bg-primary"><?php echo number_format($url['clicks'] ?? 0); ?></span>
                                            <?php if (($url['period_clicks'] ?? 0) > 0): ?>
                                            <br>
                                            <small class="text-success">
                                                <i class="bi bi-arrow-up"></i> +<?php echo $url['period_clicks']; ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="analytics_url.php?url_id=<?php echo $url['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Ver estadísticas">
                                                <i class="bi bi-graph-up"></i>
                                            </a>
                                            <a href="geo_map.php?url_id=<?php echo $url['id']; ?>" 
                                               class="btn btn-outline-success" 
                                               title="Ver mapa">
                                                <i class="bi bi-geo-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actividad Reciente -->
        <div class="data-table">
            <h3 class="table-title">
                <i class="bi bi-activity text-info"></i> Actividad Reciente
                <small class="text-muted ms-2">(Últimos 50 clicks)</small>
            </h3>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($recent_activity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-time">
                        <i class="bi bi-clock"></i>
                        <?php echo date('d/m H:i', strtotime($activity['clicked_at'])); ?>
                    </div>
                    <div class="activity-details">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <strong><?php echo htmlspecialchars($activity['title'] ?: $activity['short_code']); ?></strong>
                            <span class="text-muted">por <?php echo htmlspecialchars($activity['username']); ?></span>
                        </div>
                        <div>
                            <?php if ($activity['country'] && $activity['country'] != 'Unknown'): ?>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($activity['country']); ?>
                                <?php if ($activity['city']): ?>, <?php echo htmlspecialchars($activity['city']); ?><?php endif; ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($activity['device'] && $activity['device'] != 'Unknown'): ?>
                            <span class="badge badge-device bg-secondary">
                                <?php 
                                $device_icon = $activity['device'] == 'mobile' ? 'phone' : 
                                              ($activity['device'] == 'tablet' ? 'tablet' : 'laptop');
                                ?>
                                <i class="bi bi-<?php echo $device_icon; ?>"></i> <?php echo ucfirst($activity['device']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($activity['browser'] && $activity['browser'] != 'Unknown'): ?>
                            <span class="badge badge-device bg-info">
                                <i class="bi bi-globe"></i> 
                                <?php echo $activity['browser']; ?>
                            </span>
                            <?php endif; ?>
                            
                            <small class="text-muted ms-2">IP: <?php echo $activity['ip_address']; ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($recent_activity)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #e2e8f0;"></i>
                    <p class="text-muted mt-3">No hay actividad reciente</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="data-table">
            <h3 class="table-title">
                <i class="bi bi-lightning-charge text-warning"></i> Acciones Rápidas
            </h3>
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="setup_migration.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-database-add"></i> Gestión de BD
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="geo_map.php?all=1" class="btn btn-outline-success w-100">
                        <i class="bi bi-globe-americas"></i> Mapa Global
                    </a>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-danger w-100" 
                            onclick="if(confirm('¿Estás seguro de que quieres limpiar los logs de más de 90 días?')) window.location='?action=clean_logs'">
                        <i class="bi bi-trash3"></i> Limpiar Logs
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-info w-100" 
                            onclick="alert('Función de exportación en desarrollo')">
                        <i class="bi bi-download"></i> Exportar Stats
                    </button>
                </div>
            </div>
            
            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <a href="index.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-eye"></i> Vista Usuario
                    </a>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-warning w-100" 
                            onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
                <div class="col-md-3">
                    <a href="#" class="btn btn-outline-dark w-100" 
                       onclick="alert('Configuración en desarrollo')">
                        <i class="bi bi-gear"></i> Configuración
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="#" class="btn btn-outline-primary w-100"
                       data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="bi bi-question-circle"></i> Ayuda
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="text-center text-muted py-4 mt-5">
        <small>
            <i class="bi bi-shield-check"></i> Panel de Administración • 
            <?php echo $siteName; ?> • 
            <?php echo date('Y'); ?>
        </small>
    </footer>
    
    <!-- Modal de Ayuda -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-question-circle text-primary"></i> Ayuda del Panel de Administración
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6><i class="bi bi-info-circle"></i> Funciones del Panel</h6>
                    <ul>
                        <li><strong>Estadísticas:</strong> Vista general del sistema con métricas en tiempo real</li>
                        <li><strong>Top Usuarios:</strong> Usuarios con más actividad y clicks</li>
                        <li><strong>URLs Populares:</strong> Enlaces más visitados del sistema</li>
                        <li><strong>Actividad Reciente:</strong> Últimos 50 clicks con detalles completos</li>
                    </ul>
                    
                    <h6 class="mt-4"><i class="bi bi-filter"></i> Filtros</h6>
                    <ul>
                        <li><strong>Período:</strong> Filtra estadísticas por rango de tiempo</li>
                        <li><strong>Usuario:</strong> Ver datos de un usuario específico</li>
                        <li><strong>Buscar:</strong> Encuentra URLs por título, código o dirección</li>
                    </ul>
                    
                    <h6 class="mt-4"><i class="bi bi-lightning"></i> Acciones Rápidas</h6>
                    <ul>
                        <li><strong>Gestión BD:</strong> Configurar y migrar base de datos</li>
                        <li><strong>Mapa Global:</strong> Ver todos los clicks en el mapa mundial</li>
                        <li><strong>Limpiar Logs:</strong> Eliminar registros antiguos (+90 días)</li>
                        <li><strong>Exportar Stats:</strong> Descargar estadísticas (próximamente)</li>
                    </ul>
                    
                    <div class="alert alert-info mt-4">
                        <i class="bi bi-lightbulb"></i> <strong>Tip:</strong> El panel se actualiza automáticamente cada 30 segundos.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-refresh cada 30 segundos para actividad reciente
    let refreshTimer = setTimeout(function() {
        location.reload();
    }, 30000);
    
    // Detener auto-refresh si el usuario está interactuando
    document.addEventListener('mousemove', function() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function() {
            location.reload();
        }, 30000);
    });
    
    // Animación suave al cargar
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.stat-card, .data-table');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
    </script>
</body>
</html>
