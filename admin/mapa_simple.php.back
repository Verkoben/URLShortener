<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != 1) {
    header('Location: login.php');
    exit;
}

require_once '../conf.php';
$db = Database::getInstance()->getConnection();

// Consulta SQL corregida para MySQL strict mode
$stmt = $db->query("
    SELECT 
        latitude,
        longitude,
        ANY_VALUE(city) as city, 
        ANY_VALUE(country) as country, 
        COUNT(*) as clicks
    FROM click_stats
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
    GROUP BY latitude, longitude
    ORDER BY clicks DESC
    LIMIT 50
");
$locations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ubicaciones - Vista Simple</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .location-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .location-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .location-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .location-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .clicks {
            font-size: 2em;
            color: #667eea;
            font-weight: bold;
            margin: 10px 0;
        }
        .map-link {
            display: inline-block;
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .map-link:hover {
            background: #218838;
        }
        .back {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .stats {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats span {
            display: inline-block;
            margin: 0 20px;
            font-size: 1.2em;
        }
        .stats strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include '../menu.php'; ?>
    
    <div class="header">
        <h1>📍 Ubicaciones de Clicks</h1>
        <p>Vista simple con enlaces a mapas externos</p>
    </div>
    
    <div class="container">
        <a href="panel_simple.php" class="back">← Volver al Panel</a>
        
        <div class="stats">
            <span><strong><?php echo count($locations); ?></strong> Ubicaciones</span>
            <span><strong><?php echo array_sum(array_column($locations, 'clicks')); ?></strong> Clicks totales</span>
            <span><strong><?php echo count(array_unique(array_column($locations, 'country'))); ?></strong> Países</span>
        </div>
        
        <div class="location-grid">
            <?php foreach($locations as $loc): ?>
            <div class="location-card">
                <h3><?php echo htmlspecialchars($loc['city'] ?: 'Ciudad desconocida'); ?></h3>
                <p><?php echo htmlspecialchars($loc['country'] ?: 'País desconocido'); ?></p>
                <div class="clicks"><?php echo $loc['clicks']; ?> clicks</div>
                <p>📍 <?php echo round($loc['latitude'], 4) . ', ' . round($loc['longitude'], 4); ?></p>
                <a href="https://www.google.com/maps?q=<?php echo $loc['latitude'] . ',' . $loc['longitude']; ?>" 
                   target="_blank" 
                   class="map-link">
                    Ver en Google Maps →
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
