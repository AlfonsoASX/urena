<?php
/**
 * config/global.php
 * Funciones globales, inicio de sesión y constantes de sistema.
 * Este archivo debe incluirse en los módulos o en el header.
 */

// 1. INICIAR SESIÓN (Si no está iniciada)
// Esto permite usar $_SESSION['usuario_id'] o $_SESSION['mensaje'] en cualquier parte.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. CONSTANTES DE RUTA FÍSICA
// Define la carpeta raíz del disco duro (ej: C:/xampp/htdocs/funeraria/)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}

/* * NOTA: APP_NAME y BASE_URL ya fueron definidas en db.php 
 * según tu solicitud anterior, por lo que no las repetimos aquí
 * para evitar errores de "Constant already defined".
 */


// --------------------------------------------------------------------------
// 3. FUNCIONES DE AYUDA (HELPERS)
// --------------------------------------------------------------------------

/**
 * Formatear montos a moneda (MXN)
 * Uso: echo formato_moneda(1500.50); // Salida: $1,500.50
 */
function formato_moneda($cantidad) {
    if ($cantidad === null || $cantidad === '') return '$0.00';
    return '$' . number_format((float)$cantidad, 2, '.', ',');
}

/**
 * Formatear fecha MySQL (YYYY-MM-DD) a formato legible en Español
 * Uso: echo fecha_es('2025-11-02'); // Salida: 02 de Noviembre de 2025
 */
function fecha_es($fecha_sql) {
    if (!$fecha_sql || $fecha_sql == '0000-00-00') return 'Sin fecha';
    
    $timestamp = strtotime($fecha_sql);
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    
    $dia = date('d', $timestamp);
    $mes = $meses[date('n', $timestamp) - 1];
    $anio = date('Y', $timestamp);
    
    return "$dia de $mes de $anio";
}

/**
 * Limpieza básica de inputs para evitar XSS (Cross Site Scripting)
 * Uso: $nombre = limpiar_str($_POST['nombre']);
 */
function limpiar_str($str) {
    $str = trim($str);
    $str = stripslashes($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    return $str;
}

/**
 * Generar Badge (Etiqueta) HTML de Cork según el estatus
 * Uso: echo badge_estatus('abierto'); 
 */
function badge_estatus($estatus) {
    $estatus = strtolower($estatus);
    $clase = 'secondary'; // Default
    
    switch ($estatus) {
        // Estatus positivos
        case 'abierto':
        case 'activo':
        case 'disponible':
        case 'pagado':
        case 'entregado':
        case 'usable':
            $clase = 'success';
            break;
            
        // Estatus de alerta/proceso
        case 'pendiente':
        case 'registrado':
        case 'preparacion':
        case 'rentado':
            $clase = 'warning';
            break;
            
        // Estatus negativos/informativos
        case 'cerrado':
        case 'cancelado':
        case 'eliminado':
        case 'baja':
        case 'mantenimiento':
            $clase = 'danger';
            break;
            
        // Informativos neutros
        case 'inhumacion':
        case 'cremacion':
            $clase = 'info';
            break;
    }
    
    // Retorna el HTML del badge de Cork
    return "<span class='badge badge-{$clase}'> " . ucfirst($estatus) . " </span>";
}

/**
 * Redirección segura con Javascript (útil si los headers de PHP ya se enviaron)
 */
function redireccionar($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}
?>