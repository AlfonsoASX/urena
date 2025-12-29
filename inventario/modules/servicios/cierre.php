<?php
// 1. CONFIGURACIÓN
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";
$vista = "selector"; // 'selector' o 'formulario'

// --------------------------------------------------------------------------
// PROCESO A: GUARDAR CIERRE (POST)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cerrar') {
    $id_servicio = intval($_POST['id_servicio']);
    $accion_ataud = $_POST['accion_ataud'];
    $condicion_ataud = $_POST['condicion_ataud'] ?? 'usable';
    $notas_cierre = limpiar_str($_POST['notas_cierre']);
    $codigo_caja = $_POST['codigo_caja_hidden'] ?? ''; // Recuperamos del hidden
    $id_contrato = intval($_POST['id_contrato_hidden'] ?? 0);
    $deuda_pendiente = floatval($_POST['deuda_hidden'] ?? 0);
    
    // Validar deuda (doble check backend)
    $forzar_cierre = isset($_POST['check_deuda']) ? true : false;
    
    if ($deuda_pendiente > 0.1 && !$forzar_cierre) {
        $mensaje = "Error: Hay deuda pendiente y no se autorizó la excepción.";
        $tipo_msg = "danger";
        $vista = "formulario"; // Mantener en formulario para corregir
    } else {
        mysqli_begin_transaction($conexion);
        try {
            // 1. Actualizar Ataúd (Si aplica)
            if (!empty($codigo_caja)) {
                if ($accion_ataud == 'consumo') {
                    mysqli_query($conexion, "UPDATE cajas SET estatus_logico = 'baja', disponible = 0 WHERE codigo = '$codigo_caja'");
                    mysqli_query($conexion, "UPDATE renta_cajas SET fecha_devolucion = NOW(), estado_devolucion = 'baja' WHERE codigo = '$codigo_caja' AND id_servicio = $id_servicio");
                } else {
                    $nuevo_estado = ($condicion_ataud == 'dañado') ? 'mantenimiento' : 'disponible';
                    $disp = ($condicion_ataud == 'dañado') ? 0 : 1;
                    mysqli_query($conexion, "UPDATE cajas SET estatus_logico = '$nuevo_estado', disponible = $disp, veces_usado = veces_usado + 1 WHERE codigo = '$codigo_caja'");
                    mysqli_query($conexion, "UPDATE renta_cajas SET fecha_devolucion = NOW(), estado_devolucion = '$condicion_ataud' WHERE codigo = '$codigo_caja' AND id_servicio = $id_servicio");
                }
            }
            
            // 2. Cerrar Servicio
            $nota_final = "\n[".date('d/m/Y H:i')." - ".($_SESSION['usuario_nombre']??'Admin')."]: CIERRE FINAL. " . $notas_cierre;
            mysqli_query($conexion, "UPDATE servicios SET estatus = 'cerrado', cerrado = 1, notas = CONCAT(IFNULL(notas,''), '$nota_final') WHERE id_servicio = $id_servicio");
            
            // 3. Actualizar Contrato
            if ($id_contrato > 0 && $deuda_pendiente <= 0.1) {
                mysqli_query($conexion, "UPDATE futuro_contratos SET estatus = 'pagado' WHERE id_contrato = $id_contrato");
            }

            mysqli_commit($conexion);
            $mensaje = "¡Servicio cerrado correctamente!";
            $tipo_msg = "success";
            $vista = "selector"; // Volver al select limpio

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error: " . $e->getMessage();
            $tipo_msg = "danger";
            $vista = "formulario";
        }
    }
}

// --------------------------------------------------------------------------
// PROCESO B: PREPARAR VISTA (SELECTOR O FORMULARIO DETALLADO)
// --------------------------------------------------------------------------

