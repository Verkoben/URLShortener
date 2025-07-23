<?php
// email_config.php - Configuración de email

// IMPORTANTE: Cambia estos valores con tus credenciales reales
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // tls o ssl
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'tu_email@gmail.com'); // TU EMAIL DE GMAIL
define('SMTP_PASSWORD', 'PASSWPORD'); // CONTRASEÑA DE APLICACIÓN (no tu contraseña normal)
define('SMTP_FROM_EMAIL', 'tu_email@gmail.com');
define('SMTP_FROM_NAME', 'URL Shortener');
define('SMTP_DEBUG', 0); // Cambiar a 0 para no mostrar información sensible

// Para otros proveedores de email:
// Outlook/Hotmail: smtp.live.com, puerto 587
// Yahoo: smtp.mail.yahoo.com, puerto 587 o 465
// Zoho: smtp.zoho.com, puerto 587
// SendGrid: smtp.sendgrid.net, puerto 587

// Debug (cambiar a 0 en producción)
//define('SMTP_DEBUG', 2); // 0 = off, 1 = client messages, 2 = client and server messages
?>