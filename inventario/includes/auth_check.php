<?php
// includes/auth_check.php

// Asegurar que la sesión esté iniciada (por si global.php no se cargó antes)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar variable de sesión (asumiendo que al loguearse creas $_SESSION['usuario_id'])
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
    // Si no está logueado, redirigir al Login
    // Usamos ruta relativa segura asumiendo que BASE_URL está definido, si no, usa relativa
    $login_url = defined('BASE_URL') ? BASE_URL . 'modules/auth/login.php' : '../../modules/auth/login.php';
    
    header("Location: " . $login_url);
    exit();
}
?>