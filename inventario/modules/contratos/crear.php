<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
include '../../config/db.php';
include '../../config/global.php';
include '../../includes/auth_check.php';

// Variables para mensajes
$mensaje = "";
$tipo_msg = "";

// 2. PROCESAR FORMULARIO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // A. Limpiar datos de entrada
    $nombre         = limpiar_str($_POST['nombre']);
    $apellido_p     = limpiar_str($_POST['apellido_p']);
    $apellido_m     = limpiar_str($_POST['apellido_m']);
    $telefono       = limpiar_str($_POST['telefono']);
    
    // Dirección
    $calle          = limpiar_str($_POST['calle']);
    $colonia        = limpiar_str($_POST['colonia']);
    $municipio      = limpiar_str($_POST['municipio']);
    $num_ext        = limpiar_str($_POST['num_ext']);
    
    // Datos del Contrato
    $tipo_contrato  = limpiar_str($_POST['tipo_contrato']); // Cremación/Inhumación
    $tipo_venta     = limpiar_str($_POST['tipo_venta']);    // Previsión/Inmediato
    $costo_total    = (float) $_POST['costo_total'];
    $pago_inicial   = (float) $_POST['pago_inicial'];
    $saldo_inicial  = $costo_total - $pago_inicial;
    
    // B. Iniciar Transacción (Todo o Nada)
    mysqli_begin_transaction($conexion);
    
    try {
        // PASO 1: Insertar Titular
        $sql_titular = "INSERT INTO titulares (nombre, apellido_p, apellido_m, created_at) 
                        VALUES ('$nombre', '$apellido_p', '$apellido_m', NOW())";
        if (!mysqli_query($conexion, $sql_titular)) throw new Exception("Error al guardar titular.");
        $id_titular = mysqli_insert_id($conexion);

        // PASO 2: Insertar Teléfono (Si existe)
        if (!empty($telefono)) {
            $sql_tel = "INSERT INTO telefonos (numero, tipo_tel, created_at) VALUES ('$telefono', 'movil', NOW())";
            if (!mysqli_query($conexion, $sql_tel)) throw new Exception("Error al guardar teléfono.");
            $id_tel = mysqli_insert_id($conexion);
            
            // Relacionar Titular-Telefono
            mysqli_query($conexion, "INSERT INTO titular_tels (id_titular, id_tel) VALUES ($id_titular, $id_tel)");
        }

        // PASO 3: Insertar Domicilio (Si existe)
        if (!empty($calle)) {
            $sql_dom = "INSERT INTO domicilios (calle, num_ext, colonia, municipio, num_int, entre_calle1, entre_calle2, tipo_dom, notas) 
                        VALUES ('$calle', '$num_ext', '$colonia', '$municipio', '', '', '', 'casa', '')";
            if (!mysqli_query($conexion, $sql_dom)) throw new Exception("Error al guardar domicilio.");
            $id_domicilio = mysqli_insert_id($conexion);
            
            // Relacionar Titular-Domicilio
            mysqli_query($conexion, "INSERT INTO titular_dom (id_titular, id_domicilio) VALUES ($id_titular, $id_domicilio)");
        }

        // PASO 4: Crear Contrato
        // Nota: 'tipo_pago' lo definimos como 'contado' o 'credito' basado en si hay saldo
        $es_credito = ($saldo_inicial > 0) ? 'credito' : 'contado';
        
        $sql_contrato = "INSERT INTO futuro_contratos (
                            tipo_contrato, tipo_pago, costo_contrato, costo_final, 
                            estatus, fecha_registro, porc_empresa
                         ) VALUES (
                            '$tipo_contrato', '$es_credito', $costo_total, $costo_total, 
                            'registrado', NOW(), 100
                         )";
        
        if (!mysqli_query($conexion, $sql_contrato)) throw new Exception("Error al crear contrato: " . mysqli_error($conexion));
        $id_contrato = mysqli_insert_id($conexion);

        // PASO 5: Relacionar Titular con Contrato
        $sql_rel_tc = "INSERT INTO titular_contrato (id_titular, id_contrato) VALUES ($id_titular, $id_contrato)";
        if (!mysqli_query($conexion, $sql_rel_tc)) throw new Exception("Error relacionando cliente.");

        // PASO 6: Registrar Pago Inicial (Si hubo)
        if ($pago_inicial > 0) {
            $sql_abono = "INSERT INTO futuro_abonos (id_contrato, saldo, cant_abono, fecha_registro) 
                          VALUES ($id_contrato, $saldo_inicial, $pago_inicial, NOW())";
            mysqli_query($conexion, $sql_abono);
        }

        // Confirmar Transacción
        mysqli_commit($conexion);
        
        // Redirigir o mostrar éxito
        $mensaje = "¡Contrato #$id_contrato registrado correctamente!";
        $tipo_msg = "success";
        
        // Opcional: Redirigir al listado después de 2 seg
        // header("refresh:2;url=index.php");

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $mensaje = "Error del sistema: " . $e->getMessage();
        $tipo_msg = "danger";
    }
}

