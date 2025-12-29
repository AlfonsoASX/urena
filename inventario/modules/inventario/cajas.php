<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";

// 2. LOGICA: GUARDAR / EDITAR (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // Recibir datos
    $codigo         = limpiar_str($_POST['codigo']);
    $modelo         = limpiar_str($_POST['modelo']);
    $color          = limpiar_str($_POST['color']);
    $costo          = floatval($_POST['costo']);
    $id_proveedor   = intval($_POST['id_proveedor']);
    $tipo_prop      = isset($_POST['es_reciclado']) ? 1 : 0; // 1 = Renta/Reciclable, 0 = Venta Unica
    $estatus        = limpiar_str($_POST['estatus_logico']);
    
    // Calcular 'disponible' basado en el estatus lógico
    $disponible = ($estatus == 'disponible') ? 1 : 0;

    if ($_POST['action'] == 'crear') {
        // INSERTAR
        // Verificamos duplicados de código
        $check = mysqli_query($conexion, "SELECT codigo FROM cajas WHERE codigo = '$codigo'");
        if (mysqli_num_rows($check) > 0) {
            $mensaje = "El código '$codigo' ya existe en el inventario.";
            $tipo_msg = "danger";
        } else {
            $sql = "INSERT INTO cajas (codigo, modelo, color, costo, id_proveedor, reciclado, estatus_logico, disponible, created_at)
                    VALUES ('$codigo', '$modelo', '$color', $costo, $id_proveedor, $tipo_prop, '$estatus', $disponible, NOW())";
            
            if (mysqli_query($conexion, $sql)) {
                $mensaje = "Ataúd registrado correctamente.";
                $tipo_msg = "success";
            } else {
                $mensaje = "Error al guardar: " . mysqli_error($conexion);
                $tipo_msg = "danger";
            }
        }
    } elseif ($_POST['action'] == 'editar') {
        // ACTUALIZAR (El código no se edita porque es PK, se usa un hidden original)
        $codigo_orig = limpiar_str($_POST['codigo_original']);
        
        $sql = "UPDATE cajas SET 
                modelo = '$modelo',
                color = '$color',
                costo = $costo,
                id_proveedor = $id_proveedor,
                reciclado = $tipo_prop,
                estatus_logico = '$estatus',
                disponible = $disponible,
                updated_at = NOW()
                WHERE codigo = '$codigo_orig'";
        
        if (mysqli_query($conexion, $sql)) {
            $mensaje = "Información actualizada correctamente.";
            $tipo_msg = "success";
        } else {
            $mensaje = "Error al actualizar: " . mysqli_error($conexion);
            $tipo_msg = "danger";
        }
    }
}

// 3. LOGICA: ELIMINAR (Soft Delete)
if (isset($_GET['del'])) {
    $cod_del = limpiar_str($_GET['del']);
    mysqli_query($conexion, "UPDATE cajas SET eliminado = 1, disponible = 0 WHERE codigo = '$cod_del'");
    $mensaje = "Ataúd eliminado del inventario activo.";
    $tipo_msg = "warning";
}

// 4. CONSULTA DE DATOS
// Lista de Cajas
$sql_cajas = "SELECT c.*, p.nombre as nombre_proveedor 
              FROM cajas c
              LEFT JOIN proveedores p ON c.id_proveedor = p.id_proveedor
              WHERE c.eliminado = 0
              ORDER BY c.created_at DESC";
$res_cajas = mysqli_query($conexion, $sql_cajas);

// Lista de Proveedores (Para el Modal)
$res_prov = mysqli_query($conexion, "SELECT id_proveedor, nombre FROM proveedores WHERE eliminado = 0 AND estatus = 'activo'");
$proveedores = [];
while($p = mysqli_fetch_assoc($res_prov)) { $proveedores[] = $p; }


