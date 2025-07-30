<?php
// menu.php - Menú de navegación para todo el sitio
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_admin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
?>

<style>
    .nav-menu {
        background-color: #2c3e50;
        padding: 0;
        margin: 0 0 30px 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        position: relative;
        z-index: 1000;
    }
    
    .nav-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 60px;
    }
    
    .nav-logo {
        color: #ecf0f1;
        font-size: 1.5rem;
        font-weight: bold;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }
    
    .nav-logo:hover {
        color: #3498db;
        transform: translateX(5px);
    }
    
    .nav-links {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        gap: 5px;
    }
    
    .nav-links li {
        margin: 0;
    }
    
    .nav-links a {
        color: #ecf0f1;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 5px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 15px;
    }
    
    .nav-links a:hover {
        background-color: #34495e;
        transform: translateY(-2px);
    }
    
    .nav-links a.active {
        background-color: #3498db;
        box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .nav-container {
            flex-direction: column;
            height: auto;
            padding: 15px 20px;
        }
        
        .nav-logo {
            margin-bottom: 15px;
        }
        
        .nav-links {
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        
        .nav-links a {
            padding: 8px 15px;
            font-size: 14px;
        }
    }
    
    /* Animación de entrada */
    @keyframes slideDown {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .nav-menu {
        animation: slideDown 0.3s ease-out;
    }
</style>

<nav class="nav-menu">
    <div class="nav-container">
        <a href="/" class="nav-logo">
            🌐 Acortador URL
        </a>
        
        <ul class="nav-links">
            <li>
                <a href="/index.php" 
                   <?php echo ($pagina_actual == 'index.php' && !$es_admin) ? 'class="active"' : ''; ?>>
                    🔗 Acortador
                </a>
            </li>
            <li>
            <li class="nav-item">
            </li>
            </li>
            <li>
                <a href="/admin/" 
                   <?php echo $es_admin ? 'class="active"' : ''; ?>>
                    ⚙️ Panel Admin
                </a>
            </li>
            <?php if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] == 1): ?>
            <li>
                <a href="/admin/logout.php" style="background-color: #e74c3c;">
                    🚪 Salir
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
