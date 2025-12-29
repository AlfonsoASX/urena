<?php
/**
 * config/funciones.php
 * Funciones de Lógica de Negocio y Consultas a BD.
 * Requiere que $conexion (de db.php) sea pasado como argumento.
 */

/* ==========================================================================
   SECCIÓN 1: FINANZAS Y CONTRATOS
   ========================================================================== */

/**
 * Calcula cuánto debe un cliente de un contrato específico.
 * Retorna el saldo pendiente (float).
 */
function obtener_saldo_pendiente($conexion, $id_contrato) {
    // 1. Obtener costo final del contrato
    $sql_costo = "SELECT costo_final FROM futuro_contratos WHERE id_contrato = $id_contrato";
    $res_costo = mysqli_query($conexion, $sql_costo);
    $row_costo = mysqli_fetch_assoc($res_costo);
    $total_a_pagar = $row_costo['costo_final'] ?? 0;

    // 2. Sumar todos los abonos registrados
    $sql_pagos = "SELECT SUM(cant_abono) as total_pagado FROM futuro_abonos WHERE id_contrato = $id_contrato";
    $res_pagos = mysqli_query($conexion, $sql_pagos);
    $row_pagos = mysqli_fetch_assoc($res_pagos);
    $total_abonado = $row_pagos['total_pagado'] ?? 0;

    return $total_a_pagar - $total_abonado;
}

/**
 * Genera un Folio único para un nuevo servicio activo.
 * Formato: SERV-{AÑO}-{ID_INCREMENTAL} (Ej: SERV-2025-0045)
 */
function generar_folio_servicio($conexion) {
    $anio = date('Y');
    
    // Buscar el último ID de servicio insertado
    $sql = "SELECT MAX(id_servicio) as max_id FROM servicios";
    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);
    $next_id = ($row['max_id'] ?? 0) + 1;
    
    // Rellenar con ceros a la izquierda (4 dígitos)
    $correlativo = str_pad($next_id, 4, "0", STR_PAD_LEFT);
    
    return "SERV-$anio-$correlativo";
}

/* ==========================================================================
   SECCIÓN 2: INVENTARIOS (Cajas y Artículos)
   ========================================================================== */

/**
 * Verifica si un ataúd está disponible para ser asignado.
 * Retorna true o false.
 */
function verificar_disponibilidad_caja($conexion, $codigo_caja) {
    $codigo = mysqli_real_escape_string($conexion, $codigo_caja);
    
    // Usamos la vista 'vw_cajas_estado' que ya tienes definida en tu SQL
    $sql = "SELECT estatus_logico, disponible FROM cajas WHERE codigo = '$codigo'";
    $res = mysqli_query($conexion, $sql);
    
    if ($row = mysqli_fetch_assoc($res)) {
        if ($row['estatus_logico'] == 'disponible' && $row['disponible'] == 1) {
            return true;
        }
    }
    return false;
}

/**
 * Genera las opciones <option> para un <select> HTML de Cajas Disponibles.
 * Útil para los formularios de "Asignar Servicio".
 */
function options_cajas_disponibles($conexion, $seleccionado = null) {
    $html = "";
    $sql = "SELECT codigo, modelo, color, costo FROM cajas 
            WHERE estatus_logico = 'disponible' AND disponible = 1 
            AND eliminado = 0 ORDER BY modelo ASC";
    $res = mysqli_query($conexion, $sql);
    
    while ($row = mysqli_fetch_assoc($res)) {
        $selected = ($row['codigo'] == $seleccionado) ? "selected" : "";
        $texto = $row['modelo'] . " - " . $row['color'] . " ($" . number_format($row['costo'], 2) . ")";
        $html .= "<option value='{$row['codigo']}' $selected>$texto</option>";
    }
    
    if (empty($html)) {
        $html = "<option value=''>-- No hay cajas disponibles --</option>";
    }
    
    return $html;
}

/**
 * Obtiene el stock actual de un artículo (velas, flores, etc).
 */
function obtener_stock_articulo($conexion, $id_articulo) {
    $sql = "SELECT existencias FROM articulos WHERE id = $id_articulo";
    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);
    return $row['existencias'] ?? 0;
}

/* ==========================================================================
   SECCIÓN 3: SISTEMA Y SEGURIDAD
   ========================================================================== */

/**
 * Registra un movimiento en la tabla `futuro_logs`.
 * Útil para auditoría (quién modificó qué).
 */
function registrar_log($conexion, $tabla, $campo, $valor_ant, $valor_nuevo, $id_registro) {
    // Escapar datos
    $tabla = mysqli_real_escape_string($conexion, $tabla);
    $campo = mysqli_real_escape_string($conexion, $campo);
    $ant   = mysqli_real_escape_string($conexion, $valor_ant);
    $nuevo = mysqli_real_escape_string($conexion, $valor_nuevo);
    $id_reg = (int)$id_registro;
    
    // Asumimos que el usuario logueado está en $_SESSION['id_usuario'] (ajustar según tu login)
    $id_usuario = $_SESSION['usuario_id'] ?? 0; // 0 si es sistema
    
    $sql = "INSERT INTO futuro_logs (nom_tabla, nom_campo, valor_anterior, valor_nuevo, id, fecha_registro) 
            VALUES ('$tabla', '$campo', '$ant', '$nuevo', $id_usuario, NOW())";
            
    mysqli_query($conexion, $sql);
}

/**
 * Obtiene el nombre completo de un usuario del sistema
 */
function obtener_nombre_usuario($conexion, $id_usuario) {
    if (!$id_usuario) return "Sistema";
    
    $sql = "SELECT nombre FROM usuarios WHERE id = $id_usuario";
    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);
    return $row['nombre'] ?? "Desconocido";
}

/**
 * Devuelve un array con los datos del titular de un contrato
 */
function obtener_datos_titular($conexion, $id_contrato) {
    $sql = "SELECT t.* FROM titulares t
            INNER JOIN titular_contrato tc ON t.id_titular = tc.id_titular
            WHERE tc.id_contrato = $id_contrato
            LIMIT 1"; // Asumimos un titular principal
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

?>