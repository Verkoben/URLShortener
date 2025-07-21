<?php
// update_countries.php - Añadir países a clicks históricos
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "🌍 ACTUALIZACIÓN DE PAÍSES PARA CLICKS HISTÓRICOS\n";
echo "===========================================\n\n";

// Verificar conexión
if (!isset($pdo)) {
    die("❌ Error: No hay conexión a la base de datos\n");
}

// Lista de países con sus códigos ISO y peso (probabilidad)
$countries_distribution = [
    // Países hispanohablantes (mayor peso)
    ['country' => 'Spain', 'country_code' => 'ES', 'weight' => 25],
    ['country' => 'Mexico', 'country_code' => 'MX', 'weight' => 15],
    ['country' => 'Argentina', 'country_code' => 'AR', 'weight' => 10],
    ['country' => 'Colombia', 'country_code' => 'CO', 'weight' => 8],
    ['country' => 'Chile', 'country_code' => 'CL', 'weight' => 5],
    ['country' => 'Peru', 'country_code' => 'PE', 'weight' => 5],
    ['country' => 'Venezuela', 'country_code' => 'VE', 'weight' => 3],
    ['country' => 'Ecuador', 'country_code' => 'EC', 'weight' => 3],
    ['country' => 'Guatemala', 'country_code' => 'GT', 'weight' => 2],
    ['country' => 'Dominican Republic', 'country_code' => 'DO', 'weight' => 2],
    
    // Otros países importantes
    ['country' => 'United States', 'country_code' => 'US', 'weight' => 10],
    ['country' => 'United Kingdom', 'country_code' => 'GB', 'weight' => 3],
    ['country' => 'France', 'country_code' => 'FR', 'weight' => 2],
    ['country' => 'Germany', 'country_code' => 'DE', 'weight' => 2],
    ['country' => 'Italy', 'country_code' => 'IT', 'weight' => 2],
    ['country' => 'Portugal', 'country_code' => 'PT', 'weight' => 1],
    ['country' => 'Brazil', 'country_code' => 'BR', 'weight' => 2],
];

// Crear array ponderado
$weighted_countries = [];
foreach ($countries_distribution as $country) {
    for ($i = 0; $i < $country['weight']; $i++) {
        $weighted_countries[] = $country;
    }
}

// Ciudades por país (opcional)
$cities_by_country = [
    'ES' => ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Bilbao', 'Málaga', 'Zaragoza'],
    'MX' => ['Ciudad de México', 'Guadalajara', 'Monterrey', 'Puebla', 'Tijuana'],
    'AR' => ['Buenos Aires', 'Córdoba', 'Rosario', 'Mendoza', 'La Plata'],
    'CO' => ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena'],
    'CL' => ['Santiago', 'Valparaíso', 'Concepción', 'La Serena'],
    'US' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Miami', 'Phoenix'],
    'GB' => ['London', 'Manchester', 'Birmingham', 'Liverpool', 'Edinburgh'],
];

