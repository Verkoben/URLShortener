<?php
session_start();
require_once '../conf.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Verificar si es admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: panel_simple.php');
    exit();
}

// Verificar si es el super admin (definido en conf.php)
$is_super_admin = ($_SESSION['username'] === ADMIN_USERNAME);

// Conectar a la base de datos
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$message = '';

// Función para registrar auditoría
function logAudit($user_id, $action, $old_value, $new_value) {
    global $db, $_SESSION;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $db->prepare("INSERT INTO user_audit_log (user_id, action, old_value, new_value, changed_by, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $old_value, $new_value, $_SESSION['user_id'], $ip, $user_agent]);
    } catch (PDOException $e) {
        // Silenciosamente fallar si no existe la tabla
    }
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                createUser();
                break;
            case 'delete':
                deleteUser($_POST['user_id']);
                break;
            case 'toggle_status':
                toggleUserStatus($_POST['user_id']);
                break;
            case 'update_password':
                updatePassword($_POST['user_id'], $_POST['new_password']);
                break;
            case 'change_role':
                if ($is_super_admin) {
                    changeUserRole($_POST['user_id'], $_POST['new_role']);
                }
                break;
        }
    }
}

// Función para cambiar rol de usuario (SOLO SUPER ADMIN)
function changeUserRole($user_id, $new_role) {
    global $db, $message, $_SESSION;
    
    $user_id = intval($user_id);
    
    // No permitir cambiar el propio rol
    if ($user_id == $_SESSION['user_id']) {
        $message = "❌ No puedes cambiar tu propio rol";
        return;
    }
    
    try {
        // Obtener rol actual
        $stmt = $db->prepare("SELECT role, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $old_role = $user['role'];
        $username = $user['username'];
        
        // Actualizar rol
        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        
        // Registrar en auditoría
        logAudit($user_id, 'role_change', $old_role, $new_role);
        
        $message = "✅ Rol de usuario '$username' actualizado de '$old_role' a '$new_role'";
    } catch (PDOException $e) {
        $message = "❌ Error al actualizar rol";
    }
}

// Función para crear usuario
function createUser() {
    global $db, $message, $is_super_admin;
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'user';
    
    // Solo el super admin puede crear otros admins
    if ($role === 'admin' && !$is_super_admin) {
        $role = 'user';
        $message = "⚠️ Solo el Super Admin puede crear administradores. Usuario creado como usuario normal.";
    }
    
    if (empty($username) || empty($password) || empty($email)) {
        $message = "Todos los campos obligatorios deben ser completados";
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email inválido";
        return;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$username, $hashed_password, $email, $full_name, $role]);
        
        $new_user_id = $db->lastInsertId();
        logAudit($new_user_id, 'user_created', null, $username);
        
        $message = "✅ Usuario creado exitosamente";
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "❌ El usuario o email ya existe";
        } else {
            $message = "❌ Error al crear usuario";
        }
    }
}

// Función para eliminar usuario
function deleteUser($user_id) {
    global $db, $message;
    
    $user_id = intval($user_id);
    
    if ($user_id == $_SESSION['user_id']) {
        $message = "❌ No puedes eliminar tu propio usuario";
        return;
    }
    
    // Verificar si es el super admin
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $username = $stmt->fetchColumn();
    
    if ($username === ADMIN_USERNAME) {
        $message = "❌ No se puede eliminar al Super Admin";
        return;
    }
    
    try {
        logAudit($user_id, 'user_deleted', $username, null);
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "✅ Usuario eliminado exitosamente";
        
    } catch (PDOException $e) {
        $message = "❌ Error al eliminar usuario";
    }
}

// Función para cambiar estado del usuario
function toggleUserStatus($user_id) {
    global $db, $message;
    
    $user_id = intval($user_id);
    
    try {
        $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_status = $stmt->fetchColumn();
        
        $new_status = ($current_status == 'active') ? 'banned' : 'active';
        
        logAudit($user_id, 'status_change', $current_status, $new_status);
        
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        $message = "✅ Estado del usuario actualizado";
    } catch (PDOException $e) {
        $message = "❌ Error al actualizar estado";
    }
}