// 5. UI
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
                                <div class="page-title"><h3>Inventario de Ataúdes y Urnas</h3></div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCaja" onclick="limpiarModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                Nuevo Ingreso
                            </button>
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

                <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">
                    <div class="widget-content widget-content-area br-8">
                        
                        <table id="tabla-cajas" class="table dt-table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Código / Serie</th>
                                    <th>Modelo y Color</th>
                                    <th>Proveedor</th>
                                    <th>Costo</th>
                                    <th>Tipo</th>
                                    <th>Estatus</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($res_cajas)): 
                                    // Badges de Estatus
                                    $badge = 'secondary';
                                    $st = $row['estatus_logico'];
                                    if($st == 'disponible') $badge = 'success';
                                    if($st == 'rentado') $badge = 'warning'; // En servicio
                                    if($st == 'mantenimiento') $badge = 'danger';
                                    if($st == 'baja') $badge = 'dark';
                                    
                                    // Icono de Reciclado
                                    $icon_tipo = ($row['reciclado'] == 1) 
                                        ? '<span class="badge badge-light-info" title="Equipo de Renta/Reutilizable">Renta ('.$row['veces_usado'].' usos)</span>' 
                                        : '<span class="badge badge-light-primary">Venta</span>';
                                ?>
                                <tr>
                                    <td><strong class="text-primary"><?php echo $row['codigo']; ?></strong></td>
                                    
                                    <td>
                                        <span class="d-block fw-bold"><?php echo $row['modelo']; ?></span>
                                        <small class="text-muted"><?php echo $row['color']; ?></small>
                                    </td>
                                    
                                    <td><?php echo $row['nombre_proveedor'] ?? 'N/A'; ?></td>
                                    
                                    <td><?php echo formato_moneda($row['costo']); ?></td>
                                    
                                    <td><?php echo $icon_tipo; ?></td>
                                    
                                    <td><span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($st); ?></span></td>
                                    
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-horizontal"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                            </a>
                                            <div class="dropdown-menu" aria-labelledby="dropdownMenuLink1">
                                                <a class="dropdown-item" href="javascript:void(0);" onclick='editarCaja(<?php echo json_encode($row); ?>)'>Editar / Ajustar</a>
                                                <?php if($st == 'disponible'): ?>
                                                <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmarBorrado('<?php echo $row['codigo']; ?>')">Eliminar</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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

    <div class="modal fade" id="modalCaja" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    <input type="hidden" name="codigo_original" id="codigoOriginal">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nuevo Ingreso de Ataúd</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label>Código de Barras / Serie *</label>
                                <input type="text" name="codigo" id="inputCodigo" class="form-control" required placeholder="Escanee o escriba código">
                            </div>
                            <div class="col-md-6">
                                <label>Modelo *</label>
                                <input type="text" name="modelo" id="inputModelo" class="form-control" required placeholder="Ej. Madera Fina Caoba">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label>Color / Acabado</label>
                                <input type="text" name="color" id="inputColor" class="form-control" placeholder="Ej. Chocolate brillante">
                            </div>
                            <div class="col-md-6">
                                <label>Costo Adquisición ($)</label>
                                <input type="number" step="0.01" name="costo" id="inputCosto" class="form-control" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label>Proveedor</label>
                                <select name="id_proveedor" id="inputProveedor" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach($proveedores as $prov): ?>
                                        <option value="<?php echo $prov['id_proveedor']; ?>"><?php echo $prov['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Estatus Inicial</label>
                                <select name="estatus_logico" id="inputEstatus" class="form-control">
                                    <option value="disponible">Disponible</option>
                                    <option value="mantenimiento">En Mantenimiento</option>
                                    <option value="rentado" disabled>Rentado (Solo por sistema)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="checkReciclado" name="es_reciclado">
                                <label class="form-check-label" for="checkReciclado">¿Es equipo de renta/reutilizable? (Urnas, Ataúdes de traslado)</label>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        $("#tabla-cajas").DataTable({
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": { "oPaginate": { "sPrevious": "<", "sNext": ">" }, "sSearch": "Buscar:", "sLengthMenu": "Ver _MENU_" },
            "stripeClasses": [],
            "lengthMenu": [10, 20, 50],
            "pageLength": 10
        });

        function limpiarModal() {
            document.getElementById("formAction").value = "crear";
            document.getElementById("modalTitle").innerText = "Nuevo Ingreso de Ataúd";
            document.getElementById("inputCodigo").value = "";
            document.getElementById("inputCodigo").readOnly = false;
            document.getElementById("inputModelo").value = "";
            document.getElementById("inputColor").value = "";
            document.getElementById("inputCosto").value = "";
            document.getElementById("inputProveedor").value = "";
            document.getElementById("checkReciclado").checked = false;
            document.getElementById("inputEstatus").value = "disponible";
        }

        function editarCaja(data) {
            var myModal = new bootstrap.Modal(document.getElementById("modalCaja"));
            
            document.getElementById("formAction").value = "editar";
            document.getElementById("modalTitle").innerText = "Editar Ataúd: " + data.modelo;
            
            document.getElementById("inputCodigo").value = data.codigo;
            document.getElementById("codigoOriginal").value = data.codigo; // Para el WHERE
            document.getElementById("inputCodigo").readOnly = true; // PK no editable
            
            document.getElementById("inputModelo").value = data.modelo;
            document.getElementById("inputColor").value = data.color;
            document.getElementById("inputCosto").value = data.costo;
            document.getElementById("inputProveedor").value = data.id_proveedor;
            document.getElementById("inputEstatus").value = data.estatus_logico;
            
            document.getElementById("checkReciclado").checked = (data.reciclado == 1);
            
            myModal.show();
        }

        function confirmarBorrado(codigo) {
            if(confirm("¿Está seguro de dar de baja este ataúd? Esta acción es lógica pero irreversible para el usuario.")) {
                window.location.href = "?del=" + codigo;
            }
        }
    </script>
    ';
    include '../../includes/footer.php'; 
    ?>
</div>