// 3. RENDERIZAR VISTA
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
                                <div class="page-title"><h3>Nuevo Contrato</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                
                <div class="col-xl-12 col-lg-12 col-md-12 col-12 layout-spacing">
                    
                    <?php if($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> mb-4" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><svg> ... </svg></button>
                        <strong><?php echo ($tipo_msg=='success') ? 'Éxito:' : 'Error:'; ?></strong> <?php echo $mensaje; ?>
                    </div>
                    <?php endif; ?>

                    <div class="widget widget-content-area br-4">
                        <form method="POST" action="" class="needs-validation" novalidate>
                            
                            <h5 class="mb-4 text-primary">1. Datos del Contratante (Titular)</h5>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-4">
                                    <label>Nombre(s) *</label>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Apellido Paterno *</label>
                                    <input type="text" name="apellido_p" class="form-control" placeholder="Ej: Pérez" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Apellido Materno</label>
                                    <input type="text" name="apellido_m" class="form-control" placeholder="Ej: López">
                                </div>
                            </div>

                            <div class="form-row mb-4">
                                <div class="form-group col-md-4">
                                    <label>Teléfono Celular *</label>
                                    <input type="tel" name="telefono" class="form-control" placeholder="Ej: 477 123 4567" required>
                                </div>
                                <div class="form-group col-md-8">
                                    <label>Correo Electrónico (Opcional)</label>
                                    <input type="email" name="email" class="form-control" placeholder="cliente@email.com">
                                </div>
                            </div>

                            <h6 class="mt-4 mb-3">Domicilio del Titular</h6>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-6">
                                    <label>Calle</label>
                                    <input type="text" name="calle" class="form-control">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Núm. Ext</label>
                                    <input type="text" name="num_ext" class="form-control">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Colonia</label>
                                    <input type="text" name="colonia" class="form-control">
                                </div>
                            </div>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-4">
                                    <label>Municipio / Ciudad</label>
                                    <input type="text" name="municipio" class="form-control" value="León">
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-4 text-primary">2. Detalles del Servicio</h5>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-4">
                                    <label>Tipo de Venta *</label>
                                    <select name="tipo_venta" class="form-control">
                                        <option value="inmediato">Uso Inmediato</option>
                                        <option value="prevision">Previsión (Futuro)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Modalidad *</label>
                                    <select name="tipo_contrato" class="form-control">
                                        <option value="Inhumacion">Inhumación (Sepultura)</option>
                                        <option value="Cremacion">Cremación</option>
                                        <option value="Traslado">Traslado</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Modelo de Ataúd (Pre-selección)</label>
                                    <select name="modelo_ataud" class="form-control">
                                        <option value="">-- Seleccione (Opcional) --</option>
                                        <option value="madera_fina">Madera Fina</option>
                                        <option value="metalico">Metálico Estándar</option>
                                        <option value="ecologico">Ecológico</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row mb-4">
                                <div class="form-group col-md-12">
                                    <label>Nombre del Fallecido (Solo para Uso Inmediato)</label>
                                    <input type="text" name="nombre_fallecido" class="form-control" placeholder="Dejar en blanco si es previsión">
                                    <small class="text-muted">Si se llena, se vinculará al activar el servicio.</small>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-4 text-primary">3. Información Financiera</h5>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-4">
                                    <label>Costo Total del Paquete ($) *</label>
                                    <div class="input-group mb-4">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" name="costo_total" class="form-control" required placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Pago Inicial / Anticipo ($)</label>
                                    <div class="input-group mb-4">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" name="pago_inicial" class="form-control" value="0.00">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Plazo de Pago (Meses)</label>
                                    <select name="plazo" class="form-control">
                                        <option value="1">Contado (1 exhibición)</option>
                                        <option value="3">3 Meses</option>
                                        <option value="6">6 Meses</option>
                                        <option value="12">12 Meses</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3 btn-lg">Guardar Contrato</button>
                            <a href="index.php" class="btn btn-dark mt-3">Cancelar</a>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</div>