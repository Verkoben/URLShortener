<?php
// config_api.php - Configuración para API (sin verificación de sesión)

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'url_shortener');
define('DB_USER', 'root');
define('DB_PASS', 'TU_PASSWORD_MYSQL'); // <-- CAMBIAR ESTO

// Configuración del sitio
define('SITE_NAME', 'Marcadores');
define('BASE_URL', 'http://localhost/marcadores');

// NO iniciar sesión
define('SKIP_SESSION', true);

// Conexión a base de datos
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Para API, devolver JSON
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a base de datos'
    ]);
    exit;
}

// Funciones básicas necesarias para la API
function getCurrentUserId() {
    return null; // La API usa api_key, no sesión
}

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>
