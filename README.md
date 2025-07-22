# 🚀 URL Shortener

Sistema completo de acortador de URLs con panel de administración.

## ✨ Características

- 🔗 Acortador de URLs con códigos personalizados
- 🌐 Soporte para múltiples dominios
- 📊 Estadísticas detalladas de clicks
- 🗺️ Geolocalización de visitantes
- 📱 Generación de códigos QR
- 👥 Sistema de usuarios con roles
- 🎨 Diseño responsive y moderno

## 🛠️ Instalación

### Requisitos
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache con mod_rewrite o Nginx
- Extensiones PHP: PDO, PDO_MySQL, GD

### Pasos

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/tu-usuario/url-shortener.git
   cd url-shortener

Configurar la base de datos
bashmysql -u root -p < database.sql

Configurar el proyecto
bashcp conf.php.example conf.php
# Editar conf.php con tus credenciales

Establecer permisos
bashsudo ./set_permissions.sh

Configurar el servidor web

Para Apache: Asegúrate de que mod_rewrite está activo
Para Nginx: Usa la configuración proporcionada



🔧 Configuración
Apache VirtualHost
apache<VirtualHost *:80>
    ServerName tu-dominio.com
    DocumentRoot /var/www/url-shortener
    
    <Directory /var/www/url-shortener>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
Dominios personalizados

Añade el dominio en el panel admin
Configura el DNS para apuntar al servidor
Añade el VirtualHost correspondiente

📝 Uso
Panel de administración

URL: https://tu-dominio.com/admin
Usuario por defecto: admin
Contraseña: admin123 (¡cambiar inmediatamente!)

API (endpoints básicos)

Crear URL: POST a /api/shorten
Estadísticas: GET a /stats.php?code=CODIGO

🔒 Seguridad

Cambia las credenciales por defecto
Usa HTTPS en producción
Mantén PHP y MySQL actualizados
Realiza backups regulares

📄 Licencia
MIT License - ver archivo LICENSE
👨‍💻 Autor
Tu Nombre - @tu-twitter

⭐ Si te gusta este proyecto, dale una estrella!

## 📦 **database.sql (estructura de la BD):**

```sql
-- URL Shortener Database Structure
-- Version 1.0

CREATE DATABASE IF NOT EXISTS `url_shortener` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `url_shortener`;

-- Tabla de usuarios
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario admin por defecto
INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
('admin', 'admin@example.com', '$2y$10$YourHashHere', 'admin');

-- Tabla de dominios personalizados
CREATE TABLE `custom_domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de URLs
CREATE TABLE `urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT '1',
  `domain_id` int(11) DEFAULT NULL,
  `short_code` varchar(20) NOT NULL,
  `original_url` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `clicks` int(11) DEFAULT '0',
  `last_clicked` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_code` (`short_code`),
  KEY `user_id` (`user_id`),
  KEY `domain_id` (`domain_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`domain_id`) REFERENCES `custom_domains`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de estadísticas
CREATE TABLE `click_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `referer` text,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `url_id` (`url_id`),
  KEY `clicked_at` (`clicked_at`),
  FOREIGN KEY (`url_id`) REFERENCES `urls`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE click_stats ADD COLUMN accessed_domain VARCHAR(255) DEFAULT NULL;
-- Crear la tabla
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE urls 
ADD COLUMN IF NOT EXISTS active TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 0,
ADD INDEX IF NOT EXISTS idx_active (active),
ADD INDEX IF NOT EXISTS idx_public (is_public);

-- Actualizar URLs existentes
UPDATE urls SET active = 1 WHERE active IS NULL;
CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(100) DEFAULT 'API Token',
    permissions TEXT,
    last_used DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
);
Opcional para el gestor de marcadores de navegadores
-- =====================================================
-- GESTOR DE URLs CORTAS - ESTRUCTURA DE BASE DE DATOS
-- =====================================================
-- Autor: Claude & Chino
-- Fecha: 17 Enero 2025
-- Descripción: Estructura completa para gestor personalizado de URLs
-- =====================================================

-- -----------------------------------------------------
-- 1. TABLA PRINCIPAL DEL GESTOR
-- -----------------------------------------------------
CREATE TABLE `user_urls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `url_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `favicon` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_url` (`user_id`, `url_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_url_id` (`url_id`),
  KEY `idx_category` (`category`),
  CONSTRAINT `fk_user_urls_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_urls_url` FOREIGN KEY (`url_id`) REFERENCES `urls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. ACTUALIZAR TABLA URLS (solo si es necesario)
