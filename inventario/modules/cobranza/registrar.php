<?php
// 1. CONFIGURACIÓN
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";
$id_contrato = isset($_GET['id_contrato']) ? intval($_GET['id_contrato']) : 0;

// 2. PROCESAR EL PAGO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_contrato_post = intval($_POST['id_contrato']);
    $monto_pago = floatval($_POST['monto']);
    $saldo_actual = floatval($_POST['saldo_actual']); // Saldo ANTES del pago
    
    if ($monto_pago > 0 && $monto_pago <= ($saldo_actual + 0.1)) { // Margen de error 0.1 por decimales
        
        mysqli_begin_transaction($conexion);
        
        try {
            // A. Calcular Nuevo Saldo Restante
            $nuevo_saldo = $saldo_actual - $monto_pago;
            if ($nuevo_saldo < 0) $nuevo_saldo = 0;
            
            // B. Insertar Abono en `futuro_abonos`
            $sql_abono = "INSERT INTO futuro_abonos (id_contrato, saldo, cant_abono, fecha_registro) 
                          VALUES ($id_contrato_post, $nuevo_saldo, $monto_pago, NOW())";
            
            if (!mysqli_query($conexion, $sql_abono)) throw new Exception("Error al registrar abono.");
            $id_abono = mysqli_insert_id($conexion);
            
            // C. Si el saldo llega a 0, actualizar estatus del contrato a 'pagado'
            $msg_extra = "";
            if ($nuevo_saldo <= 0.1) {
                $sql_update = "UPDATE futuro_contratos SET estatus = 'pagado' WHERE id_contrato = $id_contrato_post";
                mysqli_query($conexion, $sql_update);
                $msg_extra = " ¡El contrato ha sido LIQUIDADO!";
            }

            mysqli_commit($conexion);
            
            $mensaje = "Pago de ".formato_moneda($monto_pago)." registrado correctamente. Folio Abono: #$id_abono." . $msg_extra;
            $tipo_msg = "success";
            
            // Limpiar variables para volver al selector
            $id_contrato = 0; 
            $data_c = null;

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error: " . $e->getMessage();
            $tipo_msg = "danger";
            $id_contrato = $id_contrato_post; // Mantener formulario
        }
        
    } else {
        $mensaje = "El monto no es válido o excede la deuda pendiente.";
        $tipo_msg = "warning";
        $id_contrato = $id_contrato_post;
    }
}

// 3. CONSULTAR LISTA DE CONTRATOS CON DEUDA (Para el Select)
// Corrección: Se eliminó 'fc.folio_contrato' que no existe en la tabla
$sql_lista = "SELECT fc.id_contrato, vwt.titular, fc.costo_final,
              (SELECT SUM(cant_abono) FROM futuro_abonos WHERE id_contrato = fc.id_contrato) as pagado
              FROM futuro_contratos fc
              LEFT JOIN vw_titular_contrato vwt ON fc.id_contrato = vwt.id_contrato
              WHERE fc.estatus NOT IN ('cancelado', 'pagado', 'eliminado')
              HAVING (costo_final - IFNULL(pagado,0)) > 1
              ORDER BY fc.fecha_registro DESC";

$res_lista = mysqli_query($conexion, $sql_lista);

// 4. SI HAY ID SELECCIONADO: TRAER DETALLES FINANCIEROS
$data_c = null;
if ($id_contrato > 0) {
    // Corrección: Se eliminó 'fc.folio_contrato'
    $sql_det = "SELECT fc.id_contrato, fc.costo_final, fc.estatus, vwt.titular
                FROM futuro_contratos fc
                LEFT JOIN vw_titular_contrato vwt ON fc.id_contrato = vwt.id_contrato
                WHERE fc.id_contrato = $id_contrato";
    $res_det = mysqli_query($conexion, $sql_det);
    $data_c = mysqli_fetch_assoc($res_det);
    
    if ($data_c) {
        // Calcular lo pagado hasta hoy
        $q_pagado = mysqli_query($conexion, "SELECT SUM(cant_abono) as total FROM futuro_abonos WHERE id_contrato = $id_contrato");
        $r_pagado = mysqli_fetch_assoc($q_pagado);
        
        $total_pagado = $r_pagado['total'] ?? 0;
        $saldo_pendiente = $data_c['costo_final'] - $total_pagado;
        if($saldo_pendiente < 0) $saldo_pendiente = 0;
        
        // Historial de abonos previos
        $q_hist = mysqli_query($conexion, "SELECT * FROM futuro_abonos WHERE id_contrato = $id_contrato ORDER BY fecha_registro DESC");
    }
}

