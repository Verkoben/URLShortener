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
ALTER TABLE url_analytics 
ADD COLUMN region VARCHAR(100) AFTER city,
ADD COLUMN latitude DECIMAL(10, 8) AFTER region,
ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude,
ADD INDEX idx_country_code (country_code),
ADD INDEX idx_geo (latitude, longitude);

-- Añadir índice para mejorar rendimiento de consultas geográficas
ALTER TABLE url_analytics
ADD INDEX idx_url_geo (url_id, latitude, longitude);
-- Verificar y añadir columnas para metadatos sociales si no existen
ALTER TABLE urls 
ADD COLUMN IF NOT EXISTS title VARCHAR(255) AFTER original_url,
ADD COLUMN IF NOT EXISTS description TEXT AFTER title,
ADD COLUMN IF NOT EXISTS og_image VARCHAR(500) AFTER description;

-- Si tu versión de MySQL no soporta IF NOT EXISTS en ALTER TABLE, usa esto:
-- Primero verifica qué columnas ya existen
SHOW COLUMNS FROM urls;

-- Luego añade solo las que faltan, por ejemplo:
-- Si falta 'title':
ALTER TABLE urls ADD COLUMN title VARCHAR(255) AFTER original_url;

-- Si falta 'description':
ALTER TABLE urls ADD COLUMN description TEXT AFTER title;

-- Si falta 'og_image':
ALTER TABLE urls ADD COLUMN og_image VARCHAR(500) AFTER description;

----------------------------------
Setea tiempo de login
-----------------------------------
 update_session.sh
-----------------------------------


# 🔗 URLShortener - Acortador de URLs Profesional

<div align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-blue.svg" alt="PHP Version">
  <img src="https://img.shields.io/badge/MySQL-5.7+-orange.svg" alt="MySQL Version">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
</div>

