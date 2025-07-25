<?php
require_once 'session_check.php';
require_once '../conf.php';

}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$message = '';
$show_instructions = false;
$new_domain_info = null;

// Generar archivo de verificación
if (isset($_GET['download_verification']) && isset($_GET['domain_id'])) {
    $domain_id = (int)$_GET['domain_id'];
    
    $stmt = $db->prepare("SELECT * FROM custom_domains WHERE id = ? AND user_id = ?");
    $stmt->execute([$domain_id, $user_id]);
    $domain = $stmt->fetch();
    
    if ($domain) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="acortador-verify-' . $domain['verification_token'] . '.txt"');
        echo "acortador-site-verification: " . $domain['verification_token'];
        exit;
    }
}

// Añadir dominio - MEJORADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_domain'])) {
    $domain = strtolower(trim($_POST['domain']));
    
    // Limpiar el dominio (quitar http, www, etc)
    $domain = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $domain);
    $domain = rtrim($domain, '/');
    
    if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
        $message = "❌ Dominio inválido. Por favor, ingresa un dominio válido (ej: ejemplo.com)";
    } else {
        // Primero verificar si el dominio ya existe
        $check_stmt = $db->prepare("SELECT id, user_id, status FROM custom_domains WHERE domain = ?");
        $check_stmt->execute([$domain]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // El dominio ya existe
            if ($existing['user_id'] == $user_id) {
                // Es del mismo usuario
                if ($existing['status'] === 'active') {
                    $message = "⚠️ Ya tienes este dominio activo y funcionando";
                } elseif ($existing['status'] === 'pending') {
                    $message = "⚠️ Este dominio está pendiente de verificación. <a href='#domain-" . $existing['id'] . "'>Ver instrucciones</a>";
                } else {
                    $message = "⚠️ Ya tienes este dominio registrado pero está inactivo. Puedes reactivarlo desde la lista";
                }
            } else {
                $message = "❌ Este dominio ya está registrado por otro usuario";
            }
        } else {
            // El dominio no existe, proceder a crearlo
            $verification_token = bin2hex(random_bytes(32));
            
            try {
                $stmt = $db->prepare("INSERT INTO custom_domains (user_id, domain, verification_token, verification_method) VALUES (?, ?, ?, 'file')");
                $stmt->execute([$user_id, $domain, $verification_token]);
                
                $new_domain_id = $db->lastInsertId();
                
                // Obtener la información del dominio recién creado
                $stmt = $db->prepare("SELECT * FROM custom_domains WHERE id = ?");
                $stmt->execute([$new_domain_id]);
                $new_domain_info = $stmt->fetch();
                
                $message = "✅ Dominio añadido exitosamente. Sigue las instrucciones a continuación para verificarlo:";
                $show_instructions = true;
                
            } catch (PDOException $e) {
                // Error genérico de base de datos
                if ($e->getCode() == '23000') {
                    $message = "❌ Error: Este dominio ya existe en el sistema";
                } else {
                    $message = "❌ Error al crear el dominio: " . $e->getMessage();
                }
            }
        }
    }
}

// Reactivar dominio existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_domain'])) {
    $domain_id = (int)$_POST['domain_id'];
    
    // Generar nuevo token de verificación
    $new_token = bin2hex(random_bytes(32));
    
    $stmt = $db->prepare("UPDATE custom_domains SET status = 'pending', verification_token = ?, verified_at = NULL WHERE id = ? AND user_id = ?");
    $stmt->execute([$new_token, $domain_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $message = "✅ Dominio reactivado. Ahora puedes verificarlo nuevamente.";
    } else {
        $message = "❌ No se pudo reactivar el dominio.";
    }
}

// Verificación manual (sin lag)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_manual'])) {
    $domain_id = (int)$_POST['domain_id'];
    
    $stmt = $db->prepare("UPDATE custom_domains SET status = 'active', verified_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$domain_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $message = "✅ Dominio activado manualmente. Prueba accediendo desde tu dominio.";
    } else {
        $message = "❌ No se pudo activar el dominio.";
    }
}

