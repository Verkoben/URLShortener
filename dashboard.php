<?php
require_once 'config_auth.php';
require_once 'check_session.php';

if (!checkUserLogin()) {
    header('Location: login.php');
    exit;
}

// CONTENIDO ORIGINAL DEL ARCHIVO
