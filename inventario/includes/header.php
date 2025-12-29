<?php
// Validar que la configuración esté cargada
if (!defined('BASE_URL')) {
    // Intentar cargar la configuración si se accedió directamente (fallback)
    $config_path = __DIR__ . '/../config/global.php';
    if (file_exists($config_path)) {
        require_once __DIR__ . '/../config/db.php';
        require_once $config_path;
    } else {
        die('Error: No se ha cargado la configuración del sistema.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'Funeraria Ureña'; ?></title>
    
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>src/assets/img/favicon.ico"/>
    
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/light/loader.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/dark/loader.css" rel="stylesheet" type="text/css" />
    <script src="<?php echo BASE_URL; ?>layouts/vertical-light-menu/loader.js"></script>

    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>src/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/light/plugins.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/dark/plugins.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/light/structure.css" rel="stylesheet" type="text/css" class="structure" />
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/dark/structure.css" rel="stylesheet" type="text/css" class="structure" />

    <style>
        /* Ajuste para que el footer no flote mal en pantallas grandes */
        .layout-px-spacing { min-height: calc(100vh - 170px) !important; }
        
        /* Ajuste para inputs readonly que parezcan deshabilitados */
        input[readonly] { background-color: #e9ecef !important; cursor: not-allowed; }
    </style>

</head>
<body class="layout-boxed" data-bs-spy="scroll" data-bs-target="#navSection" data-bs-offset="100">
    
    <div id="load_screen"> 
        <div class="loader"> 
            <div class="loader-content">
                <div class="spinner-grow align-self-center"></div>
            </div>
        </div>
    </div>
    ```

