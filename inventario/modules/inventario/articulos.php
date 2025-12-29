<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";

// 2. LÓGICA: GUARDAR / EDITAR (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // Limpieza de datos
    $nombre = limpiar_str($_POST['articulo']);
    $marca  = limpiar_str($_POST['marca']);
    $stock  = intval($_POST['existencias']);
    
    if ($_POST['action'] == 'crear') {
        // INSERTAR
        $sql = "INSERT INTO articulos (articulo, marca, existencias, updated_at, eliminado) 
                VALUES ('$nombre', '$marca', $stock, NOW(), 0)";
        
        if (mysqli_query($conexion, $sql)) {
            $mensaje = "Artículo registrado correctamente.";
            $tipo_msg = "success";
        } else {
            $mensaje = "Error al guardar: " . mysqli_error($conexion);
            $tipo_msg = "danger";
        }

    } elseif ($_POST['action'] == 'editar') {
        // ACTUALIZAR
        $id_art = intval($_POST['id_articulo']);
        
        $sql = "UPDATE articulos SET 
                articulo = '$nombre',
                marca = '$marca',
                existencias = $stock,
                updated_at = NOW()
                WHERE id = $id_art";
        
        if (mysqli_query($conexion, $sql)) {
            $mensaje = "Artículo actualizado correctamente.";
            $tipo_msg = "success";
        } else {
            $mensaje = "Error al actualizar: " . mysqli_error($conexion);
            $tipo_msg = "danger";
        }
    }
}

// 3. LÓGICA: ELIMINAR (Soft Delete)
if (isset($_GET['del'])) {
    $id_del = intval($_GET['del']);
    // Validar que no tenga salidas recientes (opcional) o simplemente marcar eliminado
    mysqli_query($conexion, "UPDATE articulos SET eliminado = 1 WHERE id = $id_del");
    $mensaje = "Artículo eliminado del catálogo.";
    $tipo_msg = "warning";
}

// 4. CONSULTA DE DATOS
$sql_lista = "SELECT * FROM articulos WHERE eliminado = 0 ORDER BY articulo ASC";
$res_lista = mysqli_query($conexion, $sql_lista);

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
                                <div class="page-title"><h3>Inventario de Insumos</h3></div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalArticulo" onclick="limpiarModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                Nuevo Artículo
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
                        
                        <table id="tabla-articulos" class="table dt-table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Nombre del Artículo</th>
                                    <th>Marca / Detalle</th>
                                    <th>Stock Actual</th>
                                    <th>Última Act.</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($res_lista)): 
                                    // Semáforo de Stock
                                    $stock = $row['existencias'];
                                    $badge = 'success';
                                    if ($stock < 10) $badge = 'warning';
                                    if ($stock < 5)  $badge = 'danger';
                                ?>
                                <tr>
                                    <td><strong class="text-dark"><?php echo $row['articulo']; ?></strong></td>
                                    
                                    <td><?php echo $row['marca']; ?></td>
                                    
                                    <td>
                                        <span class="badge badge-<?php echo $badge; ?> inv-status">
                                            <?php echo $stock; ?> unidades
                                        </span>
                                    </td>
                                    
                                    <td><?php echo date('d/m/Y', strtotime($row['updated_at'])); ?></td>
                                    
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-horizontal"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                            </a>
                                            <div class="dropdown-menu" aria-labelledby="dropdownMenuLink1">
                                                <a class="dropdown-item" href="javascript:void(0);" onclick='editarArticulo(<?php echo json_encode($row); ?>)'>Editar</a>
                                                <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmarBorrado(<?php echo $row['id']; ?>, '<?php echo $row['articulo']; ?>')">Eliminar</a>
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

    <div class="modal fade" id="modalArticulo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    <input type="hidden" name="id_articulo" id="inputId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nuevo Artículo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        
                        <div class="form-group mb-4">
                            <label>Nombre del Artículo *</label>
                            <input type="text" name="articulo" id="inputNombre" class="form-control" required placeholder="Ej. Café Soluble 1kg">
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label>Marca / Descripción *</label>
                                <input type="text" name="marca" id="inputMarca" class="form-control" required placeholder="Ej. Nescafé">
                            </div>
                            <div class="col-md-6">
                                <label>Stock Inicial / Actual *</label>
                                <input type="number" name="existencias" id="inputStock" class="form-control" required min="0">
                            </div>
                        </div>

                        <div class="alert alert-light-info mb-0">
                            <small><strong>Nota:</strong> Las salidas de stock se registran automáticamente desde la Bitácora de Servicios.</small>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Datos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        $("#tabla-articulos").DataTable({
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": { "oPaginate": { "sPrevious": "<", "sNext": ">" }, "sSearch": "Buscar:", "sLengthMenu": "Ver _MENU_" },
            "stripeClasses": [],
            "lengthMenu": [10, 25, 50],
            "pageLength": 10
        });

        function limpiarModal() {
            document.getElementById("formAction").value = "crear";
            document.getElementById("modalTitle").innerText = "Nuevo Artículo";
            document.getElementById("inputId").value = "";
            document.getElementById("inputNombre").value = "";
            document.getElementById("inputMarca").value = "";
            document.getElementById("inputStock").value = "";
        }

        function editarArticulo(data) {
            var myModal = new bootstrap.Modal(document.getElementById("modalArticulo"));
            
            document.getElementById("formAction").value = "editar";
            document.getElementById("modalTitle").innerText = "Editar: " + data.articulo;
            document.getElementById("inputId").value = data.id;
            
            document.getElementById("inputNombre").value = data.articulo;
            document.getElementById("inputMarca").value = data.marca;
            document.getElementById("inputStock").value = data.existencias;
            
            myModal.show();
        }

        function confirmarBorrado(id, nombre) {
            if(confirm("¿Está seguro de eliminar el artículo: " + nombre + "?")) {
                window.location.href = "?del=" + id;
            }
        }
    </script>
    ';
    include '../../includes/footer.php'; 
    ?>
</div>