// Eliminar dominio - CORREGIDO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain'])) {
    $domain_id = (int)$_POST['domain_id'];
    
    // Obtener info del dominio antes de borrarlo
    $stmt = $db->prepare("SELECT domain FROM custom_domains WHERE id = ? AND user_id = ?");
    $stmt->execute([$domain_id, $user_id]);
    $domain_info = $stmt->fetch();
    
    if ($domain_info) {
        // Primero eliminar cualquier URL que use este dominio
        try {
            $stmt = $db->prepare("UPDATE urls SET domain_id = NULL WHERE domain_id = ?");
            $stmt->execute([$domain_id]);
        } catch (Exception $e) {
            // Ignorar si no existe la columna domain_id
        }
        
        // Ahora eliminar el dominio
        $stmt = $db->prepare("DELETE FROM custom_domains WHERE id = ? AND user_id = ?");
        $stmt->execute([$domain_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "✅ Dominio " . $domain_info['domain'] . " eliminado completamente";
        } else {
            $message = "❌ No se pudo eliminar el dominio";
        }
    } else {
        $message = "❌ Dominio no encontrado o no tienes permiso para eliminarlo";
    }
}

// Obtener dominios según el modo de visualización
$showing_all = false;
if ($is_admin && isset($_GET['show_all'])) {
    // Admin ve todos los dominios SOLO si lo pide explícitamente
    $stmt = $db->query("
        SELECT cd.*, u.username 
        FROM custom_domains cd
        LEFT JOIN users u ON cd.user_id = u.id
        ORDER BY cd.created_at DESC
    ");
    $showing_all = true;
} else {
    // Por defecto, todos ven solo SUS dominios
    $stmt = $db->prepare("SELECT * FROM custom_domains WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
}
$domains = $stmt->fetchAll();

// Contar estadísticas
$stats = [
    'total' => count($domains),
    'active' => 0,
    'pending' => 0,
    'inactive' => 0
];

foreach ($domains as $d) {
    if ($d['status'] === 'active') $stats['active']++;
    elseif ($d['status'] === 'pending') $stats['pending']++;
    else $stats['inactive']++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dominios Personalizados - Acortador URL</title>
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
        }
        .header h1 {
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body {
            padding: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-size: 14px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: #212529; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        .domain-table {
            width: 100%;
            border-collapse: collapse;
        }
        .domain-table th, .domain-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .domain-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .domain-table tr:hover {
            background: #f8f9fa;
        }
        .domain-table tr:target {
            background: #fff3cd;
        }
        .instructions {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 20px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
            word-break: break-all;
            border: 1px solid #ddd;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        .message a {
            color: inherit;
            text-decoration: underline;
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
        .verification-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .method-card {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .method-card h5 {
            margin-bottom: 15px;
            color: #333;
        }
        .method-card ol {
            margin-left: 20px;
        }
        .method-card li {
            margin-bottom: 10px;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-active { background: #28a745; }
        .status-pending { background: #ffc107; }
        .status-inactive { background: #dc3545; }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .view-toggle {
            float: right;
        }
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .alert-help {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .token-box {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .token-value {
            background: #fff;
            padding: 5px 10px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9em;
            word-break: break-all;
        }
        /* Instrucciones destacadas al añadir dominio */
        .new-domain-instructions {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            padding: 25px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .new-domain-instructions h3 {
            color: #2e7d32;
            margin-bottom: 20px;
        }
        .step-number {
            background: #4caf50;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .dns-example {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .dns-example h5 {
            margin-bottom: 10px;
            color: #555;
        }
        .copy-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        .copy-btn:hover {
            background: #0056b3;
        }
        .provider-examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .provider-card {
            background: white;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        .provider-card h6 {
            color: #333;
            margin-bottom: 10px;
        }
        .provider-card ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="simple-menu">
        <div class="simple-menu-container">
            <div class="menu-title">
                🌐 Acortador URL
            </div>
            <div class="menu-links">
                <a href="../">🔗 Acortador</a>
                <a href="panel_simple.php">📊 Panel</a>
                <a href="logout.php">🚪 Salir</a>
            </div>
        </div>
    </div>
    
    <div class="header">
        <h1>🌐 Dominios Personalizados</h1>
        <p>Usa tu propio dominio para acortar URLs</p>
    </div>
    
    <div class="container">
        <div class="back-links">
            <a href="panel_simple.php">← Volver al Panel</a>
            <a href="../">🏠 Ir al Acortador</a>
        </div>
        
        <?php if ($message): ?>
            <?php
            $messageClass = '';
            if (strpos($message, '❌') !== false) $messageClass = 'error';
            elseif (strpos($message, '⚠️') !== false) $messageClass = 'warning';
            ?>
            <div class="message <?php echo $messageClass; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_instructions && $new_domain_info): ?>
        <!-- Instrucciones destacadas para el nuevo dominio -->
        <div class="new-domain-instructions">
            <h3>🎉 ¡Dominio <?php echo htmlspecialchars($new_domain_info['domain']); ?> añadido! Ahora configúralo:</h3>
            
            <div style="margin-bottom: 30px;">
                <h4><span class="step-number">1</span>Configura el DNS de tu dominio</h4>
                <p>Primero, necesitas apuntar tu dominio a nuestro servidor. Ve al panel de control de tu dominio (donde lo compraste) y añade:</p>
                
                <div class="dns-example">
                    <h5>Opción A: Registro CNAME (Recomendado)</h5>
                    <div class="code">
                        Tipo: CNAME<br>
                        Nombre: @ (o www)<br>
                        Destino: <?php echo $_SERVER['HTTP_HOST']; ?>
                        <button class="copy-btn" onclick="copyText('<?php echo $_SERVER['HTTP_HOST']; ?>')">Copiar</button>
                    </div>
                </div>
                
                <div class="dns-example">
                    <h5>Opción B: Registro A</h5>
                    <div class="code">
                        Tipo: A<br>
                        Nombre: @<br>
                        IP: <?php echo $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']) ?? 'Consulta con tu proveedor'; ?>
                        <button class="copy-btn" onclick="copyText('<?php echo $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']); ?>')">Copiar</button>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 30px;">
                <h4><span class="step-number">2</span>Añade el registro TXT para verificación</h4>
                <p>Para confirmar que eres el dueño del dominio, añade este registro TXT:</p>
                
                <div class="dns-example">
                    <h5>Registro TXT de Verificación</h5>
                    <div class="code">
                        Tipo: TXT<br>
                        Nombre: _acortador
                        <button class="copy-btn" onclick="copyText('_acortador')">Copiar</button><br>
                        Valor: <?php echo $new_domain_info['verification_token']; ?>
                        <button class="copy-btn" onclick="copyText('<?php echo $new_domain_info['verification_token']; ?>')">Copiar</button>
                    </div>
                    <p style="margin-top: 10px; font-size: 0.9em; color: #666;">
                        ⚠️ <strong>Importante:</strong> El registro debe quedar como: <code>_acortador.<?php echo $new_domain_info['domain']; ?></code>
                    </p>
                </div>
            </div>
            
            <div class="provider-examples">
                <div class="provider-card">
                    <h6>📘 En GoDaddy:</h6>
                    <ul>
                        <li>Ve a "Administrar DNS"</li>
                        <li>Añade registro → TXT</li>
                        <li>Host: _acortador</li>
                        <li>TXT Value: [token]</li>
                    </ul>
                </div>
                
                <div class="provider-card">
                    <h6>🟠 En Namecheap:</h6>
                    <ul>
                        <li>Advanced DNS</li>
                        <li>Add New Record → TXT</li>
                        <li>Host: _acortador</li>
                        <li>Value: [token]</li>
                    </ul>
                </div>
                
                <div class="provider-card">
                    <h6>☁️ En Cloudflare:</h6>
                    <ul>
                        <li>DNS → Records</li>
                        <li>Add Record → TXT</li>
                        <li>Name: _acortador</li>
                        <li>Content: [token]</li>
                    </ul>
                </div>
                
                <div class="provider-card">
                    <h6>🔵 En otros proveedores:</h6>
                    <ul>
                        <li>Busca "DNS" o "Zona DNS"</li>
                        <li>Crear registro TXT</li>
                        <li>Subdomain: _acortador</li>
                        <li>Valor: [token]</li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-top: 30px; text-align: center;">
                <h4><span class="step-number">3</span>Verifica tu dominio</h4>
                <p>Los cambios DNS pueden tardar entre 5 minutos y 48 horas en propagarse. Una vez configurado:</p>
                
                <button onclick="verificarDominio(<?php echo $new_domain_info['id']; ?>)" 
                        class="btn btn-success btn-lg"
                        id="verify-btn-<?php echo $new_domain_info['id']; ?>">
                    🔍 Verificar Ahora
                </button>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="verify_manual" value="1">
                    <input type="hidden" name="domain_id" value="<?php echo $new_domain_info['id']; ?>">
                    <button type="submit" class="btn btn-warning btn-lg" 
                            title="Activar sin verificación">
                        ⚡ Activar Sin Verificación
                    </button>
                </form>
                
                <p style="margin-top: 15px; color: #666;">
                    💡 <strong>Tip:</strong> Si tienes prisa, puedes usar "Activar Sin Verificación", 
                    pero es recomendable verificar para mayor seguridad.
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            User ID: <?php echo $user_id; ?><br>
            Is Admin: <?php echo $is_admin ? 'Yes' : 'No'; ?><br>
            Showing All: <?php echo $showing_all ? 'Yes' : 'No'; ?><br>
            Total Dominios: <?php echo count($domains); ?><br>
            Server Host: <?php echo $_SERVER['HTTP_HOST']; ?><br>
            Server IP: <?php echo $_SERVER['SERVER_ADDR'] ?? 'N/A'; ?>
            <?php if (count($domains) > 0): ?>
                <br><br><strong>Dominios encontrados:</strong><br>
                <?php foreach ($domains as $d): ?>
                    - ID: <?php echo $d['id']; ?>, 
                    Domain: <?php echo $d['domain']; ?>, 
                    User: <?php echo $d['user_id']; ?><?php echo isset($d['username']) ? ' (' . $d['username'] . ')' : ''; ?>, 
                    Status: <?php echo $d['status']; ?><br>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <?php if (!$showing_all): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-number" style="color: #007bff;"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Dominios</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #28a745;"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #ffc107;"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #dc3545;"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inactivos</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Añadir dominio -->
        <div class="card">
            <div class="card-header">
                ➕ Añadir Nuevo Dominio
            </div>
            <div class="card-body">
                <form method="POST" class="form-inline">
                    <input type="hidden" name="add_domain" value="1">
                    <input type="text" name="domain" class="form-control" style="flex: 1;"
                           placeholder="ejemplo.com (sin http:// ni www)" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Añadir Dominio
                    </button>
                </form>
                <small class="text-muted">Ingresa solo el nombre del dominio, por ejemplo: miempresa.com</small>
            </div>
        </div>
        
        <!-- Lista de dominios -->
        <div class="card">
            <div class="card-header">
                <div>
                    📋 <?php echo $showing_all ? 'Todos los Dominios' : 'Mis Dominios'; ?> (<?php echo count($domains); ?>)
                </div>
                <?php if ($is_admin): ?>
                    <div class="view-toggle">
                        <?php if ($showing_all): ?>
                            <a href="?" class="btn btn-sm btn-info">Ver solo mis dominios</a>
                        <?php else: ?>
                            <a href="?show_all=1" class="btn btn-sm btn-info">Ver todos los dominios</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($domains)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">
                        <?php echo $showing_all ? 'No hay dominios configurados en el sistema.' : 'No tienes dominios personalizados configurados.'; ?>
                    </p>
                    <div class="alert-help">
                        <h5>🚀 ¿Cómo empezar?</h5>
                        <ol>
                            <li>Añade tu dominio usando el formulario de arriba</li>
                            <li>Configura el DNS de tu dominio para que apunte a nuestro servidor</li>
                            <li>Añade el registro TXT para verificar la propiedad</li>
                            <li>¡Listo! Ya puedes crear URLs con tu dominio</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="domain-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Dominio</th>
                                    <?php if ($is_admin && $showing_all): ?>
                                    <th>Usuario</th>
                                    <?php endif; ?>
                                    <th>Estado</th>
                                    <th>Método</th>
                                    <th>Creado</th>
                                    <th>Verificado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domains as $domain): ?>
                                <?php
                                // Verificar si es el dominio del usuario actual
                                $is_my_domain = ($domain['user_id'] == $user_id);
                                ?>
                                <tr id="domain-<?php echo $domain['id']; ?>">
                                    <td><?php echo $domain['id']; ?></td>
                                    <td>
                                        <strong>
                                            <span class="status-indicator status-<?php echo $domain['status']; ?>"></span>
                                            <?php echo htmlspecialchars($domain['domain']); ?>
                                        </strong>
                                        <?php if ($domain['ssl_enabled']): ?>
                                            <span class="badge badge-success">🔒 SSL</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($is_admin && $showing_all): ?>
                                    <td>
                                        <?php echo htmlspecialchars($domain['username'] ?? 'User #' . $domain['user_id']); ?>
                                        <?php if ($is_my_domain): ?>
                                            <span class="badge badge-info">Tú</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($domain['status'] === 'active'): ?>
                                            <span class="badge badge-success">✅ Activo</span>
                                        <?php elseif ($domain['status'] === 'pending'): ?>
                                            <span class="badge badge-warning">⏳ Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">❌ Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $domain['verification_method'] ?? 'file'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($domain['created_at'])); ?></td>
                                    <td>
                                        <?php echo $domain['verified_at'] ? date('d/m/Y', strtotime($domain['verified_at'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_my_domain): ?>
                                            <?php if ($domain['status'] === 'pending'): ?>
                                                <button onclick="toggleInstructions(<?php echo $domain['id']; ?>)" 
                                                        class="btn btn-sm btn-info">
                                                    📋 Instrucciones
                                                </button>
                                                <button onclick="verificarDominio(<?php echo $domain['id']; ?>)" 
                                                        class="btn btn-sm btn-success"
                                                        id="verify-btn-<?php echo $domain['id']; ?>">
                                                    🔍 Verificar
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="verify_manual" value="1">
                                                    <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            title="Activar sin verificación">
                                                        ⚡ Activar
                                                    </button>
                                                </form>
                                            <?php elseif ($domain['status'] === 'inactive'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="reactivate_domain" value="1">
                                                    <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            title="Reactivar dominio">
                                                        🔄 Reactivar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="http://<?php echo $domain['domain']; ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-success">
                                                    🌐 Visitar
                                                </a>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('¿Estás seguro de eliminar el dominio <?php echo htmlspecialchars($domain['domain']); ?>?\n\nEsta acción no se puede deshacer.');">
                                                <input type="hidden" name="delete_domain" value="1">
                                                <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        title="Eliminar dominio">
                                                    🗑️
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Instrucciones (ocultas) - Solo para dominios propios -->
                                <?php if ($is_my_domain): ?>
                                <tr id="instructions-<?php echo $domain['id']; ?>" style="display: none;">
                                    <td colspan="<?php echo ($is_admin && $showing_all) ? '8' : '7'; ?>">
                                        <div class="instructions">
                                            <h4>📋 Configuración para <?php echo $domain['domain']; ?></h4>
                                            
                                            <div class="verification-methods">
                                                <div class="method-card">
                                                    <h5>🚀 Método 1: Archivo (Más rápido)</h5>
                                                    <ol>
                                                        <li>
                                                            <a href="?download_verification=1&domain_id=<?php echo $domain['id']; ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                📥 Descargar archivo de verificación
                                                            </a>
                                                        </li>
                                                        <li>Súbelo a la raíz de tu dominio</li>
                                                        <li>Debe ser accesible en:
                                                            <div class="code">
                                                                http://<?php echo $domain['domain']; ?>/acortador-verify-<?php echo $domain['verification_token']; ?>.txt
                                                            </div>
                                                        </li>
                                                        <li>Haz clic en "Verificar"</li>
                                                    </ol>
                                                </div>
                                                
                                                <div class="method-card">
                                                    <h5>🔐 Método 2: Registro TXT (Verificación DNS)</h5>
                                                    <ol>
                                                        <li>Ve al panel DNS de tu dominio</li>
                                                        <li>Crea un registro TXT:</li>
                                                        <li>
                                                            <div class="code">
                                                                Tipo: TXT<br>
                                                                Nombre: _acortador<br>
                                                                Valor: <?php echo $domain['verification_token']; ?>
                                                            </div>
                                                        </li>
                                                        <li>Espera la propagación DNS (5-60 minutos)</li>
                                                        <li>Haz clic en "Verificar"</li>
                                                    </ol>
                                                    <p><small>Este método es útil si no puedes subir archivos al servidor.</small></p>
                                                </div>
                                                
                                                <div class="method-card">
                                                    <h5>🌐 Método 3: CNAME (Para uso completo)</h5>
                                                    <ol>
                                                        <li>Ve al panel DNS de tu dominio</li>
                                                        <li>Crea un registro CNAME:</li>
                                                        <li>
                                                            <div class="code">
                                                                Tipo: CNAME<br>
                                                                Nombre: @ (o www)<br>
                                                                Destino: <?php echo $_SERVER['HTTP_HOST']; ?>
                                                            </div>
                                                        </li>
                                                        <li>O si prefieres usar un registro A:</li>
                                                        <li>
                                                            <div class="code">
                                                                Tipo: A<br>
                                                                Nombre: @<br>
                                                                IP: <?php echo $_SERVER['SERVER_ADDR'] ?? 'IP del servidor'; ?>
                                                            </div>
                                                        </li>
                                                        <li>Espera propagación (hasta 48h)</li>
                                                    </ol>
                                                    <p><small>Este método es necesario para que las URLs funcionen con tu dominio.</small></p>
                                                </div>
                                            </div>
                                            
                                            <div class="token-box">
                                                <h5>📌 Información importante:</h5>
                                                <ul>
                                                    <li><strong>Token de verificación:</strong> <span class="token-value"><?php echo $domain['verification_token']; ?></span></li>
                                                    <li><strong>Método recomendado:</strong> Registro TXT (no requiere archivos)</li>
                                                    <li><strong>Alternativa rápida:</strong> Usa el botón "⚡ Activar" para activar sin verificación</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Información -->
        <div class="card">
            <div class="card-header">
                ℹ️ Información Importante
            </div>
            <div class="card-body">
                <h5>¿Cómo funciona?</h5>
                <ul style="line-height: 1.8;">
                    <li>Añade tu dominio personalizado</li>
                    <li>Configura el DNS (CNAME o registro A)</li>
                    <li>Verifica la propiedad con registro TXT</li>
                    <li>Una vez activo, podrás usar <code>tudominio.com/codigo</code> para tus URLs</li>
                    <li>Las estadísticas se mantienen separadas por dominio</li>
                </ul>
                
                <h5 style="margin-top: 20px;">Métodos de verificación:</h5>
                <table class="table table-sm">
                    <tr>
                        <td><strong>TXT DNS</strong></td>
                        <td>Añade registro TXT en _acortador.tudominio.com (recomendado)</td>
                    </tr>
                    <tr>
                        <td><strong>Archivo</strong></td>
                        <td>Sube un archivo a tu servidor (requiere acceso FTP)</td>
                    </tr>
                    <tr>
                        <td><strong>Manual</strong></td>
                        <td>Activa sin verificación (menos seguro)</td>
                    </tr>
                </table>
                
                <h5 style="margin-top: 20px;">IP del servidor para registro A:</h5>
                <div class="code">
                    <?php echo $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']) ?? 'Consulta con tu proveedor de hosting'; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function toggleInstructions(id) {
        const row = document.getElementById('instructions-' + id);
        if (row.style.display === 'none') {
            // Cerrar todas las otras instrucciones
            document.querySelectorAll('[id^="instructions-"]').forEach(el => {
                el.style.display = 'none';
            });
            row.style.display = 'table-row';
        } else {
            row.style.display = 'none';
        }
    }
    
    function copyText(text) {
        // Crear un elemento temporal
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'absolute';
        el.style.left = '-9999px';
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        
        // Cambiar el texto del botón temporalmente
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '✅ Copiado!';
        btn.style.background = '#28a745';
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = '#007bff';
        }, 2000);
    }
    
    function verificarDominio(domainId) {
        const btn = document.getElementById('verify-btn-' + domainId);
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Verificando...';
        
        // Crear formulario para verificación AJAX
        const formData = new FormData();
        formData.append('domain_id', domainId);
        formData.append('verify_ajax', '1');
        
        fetch('verificar_dominio_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = '✅ Verificado';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-secondary');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                btn.innerHTML = '❌ No verificado';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 3000);
                if (data.message) {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.innerHTML = '❌ Error';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        });
    }
    
    // Auto-ocultar mensajes
    setTimeout(() => {
        const messages = document.querySelectorAll('.message:not(.new-domain-instructions)');
        messages.forEach(msg => {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                msg.style.display = 'none';
            }, 500);
        });
    }, 5000);
    
    // Si hay un hash en la URL, hacer scroll al dominio
    if (window.location.hash) {
        const element = document.querySelector(window.location.hash);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    </script>
</body>
</html>
