-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 25-07-2025 a las 19:49:00
-- Versión del servidor: 8.0.42-0ubuntu0.22.04.1
-- Versión de PHP: 8.4.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `url_shortener`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `BanUser` (IN `p_user_id` INT, IN `p_reason` TEXT, IN `p_banned_by` INT)  BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Actualizar estado del usuario
    UPDATE users 
    SET status = 'banned', 
        banned_reason = p_reason, 
        banned_at = NOW(), 
        banned_by = p_banned_by
    WHERE id = p_user_id AND status != 'banned';
    
    -- Desactivar todas las sesiones
    UPDATE user_sessions 
    SET is_active = 0 
    WHERE user_id = p_user_id;
    
    -- Desactivar todas las URLs del usuario
    UPDATE urls 
    SET active = 0 
    WHERE user_id = p_user_id;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_old_data` ()  BEGIN
    
    DELETE FROM rate_limit WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    
    DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY));
    
    
    
    
    
    
    UPDATE urls SET active = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW();
    
    
    UPDATE urls SET active = 0 WHERE max_clicks IS NOT NULL AND clicks >= max_clicks;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UnbanUser` (IN `p_user_id` INT)  BEGIN
    UPDATE users 
    SET status = 'active', 
        banned_reason = NULL, 
        banned_at = NULL, 
        banned_by = NULL,
        failed_login_attempts = 0,
        locked_until = NULL
    WHERE id = p_user_id;
    
    -- Reactivar URLs del usuario
    UPDATE urls 
    SET active = 1 
    WHERE user_id = p_user_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` int NOT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `last_four` char(4) NOT NULL,
  `permissions` json DEFAULT NULL,
  `rate_limit` int DEFAULT '1000',
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'API Token',
  `permissions` text COLLATE utf8mb4_unicode_ci,
  `last_used` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `user_id`, `token`, `name`, `permissions`, `last_used`, `created_at`, `expires_at`, `is_active`) VALUES
(5, 12, '7367c5ca87ed0e0af14d7f11cd7ae1953c50f3712df6d96f1e19c9fd7923e65e', 'Extension Chrome', 'read', NULL, '2025-07-17 10:15:22', NULL, 1),
(6, 12, 'ff996a3ed7a793de69ef7701776fdbaef0ebf502832fee71ab186321dd008c46', 'Extension chrome', 'read', NULL, '2025-07-17 10:41:26', NULL, 1),
(11, 13, '45eb614358a409b8a65b36bc517ca12446c17b2fd94db76d20159ac2f1a9164f', 'Extension Chrome', 'read', NULL, '2025-07-21 20:36:37', NULL, 1),
(13, 1, '0607171faecd57257b35170eaded897be066eae8d2cd07b983f7ac5f0bef1f70', 'API Token', 'read', NULL, '2025-07-21 22:23:43', NULL, 1),
(14, 17, 'c81e3f5d47afbd49923999415f9894250f3b8df17d33960220a449e3b9757b0f', 'API Token', 'read', NULL, '2025-07-23 01:23:37', NULL, 1),
(15, 11, '141c51c157b20b800ce46e746726a194fedf4eef4e4e86e002abd70f41477723', 'API Token', 'read', NULL, '2025-07-23 01:27:20', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `tags` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `is_favorite` tinyint(1) DEFAULT '0',
  `short_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bookmarks`
--

INSERT INTO `bookmarks` (`id`, `user_id`, `url`, `title`, `description`, `tags`, `category`, `is_favorite`, `short_code`, `url_id`, `created_at`, `updated_at`) VALUES
(1, 17, 'https://www.example.com', 'Ejemplo de prueba', '', '', 'general', 0, NULL, NULL, '2025-07-21 00:23:47', '2025-07-21 00:23:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `click_stats`
--

CREATE TABLE `click_stats` (
  `id` int NOT NULL,
  `url_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `referer` text COLLATE utf8mb4_unicode_ci,
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `timezone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accessed_domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `click_stats`
--

INSERT INTO `click_stats` (`id`, `url_id`, `user_id`, `session_id`, `clicked_at`, `ip_address`, `user_agent`, `referer`, `country_code`, `region`, `latitude`, `longitude`, `timezone`, `country`, `city`, `accessed_domain`) VALUES
(17, 20, NULL, NULL, '2025-07-09 13:23:51', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:128.0) Gecko/20100101 Firefox/128.0', '', 'ES', 'PV', '43.26540000', '-2.92650000', 'Europe/Madrid', 'Spain', 'Bilbao', NULL),
(19, 20, NULL, NULL, '2025-07-01 13:36:24', '225.31.223.215', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Zaragoza', '41.64880000', '-0.88910000', NULL, 'España', 'Zaragoza', NULL),
(21, 20, NULL, NULL, '2025-06-29 13:36:42', '131.18.165.71', 'Mozilla/5.0 Test Browser', NULL, 'It', 'Roma', '41.90280000', '12.49640000', NULL, 'Italia', 'Roma', NULL),
(23, 20, NULL, NULL, '2025-06-20 13:36:42', '131.98.28.133', 'Mozilla/5.0 Test Browser', NULL, 'Re', 'Londres', '51.50740000', '-0.12780000', NULL, 'Reino Unido', 'Londres', NULL),
(25, 20, NULL, NULL, '2025-07-04 13:36:42', '151.1.37.148', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(27, 20, NULL, NULL, '2025-06-20 13:36:42', '208.40.90.186', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(29, 20, NULL, NULL, '2025-06-25 13:36:42', '21.49.87.92', 'Mozilla/5.0 Test Browser', NULL, 'Al', 'Berlín', '52.52000000', '13.40500000', NULL, 'Alemania', 'Berlín', NULL),
(37, 20, NULL, NULL, '2025-06-12 13:36:42', '209.154.57.46', 'Mozilla/5.0 Test Browser', NULL, 'Pe', 'Lima', '-12.04640000', '-77.04280000', NULL, 'Perú', 'Lima', NULL),
(43, 20, NULL, NULL, '2025-06-14 13:36:42', '153.170.91.63', 'Mozilla/5.0 Test Browser', NULL, 'Br', 'São Paulo', '-23.55050000', '-46.63330000', NULL, 'Brasil', 'São Paulo', NULL),
(44, 20, NULL, NULL, '2025-06-17 13:36:42', '89.110.87.126', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Palma', '39.56960000', '2.65020000', NULL, 'España', 'Palma', NULL),
(46, 20, NULL, NULL, '2025-06-24 13:36:42', '157.112.165.238', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Málaga', '36.72130000', '-4.42140000', NULL, 'España', 'Málaga', NULL),
(54, 20, NULL, NULL, '2025-06-25 13:36:42', '60.97.176.78', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Málaga', '36.72130000', '-4.42140000', NULL, 'España', 'Málaga', NULL),
(63, 20, NULL, NULL, '2025-06-19 13:36:42', '221.238.148.229', 'Mozilla/5.0 Test Browser', NULL, 'Es', 'Palma', '39.56960000', '2.65020000', NULL, 'España', 'Palma', NULL),
(68, 20, NULL, NULL, '2025-07-05 13:51:22', '172.232.234.164', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Murcia', '37.99220000', '-1.13070000', NULL, 'España', 'Murcia', NULL),
(70, 20, NULL, NULL, '2025-06-13 13:51:22', '16.60.5.75', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Bilbao', '43.26300000', '-2.93500000', NULL, 'España', 'Bilbao', NULL),
(71, 20, NULL, NULL, '2025-06-15 13:51:22', '84.144.33.79', 'Mozilla/5.0 Test Browser', NULL, 'BR', 'São Paulo', '-23.55050000', '-46.63330000', NULL, 'Brasil', 'São Paulo', NULL),
(73, 20, NULL, NULL, '2025-06-28 13:51:22', '23.83.127.97', 'Mozilla/5.0 Test Browser', NULL, 'GB', 'Londres', '51.50740000', '-0.12780000', NULL, 'Reino Unido', 'Londres', NULL),
(74, 20, NULL, NULL, '2025-06-17 13:51:22', '32.8.168.198', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Málaga', '36.72130000', '-4.42140000', NULL, 'España', 'Málaga', NULL),
(76, 20, NULL, NULL, '2025-07-08 13:51:22', '45.102.172.109', 'Mozilla/5.0 Test Browser', NULL, 'IT', 'Roma', '41.90280000', '12.49640000', NULL, 'Italia', 'Roma', NULL),
(77, 20, NULL, NULL, '2025-07-02 13:51:22', '239.24.131.133', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Las Palmas', '28.12350000', '-15.43630000', NULL, 'España', 'Las Palmas', NULL),
(83, 20, NULL, NULL, '2025-06-26 13:51:23', '222.119.3.120', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Barcelona', '41.38510000', '2.17340000', NULL, 'España', 'Barcelona', NULL),
(86, 20, NULL, NULL, '2025-07-02 13:51:23', '224.120.183.222', 'Mozilla/5.0 Test Browser', NULL, 'US', 'Nueva York', '40.71280000', '-74.00600000', NULL, 'Estados Unidos', 'Nueva York', NULL),
(89, 20, NULL, NULL, '2025-07-05 13:51:23', '133.244.145.206', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Murcia', '37.99220000', '-1.13070000', NULL, 'España', 'Murcia', NULL),
(91, 20, NULL, NULL, '2025-06-22 13:51:23', '204.49.80.10', 'Mozilla/5.0 Test Browser', NULL, 'US', 'Nueva York', '40.71280000', '-74.00600000', NULL, 'Estados Unidos', 'Nueva York', NULL),
(92, 20, NULL, NULL, '2025-07-03 13:51:23', '82.193.221.112', 'Mozilla/5.0 Test Browser', NULL, 'MX', 'México DF', '19.43260000', '-99.13320000', NULL, 'México', 'México DF', NULL),
(97, 20, NULL, NULL, '2025-07-04 13:51:23', '228.171.113.252', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Bilbao', '43.26300000', '-2.93500000', NULL, 'España', 'Bilbao', NULL),
(101, 20, NULL, NULL, '2025-06-28 13:51:23', '36.100.152.72', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(107, 20, NULL, NULL, '2025-06-27 13:51:23', '48.234.119.143', 'Mozilla/5.0 Test Browser', NULL, 'PE', 'Lima', '-12.04640000', '-77.04280000', NULL, 'Perú', 'Lima', NULL),
(109, 20, NULL, NULL, '2025-07-04 13:51:23', '157.90.4.16', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(116, 20, NULL, NULL, '2025-06-11 13:58:11', '21.146.11.153', 'Mozilla/5.0 Test Browser', NULL, 'FR', 'París', '48.85660000', '2.35220000', NULL, 'Francia', 'París', NULL),
(117, 20, NULL, NULL, '2025-06-13 13:58:11', '6.43.245.7', 'Mozilla/5.0 Test Browser', NULL, 'IT', 'Roma', '41.90280000', '12.49640000', NULL, 'Italia', 'Roma', NULL),
(119, 20, NULL, NULL, '2025-06-17 13:58:11', '178.169.249.132', 'Mozilla/5.0 Test Browser', NULL, 'PT', 'Lisboa', '38.72230000', '-9.13930000', NULL, 'Portugal', 'Lisboa', NULL),
(122, 20, NULL, NULL, '2025-06-20 13:58:11', '121.93.174.161', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(123, 20, NULL, NULL, '2025-06-23 13:58:11', '67.3.121.67', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Las Palmas', '28.12350000', '-15.43630000', NULL, 'España', 'Las Palmas', NULL),
(128, 20, NULL, NULL, '2025-06-16 13:58:11', '69.216.25.126', 'Mozilla/5.0 Test Browser', NULL, 'PE', 'Lima', '-12.04640000', '-77.04280000', NULL, 'Perú', 'Lima', NULL),
(131, 20, NULL, NULL, '2025-06-27 13:58:11', '173.116.229.188', 'Mozilla/5.0 Test Browser', NULL, 'AR', 'Buenos Aires', '-34.60370000', '-58.38160000', NULL, 'Argentina', 'Buenos Aires', NULL),
(135, 20, NULL, NULL, '2025-07-05 13:58:11', '55.225.34.253', 'Mozilla/5.0 Test Browser', NULL, 'FR', 'París', '48.85660000', '2.35220000', NULL, 'Francia', 'París', NULL),
(140, 20, NULL, NULL, '2025-06-22 13:58:11', '105.167.57.117', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Madrid', '40.41680000', '-3.70380000', NULL, 'España', 'Madrid', NULL),
(142, 20, NULL, NULL, '2025-06-20 13:58:11', '126.23.88.180', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Madrid', '40.41680000', '-3.70380000', NULL, 'España', 'Madrid', NULL),
(146, 20, NULL, NULL, '2025-06-23 13:58:11', '29.39.58.163', 'Mozilla/5.0 Test Browser', NULL, 'FR', 'París', '48.85660000', '2.35220000', NULL, 'Francia', 'París', NULL),
(147, 20, NULL, NULL, '2025-07-04 13:58:11', '125.136.99.65', 'Mozilla/5.0 Test Browser', NULL, 'IT', 'Roma', '41.90280000', '12.49640000', NULL, 'Italia', 'Roma', NULL),
(148, 20, NULL, NULL, '2025-07-04 13:58:12', '48.96.44.158', 'Mozilla/5.0 Test Browser', NULL, 'AR', 'Buenos Aires', '-34.60370000', '-58.38160000', NULL, 'Argentina', 'Buenos Aires', NULL),
(151, 20, NULL, NULL, '2025-06-12 13:58:12', '228.29.239.73', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(152, 20, NULL, NULL, '2025-07-02 13:58:12', '65.85.49.161', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Sevilla', '37.38910000', '-5.98450000', NULL, 'España', 'Sevilla', NULL),
(159, 20, NULL, NULL, '2025-07-02 13:58:12', '120.132.110.178', 'Mozilla/5.0 Test Browser', NULL, 'AR', 'Buenos Aires', '-34.60370000', '-58.38160000', NULL, 'Argentina', 'Buenos Aires', NULL),
(160, 20, NULL, NULL, '2025-06-23 13:58:12', '105.43.144.248', 'Mozilla/5.0 Test Browser', NULL, 'US', 'Nueva York', '40.71280000', '-74.00600000', NULL, 'Estados Unidos', 'Nueva York', NULL),
(162, 20, NULL, NULL, '2025-06-29 13:58:12', '239.64.172.8', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Madrid', '40.41680000', '-3.70380000', NULL, 'España', 'Madrid', NULL),
(168, 20, NULL, NULL, '2025-07-07 15:50:52', '233.19.232.197', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Bilbao', '43.26300000', '-2.93500000', NULL, 'España', 'Bilbao', NULL),
(171, 20, NULL, NULL, '2025-06-22 15:50:52', '147.199.5.187', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Palma', '39.56960000', '2.65020000', NULL, 'España', 'Palma', NULL),
(174, 20, NULL, NULL, '2025-06-30 15:50:52', '44.82.46.203', 'Mozilla/5.0 Test Browser', NULL, 'BR', 'São Paulo', '-23.55050000', '-46.63330000', NULL, 'Brasil', 'São Paulo', NULL),
(179, 20, NULL, NULL, '2025-06-17 15:50:52', '10.10.88.238', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(180, 20, NULL, NULL, '2025-06-09 15:50:52', '58.52.1.254', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Palma', '39.56960000', '2.65020000', NULL, 'España', 'Palma', NULL),
(185, 20, NULL, NULL, '2025-06-24 15:50:52', '59.190.37.151', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Málaga', '36.72130000', '-4.42140000', NULL, 'España', 'Málaga', NULL),
(187, 20, NULL, NULL, '2025-06-20 15:50:52', '235.203.201.35', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Murcia', '37.99220000', '-1.13070000', NULL, 'España', 'Murcia', NULL),
(193, 20, NULL, NULL, '2025-07-01 15:50:53', '11.214.167.145', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Sevilla', '37.38910000', '-5.98450000', NULL, 'España', 'Sevilla', NULL),
(198, 20, NULL, NULL, '2025-06-22 15:50:53', '109.56.28.33', 'Mozilla/5.0 Test Browser', NULL, 'AR', 'Buenos Aires', '-34.60370000', '-58.38160000', NULL, 'Argentina', 'Buenos Aires', NULL),
(199, 20, NULL, NULL, '2025-06-14 15:50:53', '132.154.183.221', 'Mozilla/5.0 Test Browser', NULL, 'MX', 'México DF', '19.43260000', '-99.13320000', NULL, 'México', 'México DF', NULL),
(204, 20, NULL, NULL, '2025-06-11 15:50:53', '63.113.27.74', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Barcelona', '41.38510000', '2.17340000', NULL, 'España', 'Barcelona', NULL),
(205, 20, NULL, NULL, '2025-07-04 15:50:53', '49.175.193.205', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Bilbao', '43.26300000', '-2.93500000', NULL, 'España', 'Bilbao', NULL),
(206, 20, NULL, NULL, '2025-07-06 15:50:53', '133.91.40.64', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Barcelona', '41.38510000', '2.17340000', NULL, 'España', 'Barcelona', NULL),
(209, 20, NULL, NULL, '2025-06-19 15:50:53', '179.223.221.174', 'Mozilla/5.0 Test Browser', NULL, 'BR', 'São Paulo', '-23.55050000', '-46.63330000', NULL, 'Brasil', 'São Paulo', NULL),
(211, 20, NULL, NULL, '2025-06-20 15:50:53', '16.218.250.115', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Palma', '39.56960000', '2.65020000', NULL, 'España', 'Palma', NULL),
(212, 20, NULL, NULL, '2025-06-15 15:50:53', '134.124.52.231', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Bilbao', '43.26300000', '-2.93500000', NULL, 'España', 'Bilbao', NULL),
(213, 20, NULL, NULL, '2025-07-08 15:50:53', '98.84.166.65', 'Mozilla/5.0 Test Browser', NULL, 'MX', 'México DF', '19.43260000', '-99.13320000', NULL, 'México', 'México DF', NULL),
(223, 23, NULL, NULL, '2025-06-22 08:36:30', '67.106.57.142', 'Mozilla/5.0 Test Browser', NULL, 'FR', 'París', '48.85660000', '2.35220000', NULL, 'Francia', 'París', NULL),
(232, 23, NULL, NULL, '2025-07-05 08:36:30', '98.244.185.122', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Palma', '39.56960000', '2.65020000', NULL, 'España', 'Palma', NULL),
(235, 20, NULL, NULL, '2025-07-10 08:36:31', '51.156.21.115', 'Mozilla/5.0 Test Browser', NULL, 'GB', 'Londres', '51.50740000', '-0.12780000', NULL, 'Reino Unido', 'Londres', NULL),
(236, 23, NULL, NULL, '2025-06-21 08:36:31', '58.29.164.4', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Málaga', '36.72130000', '-4.42140000', NULL, 'España', 'Málaga', NULL),
(240, 20, NULL, NULL, '2025-06-29 08:36:31', '182.147.160.241', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(244, 25, NULL, NULL, '2025-07-03 08:36:31', '254.76.112.68', 'Mozilla/5.0 Test Browser', NULL, 'DE', 'Berlín', '52.52000000', '13.40500000', NULL, 'Alemania', 'Berlín', NULL),
(248, 20, NULL, NULL, '2025-06-29 08:36:31', '167.153.244.2', 'Mozilla/5.0 Test Browser', NULL, 'MX', 'México DF', '19.43260000', '-99.13320000', NULL, 'México', 'México DF', NULL),
(249, 20, NULL, NULL, '2025-06-11 08:36:31', '159.119.212.57', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(250, 23, NULL, NULL, '2025-06-17 08:36:31', '19.225.138.86', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Zaragoza', '41.64880000', '-0.88910000', NULL, 'España', 'Zaragoza', NULL),
(251, 25, NULL, NULL, '2025-07-09 08:36:31', '47.28.38.24', 'Mozilla/5.0 Test Browser', NULL, 'MX', 'México DF', '19.43260000', '-99.13320000', NULL, 'México', 'México DF', NULL),
(255, 25, NULL, NULL, '2025-07-08 08:36:31', '154.244.74.227', 'Mozilla/5.0 Test Browser', NULL, 'IT', 'Roma', '41.90280000', '12.49640000', NULL, 'Italia', 'Roma', NULL),
(257, 20, NULL, NULL, '2025-06-14 08:36:31', '10.148.27.69', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Bilbao', '43.26300000', '-2.93500000', NULL, 'España', 'Bilbao', NULL),
(259, 20, NULL, NULL, '2025-07-08 08:36:31', '153.100.61.183', 'Mozilla/5.0 Test Browser', NULL, 'AR', 'Buenos Aires', '-34.60370000', '-58.38160000', NULL, 'Argentina', 'Buenos Aires', NULL),
(260, 20, NULL, NULL, '2025-06-23 08:36:31', '45.198.20.235', 'Mozilla/5.0 Test Browser', NULL, 'US', 'Nueva York', '40.71280000', '-74.00600000', NULL, 'Estados Unidos', 'Nueva York', NULL),
(261, 23, NULL, NULL, '2025-06-18 08:36:31', '67.61.185.186', 'Mozilla/5.0 Test Browser', NULL, 'DE', 'Berlín', '52.52000000', '13.40500000', NULL, 'Alemania', 'Berlín', NULL),
(264, 25, NULL, NULL, '2025-06-22 08:36:31', '212.20.98.159', 'Mozilla/5.0 Test Browser', NULL, 'FR', 'París', '48.85660000', '2.35220000', NULL, 'Francia', 'París', NULL),
(265, 23, NULL, NULL, '2025-07-02 08:36:31', '229.100.101.51', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Valencia', '39.46990000', '-0.37630000', NULL, 'España', 'Valencia', NULL),
(267, 20, NULL, NULL, '2025-06-15 08:36:31', '19.251.73.72', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Murcia', '37.99220000', '-1.13070000', NULL, 'España', 'Murcia', NULL),
(269, 25, NULL, NULL, '2025-06-14 08:36:31', '122.5.193.63', 'Mozilla/5.0 Test Browser', NULL, 'ES', 'Málaga', '36.72130000', '-4.42140000', NULL, 'España', 'Málaga', NULL),
(271, 20, NULL, NULL, '2025-07-11 08:39:23', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(272, 25, NULL, NULL, '2025-07-11 08:58:44', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(322, 23, NULL, NULL, '2025-07-12 14:25:01', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(356, 50, NULL, NULL, '2025-07-13 08:59:48', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(357, 51, NULL, NULL, '2025-07-13 09:10:36', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(358, 52, NULL, NULL, '2025-07-13 09:11:23', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(362, 53, NULL, NULL, '2025-07-13 09:56:02', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(363, 55, NULL, NULL, '2025-07-13 09:57:21', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(364, 55, NULL, NULL, '2025-07-13 10:00:19', '20.171.207.124', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(365, 54, NULL, NULL, '2025-07-13 10:00:19', '20.171.207.30', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(366, 53, NULL, NULL, '2025-07-13 10:00:21', '20.171.207.30', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(367, 52, NULL, NULL, '2025-07-13 10:00:23', '20.171.207.124', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(368, 51, NULL, NULL, '2025-07-13 10:00:25', '20.171.207.124', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(369, 53, NULL, NULL, '2025-07-13 10:36:56', '43.153.113.127', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(370, 52, NULL, NULL, '2025-07-13 10:37:38', '43.166.247.155', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(371, 54, NULL, NULL, '2025-07-13 10:46:15', '150.109.230.210', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(372, 55, NULL, NULL, '2025-07-13 10:48:35', '43.166.246.180', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(373, 51, NULL, NULL, '2025-07-13 10:56:07', '43.156.202.34', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(374, 56, NULL, NULL, '2025-07-13 11:39:21', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(375, 57, NULL, NULL, '2025-07-13 11:39:46', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(376, 57, NULL, NULL, '2025-07-13 11:40:39', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(377, 56, NULL, NULL, '2025-07-13 12:15:10', '43.157.156.190', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(378, 57, NULL, NULL, '2025-07-13 12:26:22', '43.131.36.84', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(381, 58, NULL, NULL, '2025-07-13 12:32:04', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.org/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(382, 59, NULL, NULL, '2025-07-13 13:20:36', '43.153.192.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(383, 60, NULL, NULL, '2025-07-13 13:26:50', '43.157.170.126', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(384, 58, NULL, NULL, '2025-07-13 13:46:16', '129.226.93.214', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(460, 89, NULL, NULL, '2025-07-17 20:49:51', '92.191.45.226', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(461, 91, NULL, NULL, '2025-07-17 21:07:09', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(462, 92, NULL, NULL, '2025-07-17 21:45:58', '199.16.157.182', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(463, 92, NULL, NULL, '2025-07-17 21:45:58', '199.16.157.183', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(464, 92, NULL, NULL, '2025-07-17 21:45:58', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(465, 92, NULL, NULL, '2025-07-17 21:46:32', '35.197.100.1', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(466, 92, NULL, NULL, '2025-07-17 21:46:33', '144.76.23.112', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', 'http://0ln.eu/PerdidoCaT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(467, 92, NULL, NULL, '2025-07-17 21:47:02', '199.16.157.180', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(468, 92, NULL, NULL, '2025-07-17 21:47:02', '199.16.157.180', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(469, 92, NULL, NULL, '2025-07-17 21:47:03', '199.16.157.183', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(470, 92, NULL, NULL, '2025-07-17 21:47:57', '199.16.157.181', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(471, 92, NULL, NULL, '2025-07-17 21:47:58', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(472, 92, NULL, NULL, '2025-07-17 21:48:40', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(473, 92, NULL, NULL, '2025-07-17 21:48:50', '199.16.157.181', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(474, 89, NULL, NULL, '2025-07-17 21:52:44', '49.51.203.164', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'http://0ln.eu/NyKMCD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(475, 92, NULL, NULL, '2025-07-17 21:57:38', '152.53.250.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(476, 92, NULL, NULL, '2025-07-17 21:59:39', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(477, 92, NULL, NULL, '2025-07-17 22:00:09', '199.16.157.182', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(478, 92, NULL, NULL, '2025-07-17 22:00:12', '34.19.70.45', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(479, 90, NULL, NULL, '2025-07-17 22:01:25', '162.62.213.187', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'http://0ln.eu/HBHXj6', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(480, 91, NULL, NULL, '2025-07-17 22:10:47', '170.106.180.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'http://0ln.eu/PhuVeJ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(481, 92, NULL, NULL, '2025-07-17 22:10:48', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(482, 92, NULL, NULL, '2025-07-17 22:11:18', '199.16.157.183', 'Twitterbot/1.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(483, 92, NULL, NULL, '2025-07-17 22:11:53', '35.197.14.98', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(484, 92, NULL, NULL, '2025-07-17 22:21:12', '43.153.15.51', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'http://0ln.eu/PerdidoCaT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(488, 91, NULL, NULL, '2025-07-18 00:48:55', '20.171.207.214', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(489, 90, NULL, NULL, '2025-07-18 00:49:00', '20.171.207.214', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(490, 92, NULL, NULL, '2025-07-18 00:49:04', '20.171.207.214', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(492, 92, NULL, NULL, '2025-07-18 00:49:10', '20.171.207.214', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(494, 97, NULL, NULL, '2025-07-18 11:25:14', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(496, 97, NULL, NULL, '2025-07-18 11:42:24', '20.171.207.214', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(497, 96, NULL, NULL, '2025-07-18 11:42:28', '20.171.207.214', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(499, 97, NULL, NULL, '2025-07-18 11:59:34', '43.135.145.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'http://0ln.eu/la_biblioteca_personal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(500, 96, NULL, NULL, '2025-07-18 12:09:01', '43.135.140.225', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'http://0ln.eu/Mi-Biblioteca_personal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(509, 97, NULL, NULL, '2025-07-18 14:10:29', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/terms/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(510, 97, NULL, NULL, '2025-07-18 14:10:51', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(511, 92, NULL, NULL, '2025-07-18 14:30:34', '54.198.55.229', 'Mozilla/5.0 (compatible)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(513, 89, NULL, NULL, '2025-07-18 14:30:49', '54.156.251.192', 'Mozilla/5.0 (compatible)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(514, 90, NULL, NULL, '2025-07-18 14:31:18', '54.198.55.229', 'Mozilla/5.0 (compatible)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(515, 91, NULL, NULL, '2025-07-18 14:31:18', '54.198.55.229', 'Mozilla/5.0 (compatible)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(516, 97, NULL, NULL, '2025-07-18 14:53:31', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(527, 92, NULL, NULL, '2025-07-18 15:17:37', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(528, 92, NULL, NULL, '2025-07-18 15:18:12', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(529, 92, NULL, NULL, '2025-07-18 15:18:13', '144.76.23.112', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', 'http://0ln.eu/PerdidoCaT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(545, 111, NULL, NULL, '2025-07-18 15:50:42', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(546, 111, NULL, NULL, '2025-07-18 15:51:15', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(547, 111, NULL, NULL, '2025-07-18 15:52:11', '149.56.25.49', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(548, 111, NULL, NULL, '2025-07-18 15:52:21', '104.198.5.130', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(549, 111, NULL, NULL, '2025-07-18 15:52:21', '104.198.5.130', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(550, 111, NULL, NULL, '2025-07-18 15:52:21', '104.198.5.130', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(551, 111, NULL, NULL, '2025-07-18 15:52:21', '104.198.5.130', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(552, 111, NULL, NULL, '2025-07-18 15:52:30', '144.76.23.169', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(553, 111, NULL, NULL, '2025-07-18 15:52:41', '15.235.114.226', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(554, 111, NULL, NULL, '2025-07-18 15:52:46', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(555, 111, NULL, NULL, '2025-07-18 15:52:51', '144.217.252.156', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(556, 111, NULL, NULL, '2025-07-18 15:56:43', '167.100.103.236', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(557, 111, NULL, NULL, '2025-07-18 15:56:44', '74.91.59.117', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(558, 92, NULL, NULL, '2025-07-18 16:01:48', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(559, 111, NULL, NULL, '2025-07-18 16:05:37', '43.135.133.194', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(560, 111, NULL, NULL, '2025-07-18 16:13:15', '34.19.76.185', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(561, 111, NULL, NULL, '2025-07-18 16:13:15', '34.19.76.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(562, 111, NULL, NULL, '2025-07-18 16:13:15', '34.19.76.185', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(563, 111, NULL, NULL, '2025-07-18 16:13:15', '34.19.76.185', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(564, 111, NULL, NULL, '2025-07-18 16:17:16', '54.39.177.173', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(565, 111, NULL, NULL, '2025-07-18 16:17:22', '34.83.22.128', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(566, 111, NULL, NULL, '2025-07-18 16:18:50', '5.154.91.217', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(567, 111, NULL, NULL, '2025-07-18 16:19:12', '90.160.103.141', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(568, 111, NULL, NULL, '2025-07-18 16:19:30', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(569, 111, NULL, NULL, '2025-07-18 16:19:30', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(570, 111, NULL, NULL, '2025-07-18 16:19:31', '34.203.135.93', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(571, 111, NULL, NULL, '2025-07-18 16:19:31', '34.203.135.93', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(572, 111, NULL, NULL, '2025-07-18 16:19:31', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(573, 111, NULL, NULL, '2025-07-18 16:19:31', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(574, 111, NULL, NULL, '2025-07-18 16:19:32', '34.203.135.93', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(575, 111, NULL, NULL, '2025-07-18 16:19:32', '34.203.135.93', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(576, 111, NULL, NULL, '2025-07-18 16:20:37', '88.12.231.232', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(577, 111, NULL, NULL, '2025-07-18 16:20:46', '88.0.132.40', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1.1 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(578, 111, NULL, NULL, '2025-07-18 16:20:49', '34.53.95.38', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(579, 111, NULL, NULL, '2025-07-18 16:20:49', '34.53.95.38', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(580, 111, NULL, NULL, '2025-07-18 16:20:49', '34.53.95.38', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(581, 111, NULL, NULL, '2025-07-18 16:20:50', '34.53.95.38', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(582, 111, NULL, NULL, '2025-07-18 16:22:18', '87.217.73.63', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(583, 111, NULL, NULL, '2025-07-18 16:22:29', '188.26.209.218', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(584, 111, NULL, NULL, '2025-07-18 16:23:17', '176.83.239.228', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(585, 111, NULL, NULL, '2025-07-18 16:27:51', '35.197.100.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(586, 111, NULL, NULL, '2025-07-18 16:27:51', '35.197.100.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(587, 111, NULL, NULL, '2025-07-18 16:27:51', '35.197.100.1', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(588, 111, NULL, NULL, '2025-07-18 16:27:51', '35.197.100.1', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(589, 111, NULL, NULL, '2025-07-18 16:28:08', '147.136.252.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(590, 111, NULL, NULL, '2025-07-18 16:28:33', '34.169.197.18', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(591, 111, NULL, NULL, '2025-07-18 16:30:27', '147.136.252.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(592, 111, NULL, NULL, '2025-07-18 16:30:29', '147.136.252.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(593, 111, NULL, NULL, '2025-07-18 16:31:51', '95.39.236.190', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(595, 111, NULL, NULL, '2025-07-18 16:37:49', '34.169.197.18', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(596, 111, NULL, NULL, '2025-07-18 16:37:49', '34.169.197.18', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(597, 111, NULL, NULL, '2025-07-18 16:37:49', '34.169.197.18', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(598, 111, NULL, NULL, '2025-07-18 16:37:49', '34.169.197.18', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(599, 111, NULL, NULL, '2025-07-18 16:37:49', '34.169.197.18', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(600, 111, NULL, NULL, '2025-07-18 16:47:57', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(601, 111, NULL, NULL, '2025-07-18 16:48:36', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(602, 111, NULL, NULL, '2025-07-18 17:01:52', '34.53.95.38', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(603, 111, NULL, NULL, '2025-07-18 17:01:52', '34.53.95.38', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(604, 111, NULL, NULL, '2025-07-18 17:01:52', '34.53.95.38', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(605, 111, NULL, NULL, '2025-07-18 17:01:52', '34.53.95.38', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(606, 111, NULL, NULL, '2025-07-18 17:12:13', '34.19.76.185', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(607, 111, NULL, NULL, '2025-07-18 17:32:59', '34.169.230.37', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(608, 111, NULL, NULL, '2025-07-18 17:33:02', '34.169.230.37', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(609, 111, NULL, NULL, '2025-07-18 17:33:02', '34.169.230.37', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(610, 111, NULL, NULL, '2025-07-18 17:33:02', '34.169.230.37', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(611, 111, NULL, NULL, '2025-07-18 17:50:14', '91.116.97.95', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_11 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(612, 111, NULL, NULL, '2025-07-18 17:50:30', '172.225.173.152', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(613, 111, NULL, NULL, '2025-07-18 17:51:20', '35.247.72.17', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(614, 111, NULL, NULL, '2025-07-18 17:51:21', '35.247.72.17', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(615, 111, NULL, NULL, '2025-07-18 17:51:21', '35.247.72.17', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(616, 111, NULL, NULL, '2025-07-18 17:51:21', '35.247.72.17', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(617, 111, NULL, NULL, '2025-07-18 17:51:35', '84.76.219.212', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(618, 111, NULL, NULL, '2025-07-18 17:52:11', '146.75.182.18', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(619, 111, NULL, NULL, '2025-07-18 17:58:46', '2.155.38.214', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(620, 111, NULL, NULL, '2025-07-18 17:59:35', '34.168.229.191', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(621, 111, NULL, NULL, '2025-07-18 17:59:35', '34.168.229.191', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(622, 111, NULL, NULL, '2025-07-18 17:59:35', '34.168.229.191', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(623, 111, NULL, NULL, '2025-07-18 18:08:21', '104.28.88.130', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(624, 111, NULL, NULL, '2025-07-18 18:33:51', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(625, 111, NULL, NULL, '2025-07-18 18:38:04', '92.177.88.153', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(626, 111, NULL, NULL, '2025-07-18 18:38:27', '80.26.163.101', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/22F76 Twitter for iPhone/11.6', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(627, 111, NULL, NULL, '2025-07-18 18:38:42', '207.248.125.96', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(628, 111, NULL, NULL, '2025-07-18 18:38:53', '34.83.93.88', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(629, 111, NULL, NULL, '2025-07-18 18:38:53', '34.83.93.88', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(630, 111, NULL, NULL, '2025-07-18 18:38:53', '34.83.93.88', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(631, 111, NULL, NULL, '2025-07-18 18:38:53', '34.83.93.88', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(632, 111, NULL, NULL, '2025-07-18 18:38:59', '83.54.155.176', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(633, 111, NULL, NULL, '2025-07-18 18:38:59', '146.75.182.19', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(634, 111, NULL, NULL, '2025-07-18 18:39:15', '81.33.50.97', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/22F76 Twitter for iPhone/11.7.5', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(635, 111, NULL, NULL, '2025-07-18 18:39:41', '80.103.26.245', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/22F76 Twitter for iPhone/11.8.5', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(636, 111, NULL, NULL, '2025-07-18 18:39:53', '62.42.20.84', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(637, 111, NULL, NULL, '2025-07-18 18:40:06', '2.140.217.149', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `click_stats` (`id`, `url_id`, `user_id`, `session_id`, `clicked_at`, `ip_address`, `user_agent`, `referer`, `country_code`, `region`, `latitude`, `longitude`, `timezone`, `country`, `city`, `accessed_domain`) VALUES
(638, 111, NULL, NULL, '2025-07-18 18:40:30', '37.10.135.81', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(639, 111, NULL, NULL, '2025-07-18 18:40:54', '77.209.84.181', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(640, 111, NULL, NULL, '2025-07-18 18:41:01', '81.39.182.137', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(641, 111, NULL, NULL, '2025-07-18 18:41:04', '185.153.167.230', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(642, 111, NULL, NULL, '2025-07-18 18:41:58', '85.52.230.5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(643, 111, NULL, NULL, '2025-07-18 18:42:09', '88.28.10.199', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(644, 111, NULL, NULL, '2025-07-18 18:42:56', '104.28.88.130', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(645, 111, NULL, NULL, '2025-07-18 18:43:12', '34.83.93.88', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(646, 111, NULL, NULL, '2025-07-18 18:44:03', '178.139.170.159', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(647, 111, NULL, NULL, '2025-07-18 18:45:07', '141.195.144.154', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(648, 111, NULL, NULL, '2025-07-18 18:47:53', '79.117.199.222', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(649, 111, NULL, NULL, '2025-07-18 18:47:57', '157.97.80.222', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(650, 111, NULL, NULL, '2025-07-18 18:48:54', '181.192.69.36', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(651, 111, NULL, NULL, '2025-07-18 18:49:36', '5.225.136.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(652, 111, NULL, NULL, '2025-07-18 18:50:41', '45.187.5.210', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(653, 111, NULL, NULL, '2025-07-18 18:50:55', '34.145.77.104', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(654, 111, NULL, NULL, '2025-07-18 18:50:55', '34.145.77.104', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(655, 111, NULL, NULL, '2025-07-18 18:50:55', '34.145.77.104', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(656, 111, NULL, NULL, '2025-07-18 18:50:55', '34.145.77.104', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(657, 111, NULL, NULL, '2025-07-18 18:52:28', '79.116.138.142', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(658, 111, NULL, NULL, '2025-07-18 18:53:06', '79.156.4.66', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(659, 111, NULL, NULL, '2025-07-18 19:01:35', '46.136.229.53', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(660, 111, NULL, NULL, '2025-07-18 19:06:55', '46.222.5.147', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(661, 111, NULL, NULL, '2025-07-18 19:10:45', '79.147.169.102', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(662, 111, NULL, NULL, '2025-07-18 19:16:52', '80.166.133.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(663, 111, NULL, NULL, '2025-07-18 19:27:25', '143.131.210.5', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(664, 111, NULL, NULL, '2025-07-18 19:31:13', '34.168.206.203', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(665, 111, NULL, NULL, '2025-07-18 19:31:13', '34.168.206.203', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(666, 111, NULL, NULL, '2025-07-18 19:31:13', '34.168.206.203', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(667, 111, NULL, NULL, '2025-07-18 19:31:13', '34.168.206.203', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(668, 111, NULL, NULL, '2025-07-18 19:51:54', '85.12.30.193', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.3124.85', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(669, 111, NULL, NULL, '2025-07-18 20:05:07', '79.116.166.112', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(670, 111, NULL, NULL, '2025-07-18 20:05:21', '90.164.11.75', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(671, 111, NULL, NULL, '2025-07-18 20:47:54', '95.127.11.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(672, 111, NULL, NULL, '2025-07-18 20:48:40', '34.53.6.100', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(673, 111, NULL, NULL, '2025-07-18 20:48:40', '34.53.6.100', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(674, 111, NULL, NULL, '2025-07-18 20:48:40', '34.53.6.100', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(675, 111, NULL, NULL, '2025-07-18 20:48:40', '34.53.6.100', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(676, 111, NULL, NULL, '2025-07-18 20:59:00', '83.36.168.80', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(677, 111, NULL, NULL, '2025-07-18 20:59:30', '34.82.206.202', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(678, 111, NULL, NULL, '2025-07-18 20:59:30', '34.82.206.202', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(679, 111, NULL, NULL, '2025-07-18 20:59:30', '34.82.206.202', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(680, 111, NULL, NULL, '2025-07-18 20:59:30', '34.82.206.202', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(681, 111, NULL, NULL, '2025-07-18 21:24:16', '172.59.69.52', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(682, 111, NULL, NULL, '2025-07-18 21:29:31', '104.28.34.162', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(683, 111, NULL, NULL, '2025-07-18 21:45:53', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(684, 111, NULL, NULL, '2025-07-18 21:55:01', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(685, 111, NULL, NULL, '2025-07-18 21:55:37', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(686, 111, NULL, NULL, '2025-07-18 21:55:56', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(687, 111, NULL, NULL, '2025-07-18 21:57:17', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(688, 111, NULL, NULL, '2025-07-18 21:57:59', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(689, 111, NULL, NULL, '2025-07-18 21:58:40', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(690, 111, NULL, NULL, '2025-07-18 22:01:10', '34.168.229.191', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(691, 111, NULL, NULL, '2025-07-18 22:01:10', '34.168.229.191', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(692, 111, NULL, NULL, '2025-07-18 22:01:10', '34.168.229.191', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(693, 111, NULL, NULL, '2025-07-18 22:01:10', '34.168.229.191', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(694, 112, NULL, NULL, '2025-07-18 22:14:58', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(695, 111, NULL, NULL, '2025-07-18 22:16:26', '172.226.116.45', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(696, 112, NULL, NULL, '2025-07-18 22:16:39', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(697, 112, NULL, NULL, '2025-07-18 22:16:46', '34.82.206.202', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(698, 112, NULL, NULL, '2025-07-18 22:16:46', '34.82.206.202', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(699, 112, NULL, NULL, '2025-07-18 22:16:46', '34.82.206.202', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(700, 111, NULL, NULL, '2025-07-18 22:16:46', '34.82.206.202', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(701, 112, NULL, NULL, '2025-07-18 22:16:46', '34.82.206.202', '', 'https://t.co/ZboQjOYVhl', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(702, 112, NULL, NULL, '2025-07-18 22:16:50', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(703, 112, NULL, NULL, '2025-07-18 22:16:52', '144.76.23.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(704, 112, NULL, NULL, '2025-07-18 22:17:09', '51.161.115.227', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(705, 112, NULL, NULL, '2025-07-18 22:18:08', '152.53.51.176', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 OPR/118.0.0.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(708, 112, NULL, NULL, '2025-07-18 22:23:31', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(715, 111, NULL, NULL, '2025-07-18 22:34:39', '170.253.13.50', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(716, 111, NULL, NULL, '2025-07-18 22:42:55', '54.39.177.48', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(717, 111, NULL, NULL, '2025-07-18 22:43:10', '35.247.72.17', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(718, 111, NULL, NULL, '2025-07-18 22:43:10', '35.247.72.17', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(719, 111, NULL, NULL, '2025-07-18 22:43:10', '35.247.72.17', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(720, 111, NULL, NULL, '2025-07-18 22:43:10', '35.247.72.17', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(721, 112, NULL, NULL, '2025-07-18 23:02:10', '43.166.131.228', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(723, 112, NULL, NULL, '2025-07-18 23:03:22', '43.153.96.233', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'https://0ln.eu/d8KRpq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(725, 111, NULL, NULL, '2025-07-19 00:39:57', '90.167.243.194', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(726, 111, NULL, NULL, '2025-07-19 00:59:14', '81.40.90.57', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(727, 111, NULL, NULL, '2025-07-19 03:19:09', '34.83.185.236', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(728, 111, NULL, NULL, '2025-07-19 03:19:09', '34.83.185.236', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(729, 111, NULL, NULL, '2025-07-19 03:19:09', '34.83.185.236', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(730, 111, NULL, NULL, '2025-07-19 03:19:09', '34.83.185.236', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(731, 111, NULL, NULL, '2025-07-19 04:28:24', '149.102.239.233', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(732, 112, NULL, NULL, '2025-07-19 06:12:58', '94.16.31.222', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(733, 111, NULL, NULL, '2025-07-19 07:11:16', '84.126.242.6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(734, 111, NULL, NULL, '2025-07-19 07:11:51', '31.221.213.221', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.4 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(735, 111, NULL, NULL, '2025-07-19 07:35:54', '79.117.226.226', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(736, 111, NULL, NULL, '2025-07-19 07:38:43', '84.124.214.249', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(737, 111, NULL, NULL, '2025-07-19 07:47:58', '178.139.162.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(739, 111, NULL, NULL, '2025-07-19 09:29:31', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(741, 112, NULL, NULL, '2025-07-19 09:43:52', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(742, 111, NULL, NULL, '2025-07-19 10:04:53', '34.19.57.124', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(743, 111, NULL, NULL, '2025-07-19 10:04:53', '34.19.57.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(744, 111, NULL, NULL, '2025-07-19 10:04:53', '34.19.57.124', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(745, 111, NULL, NULL, '2025-07-19 10:04:53', '34.19.57.124', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(746, 112, NULL, NULL, '2025-07-19 12:06:36', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(747, 111, NULL, NULL, '2025-07-19 12:38:10', '80.30.158.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(748, 92, NULL, NULL, '2025-07-19 13:19:17', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(749, 92, NULL, NULL, '2025-07-19 13:19:50', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(750, 92, NULL, NULL, '2025-07-19 13:19:50', '34.83.114.211', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(751, 111, NULL, NULL, '2025-07-19 14:18:39', '46.132.90.201', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.3351.77', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(752, 111, NULL, NULL, '2025-07-19 14:54:57', '35.230.13.109', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(753, 111, NULL, NULL, '2025-07-19 14:54:57', '35.230.13.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(754, 111, NULL, NULL, '2025-07-19 14:54:57', '35.230.13.109', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(755, 111, NULL, NULL, '2025-07-19 14:54:57', '35.230.13.109', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(756, 92, NULL, NULL, '2025-07-19 15:13:42', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(757, 92, NULL, NULL, '2025-07-19 15:14:14', '34.83.114.211', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(758, 92, NULL, NULL, '2025-07-19 15:21:30', '104.28.88.133', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(759, 92, NULL, NULL, '2025-07-19 15:34:26', '35.199.164.197', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(760, 112, NULL, NULL, '2025-07-19 16:12:01', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(761, 111, NULL, NULL, '2025-07-19 16:29:30', '35.203.150.228', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(762, 111, NULL, NULL, '2025-07-19 16:29:30', '35.203.150.228', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(763, 111, NULL, NULL, '2025-07-19 16:29:30', '35.203.150.228', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(764, 111, NULL, NULL, '2025-07-19 16:29:30', '35.203.150.228', '', 'https://t.co/b2Fhgbxe4F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(765, 111, NULL, NULL, '2025-07-19 16:57:37', '54.198.55.229', 'Mozilla/5.0 (compatible)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(766, 111, NULL, NULL, '2025-07-19 16:57:37', '54.156.251.192', 'Mozilla/5.0 (compatible)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(767, 112, NULL, NULL, '2025-07-19 17:00:46', '20.171.207.134', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(768, 57, NULL, NULL, '2025-07-19 17:37:20', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(769, 111, NULL, NULL, '2025-07-19 18:48:35', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(770, 92, NULL, NULL, '2025-07-19 19:55:36', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(771, 92, NULL, NULL, '2025-07-19 19:56:08', '35.203.150.228', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(772, 92, NULL, NULL, '2025-07-19 19:59:08', '176.82.134.66', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/rSjeywjWzU', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(773, 92, NULL, NULL, '2025-07-19 20:16:58', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(774, 92, NULL, NULL, '2025-07-19 20:17:14', '3.235.122.148', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(775, 92, NULL, NULL, '2025-07-19 20:17:14', '3.235.122.148', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(776, 92, NULL, NULL, '2025-07-19 20:17:15', '34.203.135.93', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(777, 92, NULL, NULL, '2025-07-19 20:17:15', '34.203.135.93', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(778, 92, NULL, NULL, '2025-07-19 20:17:15', '34.203.135.93', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(779, 92, NULL, NULL, '2025-07-19 20:17:15', '3.235.122.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(780, 92, NULL, NULL, '2025-07-19 20:17:15', '34.203.135.93', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(781, 92, NULL, NULL, '2025-07-19 20:17:15', '3.235.122.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(782, 92, NULL, NULL, '2025-07-19 20:17:30', '34.168.69.118', '', 'https://t.co/3koa3VkmQn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(783, 112, NULL, NULL, '2025-07-19 21:30:46', '37.114.33.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 OPR/118.0.0.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(785, 117, NULL, NULL, '2025-07-20 01:16:59', '43.133.139.6', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(787, 116, NULL, NULL, '2025-07-20 01:33:12', '43.166.244.192', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(790, 111, NULL, NULL, '2025-07-20 06:01:37', '88.9.171.142', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(792, 111, NULL, NULL, '2025-07-20 16:17:36', '88.11.217.248', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(793, 111, NULL, NULL, '2025-07-20 17:44:45', '217.198.193.76', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(795, 112, NULL, NULL, '2025-07-20 21:56:45', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(797, 112, NULL, NULL, '2025-07-21 03:49:39', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(798, 112, NULL, NULL, '2025-07-21 03:50:10', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(799, 96, NULL, NULL, '2025-07-21 03:50:46', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(813, 132, NULL, NULL, '2025-07-21 10:47:41', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(814, 132, NULL, NULL, '2025-07-21 10:55:22', '170.106.110.146', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(815, 136, NULL, NULL, '2025-07-21 12:37:36', '43.166.246.180', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(818, 139, NULL, NULL, '2025-07-21 14:54:43', '43.157.38.131', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(819, 141, NULL, NULL, '2025-07-21 15:04:26', '129.226.93.214', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(825, 111, NULL, NULL, '2025-07-21 20:43:39', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(826, 97, NULL, NULL, '2025-07-21 20:56:22', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(827, 152, NULL, NULL, '2025-07-21 23:14:15', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(830, 152, NULL, NULL, '2025-07-22 01:25:45', '43.153.204.189', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(833, 152, NULL, NULL, '2025-07-22 02:56:02', '20.171.207.33', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(839, 111, NULL, NULL, '2025-07-22 11:32:29', '20.171.207.69', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(844, 160, NULL, NULL, '2025-07-22 14:29:13', '43.153.62.161', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(845, 111, NULL, NULL, '2025-07-22 16:27:59', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/gestor.php', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(846, 139, NULL, NULL, '2025-07-22 20:52:44', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(848, 152, NULL, NULL, '2025-07-22 22:00:48', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(849, 97, NULL, NULL, '2025-07-22 23:00:47', '54.156.251.192', 'Mozilla/5.0 (compatible)', 'direct', 'US', NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(850, 161, NULL, NULL, '2025-07-23 00:55:14', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(851, 91, NULL, NULL, '2025-07-23 01:18:54', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(852, 161, NULL, NULL, '2025-07-23 02:55:02', '170.106.72.178', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'direct', 'US', NULL, NULL, NULL, NULL, 'United States', 'Santa Clara', NULL),
(853, 96, NULL, NULL, '2025-07-23 12:12:01', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'direct', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(854, 152, NULL, NULL, '2025-07-23 12:12:20', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'direct', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(855, 132, NULL, NULL, '2025-07-23 12:41:23', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'direct', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(856, 164, NULL, NULL, '2025-07-23 13:01:59', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'direct', 'ES', NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(860, 166, NULL, NULL, '2025-07-23 13:06:56', '54.83.9.180', 'help@dataminr.com', 'direct', 'US', NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(861, 166, NULL, NULL, '2025-07-23 13:07:31', '144.76.14.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', 'direct', 'DE', NULL, NULL, NULL, NULL, 'Germany', 'Falkenstein', NULL),
(862, 166, NULL, NULL, '2025-07-23 13:07:53', '158.69.27.238', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', 'CA', NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(863, 166, NULL, NULL, '2025-07-23 13:08:25', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(864, 166, NULL, NULL, '2025-07-23 13:09:01', '34.169.117.110', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(865, 166, NULL, NULL, '2025-07-23 13:09:01', '34.169.117.110', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(866, 166, NULL, NULL, '2025-07-23 13:09:01', '34.169.117.110', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(867, 166, NULL, NULL, '2025-07-23 13:09:01', '34.169.117.110', '', 'https://t.co/sSJqBq1hVD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(868, 166, NULL, NULL, '2025-07-23 13:09:11', '152.53.245.21', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:136.0) Gecko/20100101 Firefox/136.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(869, 164, NULL, NULL, '2025-07-23 13:15:25', '150.109.230.210', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(870, 167, NULL, NULL, '2025-07-23 13:16:31', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(871, 167, NULL, NULL, '2025-07-23 13:17:04', '34.105.123.7', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(872, 167, NULL, NULL, '2025-07-23 13:17:05', '34.105.123.7', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(873, 167, NULL, NULL, '2025-07-23 13:17:08', '144.76.22.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(874, 167, NULL, NULL, '2025-07-23 13:17:19', '152.53.67.235', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:136.0) Gecko/20100101 Firefox/136.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(875, 167, NULL, NULL, '2025-07-23 13:17:37', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(876, 167, NULL, NULL, '2025-07-23 13:21:07', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(877, 167, NULL, NULL, '2025-07-23 13:47:47', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(878, 167, NULL, NULL, '2025-07-23 13:48:01', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(879, 167, NULL, NULL, '2025-07-23 13:48:01', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(880, 167, NULL, NULL, '2025-07-23 13:48:01', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(881, 167, NULL, NULL, '2025-07-23 13:48:01', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(882, 167, NULL, NULL, '2025-07-23 13:48:02', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(883, 167, NULL, NULL, '2025-07-23 13:48:02', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(884, 167, NULL, NULL, '2025-07-23 13:48:03', '52.22.37.162', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(885, 167, NULL, NULL, '2025-07-23 13:48:03', '52.22.37.162', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(886, 167, NULL, NULL, '2025-07-23 13:48:03', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(887, 167, NULL, NULL, '2025-07-23 13:48:21', '34.83.200.114', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(888, 167, NULL, NULL, '2025-07-23 13:48:29', '95.22.122.73', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36 OPR/90.0.0.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(889, 167, NULL, NULL, '2025-07-23 13:48:32', '212.183.213.73', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(890, 167, NULL, NULL, '2025-07-23 13:48:41', '141.98.37.139', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(891, 167, NULL, NULL, '2025-07-23 13:49:08', '92.187.91.125', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(892, 167, NULL, NULL, '2025-07-23 13:49:08', '176.83.195.159', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(893, 167, NULL, NULL, '2025-07-23 13:49:35', '88.21.160.24', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(894, 167, NULL, NULL, '2025-07-23 13:49:40', '84.126.89.124', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(895, 167, NULL, NULL, '2025-07-23 13:50:24', '78.30.13.13', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(896, 167, NULL, NULL, '2025-07-23 13:50:38', '2.139.0.227', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(897, 167, NULL, NULL, '2025-07-23 13:51:20', '88.28.18.180', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(898, 167, NULL, NULL, '2025-07-23 13:51:30', '81.37.14.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(899, 167, NULL, NULL, '2025-07-23 13:56:42', '79.150.64.41', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(900, 167, NULL, NULL, '2025-07-23 13:56:51', '93.156.218.65', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(901, 167, NULL, NULL, '2025-07-23 13:58:54', '62.151.236.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(902, 167, NULL, NULL, '2025-07-23 13:59:12', '93.156.205.133', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(903, 167, NULL, NULL, '2025-07-23 13:59:12', '84.125.69.201', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(904, 166, NULL, NULL, '2025-07-23 13:59:17', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(905, 167, NULL, NULL, '2025-07-23 14:01:58', '216.146.29.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 OPR/118.0.0.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(906, 167, NULL, NULL, '2025-07-23 14:02:10', '34.82.194.163', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(907, 167, NULL, NULL, '2025-07-23 14:03:15', '54.39.177.173', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(908, 167, NULL, NULL, '2025-07-23 14:05:40', '46.25.51.157', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(909, 167, NULL, NULL, '2025-07-23 14:14:46', '148.252.141.147', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(910, 167, NULL, NULL, '2025-07-23 14:15:24', '34.168.139.90', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(911, 166, NULL, NULL, '2025-07-23 14:18:05', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(912, 167, NULL, NULL, '2025-07-23 14:19:31', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(913, 167, NULL, NULL, '2025-07-23 14:20:37', '200.83.124.114', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(914, 167, NULL, NULL, '2025-07-23 14:23:46', '77.229.179.18', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(915, 167, NULL, NULL, '2025-07-23 14:24:07', '77.229.179.18', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(916, 167, NULL, NULL, '2025-07-23 14:32:35', '35.233.158.145', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(917, 167, NULL, NULL, '2025-07-23 14:36:02', '176.80.130.0', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(918, 167, NULL, NULL, '2025-07-23 14:38:31', '34.168.30.81', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `click_stats` (`id`, `url_id`, `user_id`, `session_id`, `clicked_at`, `ip_address`, `user_agent`, `referer`, `country_code`, `region`, `latitude`, `longitude`, `timezone`, `country`, `city`, `accessed_domain`) VALUES
(919, 112, NULL, NULL, '2025-07-23 14:48:16', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(920, 167, NULL, NULL, '2025-07-23 14:49:24', '88.5.54.128', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(921, 167, NULL, NULL, '2025-07-23 14:50:08', '34.83.178.82', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(922, 167, NULL, NULL, '2025-07-23 14:56:13', '88.1.177.235', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(923, 167, NULL, NULL, '2025-07-23 14:59:26', '31.4.209.239', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(924, 167, NULL, NULL, '2025-07-23 15:08:36', '176.83.43.251', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(925, 167, NULL, NULL, '2025-07-23 15:14:50', '43.163.104.54', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(926, 166, NULL, NULL, '2025-07-23 15:15:20', '43.163.104.54', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(927, 167, NULL, NULL, '2025-07-23 15:22:05', '81.40.59.138', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(928, 112, NULL, NULL, '2025-07-23 15:34:14', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(929, 167, NULL, NULL, '2025-07-23 15:39:08', '136.226.215.114', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(930, 167, NULL, NULL, '2025-07-23 15:40:27', '92.177.236.42', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(931, 112, NULL, NULL, '2025-07-23 15:41:36', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(932, 167, NULL, NULL, '2025-07-23 15:52:28', '34.145.80.135', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(933, 167, NULL, NULL, '2025-07-23 15:53:19', '149.74.250.96', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(934, 167, NULL, NULL, '2025-07-23 15:53:24', '35.197.81.120', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(935, 167, NULL, NULL, '2025-07-23 16:01:33', '85.55.185.5', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(936, 167, NULL, NULL, '2025-07-23 16:31:34', '90.160.92.60', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(937, 167, NULL, NULL, '2025-07-23 16:32:26', '34.145.80.135', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(938, 167, NULL, NULL, '2025-07-23 16:32:53', '185.183.106.106', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(939, 167, NULL, NULL, '2025-07-23 16:49:40', '46.222.28.203', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(940, 112, NULL, NULL, '2025-07-23 16:50:43', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(941, 167, NULL, NULL, '2025-07-23 16:52:08', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(942, 167, NULL, NULL, '2025-07-23 16:59:01', '35.227.169.37', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(943, 167, NULL, NULL, '2025-07-23 16:59:14', '34.53.95.38', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(944, 141, NULL, NULL, '2025-07-23 17:17:11', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(945, 167, NULL, NULL, '2025-07-23 17:28:44', '104.28.88.127', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(946, 167, NULL, NULL, '2025-07-23 17:28:55', '35.197.56.53', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(947, 167, NULL, NULL, '2025-07-23 17:29:30', '91.126.43.167', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(948, 167, NULL, NULL, '2025-07-23 17:30:46', '46.222.129.52', 'Mozilla/5.0 (Linux; Android 10; SM-A505FN) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Mobile Safari/537.36', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(949, 167, NULL, NULL, '2025-07-23 18:00:12', '31.4.222.255', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(950, 167, NULL, NULL, '2025-07-23 18:03:01', '88.14.235.170', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(951, 167, NULL, NULL, '2025-07-23 19:28:59', '2.140.226.31', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(952, 167, NULL, NULL, '2025-07-23 19:45:12', '34.145.80.135', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(953, 167, NULL, NULL, '2025-07-23 20:12:46', '95.124.137.219', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(954, 167, NULL, NULL, '2025-07-23 20:13:24', '35.197.81.120', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(955, 168, NULL, NULL, '2025-07-23 20:24:32', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(956, 168, NULL, NULL, '2025-07-23 20:24:46', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(957, 168, NULL, NULL, '2025-07-23 20:24:56', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(958, 168, NULL, NULL, '2025-07-23 20:25:06', '35.230.42.185', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(959, 168, NULL, NULL, '2025-07-23 20:25:06', '35.230.42.185', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(960, 168, NULL, NULL, '2025-07-23 20:25:06', '35.230.42.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(961, 168, NULL, NULL, '2025-07-23 20:25:06', '35.230.42.185', '', 'https://t.co/m1ppdmykcw', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(962, 168, NULL, NULL, '2025-07-23 20:25:06', '144.76.23.44', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(963, 168, NULL, NULL, '2025-07-23 20:25:45', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(964, 168, NULL, NULL, '2025-07-23 20:26:08', '54.39.177.173', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(965, 167, NULL, NULL, '2025-07-23 20:43:21', '78.30.32.124', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.118 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.37.1-gn', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(966, 167, NULL, NULL, '2025-07-23 21:01:25', '213.177.206.16', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(967, 167, NULL, NULL, '2025-07-23 21:01:42', '34.168.232.7', '', 'https://t.co/u5UNxFcEv0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(968, 167, NULL, NULL, '2025-07-23 21:06:21', '188.26.194.193', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(969, 168, NULL, NULL, '2025-07-23 21:36:52', '43.154.127.188', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(970, 169, NULL, NULL, '2025-07-23 21:43:47', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(971, 169, NULL, NULL, '2025-07-23 21:46:53', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(972, 169, NULL, NULL, '2025-07-23 21:47:16', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(973, 169, NULL, NULL, '2025-07-23 21:47:26', '104.198.98.65', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(974, 169, NULL, NULL, '2025-07-23 21:47:26', '104.198.98.65', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(975, 169, NULL, NULL, '2025-07-23 21:47:26', '104.198.98.65', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(976, 169, NULL, NULL, '2025-07-23 21:47:26', '104.198.98.65', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(977, 169, NULL, NULL, '2025-07-23 21:47:34', '144.76.22.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(978, 169, NULL, NULL, '2025-07-23 21:48:20', '54.39.107.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(979, 167, NULL, NULL, '2025-07-23 21:52:41', '90.170.191.252', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(980, 169, NULL, NULL, '2025-07-23 21:52:53', '74.91.59.17', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(981, 169, NULL, NULL, '2025-07-23 21:55:53', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(982, 169, NULL, NULL, '2025-07-23 22:08:47', '43.155.162.41', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(983, 169, NULL, NULL, '2025-07-23 22:52:34', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(984, 169, NULL, NULL, '2025-07-23 23:13:41', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(985, 169, NULL, NULL, '2025-07-23 23:14:02', '142.44.136.207', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(986, 169, NULL, NULL, '2025-07-23 23:14:14', '104.198.98.65', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(987, 169, NULL, NULL, '2025-07-23 23:14:14', '104.198.98.65', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(988, 169, NULL, NULL, '2025-07-23 23:14:14', '104.198.98.65', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(989, 169, NULL, NULL, '2025-07-23 23:14:14', '104.198.98.65', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(990, 169, NULL, NULL, '2025-07-23 23:15:01', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(991, 167, NULL, NULL, '2025-07-23 23:19:41', '83.45.190.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(992, 169, NULL, NULL, '2025-07-23 23:19:59', '162.251.71.245', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(993, 169, NULL, NULL, '2025-07-24 00:03:43', '20.171.207.189', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(994, 166, NULL, NULL, '2025-07-24 00:03:46', '20.171.207.189', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(995, 168, NULL, NULL, '2025-07-24 00:03:48', '20.171.207.189', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(996, 166, NULL, NULL, '2025-07-24 00:03:51', '20.171.207.189', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(997, 167, NULL, NULL, '2025-07-24 00:28:35', '20.171.207.204', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(998, 164, NULL, NULL, '2025-07-24 00:28:38', '20.171.207.204', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1001, 172, NULL, NULL, '2025-07-24 02:40:13', '124.156.157.91', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1005, 167, NULL, NULL, '2025-07-24 04:10:10', '80.29.202.12', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1006, 169, NULL, NULL, '2025-07-24 07:13:43', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1007, 169, NULL, NULL, '2025-07-24 07:13:58', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1008, 169, NULL, NULL, '2025-07-24 07:13:58', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1009, 169, NULL, NULL, '2025-07-24 07:13:58', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1010, 169, NULL, NULL, '2025-07-24 07:13:58', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1011, 169, NULL, NULL, '2025-07-24 07:14:09', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1012, 169, NULL, NULL, '2025-07-24 07:14:16', '34.82.3.127', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1013, 169, NULL, NULL, '2025-07-24 07:14:16', '34.82.3.127', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1014, 169, NULL, NULL, '2025-07-24 07:14:16', '34.82.3.127', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1015, 169, NULL, NULL, '2025-07-24 07:14:16', '34.82.3.127', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1016, 169, NULL, NULL, '2025-07-24 07:15:18', '192.99.44.95', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1017, 169, NULL, NULL, '2025-07-24 07:19:50', '172.102.204.75', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1018, 169, NULL, NULL, '2025-07-24 11:08:53', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1019, 169, NULL, NULL, '2025-07-24 11:09:16', '167.114.173.221', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1020, 169, NULL, NULL, '2025-07-24 11:09:27', '34.168.215.172', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1021, 169, NULL, NULL, '2025-07-24 11:09:27', '34.168.215.172', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1022, 169, NULL, NULL, '2025-07-24 11:09:27', '34.168.215.172', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1023, 169, NULL, NULL, '2025-07-24 11:09:27', '34.168.215.172', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1024, 169, NULL, NULL, '2025-07-24 11:09:55', '149.56.25.49', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1025, 169, NULL, NULL, '2025-07-24 11:15:11', '167.100.103.160', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1026, 169, NULL, NULL, '2025-07-24 11:58:57', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1027, 169, NULL, NULL, '2025-07-24 12:30:32', '51.79.77.186', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1028, 169, NULL, NULL, '2025-07-24 12:30:38', '34.168.163.140', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1029, 169, NULL, NULL, '2025-07-24 12:30:38', '34.168.163.140', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1030, 169, NULL, NULL, '2025-07-24 12:30:39', '34.168.163.140', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1031, 169, NULL, NULL, '2025-07-24 12:30:39', '34.168.163.140', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1032, 169, NULL, NULL, '2025-07-24 12:31:13', '51.161.84.125', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1033, 169, NULL, NULL, '2025-07-24 12:35:07', '167.100.103.26', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1034, 170, NULL, NULL, '2025-07-24 12:48:42', '43.159.138.217', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1035, 170, NULL, NULL, '2025-07-24 13:18:55', '20.171.207.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1038, 172, NULL, NULL, '2025-07-24 13:19:26', '20.171.207.242', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1039, 169, NULL, NULL, '2025-07-24 13:25:49', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1040, 177, NULL, NULL, '2025-07-24 13:45:30', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1041, 167, NULL, NULL, '2025-07-24 13:52:43', '79.148.86.139', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1042, 178, NULL, NULL, '2025-07-24 14:06:40', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1043, 178, NULL, NULL, '2025-07-24 14:06:47', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1044, 178, NULL, NULL, '2025-07-24 14:06:54', '144.76.23.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1045, 178, NULL, NULL, '2025-07-24 14:07:05', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1046, 178, NULL, NULL, '2025-07-24 14:07:15', '35.233.208.121', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1047, 178, NULL, NULL, '2025-07-24 14:07:15', '35.233.208.121', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1048, 178, NULL, NULL, '2025-07-24 14:07:15', '35.233.208.121', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1049, 178, NULL, NULL, '2025-07-24 14:07:15', '35.233.208.121', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1050, 178, NULL, NULL, '2025-07-24 14:07:49', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1051, 177, NULL, NULL, '2025-07-24 14:08:34', '43.153.104.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1052, 178, NULL, NULL, '2025-07-24 14:11:20', '54.39.177.173', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1053, 178, NULL, NULL, '2025-07-24 14:11:49', '142.4.217.87', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1054, 178, NULL, NULL, '2025-07-24 14:12:31', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1055, 178, NULL, NULL, '2025-07-24 14:12:42', '158.69.27.238', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1056, 178, NULL, NULL, '2025-07-24 14:12:43', '167.100.100.34', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1057, 178, NULL, NULL, '2025-07-24 14:12:45', '34.203.135.93', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1058, 178, NULL, NULL, '2025-07-24 14:12:45', '34.203.135.93', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1059, 178, NULL, NULL, '2025-07-24 14:12:45', '3.235.122.148', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1060, 178, NULL, NULL, '2025-07-24 14:12:45', '3.235.122.148', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1061, 178, NULL, NULL, '2025-07-24 14:12:54', '54.39.103.203', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1062, 178, NULL, NULL, '2025-07-24 14:13:21', '167.114.119.164', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1063, 178, NULL, NULL, '2025-07-24 14:15:40', '34.86.192.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Gecko/20100101 Firefox/134.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1064, 178, NULL, NULL, '2025-07-24 14:16:53', '34.175.190.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Gecko/20100101 Firefox/134.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1065, 178, NULL, NULL, '2025-07-24 14:17:34', '74.91.59.170', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1066, 178, NULL, NULL, '2025-07-24 14:18:11', '172.102.204.179', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1067, 177, NULL, NULL, '2025-07-24 14:21:35', '43.133.187.11', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1068, 178, NULL, NULL, '2025-07-24 14:23:14', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1069, 178, NULL, NULL, '2025-07-24 14:23:22', '34.168.130.50', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1070, 178, NULL, NULL, '2025-07-24 14:23:22', '34.168.130.50', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1071, 178, NULL, NULL, '2025-07-24 14:23:22', '34.168.130.50', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1072, 178, NULL, NULL, '2025-07-24 14:23:22', '34.168.130.50', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1073, 178, NULL, NULL, '2025-07-24 14:23:54', '192.99.101.184', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1074, 178, NULL, NULL, '2025-07-24 14:27:52', '74.91.57.33', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1075, 178, NULL, NULL, '2025-07-24 14:31:38', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1076, 178, NULL, NULL, '2025-07-24 14:31:57', '54.39.177.173', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1077, 178, NULL, NULL, '2025-07-24 14:32:00', '54.39.177.48', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1078, 178, NULL, NULL, '2025-07-24 14:32:11', '35.247.106.251', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1079, 178, NULL, NULL, '2025-07-24 14:32:11', '35.247.106.251', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1080, 178, NULL, NULL, '2025-07-24 14:32:11', '35.247.106.251', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1081, 178, NULL, NULL, '2025-07-24 14:32:37', '54.39.18.152', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1082, 178, NULL, NULL, '2025-07-24 14:33:43', '51.161.84.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1083, 178, NULL, NULL, '2025-07-24 14:35:34', '43.135.115.233', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1084, 178, NULL, NULL, '2025-07-24 14:38:01', '54.39.103.203', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1085, 178, NULL, NULL, '2025-07-24 14:38:08', '15.235.114.226', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1086, 178, NULL, NULL, '2025-07-24 14:38:11', '34.53.52.255', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1087, 178, NULL, NULL, '2025-07-24 14:38:11', '34.53.52.255', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1088, 178, NULL, NULL, '2025-07-24 14:38:11', '34.53.52.255', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1089, 178, NULL, NULL, '2025-07-24 14:38:11', '34.53.52.255', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1090, 178, NULL, NULL, '2025-07-24 14:38:53', '74.91.59.234', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1091, 178, NULL, NULL, '2025-07-24 14:39:02', '51.222.42.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1092, 178, NULL, NULL, '2025-07-24 14:39:10', '158.69.27.238', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1093, 169, NULL, NULL, '2025-07-24 14:43:21', '34.53.52.255', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1094, 169, NULL, NULL, '2025-07-24 14:43:21', '34.53.52.255', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1095, 169, NULL, NULL, '2025-07-24 14:43:21', '34.53.52.255', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1096, 169, NULL, NULL, '2025-07-24 14:43:21', '34.53.52.255', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1097, 178, NULL, NULL, '2025-07-24 14:43:28', '167.100.103.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1098, 178, NULL, NULL, '2025-07-24 14:43:34', '167.100.103.178', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1099, 169, NULL, NULL, '2025-07-24 14:43:44', '149.56.25.49', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1100, 178, NULL, NULL, '2025-07-24 14:43:52', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1101, 178, NULL, NULL, '2025-07-24 14:43:53', '54.166.229.137', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1102, 178, NULL, NULL, '2025-07-24 14:44:15', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1103, 178, NULL, NULL, '2025-07-24 14:44:24', '104.199.119.50', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1104, 169, NULL, NULL, '2025-07-24 14:44:36', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1105, 178, NULL, NULL, '2025-07-24 14:45:10', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1106, 178, NULL, NULL, '2025-07-24 14:48:52', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1107, 178, NULL, NULL, '2025-07-24 14:49:02', '35.197.10.123', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1108, 178, NULL, NULL, '2025-07-24 14:49:02', '35.197.10.123', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1109, 178, NULL, NULL, '2025-07-24 14:49:02', '35.197.10.123', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1110, 178, NULL, NULL, '2025-07-24 14:49:03', '192.99.101.184', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1111, 178, NULL, NULL, '2025-07-24 14:49:12', '35.233.174.13', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1112, 178, NULL, NULL, '2025-07-24 14:49:13', '35.233.174.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1113, 178, NULL, NULL, '2025-07-24 14:49:13', '35.233.174.13', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1114, 178, NULL, NULL, '2025-07-24 14:49:13', '35.233.174.13', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1115, 178, NULL, NULL, '2025-07-24 14:49:13', '192.99.44.57', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1116, 169, NULL, NULL, '2025-07-24 14:49:14', '104.218.197.18', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1117, 178, NULL, NULL, '2025-07-24 14:49:16', '144.217.74.133', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1118, 178, NULL, NULL, '2025-07-24 14:49:17', '167.100.103.191', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1119, 178, NULL, NULL, '2025-07-24 14:53:47', '74.91.58.31', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1120, 178, NULL, NULL, '2025-07-24 14:53:50', '74.91.58.33', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1121, 167, NULL, NULL, '2025-07-24 14:55:55', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1122, 178, NULL, NULL, '2025-07-24 14:58:43', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1123, 178, NULL, NULL, '2025-07-24 15:03:34', '142.4.217.87', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1124, 178, NULL, NULL, '2025-07-24 15:03:40', '35.247.106.251', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1125, 178, NULL, NULL, '2025-07-24 15:03:40', '35.247.106.251', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1126, 178, NULL, NULL, '2025-07-24 15:03:40', '35.247.106.251', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1127, 178, NULL, NULL, '2025-07-24 15:03:40', '35.247.106.251', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1128, 178, NULL, NULL, '2025-07-24 15:03:54', '192.99.19.229', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1129, 167, NULL, NULL, '2025-07-24 15:05:53', '95.124.222.175', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1130, 178, NULL, NULL, '2025-07-24 15:09:06', '172.102.204.112', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1131, 169, NULL, NULL, '2025-07-24 15:18:25', '54.39.50.77', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1132, 178, NULL, NULL, '2025-07-24 15:18:26', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1133, 178, NULL, NULL, '2025-07-24 15:18:33', '34.168.86.249', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1134, 178, NULL, NULL, '2025-07-24 15:18:33', '34.168.86.249', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1135, 178, NULL, NULL, '2025-07-24 15:18:33', '34.168.86.249', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1136, 178, NULL, NULL, '2025-07-24 15:18:33', '34.168.86.249', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1137, 169, NULL, NULL, '2025-07-24 15:18:36', '35.233.174.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1138, 169, NULL, NULL, '2025-07-24 15:18:36', '35.233.174.13', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1139, 169, NULL, NULL, '2025-07-24 15:18:36', '35.233.174.13', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1140, 169, NULL, NULL, '2025-07-24 15:18:36', '35.233.174.13', '', 'https://t.co/FvBv9zwDVn', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1141, 169, NULL, NULL, '2025-07-24 15:18:44', '192.99.44.57', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1142, 178, NULL, NULL, '2025-07-24 15:18:46', '51.161.117.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1143, 169, NULL, NULL, '2025-07-24 15:23:23', '74.91.58.31', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1144, 178, NULL, NULL, '2025-07-24 15:23:34', '74.91.57.165', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1145, 169, NULL, NULL, '2025-07-24 15:34:24', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1146, 178, NULL, NULL, '2025-07-24 15:54:30', '54.39.107.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1147, 178, NULL, NULL, '2025-07-24 15:54:42', '34.83.200.114', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1148, 178, NULL, NULL, '2025-07-24 15:54:42', '34.83.200.114', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1149, 178, NULL, NULL, '2025-07-24 15:54:42', '34.83.200.114', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1150, 178, NULL, NULL, '2025-07-24 15:54:42', '34.83.200.114', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1151, 178, NULL, NULL, '2025-07-24 15:54:49', '54.39.107.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1152, 178, NULL, NULL, '2025-07-24 15:59:22', '74.91.59.61', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1154, 178, NULL, NULL, '2025-07-24 16:47:41', '35.247.98.216', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `click_stats` (`id`, `url_id`, `user_id`, `session_id`, `clicked_at`, `ip_address`, `user_agent`, `referer`, `country_code`, `region`, `latitude`, `longitude`, `timezone`, `country`, `city`, `accessed_domain`) VALUES
(1155, 178, NULL, NULL, '2025-07-24 16:47:41', '35.247.98.216', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1156, 178, NULL, NULL, '2025-07-24 16:47:41', '35.247.98.216', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1157, 178, NULL, NULL, '2025-07-24 16:47:42', '35.247.98.216', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1158, 178, NULL, NULL, '2025-07-24 16:48:35', '104.28.34.175', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1159, 178, NULL, NULL, '2025-07-24 16:49:48', '158.69.55.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1160, 178, NULL, NULL, '2025-07-24 16:50:06', '51.222.42.127', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1161, 178, NULL, NULL, '2025-07-24 16:54:59', '54.166.229.137', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1162, 178, NULL, NULL, '2025-07-24 16:55:10', '167.100.103.90', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1163, 178, NULL, NULL, '2025-07-24 16:55:31', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1164, 178, NULL, NULL, '2025-07-24 16:55:32', '35.247.40.28', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1165, 178, NULL, NULL, '2025-07-24 16:55:32', '35.247.40.28', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1166, 178, NULL, NULL, '2025-07-24 16:55:32', '35.247.40.28', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1167, 178, NULL, NULL, '2025-07-24 16:55:32', '35.247.40.28', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1168, 178, NULL, NULL, '2025-07-24 16:55:37', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1169, 178, NULL, NULL, '2025-07-24 16:57:46', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1170, 178, NULL, NULL, '2025-07-24 16:57:55', '34.168.86.249', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1171, 178, NULL, NULL, '2025-07-24 16:58:38', '167.114.1.28', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1172, 178, NULL, NULL, '2025-07-24 17:00:51', '74.91.57.155', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1173, 178, NULL, NULL, '2025-07-24 17:03:28', '104.251.87.74', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1174, 178, NULL, NULL, '2025-07-24 17:16:24', '104.199.119.50', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1175, 178, NULL, NULL, '2025-07-24 17:16:24', '104.199.119.50', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1176, 178, NULL, NULL, '2025-07-24 17:16:24', '104.199.119.50', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1177, 178, NULL, NULL, '2025-07-24 17:16:24', '104.199.119.50', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1178, 178, NULL, NULL, '2025-07-24 17:16:32', '34.169.143.237', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1179, 178, NULL, NULL, '2025-07-24 17:16:32', '34.169.143.237', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1180, 178, NULL, NULL, '2025-07-24 17:16:32', '34.169.143.237', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1181, 178, NULL, NULL, '2025-07-24 17:16:32', '34.169.143.237', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1182, 178, NULL, NULL, '2025-07-24 17:17:16', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1183, 178, NULL, NULL, '2025-07-24 17:17:25', '144.217.74.133', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1184, 178, NULL, NULL, '2025-07-24 17:18:17', '51.161.84.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1185, 178, NULL, NULL, '2025-07-24 17:18:19', '54.39.107.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1186, 178, NULL, NULL, '2025-07-24 17:20:41', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1187, 178, NULL, NULL, '2025-07-24 17:21:23', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1188, 178, NULL, NULL, '2025-07-24 17:23:19', '173.248.184.14', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1189, 178, NULL, NULL, '2025-07-24 17:23:29', '167.100.103.6', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1190, 178, NULL, NULL, '2025-07-24 17:28:47', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1191, 178, NULL, NULL, '2025-07-24 17:58:33', '144.217.74.133', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1192, 178, NULL, NULL, '2025-07-24 17:58:41', '35.233.211.120', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1193, 178, NULL, NULL, '2025-07-24 17:58:41', '35.233.211.120', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1194, 178, NULL, NULL, '2025-07-24 17:58:41', '35.233.211.120', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1195, 178, NULL, NULL, '2025-07-24 17:58:42', '35.233.211.120', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1196, 178, NULL, NULL, '2025-07-24 17:59:34', '149.56.25.49', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1197, 178, NULL, NULL, '2025-07-24 18:03:50', '167.100.103.80', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1198, 178, NULL, NULL, '2025-07-24 18:18:14', '192.99.44.57', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1199, 178, NULL, NULL, '2025-07-24 18:18:23', '34.168.215.172', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1200, 178, NULL, NULL, '2025-07-24 18:18:23', '34.168.215.172', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1201, 178, NULL, NULL, '2025-07-24 18:18:23', '34.168.215.172', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1202, 178, NULL, NULL, '2025-07-24 18:18:23', '34.168.215.172', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1203, 178, NULL, NULL, '2025-07-24 18:18:33', '51.79.77.165', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1204, 178, NULL, NULL, '2025-07-24 18:22:11', '74.91.59.17', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1206, 178, NULL, NULL, '2025-07-24 18:42:41', '51.222.42.127', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1207, 178, NULL, NULL, '2025-07-24 18:42:41', '142.44.136.207', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1208, 178, NULL, NULL, '2025-07-24 18:42:52', '35.233.208.121', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1209, 178, NULL, NULL, '2025-07-24 18:42:52', '35.233.208.121', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1210, 178, NULL, NULL, '2025-07-24 18:42:52', '35.233.208.121', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1211, 178, NULL, NULL, '2025-07-24 18:42:53', '35.233.208.121', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1212, 178, NULL, NULL, '2025-07-24 18:47:06', '74.91.59.159', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1213, 179, NULL, NULL, '2025-07-24 18:55:35', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1214, 179, NULL, NULL, '2025-07-24 18:56:00', '144.217.252.156', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1215, 179, NULL, NULL, '2025-07-24 18:56:31', '144.76.23.231', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1217, 178, NULL, NULL, '2025-07-24 19:17:35', '192.99.44.95', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1218, 178, NULL, NULL, '2025-07-24 19:17:49', '35.233.208.121', '', 'https://t.co/YLlSfTLBbq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1219, 178, NULL, NULL, '2025-07-24 19:17:49', '35.233.208.121', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1220, 178, NULL, NULL, '2025-07-24 19:17:49', '35.233.208.121', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1221, 178, NULL, NULL, '2025-07-24 19:17:50', '35.233.208.121', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1222, 178, NULL, NULL, '2025-07-24 19:17:51', '144.76.23.78', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; trendictionbot0.5.0; trendiction search; http://www.trendiction.de/bot; please let us know of any problems; web at trendiction.com) Gecko/20100101 Firefox/125.0', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1223, 178, NULL, NULL, '2025-07-24 19:18:20', '51.161.84.125', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1225, 178, NULL, NULL, '2025-07-24 19:19:08', '51.161.115.227', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1228, 178, NULL, NULL, '2025-07-24 19:20:30', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1229, 178, NULL, NULL, '2025-07-24 19:22:29', '51.161.115.227', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1230, 178, NULL, NULL, '2025-07-24 19:22:30', '74.91.58.244', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1231, 178, NULL, NULL, '2025-07-24 19:22:36', '54.39.107.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1232, 178, NULL, NULL, '2025-07-24 19:25:05', '199.188.14.61', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1233, 178, NULL, NULL, '2025-07-24 19:25:58', '54.39.177.48', 'Mozilla/5.0 (compatible; YaK/1.0; http://linkfluence.com/; bot@linkfluence.com)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1235, 178, NULL, NULL, '2025-07-24 19:27:34', '74.91.58.35', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1239, 178, NULL, NULL, '2025-07-24 19:33:36', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1240, 178, NULL, NULL, '2025-07-24 19:34:09', '51.79.77.165', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1247, 178, NULL, NULL, '2025-07-24 19:38:12', '158.115.233.55', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Miami', NULL),
(1249, 178, NULL, NULL, '2025-07-24 19:44:39', '192.99.232.216', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1250, 178, NULL, NULL, '2025-07-24 19:44:49', '104.196.224.144', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1251, 178, NULL, NULL, '2025-07-24 19:44:49', '104.196.224.144', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1252, 178, NULL, NULL, '2025-07-24 19:44:49', '104.196.224.144', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1253, 178, NULL, NULL, '2025-07-24 19:44:49', '104.196.224.144', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1254, 178, NULL, NULL, '2025-07-24 19:46:45', '54.39.107.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1255, 179, NULL, NULL, '2025-07-24 19:48:30', '43.135.148.92', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Santa Clara', NULL),
(1256, 178, NULL, NULL, '2025-07-24 19:51:11', '167.100.103.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Charlotte', NULL),
(1262, 178, NULL, NULL, '2025-07-24 20:09:01', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1263, 178, NULL, NULL, '2025-07-24 20:09:43', '34.82.3.127', '', 'https://t.co/YLlSfTLBbq', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1264, 178, NULL, NULL, '2025-07-24 20:09:43', '34.82.3.127', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1265, 178, NULL, NULL, '2025-07-24 20:09:43', '34.82.3.127', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1266, 178, NULL, NULL, '2025-07-24 20:09:43', '34.82.3.127', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1267, 178, NULL, NULL, '2025-07-24 20:10:05', '51.161.117.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1270, 178, NULL, NULL, '2025-07-24 20:15:18', '167.100.103.167', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Charlotte', NULL),
(1275, 178, NULL, NULL, '2025-07-24 20:31:17', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1279, 178, NULL, NULL, '2025-07-24 20:34:49', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1305, 178, NULL, NULL, '2025-07-24 21:10:48', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1306, 184, NULL, NULL, '2025-07-24 21:13:17', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1307, 184, NULL, NULL, '2025-07-24 21:13:40', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1308, 178, NULL, NULL, '2025-07-24 21:13:40', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1309, 178, NULL, NULL, '2025-07-24 21:13:52', '35.185.196.18', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1310, 184, NULL, NULL, '2025-07-24 21:13:52', '35.185.196.18', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1311, 178, NULL, NULL, '2025-07-24 21:13:52', '35.185.196.18', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1312, 184, NULL, NULL, '2025-07-24 21:13:52', '35.185.196.18', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1313, 178, NULL, NULL, '2025-07-24 21:13:52', '35.185.196.18', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1314, 184, NULL, NULL, '2025-07-24 21:13:52', '35.185.196.18', '', 'https://t.co/PvziBesepa', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1315, 178, NULL, NULL, '2025-07-24 21:13:53', '35.185.196.18', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1316, 184, NULL, NULL, '2025-07-24 21:13:53', '35.185.196.18', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1317, 184, NULL, NULL, '2025-07-24 21:13:55', '35.185.196.18', '', 'https://t.co/PvziBesepa', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1318, 178, NULL, NULL, '2025-07-24 21:14:50', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1320, 178, NULL, NULL, '2025-07-24 21:20:08', '162.213.72.246', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Salem', NULL),
(1321, 184, NULL, NULL, '2025-07-24 21:20:30', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1322, 184, NULL, NULL, '2025-07-24 21:20:45', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1323, 184, NULL, NULL, '2025-07-24 21:20:45', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1324, 184, NULL, NULL, '2025-07-24 21:20:45', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1325, 184, NULL, NULL, '2025-07-24 21:20:45', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1326, 184, NULL, NULL, '2025-07-24 21:20:46', '52.22.37.162', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1327, 184, NULL, NULL, '2025-07-24 21:20:46', '52.22.37.162', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1328, 184, NULL, NULL, '2025-07-24 21:20:46', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1329, 184, NULL, NULL, '2025-07-24 21:20:46', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1330, 178, NULL, NULL, '2025-07-24 21:20:54', '54.39.107.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1331, 178, NULL, NULL, '2025-07-24 21:22:06', '54.39.107.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1332, 178, NULL, NULL, '2025-07-24 21:23:46', '34.206.217.125', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1333, 178, NULL, NULL, '2025-07-24 21:26:14', '74.91.57.135', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1335, 178, NULL, NULL, '2025-07-24 21:32:09', '51.79.77.186', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1336, 178, NULL, NULL, '2025-07-24 21:32:16', '35.233.208.121', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1337, 178, NULL, NULL, '2025-07-24 21:32:16', '35.233.208.121', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1338, 178, NULL, NULL, '2025-07-24 21:32:16', '35.233.208.121', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1339, 178, NULL, NULL, '2025-07-24 21:32:16', '35.233.208.121', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1340, 178, NULL, NULL, '2025-07-24 21:33:18', '192.99.44.95', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1341, 178, NULL, NULL, '2025-07-24 21:35:35', '51.161.115.226', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1342, 178, NULL, NULL, '2025-07-24 21:35:44', '158.69.27.238', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1343, 178, NULL, NULL, '2025-07-24 21:37:39', '162.213.74.14', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Salem', NULL),
(1344, 178, NULL, NULL, '2025-07-24 21:37:50', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1345, 167, NULL, NULL, '2025-07-24 21:38:50', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1346, 185, NULL, NULL, '2025-07-24 21:39:47', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1347, 185, NULL, NULL, '2025-07-24 21:40:44', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1348, 178, NULL, NULL, '2025-07-24 21:41:03', '167.100.100.167', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Charlotte', NULL),
(1349, 184, NULL, NULL, '2025-07-24 21:52:26', '49.51.196.42', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Santa Clara', NULL),
(1350, 185, NULL, NULL, '2025-07-24 21:56:40', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1351, 184, NULL, NULL, '2025-07-24 22:00:03', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1352, 178, NULL, NULL, '2025-07-24 22:10:43', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1353, 187, NULL, NULL, '2025-07-24 22:16:05', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1354, 187, NULL, NULL, '2025-07-24 22:16:31', '54.39.243.52', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Québec', NULL),
(1355, 187, NULL, NULL, '2025-07-24 22:16:39', '104.196.224.144', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1356, 187, NULL, NULL, '2025-07-24 22:16:40', '104.196.224.144', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1357, 187, NULL, NULL, '2025-07-24 22:16:40', '104.196.224.144', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1358, 187, NULL, NULL, '2025-07-24 22:16:40', '104.196.224.144', '', 'https://t.co/7Bc7pkvtye', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1359, 187, NULL, NULL, '2025-07-24 22:19:37', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1360, 187, NULL, NULL, '2025-07-24 22:21:14', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1361, 185, NULL, NULL, '2025-07-25 00:17:46', '43.157.156.190', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'Brazil', 'Sao Paulo', NULL),
(1362, 187, NULL, NULL, '2025-07-25 00:25:19', '43.153.135.208', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'Japan', 'Tokyo', NULL),
(1363, 167, NULL, NULL, '2025-07-25 00:49:14', '83.55.219.33', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Palencia', NULL),
(1364, 187, NULL, NULL, '2025-07-25 05:00:25', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1365, 187, NULL, NULL, '2025-07-25 05:00:58', '34.82.217.25', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1366, 187, NULL, NULL, '2025-07-25 05:00:58', '34.82.217.25', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1367, 187, NULL, NULL, '2025-07-25 05:00:58', '34.82.217.25', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1368, 187, NULL, NULL, '2025-07-25 05:00:58', '34.82.217.25', '', 'https://t.co/7Bc7pkvtye', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1369, 178, NULL, NULL, '2025-07-25 08:46:10', '54.39.18.152', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1370, 178, NULL, NULL, '2025-07-25 08:46:15', '34.83.24.20', '', 'https://t.co/YLlSfTL3lS', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1371, 178, NULL, NULL, '2025-07-25 08:46:32', '144.217.252.156', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1372, 178, NULL, NULL, '2025-07-25 08:46:41', '51.222.42.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1373, 178, NULL, NULL, '2025-07-25 08:47:01', '54.39.103.203', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1374, 178, NULL, NULL, '2025-07-25 08:51:10', '167.100.103.49', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Charlotte', NULL),
(1375, 178, NULL, NULL, '2025-07-25 08:51:20', '74.91.57.96', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1376, 185, NULL, NULL, '2025-07-25 08:57:54', '5.196.160.191', 'Mozilla/5.0 (X11; Linux i686; rv:114.0) Gecko/20100101 Firefox/114.0', 'http://0ln.org', NULL, NULL, NULL, NULL, NULL, 'France', 'Roubaix', NULL),
(1377, 179, NULL, NULL, '2025-07-25 08:57:55', '5.196.160.191', 'Mozilla/5.0 (X11; Linux i686; rv:114.0) Gecko/20100101 Firefox/114.0', 'http://0ln.org', NULL, NULL, NULL, NULL, NULL, 'France', 'Roubaix', NULL),
(1378, 184, NULL, NULL, '2025-07-25 08:57:55', '5.196.160.191', 'Mozilla/5.0 (X11; Linux i686; rv:114.0) Gecko/20100101 Firefox/114.0', 'http://0ln.org', NULL, NULL, NULL, NULL, NULL, 'France', 'Roubaix', NULL),
(1379, 178, NULL, NULL, '2025-07-25 08:57:56', '5.196.160.191', 'Mozilla/5.0 (X11; Linux i686; rv:114.0) Gecko/20100101 Firefox/114.0', 'http://0ln.org', NULL, NULL, NULL, NULL, NULL, 'France', 'Roubaix', NULL),
(1380, 187, NULL, NULL, '2025-07-25 08:57:56', '5.196.160.191', 'Mozilla/5.0 (X11; Linux i686; rv:114.0) Gecko/20100101 Firefox/114.0', 'http://0ln.org', NULL, NULL, NULL, NULL, NULL, 'France', 'Roubaix', NULL),
(1381, 187, NULL, NULL, '2025-07-25 10:56:11', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1382, 167, NULL, NULL, '2025-07-25 11:01:40', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1383, 179, NULL, NULL, '2025-07-25 11:03:02', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1384, 184, NULL, NULL, '2025-07-25 11:03:09', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1388, 184, NULL, NULL, '2025-07-25 11:20:43', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1389, 184, NULL, NULL, '2025-07-25 11:21:03', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1390, 187, NULL, NULL, '2025-07-25 12:07:55', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1391, 169, NULL, NULL, '2025-07-25 12:32:59', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1392, 189, NULL, NULL, '2025-07-25 13:33:45', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1393, 189, NULL, NULL, '2025-07-25 13:34:08', '51.161.115.227', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1394, 189, NULL, NULL, '2025-07-25 13:34:28', '51.161.117.63', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1395, 189, NULL, NULL, '2025-07-25 13:34:36', '35.233.220.140', '', 'https://t.co/oeyPvq0aV0', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1396, 189, NULL, NULL, '2025-07-25 13:34:38', '35.233.220.140', 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1397, 189, NULL, NULL, '2025-07-25 13:34:38', '35.233.220.140', '', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1398, 189, NULL, NULL, '2025-07-25 13:34:38', '35.233.220.140', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1399, 189, NULL, NULL, '2025-07-25 13:35:02', '188.171.163.152', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Gijón', NULL),
(1400, 189, NULL, NULL, '2025-07-25 13:36:07', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1401, 189, NULL, NULL, '2025-07-25 13:36:29', '54.39.107.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1402, 189, NULL, NULL, '2025-07-25 13:36:54', '54.39.103.203', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1403, 189, NULL, NULL, '2025-07-25 13:37:07', '54.83.9.180', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1404, 189, NULL, NULL, '2025-07-25 13:37:14', '34.83.24.20', '', 'https://t.co/oeyPvq0aV0', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1405, 189, NULL, NULL, '2025-07-25 13:37:29', '188.171.163.152', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Gijón', NULL),
(1406, 189, NULL, NULL, '2025-07-25 13:37:32', '51.79.99.78', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1407, 189, NULL, NULL, '2025-07-25 13:38:09', '54.39.104.161', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1408, 189, NULL, NULL, '2025-07-25 13:38:31', '35.193.191.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Gecko/20100101 Firefox/134.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Council Bluffs', NULL),
(1409, 189, NULL, NULL, '2025-07-25 13:39:45', '167.100.103.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Charlotte', NULL),
(1410, 189, NULL, NULL, '2025-07-25 13:42:47', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1411, 189, NULL, NULL, '2025-07-25 13:44:49', '54.85.32.163', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1412, 189, NULL, NULL, '2025-07-25 13:49:45', '49.51.36.179', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Los Angeles', NULL),
(1413, 189, NULL, NULL, '2025-07-25 13:52:26', '95.127.81.137', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Sotodosos', NULL),
(1414, 189, NULL, NULL, '2025-07-25 13:52:43', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1415, 189, NULL, NULL, '2025-07-25 13:52:43', '52.201.148.193', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1416, 189, NULL, NULL, '2025-07-25 13:52:43', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1417, 189, NULL, NULL, '2025-07-25 13:52:43', '52.22.37.162', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1418, 189, NULL, NULL, '2025-07-25 13:52:44', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1419, 189, NULL, NULL, '2025-07-25 13:52:44', '52.201.148.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1420, 189, NULL, NULL, '2025-07-25 13:52:44', '52.22.37.162', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1421, 189, NULL, NULL, '2025-07-25 13:52:44', '52.22.37.162', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1422, 189, NULL, NULL, '2025-07-25 13:52:52', '95.127.81.137', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Sotodosos', NULL),
(1423, 189, NULL, NULL, '2025-07-25 13:56:15', '181.65.34.193', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Peru', 'Lima region', NULL),
(1424, 189, NULL, NULL, '2025-07-25 13:57:25', '90.167.87.84', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Barcelona', NULL),
(1425, 189, NULL, NULL, '2025-07-25 14:07:51', '79.117.199.222', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Alicante', NULL),
(1426, 189, NULL, NULL, '2025-07-25 14:07:54', '77.26.12.4', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://www.google.com/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'A Coruña', NULL),
(1427, 189, NULL, NULL, '2025-07-25 14:09:24', '31.4.179.95', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Madrid', NULL),
(1428, 189, NULL, NULL, '2025-07-25 14:11:21', '81.47.17.16', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Valga', NULL),
(1429, 189, NULL, NULL, '2025-07-25 14:12:27', '54.166.229.137', 'help@dataminr.com', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1430, 189, NULL, NULL, '2025-07-25 14:13:11', '84.125.72.125', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Valencia', NULL),
(1431, 189, NULL, NULL, '2025-07-25 14:13:28', '46.6.151.236', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Beasain', NULL);
INSERT INTO `click_stats` (`id`, `url_id`, `user_id`, `session_id`, `clicked_at`, `ip_address`, `user_agent`, `referer`, `country_code`, `region`, `latitude`, `longitude`, `timezone`, `country`, `city`, `accessed_domain`) VALUES
(1432, 189, NULL, NULL, '2025-07-25 14:13:31', '81.33.208.25', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Pozuelo de Alarcón', NULL),
(1433, 189, NULL, NULL, '2025-07-25 14:13:44', '212.142.160.102', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Mobile Safari/537.36', 'https://www.google.com/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1434, 189, NULL, NULL, '2025-07-25 14:14:22', '79.117.193.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Torrent', NULL),
(1435, 189, NULL, NULL, '2025-07-25 14:14:24', '46.6.151.236', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Beasain', NULL),
(1436, 178, NULL, NULL, '2025-07-25 14:15:08', '167.114.101.27', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1437, 189, NULL, NULL, '2025-07-25 14:15:17', '91.126.42.245', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Pioz', NULL),
(1438, 178, NULL, NULL, '2025-07-25 14:15:42', '54.39.107.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1439, 189, NULL, NULL, '2025-07-25 14:15:47', '81.33.66.53', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Madrid', NULL),
(1440, 189, NULL, NULL, '2025-07-25 14:16:53', '79.116.142.59', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Mislata', NULL),
(1441, 189, NULL, NULL, '2025-07-25 14:17:15', '34.168.30.209', '', 'https://t.co/oeyPvq0aV0', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1442, 178, NULL, NULL, '2025-07-25 14:19:50', '74.91.58.238', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Ashburn', NULL),
(1443, 189, NULL, NULL, '2025-07-25 14:22:11', '90.171.74.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Massalfassar', NULL),
(1444, 189, NULL, NULL, '2025-07-25 14:22:49', '86.127.226.67', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Alcobendas', NULL),
(1445, 189, NULL, NULL, '2025-07-25 14:25:13', '78.30.10.184', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Seville', NULL),
(1446, 189, NULL, NULL, '2025-07-25 14:25:36', '109.205.140.79', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Alcossebre', NULL),
(1447, 189, NULL, NULL, '2025-07-25 14:40:11', '80.29.102.210', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'San Martín de la Vega', NULL),
(1448, 189, NULL, NULL, '2025-07-25 14:44:27', '84.78.250.37', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Madrid', NULL),
(1449, 189, NULL, NULL, '2025-07-25 14:48:48', '79.152.105.220', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Barcelona', NULL),
(1450, 189, NULL, NULL, '2025-07-25 14:49:50', '46.6.121.10', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Lucena', NULL),
(1451, 189, NULL, NULL, '2025-07-25 14:51:38', '90.160.102.80', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Aldehuela de la Bóveda', NULL),
(1452, 189, NULL, NULL, '2025-07-25 14:57:11', '84.76.80.152', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Salamanca', NULL),
(1453, 189, NULL, NULL, '2025-07-25 14:57:55', '79.116.218.111', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Valencia', NULL),
(1454, 189, NULL, NULL, '2025-07-25 15:00:30', '34.168.30.209', '', 'https://t.co/oeyPvq0aV0', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL),
(1455, 189, NULL, NULL, '2025-07-25 15:15:36', '46.6.228.188', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Inca', NULL),
(1456, 189, NULL, NULL, '2025-07-25 15:26:15', '92.191.186.26', 'Mozilla/5.0 (Android 12; Mobile; rv:141.0) Gecko/141.0 Firefox/141.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Uceda', NULL),
(1457, 178, NULL, NULL, '2025-07-25 15:29:50', '192.99.101.184', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Beauharnois', NULL),
(1458, 178, NULL, NULL, '2025-07-25 15:29:53', '167.61.233.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Uruguay', 'Montevideo', NULL),
(1459, 178, NULL, NULL, '2025-07-25 15:31:02', '142.44.136.207', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Canada', 'Montreal', NULL),
(1460, 178, NULL, NULL, '2025-07-25 15:35:23', '107.158.4.33', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'United States', 'Dallas', NULL),
(1461, 189, NULL, NULL, '2025-07-25 16:14:49', '213.94.52.19', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Madrid', NULL),
(1462, 189, NULL, NULL, '2025-07-25 16:24:33', '47.61.198.152', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Madrid', NULL),
(1463, 189, NULL, NULL, '2025-07-25 16:45:37', '88.17.186.206', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Mobile/15E148 Safari/604.1', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Valencia', NULL),
(1464, 189, NULL, NULL, '2025-07-25 17:13:29', '79.117.69.60', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Moratalla', NULL),
(1465, 189, NULL, NULL, '2025-07-25 17:13:41', '79.117.69.60', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Moratalla', NULL),
(1466, 189, NULL, NULL, '2025-07-25 17:14:34', '34.86.192.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Gecko/20100101 Firefox/134.0', '', NULL, NULL, NULL, NULL, NULL, 'United States', 'Washington', NULL),
(1467, 189, NULL, NULL, '2025-07-25 17:20:49', '176.83.78.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Sant Adrià de Besòs', NULL),
(1468, 189, NULL, NULL, '2025-07-25 17:58:33', '88.10.101.6', 'Mozilla/5.0 (Android 16; Mobile; rv:140.0) Gecko/140.0 Firefox/140.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Berriozar', NULL),
(1469, 189, NULL, NULL, '2025-07-25 17:58:35', '88.10.101.6', 'Mozilla/5.0 (Android 16; Mobile; rv:140.0) Gecko/140.0 Firefox/140.0', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Berriozar', NULL),
(1470, 189, NULL, NULL, '2025-07-25 18:03:08', '178.139.173.162', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Seville', NULL),
(1471, 189, NULL, NULL, '2025-07-25 18:09:13', '83.54.175.146', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://t.co/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'León', NULL),
(1472, 189, NULL, NULL, '2025-07-25 19:20:28', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'https://0ln.eu/', NULL, NULL, NULL, NULL, NULL, 'Spain', 'Bilbao', NULL),
(1473, 189, NULL, NULL, '2025-07-25 19:48:43', '35.230.80.4', '', 'https://t.co/oeyPvq0aV0', NULL, NULL, NULL, NULL, NULL, 'United States', 'The Dalles', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config`
--

