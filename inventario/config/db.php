<?php
/**
 * config/db.php
 * Conexión a Base de Datos y Configuración General
 */

// 1. Definición de Credenciales (Extraídas de tu información)
$db_host = 'ganas001.mysql.guardedhost.com';
$db_user = 'ganas001_urena';
$db_pass = 't*H3q2pb6Kk)';
$db_name = 'ganas001_urena';
$charset = 'utf8mb4';

// 2. Configuración de la Aplicación
$config_app = [
    'app_name' => 'Grupo Ureña Funerarias',
    'base_url' => '/',  // Ojo: Si usas XAMPP/WAMP en subcarpeta, ajusta esto (ej: '/mi_proyecto/')
    'debug'    => true
];

// Definimos constantes globales para usarlas en todo el sitio
if (!defined('APP_NAME')) define('APP_NAME', $config_app['app_name']);
if (!defined('BASE_URL')) define('BASE_URL', $config_app['base_url']);

// 3. Creación de la conexión (Estilo Procedural)
$conexion = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// 4. Verificación de errores
if (!$conexion) {
    if ($config_app['debug']) {
        die("Error crítico de conexión: " . mysqli_connect_error());
    } else {
        // En producción, no mostrar detalles técnicos al usuario
        die("Error de conexión con el servidor. Por favor intente más tarde.");
    }
}

// 5. Configurar set de caracteres
if (!mysqli_set_charset($conexion, $charset)) {
    if ($config_app['debug']) {
        printf("Error cargando el conjunto de caracteres %s: %s\n", $charset, mysqli_error($conexion));
        exit();
    }
}

// 6. Configuración de zona horaria (Opcional, pero recomendado para México)
date_default_timezone_set('America/Mexico_City');
mysqli_query($conexion, "SET time_zone = '-06:00'");

?>