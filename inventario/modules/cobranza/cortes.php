<?php
// 1. CONFIGURACIÓN
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";

// Variables de Filtro
$fecha_inicio = isset($_GET['f_inicio']) ? $_GET['f_inicio'] : date('Y-m-d 00:00:00');
$fecha_fin    = isset($_GET['f_fin'])    ? $_GET['f_fin']    : date('Y-m-d 23:59:59');
$ver_preview  = isset($_GET['preview']);

// --------------------------------------------------------------------------
// LÓGICA 1: GUARDAR EL CORTE (CONFIRMADO)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'realizar_corte') {
    
    $f_ini_save = $_POST['fecha_inicio'];
    $f_fin_save = $_POST['fecha_fin'];
    $total_corte = floatval($_POST['total_calculado']);
    $usuario_id = $_SESSION['usuario_id'];
    
    // Validar que haya monto
    if ($total_corte <= 0) {
        $mensaje = "No hay montos pendientes para realizar un corte.";
        $tipo_msg = "warning";
    } else {
        mysqli_begin_transaction($conexion);
        try {
            // A. Crear Cabecera del Corte
            // 'tipo' lo dejamos como 'cobrador' por defecto o general
            $sql_header = "INSERT INTO cortes_pagos (tipo, id_personal, fecha_desde, fecha_hasta, total, estatus, creado_por, creado_en) 
                           VALUES ('cobrador', 0, '$f_ini_save', '$f_fin_save', $total_corte, 'cerrado', $usuario_id, NOW())";
            
            if (!mysqli_query($conexion, $sql_header)) throw new Exception("Error al crear cabecera de corte.");
            $id_corte = mysqli_insert_id($conexion);

            // B. Buscar los IDs de los abonos pendientes en ese rango para insertarlos en detalle
            // Nota: Es crucial volver a consultar para asegurar concurrencia, o usar los IDs pasados por hidden (menos seguro si son muchos)
            // Hacemos la consulta de inserción directa:
            $sql_detalles = "INSERT INTO cortes_pagos_det (id_corte, id_abono, monto)
                             SELECT $id_corte, a.id_abono, a.cant_abono
                             FROM futuro_abonos a
                             LEFT JOIN cortes_pagos_det cpd ON a.id_abono = cpd.id_abono
                             WHERE cpd.id_detalle IS NULL 
                             AND a.fecha_registro BETWEEN '$f_ini_save' AND '$f_fin_save'";
            
            if (!mysqli_query($conexion, $sql_detalles)) throw new Exception("Error al vincular los pagos al corte.");

            mysqli_commit($conexion);
            
            $mensaje = "Corte de Caja #$id_corte realizado exitosamente por ".formato_moneda($total_corte);
            $tipo_msg = "success";
            $ver_preview = false; // Resetear vista

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error: " . $e->getMessage();
            $tipo_msg = "danger";
        }
    }
}

// --------------------------------------------------------------------------
// LÓGICA 2: CALCULAR PRE-CORTE (PREVIEW)
// --------------------------------------------------------------------------
$pagos_pendientes = [];
$total_pendiente = 0;

if ($ver_preview) {
    // Buscamos abonos que NO estén en la tabla `cortes_pagos_det`
    $sql_pend = "SELECT a.id_abono, a.fecha_registro, a.cant_abono, a.id_contrato,
                        fc.costo_final, vwt.titular
                 FROM futuro_abonos a
                 LEFT JOIN cortes_pagos_det cpd ON a.id_abono = cpd.id_abono
                 JOIN futuro_contratos fc ON a.id_contrato = fc.id_contrato
                 LEFT JOIN vw_titular_contrato vwt ON fc.id_contrato = vwt.id_contrato
                 WHERE cpd.id_detalle IS NULL 
                 AND a.fecha_registro BETWEEN '$fecha_inicio' AND '$fecha_fin'
                 ORDER BY a.fecha_registro DESC";
                 
    $res_pend = mysqli_query($conexion, $sql_pend);
    while($row = mysqli_fetch_assoc($res_pend)) {
        $pagos_pendientes[] = $row;
        $total_pendiente += $row['cant_abono'];
    }
}

// --------------------------------------------------------------------------
// LÓGICA 3: HISTORIAL DE CORTES
// --------------------------------------------------------------------------
$sql_hist = "SELECT c.*, u.usuario as nombre_usuario
             FROM cortes_pagos c
             JOIN usuarios u ON c.creado_por = u.id
             ORDER BY c.creado_en DESC LIMIT 20";
$res_hist = mysqli_query($conexion, $sql_hist);

// 3. UI
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>src/plugins/src/table/datatable/datatables.css">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>src/plugins/css/light/table/datatable/dt-global_style.css">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>src/plugins/css/dark/table/datatable/dt-global_style.css">