CREATE TABLE `config` (
  `id` int NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `config`
--

INSERT INTO `config` (`id`, `config_key`, `config_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Acortador de URLs', 'Nombre del sitio', '2025-07-08 16:49:59', '2025-07-08 16:49:59'),
(2, 'site_description', 'Acorta URLs de forma rápida y segura', 'Descripción', '2025-07-08 16:49:59', '2025-07-08 16:49:59'),
(3, 'max_urls_per_ip', '100', 'URLs máximas por IP', '2025-07-08 16:49:59', '2025-07-08 16:49:59'),
(4, 'enable_custom_codes', '1', 'Códigos personalizados', '2025-07-08 16:49:59', '2025-07-08 16:49:59'),
(5, 'enable_stats', '1', 'Estadísticas públicas', '2025-07-08 16:49:59', '2025-07-08 16:49:59'),
(6, 'version', '1.0', 'Versión del sistema', '2025-07-08 16:49:59', '2025-07-08 16:49:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `custom_domains`
--

CREATE TABLE `custom_domains` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `verification_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ssl_enabled` tinyint(1) DEFAULT '0',
  `verification_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `custom_domains`
--

INSERT INTO `custom_domains` (`id`, `user_id`, `domain`, `status`, `verification_token`, `verified_at`, `created_at`, `updated_at`, `ssl_enabled`, `verification_method`) VALUES
(14, NULL, '0ln.org', 'active', '72eb9bcb1a1d37844fb550e6bde11c4b16fbcc4ce9dcbda504a6ae678404218c', '2025-07-12 23:21:37', '2025-07-12 23:20:01', '2025-07-14 16:10:57', 0, 'dns_txt'),
(15, NULL, 'short.tudominio.com', 'active', NULL, NULL, '2025-07-13 08:56:59', '2025-07-13 08:56:59', 0, NULL),
(16, NULL, 'link.tudominio.com', 'active', NULL, NULL, '2025-07-13 08:56:59', '2025-07-13 08:56:59', 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `daily_stats`
--

CREATE TABLE `daily_stats` (
  `id` int NOT NULL,
  `url_id` int NOT NULL,
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `total_clicks` int DEFAULT '0',
  `unique_visitors` int DEFAULT '0',
  `desktop_clicks` int DEFAULT '0',
  `mobile_clicks` int DEFAULT '0',
  `tablet_clicks` int DEFAULT '0',
  `top_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `top_browser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username_attempted` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rate_limit`
--

CREATE TABLE `rate_limit` (
  `id` int NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `action` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `security_logs`
--

CREATE TABLE `security_logs` (
  `id` bigint NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `severity` enum('info','warning','error','critical') DEFAULT 'info',
  `details` json DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` text NOT NULL,
  `last_activity` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text,
  `type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `settings`
--

INSERT INTO `settings` (`key`, `value`, `type`, `description`, `updated_at`) VALUES
('allow_registration', '1', 'boolean', 'Permitir registro de nuevos usuarios', '2025-07-13 18:00:22'),
('analytics_retention_days', '365', 'integer', 'Días de retención de estadísticas', '2025-07-13 18:00:22'),
('blocked_domains', '[]', 'json', 'Lista de dominios bloqueados', '2025-07-13 18:00:22'),
('default_url_expiry_days', '0', 'integer', 'Días de expiración por defecto (0 = sin expiración)', '2025-07-13 18:00:22'),
('enable_2fa', '1', 'boolean', 'Habilitar autenticación de dos factores', '2025-07-13 18:00:22'),
('maintenance_mode', '0', 'boolean', 'Modo de mantenimiento', '2025-07-13 18:00:22'),
('max_urls_per_user', '0', 'integer', 'Máximo de URLs por usuario (0 = ilimitado)', '2025-07-13 18:00:22'),
('require_email_verification', '1', 'boolean', 'Requerir verificación de email', '2025-07-13 18:00:22'),
('reserved_codes', '[\"admin\",\"api\",\"login\",\"register\",\"dashboard\",\"stats\",\"qr\"]', 'json', 'Códigos reservados', '2025-07-13 18:00:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sync_logs`
--

INSERT INTO `sync_logs` (`id`, `user_id`, `action`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 11, 'import_from_extension', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-21 13:58:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_type` enum('string','integer','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES
(1, 'max_urls_per_user', '100', 'integer', 'Máximo número de URLs por usuario', '2025-07-09 20:22:44', NULL),
(2, 'allow_registration', 'true', 'boolean', 'Permitir registro de nuevos usuarios', '2025-07-09 20:22:44', NULL),
(3, 'require_email_verification', 'false', 'boolean', 'Requerir verificación de email', '2025-07-09 20:22:44', NULL),
(4, 'max_failed_login_attempts', '5', 'integer', 'Máximo intentos de login fallidos', '2025-07-09 20:22:44', NULL),
(5, 'account_lockout_duration', '1800', 'integer', 'Duración de bloqueo de cuenta en segundos', '2025-07-09 20:22:44', NULL),
(6, 'session_lifetime', '86400', 'integer', 'Duración de sesión en segundos (24 horas)', '2025-07-09 20:22:44', NULL),
(7, 'password_min_length', '8', 'integer', 'Longitud mínima de contraseña', '2025-07-09 20:22:44', NULL),
(8, 'site_name', 'URL Shortener', 'string', 'Nombre del sitio', '2025-07-09 20:22:44', NULL),
(9, 'admin_email', 'admin@localhost', 'string', 'Email del administrador', '2025-07-09 20:22:44', NULL),
(10, 'enable_geolocation', 'true', 'boolean', 'Habilitar geolocalización', '2025-07-09 20:22:44', NULL),
(11, 'allow_custom_codes', 'true', 'boolean', 'Permitir códigos personalizados', '2025-07-09 20:22:44', NULL),
(12, 'max_custom_code_length', '20', 'integer', 'Longitud máxima de códigos personalizados', '2025-07-09 20:22:44', NULL),
(13, 'enable_url_expiration', 'true', 'boolean', 'Permitir URLs con expiración', '2025-07-09 20:22:44', NULL),
(14, 'default_url_expiration_days', '365', 'integer', 'Días por defecto para expiración de URLs', '2025-07-09 20:22:44', NULL),
(15, 'enable_url_analytics', 'true', 'boolean', 'Habilitar analíticas detalladas', '2025-07-09 20:22:44', NULL),
(16, 'maintenance_mode', 'false', 'boolean', 'Modo mantenimiento', '2025-07-09 20:22:44', NULL),
(17, 'maintenance_message', 'Sitio en mantenimiento', 'string', 'Mensaje de mantenimiento', '2025-07-09 20:22:44', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `urls`
--

CREATE TABLE `urls` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `domain_id` int DEFAULT NULL,
  `short_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_accessed` datetime DEFAULT NULL,
  `clicks` int DEFAULT '0',
  `last_click` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `is_public` tinyint(1) DEFAULT '1',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `og_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `urls`
--

INSERT INTO `urls` (`id`, `user_id`, `domain_id`, `short_code`, `original_url`, `created_at`, `last_accessed`, `clicks`, `last_click`, `ip_address`, `user_agent`, `active`, `is_public`, `title`, `description`, `og_image`, `deleted_at`) VALUES
(20, NULL, NULL, '160orw', 'https://abc.es', '2025-07-09 13:23:29', NULL, 73, '2025-07-09 13:23:51', '62.99.100.233', NULL, 1, 1, NULL, NULL, NULL, NULL),
(23, NULL, NULL, 'X9k7IN', 'https://adunti.net', '2025-07-10 20:41:22', NULL, 7, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(25, NULL, NULL, 'hoQdJs', 'https://biblioteca.store', '2025-07-10 20:54:54', NULL, 6, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(42, 13, NULL, '6dhxRG', 'https://adunti.org/biblioteca', '2025-07-12 21:19:12', NULL, 0, NULL, NULL, NULL, 1, 1, 'Adunti.org - 6dhxRG → https://adunti.org/biblioteca', NULL, NULL, NULL),
(50, 1, 14, 'md4CYW', 'https://amzn.to/3R2pzir', '2025-07-13 08:59:22', NULL, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(51, 1, 14, 'IezPkI', 'https://amzn.to/3R2pzir', '2025-07-13 09:10:01', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(52, 1, 14, 'CgsEuL', 'https://adunti.net/biblioteca', '2025-07-13 09:11:06', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(53, 1, 0, '2Dtjhy', 'https://adunti.net', '2025-07-13 09:36:36', NULL, 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(54, 1, 0, 'lpBDcU', 'https://adunti.net', '2025-07-13 09:56:47', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(55, 1, 14, 'fdeSit', 'https://adunti.net', '2025-07-13 09:57:09', NULL, 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(56, 1, 14, '8zaHZF', 'https://adunti.net', '2025-07-13 11:39:16', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(57, 1, 14, 'vosNy7', 'https://adunti.net', '2025-07-13 11:39:43', NULL, 4, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(58, 1, 0, 'EVYDFU', 'https://adunti.net', '2025-07-13 12:32:02', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(59, 1, 14, 'KnIyVp', 'https://adunti.net', '2025-07-13 12:42:57', NULL, 1, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(60, 1, 14, 'liL38N', 'https://adunti.org/biblioteca', '2025-07-13 12:47:55', NULL, 1, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(89, 17, 0, 'NyKMCD', 'https://www.vanitatis.elconfidencial.com/famosos/2025-07-17/georgina-rodriguez-compras-mansion-pisos-hipoteca_4173235/', '2025-07-17 20:49:38', NULL, 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(90, 17, 0, 'HBHXj6', 'https://computerhoy.20minutos.es/moviles/expertos-tienen-claro-razon-siempre-deberias-apagar-movil-vacaciones-1472836?utm_source=firefox-newtab-es-es', '2025-07-17 20:51:59', NULL, 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(91, 17, 0, 'PhuVeJ', 'https://www.vanitatis.elconfidencial.com/famosos/2025-07-17/georgina-rodriguez-compras-mansion-pisos-hipoteca_4173235/', '2025-07-17 20:55:56', '2025-07-23 01:18:54', 5, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(92, 17, 0, 'PerdidoCaT', 'https://www.elespanol.com/edicion/20250717/gobierno-da-perdido-septimo-intento-oficializar-catalan-ue-pese-carta-conjunta-illa-pradales/1003743853036_16.html', '2025-07-17 21:45:29', NULL, 47, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(96, 1, 0, 'Mi-Biblioteca_personal', 'https://adunti.org/biblioteca', '2025-07-18 10:42:53', '2025-07-23 12:12:01', 4, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(97, 1, 0, 'la_biblioteca_personal', 'https://adunti.org/biblioteca', '2025-07-18 11:24:26', '2025-07-22 23:00:47', 8, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(111, 1, 14, 'ElGobiernoLoDaPorPerdidoElCat', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/1003743853036_16.html', '2025-07-18 15:50:27', '2025-07-22 16:27:59', 191, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(112, 1, 14, 'PasoAtrasDeSanchezConElCatEnLaUE', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/1003743854826_16-1.html', '2025-07-18 22:14:31', NULL, 26, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(116, 13, 14, 'Gobierno-CAT2', 'https://www.elespanol.com/edicion/20250717/gobierno-da-perdido-septimo-intento-oficializar-catalan-ue-pese-carta-conjunta-illa-pradales/1003743853036_16.html', '2025-07-19 22:06:57', NULL, 1, NULL, NULL, NULL, 1, 1, 'Elespanol.com - Gobierno-CAT2 → https://www.elespanol.com/edicion/20250717/gobierno-da-perdido-septimo-intento-oficializar-catalan-ue-pese-carta-conjunta-illa-pradales/1003743853036_16.html', NULL, NULL, NULL),
(117, 13, 14, 'TsMYj9', 'https://hola.es', '2025-07-19 22:08:15', NULL, 1, NULL, NULL, NULL, 1, 1, 'Hola.es - TsMYj9 → https://hola.es', NULL, NULL, NULL),
(132, 12, 14, 'GyIf6O', 'https://hola.es', '2025-07-21 10:13:40', '2025-07-23 12:41:23', 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(136, 12, 14, 'i4V4Y2', 'https://adunti.net/biblioteca', '2025-07-21 12:13:57', '2025-07-21 12:37:36', 1, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(139, 12, 14, 'mmg0p8', 'https://adunti.net/biblioteca', '2025-07-21 13:30:18', '2025-07-22 20:52:44', 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(141, 11, 14, 'kiaNQu', 'https://amzn.to/3R2pzir', '2025-07-21 14:00:28', '2025-07-21 15:04:26', 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(152, 1, NULL, 'RXua4A', 'https://proton.me', '2025-07-21 23:09:33', '2025-07-23 12:12:20', 5, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(160, 13, NULL, 'rzNzbq', 'https://Proton.me', '2025-07-22 13:55:05', '2025-07-22 14:29:13', 1, NULL, NULL, NULL, 1, 1, 'Proton.me - rzNzbq → https://Proton.me', NULL, NULL, NULL),
(161, 18, NULL, 'P1Bl4q', 'https://adunti.net/biblioteca', '2025-07-23 00:54:27', '2025-07-23 02:55:02', 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(164, 12, NULL, 'F8rInC', 'https://proton.me', '2025-07-23 13:01:13', '2025-07-23 13:01:59', 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(166, 12, 14, 'Zapatero', 'https://www.elespanol.com/espana/politica/20250723/feijoo-tilda-sospechosos-comportamientos-zapatero-vincula-huawei-gobierno-debe-explicarlo/1003743859690_0.html', '2025-07-23 13:06:33', '2025-07-23 13:07:53', 14, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(167, 13, 0, 'Zapaptero', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/1003743859690_0-1.html', '2025-07-23 13:16:02', NULL, 91, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(168, 1, 14, 'El-ABC', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/index-1.html', '2025-07-23 20:23:46', NULL, 12, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(169, 1, 14, 'TribunalCerdan', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/FireShot-Capture-001-El-Tribunal-Supremo-confirma-la-prision-para-Cerdan-por-riesgo-de-d_-www.abc_.es_.pdf', '2025-07-23 21:43:17', NULL, 67, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(170, 1, 14, 'Y9xfpn', 'https://adunti.net/biblioteca', '2025-07-24 00:43:25', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(172, 1, 0, 'T2DMVp', 'https://adunti.net/biblioteca', '2025-07-24 00:46:38', NULL, 2, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(177, 16, 14, 'Hola', 'https://hola.es', '2025-07-24 13:45:10', NULL, 3, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(178, 1, 14, 'LaAudienciaDeGranada', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/FireShot-Capture-002-Noelia-Nunez-dimite-como-diputada-y-vicesecretaria-del-PP-tras-reco_-www.abc_.es_-1.pdf', '2025-07-24 14:03:41', NULL, 223, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(179, 1, 14, 'CesePP', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/ssstwitter.com_1753383144489.mp4', '2025-07-24 18:55:00', NULL, 6, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(184, 1, 14, 'Dimite-del-PP', 'https://dai.ly/x9ninpy', '2025-07-24 21:11:44', NULL, 22, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(185, 1, 14, 'CWDi58', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/1003743859690_0-1.html', '2025-07-24 21:39:24', NULL, 5, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(187, 1, 14, 'Hito-Fusion', 'https://www.youtube.com/watch?v=irpCrZUeqFk', '2025-07-24 22:15:43', NULL, 17, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL),
(189, 1, 14, 'Pollo', 'https://orange-hawk-291552.hostingersite.com/wp-content/uploads/2025/03/1003743863051_0.amp_.html', '2025-07-25 13:33:18', NULL, 75, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `urls_deleted_history`
--

CREATE TABLE `urls_deleted_history` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `short_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_from` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `url_analytics`
--

CREATE TABLE `url_analytics` (
  `id` bigint NOT NULL,
  `url_id` int NOT NULL,
  `user_id` int NOT NULL,
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `referer` text COLLATE utf8mb4_unicode_ci,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `device_type` enum('desktop','mobile','tablet','bot') COLLATE utf8mb4_unicode_ci DEFAULT 'desktop',
  `browser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `url_analytics`
--

INSERT INTO `url_analytics` (`id`, `url_id`, `user_id`, `short_code`, `ip_address`, `user_agent`, `referer`, `country`, `country_code`, `city`, `region`, `latitude`, `longitude`, `device_type`, `browser`, `os`, `clicked_at`, `session_id`) VALUES
(1, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.192.236.49', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'tablet', 'Chrome', 'Linux', '2025-07-18 13:35:34', 'historical_40dd3f79f774a878d14eb6b353b0974f'),
(2, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.201.49.166', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Buenos Aires', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-18 17:44:01', 'historical_03be2aed86c422d5061ce5a58643a4d0'),
(3, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.16.75.108', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'United Kingdom', 'GB', 'Birmingham', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-18 11:00:25', 'historical_878a99ca37aa2f9882de2c5401df4089'),
(4, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.171.235.214', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-18 12:15:58', 'historical_af0507e6d1eba1ecd9e33d79c8c110f7'),
(5, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.69.117.80', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Buenos Aires', NULL, NULL, NULL, 'mobile', 'Chrome', 'Android', '2025-07-18 16:35:06', 'historical_63b68c085b8dff3feed044f3aaa33396'),
(6, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.132.34.184', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 09:18:45', 'historical_8ca2cd8ddcb23e45ddffb96d81fd4c99'),
(7, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.92.107.224', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Edge', 'Android', '2025-07-18 17:54:35', 'historical_83a9a582fbc2788871599988bb421199'),
(8, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.64.224.174', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Guatemala', 'GT', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-18 15:05:46', 'historical_6dbd88ce10cd761ceae52326a9870e75'),
(9, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.176.230.161', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Monterrey', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-18 17:18:32', 'historical_0d76bad646edb484694c468bb92951f5'),
(10, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.143.195.113', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 22:28:41', 'historical_7d466c87cdc8a38051dcb6e3ed248b41'),
(11, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.40.217.33', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 12:12:29', 'historical_50b5ad8cfbf1a4744fe2166a0b158c98'),
(12, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.44.145.114', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-18 15:54:58', 'historical_a3ee22b0393ae10442780903f1767355'),
(13, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.75.19.192', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Brazil', 'BR', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-18 14:24:40', 'historical_86887d74b9a8d044a1866ebbe73a5538'),
(14, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.81.195.125', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'mobile', 'Firefox', 'iOS', '2025-07-18 14:41:36', 'historical_deb8f8a21f5ca236a0f2d6160c107bed'),
(15, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.212.229.243', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'mobile', 'Chrome', 'Android', '2025-07-18 12:43:06', 'historical_0171067ff5883c12396175ff728bc2c6'),
(16, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.117.32.182', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 16:54:44', 'historical_5c4f71c643df3707553f622b3b086bcf'),
(17, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.146.56.82', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'tablet', 'Firefox', 'Windows', '2025-07-18 09:08:08', 'historical_89d6cbd73303c1c3fcd936a91a965a25'),
(18, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.118.211.96', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-18 21:09:33', 'historical_7f2528fb949b991acfc704643d9e3bf3'),
(19, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.76.236.224', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-18 20:48:50', 'historical_8d9bb4b1c61dfb7b938a5856f7895772'),
(20, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.151.51.137', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Colombia', 'CO', 'Bogotá', NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-18 20:13:25', 'historical_bb4cd743812cadaa789422d76ae24caf'),
(21, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.128.181.177', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'desktop', 'Safari', 'iOS', '2025-07-18 12:13:29', 'historical_c35da22ef0f4e11903d55f7b581c495f'),
(22, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.196.33.241', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 11:18:12', 'historical_0d0ae7893392201dcac0be83381142ae'),
(23, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.240.24.48', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 21:30:19', 'historical_484c7c510fa1b314f76b05b5d0bfca9a'),
(24, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.55.101.14', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-18 16:34:42', 'historical_0aa0daa5b64115effb7501a634419d31'),
(25, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.31.168.147', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'mobile', 'Safari', 'macOS', '2025-07-18 22:14:20', 'historical_cd0bd308282fe5763b3d488dcb065714'),
(26, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.95.109.226', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-18 22:34:13', 'historical_d6b3eed35cf7297534837840978aebd0'),
(27, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.27.200.62', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-18 16:33:59', 'historical_aadc91bcfe704a3ae05d615b5c20b881'),
(28, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.53.168.46', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 20:23:05', 'historical_14fd24aaa30f20803d96455306106171'),
(29, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.175.120.108', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'desktop', 'Firefox', 'Linux', '2025-07-18 19:53:03', 'historical_a1d1e4310292ad455e456728a6bd6e63'),
(30, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.212.101.66', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 19:35:53', 'historical_c833100eb4b75cfc430e12f9cd828d1d'),
(31, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.45.240.81', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-18 18:04:46', 'historical_8a5b8bc59f69b4ceda7722006464297e'),
(32, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.66.116.228', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Argentina', 'AR', 'La Plata', NULL, NULL, NULL, 'mobile', 'Edge', 'iOS', '2025-07-18 10:59:28', 'historical_5106e70e2aa70aa46c626cf0238ba9d8'),
(33, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.241.213.168', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Colombia', 'CO', 'Cali', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-18 14:18:30', 'historical_27be3ae7a98770db5dbe6c3daa4d0828'),
(34, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.197.252.95', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Portugal', 'PT', NULL, NULL, NULL, NULL, 'mobile', 'Firefox', 'macOS', '2025-07-18 10:24:22', 'historical_5d136154478ca5d0a233a39948912198'),
(35, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.91.159.187', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Miami', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-18 15:04:22', 'historical_c370b2ecaa201d3b5bcc8dffc758b19f'),
(36, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.142.8.61', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Italy', 'IT', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-18 21:00:01', 'historical_f3e2abc9ba8404ae581b1d606fb6579c'),
(37, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.187.235.24', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'mobile', 'Safari', 'iOS', '2025-07-18 12:42:30', 'historical_8549975c8da55d4c2bfa7e7f5fd31c92'),
(38, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.28.165.228', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Colombia', 'CO', 'Cartagena', NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-18 08:11:05', 'historical_f7a844bfd5b6e6d93146a22b64793c97'),
(39, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.7.41.252', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Germany', 'DE', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-18 22:01:28', 'historical_33ece5423824483bc7e353b0208da71c'),
(40, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.5.21.103', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 17:51:41', 'historical_f51a699fd503a5cb44a65d8757ade452'),
(41, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.203.97.31', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Italy', 'IT', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-18 10:01:33', 'historical_3a7ae74bd57b1a25529143999eac62a0'),
(42, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.83.158.175', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'Buenos Aires', NULL, NULL, NULL, 'desktop', 'Firefox', 'Android', '2025-07-18 17:55:47', 'historical_d90b649913b6c2a49a8ebad4eb262f87'),
(43, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.176.133.36', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'macOS', '2025-07-18 17:53:55', 'historical_62f71030feccad116cd4ac7bdfb8bc23'),
(44, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.140.175.37', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-18 15:12:24', 'historical_7efb670fde54bcc2fc0f1b558c4555fd'),
(45, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.223.144.196', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'desktop', 'Safari', 'iOS', '2025-07-18 11:36:57', 'historical_8f556dd03b28c78ab3fa244a369e9461'),
(46, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.106.111.67', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Bogotá', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 15:51:04', 'historical_11b1c6c1d578c19513bf81b01834658f'),
(47, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.172.33.111', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Barranquilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-18 19:02:34', 'historical_99173ee40e8420fbba2564268bddceec'),
(48, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.109.88.226', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'desktop', 'Safari', 'iOS', '2025-07-18 17:34:32', 'historical_763510693bbe8ffc5a2ae73b71ac9e34'),
(49, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.73.178.158', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 20:11:24', 'historical_5286de313e06a86c7c64a06816f512c8'),
(50, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.51.135.83', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-18 14:20:09', 'historical_aa91f2cf6244ec07aad82d01c08c5efc'),
(51, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.139.122.41', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 08:04:14', 'historical_b7b2ef9f1e523d85844fbefadd5f7bbd'),
(52, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.197.178.96', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Córdoba', NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-18 09:29:34', 'historical_b23d71b8d46de8dc75ecc013d83f3ecc'),
(53, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.108.215.140', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-18 14:00:20', 'historical_ba429dfb11323d1e1d8e815c48463a23'),
(54, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.91.128.51', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-18 21:45:03', 'historical_05f461491e9347aa9beff3fe5ef710ef'),
(55, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.73.181.114', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-18 14:48:39', 'historical_fb32094bb3e16fb0a47e7fbf8e755998'),
(56, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.67.209.150', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'United States', 'US', 'New York', NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-18 10:12:40', 'historical_617b73c69602621bc4f126154b701b0b'),
(57, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.37.167.154', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 22:53:36', 'historical_15197403758ca351b510a6169a82548a'),
(58, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.218.94.18', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'New York', NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-19 18:34:40', 'historical_2895ec213947f286b39030ee291a6c92'),
(59, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.114.255.247', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'La Plata', NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-19 12:21:51', 'historical_e2f5d2082ef76bfd041a90bc0fee411a'),
(60, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.59.123.123', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Firefox', 'macOS', '2025-07-19 18:28:05', 'historical_e729946e6d3d42f988770bd91e3ddc1b'),
(61, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.229.91.230', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'United States', 'US', 'Phoenix', NULL, NULL, NULL, 'mobile', 'Edge', 'Android', '2025-07-19 15:28:28', 'historical_3f0072e100693241e14b49968c99b805'),
(62, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.26.35.56', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-19 15:19:19', 'historical_73454bf64a97c989e7677c98a7776cf2'),
(63, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.120.73.17', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'mobile', 'Firefox', 'Android', '2025-07-19 16:34:03', 'historical_8a7e039fbf642fb34352a01cab8a775a'),
(64, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.61.25.153', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'tablet', 'Edge', 'iOS', '2025-07-19 09:33:32', 'historical_5e6fb17f647992009f394628cfc2a30b'),
(65, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.158.115.90', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-19 15:40:55', 'historical_39f35f8ff2461d6c2be0605603a0825a'),
(66, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.164.174.50', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Phoenix', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-19 12:37:31', 'historical_548630b8a51d2604f561b7e574878762'),
(67, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.81.2.219', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Medellín', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-19 11:12:52', 'historical_17fc93074b6f9d474cc3dfb610af2791'),
(68, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.224.60.95', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Medellín', NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-19 20:58:39', 'historical_57afcce1e9744feb43c886608fc510dc'),
(69, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.33.111.240', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'desktop', 'Edge', 'Android', '2025-07-19 18:05:53', 'historical_3130b58e603e88c9db7b2f68ed25dadf'),
(70, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.234.82.252', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Monterrey', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 19:40:09', 'historical_d42b0dd6a18ab7ea4b97c9224e3da0ca'),
(71, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.86.59.117', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-19 19:27:52', 'historical_8c494ada5279e0098e738c244425b6a0'),
(72, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.152.50.166', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'tablet', 'Firefox', 'Windows', '2025-07-19 17:53:56', 'historical_410f6733b40c3c32fb8be4fb15b97206'),
(73, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.83.109.62', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-19 22:18:33', 'historical_0ff7a07202f8af6480b5113e2d753033'),
(74, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.146.69.233', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-19 15:47:49', 'historical_f6b599a6d0d82456f38e15c7a51ad41d'),
(75, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.134.130.41', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Monterrey', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 21:54:17', 'historical_502b4a6f85851279edd3ba682fba6d81'),
(76, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.78.150.66', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'mobile', 'Safari', 'Windows', '2025-07-19 21:59:39', 'historical_fe606255a1d07fa80a7d8c94f286e3c4'),
(77, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.146.6.138', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'Córdoba', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-19 17:26:19', 'historical_d54f2b8ac1228f28fb2ef677db64a149'),
(78, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.62.141.254', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Medellín', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 18:28:39', 'historical_d5e0c19d209973a30977aa0b3bb1252c'),
(79, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.72.137.193', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'tablet', 'Firefox', 'Windows', '2025-07-19 19:09:06', 'historical_19d5c084d0b293c89d52a2a206c9264f'),
(80, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.54.27.28', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'mobile', 'Firefox', 'macOS', '2025-07-19 08:08:38', 'historical_c9b14939bd4a0c6762c164763ee09f6f'),
(81, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.49.98.201', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'tablet', 'Firefox', 'Windows', '2025-07-19 12:33:18', 'historical_4d1b13100f7b21c729e21de6fc9941eb'),
(82, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.110.112.214', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-19 09:35:08', 'historical_c2d81511f517bc5a18b3770f49e913c7'),
(83, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.209.152.188', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Italy', 'IT', NULL, NULL, NULL, NULL, 'tablet', 'Safari', 'macOS', '2025-07-19 14:31:52', 'historical_44f20105af5568982f172954c500c0be'),
(84, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.139.175.141', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Miami', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-19 14:19:58', 'historical_75ac4484f3d7efb130aa078ce60f6e3d'),
(85, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.12.66.246', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Edge', 'Android', '2025-07-19 15:21:19', 'historical_535ac9c652599ca9d97fd2aa6f1fb2a1'),
(86, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.120.136.117', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-19 13:59:55', 'historical_618473097fbc012d7d2a3ffb85adda57'),
(87, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.53.185.198', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'desktop', 'Safari', 'Android', '2025-07-19 20:02:37', 'historical_859bcfc7ae06c8df01770c2e02294bdf'),
(88, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.248.43.16', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Monterrey', NULL, NULL, NULL, 'tablet', 'Chrome', 'Linux', '2025-07-19 15:47:19', 'historical_dd9fb0ea6ff5ac68bac2f4863e88c8f7'),
(89, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.142.73.96', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-19 09:34:00', 'historical_6ca6958b79819b6f7e0165dc5e1cfb9e'),
(90, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.218.224.248', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'tablet', 'Safari', 'iOS', '2025-07-19 18:41:09', 'historical_532092e60d6970846c0f09798197ab4b'),
(91, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.166.164.194', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-19 13:21:46', 'historical_d21d8a1c0c6946f9eaf4063b89b9b66c'),
(92, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.21.147.90', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'tablet', 'Edge', 'Windows', '2025-07-19 12:06:43', 'historical_792a592c91dd46946c7037670cf87c1f'),
(93, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.25.32.196', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'desktop', 'Edge', 'Android', '2025-07-19 19:04:00', 'historical_5673f0d2dbd07d6c295288cce4ea164f'),
(94, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.84.246.3', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Barranquilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-19 17:22:39', 'historical_5317ba54dbfc8fbc54d26df4c35e7f24'),
(95, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.148.150.9', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-19 22:11:40', 'historical_b0390d17cf6a9b39626b77adfaec4560'),
(96, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.150.107.60', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'desktop', 'Edge', 'macOS', '2025-07-19 19:48:42', 'historical_2732efebbf4d94881f66251fb0503cf5'),
(97, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.32.50.62', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 09:38:11', 'historical_b86d5c1240b09bdf7f2f5edc217cc96a'),
(98, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.254.68.158', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-19 09:59:22', 'historical_94980023f20f041de28bbcc6b7fa0138'),
(99, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.44.37.247', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Germany', 'DE', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-19 14:15:31', 'historical_7c8c710962c4da0528eb668bf52857c9'),
(100, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.119.184.144', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'Córdoba', NULL, NULL, NULL, 'mobile', 'Firefox', 'iOS', '2025-07-19 08:56:20', 'historical_c89851453d0cadfc0f0e9733e44a58f8'),
(101, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.176.168.69', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'United Kingdom', 'GB', 'Manchester', NULL, NULL, NULL, 'tablet', 'Edge', 'iOS', '2025-07-19 20:25:00', 'historical_516db0120b9d84892d352f48e3a1f61e'),
(102, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.176.70.243', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'desktop', 'Firefox', 'iOS', '2025-07-19 08:05:18', 'historical_9566c21cd1df4355b5c36a22236d74a7'),
(103, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.129.225.106', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-19 14:25:35', 'historical_c60efe48974cd5185a35096faad68b97'),
(104, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.182.57.56', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Guatemala', 'GT', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-20 13:13:35', 'historical_c383d63fbe9605d6f87bdcfc663e0fdc'),
(105, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.30.79.233', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-20 12:39:30', 'historical_f155e2df628331bfbe1b6d4f6f8da1e6'),
(106, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.223.209.86', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-20 13:01:11', 'historical_8062d269023c0ab18ac9013cf29cc2c4'),
(107, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.51.83.247', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-20 09:02:45', 'historical_d78fded31cb15cafbfb734aca470fece'),
(108, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.98.4.60', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Argentina', 'AR', 'Rosario', NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-20 15:50:44', 'historical_41d1d1d7e1a9d86b28c41b228b240758'),
(109, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.59.51.101', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-20 12:38:50', 'historical_b5a36c69cf1108b00d227135618ca8bf'),
(110, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.204.255.246', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-20 09:39:02', 'historical_ceae5b3f7d2f219d42cb23eafc8179d3'),
(111, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.187.122.145', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Bogotá', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-20 18:25:20', 'historical_560d784bcb97f28c963ab3fb2d422400'),
(112, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.19.89.233', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-20 14:49:29', 'historical_923aa36b070150e58e882869fe1600dc'),
(113, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.15.188.196', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Phoenix', NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-20 19:56:13', 'historical_6aea7f9c5813d0211c1e442ec375920e'),
(114, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.98.86.176', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-20 13:53:02', 'historical_ddeab391800a9b6d99bf76a1b16236c5'),
(115, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.16.8.176', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'desktop', 'Edge', 'macOS', '2025-07-20 14:22:05', 'historical_9fc3367504b072c400dc42ea28df6282'),
(116, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.215.64.177', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-20 12:05:14', 'historical_5ada10471ffe245f398fcb868152553b'),
(117, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.177.131.56', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'United States', 'US', 'Los Angeles', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-20 18:44:44', 'historical_dd4c8de55ffdcdc7ee42f24c971d5d42'),
(118, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.213.43.183', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-20 22:10:59', 'historical_8f69da483a21e94befbdd8fbc778e303'),
(119, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.110.12.4', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-20 08:18:51', 'historical_1c0e4346c08fe0e718c20348437d2243'),
(120, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.206.12.72', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'tablet', 'Safari', 'macOS', '2025-07-20 18:16:16', 'historical_e4f3ade74a170f7d8f70c9586e127cd3'),
(121, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.142.38.24', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'desktop', 'Safari', 'Linux', '2025-07-20 12:16:43', 'historical_62454219262905d3f09789376d14eb69'),
(122, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.61.249.219', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Houston', NULL, NULL, NULL, 'tablet', 'Chrome', 'Linux', '2025-07-20 09:01:29', 'historical_194f4b2599be39f0dd039e820680cd6f'),
(123, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.181.236.164', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-20 11:27:55', 'historical_86babe04019c8d6669dc0255edd3a129'),
(124, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.33.229.248', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-20 08:04:34', 'historical_0c5e54fb9da415d216382598454ed92c'),
(125, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.203.96.165', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-20 22:16:00', 'historical_dd79fc1d8d5dd4511e70c6cddbd46498'),
(126, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.85.157.210', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'mobile', 'Safari', 'Android', '2025-07-20 12:46:15', 'historical_fa422641eb08452134c8c019bc19b18d'),
(127, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.181.106.230', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Miami', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-20 21:23:14', 'historical_a4e91c6e2f7e98305d221ac2c4772ce3'),
(128, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.177.148.105', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'iOS', '2025-07-20 14:47:02', 'historical_1db3a429e39057147f4b6822fed070f7'),
(129, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.3.178.214', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Chicago', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 08:55:14', 'historical_2c8dd561525d711ffb4ec32121faca5e'),
(130, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.125.202.150', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Colombia', 'CO', 'Medellín', NULL, NULL, NULL, 'desktop', 'Firefox', 'Android', '2025-07-20 17:17:23', 'historical_2094e6616637ebe64f720b8caf76b2fc'),
(131, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.223.176.62', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'desktop', 'Safari', 'iOS', '2025-07-20 16:25:42', 'historical_dc01a39b9a195f11e4263a796f9de3ed'),
(132, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.105.195.190', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Dominican Republic', 'DO', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-20 11:19:41', 'historical_4668dcc98d92cc4a3b9a119ffaaed22a'),
(133, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.144.223.209', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'La Plata', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-20 20:33:00', 'historical_e28daff96f55f580c3198d56f09f12e5'),
(134, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.48.105.139', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'mobile', 'Safari', 'Windows', '2025-07-20 11:51:25', 'historical_134d7e9fe9c76223775c875c8095f7d0'),
(135, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.99.35.127', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Guatemala', 'GT', NULL, NULL, NULL, NULL, 'mobile', 'Firefox', 'Linux', '2025-07-20 19:21:49', 'historical_0efef44a193b0036e32eeae77acd76c1'),
(136, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.23.145.48', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Colombia', 'CO', 'Cartagena', NULL, NULL, NULL, 'mobile', 'Safari', 'iOS', '2025-07-20 08:40:19', 'historical_6aec4226f0b6b64b59a1a7a39b80dd9a'),
(137, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.200.64.109', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'mobile', 'Edge', 'Android', '2025-07-20 18:25:14', 'historical_2d4e7f5072187d7582ff6863adcb5129'),
(138, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.202.61.154', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Argentina', 'AR', 'Buenos Aires', NULL, NULL, NULL, 'mobile', 'Safari', 'Android', '2025-07-20 20:55:32', 'historical_e45a9cbf6e4364896fb55371b575cab2'),
(139, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.124.91.145', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Brazil', 'BR', NULL, NULL, NULL, NULL, 'tablet', 'Safari', 'Android', '2025-07-20 13:51:34', 'historical_c1b8ea99aa84c0222ef6d769accaf538'),
(140, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.161.213.71', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-20 09:53:36', 'historical_6cec0738a5ce71a85d6c818964d44708'),
(141, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.235.115.234', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-20 12:40:38', 'historical_8dfac37fc50ce8b9cbc9918fff2e7a07'),
(142, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.7.195.174', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Germany', 'DE', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-20 15:26:45', 'historical_77fa25146c4b7aa3862cd6fe25b38f89'),
(143, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.73.60.112', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-20 20:17:12', 'historical_77e375fba8486e0ee2c83cbac2f0d4cb'),
(144, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.47.37.148', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-20 18:55:43', 'historical_5c503bf64517afbae11bbc7ea68f8674'),
(145, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.48.188.71', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-20 17:32:16', 'historical_314808ecacaeccfe479e9703c4eb1deb'),
(146, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.63.132.252', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'La Plata', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-20 14:48:11', 'historical_18301cfebd8063b354a8f94b88feb018'),
(147, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.120.4.238', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-20 14:41:56', 'historical_359d59eb3c2d6698b19f86c1058f0165'),
(148, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.113.254.54', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 08:51:38', 'historical_61b982ee3e472481d1d5ba80033c2f81'),
(149, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.185.36.237', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'mobile', 'Safari', 'macOS', '2025-07-20 22:30:42', 'historical_df57d02d1040968ef26f3965f472f4b0'),
(150, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.195.111.132', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Córdoba', NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-20 19:11:49', 'historical_a72ec90b39e3742b47a58df58e172796'),
(151, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.131.75.126', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 11:25:25', 'historical_b90c9c7794ea52c169898fdb3b7dd803'),
(152, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.209.21.154', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-20 22:04:48', 'historical_7476f17faf0dcad418b38ccacf8518f7'),
(153, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.239.208.115', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-20 21:17:01', 'historical_c4dd0cf8fdeb7ad5e3f14fcfacae4016'),
(154, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.4.5.10', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'tablet', 'Edge', 'Windows', '2025-07-20 15:36:43', 'historical_9404829c487b073f0e0703ff6f76cb6d'),
(155, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.80.90.79', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'mobile', 'Safari', 'Windows', '2025-07-20 15:26:01', 'historical_f9876c693b3c6b228266f47664937339'),
(156, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.232.84.133', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'tablet', 'Chrome', 'Android', '2025-07-20 17:10:19', 'historical_cfdc9455fb990f83f9dde369e31692fc'),
(157, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.142.166.20', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Italy', 'IT', NULL, NULL, NULL, NULL, 'tablet', 'Safari', 'Android', '2025-07-20 22:36:20', 'historical_e06e82ab4ee9d6a38c55825a549eb3c8'),
(158, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.24.236.239', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-20 16:10:58', 'historical_649de996e29bef40bce21a4a735b2783'),
(159, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.110.2.156', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'tablet', 'Safari', 'macOS', '2025-07-20 10:17:56', 'historical_a08c60c884b8cd9f0c020cf6330bcd48'),
(160, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.17.247.186', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-20 19:37:04', 'historical_cb94dedae3562e5d5ef29d1e5b3108d9'),
(161, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.177.196.187', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Colombia', 'CO', 'Cartagena', NULL, NULL, NULL, 'desktop', 'Edge', 'Linux', '2025-07-20 11:29:44', 'historical_3e4f3cb1f7ea9f4e54e860f1c946ede3'),
(162, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.149.123.80', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'mobile', 'Firefox', 'Android', '2025-07-20 09:35:08', 'historical_2b08db1c6033f2acf0ba843d36cd2a64'),
(163, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.85.224.137', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United Kingdom', 'GB', 'Edinburgh', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-20 09:10:18', 'historical_985513a8c440eeace92f37f26e6524de'),
(164, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.2.92.231', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Argentina', 'AR', 'Buenos Aires', NULL, NULL, NULL, 'mobile', 'Safari', 'macOS', '2025-07-20 15:30:33', 'historical_631f6ad845b07a682d7c8d676e1be59b'),
(165, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.201.55.53', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'mobile', 'Edge', 'Android', '2025-07-20 14:07:48', 'historical_94e2ba7e4d3fcfe3882247b1222bc1d2'),
(166, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.127.164.192', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'tablet', 'Firefox', 'Windows', '2025-07-20 16:41:03', 'historical_9a5ac0d812ca2dc8bf0af6dd95f4fd68'),
(167, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.14.59.229', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Italy', 'IT', NULL, NULL, NULL, NULL, 'desktop', 'Firefox', 'Linux', '2025-07-20 12:40:10', 'historical_de21ced5b5ec9dc4e9243b799a76b193'),
(168, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.159.179.177', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-20 22:17:20', 'historical_60e2060ac6ad32ff0bc1066fb719ff97'),
(169, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.184.182.69', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'Android', '2025-07-20 22:22:57', 'historical_d5f90c4791e6d9d34c95de7188b88cba'),
(170, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.147.119.59', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Barcelona', NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-20 08:29:24', 'historical_b632e0fcb297bc2a7e9a35471c10c87e'),
(171, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.151.0.253', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-20 20:08:59', 'historical_b9238745cea65212c01afa19e22a54eb'),
(172, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.5.27.187', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Dominican Republic', 'DO', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-20 09:51:25', 'historical_0b781bf705b8db1fcde04d6b3c0d5949'),
(173, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.183.232.74', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-20 12:17:38', 'historical_03bc5879672b547e86a58f5723894091'),
(174, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.15.234.108', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 10:42:53', 'historical_d260db2fa553f93634ba74eeb9623466'),
(175, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.69.186.159', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'Rosario', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-20 14:15:40', 'historical_3f609707a7dc41b374dc15a8d8fb47cc'),
(176, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.31.166.99', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-20 08:02:52', 'historical_f0bd303cf8ef4ca564e39097332e0fdf'),
(177, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.183.159.204', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Colombia', 'CO', 'Barranquilla', NULL, NULL, NULL, 'mobile', 'Edge', 'Android', '2025-07-20 20:40:51', 'historical_216aa12a96d6d41ea177414b9a54cee4'),
(178, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.99.72.235', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-20 13:04:37', 'historical_2c86ec538c631c26212e230ccef373bc'),
(179, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.109.83.106', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'mobile', 'Firefox', 'Android', '2025-07-20 19:38:59', 'historical_6d335770ac221cb3e641f6eb702bd980'),
(180, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.40.242.216', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-20 17:14:17', 'historical_6880c21c2070a1c49778ea21fee9a392'),
(181, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.76.199.10', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'Buenos Aires', NULL, NULL, NULL, 'mobile', 'Firefox', 'Linux', '2025-07-20 14:55:12', 'historical_a93931279a4f5c8fe34371c1750908e4'),
(182, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.247.47.143', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'United Kingdom', 'GB', 'Manchester', NULL, NULL, NULL, 'desktop', 'Edge', 'Linux', '2025-07-20 09:39:10', 'historical_fca08f6fd974a7f37e394fc8a1ec4d01'),
(183, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.184.135.204', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-20 12:10:57', 'historical_e96f841aabf3d156f9e24bf9963b7b80'),
(184, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.18.149.230', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-20 12:58:45', 'historical_aa7d17f3235361d57028d46589b3d78a'),
(185, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.191.73.29', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Argentina', 'AR', 'La Plata', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-20 08:06:25', 'historical_3052a8bc19873648808c56f1579ef080'),
(186, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.144.227.2', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-20 13:30:32', 'historical_8d89fa468ad852948e55d5a2c267ed20'),
(187, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.128.78.239', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-20 10:00:21', 'historical_3a904b5dd8b485bef450d1eadda5550b');
INSERT INTO `url_analytics` (`id`, `url_id`, `user_id`, `short_code`, `ip_address`, `user_agent`, `referer`, `country`, `country_code`, `city`, `region`, `latitude`, `longitude`, `device_type`, `browser`, `os`, `clicked_at`, `session_id`) VALUES
(188, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.132.227.197', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 12:19:15', 'historical_c241ef692d8ab2130f0f615d50fd1fc1'),
(189, 92, 17, 'PerdidoCaT', '10.133.243.178', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-17 13:21:40', 'historical_50e5d62dd405d21d7b00df638a956944'),
(190, 92, 17, 'PerdidoCaT', '10.96.142.143', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-17 19:25:41', 'historical_05e3644b51464d306193ec82a9f938db'),
(191, 92, 17, 'PerdidoCaT', '10.19.85.67', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-17 18:08:38', 'historical_d71d8ef9b3a8c0085ac22fd0509cc38b'),
(192, 92, 17, 'PerdidoCaT', '10.181.196.184', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Firefox', 'Linux', '2025-07-17 18:14:14', 'historical_8bc83623c223504b07576ae867dc2d94'),
(193, 92, 17, 'PerdidoCaT', '10.99.163.29', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Edge', 'macOS', '2025-07-17 16:16:23', 'historical_b3ce57df3917b143fe640aa7ed9ebcda'),
(194, 92, 17, 'PerdidoCaT', '10.255.129.11', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-17 14:45:23', 'historical_11541ba56c010bd91791b582fb8b332f'),
(195, 92, 17, 'PerdidoCaT', '10.225.20.159', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-17 18:14:33', 'historical_cddf88383d741768c8071cd7873be76d'),
(196, 92, 17, 'PerdidoCaT', '10.15.129.138', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-17 18:41:04', 'historical_e84192ed65f6dfb9e67b085fd41ffb8d'),
(197, 92, 17, 'PerdidoCaT', '10.26.200.2', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'tablet', 'Firefox', 'Linux', '2025-07-17 16:01:14', 'historical_8cdef57790289c658e38c5b6b39d6819'),
(198, 92, 17, 'PerdidoCaT', '10.99.174.60', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'tablet', 'Chrome', 'Android', '2025-07-17 08:27:30', 'historical_2721a366141b6cbe0ad8ef8846130a3b'),
(199, 92, 17, 'PerdidoCaT', '10.119.64.27', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-18 12:26:46', 'historical_c7e948d5e2ce135a06fdbb37b41ca04f'),
(200, 92, 17, 'PerdidoCaT', '10.134.157.75', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-18 22:00:47', 'historical_7ecc70a7f6d0ca8439d698f7d2216844'),
(201, 92, 17, 'PerdidoCaT', '10.11.222.204', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-18 18:56:41', 'historical_63d01b98297b716456aa006524d0951a'),
(202, 92, 17, 'PerdidoCaT', '10.213.126.197', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-18 09:25:14', 'historical_d074577321987c2b1b5862c81fcf5af9'),
(203, 92, 17, 'PerdidoCaT', '10.72.197.147', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Edge', 'Android', '2025-07-18 15:39:48', 'historical_a711079df0b8be63c4c05049beec159b'),
(204, 92, 17, 'PerdidoCaT', '10.116.230.7', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 17:10:55', 'historical_24acdedd1defb0980f2d21c38395ea2d'),
(205, 92, 17, 'PerdidoCaT', '10.119.39.124', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'desktop', 'Edge', 'Android', '2025-07-18 10:09:07', 'historical_1004b66fb13b2e85d192612c7451f7ca'),
(206, 92, 17, 'PerdidoCaT', '10.1.32.112', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'United States', 'US', 'New York', NULL, NULL, NULL, 'mobile', 'Firefox', 'Windows', '2025-07-18 15:27:36', 'historical_737a3418fc130d5b9f42393d88054ff6'),
(207, 92, 17, 'PerdidoCaT', '10.99.178.5', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-18 13:58:52', 'historical_31d0618823c9b053fa397aaf880643f0'),
(208, 92, 17, 'PerdidoCaT', '10.225.95.208', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'desktop', 'Safari', 'Android', '2025-07-19 08:33:40', 'historical_349581abdb579fc6cb67f7a9da1bfd09'),
(209, 92, 17, 'PerdidoCaT', '10.217.138.9', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-19 14:36:48', 'historical_8e741c96d374eba87779290d6700d67a'),
(210, 92, 17, 'PerdidoCaT', '10.35.254.126', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 13:34:11', 'historical_93675d21a2813d9b012c700f88c55537'),
(211, 92, 17, 'PerdidoCaT', '10.177.6.233', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-19 10:04:05', 'historical_64b76fde049f5a8031d65ff89ba773c8'),
(212, 92, 17, 'PerdidoCaT', '10.8.188.30', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Edge', 'Android', '2025-07-19 21:52:50', 'historical_06b9ceeaada88dafc0c56096c51592f3'),
(213, 92, 17, 'PerdidoCaT', '10.8.41.146', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-19 17:07:04', 'historical_e90d8f401679a403d9fc719b57124745'),
(214, 92, 17, 'PerdidoCaT', '10.141.14.162', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-19 10:27:21', 'historical_71d75a47b1e01b0832786f13aea5e0b3'),
(215, 92, 17, 'PerdidoCaT', '10.45.219.254', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Firefox', 'Linux', '2025-07-19 21:28:34', 'historical_7e4c00ecb75593b821ecdc55f61c6570'),
(216, 92, 17, 'PerdidoCaT', '10.238.127.230', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'mobile', 'Chrome', 'Android', '2025-07-19 21:41:01', 'historical_e559d7fda46cdbae609742ccefde1130'),
(217, 92, 17, 'PerdidoCaT', '10.222.9.126', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Safari', 'iOS', '2025-07-20 19:31:29', 'historical_4a1fc1f8777bcec170bdc3e4a2102a1e'),
(218, 92, 17, 'PerdidoCaT', '10.231.51.148', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-20 10:54:07', 'historical_be98c21c3d73e1ec420fd27abcd0be80'),
(219, 92, 17, 'PerdidoCaT', '10.199.121.91', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-20 09:16:56', 'historical_194c553acf4f277e167012a1848e7db4'),
(220, 92, 17, 'PerdidoCaT', '10.14.58.254', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'tablet', 'Firefox', 'Android', '2025-07-20 17:47:40', 'historical_3d7672d6ea18d564d705160e9ac3c92c'),
(221, 92, 17, 'PerdidoCaT', '10.194.44.150', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Valencia', NULL, NULL, NULL, 'mobile', 'Safari', 'Windows', '2025-07-20 19:36:20', 'historical_41dc296f2be9eebcb7607c1bbc7f4178'),
(222, 92, 17, 'PerdidoCaT', '10.111.137.120', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-20 19:33:03', 'historical_8cad9d06ce4822df999e761d3fd42928'),
(223, 92, 17, 'PerdidoCaT', '10.221.207.221', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-20 20:46:28', 'historical_ece0237e111c55b83ca4613a0aeab8a6'),
(224, 92, 17, 'PerdidoCaT', '10.89.105.133', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-20 19:09:22', 'historical_6644d77cf293de2e8cd9dbd8105f2564'),
(225, 92, 17, 'PerdidoCaT', '10.9.116.17', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'tablet', 'Chrome', 'Android', '2025-07-20 21:42:03', 'historical_6f278ef713b561d3b033076a972aedec'),
(226, 92, 17, 'PerdidoCaT', '10.151.249.76', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'tablet', 'Edge', 'Windows', '2025-07-20 17:17:45', 'historical_97eed94b8d7aa894c79edd7a01419859'),
(227, 92, 17, 'PerdidoCaT', '10.144.204.241', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-20 10:31:08', 'historical_19422e6dedc2a7912d3bbbda3d7886e1'),
(228, 92, 17, 'PerdidoCaT', '10.220.136.186', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Safari', 'macOS', '2025-07-20 15:20:43', 'historical_95cdfa0239bbb61922897a0e1d555b47'),
(229, 92, 17, 'PerdidoCaT', '10.12.8.196', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'tablet', 'Chrome', 'Linux', '2025-07-20 22:01:11', 'historical_21cbcd6e755848f00e439b357127004f'),
(230, 92, 17, 'PerdidoCaT', '10.96.130.175', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-20 17:11:49', 'historical_822f33b84b14920d202f82c13b294aa6'),
(231, 92, 17, 'PerdidoCaT', '10.81.31.252', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Phoenix', NULL, NULL, NULL, 'desktop', 'Chrome', 'iOS', '2025-07-20 17:05:51', 'historical_31d7f5eb6243f2843d4b06f538fe4292'),
(232, 92, 17, 'PerdidoCaT', '10.251.165.169', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'mobile', 'Edge', 'Windows', '2025-07-20 08:43:08', 'historical_33c58841bb43c49a096b82248153d77f'),
(233, 92, 17, 'PerdidoCaT', '10.208.200.219', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'mobile', 'Safari', 'Linux', '2025-07-20 21:32:18', 'historical_20066eba19f66195974deaf8cbb58256'),
(234, 92, 17, 'PerdidoCaT', '10.149.92.159', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Edge', 'iOS', '2025-07-20 18:41:40', 'historical_5d816646963acd0862262010e1988728'),
(235, 92, 17, 'PerdidoCaT', '10.62.205.199', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'tablet', 'Chrome', 'Linux', '2025-07-20 15:12:25', 'historical_a5f58f2783c678a3e11ac5ef600ddca9'),
(261, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.26.36.208', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 17:00:10', 'historical_3a4cb456078e7e1895fe1e9651029e13'),
(262, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.68.94.70', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Edge', 'Linux', '2025-07-18 15:11:40', 'historical_91195580bd666c370d753215cb1ab6ad'),
(263, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.98.200.194', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-18 12:16:57', 'historical_f6a8114aec901de3b151d96e83f1f2fa'),
(264, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.251.166.224', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Italy', 'IT', NULL, NULL, NULL, NULL, 'tablet', 'Safari', 'iOS', '2025-07-18 16:34:48', 'historical_ac0c7e81df69efb125593d4f0b8b320d'),
(265, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.23.212.142', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'tablet', 'Edge', 'Linux', '2025-07-18 15:29:18', 'historical_4744f7a257208ca91ec6147dc6bd133f'),
(266, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.4.154.72', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-18 21:16:41', 'historical_80e12050169d05753fb05039ff053d3b'),
(267, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.129.189.29', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-18 16:03:40', 'historical_a38e44b217d5a7a3ccbd45fd502b7bbc'),
(268, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.56.206.211', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-19 18:27:25', 'historical_0e231624a383af2d72cc4373affd9a83'),
(269, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.150.168.30', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Guatemala', 'GT', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-19 16:05:22', 'historical_3ae51ec030d770bbb7c9d0491767ed78'),
(270, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.164.94.211', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-19 11:50:33', 'historical_5ee713fab720493a02d216e56d132ba8'),
(271, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.123.155.235', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'tablet', 'Firefox', 'Android', '2025-07-19 13:11:36', 'historical_e4415f8fa53bd4d78c5fbd1b21e0e6f7'),
(272, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.204.163.6', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-19 18:58:17', 'historical_315eda0c42148ab01a5ee07ab0cc5aff'),
(273, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.6.121.228', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 12:20:44', 'historical_e0b22c1378166781c33d04d88ba5e4eb'),
(274, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.134.253.181', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 15:12:01', 'historical_7c0b6a8844370a4712a8b975071af06a'),
(275, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.174.44.51', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-20 17:37:27', 'historical_638b058f057b831f4265930e9b90e005'),
(276, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.125.43.5', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Puebla', NULL, NULL, NULL, 'tablet', 'Chrome', 'macOS', '2025-07-20 19:49:20', 'historical_28e6b8e6a0f716c7ffd37fd19bcbc0a0'),
(277, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.39.108.171', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'La Plata', NULL, NULL, NULL, 'desktop', 'Firefox', 'macOS', '2025-07-20 20:22:41', 'historical_27c42fa89f13d02ff415618b45f69f50'),
(278, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.254.168.244', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-20 09:22:37', 'historical_b7fc7cdedf5f9deda3a3ee8883b4157d'),
(279, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.254.57.217', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Argentina', 'AR', 'Rosario', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-20 17:28:28', 'historical_be4932d61295ab1d2a23af9fa565ec3b'),
(280, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.205.71.205', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-20 08:06:44', 'historical_e05dcd25e0509efc872295e3503f3c9f'),
(339, 97, 1, 'la_biblioteca_personal', '10.162.233.110', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Guatemala', 'GT', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-18 13:17:26', 'historical_eaa50f69dc0c7229bced31dc5ca0b5c1'),
(340, 97, 1, 'la_biblioteca_personal', '10.20.215.135', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'tablet', 'Firefox', 'macOS', '2025-07-18 22:47:16', 'historical_33d9d60a83ef132e3fb5bc40a6eec176'),
(341, 97, 1, 'la_biblioteca_personal', '10.40.25.223', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Colombia', 'CO', 'Cartagena', NULL, NULL, NULL, 'mobile', 'Edge', 'Windows', '2025-07-19 22:31:43', 'historical_91196d1937ce30f32861d1c25469e8cf'),
(342, 97, 1, 'la_biblioteca_personal', '10.200.173.172', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'United States', 'US', 'Houston', NULL, NULL, NULL, 'desktop', 'Firefox', 'macOS', '2025-07-19 09:24:12', 'historical_27930f6ca20e7f66798be78cc42ad796'),
(343, 97, 1, 'la_biblioteca_personal', '10.122.22.124', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Argentina', 'AR', 'Rosario', NULL, NULL, NULL, 'mobile', 'Firefox', 'Linux', '2025-07-20 11:46:48', 'historical_43ba5910ccffda0fc0f2e38de26e6ca2'),
(344, 97, 1, 'la_biblioteca_personal', '10.57.179.42', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Los Angeles', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-20 18:16:52', 'historical_47855f8a05d86e2f3821b825a611b039'),
(355, 91, 17, 'PhuVeJ', '10.195.128.200', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-17 15:28:11', 'historical_d00cb133a4d0b319d3cb9f84588aeda7'),
(356, 91, 17, 'PhuVeJ', '10.253.231.156', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-18 09:09:52', 'historical_a17761d717c0f861493fe9fe4415fd1c'),
(357, 91, 17, 'PhuVeJ', '10.38.145.34', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 15:48:03', 'historical_ee846ca6b1600ac0f680bc43fe31cdb3'),
(358, 91, 17, 'PhuVeJ', '10.40.58.223', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Edge', 'Windows', '2025-07-20 14:50:44', 'historical_f78bb89be54b2fa5698e98ffa01cf758'),
(363, 57, 1, 'vosNy7', '10.199.236.141', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'macOS', '2025-07-13 20:53:57', 'historical_9cf6088bf5b1c695ed6125aba69a6a43'),
(364, 57, 1, 'vosNy7', '10.207.245.121', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'mobile', 'Firefox', 'macOS', '2025-07-14 21:40:23', 'historical_a2336885806c9964ee30a4e69f2b69ef'),
(365, 57, 1, 'vosNy7', '10.213.113.9', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Ecuador', 'EC', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-15 14:52:37', 'historical_4663de8d625e17589066318ae7da5132'),
(366, 57, 1, 'vosNy7', '10.188.219.237', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'desktop', 'Edge', 'Linux', '2025-07-16 19:21:45', 'historical_f1f2e659d24e2e2a8a2cbac44900d760'),
(374, 90, 17, 'HBHXj6', '10.188.127.57', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'La Serena', NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-17 09:23:34', 'historical_ff9e749420eea05e8a54b714091ca96b'),
(375, 90, 17, 'HBHXj6', '10.205.29.148', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Santiago', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-18 21:36:53', 'historical_fb26a5f554a4111947ed6cdae880fcb2'),
(376, 90, 17, 'HBHXj6', '10.5.0.184', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-19 18:50:33', 'historical_757b358036d7a7533f3cd20dd6630785'),
(377, 89, 17, 'NyKMCD', '10.222.207.189', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-17 19:00:33', 'historical_7a8080da2124fea151070ecaaaa42b3b'),
(378, 89, 17, 'NyKMCD', '10.39.111.135', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-18 08:57:48', 'historical_65fb956ffbce50b351477716280ac082'),
(379, 89, 17, 'NyKMCD', '10.128.209.193', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United Kingdom', 'GB', 'Liverpool', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-19 12:16:46', 'historical_b86762cb0e14e82d0cd8de481a8a8bcc'),
(380, 53, 1, '2Dtjhy', '10.99.129.85', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'United States', 'US', 'Phoenix', NULL, NULL, NULL, 'mobile', 'Safari', 'Windows', '2025-07-13 12:42:50', 'historical_d01720f03bf8dfb2f1b58d6793ee1dc1'),
(381, 53, 1, '2Dtjhy', '10.200.116.199', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-14 09:27:09', 'historical_990f4ae9723c281614e7e1420190e80b'),
(382, 53, 1, '2Dtjhy', '10.9.30.41', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-15 21:29:29', 'historical_6284c95500bd8662c52e8e06d7b6a6f8'),
(383, 55, 1, 'fdeSit', '10.7.88.175', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-13 10:18:28', 'historical_3cb9417dee18b3f397a3a793ce89a2af'),
(384, 55, 1, 'fdeSit', '10.100.249.210', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Bilbao', NULL, NULL, NULL, 'tablet', 'Chrome', 'macOS', '2025-07-14 22:08:48', 'historical_d85823f0f95fd7bdd9a611aaf1a2117f'),
(385, 55, 1, 'fdeSit', '10.60.122.77', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Mexico', 'MX', 'Tijuana', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-15 10:10:00', 'historical_f538d2ac5ec581aaf34117aff5bcd77e'),
(389, 96, 1, 'Mi-Biblioteca_personal', '10.156.55.96', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'tablet', 'Chrome', 'iOS', '2025-07-18 22:11:18', 'historical_7e5b6a722d2472524a5a60c6ab7c0825'),
(390, 96, 1, 'Mi-Biblioteca_personal', '10.138.171.34', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Guadalajara', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-19 12:19:35', 'historical_b0d2eac6ae39709fbbd345ef7436e56b'),
(393, 58, 1, 'EVYDFU', '10.245.173.240', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Chile', 'CL', 'Concepción', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-13 21:39:43', 'historical_ec695f940e39cb15b1bb8252fd5c0d02'),
(394, 58, 1, 'EVYDFU', '10.164.64.122', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Colombia', 'CO', 'Cali', NULL, NULL, NULL, 'mobile', 'Chrome', 'iOS', '2025-07-14 12:30:48', 'historical_2499f711f5de8de73b918af0cada3165'),
(395, 56, 1, '8zaHZF', '10.57.203.103', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-13 15:17:55', 'historical_a5d24e245cf3d2433689bf4dd3b1f091'),
(396, 56, 1, '8zaHZF', '10.159.220.139', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'mobile', 'Chrome', 'Linux', '2025-07-14 13:23:19', 'historical_4befd3c3c38f0d5000abe63e9fb2a384'),
(397, 54, 1, 'lpBDcU', '10.230.93.34', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Spain', 'ES', 'Zaragoza', NULL, NULL, NULL, 'tablet', 'Safari', 'Windows', '2025-07-13 10:13:40', 'historical_cb317cbc9db0d3412a400ed9a8c3e6d0'),
(398, 54, 1, 'lpBDcU', '10.167.173.130', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Chile', 'CL', 'Valparaíso', NULL, NULL, NULL, 'desktop', 'Firefox', 'macOS', '2025-07-14 10:13:07', 'historical_65ba67e27de36257ef5d500f775ec762'),
(399, 52, 1, 'CgsEuL', '10.101.49.222', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Sevilla', NULL, NULL, NULL, 'desktop', 'Chrome', 'Android', '2025-07-13 18:58:42', 'historical_0e7b507a8746abe115d9b628250c8730'),
(400, 52, 1, 'CgsEuL', '10.231.255.74', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'desktop', 'Firefox', 'iOS', '2025-07-14 13:26:59', 'historical_4c6bdcc819a5b4d18be17c4d75b68a29'),
(401, 51, 1, 'IezPkI', '10.78.253.32', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Madrid', NULL, NULL, NULL, 'tablet', 'Chrome', 'Windows', '2025-07-13 22:57:34', 'historical_835ebb116888c5b6643d1b00425dd121'),
(402, 51, 1, 'IezPkI', '10.189.226.175', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Germany', 'DE', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'Linux', '2025-07-14 13:02:13', 'historical_fd79a0120a105d1beef04d672e9ca977'),
(407, 116, 13, 'Gobierno-CAT2', '10.126.231.33', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Argentina', 'AR', 'Mendoza', NULL, NULL, NULL, 'mobile', 'Edge', 'iOS', '2025-07-19 11:53:51', 'historical_428f9087d7ee43ddc964616c1ab50fd1'),
(408, 117, 13, 'TsMYj9', '10.192.247.183', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'United States', 'US', 'Los Angeles', NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-19 10:05:55', 'historical_d0b1cc24ae10cd5389d16d5a327f42e7'),
(411, 60, 1, 'liL38N', '10.60.120.97', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Peru', 'PE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-13 22:32:00', 'historical_f4c7660eed3248ef4c041596a00458c3'),
(412, 59, 1, 'KnIyVp', '10.76.132.141', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Mexico', 'MX', 'Ciudad de México', NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-13 08:12:37', 'historical_15db156d77d4ac7505b9b6259df00f66'),
(416, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.243.111.55', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'macOS', '2025-07-18 09:55:17', 'historical_2ccd6032cd4d3b2842acc6c8e9264b6b'),
(417, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.35.150.99', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Firefox', 'Windows', '2025-07-18 10:05:12', 'historical_a52ee4f1928675746d5644a282451eb3'),
(418, 111, 1, 'ElGobiernoLoDaPorPerdidoElCat', '10.27.2.232', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-19 10:44:00', 'historical_31ae3245d50784f09758f552f07aad38'),
(419, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.244.33.201', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'mobile', 'Safari', 'Linux', '2025-07-18 22:00:34', 'historical_739276864deab98f3b3e29e83ff43ce7'),
(420, 112, 1, 'PasoAtrasDeSanchezConElCatEnLaUE', '10.50.233.237', 'Mozilla/5.0 (Compatible; HistoricalClick/Firefox)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Firefox', 'macOS', '2025-07-19 11:08:48', 'historical_b6b0ee15215e3e037f6925ae8267be21'),
(421, 97, 1, 'la_biblioteca_personal', '10.25.217.39', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Spain', 'ES', 'Málaga', NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-18 16:00:35', 'historical_a243c327a905156760b49da86ee1a401'),
(423, 96, 1, 'Mi-Biblioteca_personal', '10.132.171.162', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Safari', 'Android', '2025-07-18 13:45:24', 'historical_649ffd982d1a75b311d02f14a319f25b'),
(424, 152, 1, 'RXua4A', '10.227.127.95', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Linux', '2025-07-21 10:04:32', 'historical_141bb2f8c0f8e51953ce322fc788b9ec'),
(425, 152, 1, 'RXua4A', '10.141.108.249', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'Venezuela', 'VE', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-21 09:18:36', 'historical_8524e9d243136c9c3584717ac9b52e7c'),
(426, 152, 1, 'RXua4A', '10.87.8.2', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'Germany', 'DE', NULL, NULL, NULL, NULL, 'desktop', 'Chrome', 'Windows', '2025-07-21 10:33:31', 'historical_8bf3a4237fb048630cd4d19acdf8c4dd'),
(427, 132, 12, 'GyIf6O', '10.106.101.232', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'tablet', 'Edge', 'Android', '2025-07-21 09:46:40', 'historical_4ddf2252e8b3aa5d80ef51c7fe1528c7'),
(428, 132, 12, 'GyIf6O', '10.75.201.182', 'Mozilla/5.0 (Compatible; HistoricalClick/Chrome)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'mobile', 'Chrome', 'Windows', '2025-07-21 17:55:21', 'historical_7a012a665936cd0eea17b49c4e2bee85'),
(431, 136, 12, 'i4V4Y2', '10.251.70.85', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'mobile', 'Safari', 'Android', '2025-07-21 18:25:15', 'historical_3ad059782501e96a449d9479fcd0d827'),
(432, 139, 12, 'mmg0p8', '10.89.84.172', 'Mozilla/5.0 (Compatible; HistoricalClick/Edge)', NULL, 'France', 'FR', NULL, NULL, NULL, NULL, 'desktop', 'Edge', 'Windows', '2025-07-21 15:57:34', 'historical_5b9f41bd1875ddc1c023d8fd96cd71ad'),
(433, 141, 11, 'kiaNQu', '10.165.222.8', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'United States', 'US', 'Houston', NULL, NULL, NULL, 'desktop', 'Safari', 'Windows', '2025-07-21 12:33:30', 'historical_ba8da669476f66dbb8194b3da0354481'),
(434, 160, 13, 'rzNzbq', '10.169.180.209', 'Mozilla/5.0 (Compatible; HistoricalClick/Safari)', NULL, 'United Kingdom', 'GB', 'Edinburgh', NULL, NULL, NULL, 'mobile', 'Safari', 'Windows', '2025-06-22 10:56:54', 'historical_0ea8087ea428919b6634db7865e5a4e2');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `url_blacklist`
--

CREATE TABLE `url_blacklist` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `short_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','banned','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `role` enum('user','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `banned_reason` text COLLATE utf8mb4_unicode_ci,
  `banned_at` timestamp NULL DEFAULT NULL,
  `banned_by` int DEFAULT NULL,
  `failed_login_attempts` int DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_count` int DEFAULT '0',
  `api_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_extension_sync` datetime DEFAULT NULL,
  `extension_sync_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `status`, `role`, `created_at`, `updated_at`, `last_login`, `email_verified`, `verification_token`, `password_reset_token`, `password_reset_expires`, `banned_reason`, `banned_at`, `banned_by`, `failed_login_attempts`, `locked_until`, `is_active`, `last_ip`, `login_count`, `api_key`, `last_extension_sync`, `extension_sync_count`) VALUES
(1, 'admin', 'admin@localhost', '$2y$12$JWbc9yPcKUQWGWs3YSC1qOl9CdXfrX4wsX3kzJ4vI1o.rw36Ac2Mi', 'Administrador', 'active', 'admin', '2025-07-09 20:22:44', '2025-07-25 17:50:00', '2025-07-25 17:50:00', 1, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, 'f7cc7fa1f5f6f3322bfca0f56c6f6e9a', NULL, 0),
(11, 'capitan', 'b@a.net', '$2y$12$pdzYpwYF/L8z7dSXiPvRQu6akfe4cmCfyVqNacFZGJiDl14496Hj6', '', 'active', 'user', '2025-07-12 12:45:21', '2025-07-23 18:49:08', '2025-07-23 18:49:08', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, '80c91be387fd11a71268c5b9a4d86d89', NULL, 0),
(12, 'Chino', 'chino@china.org', '$2y$12$oOLzbxiw22kZJ8hkVJxS9.lrEA1mLcQq7DyvN3dqZ0/Qa5FedgHUG', '', 'active', 'user', '2025-07-12 13:22:13', '2025-07-25 17:35:17', '2025-07-25 17:35:17', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, 'd0ff6dc7682df98bbb25257af6d9ef01', NULL, 0),
(13, 'Antonio', 'budino@antonio.org', '$2y$12$JhsrPDK3gAIzt0kDV9523esjTotHnay7sPBmhQRnRUybR.oBrjcg6', '', 'active', 'admin', '2025-07-12 16:55:32', '2025-07-25 10:59:21', '2025-07-25 10:59:21', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, 'ec1ea2eceb6daeeb0d659117b54d4e0b', NULL, 0),
(16, 'Pollito', 'Pollo@avecrem.com', '$2y$12$7br9tFiyw0hpE3ncgBMpyu0GelrM6hi4MoIklfDo00fE3jUPhi3La', 'Pollo perez', 'active', 'user', '2025-07-15 18:00:14', '2025-07-24 13:44:40', '2025-07-24 13:44:40', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, 'b905780256ca0b6eafce57daf5f7756f', NULL, 0),
(17, 'anaaaa', 'mondalironda@hotmail.com', '$2y$12$WujXjVZQj4zuM5rGFmZnDOgcYWYib.q4nTS7UgCRHqks9HlLscH8W', 'anaaaa', 'active', 'user', '2025-07-17 20:27:35', '2025-07-23 01:26:11', '2025-07-23 01:26:11', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, '5394c265b597fae954901affafdd4b3e', NULL, 0),
(18, 'Jaime', 'siete@protonmail.com', '$2y$12$KDMywS1hfCc8iHFDm4ChNOe9Aed.UfWsqtGsbSuOd6LGMF7g26OOi', 'Jaime Gomez', 'active', 'user', '2025-07-22 23:49:32', '2025-07-24 00:53:45', '2025-07-23 00:46:26', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action_type` enum('login','logout','register','url_create','url_click','password_change','profile_update','ban','unban') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_audit_log`
--

CREATE TABLE `user_audit_log` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `user_audit_log`
--

INSERT INTO `user_audit_log` (`id`, `user_id`, `action`, `old_value`, `new_value`, `changed_by`, `changed_at`, `ip_address`, `user_agent`) VALUES
(1, 13, 'role_change', 'admin', 'user', 1, '2025-07-12 17:36:40', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(2, 13, 'role_change', 'user', 'admin', 1, '2025-07-12 17:36:52', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(3, 13, 'role_change', 'admin', 'admin', 1, '2025-07-12 17:49:29', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(4, 13, 'status_change', 'active', 'banned', 13, '2025-07-12 17:52:51', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(5, 13, 'status_change', 'banned', 'active', 13, '2025-07-12 17:53:01', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(6, 13, 'status_change', 'active', 'banned', 13, '2025-07-12 17:53:29', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(7, 13, 'status_change', 'banned', 'active', 13, '2025-07-12 17:53:33', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(8, 12, 'status_change', 'active', 'banned', 1, '2025-07-13 14:29:31', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(9, 12, 'status_change', 'banned', 'active', 1, '2025-07-13 14:29:39', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(10, 1, 'password_changed', NULL, NULL, 13, '2025-07-13 20:28:25', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(11, 1, 'status_change', 'active', 'banned', 13, '2025-07-13 20:28:39', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(12, 1, 'status_change', 'banned', 'active', 13, '2025-07-13 20:28:42', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(20, 17, 'password_changed', NULL, NULL, 1, '2025-07-17 20:43:40', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(21, 17, 'role_change', 'user', 'admin', 1, '2025-07-17 20:46:30', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(22, 17, 'role_change', 'admin', 'admin', 1, '2025-07-17 20:49:29', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(23, 17, 'role_change', 'admin', 'admin', 1, '2025-07-17 20:51:56', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(24, 17, 'role_change', 'admin', 'user', 1, '2025-07-17 21:04:23', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(25, 1, 'password_changed', NULL, NULL, 13, '2025-07-20 21:26:35', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(26, 18, 'user_created', NULL, 'Jaime', 1, '2025-07-22 23:49:32', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(27, 18, 'role_change', 'user', 'admin', 1, '2025-07-24 00:53:40', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(28, 18, 'role_change', 'admin', 'user', 1, '2025-07-24 00:53:45', '62.99.100.233', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `user_stats`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `user_stats` (
`total_users` bigint
,`active_users` bigint
,`banned_users` bigint
,`pending_users` bigint
,`admin_users` bigint
,`registrations_today` bigint
,`logins_today` bigint
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_urls`
--

CREATE TABLE `user_urls` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `url_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `user_urls`
--

INSERT INTO `user_urls` (`id`, `user_id`, `url_id`, `title`, `category`, `favicon`, `notes`, `created_at`, `updated_at`) VALUES
(2, 1, 83, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=example.com', NULL, '2025-07-16 11:57:59', '2025-07-17 14:20:14'),
(3, 1, 79, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=www.elespanol.com', NULL, '2025-07-14 21:09:00', '2025-07-17 14:20:14'),
(4, 1, 68, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=x.com', NULL, '2025-07-13 20:45:32', '2025-07-17 14:20:14'),
(5, 1, 67, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=hola.es', NULL, '2025-07-13 20:10:00', '2025-07-17 14:20:14'),
(6, 1, 66, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 16:46:54', '2025-07-17 14:20:14'),
(7, 1, 65, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 16:43:57', '2025-07-17 14:20:14'),
(8, 1, 64, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=0ln.eu', NULL, '2025-07-13 15:31:29', '2025-07-17 14:20:14'),
(9, 1, 61, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=mega.nz', NULL, '2025-07-13 14:13:12', '2025-07-17 14:20:14'),
(10, 1, 60, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.org', NULL, '2025-07-13 12:47:55', '2025-07-17 14:20:14'),
(11, 1, 59, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 12:42:57', '2025-07-17 14:20:14'),
(12, 1, 58, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 12:32:02', '2025-07-17 14:20:14'),
(13, 1, 57, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 11:39:43', '2025-07-17 14:20:14'),
(14, 1, 56, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 11:39:16', '2025-07-17 14:20:14'),
(15, 1, 55, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 09:57:09', '2025-07-17 14:20:14'),
(16, 1, 54, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 09:56:47', '2025-07-17 14:20:14'),
(17, 1, 53, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 09:36:36', '2025-07-17 14:20:14'),
(18, 1, 52, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 09:11:06', '2025-07-17 14:20:14'),
(19, 1, 51, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=amzn.to', NULL, '2025-07-13 09:10:01', '2025-07-17 14:20:14'),
(20, 1, 50, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=amzn.to', NULL, '2025-07-13 08:59:22', '2025-07-17 14:20:14'),
(21, 1, 49, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=amzn.to', NULL, '2025-07-13 08:58:45', '2025-07-17 14:20:14'),
(22, 1, 48, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=amzn.to', NULL, '2025-07-13 08:50:27', '2025-07-17 14:20:14'),
(23, 1, 47, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.org', NULL, '2025-07-13 08:34:25', '2025-07-17 14:20:14'),
(24, 1, 46, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-13 08:21:39', '2025-07-17 14:20:14'),
(25, 1, 45, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=hola.es', NULL, '2025-07-13 07:03:52', '2025-07-17 14:20:14'),
(26, 1, 44, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=hola.es', NULL, '2025-07-13 03:39:37', '2025-07-17 14:20:14'),
(27, 1, 43, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=www.google.fr', NULL, '2025-07-12 22:05:46', '2025-07-17 14:20:14'),
(28, 1, 37, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=www.elespanol.com', NULL, '2025-07-12 12:34:22', '2025-07-17 14:20:14'),
(29, 1, 36, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=amazon.com', NULL, '2025-07-11 20:56:14', '2025-07-17 14:20:14'),
(30, 1, 35, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-11 19:28:02', '2025-07-17 14:20:14'),
(31, 1, 34, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.org', NULL, '2025-07-11 19:03:20', '2025-07-17 14:20:14'),
(32, 1, 30, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=adunti.net', NULL, '2025-07-11 15:35:36', '2025-07-17 14:20:14'),
(33, 1, 29, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=www.elespanol.com', NULL, '2025-07-11 15:18:14', '2025-07-17 14:20:14'),
(36, 12, 87, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=proton.me', NULL, '2025-07-16 13:46:30', '2025-07-17 16:32:17'),
(37, 12, 86, 'Sincronizado', NULL, 'https://www.google.com/s2/favicons?domain=proton.me', NULL, '2025-07-16 12:57:00', '2025-07-17 16:32:17');

-- --------------------------------------------------------

--
-- Estructura para la vista `user_stats`
--
DROP TABLE IF EXISTS `user_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_stats`  AS SELECT count(0) AS `total_users`, count((case when (`users`.`status` = 'active') then 1 end)) AS `active_users`, count((case when (`users`.`status` = 'banned') then 1 end)) AS `banned_users`, count((case when (`users`.`status` = 'pending') then 1 end)) AS `pending_users`, count((case when (`users`.`role` = 'admin') then 1 end)) AS `admin_users`, count((case when (cast(`users`.`created_at` as date) = curdate()) then 1 end)) AS `registrations_today`, count((case when (cast(`users`.`last_login` as date) = curdate()) then 1 end)) AS `logins_today` FROM `users` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indices de la tabla `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_hash` (`key_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indices de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indices de la tabla `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_short_code` (`short_code`),
  ADD KEY `idx_url_id` (`url_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `click_stats`
--
ALTER TABLE `click_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_url_id` (`url_id`),
  ADD KEY `idx_country_code` (`country_code`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_click_stats_user_id` (`user_id`),
  ADD KEY `idx_click_stats_url_date` (`url_id`,`clicked_at`);

--
-- Indices de la tabla `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Indices de la tabla `custom_domains`
--
ALTER TABLE `custom_domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `domain` (`domain`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indices de la tabla `daily_stats`
--
ALTER TABLE `daily_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_url_date` (`url_id`,`date`),
  ADD KEY `idx_user_date` (`user_id`,`date`);

--
-- Indices de la tabla `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indices de la tabla `rate_limit`
--
ALTER TABLE `rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `identifier_action_created` (`identifier`,`action`,`created_at`),
  ADD KEY `idx_rate_limit_cleanup` (`created_at`);

--
-- Indices de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `severity` (`severity`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `idx_security_logs_cleanup` (`created_at`);

--
-- Indices de la tabla `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `last_activity` (`last_activity`);

--
-- Indices de la tabla `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indices de la tabla `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_sync` (`user_id`,`created_at`);

--
-- Indices de la tabla `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indices de la tabla `urls`
--
ALTER TABLE `urls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `short_code` (`short_code`),
  ADD KEY `idx_short_code` (`short_code`),
  ADD KEY `idx_urls_user_status` (`user_id`,`active`),
  ADD KEY `idx_domain_id` (`domain_id`),
  ADD KEY `idx_urls_short_code_active` (`short_code`,`active`),
  ADD KEY `idx_user_deleted` (`user_id`,`deleted_at`),
  ADD KEY `idx_last_accessed` (`last_accessed`);

--
-- Indices de la tabla `urls_deleted_history`
--
ALTER TABLE `urls_deleted_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_history` (`user_id`,`deleted_at`);

--
-- Indices de la tabla `url_analytics`
--
ALTER TABLE `url_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_url_id` (`url_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_short_code` (`short_code`),
  ADD KEY `idx_clicked_at` (`clicked_at`),
  ADD KEY `idx_country` (`country_code`),
  ADD KEY `idx_device` (`device_type`),
  ADD KEY `idx_analytics_date_range` (`clicked_at`,`user_id`),
  ADD KEY `idx_analytics_url_date` (`url_id`,`clicked_at`),
  ADD KEY `idx_country_code` (`country_code`),
  ADD KEY `idx_geo` (`latitude`,`longitude`),
  ADD KEY `idx_url_geo` (`url_id`,`latitude`,`longitude`);

--
-- Indices de la tabla `url_blacklist`
--
ALTER TABLE `url_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_code` (`user_id`,`short_code`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_verification_token` (`verification_token`),
  ADD KEY `idx_password_reset_token` (`password_reset_token`),
  ADD KEY `idx_users_status_role` (`status`,`role`);

--
-- Indices de la tabla `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_user_activity_user_date` (`user_id`,`created_at`);

--
-- Indices de la tabla `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_changed_by` (`changed_by`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indices de la tabla `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_user_sessions_user_active` (`user_id`,`is_active`);

--
-- Indices de la tabla `user_urls`
--
ALTER TABLE `user_urls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_url` (`user_id`,`url_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `url_id` (`url_id`),
  ADD KEY `category` (`category`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_user_category` (`user_id`,`category`),
  ADD KEY `idx_user_url` (`user_id`,`url_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `click_stats`
--
ALTER TABLE `click_stats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1474;

--
-- AUTO_INCREMENT de la tabla `config`
--
ALTER TABLE `config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `custom_domains`
--
ALTER TABLE `custom_domains`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `daily_stats`
--
ALTER TABLE `daily_stats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `rate_limit`
--
ALTER TABLE `rate_limit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT de la tabla `urls`
--
ALTER TABLE `urls`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT de la tabla `urls_deleted_history`
--
ALTER TABLE `urls_deleted_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `url_analytics`
--
ALTER TABLE `url_analytics`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=435;

--
-- AUTO_INCREMENT de la tabla `url_blacklist`
--
ALTER TABLE `url_blacklist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_audit_log`
--
ALTER TABLE `user_audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_urls`
--
ALTER TABLE `user_urls`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `click_stats`
--
ALTER TABLE `click_stats`
  ADD CONSTRAINT `click_stats_ibfk_1` FOREIGN KEY (`url_id`) REFERENCES `urls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_click_stats_url_id` FOREIGN KEY (`url_id`) REFERENCES `urls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_click_stats_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `custom_domains`
--
ALTER TABLE `custom_domains`
  ADD CONSTRAINT `custom_domains_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `daily_stats`
--
ALTER TABLE `daily_stats`
  ADD CONSTRAINT `fk_daily_stats_url` FOREIGN KEY (`url_id`) REFERENCES `urls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_daily_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD CONSTRAINT `sync_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `urls`
--
ALTER TABLE `urls`
  ADD CONSTRAINT `fk_urls_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_urls_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `url_analytics`
--
ALTER TABLE `url_analytics`
  ADD CONSTRAINT `fk_analytics_url` FOREIGN KEY (`url_id`) REFERENCES `urls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_analytics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `url_blacklist`
--
ALTER TABLE `url_blacklist`
  ADD CONSTRAINT `url_blacklist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `fk_user_activity_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD CONSTRAINT `user_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_audit_log_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `cleanup_expired_sessions` ON SCHEDULE EVERY 1 HOUR STARTS '2025-07-09 20:20:17' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM user_sessions WHERE expires_at < NOW() OR is_active = 0$$

CREATE DEFINER=`root`@`localhost` EVENT `cleanup_old_activity` ON SCHEDULE EVERY 1 DAY STARTS '2025-07-09 20:20:17' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM user_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)$$

CREATE DEFINER=`root`@`localhost` EVENT `daily_cleanup` ON SCHEDULE EVERY 1 DAY STARTS '2025-07-14 03:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL cleanup_old_data()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