// Si viene un ID por GET o acabamos de fallar un POST, mostramos el formulario detallado
if ( (isset($_GET['id']) && !empty($_GET['id'])) || ($vista == 'formulario' && isset($id_servicio)) ) {
    
    $vista = "formulario";
    $id_servicio = isset($_GET['id']) ? intval($_GET['id']) : $id_servicio;

    // Obtener datos
    $sql_s = "SELECT s.*, f.nom_fallecido, sc.codigo as codigo_caja, c.modelo as modelo_caja 
              FROM servicios s
              LEFT JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
              LEFT JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
              LEFT JOIN servicio_caja sc ON s.id_servicio = sc.id_servicio
              LEFT JOIN cajas c ON sc.codigo = c.codigo
              WHERE s.id_servicio = $id_servicio";
    $res = mysqli_query($conexion, $sql_s);
    $data = mysqli_fetch_assoc($res);

    if (!$data) { 
        $mensaje = "El servicio solicitado no existe."; $tipo_msg = "danger"; $vista = "selector"; 
    } else {
        // Calcular deuda (Lógica simplificada)
        $deuda_pendiente = 0; $id_contrato = 0; $total_pagar = 0; $total_pagado = 0;
        if (!empty($data['contratante_nombre'])) {
            $nom = mysqli_real_escape_string($conexion, substr($data['contratante_nombre'], 0, 15));
            $sql_f = "SELECT fc.id_contrato, fc.costo_final, (SELECT SUM(cant_abono) FROM futuro_abonos WHERE id_contrato = fc.id_contrato) as pagado
                      FROM futuro_contratos fc 
                      JOIN titular_contrato tc ON fc.id_contrato = tc.id_contrato
                      JOIN titulares t ON tc.id_titular = t.id_titular
                      WHERE t.nombre LIKE '%$nom%' AND fc.estatus IN ('servicio_activo', 'pagado', 'registrado') LIMIT 1";
            $res_f = mysqli_query($conexion, $sql_f);
            if ($row_f = mysqli_fetch_assoc($res_f)) {
                $id_contrato = $row_f['id_contrato'];
                $total_pagar = $row_f['costo_final'];
                $total_pagado = $row_f['pagado'] ?? 0;
                $deuda_pendiente = $total_pagar - $total_pagado;
            }
        }
    }
}

// Si estamos en modo selector, cargar lista de activos
if ($vista == 'selector') {
    $sql_list = "SELECT s.id_servicio, s.folio, f.nom_fallecido, s.tipo_servicio 
                 FROM servicios s
                 LEFT JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
                 LEFT JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
                 WHERE s.estatus != 'cerrado' AND s.eliminado = 0
                 ORDER BY s.created_at ASC"; // Los más viejos primero
    $res_list = mysqli_query($conexion, $sql_list);
}

