<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../includes/auth_check.php';

// Validar ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$id_servicio = intval($_GET['id']);
$mensaje = "";
$tipo_msg = "";

// 2. LÓGICA DE NEGOCIO (POST)

// A. REGISTRAR INCIDENCIA / NOTA
if (isset($_POST['btn_nota'])) {
    $nota_nueva = limpiar_str($_POST['nota']);
    $usuario = $_SESSION['usuario_nombre'] ?? 'Sistema';
    $fecha_hora = date('d/m/Y H:i');
    
    // Formato: [Fecha - Usuario]: Nota
    $texto_bitacora = "\n[$fecha_hora - $usuario]: $nota_nueva";
    
    $sql_update = "UPDATE servicios SET notas = CONCAT(IFNULL(notas,''), '$texto_bitacora') WHERE id_servicio = $id_servicio";
    if(mysqli_query($conexion, $sql_update)){
        $mensaje = "Bitácora actualizada correctamente.";
        $tipo_msg = "success";
    }
}

// B. SOLICITAR ARTÍCULO EXTRA (Genera Vale de Salida)
if (isset($_POST['btn_articulo'])) {
    $id_articulo = intval($_POST['id_articulo']);
    $cantidad = intval($_POST['cantidad']);
    
    if ($cantidad > 0 && $id_articulo > 0) {
        mysqli_begin_transaction($conexion);
        try {
            // 1. Verificar Stock
            $res_stock = mysqli_query($conexion, "SELECT articulo, existencias FROM articulos WHERE id = $id_articulo");
            $row_stock = mysqli_fetch_assoc($res_stock);
            
            if ($row_stock['existencias'] < $cantidad) {
                throw new Exception("Stock insuficiente. Solo hay {$row_stock['existencias']}.");
            }

            // 2. Crear Vale de Salida
            // Nota: La tabla vales_salida usa varchar para responsable/solicitante según tu esquema
            $responsable = $_SESSION['usuario_nombre'] ?? 'Auxiliar';
            $solicitante = "Servicio #$id_servicio"; // Referencia al servicio
            
            $sql_vale = "INSERT INTO vales_salida (responsable, solicitante, fecha) VALUES ('$responsable', '$solicitante', NOW())";
            mysqli_query($conexion, $sql_vale);
            $id_vale = mysqli_insert_id($conexion);

            // 3. Detalle del Vale
            $sql_det = "INSERT INTO articulos_vale_salida (id, cantidad, id_vale, fecha) VALUES ($id_articulo, $cantidad, $id_vale, NOW())";
            mysqli_query($conexion, $sql_det);

            // 4. Descontar Inventario
            $sql_resta = "UPDATE articulos SET existencias = existencias - $cantidad WHERE id = $id_articulo";
            mysqli_query($conexion, $sql_resta);

            // 5. Registrar en notas del servicio también para trazabilidad rápida
            $log = "\n[".date('d/m H:i')." - Sistema]: Solicitud Extra - {$cantidad}x {$row_stock['articulo']}";
            mysqli_query($conexion, "UPDATE servicios SET notas = CONCAT(IFNULL(notas,''), '$log') WHERE id_servicio = $id_servicio");

            mysqli_commit($conexion);
            $mensaje = "Material solicitado y descontado del inventario.";
            $tipo_msg = "success";

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error: " . $e->getMessage();
            $tipo_msg = "danger";
        }
    }
}

// C. ACTUALIZAR ESTATUS (Finalizar)
if (isset($_POST['btn_status'])) {
    $nuevo_estatus = limpiar_str($_POST['nuevo_estatus']);
    // Validamos estatus permitidos
    if (in_array($nuevo_estatus, ['en_curso', 'por_cerrar'])) {
        $sql_st = "UPDATE servicios SET estatus = '$nuevo_estatus' WHERE id_servicio = $id_servicio";
        mysqli_query($conexion, $sql_st);
        $mensaje = "Estatus actualizado a: " . ucfirst(str_replace('_', ' ', $nuevo_estatus));
        $tipo_msg = "warning";
    }
}

// 3. CONSULTA DE DATOS DEL SERVICIO
$sql_info = "SELECT s.*, f.nom_fallecido, f.dom_velacion, f.municipio 
             FROM servicios s
             JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
             JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
             WHERE s.id_servicio = $id_servicio";
$res_info = mysqli_query($conexion, $sql_info);
$data = mysqli_fetch_assoc($res_info);

if (!$data) { echo "Servicio no encontrado"; exit; }

// Obtener Lista de Artículos para el Select
$res_articulos = mysqli_query($conexion, "SELECT id, articulo, existencias FROM articulos WHERE existencias > 0 AND eliminado = 0 ORDER BY articulo ASC");

