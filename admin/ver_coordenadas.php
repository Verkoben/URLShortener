<?php
require_once 'session_check.php';
require_once '../conf.php';

if (!isset($_SESSION['admin_logged_in'])) die('No autorizado');

$db = Database::getInstance()->getConnection();

// Ver las coordenadas directamente
$stmt = $db->query("
    SELECT 
        id,
        latitude,
        longitude,
        city,
        country,
        CONCAT(latitude, ',', longitude) as coords
    FROM click_stats 
    WHERE latitude IS NOT NULL 
    AND longitude IS NOT NULL 
    LIMIT 20
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ver Coordenadas</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Coordenadas en la Base de Datos</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Ciudad</th>
            <th>País</th>
            <th>Latitud</th>
            <th>Longitud</th>
            <th>Google Maps</th>
        </tr>
        <?php while ($row = $stmt->fetch()): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['city']; ?></td>
            <td><?php echo $row['country']; ?></td>
            <td><?php echo $row['latitude']; ?></td>
            <td><?php echo $row['longitude']; ?></td>
            <td>
                <a href="https://maps.google.com/?q=<?php echo $row['coords']; ?>" 
                   target="_blank">Ver en Maps</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