// Función para actualizar contraseña
function updatePassword($user_id, $new_password) {
    global $db, $message;
    
    $user_id = intval($user_id);
    
    if (strlen($new_password) < 6) {
        $message = "❌ La contraseña debe tener al menos 6 caracteres";
        return;
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    try {
        logAudit($user_id, 'password_changed', null, null);
        
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        $message = "✅ Contraseña actualizada exitosamente";
    } catch (PDOException $e) {
        $message = "❌ Error al actualizar contraseña";
    }
}

// Obtener lista de usuarios con sus estadísticas
$query = "SELECT u.*, 
          COUNT(DISTINCT urls.id) as total_urls,
          COALESCE(SUM(urls.clicks), 0) as total_clicks,
          (SELECT CONCAT(changed_by_user.username, ' el ', DATE_FORMAT(al.changed_at, '%d/%m/%Y %H:%i'))
           FROM user_audit_log al
           LEFT JOIN users changed_by_user ON al.changed_by = changed_by_user.id
           WHERE al.user_id = u.id AND al.action = 'role_change'
           ORDER BY al.changed_at DESC
           LIMIT 1) as last_role_change
          FROM users u
          LEFT JOIN urls ON urls.user_id = u.id
          GROUP BY u.id
          ORDER BY u.created_at DESC";
$stmt = $db->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales
$stats = [];
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['active_users'] = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$stats['admin_users'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - URL Shortener</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        .header h1 {
            margin: 0;
        }
        .super-admin-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #333;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
        }
        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        .badge-gold {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #333;
            font-weight: bold;
        }
        .back-links {
            margin-bottom: 20px;
        }
        .back-links a {
            color: #007bff;
            text-decoration: none;
            margin-right: 20px;
        }
        .back-links a:hover {
            text-decoration: underline;
        }
        .overflow-x {
            overflow-x: auto;
        }
        /* Menú superior */
        .simple-menu {
            background-color: #3a4149;
            padding: 15px 0;
            color: white;
        }
        .simple-menu-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-title {
            font-size: 1.5em;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .menu-links {
            display: flex;
            gap: 20px;
        }
        .menu-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .menu-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        .menu-links .btn-acortador {
            background: #28a745;
        }
        .menu-links .btn-panel {
            background: #007bff;
        }
        .menu-links .btn-salir {
            background: #dc3545;
        }
        .role-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            background: white;
        }
        .role-change-form {
            display: inline-block;
        }
        .super-admin-info {
            background: #fffbf0;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .audit-info {
            font-size: 11px;
            color: #666;
            font-style: italic;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <!-- Menú superior -->
    <div class="simple-menu">
        <div class="simple-menu-container">
            <div class="menu-title">
                🌐 Acortador URL
            </div>
            <div class="menu-links">
                <a href="../" class="btn-acortador">🔗 Acortador</a>
                <a href="panel_simple.php" class="btn-panel">📊 Panel</a>
                <a href="logout.php" class="btn-salir">🚪 Salir</a>
            </div>
        </div>
    </div>
    
    <div class="header">
        <h1>👥 Gestión de Usuarios</h1>
        <p>Administra los usuarios del sistema</p>
        <?php if ($is_super_admin): ?>
        <div class="super-admin-badge">
            👑 Super Admin
        </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="back-links">
            <a href="panel_simple.php">← Volver al Panel</a>
            <a href="../">🏠 Ir al Acortador</a>
        </div>

        <?php if ($message): ?>
            <div class="message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($is_super_admin): ?>
        <div class="super-admin-info">
            <strong>👑 Privilegios de Super Admin:</strong> Puedes crear administradores y cambiar roles de usuarios.
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Usuarios Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['admin_users']; ?></div>
                <div class="stat-label">Administradores</div>
            </div>
        </div>
        
        <!-- Crear usuario -->
        <div class="card">
            <div class="card-header">
                ➕ Crear Nuevo Usuario
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Usuario *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Contraseña *</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" class="form-control" name="full_name">
                        </div>
                        
                        <div class="form-group">
                            <label>Rol <?php echo !$is_super_admin ? '<small>(Solo usuarios normales)</small>' : ''; ?></label>
                            <select class="form-control" name="role">
                                <option value="user">Usuario</option>
                                <?php if ($is_super_admin): ?>
                                <option value="admin">Administrador</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        ✅ Crear Usuario
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Lista de usuarios -->
        <div class="card">
            <div class="card-header">
                📋 Usuarios Registrados (<?php echo count($users); ?>)
            </div>
            <div class="card-body">
                <div class="overflow-x">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>URLs</th>
                                <th>Clicks</th>
                                <th>Último Login</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <?php $is_this_super_admin = ($user['username'] === ADMIN_USERNAME); ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge badge-info">Tú</span>
                                    <?php endif; ?>
                                    <?php if ($is_this_super_admin): ?>
                                        <span class="badge badge-gold">👑</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($is_super_admin && !$is_this_super_admin && $user['id'] != $_SESSION['user_id']): ?>
                                        <!-- Solo el super admin puede cambiar roles -->
                                        <form method="POST" class="role-change-form">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="role-select" onchange="this.form.submit()">
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                        <?php if (!empty($user['last_role_change'])): ?>
                                            <div class="audit-info">
                                                Cambiado por <?php echo htmlspecialchars($user['last_role_change']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $statusColors = [
                                        'active' => 'success',
                                        'banned' => 'danger',
                                        'pending' => 'warning'
                                    ];
                                    $statusText = [
                                        'active' => 'Activo',
                                        'banned' => 'Baneado',
                                        'pending' => 'Pendiente'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statusColors[$user['status']] ?? 'secondary'; ?>">
                                        <?php echo $statusText[$user['status']] ?? $user['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['total_urls']; ?></td>
                                <td><?php echo $user['total_clicks']; ?></td>
                                <td>
                                    <?php 
                                    if ($user['last_login']) {
                                        echo date('d/m/Y H:i', strtotime($user['last_login']));
                                    } else {
                                        echo '<span style="color: #999;">Nunca</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <!-- Cambiar estado -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-warning" title="Cambiar estado">
                                            <?php echo $user['status'] == 'active' ? '🔒' : '🔓'; ?>
                                        </button>
                                    </form>
                                    
                                    <!-- Cambiar contraseña -->
                                    <button type="button" class="btn btn-info" 
                                            onclick="showPasswordForm(<?php echo $user['id']; ?>)">
                                        🔑
                                    </button>
                                    
                                    <!-- Eliminar -->
                                    <?php if ($user['id'] != $_SESSION['user_id'] && !$is_this_super_admin): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Eliminar usuario <?php echo htmlspecialchars($user['username']); ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            🗑️
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Formulario de cambio de contraseña (oculto) -->
                            <tr id="password-form-<?php echo $user['id']; ?>" style="display: none;">
                                <td colspan="10">
                                    <form method="POST" style="padding: 10px; background: #f8f9fa;">
                                        <input type="hidden" name="action" value="update_password">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <label>Nueva contraseña para <?php echo $user['username']; ?>:</label>
                                        <input type="password" name="new_password" required minlength="6" 
                                               style="margin: 0 10px; padding: 5px;">
                                        <button type="submit" class="btn btn-primary">Cambiar</button>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="hidePasswordForm(<?php echo $user['id']; ?>)">Cancelar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPasswordForm(userId) {
            document.getElementById('password-form-' + userId).style.display = 'table-row';
        }
        
        function hidePasswordForm(userId) {
            document.getElementById('password-form-' + userId).style.display = 'none';
        }
        
        // Auto-ocultar mensajes después de 5 segundos
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(msg) {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    msg.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