-- -----------------------------------------------------
-- Agregar campo title si no existe
ALTER TABLE `urls` 
ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `original_url`;

-- Agregar campo active si no existe  
ALTER TABLE `urls` 
ADD COLUMN `active` tinyint(1) DEFAULT 1 AFTER `title`;

-- Agregar índices para optimización
ALTER TABLE `urls` 
ADD INDEX `idx_user_active` (`user_id`, `active`),
ADD INDEX `idx_short_code` (`short_code`);

-- -----------------------------------------------------
-- 3. TABLA API TOKENS (opcional)
-- -----------------------------------------------------
CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE urls MODIFY COLUMN short_code VARCHAR(100) NOT NULL;

-- -----------------------------------------------------
-- 4. ACTUALIZAR TÍTULOS VACÍOS CON FORMATO MEJORADO
-- -----------------------------------------------------
UPDATE `urls` 
SET `title` = CONCAT(
    UPPER(LEFT(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, '://', -1), '/', 1), 'www.', ''), 1)),
    LOWER(SUBSTRING(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, '://', -1), '/', 1), 'www.', ''), 2)),
    ' - ', 
    short_code,
    ' → ',
    original_url
)
WHERE (title IS NULL OR title = '') 
AND active = 1;

-- -----------------------------------------------------
-- 5. SINCRONIZAR URLs AL GESTOR (ejemplo para user_id = 12)
-- -----------------------------------------------------
INSERT INTO `user_urls` (`user_id`, `url_id`, `title`, `category`, `favicon`, `notes`, `created_at`) 
SELECT 
    12, 
    u.id, 
    CONCAT(
        UPPER(LEFT(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(u.original_url, '://', -1), '/', 1), 'www.', ''), 1)),
        LOWER(SUBSTRING(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(u.original_url, '://', -1), '/', 1), 'www.', ''), 2)),
        ' - ', 
        u.short_code,
        ' → ',
        u.original_url
    ) as title,
    'personal' as category,
    CONCAT('https://www.google.com/s2/favicons?domain=', SUBSTRING_INDEX(SUBSTRING_INDEX(u.original_url, '://', -1), '/', 1)) as favicon,
    'Importado automáticamente' as notes,
    u.created_at
FROM `urls` u 
WHERE u.user_id = 12 
AND u.active = 1 
AND NOT EXISTS (
    SELECT 1 FROM user_urls uu WHERE uu.user_id = 12 AND uu.url_id = u.id
);

-- -----------------------------------------------------
-- 6. CONSULTAS DE VERIFICACIÓN
-- -----------------------------------------------------
-- Verificar estructura de user_urls
DESCRIBE `user_urls`;

-- Verificar URLs del usuario en el gestor
SELECT 
    uu.id,
    uu.title,
    uu.category,
    u.short_code,
    u.original_url,
    uu.created_at
FROM `user_urls` uu
JOIN `urls` u ON uu.url_id = u.id
WHERE uu.user_id = 12
ORDER BY uu.created_at DESC;

-- Verificar estadísticas del gestor
SELECT 
    'En gestor' as tipo,
    COUNT(*) as total
FROM `user_urls` 
WHERE user_id = 12

UNION ALL

SELECT 
    'En sistema' as tipo,
    COUNT(*) as total
FROM `urls` 
WHERE user_id = 12 AND active = 1;

-- Verificar dominios más usados
SELECT 
    cd.domain,
    COUNT(*) as count
FROM `urls` u
LEFT JOIN `custom_domains` cd ON u.domain_id = cd.id
WHERE u.user_id = 12 AND u.active = 1
GROUP BY u.domain_id, cd.domain
ORDER BY count DESC;

-- -----------------------------------------------------
-- 7. CONSULTAS DE LIMPIEZA (usar con precaución)
-- -----------------------------------------------------
-- Limpiar gestor de un usuario específico
-- DELETE FROM `user_urls` WHERE `user_id` = 12;

-- Limpiar URLs inactivas
-- DELETE FROM `urls` WHERE `active` = 0;