## 📋 Tabla de Contenidos
- [Descripción](#descripción)
- [Características](#características)
- [Demo](#demo)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Uso](#uso)
- [API](#api)
- [Extensión de Navegador](#extensión-de-navegador)
- [Seguridad](#seguridad)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Contribuir](#contribuir)
- [Licencia](#licencia)

## 📖 Descripción

URLShortener es un sistema completo y profesional de acortamiento de URLs con panel de administración avanzado. Diseñado para ofrecer una solución robusta tanto para uso personal como empresarial, permitiendo gestionar múltiples dominios, obtener estadísticas detalladas y mantener un control total sobre los enlaces acortados.

### 🎯 Propósito

Este proyecto nace de la necesidad de tener un control total sobre los enlaces acortados, con características empresariales como:
- Privacidad de datos
- Estadísticas detalladas sin depender de terceros
- Personalización completa
- Integración con sistemas propios
- Gestión multi-dominio
- Sistema de marcadores integrado

## ✨ Características

### 🔗 Core Features
- **Acortador de URLs** con códigos personalizados o aleatorios
- **Redirección ultrarrápida** con caché optimizado
- **URLs personalizadas** (vanity URLs)
- **Validación automática** de URLs

### 📑 Gestor de Marcadores
- **Sistema completo de bookmarks** integrado
- **Categorización** de enlaces guardados
- **Etiquetas y notas** personalizadas
- **Favicons automáticos** para identificación visual
- **Búsqueda avanzada** en marcadores
- **Importación/Exportación** de marcadores
- **Sincronización** con extensión del navegador

### 🌐 Multi-dominio
- Soporte para múltiples dominios personalizados
- Gestión centralizada de todos los dominios
- Configuración independiente por dominio
- SSL/HTTPS automático

### 📊 Analytics y Estadísticas
- **Estadísticas en tiempo real** de clics
- **Geolocalización** de visitantes con mapas interactivos
- **Análisis de dispositivos** (Desktop, Mobile, Tablet)
- **Detección de navegadores** y sistemas operativos
- **Gráficos y reportes** exportables
- **Heatmaps** de actividad
- **Analytics por marcador** guardado

### 👥 Sistema de Usuarios
- **Roles y permisos** (Admin, Usuario)
- **Panel personalizado** por usuario
- **API Keys** individuales
- **Límites configurables** por usuario
- **Gestor personal de URLs** y marcadores

### 🎨 Interfaz y UX
- **Diseño responsive** y moderno
- **Tema oscuro/claro**
- **Dashboard intuitivo**
- **Búsqueda y filtros** avanzados
- **Vista de marcadores** estilo navegador

### 🔧 Características Técnicas
- **API RESTful** completa
- **Generación de códigos QR** integrada
- **Importación/Exportación** masiva
- **Caché inteligente** para optimización
- **Logs detallados** de actividad
- **Backup automático**
- **Extensión de navegador** para Chrome/Firefox

### 🔐 Seguridad
- **Protección contra spam** y abuso
- **Lista negra** de dominios
- **Rate limiting** configurable
- **Validación de entrada** exhaustiva
- **Tokens CSRF**
- **Preparación contra SQL injection**
- **API Keys seguras** para extensiones

## 🖥️ Demo

```
URL: https://tu-dominio.com/demo
Usuario: demo
Contraseña: demo123
```

## 📋 Requisitos

### Requisitos Mínimos
- **PHP** 7.4 o superior
- **MySQL** 5.7 o superior / MariaDB 10.2+
- **Apache** 2.4+ con mod_rewrite / **Nginx** 1.18+
- **RAM**: 512MB mínimo
- **Espacio**: 100MB mínimo

### Extensiones PHP Requeridas
```bash
- PDO
- PDO_MySQL
- GD (para códigos QR)
- cURL
- JSON
- mbstring
- openssl
```

### Recomendado para Producción
- PHP 8.0+
- MySQL 8.0+
- Redis/Memcached para caché
- SSL certificado

## 🚀 Instalación

### 1. Clonar el Repositorio
```bash
git clone https://github.com/Verkoben/URLShortener.git
cd URLShortener
```

### 2. Configurar la Base de Datos
```bash
# Crear la base de datos
mysql -u root -p -e "CREATE DATABASE url_shortener CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar la estructura
mysql -u root -p url_shortener < database.sql
```

### 3. Configurar el Proyecto
```bash
# Copiar archivo de configuración
cp conf.php.example conf.php

# Editar configuración
nano conf.php
```

Configurar los siguientes parámetros:
```php
<?php
// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'url_shortener');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');

// Configuración del Sitio
define('SITE_URL', 'https://tu-dominio.com');
define('SITE_NAME', 'Mi Acortador');
define('ADMIN_EMAIL', 'admin@tu-dominio.com');

// Seguridad
define('SECURITY_SALT', 'genera-un-salt-aleatorio-aqui');
define('API_RATE_LIMIT', 100); // requests por hora

// Características
define('ENABLE_BOOKMARKS', true);
define('ENABLE_QR', true);
?>
```

### 4. Establecer Permisos
```bash
# Dar permisos de ejecución al script
chmod +x set_permissions.sh

# Ejecutar script de permisos
sudo ./set_permissions.sh

# O manualmente:
sudo chown -R www-data:www-data /var/www/URLShortener
sudo chmod -R 755 /var/www/URLShortener
sudo chmod -R 775 /var/www/URLShortener/cache
sudo chmod -R 775 /var/www/URLShortener/logs
sudo chmod -R 775 /var/www/URLShortener/uploads
```

### 5. Configurar el Servidor Web

#### Apache
```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    DocumentRoot /var/www/URLShortener
    
    <Directory /var/www/URLShortener>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/urlshortener-error.log
    CustomLog ${APACHE_LOG_DIR}/urlshortener-access.log combined
    
    # Redirección a HTTPS (recomendado)
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/URLShortener;
    index index.php;
    
    # Logs
    error_log /var/log/nginx/urlshortener-error.log;
    access_log /var/log/nginx/urlshortener-access.log;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
}
```

### 6. Activar el Sitio
```bash
# Apache
sudo a2ensite urlshortener.conf
sudo a2enmod rewrite
sudo systemctl reload apache2

# Nginx
sudo ln -s /etc/nginx/sites-available/urlshortener /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## ⚙️ Configuración

### 🔐 Primer Acceso
1. Navegar a `https://tu-dominio.com/admin`
2. Usuario por defecto: `admin`
3. Contraseña: `admin123`
4. **⚠️ IMPORTANTE**: Cambiar la contraseña inmediatamente

### 🌐 Añadir Dominios Personalizados
1. Acceder al panel de administración
2. Ir a "Dominios" → "Añadir Dominio"
3. Configurar el DNS del dominio para apuntar a tu servidor
4. Añadir el VirtualHost correspondiente

### 📧 Configurar Email (Opcional)
```php
// En conf.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'tu-email@gmail.com');
define('SMTP_PASS', 'tu-contraseña');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
```

## 📚 Uso

### Panel de Administración
- **URL**: `https://tu-dominio.com/admin`
- **Dashboard**: Estadísticas generales y actividad reciente
- **URLs**: Gestión completa de enlaces
- **Marcadores**: Gestor de bookmarks personal
- **Usuarios**: Administración de usuarios y permisos
- **Dominios**: Configuración de dominios personalizados
- **Estadísticas**: Analytics detallado

### Crear URL Corta (Web)
1. Acceder al panel
2. Click en "Nueva URL"
3. Pegar la URL larga
4. (Opcional) Personalizar el código corto
5. (Opcional) Guardar como marcador
6. Click en "Acortar"

### Panel Simple
- Acceso directo: `https://tu-dominio.com/panel_simple.php`
- Interfaz minimalista para crear URLs rápidamente
- Ideal para usuarios que solo necesitan acortar enlaces

### Gestor de Marcadores
1. Acceder a "Mis Marcadores"
2. Organizar por categorías
3. Añadir etiquetas y notas
4. Buscar y filtrar marcadores
5. Exportar/Importar colecciones

### Compartir Enlaces
- URL corta: `https://tu-dominio.com/abc123`
- Código QR: `https://tu-dominio.com/qr/abc123`
- Estadísticas públicas: `https://tu-dominio.com/stats.php?code=abc123`

## 🔌 API

### Autenticación
```bash
# Header requerido
Authorization: Bearer TU_API_KEY
```

### Endpoints Principales

#### Crear URL Corta
```bash
POST /api/shorten
Content-Type: application/json

{
    "url": "https://ejemplo-muy-largo.com/pagina",
    "custom_code": "mi-codigo", // opcional
    "domain_id": 1, // opcional
    "save_bookmark": true, // opcional
    "bookmark_category": "trabajo" // opcional
}
```

#### Gestión de Marcadores
```bash
# Listar marcadores
GET /api/bookmarks?category=trabajo&page=1

# Crear marcador
POST /api/bookmarks
{
    "url": "https://ejemplo.com",
    "title": "Mi sitio",
    "category": "personal",
    "tags": "importante,referencia"
}

# Actualizar marcador
PUT /api/bookmarks/{id}

# Eliminar marcador
DELETE /api/bookmarks/{id}
```

#### Obtener Estadísticas
```bash
GET /api/stats/{codigo}
```

#### Listar URLs del Usuario
```bash
GET /api/urls?page=1&limit=20
```

### Ejemplo en PHP
```php
<?php
$api_key = 'TU_API_KEY';
$url = 'https://tu-dominio.com/api/shorten';

$data = [
    'url' => 'https://ejemplo.com/pagina-muy-larga',
    'custom_code' => 'ejemplo',
    'save_bookmark' => true,
    'bookmark_category' => 'trabajo'
];

$options = [
    'http' => [
        'header' => [
            "Content-Type: application/json",
            "Authorization: Bearer $api_key"
        ],
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$response = json_decode($result, true);

echo "URL Corta: " . $response['short_url'];
?>
```

## 🧩 Extensión de Navegador

### Características
- **Acortar con un click** desde cualquier página
- **Guardar marcadores** directamente
- **Ver estadísticas** sin abrir el panel
- **Sincronización automática** con tu cuenta
- **Modo offline** con caché local

### Instalación
1. Descargar desde la Chrome Web Store / Firefox Add-ons
2. Configurar con tu API Key personal
3. Personalizar opciones de guardado

### Uso de la Extensión
- Click derecho → "Acortar con URLShortener"
- Botón de la barra de herramientas para acceso rápido
- Atajos de teclado personalizables

## 🔒 Seguridad

### Mejores Prácticas
1. **Usar HTTPS** siempre en producción
2. **Cambiar credenciales** por defecto inmediatamente
3. **Actualizar regularmente** PHP y MySQL
4. **Configurar backups** automáticos
5. **Monitorear logs** de acceso
6. **Limitar rate** de API según necesidades
7. **Rotar API Keys** periódicamente

### Headers de Seguridad Recomendados
```apache
# En .htaccess
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Content-Security-Policy "default-src 'self'"
```


### Tablas de Base de Datos
- `users` - Gestión de usuarios y roles
- `urls` - URLs acortadas
- `custom_domains` - Dominios personalizados
- `click_stats` - Estadísticas básicas de clicks
- `url_analytics` - Analytics detallado (extensión)
- `daily_stats` - Estadísticas diarias agregadas
- `user_urls` - Gestor personal de URLs
- `bookmarks` - Sistema de marcadores
- `api_tokens` - Tokens de API
- `url_blacklist` - Lista negra de códigos

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Por favor:

1. Fork el proyecto
2. Crea tu rama de feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

### Guías de Contribución
- Seguir PSR-12 para estilo de código PHP
- Añadir tests para nuevas funcionalidades
- Actualizar documentación según sea necesario
- Mantener retrocompatibilidad cuando sea posible

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**Tu Nombre**
- GitHub: [@Verkoben](https://github.com/Verkoben)
- Twitter: [@tu-twitter](https://twitter.com/tu-twitter)

## 🌟 Agradecimientos

- A todos los contribuidores
- Librerías de código abierto utilizadas
- Comunidad de desarrolladores

---

<div align="center">
  
⭐ **Si este proyecto te resulta útil, considera darle una estrella** ⭐

</div>
...