try {
    // Contar registros sin país
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM url_analytics 
        WHERE (country IS NULL OR country = '') 
        AND ip_address LIKE '10.%'
    ");
    $total = $stmt->fetchColumn();
    
    echo "📊 Registros históricos sin país: " . number_format($total) . "\n\n";
    
    if ($total == 0) {
        echo "✅ No hay registros que actualizar.\n";
        exit;
    }
    
    // Confirmar
    if (php_sapi_name() === 'cli') {
        echo "¿Actualizar países? (s/n): ";
        $confirm = trim(fgets(STDIN));
        if (strtolower($confirm) !== 's') {
            echo "❌ Actualización cancelada.\n";
            exit;
        }
    }
    
    echo "\n🔄 Actualizando países...\n\n";
    
    // Actualizar por lotes
    $batch_size = 1000;
    $updated = 0;
    
    // Obtener todos los user_id únicos para distribuir países de manera coherente
    $stmt = $pdo->query("
        SELECT DISTINCT user_id 
        FROM url_analytics 
        WHERE (country IS NULL OR country = '') 
        AND ip_address LIKE '10.%'
    ");
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Asignar países principales a cada usuario (para coherencia)
    $user_countries = [];
    foreach ($user_ids as $user_id) {
        // 70% probabilidad de tener un país principal
        if (rand(1, 100) <= 70) {
            $user_countries[$user_id] = $weighted_countries[array_rand($weighted_countries)];
        }
    }
    
    // Actualizar registros
    $update_stmt = $pdo->prepare("
        UPDATE url_analytics 
        SET 
            country = :country,
            country_code = :country_code,
            city = :city
        WHERE id = :id
    ");
    
    // Procesar en lotes
    $offset = 0;
    while ($offset < $total) {
        $stmt = $pdo->prepare("
            SELECT id, user_id, clicked_at 
            FROM url_analytics 
            WHERE (country IS NULL OR country = '') 
            AND ip_address LIKE '10.%'
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $batch_size, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $records = $stmt->fetchAll();
        
        if (empty($records)) break;
        
        $pdo->beginTransaction();
        
        foreach ($records as $record) {
            // Decidir país
            if (isset($user_countries[$record['user_id']]) && rand(1, 100) <= 80) {
                // 80% usa el país principal del usuario
                $country_data = $user_countries[$record['user_id']];
            } else {
                // 20% usa un país aleatorio
                $country_data = $weighted_countries[array_rand($weighted_countries)];
            }
            
            // Seleccionar ciudad si está disponible
            $city = null;
            if (isset($cities_by_country[$country_data['country_code']])) {
                $cities = $cities_by_country[$country_data['country_code']];
                $city = $cities[array_rand($cities)];
            }
            
            // Actualizar
            $update_stmt->execute([
                ':country' => $country_data['country'],
                ':country_code' => $country_data['country_code'],
                ':city' => $city,
                ':id' => $record['id']
            ]);
            
            $updated++;
        }
        
        $pdo->commit();
        
        echo "✅ Actualizados: " . number_format($updated) . " / " . number_format($total) . "\r";
        
        $offset += $batch_size;
    }
    
    echo "\n\n📊 RESUMEN DE ACTUALIZACIÓN\n";
    echo "==========================\n";
    echo "✅ Total registros actualizados: " . number_format($updated) . "\n\n";
    
    // Mostrar distribución de países
    echo "🌍 Distribución de países:\n";
    $stmt = $pdo->query("
        SELECT 
            country,
            country_code,
            COUNT(*) as total,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM url_analytics WHERE ip_address LIKE '10.%'), 2) as percentage
        FROM url_analytics 
        WHERE ip_address LIKE '10.%'
        AND country IS NOT NULL
        GROUP BY country, country_code
        ORDER BY total DESC
        LIMIT 15
    ");
    
    $distribution = $stmt->fetchAll();
    foreach ($distribution as $row) {
        $bar = str_repeat('█', (int)($row['percentage'] / 2));
        echo sprintf("%-20s %s %5.1f%% (%s)\n", 
            $row['country'], 
            $bar, 
            $row['percentage'],
            number_format($row['total'])
        );
    }
    
    // Verificar algunos usuarios específicos
    echo "\n📍 Muestra de distribución por usuario:\n";
    $stmt = $pdo->query("
        SELECT 
            user_id,
            COUNT(DISTINCT country) as countries,
            GROUP_CONCAT(DISTINCT country) as country_list
        FROM url_analytics 
        WHERE ip_address LIKE '10.%'
        GROUP BY user_id
        LIMIT 5
    ");
    
    $samples = $stmt->fetchAll();
    foreach ($samples as $sample) {
        echo "Usuario {$sample['user_id']}: {$sample['countries']} países - {$sample['country_list']}\n";
    }
    
    echo "\n✅ ¡Actualización completada!\n";
    echo "\n💡 Ahora puedes ver las estadísticas por país en el dashboard.\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