// --------------------------------------------------------------------------
// UI
// --------------------------------------------------------------------------
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<div id="content" class="main-content">
    <div class="layout-px-spacing">
        <div class="middle-content container-xxl p-0">
            
            <div class="secondary-nav">
                <div class="breadcrumbs-container" data-page-heading="Sales">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title"><h3>Módulo de Cierre</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing justify-content-center">
                
                <?php if($mensaje): ?>
                <div class="col-md-8 layout-spacing">
                    <div class="alert alert-<?php echo $tipo_msg; ?> mb-4"><?php echo $mensaje; ?></div>
                </div>
                <?php endif; ?>

                <?php if ($vista == 'selector'): ?>
                <div class="col-xl-6 col-lg-8 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4 text-center p-5">
                        <div class="icon-svg mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e7515a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check-circle"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        </div>
                        <h3>Seleccione el Servicio a Cerrar</h3>
                        <p class="text-muted mb-4">Solo se muestran los servicios actualmente activos o en curso.</p>
                        
                        <form action="" method="GET">
                            <div class="form-group mb-4">
                                <select name="id" class="form-control form-control-lg" required>
                                    <option value="">-- Seleccionar de la lista --</option>
                                    <?php 
                                    if(mysqli_num_rows($res_list) > 0) {
                                        while($row = mysqli_fetch_assoc($res_list)): 
                                    ?>
                                        <option value="<?php echo $row['id_servicio']; ?>">
                                            #<?php echo $row['folio']; ?> - <?php echo $row['nom_fallecido']; ?> (<?php echo $row['tipo_servicio']; ?>)
                                        </option>
                                    <?php 
                                        endwhile; 
                                    } else {
                                        echo "<option disabled>No hay servicios activos</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger btn-lg w-100">
                                Gestionar Cierre
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>


                <?php if ($vista == 'formulario'): ?>
                <div class="col-xl-8 col-lg-10 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Finalizando Servicio <span class="text-danger">#<?php echo $data['folio']; ?></span></h4>
                            <a href="cierre.php" class="btn btn-outline-dark btn-sm">Cancelar / Volver</a>
                        </div>

                        <?php if ($deuda_pendiente > 0.1): ?>
                        <div class="alert alert-danger mb-4" role="alert">
                            <h4 class="alert-heading">⚠️ Deuda Pendiente: <?php echo formato_moneda($deuda_pendiente); ?></h4>
                            <p class="mb-0">El contrato vinculado no ha sido liquidado. Para cerrar este servicio, debe autorizar la excepción abajo.</p>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success mb-4" role="alert">
                            <strong>Cuenta al corriente.</strong> No hay adeudos pendientes.
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="cerrar">
                            <input type="hidden" name="id_servicio" value="<?php echo $id_servicio; ?>">
                            <input type="hidden" name="codigo_caja_hidden" value="<?php echo $data['codigo_caja']; ?>">
                            <input type="hidden" name="id_contrato_hidden" value="<?php echo $id_contrato; ?>">
                            <input type="hidden" name="deuda_hidden" value="<?php echo $deuda_pendiente; ?>">

                            <?php if (!empty($data['codigo_caja'])): ?>
                            <div class="card bg-light mb-4 border-0">
                                <div class="card-body">
                                    <h6 class="card-title text-dark">Destino del Ataúd: <b><?php echo $data['modelo_caja']; ?></b></h6>
                                    <div class="n-chk mt-3">
                                        <label class="new-control new-radio radio-danger">
                                            <input type="radio" class="new-control-input" name="accion_ataud" value="consumo" checked onclick="$('#condicion_box').hide()">
                                            <span class="new-control-indicator"></span>
                                            <b>Consumo</b> (Se fue con el cliente / Inhumación)
                                        </label>
                                    </div>
                                    <div class="n-chk mt-2">
                                        <label class="new-control new-radio radio-success">
                                            <input type="radio" class="new-control-input" name="accion_ataud" value="retorno" onclick="$('#condicion_box').show()">
                                            <span class="new-control-indicator"></span>
                                            <b>Devolución</b> (Regresa al inventario / Renta)
                                        </label>
                                    </div>
                                    
                                    <div id="condicion_box" class="mt-3 ps-4" style="display:none;">
                                        <label>Estado del equipo al regresar:</label>
                                        <select name="condicion_ataud" class="form-control form-control-sm w-50">
                                            <option value="usable">Bueno / Usable</option>
                                            <option value="dañado">Dañado (Mantenimiento)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="accion_ataud" value="ninguno">
                            <?php endif; ?>

                            <div class="form-group mb-4">
                                <label>Observaciones del Cierre</label>
                                <textarea name="notas_cierre" class="form-control" rows="3" placeholder="Comentarios finales..."></textarea>
                            </div>

                            <?php if ($deuda_pendiente > 0.1): ?>
                            <div class="form-group mb-3">
                                <div class="n-chk">
                                    <label class="new-control new-checkbox checkbox-danger">
                                        <input type="checkbox" class="new-control-input" name="check_deuda" required>
                                        <span class="new-control-indicator"></span> Autorizo cerrar con deuda.
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-danger btn-lg w-100">CONFIRMAR CIERRE</button>
                        </form>

                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php include '../../includes/footer.php'; ?>
</div>