-- -----------------------------------------------------
-- 8. CONSULTAS DE MANTENIMIENTO
-- -----------------------------------------------------
-- Optimizar tablas
OPTIMIZE TABLE `user_urls`;
OPTIMIZE TABLE `urls`;
OPTIMIZE TABLE `custom_domains`;

-- Verificar integridad de foreign keys
SELECT 
    uu.id,
    uu.user_id,
    uu.url_id,
    u.id as url_exists,
    us.id as user_exists
FROM `user_urls` uu
LEFT JOIN `urls` u ON uu.url_id = u.id
LEFT JOIN `users` us ON uu.user_id = us.id
WHERE u.id IS NULL OR us.id IS NULL;

-- =====================================================
-- NOTAS DE IMPLEMENTACIÓN:
-- =====================================================
-- 1. Ejecutar CREATE TABLE user_urls primero
-- 2. Solo ejecutar ALTER TABLE si los campos no existen
-- 3. Cambiar user_id = 12 por el ID del usuario real
-- 4. Las consultas de limpieza están comentadas por seguridad
-- 5. Verificar foreign keys antes de ejecutar
-- =====================================================
OPCIONALES PARA LA EXTENSION
CREATE TABLE url_analytics (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  url_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  short_code varchar(255) NOT NULL,
  ip_address varchar(45) DEFAULT NULL,
  user_agent text DEFAULT NULL,
  referer text DEFAULT NULL,
  country varchar(100) DEFAULT NULL,
  country_code varchar(2) DEFAULT NULL,
  city varchar(100) DEFAULT NULL,
  device_type enum('desktop','mobile','tablet','bot') DEFAULT 'desktop',
  browser varchar(100) DEFAULT NULL,
  os varchar(100) DEFAULT NULL,
  clicked_at timestamp DEFAULT CURRENT_TIMESTAMP,
  session_id varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_url_id (url_id),
  KEY idx_user_id (user_id),
  KEY idx_short_code (short_code),
  KEY idx_clicked_at (clicked_at),
  KEY idx_country (country_code),
  KEY idx_device (device_type),
  CONSTRAINT fk_analytics_url FOREIGN KEY (url_id) REFERENCES urls (id) ON DELETE CASCADE,
  CONSTRAINT fk_analytics_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE daily_stats (
  id int(11) NOT NULL AUTO_INCREMENT,
  url_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  date date NOT NULL,
  total_clicks int(11) DEFAULT 0,
  unique_visitors int(11) DEFAULT 0,
  desktop_clicks int(11) DEFAULT 0,
  mobile_clicks int(11) DEFAULT 0,
  tablet_clicks int(11) DEFAULT 0,
  top_country varchar(100) DEFAULT NULL,
  top_browser varchar(100) DEFAULT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_url_date (url_id, date),
  KEY idx_user_date (user_id, date),
  CONSTRAINT fk_daily_stats_url FOREIGN KEY (url_id) REFERENCES urls (id) ON DELETE CASCADE,
  CONSTRAINT fk_daily_stats_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_analytics_date_range ON url_analytics (clicked_at, user_id);
CREATE INDEX idx_analytics_url_date ON url_analytics (url_id, clicked_at);
------------------------
Crear tabla de bookmarks, para la extensión
-------------------------
CREATE TABLE bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url VARCHAR(2048) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    category VARCHAR(100) DEFAULT 'general',
    is_favorite BOOLEAN DEFAULT FALSE,
    short_code VARCHAR(20) DEFAULT NULL,
    url_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_category (category),
    INDEX idx_short_code (short_code),
    INDEX idx_url_id (url_id),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-----------------------
Apy key, muy importante
-----------------------
ALTER TABLE users ADD COLUMN api_key VARCHAR(64) UNIQUE DEFAULT NULL;
------------------------
Black list
------------------------
CREATE TABLE url_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    short_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_code (user_id, short_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
----------------------------
ADD COLUMN last_accessed DATETIME DEFAULT NULL AFTER created_at,
ADD INDEX idx_last_accessed (last_accessed);"
-----------------------------
Datos de geolocalización
-----------------------------
ALTER TABLE url_analytics 
ADD COLUMN region VARCHAR(100) AFTER city,
ADD COLUMN latitude DECIMAL(10, 8) AFTER region,
ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude,
ADD INDEX idx_country_code (country_code),
ADD INDEX idx_geo (latitude, longitude);