// 5. UI
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
                                <div class="page-title"><h3>Caja y Cobranza</h3></div>
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

                <div class="col-12 layout-spacing">
                    <div class="widget widget-content-area br-4">
                        <form action="" method="GET">
                            <div class="form-row align-items-end">
                                <div class="col-md-9">
                                    <label>Buscar Cliente / Contrato con Adeudo</label>
                                    <select name="id_contrato" class="form-control" onchange="this.form.submit()">
                                        <option value="">-- Seleccione Cliente --</option>
                                        <?php 
                                        if($res_lista):
                                            while($row = mysqli_fetch_assoc($res_lista)): 
                                                $pendiente = $row['costo_final'] - $row['pagado'];
                                                // Simulamos un folio visual rellenando con ceros
                                                $folio_txt = str_pad($row['id_contrato'], 5, "0", STR_PAD_LEFT);
                                        ?>
                                            <option value="<?php echo $row['id_contrato']; ?>" <?php if($id_contrato == $row['id_contrato']) echo 'selected'; ?>>
                                                Contrato #<?php echo $folio_txt; ?> - <?php echo $row['titular']; ?> (Debe: <?php echo formato_moneda($pendiente); ?>)
                                            </option>
                                        <?php 
                                            endwhile; 
                                        endif;
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100">Cargar Datos</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($data_c): ?>
                
                <div class="col-xl-6 col-lg-6 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4 h-100">
                        <div class="widget-header">
                            <h3 class="text-primary">Registrar Abono</h3>
                        </div>
                        
                        <div class="info-box bg-light p-3 mb-4 rounded">
                            <h5 class="mb-0 text-dark"><?php echo $data_c['titular']; ?></h5>
                            <p class="text-muted mb-0">Contrato #<?php echo str_pad($data_c['id_contrato'], 5, "0", STR_PAD_LEFT); ?></p>
                        </div>

                        <div class="row text-center mb-4">
                            <div class="col-4 border-end">
                                <span class="text-muted">Costo Total</span>
                                <h5 class="text-dark"><?php echo formato_moneda($data_c['costo_final']); ?></h5>
                            </div>
                            <div class="col-4 border-end">
                                <span class="text-success">Pagado</span>
                                <h5 class="text-success"><?php echo formato_moneda($total_pagado); ?></h5>
                            </div>
                            <div class="col-4">
                                <span class="text-danger">Pendiente</span>
                                <h5 class="text-danger"><?php echo formato_moneda($saldo_pendiente); ?></h5>
                            </div>
                        </div>

                        <?php if ($saldo_pendiente <= 0.1): ?>
                            <div class="alert alert-success text-center">
                                <strong>¡Cuenta Liquidada!</strong><br>Este contrato no tiene adeudos pendientes.
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <input type="hidden" name="id_contrato" value="<?php echo $id_contrato; ?>">
                                <input type="hidden" name="saldo_actual" value="<?php echo $saldo_pendiente; ?>">
                                
                                <div class="form-group mb-4">
                                    <label class="h5">Monto a Pagar ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" name="monto" class="form-control form-control-lg" placeholder="0.00" min="1" max="<?php echo number_format($saldo_pendiente, 2, '.', ''); ?>" required>
                                    </div>
                                    <small class="text-muted">Máximo a recibir: <?php echo formato_moneda($saldo_pendiente); ?></small>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                    Procesar Pago
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4 h-100">
                        <div class="widget-header d-flex justify-content-between">
                            <h4>Historial de Pagos</h4>
                            <button class="btn btn-outline-dark btn-sm" onclick="window.print()">Imprimir Estado</button>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Saldo Restante</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($abo = mysqli_fetch_assoc($q_hist)): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($abo['fecha_registro'])); ?></td>
                                        <td class="text-success fw-bold">+ <?php echo formato_moneda($abo['cant_abono']); ?></td>
                                        <td><?php echo formato_moneda($abo['saldo']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</div>