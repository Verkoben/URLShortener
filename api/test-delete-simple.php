<?php
// Test simple de eliminación
header('Content-Type: text/plain');

$code = $_GET['code'] ?? 'test';
$user_id = $_GET['user'] ?? 1;

echo "Test Delete Simple\n";
echo "==================\n\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=url_shortener", 'root', 'trapisonda');
    
    // Ver si existe
    $stmt = $pdo->prepare("SELECT * FROM urls WHERE short_code = ?");
    $stmt->execute([$code]);
    $url = $stmt->fetch();
    
    echo "1. URL encontrada: " . ($url ? "SI - User: {$url['user_id']}" : "NO") . "\n";
    
    if ($url) {
        // Intentar eliminar
        $stmt = $pdo->prepare("DELETE FROM urls WHERE short_code = ? AND user_id = ?");
        $stmt->execute([$code, $user_id]);
        $deleted = $stmt->rowCount();
        
        echo "2. Filas eliminadas: $deleted\n";
        
        // Verificar
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM urls WHERE short_code = ?");
        $stmt->execute([$code]);
        $remaining = $stmt->fetchColumn();
        
        echo "3. URLs restantes con ese código: $remaining\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
