<?php
// api/auth-token.php - Autenticación por token para la API
function authenticateWithToken($db) {
    // 1. Verificar Authorization header
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    // 2. Verificar X-API-Token header (alternativa)
    if (!$token && isset($headers['X-API-Token'])) {
        $token = $headers['X-API-Token'];
    }
    
    // 3. Verificar token en query string (menos seguro)
    if (!$token && isset($_GET['api_token'])) {
        $token = $_GET['api_token'];
    }
    
    if (!$token) {
        return false;
    }
    
    try {
        // Buscar token activo
        $stmt = $db->prepare("
            SELECT t.*, u.username, u.role, u.status 
            FROM api_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = ? 
            AND t.is_active = 1
            AND u.status = 'active'
            AND (t.expires_at IS NULL OR t.expires_at > NOW())
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            // Actualizar último uso
            $updateStmt = $db->prepare("UPDATE api_tokens SET last_used = NOW() WHERE id = ?");
            $updateStmt->execute([$tokenData['id']]);
            
            return [
                'authenticated' => true,
                'user_id' => $tokenData['user_id'],
                'username' => $tokenData['username'],
                'role' => $tokenData['role'],
                'token_id' => $tokenData['id'],
                'permissions' => $tokenData['permissions']
            ];
        }
    } catch (Exception $e) {
        error_log("Token auth error: " . $e->getMessage());
    }
    
    return false;
}

// Función combinada de autenticación (sesión + token)
function authenticateAPI($db) {
    // Primero intentar con sesión
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return [
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'] ?? 1,
            'username' => $_SESSION['username'] ?? 'Usuario',
            'role' => $_SESSION['role'] ?? 'user',
            'method' => 'session'
        ];
    }
    
    // Luego intentar con token
    $tokenAuth = authenticateWithToken($db);
    if ($tokenAuth) {
        $tokenAuth['method'] = 'token';
        return $tokenAuth;
    }
    
    return ['authenticated' => false];
}
?>