// 4. UI
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<style>
    .bitacora-box { background: #f1f2f3; padding: 15px; border-radius: 8px; max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; }
    .status-active { border-left: 5px solid #1abc9c; } /* Verde */
    .status-closing { border-left: 5px solid #e2a03f; } /* Naranja */
</style>

<div id="content" class="main-content">
    <div class="layout-px-spacing">

        <div class="middle-content container-xxl p-0">
            
            <div class="secondary-nav">
                <div class="breadcrumbs-container" data-page-heading="Sales">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title">
                                    <h3>Seguimiento Operativo <small class="text-muted">#<?php echo $data['folio']; ?></small></h3>
                                </div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <a href="index.php" class="btn btn-dark">Volver al Tablero</a>
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

                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 col-12 layout-spacing">
                    
                    <div class="widget widget-content-area br-4 mb-3 <?php echo ($data['estatus']=='en_curso') ? 'status-active' : 'status-closing'; ?>">
                        <div class="d-flex justify-content-between">
                            <h5 class="">Estado Actual</h5>
                            <?php echo badge_estatus($data['estatus']); ?>
                        </div>
                        
                        <hr>
                        
                        <h6 class="text-primary"><?php echo $data['nom_fallecido']; ?></h6>
                        <p class="mb-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-map-pin"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> 
                            <strong>Ubicación:</strong>
                        </p>
                        <p class="ms-4 text-muted">
                            <?php echo $data['dom_velacion']; ?><br>
                            <?php echo $data['municipio']; ?>
                        </p>
                        
                        <div class="mt-3">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($data['dom_velacion'] . ', ' . $data['municipio']); ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                Ver en Google Maps
                            </a>
                        </div>
                    </div>

                    <div class="widget widget-content-area br-4">
                        <h5 class="mb-3">Acciones de Servicio</h5>
                        <form method="POST" action="">
                            <?php if($data['estatus'] == 'en_curso'): ?>
                                <button type="submit" name="btn_status" value="por_cerrar" class="btn btn-warning w-100 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-flag"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                    Notificar Fin de Velación
                                </button>
                                <small class="text-muted d-block text-center">Usar cuando el cuerpo sale rumbo al panteón/crematorio.</small>
                            <?php elseif($data['estatus'] == 'por_cerrar'): ?>
                                <div class="alert alert-warning">
                                    El servicio está marcado para cierre administrativo. <br>
                                    <a href="cierre.php?id=<?php echo $id_servicio; ?>">Ir a Cierre y Devoluciones</a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                </div>

                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4 h-100">
                        <div class="widget-header">
                            <div class="row">
                                <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                                    <h4>Bitácora de Incidencias</h4>
                                </div>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="bitacora-box mb-3">
                                <?php echo empty($data['notas']) ? "Sin registros en bitácora..." : $data['notas']; ?>
                            </div>

                            <form method="POST" action="">
                                <div class="form-group">
                                    <label>Nueva Observación / Incidencia</label>
                                    <textarea name="nota" class="form-control" rows="2" placeholder="Ej: Llegada a sala 2:00 PM, Familia solicita..." required></textarea>
                                </div>
                                <button type="submit" name="btn_nota" class="btn btn-secondary mt-2 float-end">Registrar Nota</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-12 col-md-12 col-sm-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4">
                        <div class="widget-header">
                            <div class="row">
                                <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                                    <h4>Solicitar Insumos Extra</h4>
                                </div>
                            </div>
                        </div>
                        <div class="widget-content">
                            <p class="text-muted mb-3">Si requiere más material del almacén (café, azúcar, flores), regístrelo aquí para generar el vale automático.</p>
                            
                            <form method="POST" action="">
                                <div class="form-group mb-3">
                                    <label>Artículo</label>
                                    <select name="id_articulo" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        <?php while($art = mysqli_fetch_assoc($res_articulos)): ?>
                                            <option value="<?php echo $art['id']; ?>">
                                                <?php echo $art['articulo']; ?> (Stock: <?php echo $art['existencias']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label>Cantidad</label>
                                    <input type="number" name="cantidad" class="form-control" value="1" min="1" required>
                                </div>

                                <button type="submit" name="btn_articulo" class="btn btn-success w-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus-circle"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                    Solicitar y Registrar Salida
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="widget widget-content-area br-4 mt-3">
                        <h5>Insumos Iniciales</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Ceras / Velas
                                <span class="badge badge-primary rounded-pill"><?php echo $data['velas']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Kits Despensa
                                <span class="badge badge-primary rounded-pill"><?php echo $data['despensa']; ?></span>
                            </li>
                        </ul>
                    </div>

                </div>

            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        var bitacora = document.querySelector('.bitacora-box');
        if(bitacora) { bitacora.scrollTop = bitacora.scrollHeight; }
    </script>
</div>