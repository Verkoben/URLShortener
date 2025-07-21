<?php
// Mostrar errores para debugging (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Verificar si está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
// Incluir configuración
$config_file = '../conf.php';
if (!file_exists($config_file)) {
    die("Error: No se encuentra el archivo de configuración conf.php");
}
require_once $config_file;
// Conexión a la base de datos
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
$message = '';
$messageType = 'info';
$section = $_GET['section'] ?? 'dashboard';
// Obtener información del usuario actual
$user_id = $_SESSION['user_id'] ?? 1;
$username = $_SESSION['username'] ?? 'Usuario';
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = ($user_role === 'admin');
// VERIFICACIÓN ESTRICTA: Solo el usuario con ID 1 es superadmin
$is_superadmin = false;
if ($user_id == 1) {
    $is_superadmin = true;
}
// Verificar acceso a dominios - SOLO SUPERADMIN
if ($section == 'domains' && !$is_superadmin) {
    $message = "⛔ Acceso denegado. Solo el superadministrador puede gestionar dominios.";
    $messageType = 'danger';
    $section = 'dashboard'; // Redirigir al dashboard
}

// SECCIÓN DE TOKENS - Procesar acciones
if ($section === 'tokens') {
    // Generar nuevo token
    if (isset($_POST['generate_token'])) {
        $token_name = trim($_POST['token_name']) ?: 'API Token';
        $token = bin2hex(random_bytes(32));
        
        try {
            // Desactivar tokens anteriores si el usuario quiere solo uno activo
            if (isset($_POST['single_token'])) {
                $stmt = $db->prepare("UPDATE api_tokens SET is_active = 0 WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            
            $stmt = $db->prepare("
                INSERT INTO api_tokens (user_id, token, name, permissions, is_active) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $token, $token_name, 'read', 1]);
            
            $message = "✅ Token creado exitosamente. ¡Cópialo ahora, no podrás verlo de nuevo!";
            $messageType = 'success';
            $_SESSION['new_token'] = $token; // Guardar temporalmente para mostrarlo
            
            logActivity($db, $user_id, 'create_token', "Creó token: $token_name");
        } catch (Exception $e) {
            $message = "❌ Error al crear el token: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Eliminar token
    if (isset($_POST['delete_token'])) {
        $token_id = (int)$_POST['token_id'];
        try {
            $stmt = $db->prepare("DELETE FROM api_tokens WHERE id = ? AND user_id = ?");
            $stmt->execute([$token_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✅ Token eliminado correctamente";
                $messageType = 'success';
                logActivity($db, $user_id, 'delete_token', "Eliminó token ID: $token_id");
            }
        } catch (Exception $e) {
            $message = "❌ Error al eliminar el token";
            $messageType = 'danger';
        }
    }
    
    // Activar/Desactivar token
    if (isset($_POST['toggle_token'])) {
        $token_id = (int)$_POST['token_id'];
        try {
            $stmt = $db->prepare("
                UPDATE api_tokens 
                SET is_active = NOT is_active 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$token_id, $user_id]);
            
            $message = "✅ Estado del token actualizado";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "❌ Error al actualizar el token";
            $messageType = 'danger';
        }
    }
}

// NUEVA FUNCIONALIDAD: Procesar regeneración de token API (compatibilidad anterior)
if (isset($_GET['action']) && $_GET['action'] === 'regenerate_token') {
    try {
        // Desactivar token anterior
        $stmt = $db->prepare("UPDATE api_tokens SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Generar nuevo token
        $newToken = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO api_tokens (user_id, token, name) VALUES (?, ?, 'Token Principal')");
        $stmt->execute([$user_id, $newToken]);
        
        $message = "✅ Token API regenerado correctamente";
        $messageType = 'success';
        logActivity($db, $user_id, 'regenerate_token', "Regeneró su token API");
        
        // Redirigir para limpiar la URL
        header('Location: panel_simple.php?section=tokens&msg=token_regenerated');
        exit;
    } catch (Exception $e) {
        $message = "❌ Error al regenerar token: " . $e->getMessage();
        $messageType = 'danger';
    }
}
// Mostrar mensaje si viene de regeneración
if (isset($_GET['msg']) && $_GET['msg'] == 'token_regenerated') {
    $message = "✅ Token API regenerado correctamente";
    $messageType = 'success';
}
// Función para registrar actividades
function logActivity($db, $user_id, $action, $details) {
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Si no existe la tabla, ignorar
    }
}
// Función optimizada para geolocalización con caché
function getGeoLocation($ip, $db) {
    // Primero buscar en caché (tabla click_stats)
    try {
        $stmt = $db->prepare("
            SELECT country, city, latitude, longitude 
            FROM click_stats 
            WHERE ip_address = ? 
            AND country IS NOT NULL 
            LIMIT 1
        ");
        $stmt->execute([$ip]);
        $cached = $stmt->fetch();
        
        if ($cached) {
            return [
                'country' => $cached['country'],
                'city' => $cached['city'],
                'lat' => $cached['latitude'],
                'lon' => $cached['longitude'],
                'flag' => '' // Se calculará después
            ];
        }
    } catch (Exception $e) {
        // Si no existen las columnas, ignorar
    }
    
    // Si no está en caché y no es IP local, consultar API
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null; // IP local, no geolocalizar
    }
    
    // Limitar llamadas a la API (máximo 1 por segundo)
    static $lastCall = 0;
    $now = microtime(true);
    if ($now - $lastCall < 1) {
        return null; // Evitar muchas llamadas seguidas
    }
    $lastCall = $now;
    
    // Consultar API con timeout corto
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 2 // 2 segundos máximo
        ]
    ]);
    
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,city,lat,lon,countryCode";
    $data = @file_get_contents($url, false, $ctx);
    
    if ($data) {
        $json = json_decode($data, true);
        if ($json && $json['status'] === 'success') {
            // Actualizar caché en la base de datos
            try {
                $stmt = $db->prepare("
                    UPDATE click_stats 
                    SET country = ?, city = ?, latitude = ?, longitude = ?
                    WHERE ip_address = ?
                ");
                $stmt->execute([
                    $json['country'],
                    $json['city'],
                    $json['lat'],
                    $json['lon'],
                    $ip
                ]);
            } catch (Exception $e) {
                // Ignorar si no existen las columnas
            }
            
            return [
                'country' => $json['country'] ?? '',
                'city' => $json['city'] ?? '',
                'lat' => $json['lat'] ?? 0,
                'lon' => $json['lon'] ?? 0,
                'flag' => strtolower($json['countryCode'] ?? '')
            ];
        }
    }
    
    return null;
}
// Procesar eliminación de URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_url_id'])) {
    $id = (int)$_POST['delete_url_id'];
    try {
        // Obtener info de la URL antes de eliminar
        $stmt = $db->prepare("SELECT short_code FROM urls WHERE id = ?");
        $stmt->execute([$id]);
        $url_info = $stmt->fetch();
        
        if ($is_admin) {
            $stmt = $db->prepare("DELETE FROM click_stats WHERE url_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("DELETE FROM urls WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("DELETE FROM click_stats WHERE url_id IN (SELECT id FROM urls WHERE id = ? AND user_id = ?)");
            $stmt->execute([$id, $user_id]);
            
            $stmt = $db->prepare("DELETE FROM urls WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
        }
        
        if ($stmt->rowCount() > 0) {
            logActivity($db, $user_id, 'delete_url', "Eliminó URL: " . ($url_info['short_code'] ?? 'ID ' . $id));
            $message = "✅ URL eliminada correctamente";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}
// Crear nueva URL desde el panel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_url'])) {
    $original_url = trim($_POST['original_url']);
    $custom_code = trim($_POST['custom_code']);
    $domain_id = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : null;
    
    if (empty($original_url)) {
        $message = 'La URL es requerida';
        $messageType = 'danger';
    } elseif (!filter_var($original_url, FILTER_VALIDATE_URL)) {
        $message = 'URL inválida';
        $messageType = 'danger';
    } else {
        // Verificar que el usuario tiene permiso para usar este dominio
        if ($domain_id && !$is_admin) {
            $stmt = $db->prepare("
                SELECT id FROM custom_domains 
                WHERE id = ? AND status = 'active' 
                AND (user_id = ? OR user_id IS NULL)
            ");
            $stmt->execute([$domain_id, $user_id]);
            if (!$stmt->fetch()) {
                $message = '❌ No tienes permiso para usar este dominio';
                $messageType = 'danger';
                $domain_id = null;
            }
        }
        
        if ($messageType !== 'danger') {
            if (empty($custom_code)) {
                do {
                    $custom_code = generateShortCode(); // Usando la función de conf.php
                    $stmt = $db->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
                    $stmt->execute([$custom_code]);
                } while ($stmt->fetchColumn() > 0);
            } else {
                // VALIDACIÓN ACTUALIZADA: incluye longitud máxima
                if (!preg_match('/^[a-zA-Z0-9-_]+$/', $custom_code)) {
                    $message = '❌ El código solo puede contener letras, números, guiones y guiones bajos';
                    $messageType = 'danger';
                    $custom_code = '';
                } elseif (strlen($custom_code) > 100) {
                    $message = '❌ El código no puede tener más de 100 caracteres';
                    $messageType = 'danger';
                    $custom_code = '';
                } else {
                    // Verificar que no existe
                    $stmt = $db->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
                    $stmt->execute([$custom_code]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = '❌ Ese código ya está en uso';
                        $messageType = 'danger';
                        $custom_code = '';
                    }
                }
            }
            
            if (!empty($custom_code)) {
                try {
                    $stmt = $db->prepare("INSERT INTO urls (short_code, original_url, user_id, domain_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$custom_code, $original_url, $user_id, $domain_id]);
                    $message = '✅ URL creada exitosamente';
                    $messageType = 'success';
                    logActivity($db, $user_id, 'create_url', "Creó URL: $custom_code");
                } catch (Exception $e) {
                    $message = '❌ Error al crear URL: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}
// Procesar acciones de dominios - SOLO SUPERADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain_action'])) {
    if (!$is_superadmin) {
        $message = "❌ No tienes permisos para gestionar dominios";
        $messageType = 'danger';
    } else {
        switch ($_POST['domain_action']) {
            case 'add':
                $domain = trim($_POST['domain']);
                $domain_user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null;
                
                if (empty($domain)) {
                    $message = "❌ El dominio es requerido";
                    $messageType = 'danger';
                } else {
                    try {
                        $stmt = $db->prepare("INSERT INTO custom_domains (domain, user_id, status, created_at) VALUES (?, ?, 'active', NOW())");
                        $stmt->execute([$domain, $domain_user_id]);
                        
                        $assigned_to = $domain_user_id ? "usuario ID $domain_user_id" : "todos los usuarios";
                        $message = "✅ Dominio añadido y asignado a: $assigned_to";
                        $messageType = 'success';
                        logActivity($db, $user_id, 'add_domain', "Añadió dominio: $domain para $assigned_to");
                    } catch (Exception $e) {
                        $message = "❌ Error al añadir dominio: " . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete':
                $domain_id = (int)$_POST['domain_id'];
                try {
                    // Actualizar URLs que usan este dominio
                    $stmt = $db->prepare("UPDATE urls SET domain_id = NULL WHERE domain_id = ?");
                    $stmt->execute([$domain_id]);
                    
                    // Eliminar dominio
                    $stmt = $db->prepare("DELETE FROM custom_domains WHERE id = ?");
                    $stmt->execute([$domain_id]);
                    
                    $message = "✅ Dominio eliminado correctamente";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "❌ Error al eliminar dominio: " . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'toggle':
                $domain_id = (int)$_POST['domain_id'];
                try {
                    $stmt = $db->prepare("
                        UPDATE custom_domains 
                        SET status = CASE 
                            WHEN status = 'active' THEN 'inactive' 
                            ELSE 'active' 
                        END 
                        WHERE id = ?
                    ");
                    $stmt->execute([$domain_id]);
                    $message = "✅ Estado del dominio actualizado";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "❌ Error al actualizar dominio: " . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update_user':
                $domain_id = (int)$_POST['domain_id'];
                $new_user_id = isset($_POST['new_user_id']) && $_POST['new_user_id'] !== '' ? (int)$_POST['new_user_id'] : null;
                
                try {
                    $stmt = $db->prepare("UPDATE custom_domains SET user_id = ? WHERE id = ?");
                    $stmt->execute([$new_user_id, $domain_id]);
                    
                    $assigned_to = $new_user_id ? "usuario ID $new_user_id" : "todos los usuarios";
                    $message = "✅ Dominio reasignado a: $assigned_to";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "❌ Error al actualizar asignación: " . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Acortador URL</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f6fa;
            color: #2c3e50;
        }
        
        /* Layout principal */
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar mejorado */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .user-info .username {
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .user-info .role {
            font-size: 0.85em;
            opacity: 0.9;
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .superadmin-badge {
            display: inline-block;
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            margin-left: 5px;
            font-weight: bold;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: block;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 0.95em;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 30px;
        }
        
        .nav-item.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        
        .nav-icon {
            margin-right: 10px;
            font-size: 1.1em;
        }
        
        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.2);
            margin: 20px 0;
        }
        
        /* Contenido principal */
        .main-content {
            margin-left: 250px;
            padding: 30px;
            width: 100%;
        }
        
        /* Header del dashboard */
        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease-out;
        }
        
        .dashboard-header h1 {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .breadcrumb {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        /* Cards de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            margin-bottom: 15px;
        }
        
        .stat-icon.blue { background: #e3f2fd; color: #2196f3; }
        .stat-icon.green { background: #e8f5e9; color: #4caf50; }
        .stat-icon.orange { background: #fff3e0; color: #ff9800; }
        .stat-icon.purple { background: #f3e5f5; color: #9c27b0; }
        .stat-icon.red { background: #ffebee; color: #f44336; }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .stat-trend {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 0.85em;
        }
        
        .trend-up { color: #4caf50; }
        .trend-down { color: #f44336; }
        
        /* Tablas mejoradas */
        .data-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            animation: fadeIn 0.5s ease-out;
        }
        
        .data-table h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: all 0.3s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }
        
        /* Botones mejorados */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-success { background: #4caf50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-info { background: #2196f3; color: white; }
        .btn-warning { background: #ff9800; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn-success:hover { background: #45a049; }
        .btn-danger:hover { background: #da190b; }
        .btn-info:hover { background: #0b7dda; }
        .btn-warning:hover { background: #e68900; }
        .btn-secondary:hover { background: #5a6268; }
        
        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-primary { background: #e3f2fd; color: #2196f3; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #ff9800; }
        .badge-danger { background: #ffebee; color: #f44336; }
        .badge-secondary { background: #e9ecef; color: #6c757d; }
        .badge-info { background: #e1f5fe; color: #0288d1; }
        
        /* Formularios */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* Search Form */
        .search-form {
            margin-bottom: 0;
        }
        
        .search-form .input-group {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }
        
        .search-form .form-control {
            flex: 1;
            min-width: 200px;
        }
        
        /* Highlight search results */
        mark {
            background: #fff59d !important;
            padding: 0 4px;
            border-radius: 3px;
            font-weight: 500;
        }
        
        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }
        
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* URL display */
        .url-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .url-display input {
            background: none;
            border: none;
            flex: 1;
            font-family: monospace;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: #5a67d8;
        }
        
        .copy-btn.copied {
            background: #4caf50;
        }
        
        /* Quick actions */
        .quick-actions {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
        }
        
        .quick-actions h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        /* Token API section */
        .token-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            border: 2px solid #e3f2fd;
        }
        
        .token-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .token-header h3 {
            color: #2c3e50;
            margin: 0;
        }
        
        .token-box {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .token-display {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
            color: #495057;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
        }
        
        .token-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .token-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .token-info h4 {
            color: #1565c0;
            margin-bottom: 15px;
        }
        
        .token-info ol {
            margin-left: 20px;
            color: #495057;
            line-height: 1.8;
        }
        
        .token-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Token Cards */
        .tokens-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .token-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            position: relative;
            transition: all 0.3s;
        }
        
        .token-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.1);
        }
        
        .token-card.active {
            border-color: #4caf50;
        }
        
        .token-card.inactive {
            opacity: 0.7;
            border-color: #f44336;
        }
        
        .token-status {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #f44336;
        }
        
        .token-status.active {
            background: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .token-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .token-details {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .token-preview {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.85em;
            color: #495057;
            margin-bottom: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .new-token-display {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .new-token-display h4 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        
        .new-token-display .token-value {
            background: white;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            word-break: break-all;
            margin-bottom: 10px;
        }
        
        /* Ejemplo de código */
        .code-example {
            background: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .code-example pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        /* Mapa */
        #geo-map {
            height: 500px;
            width: 100%;
            border-radius: 10px;
            margin-top: 20px;
            background: #f0f0f0;
        }
        
        /* Geolocalización */
        .geo-info {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        
        .flag-icon {
            width: 20px;
            height: 15px;
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header h1 {
                font-size: 1.5em;
            }
            
            .token-actions {
                flex-direction: column;
            }
            
            .token-actions .btn {
                width: 100%;
            }
            
            .tokens-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state h4 {
            margin: 20px 0 10px 0;
            color: #495057;
        }
        
        /* Tooltips */
        .tooltip {
            position: relative;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 5px 10px;
            border-radius: 6px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Geo Stats */
        .geo-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .geo-stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .geo-stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .geo-stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        /* Domain status */
        .domain-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-dot.active {
            background: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .status-dot.inactive {
            background: #f44336;
            box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.2);
        }
        
        /* Domain form */
        .domain-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .domain-form {
                grid-template-columns: 1fr;
            }
        }
        
        /* Domain assignment info */
        .domain-assignment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .assignment-rule {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        
        .assignment-rule .icon {
            font-size: 1.2em;
        }
        
        .mini-form {
            display: inline-flex;
            gap: 10px;
            align-items: center;
        }
        
        .mini-select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9em;
        }
        
        /* Input group para búsqueda */
        .input-group {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }
        
        .input-group .form-control {
            flex: 1;
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        ☰
    </button>
    
    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>🚀 URL Shortener</h3>
                <div class="user-info">
                    <div class="username">
                        <?php echo htmlspecialchars($username); ?>
                        <?php if ($is_superadmin): ?>
                        <span class="superadmin-badge">SUPER</span>
                        <?php endif; ?>
                    </div>
                    <span class="role"><?php echo $is_admin ? '👑 Administrador' : '👤 Usuario'; ?></span>
                </div>
            </div>
            
            <div class="nav-menu">
                <a href="?section=dashboard" class="nav-item <?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="?section=urls" class="nav-item <?php echo $section === 'urls' ? 'active' : ''; ?>">
                    <span class="nav-icon">🔗</span> <?php echo $is_admin ? 'Gestión URLs' : 'Mis URLs'; ?>
                </a>
                <a href="?section=tokens" class="nav-item <?php echo $section === 'tokens' ? 'active' : ''; ?>">
                    <span class="nav-icon">🔑</span> Tokens API
                </a>
                <a href="?section=stats" class="nav-item <?php echo $section === 'stats' ? 'active' : ''; ?>">
                    <span class="nav-icon">📈</span> Estadísticas
                </a>
                <a href="?section=geo" class="nav-item <?php echo $section === 'geo' ? 'active' : ''; ?>">
                    <span class="nav-icon">🌍</span> Geolocalización
                </a>
                
                <div class="nav-divider"></div>
                
                <?php if ($is_superadmin): ?>
                <!-- SOLO EL SUPERADMIN VE LA OPCIÓN DE DOMINIOS -->
                <a href="?section=domains" class="nav-item <?php echo $section === 'domains' ? 'active' : ''; ?>">
                    <span class="nav-icon">🌐</span> Dominios
                    <span class="superadmin-badge" style="margin-left: auto;">SUPER</span>
                </a>
                <?php endif; ?>
                
                <?php if ($is_admin): ?>
                <!-- TODOS LOS ADMINS VEN USUARIOS -->
                <a href="usuarios.php" class="nav-item">
                    <span class="nav-icon">👥</span> Usuarios
                </a>
                <?php endif; ?>
                
                <div class="nav-divider"></div>
                <a href="../" class="nav-item">
                    <span class="nav-icon">🏠</span> Ir al Sitio
                </a>
                <a href="logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span> Cerrar Sesión
                </a>
            </div>
        </nav>
        
        <!-- Contenido Principal -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1>
                    <?php 
                    switch($section) {
                        case 'dashboard': echo '📊 Dashboard'; break;
                        case 'urls': echo '🔗 ' . ($is_admin ? 'Gestión de URLs' : 'Mis URLs'); break;
                        case 'tokens': echo '🔑 Tokens API'; break;
                        case 'stats': echo '📈 Estadísticas'; break;
                        case 'geo': echo '🌍 Geolocalización'; break;
                        case 'domains': echo '🌐 Gestión de Dominios'; break;
                        default: echo '📊 Dashboard';
                    }
                    ?>
                </h1>
                <div class="breadcrumb">
                    📅 <?php echo date('l, d F Y'); ?> • 
                    🕐 <?php echo date('H:i'); ?>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Dashboard -->
            <?php if ($section === 'dashboard'): ?>
                <?php
                // Obtener estadísticas
                try {
                    if ($is_admin) {
                        $stmt = $db->query("SELECT COUNT(*) as total FROM urls");
                        $total_urls = $stmt->fetch()['total'];
                        
                        $stmt = $db->query("SELECT SUM(clicks) as total FROM urls");
                        $total_clicks = $stmt->fetch()['total'] ?? 0;
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
                        $total_users = $stmt->fetch()['total'];
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
                        $active_users = $stmt->fetch()['total'];
                        
                        // URLs hoy
                        $stmt = $db->query("SELECT COUNT(*) as total FROM urls WHERE DATE(created_at) = CURDATE()");
                        $today_urls = $stmt->fetch()['total'];
                        
                        // Clicks hoy
                        $stmt = $db->query("SELECT COUNT(*) as total FROM click_stats WHERE DATE(clicked_at) = CURDATE()");
                        $today_clicks = $stmt->fetch()['total'];
                    } else {
                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM urls WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $total_urls = $stmt->fetch()['total'];
                        
                        $stmt = $db->prepare("SELECT SUM(clicks) as total FROM urls WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $total_clicks = $stmt->fetch()['total'] ?? 0;
                        
                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM urls WHERE user_id = ? AND DATE(created_at) = CURDATE()");
                        $stmt->execute([$user_id]);
                        $today_urls = $stmt->fetch()['total'];
                        
                        $today_clicks = 0;
                    }
                    
                    $avg_clicks = $total_urls > 0 ? round($total_clicks / $total_urls, 1) : 0;
                } catch (Exception $e) {
                    // Valores por defecto si hay error
                    $total_urls = 0;
                    $total_clicks = 0;
                    $total_users = 0;
                    $active_users = 0;
                    $today_urls = 0;
                    $today_clicks = 0;
                    $avg_clicks = 0;
                }
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            🔗
                        </div>
                        <div class="stat-value"><?php echo number_format($total_urls); ?></div>
                        <div class="stat-label"><?php echo $is_admin ? 'URLs Totales' : 'Mis URLs'; ?></div>
                        <?php if ($today_urls > 0): ?>
                        <span class="stat-trend trend-up">
                            ⬆️ +<?php echo $today_urls; ?> hoy
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            👆
                        </div>
                        <div class="stat-value"><?php echo number_format($total_clicks); ?></div>
                        <div class="stat-label"><?php echo $is_admin ? 'Clicks Totales' : 'Mis Clicks'; ?></div>
                        <?php if ($today_clicks > 0): ?>
                        <span class="stat-trend trend-up">
                            ⬆️ +<?php echo $today_clicks; ?> hoy
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            📈
                        </div>
                        <div class="stat-value"><?php echo $avg_clicks; ?></div>
                        <div class="stat-label">Promedio Clicks/URL</div>
                    </div>
                    
                    <?php if ($is_admin): ?>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            👥
                        </div>
                        <div class="stat-value"><?php echo number_format($total_users); ?></div>
                        <div class="stat-label">Usuarios Totales</div>
                        <span class="badge badge-success" style="position: absolute; top: 20px; right: 20px;">
                            <?php echo $active_users; ?> activos
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3>🚀 Acciones Rápidas</h3>
                    <div class="action-grid">
                        <a href="../" class="btn btn-success">
                            ➕ Nueva URL
                        </a>
                        <a href="?section=urls" class="btn btn-primary">
                            🔗 Ver URLs
                        </a>
                        <a href="?section=tokens" class="btn btn-warning">
                            🔑 Mis Tokens API
                        </a>
                        <a href="?section=stats" class="btn btn-info">
                            📊 Estadísticas
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="usuarios.php" class="btn btn-secondary">
                            👥 Usuarios
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top URLs -->
                <div class="data-table">
                    <h3>
                        <span>🏆 Top 5 URLs más visitadas</span>
                    </h3>
                    <?php
                    try {
                        if ($is_admin) {
                            $stmt = $db->query("
                                SELECT u.*, cd.domain as custom_domain 
                                FROM urls u 
                                LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
                                ORDER BY u.clicks DESC 
                                LIMIT 5
                            ");
                        } else {
                            $stmt = $db->prepare("
                                SELECT u.*, cd.domain as custom_domain 
                                FROM urls u 
                                LEFT JOIN custom_domains cd ON u.domain_id = cd.id 
                                WHERE u.user_id = ? 
                                ORDER BY u.clicks DESC 
                                LIMIT 5
                            ");
                            $stmt->execute([$user_id]);
                        }
                        $top_urls = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $top_urls = [];
                    }
                    ?>
                    
                    <?php if ($top_urls): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>URL Original</th>
                                <th>URL Corta</th>
                                <th>Clicks</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_urls as $url): ?>
                            <?php
                            // Determinar URL correcta
                            if (!empty($url['custom_domain'])) {
                                $short_url_display = "https://" . $url['custom_domain'] . "/" . $url['short_code'];
                            } else {
                                $short_url_display = rtrim(BASE_URL, '/') . '/' . $url['short_code'];
                            }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo htmlspecialchars($url['original_url']); ?>" 
                                       target="_blank" style="color: #667eea; text-decoration: none;">
                                        <?php echo htmlspecialchars(substr($url['original_url'], 0, 50)) . '...'; ?>
                                    </a>
                                </td>
                                <td>
                                    <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">
                                        <?php echo $short_url_display; ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        👆 <?php echo number_format($url['clicks']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../stats.php?code=<?php echo $url['short_code']; ?>" 
                                       class="btn btn-sm btn-info tooltip">
                                        📊
                                        <span class="tooltiptext">Ver estadísticas</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <span style="font-size: 4em;">🔗</span>
                        <h4>No hay URLs todavía</h4>
                        <p>Crea tu primera URL corta</p>
                        <a href="../" class="btn btn-primary">
                            ➕ Crear URL
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
            <!-- Tokens API -->
            <?php elseif ($section === 'tokens'): ?>
                <?php
                // Obtener tokens del usuario
                $stmt = $db->prepare("
                    SELECT * FROM api_tokens 
                    WHERE user_id = ? 
                    ORDER BY is_active DESC, created_at DESC
                ");
                $stmt->execute([$user_id]);
                $tokens = $stmt->fetchAll();
                
                // Mostrar nuevo token si se acaba de crear
                $show_new_token = isset($_SESSION['new_token']);
                $new_token = $_SESSION['new_token'] ?? '';
                unset($_SESSION['new_token']);
                ?>
                
                <?php if ($show_new_token): ?>
                <div class="new-token-display">
                    <h4>🎉 ¡Nuevo Token Creado!</h4>
                    <div class="token-value">
                        <?php echo htmlspecialchars($new_token); ?>
                    </div>
                    <div class="token-actions">
                        <button class="btn btn-success" onclick="copyNewToken('<?php echo $new_token; ?>')">
                            📋 Copiar Token
                        </button>
                    </div>
                    <div class="token-warning">
                        <span>⚠️</span>
                        <div>
                            <strong>¡Importante!</strong> Este es el único momento en que verás este token completo. 
                            Cópialo ahora y guárdalo en un lugar seguro.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Crear Token -->
                <div class="token-section">
                    <div class="token-header">
                        <h3>🔑 Crear Nuevo Token API</h3>
                        <span class="badge badge-info">Para extensión o API</span>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Nombre del Token:</label>
                            <input type="text" name="token_name" class="form-control" 
                                   placeholder="Ej: Extensión Chrome, API Personal, etc." 
                                   maxlength="100">
                            <small style="color: #7f8c8d;">Dale un nombre descriptivo para identificarlo después</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="single_token" value="1">
                                <span>Mantener solo un token activo (desactiva los anteriores)</span>
                            </label>
                        </div>
                        
                        <button type="submit" name="generate_token" class="btn btn-primary">
                            🔑 Generar Nuevo Token
                        </button>
                    </form>
                </div>
                
                <!-- Lista de Tokens -->
                <div class="data-table">
                    <h3>
                        <span>📋 Mis Tokens API</span>
                        <span class="badge badge-primary"><?php echo count($tokens); ?></span>
                    </h3>
                    
                    <?php if (empty($tokens)): ?>
                    <div class="empty-state">
                        <span style="font-size: 4em;">🔐</span>
                        <h4>No tienes tokens activos</h4>
                        <p>Crea un token para usar la API o la extensión del navegador</p>
                    </div>
                    <?php else: ?>
                    <div class="tokens-grid">
                        <?php foreach ($tokens as $token): ?>
                        <div class="token-card <?php echo $token['is_active'] ? 'active' : 'inactive'; ?>">
                            <div class="token-status <?php echo $token['is_active'] ? 'active' : ''; ?>"></div>
                            <div class="token-name">
                                🔑 <?php echo htmlspecialchars($token['name']); ?>
                            </div>
                            <div class="token-details">
                                <div>📅 Creado: <?php echo date('d/m/Y H:i', strtotime($token['created_at'])); ?></div>
                                <div>🕐 Último uso: <?php echo $token['last_used'] ? date('d/m/Y H:i', strtotime($token['last_used'])) : 'Nunca'; ?></div>
                                <div>📊 Permisos: <span class="badge badge-info"><?php echo $token['permissions']; ?></span></div>
                                <div>✅ Estado: <span class="badge badge-<?php echo $token['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $token['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                </span></div>
                            </div>
                            <div class="token-preview">
                                <?php echo substr($token['token'], 0, 20); ?>...
                            </div>
                            <div class="token-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                    <button type="submit" name="toggle_token" 
                                            class="btn btn-sm btn-<?php echo $token['is_active'] ? 'warning' : 'success'; ?>">
                                        <?php echo $token['is_active'] ? '⏸️ Desactivar' : '▶️ Activar'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('¿Eliminar este token? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                    <button type="submit" name="delete_token" class="btn btn-sm btn-danger">
                                        🗑️ Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Instrucciones de Uso -->
                <div class="token-info">
                    <h4>📖 Cómo usar los tokens API</h4>
                    
                    <h5 style="margin-top: 20px;">🧩 En la extensión del navegador:</h5>
                    <ol>
                        <li>Instala la extensión "URL Shortener" desde Chrome Web Store</li>
                        <li>Haz clic en el icono de la extensión</li>
                        <li>Ve a configuración y pega tu token</li>
                        <li>¡Listo! Ya puedes acortar URLs desde cualquier página</li>
                    </ol>
                    
                    <h5 style="margin-top: 20px;">💻 En peticiones HTTP:</h5>
                    <div class="code-example">
                        <pre>// Opción 1: Authorization Header (Recomendado)
Authorization: Bearer TU_TOKEN_AQUI

// Opción 2: Custom Header
X-API-Token: TU_TOKEN_AQUI</pre>
                    </div>
                    
                    <h5 style="margin-top: 20px;">📡 Ejemplos de uso:</h5>
                    <div class="code-example">
                        <pre>// JavaScript/Fetch
fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/my-urls.php', {
    headers: {
        'Authorization': 'Bearer TU_TOKEN_AQUI'
    }
})
.then(response => response.json())
.then(data => console.log(data));

// cURL
curl -H "Authorization: Bearer TU_TOKEN_AQUI" \
     https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/my-urls.php

// Python
import requests
headers = {'Authorization': 'Bearer TU_TOKEN_AQUI'}
response = requests.get('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/my-urls.php', headers=headers)</pre>
                    </div>
                </div>
                
                <!-- Información de la API -->
                <div class="data-table" style="margin-top: 30px;">
                    <h3>🔌 Endpoints de la API</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Método</th>
                                <th>Descripción</th>
                                <th>Respuesta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>/api/my-urls.php</code></td>
                                <td><span class="badge badge-success">GET</span></td>
                                <td>Obtener todas tus URLs</td>
                                <td>Array de URLs en JSON</td>
                            </tr>
                            <tr>
                                <td><code>/api/shorten.php</code></td>
                                <td><span class="badge badge-warning">POST</span></td>
                                <td>Crear nueva URL corta</td>
                                <td>Objeto con URL creada</td>
                            </tr>
                            <tr>
                                <td><code>/api/delete-url.php</code></td>
                                <td><span class="badge badge-danger">DELETE</span></td>
                                <td>Eliminar una URL</td>
                                <td>Confirmación de éxito</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <script>
                function copyNewToken(token) {
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(token).then(function() {
                            showToast('✅ Token copiado al portapapeles');
                        });
                    } else {
                        // Fallback
                        const textArea = document.createElement('textarea');
                        textArea.value = token;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-999999px';
                        document.body.appendChild(textArea);
                        textArea.select();
                        try {
                            document.execCommand('copy');
                            showToast('✅ Token copiado al portapapeles');
                        } catch (err) {
                            alert('Error al copiar. Selecciona y copia manualmente.');
                        }
                        document.body.removeChild(textArea);
                    }
                }
                
                function showToast(message) {
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-success';
                    toast.style.position = 'fixed';
                    toast.style.top = '20px';
                    toast.style.right = '20px';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = message;
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(-20px)';
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                }
                </script>
            
            <!-- Gestión de URLs CON BUSCADOR -->
            <?php elseif ($section === 'urls'): ?>
                <?php
                // CONSULTA MODIFICADA: Solo mostrar dominios disponibles para el usuario actual
                $available_domains = [];
                try {
                    if ($is_superadmin) {
                        // El superadmin ve todos los dominios activos
                        $stmt = $db->query("SELECT id, domain FROM custom_domains WHERE status = 'active' ORDER BY domain");
                    } else {
                        // Los demás usuarios solo ven dominios asignados a ellos o sin asignar
                        $stmt = $db->prepare("
                            SELECT id, domain 
                            FROM custom_domains 
                            WHERE status = 'active' 
                            AND (user_id = ? OR user_id IS NULL) 
                            ORDER BY domain
                        ");
                        $stmt->execute([$user_id]);
                    }
                    $available_domains = $stmt->fetchAll();
                } catch (Exception $e) {
                    // Ignorar si no existe la tabla
                }
                ?>
                
                <!-- NUEVO: Buscador de URLs -->
                <div class="data-table" style="margin-bottom: 30px;">
                    <h3>🔍 Buscar URLs</h3>
                    <form method="GET" action="" class="search-form">
                        <input type="hidden" name="section" value="urls">
                        <div class="input-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Buscar por URL original, código corto o dominio..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   style="flex: 1;">
                            <button type="submit" class="btn btn-primary">
                                🔍 Buscar
                            </button>
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <a href="?section=urls" class="btn btn-secondary">
                                ❌ Limpiar
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <div style="margin-top: 10px; color: #6c757d;">
                            Buscando: "<strong><?php echo htmlspecialchars($_GET['search']); ?></strong>"
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Formulario para crear nueva URL -->
                <div class="data-table" style="margin-bottom: 30px;">
                    <h3>➕ Crear nueva URL</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="create_url" value="1">
                        <div class="form-group">
                            <label class="form-label">URL Original:</label>
                            <input type="url" name="original_url" class="form-control" 
                                   placeholder="https://ejemplo.com/pagina-muy-larga" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código personalizado (opcional):</label>
                            <input type="text" name="custom_code" class="form-control" 
                                   placeholder="mi-codigo" 
                                   pattern="[a-zA-Z0-9-_]+"
                                   maxlength="100">
                            <small style="color: #7f8c8d;">Deja vacío para generar automáticamente (máximo 100 caracteres)</small>
                        </div>
                        <?php if (!empty($available_domains)): ?>
                        <div class="form-group">
                            <label class="form-label">Dominio:</label>
                            <select name="domain_id" class="form-select">
                                <option value="">Dominio principal (<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?>)</option>
                                <?php foreach ($available_domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>">
                                    <?php echo htmlspecialchars($domain['domain']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            ➕ Crear URL
                        </button>
                    </form>
                </div>
                
                <?php
                // Obtener URLs CON BÚSQUEDA
                $search_term = $_GET['search'] ?? '';
                $urls = [];
                $total_results = 0;
                
                try {
                    if ($is_admin) {
                        if (!empty($search_term)) {
                            // Búsqueda para admin
                            $search_param = '%' . $search_term . '%';
                            $stmt = $db->prepare("
                                SELECT u.*, users.username, cd.domain as custom_domain,
                                       COUNT(*) OVER() as total_count
                                FROM urls u
                                LEFT JOIN users ON u.user_id = users.id
                                LEFT JOIN custom_domains cd ON u.domain_id = cd.id
                                WHERE u.original_url LIKE ? 
                                   OR u.short_code LIKE ?
                                   OR cd.domain LIKE ?
                                ORDER BY u.created_at DESC 
                                LIMIT 100
                            ");
                            $stmt->execute([$search_param, $search_param, $search_param]);
                        } else {
                            // Sin búsqueda - mostrar todas
                            $stmt = $db->query("
                                SELECT u.*, users.username, cd.domain as custom_domain
                                FROM urls u
                                LEFT JOIN users ON u.user_id = users.id
                                LEFT JOIN custom_domains cd ON u.domain_id = cd.id
                                ORDER BY u.created_at DESC 
                                LIMIT 100
                            ");
                        }
                    } else {
                        if (!empty($search_term)) {
                            // Búsqueda para usuario normal
                            $search_param = '%' . $search_term . '%';
                            $stmt = $db->prepare("
                                SELECT u.*, users.username, cd.domain as custom_domain,
                                       COUNT(*) OVER() as total_count
                                FROM urls u
                                LEFT JOIN users ON u.user_id = users.id
                                LEFT JOIN custom_domains cd ON u.domain_id = cd.id
                                WHERE u.user_id = ?
                                  AND (u.original_url LIKE ? 
                                       OR u.short_code LIKE ?
                                       OR cd.domain LIKE ?)
                                ORDER BY u.created_at DESC 
                                LIMIT 100
                            ");
                            $stmt->execute([$user_id, $search_param, $search_param, $search_param]);
                        } else {
                            // Sin búsqueda - mostrar solo las del usuario
                            $stmt = $db->prepare("
                                SELECT u.*, users.username, cd.domain as custom_domain
                                FROM urls u
                                LEFT JOIN users ON u.user_id = users.id
                                LEFT JOIN custom_domains cd ON u.domain_id = cd.id
                                WHERE u.user_id = ?
                                ORDER BY u.created_at DESC 
                                LIMIT 100
                            ");
                            $stmt->execute([$user_id]);
                        }
                    }
                    $urls = $stmt->fetchAll();
                    
                    // Obtener el total de resultados si hay búsqueda
                    if (!empty($search_term) && !empty($urls)) {
                        $total_results = $urls[0]['total_count'] ?? count($urls);
                    }
                } catch (Exception $e) {
                    $urls = [];
                    $message = "Error en la búsqueda: " . $e->getMessage();
                    $messageType = 'danger';
                }
                ?>
                
                <div class="data-table">
                    <h3>
                        <span>🔗 <?php echo $is_admin ? 'Todas las URLs' : 'Mis URLs'; ?></span>
                        <?php if (!empty($search_term)): ?>
                            <span class="badge badge-info"><?php echo $total_results; ?> resultados</span>
                        <?php else: ?>
                            <span class="badge badge-primary"><?php echo count($urls); ?></span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if ($urls): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>URL Original</th>
                                <th>URL Corta</th>
                                <th>Dominio</th>
                                <?php if ($is_admin): ?>
                                <th>Usuario</th>
                                <?php endif; ?>
                                <th>Clicks</th>
                                <th>Creada</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($urls as $url): ?>
                            <?php
                            // Determinar la URL corta completa basándose en el dominio
                            if (!empty($url['custom_domain'])) {
                                $short_url = "https://" . $url['custom_domain'] . "/" . $url['short_code'];
                                $domain_display = $url['custom_domain'];
                                $domain_badge_class = 'badge-success';
                            } else {
                                $short_url = rtrim(BASE_URL, '/') . '/' . $url['short_code'];
                                $domain_display = parse_url(BASE_URL, PHP_URL_HOST);
                                $domain_badge_class = 'badge-secondary';
                            }
                            
                            // RESALTAR coincidencias de búsqueda
                            if (!empty($search_term)) {
                                $highlighted_url = str_ireplace(
                                    $search_term, 
                                    '<mark style="background: yellow; padding: 0 2px;">' . htmlspecialchars($search_term) . '</mark>', 
                                    htmlspecialchars($url['original_url'])
                                );
                                $highlighted_code = str_ireplace(
                                    $search_term, 
                                    '<mark style="background: yellow; padding: 0 2px;">' . htmlspecialchars($search_term) . '</mark>', 
                                    htmlspecialchars($url['short_code'])
                                );
                            } else {
                                $highlighted_url = htmlspecialchars($url['original_url']);
                                $highlighted_code = htmlspecialchars($url['short_code']);
                            }
                            ?>
                            <tr>
                                <td><?php echo $url['id']; ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($url['original_url']); ?>" 
                                       target="_blank" 
                                       style="color: #667eea; text-decoration: none; display: block; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                       title="<?php echo htmlspecialchars($url['original_url']); ?>">
                                        <?php echo $highlighted_url; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="url-display">
                                        <input type="text" value="<?php echo $short_url; ?>" 
                                               id="url-<?php echo $url['id']; ?>" readonly>
                                        <button class="copy-btn" onclick="copyUrl('url-<?php echo $url['id']; ?>', this)">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $domain_badge_class; ?>">
                                        🌐 <?php echo $domain_display; ?>
                                    </span>
                                </td>
                                <?php if ($is_admin): ?>
                                <td>
                                    <span class="badge badge-secondary">
                                        👤 <?php echo $url['username'] ?? 'Sistema'; ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge badge-success">
                                        👆 <?php echo number_format($url['clicks'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($url['created_at'])); ?></small>
                                </td>
                                <td>
                                    <a href="../stats.php?code=<?php echo $url['short_code']; ?>" 
                                       class="btn btn-sm btn-info tooltip">
                                        📊
                                        <span class="tooltiptext">Estadísticas</span>
                                    </a>
                                    <a href="<?php echo $short_url; ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-success tooltip">
                                        🔗
                                        <span class="tooltiptext">Abrir</span>
                                    </a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Eliminar esta URL?');">
                                        <input type="hidden" name="delete_url_id" value="<?php echo $url['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger tooltip">
                                            🗑️
                                            <span class="tooltiptext">Eliminar</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (!empty($search_term) && $total_results > 100): ?>
                    <div class="alert alert-info" style="margin-top: 20px;">
                        ℹ️ Mostrando los primeros 100 resultados de <?php echo $total_results; ?> encontrados. 
                        Refina tu búsqueda para ver resultados más específicos.
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <span style="font-size: 4em;">
                            <?php echo !empty($search_term) ? '🔍' : '🔗'; ?>
                        </span>
                        <h4>
                            <?php echo !empty($search_term) 
                                ? 'No se encontraron URLs que coincidan con "' . htmlspecialchars($search_term) . '"' 
                                : 'No hay URLs registradas'; ?>
                        </h4>
                        <p>
                            <?php echo !empty($search_term) 
                                ? 'Intenta con otros términos de búsqueda' 
                                : 'Crea tu primera URL corta usando el formulario de arriba'; ?>
                        </p>
                        <?php if (!empty($search_term)): ?>
                        <a href="?section=urls" class="btn btn-primary" style="margin-top: 20px;">
                            ← Volver a todas las URLs
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            
            <!-- Estadísticas -->
            <?php elseif ($section === 'stats'): ?>
                <?php
                // Estadísticas por día
                try {
                    if ($is_admin) {
                        $stmt = $db->query("
                            SELECT DATE(clicked_at) as date, COUNT(*) as clicks
                            FROM click_stats
                            WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            GROUP BY DATE(clicked_at)
                            ORDER BY date DESC
                        ");
                    } else {
                        $stmt = $db->prepare("
                            SELECT DATE(cs.clicked_at) as date, COUNT(*) as clicks
                            FROM click_stats cs
                            INNER JOIN urls u ON cs.url_id = u.id
                            WHERE u.user_id = ? AND cs.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            GROUP BY DATE(cs.clicked_at)
                            ORDER BY date DESC
                        ");
                        $stmt->execute([$user_id]);
                    }
                    $daily_stats = $stmt->fetchAll();
                } catch (Exception $e) {
                    $daily_stats = [];
                }
                ?>
                
                <div class="data-table">
                    <h3>📈 Clicks últimos 7 días</h3>
                    <?php if ($daily_stats): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Clicks</th>
                                <th>Gráfico</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $max_clicks = max(array_column($daily_stats, 'clicks'));
                            foreach ($daily_stats as $stat):
                                $percentage = $max_clicks > 0 ? ($stat['clicks'] / $max_clicks) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    📅 <?php echo date('d/m/Y', strtotime($stat['date'])); ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo number_format($stat['clicks']); ?> clicks
                                    </span>
                                </td>
                                <td>
                                    <div style="background: #e3f2fd; height: 25px; border-radius: 12px; overflow: hidden; position: relative;">
                                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                                    width: <?php echo $percentage; ?>%; 
                                                    height: 100%; 
                                                    transition: width 0.5s;
                                                    display: flex;
                                                    align-items: center;
                                                    justify-content: flex-end;
                                                    padding-right: 10px;">
                                            <?php if ($percentage > 20): ?>
                                            <span style="color: white; font-size: 12px; font-weight: bold;">
                                                <?php echo round($percentage); ?>%
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <span style="font-size: 4em;">📊</span>
                        <h4>No hay datos de los últimos 7 días</h4>
                        <p>Las estadísticas aparecerán cuando tus URLs reciban clicks</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Estadísticas por navegador -->
                <?php
                try {
                    if ($is_admin) {
                        $stmt = $db->query("
                            SELECT 
                                CASE 
                                    WHEN user_agent LIKE '%Chrome%' THEN 'Chrome'
                                    WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                                    WHEN user_agent LIKE '%Safari%' THEN 'Safari'
                                    WHEN user_agent LIKE '%Edge%' THEN 'Edge'
                                    ELSE 'Otro'
                                END as browser,
                                COUNT(*) as count
                            FROM click_stats
                            GROUP BY browser
                            ORDER BY count DESC
                        ");
                    } else {
                        $stmt = $db->prepare("
                            SELECT 
                                CASE 
                                    WHEN cs.user_agent LIKE '%Chrome%' THEN 'Chrome'
                                    WHEN cs.user_agent LIKE '%Firefox%' THEN 'Firefox'
                                    WHEN cs.user_agent LIKE '%Safari%' THEN 'Safari'
                                    WHEN cs.user_agent LIKE '%Edge%' THEN 'Edge'
                                    ELSE 'Otro'
                                END as browser,
                                COUNT(*) as count
                            FROM click_stats cs
                            INNER JOIN urls u ON cs.url_id = u.id
                            WHERE u.user_id = ?
                            GROUP BY browser
                            ORDER BY count DESC
                        ");
                        $stmt->execute([$user_id]);
                    }
                    $browser_stats = $stmt->fetchAll();
                } catch (Exception $e) {
                    $browser_stats = [];
                }
                ?>
                
                <?php if ($browser_stats): ?>
                <div class="data-table">
                    <h3>🌐 Estadísticas por Navegador</h3>
                    <div class="stats-grid">
                        <?php 
                        $browser_emojis = [
                            'Chrome' => '🔵',
                            'Firefox' => '🦊',
                            'Safari' => '🧭',
                            'Edge' => '🌊',
                            'Otro' => '🌐'
                        ];
                        
                        $browser_colors = [
                            'Chrome' => 'blue',
                            'Firefox' => 'orange',
                            'Safari' => 'blue',
                            'Edge' => 'green',
                            'Otro' => 'purple'
                        ];
                        
                        foreach ($browser_stats as $stat): 
                        ?>
                        <div class="stat-card">
                            <div class="stat-icon <?php echo $browser_colors[$stat['browser']] ?? 'purple'; ?>">
                                <?php echo $browser_emojis[$stat['browser']] ?? '🌐'; ?>
                            </div>
                            <div class="stat-value"><?php echo number_format($stat['count']); ?></div>
                            <div class="stat-label"><?php echo $stat['browser']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            
            <!-- Geolocalización -->
            <?php elseif ($section === 'geo'): ?>
                <!-- IMPORTANTE: Cargar Leaflet AL PRINCIPIO de la sección -->
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
                <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
                
                <?php
                // Obtener datos con geolocalización
                $geo_stats = [];
                $total_countries = 0;
                $total_cities = 0;
                $total_clicks = 0;
                $total_visitors = 0;
                $top_countries = [];
                
                try {
                    $stmt = $db->query("
                        SELECT country, city, latitude, longitude, COUNT(*) as clicks
                        FROM click_stats 
                        WHERE country IS NOT NULL 
                        AND latitude IS NOT NULL 
                        AND longitude IS NOT NULL
                        GROUP BY country, city, latitude, longitude
                        ORDER BY clicks DESC
                        LIMIT 50
                    ");
                    $geo_stats = $stmt->fetchAll();
                    
                    // Calcular estadísticas
                    if (!empty($geo_stats)) {
                        $total_countries = count(array_unique(array_column($geo_stats, 'country')));
                        $total_cities = count($geo_stats);
                        $total_clicks = array_sum(array_column($geo_stats, 'clicks'));
                        
                        // Top países
                        $countries = [];
                        foreach ($geo_stats as $stat) {
                            $country = $stat['country'];
                            if (!isset($countries[$country])) {
                                $countries[$country] = 0;
                            }
                            $countries[$country] += $stat['clicks'];
                        }
                        arsort($countries);
                        $top_countries = array_slice($countries, 0, 5, true);
                    }
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                }
                ?>
                
                <!-- Estadísticas generales -->
                <div class="geo-stats">
                    <div class="geo-stat-card">
                        <div class="geo-stat-value"><?php echo number_format($total_countries); ?></div>
                        <div class="geo-stat-label">🌍 Países</div>
                    </div>
                    <div class="geo-stat-card">
                        <div class="geo-stat-value"><?php echo number_format($total_cities); ?></div>
                        <div class="geo-stat-label">🏙️ Ciudades</div>
                    </div>
                    <div class="geo-stat-card">
                        <div class="geo-stat-value"><?php echo number_format($total_clicks); ?></div>
                        <div class="geo-stat-label">👆 Clicks Totales</div>
                    </div>
                    <div class="geo-stat-card">
                        <div class="geo-stat-value"><?php echo count($geo_stats); ?></div>
                        <div class="geo-stat-label">📍 Ubicaciones</div>
                    </div>
                </div>
                
                <div class="data-table">
                    <h3>🗺️ Mapa de Geolocalización</h3>
                    
                    <!-- Debug info -->
                    <div class="debug-info">
                        <strong>Debug:</strong> Encontradas <?php echo count($geo_stats); ?> ubicaciones<br>
                        <small>Si no ves el mapa, revisa la consola del navegador (F12)</small>
                    </div>
                    
                    <!-- Contenedor del mapa con altura inline para asegurar -->
                    <div id="geo-map" style="height: 500px; width: 100%; border-radius: 10px; margin-top: 20px; background: #f0f0f0;"></div>
                </div>
                
                <!-- Script del mapa INMEDIATAMENTE después del contenedor -->
                <script>
                    // Usar setTimeout para asegurar que el DOM está listo
                    setTimeout(function() {
                        console.log('Inicializando mapa...');
                        
                        try {
                            // Verificar que Leaflet está cargado
                            if (typeof L === 'undefined') {
                                console.error('Leaflet no está cargado!');
                                return;
                            }
                            
                            // Verificar que el contenedor existe
                            var container = document.getElementById('geo-map');
                            if (!container) {
                                console.error('Contenedor del mapa no encontrado!');
                                return;
                            }
                            
                            // Inicializar mapa
                            var map = L.map('geo-map').setView([20, 0], 2);
                            console.log('Mapa creado correctamente');
                            
                            // Añadir capa de tiles
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap contributors',
                                maxZoom: 18
                            }).addTo(map);
                            console.log('Tiles añadidos');
                            
                            <?php if (!empty($geo_stats)): ?>
                            // Añadir marcadores
                            var markers = [];
                            <?php foreach ($geo_stats as $stat): ?>
                            <?php if ($stat['latitude'] && $stat['longitude']): ?>
                            var marker = L.marker([<?php echo $stat['latitude']; ?>, <?php echo $stat['longitude']; ?>])
                                .addTo(map)
                                .bindPopup('<b><?php echo addslashes($stat['city'] . ', ' . $stat['country']); ?></b><br>Clicks: <?php echo $stat['clicks']; ?>');
                            markers.push(marker);
                            <?php endif; ?>
                            <?php endforeach; ?>
                            console.log('Marcadores añadidos: ' + markers.length);
                            
                            // Ajustar vista para mostrar todos los marcadores
                            if (markers.length > 0) {
                                var group = new L.featureGroup(markers);
                                map.fitBounds(group.getBounds().pad(0.1));
                            }
                            <?php else: ?>
                            console.log('No hay datos de geolocalización');
                            
                            // Añadir algunos marcadores de ejemplo
                            L.marker([40.416775, -3.703790]).addTo(map).bindPopup('Madrid, España');
                            L.marker([48.8566, 2.3522]).addTo(map).bindPopup('París, Francia');
                            L.marker([51.5074, -0.1278]).addTo(map).bindPopup('Londres, UK');
                            <?php endif; ?>
                            
                            // Forzar redimensionamiento del mapa
                            setTimeout(function() {
                                map.invalidateSize();
                            }, 100);
                            
                        } catch (error) {
                            console.error('Error al inicializar el mapa:', error);
                        }
                    }, 100);
                </script>
                
                <!-- Top países -->
                <?php if (!empty($top_countries)): ?>
                <div class="data-table" style="margin-top: 20px;">
                    <h3>🏆 Top 5 Países por Clicks</h3>
                    <div class="stats-grid">
                        <?php 
                        $position = 1;
                        foreach ($top_countries as $country => $clicks): 
                        ?>
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <?php echo $position; ?>°
                            </div>
                            <div class="stat-value"><?php echo number_format($clicks); ?></div>
                            <div class="stat-label"><?php echo $country; ?></div>
                        </div>
                        <?php 
                        $position++;
                        endforeach; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabla de datos -->
                <div class="data-table" style="margin-top: 20px;">
                    <h3>📊 Datos de Geolocalización (Top 10)</h3>
                    <?php if (!empty($geo_stats)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>País</th>
                                <th>Ciudad</th>
                                <th>Coordenadas</th>
                                <th>Clicks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($geo_stats, 0, 10) as $stat): ?>
                            <tr>
                                <td>🌍 <?php echo $stat['country']; ?></td>
                                <td>📍 <?php echo $stat['city']; ?></td>
                                <td>
                                    <small style="font-family: monospace;">
                                        <?php echo round($stat['latitude'], 4); ?>, <?php echo round($stat['longitude'], 4); ?>
                                    </small>
                                </td>
                                <td><span class="badge badge-primary"><?php echo $stat['clicks']; ?> clicks</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <span style="font-size: 4em;">📍</span>
                        <h4>No hay datos de geolocalización disponibles</h4>
                        <p>Los datos aparecerán cuando tus URLs reciban clicks con IPs válidas</p>
                    </div>
                    <?php endif; ?>
                </div>
            
            <!-- Gestión de Dominios - SOLO SUPERADMIN -->
            <?php elseif ($section === 'domains' && $is_superadmin): ?>
                <!-- Información sobre asignación de dominios -->
                <div class="domain-assignment-info">
                    <h3>📌 Reglas de Asignación de Dominios</h3>
                    <div class="assignment-rule">
                        <span class="icon">👥</span>
                        <span><strong>Sin usuario asignado:</strong> El dominio está disponible para TODOS los usuarios</span>
                    </div>
                    <div class="assignment-rule">
                        <span class="icon">👤</span>
                        <span><strong>Con usuario asignado:</strong> SOLO ese usuario puede usar el dominio</span>
                    </div>
                    <div class="assignment-rule">
                        <span class="icon">👑</span>
                        <span><strong>Superadmin:</strong> Siempre puede usar todos los dominios</span>
                    </div>
                </div>
                
                <!-- Formulario para añadir dominio -->
                <div class="data-table" style="margin-bottom: 30px;">
                    <h3>➕ Añadir nuevo dominio</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="domain_action" value="add">
                        <div class="domain-form">
                            <div class="form-group">
                                <label class="form-label">Dominio:</label>
                                <input type="text" name="domain" class="form-control" 
                                       placeholder="ejemplo.com" 
                                       pattern="^([a-zA-Z0-9][a-zA-Z0-9-]*\.)+[a-zA-Z]{2,}$"
                                       required>
                                <small style="color: #7f8c8d;">Sin http:// ni https://</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Asignar a usuario:</label>
                                <select name="user_id" class="form-select">
                                    <option value="">👥 Disponible para todos</option>
                                    <?php
                                    try {
                                        $stmt = $db->query("SELECT id, username FROM users WHERE status = 'active' ORDER BY username");
                                        $users = $stmt->fetchAll();
                                        foreach ($users as $user):
                                    ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        👤 <?php echo htmlspecialchars($user['username']); ?> (solo este usuario)
                                    </option>
                                    <?php 
                                        endforeach;
                                    } catch (Exception $e) {
                                        // Ignorar
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group" style="align-self: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    ➕ Añadir Dominio
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Instrucciones de configuración -->
                <div class="alert alert-info">
                    <strong>📝 Configuración necesaria:</strong><br>
                    1. Apunta el dominio a la IP del servidor: <code><?php echo $_SERVER['SERVER_ADDR'] ?? 'tu-servidor-ip'; ?></code><br>
                    2. Configura el archivo <code>.htaccess</code> o nginx para redirigir los dominios personalizados<br>
                    3. Opcionalmente, configura SSL para cada dominio
                </div>
                
                <!-- Lista de dominios -->
                <?php
                try {
                    $stmt = $db->query("
                        SELECT cd.*, users.username,
                        (SELECT COUNT(*) FROM urls WHERE domain_id = cd.id) as url_count
                        FROM custom_domains cd
                        LEFT JOIN users ON cd.user_id = users.id
                        ORDER BY cd.created_at DESC
                    ");
                    $domains = $stmt->fetchAll();
                } catch (Exception $e) {
                    $domains = [];
                }
                ?>
                
                <div class="data-table">
                    <h3>
                        <span>🌐 Dominios Personalizados</span>
                        <span class="badge badge-primary"><?php echo count($domains); ?></span>
                    </h3>
                    
                    <?php if ($domains): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Dominio</th>
                                <th>Estado</th>
                                <th>Asignado a</th>
                                <th>URLs</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td>
                                    <a href="https://<?php echo htmlspecialchars($domain['domain']); ?>" 
                                       target="_blank" 
                                       style="color: #667eea; text-decoration: none;">
                                        🌐 <?php echo htmlspecialchars($domain['domain']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="domain-status">
                                        <span class="status-dot <?php echo $domain['status']; ?>"></span>
                                        <span class="badge badge-<?php echo $domain['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $domain['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($domain['username']): ?>
                                        <span class="badge badge-warning">
                                            👤 <?php echo htmlspecialchars($domain['username']); ?>
                                        </span>
                                        <small style="color: #856404; display: block; font-size: 0.75em;">
                                            Solo este usuario
                                        </small>
                                    <?php else: ?>
                                        <span class="badge badge-info">
                                            👥 Todos los usuarios
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Mini formulario para cambiar asignación -->
                                    <form method="POST" class="mini-form" style="margin-top: 5px;">
                                        <input type="hidden" name="domain_action" value="update_user">
                                        <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                        <select name="new_user_id" class="mini-select" onchange="this.form.submit()">
                                            <option value="">Cambiar a...</option>
                                            <option value="">👥 Todos</option>
                                            <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                👤 <?php echo htmlspecialchars($user['username']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        🔗 <?php echo number_format($domain['url_count']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y', strtotime($domain['created_at'])); ?></small>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="domain_action" value="toggle">
                                        <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-<?php echo $domain['status'] === 'active' ? 'warning' : 'success'; ?> tooltip">
                                            <?php echo $domain['status'] === 'active' ? '⏸️' : '▶️'; ?>
                                            <span class="tooltiptext">
                                                <?php echo $domain['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>
                                            </span>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Eliminar este dominio? Las URLs volverán al dominio principal.');">
                                        <input type="hidden" name="domain_action" value="delete">
                                        <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger tooltip">
                                            🗑️
                                            <span class="tooltiptext">Eliminar</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <span style="font-size: 4em;">🌐</span>
                        <h4>No hay dominios personalizados</h4>
                        <p>Añade tu primer dominio usando el formulario de arriba</p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    // Toggle sidebar mobile
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
    
    // Copy URL function
    function copyUrl(inputId, button) {
        const input = document.getElementById(inputId);
        input.select();
        document.execCommand('copy');
        
        // Visual feedback
        button.classList.add('copied');
        button.innerHTML = '✅';
        
        setTimeout(() => {
            button.classList.remove('copied');
            button.innerHTML = '📋';
        }, 2000);
    }
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
    </script>
</body>
</html>