<div id="content" class="main-content">
    <div class="layout-px-spacing">

        <div class="middle-content container-xxl p-0">
            
            <div class="secondary-nav">
                <div class="breadcrumbs-container" data-page-heading="Sales">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title"><h3>Cortes de Caja</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                
                <?php if($mensaje): ?>
                <div class="col-12">
                    <div class="alert alert-<?php echo $tipo_msg; ?> mb-4"><?php echo $mensaje; ?></div>
                </div>
                <?php endif; ?>

                <div class="col-xl-5 col-lg-12 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4">
                        <div class="widget-header">
                            <h4>Nuevo Corte</h4>
                            <p class="text-muted">Calcula el total de ingresos no procesados.</p>
                        </div>
                        
                        <form action="" method="GET" class="mb-4 border-bottom pb-4">
                            <input type="hidden" name="preview" value="1">
                            <div class="form-row">
                                <div class="col-md-6 mb-3">
                                    <label>Desde</label>
                                    <input type="datetime-local" name="f_inicio" class="form-control" value="<?php echo date('Y-m-d\T00:00', strtotime($fecha_inicio)); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Hasta</label>
                                    <input type="datetime-local" name="f_fin" class="form-control" value="<?php echo date('Y-m-d\T23:59', strtotime($fecha_fin)); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                Calcular Total Pendiente
                            </button>
                        </form>

                        <?php if($ver_preview): ?>
                            <div class="text-center mb-4">
                                <h6 class="text-muted">Total Recaudado (Pendiente de Corte)</h6>
                                <h2 class="text-success fw-bold"><?php echo formato_moneda($total_pendiente); ?></h2>
                                <p class="small"><?php echo count($pagos_pendientes); ?> movimientos encontrados</p>
                            </div>

                            <?php if($total_pendiente > 0): ?>
                            <div class="table-responsive mb-4" style="max-height: 250px; overflow-y: auto;">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Hora</th>
                                            <th>Cliente</th>
                                            <th class="text-end">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pagos_pendientes as $pago): ?>
                                        <tr>
                                            <td><?php echo date('d/m H:i', strtotime($pago['fecha_registro'])); ?></td>
                                            <td><small><?php echo substr($pago['titular'], 0, 15); ?>...</small></td>
                                            <td class="text-end text-dark fw-bold"><?php echo number_format($pago['cant_abono'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <form action="" method="POST">
                                <input type="hidden" name="action" value="realizar_corte">
                                <input type="hidden" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                                <input type="hidden" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                                <input type="hidden" name="total_calculado" value="<?php echo $total_pendiente; ?>">
                                
                                <button type="submit" class="btn btn-success btn-lg w-100 shadow" onclick="return confirm('¿Está seguro de realizar el corte? Esto congelará los movimientos seleccionados.');">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-lock"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    CONFIRMAR CORTE
                                </button>
                            </form>
                            <?php else: ?>
                                <div class="alert alert-info">No hay movimientos pendientes en este rango de fechas.</div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-xl-7 col-lg-12 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4">
                        <div class="widget-header">
                            <h4>Historial de Cortes</h4>
                        </div>
                        <div class="table-responsive mt-3">
                            <table id="tabla-cortes" class="table dt-table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Fecha Corte</th>
                                        <th>Periodo Abarcado</th>
                                        <th>Total</th>
                                        <th>Usuario</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($res_hist)): ?>
                                    <tr>
                                        <td><span class="badge badge-dark">#<?php echo $row['id_corte']; ?></span></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['creado_en'])); ?></td>
                                        <td>
                                            <small class="d-block text-muted">Desde: <?php echo date('d/m H:i', strtotime($row['fecha_desde'])); ?></small>
                                            <small class="d-block text-muted">Hasta: <?php echo date('d/m H:i', strtotime($row['fecha_hasta'])); ?></small>
                                        </td>
                                        <td class="text-success fw-bold"><?php echo formato_moneda($row['total']); ?></td>
                                        <td><?php echo $row['nombre_usuario']; ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-outline-primary btn-sm" onclick="alert('Función de impresión pendiente de configurar con impresora térmica.')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        $("#tabla-cortes").DataTable({
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": {
                "oPaginate": { "sPrevious": "<", "sNext": ">" },
                "sInfo": "Página _PAGE_ de _PAGES_",
                "sSearch": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-search\'><circle cx=\'11\' cy=\'11\' r=\'8\'></circle><line x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'></line></svg>",
                "sSearchPlaceholder": "Buscar...",
                "sLengthMenu": "Ver :  _MENU_",
            },
            "stripeClasses": [],
            "lengthMenu": [5, 10, 20],
            "pageLength": 5, 
            "order": [[ 0, "desc" ]] 
        });
    </script>
    ';
    include '../../includes/footer.php'; 
    ?>
</div>