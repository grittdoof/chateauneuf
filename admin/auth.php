<?php
/**
 * auth.php — À inclure en tête de chaque page admin protégée
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Expiration de session
if (isset($_SESSION['admin_login_time']) &&
    (time() - $_SESSION['admin_login_time']) > $config['session_lifetime']) {
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

$_SESSION['admin_login_time'] = time(); // Renouvelle le timer
