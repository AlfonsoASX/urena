<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../includes/auth_check.php';

// 2. CONSULTA SQL (Solo cerrados)
// Obtenemos los servicios marcados como 'cerrado' o con la bandera cerrado=1
$sql = "SELECT s.id_servicio, s.folio, s.tipo_servicio, s.modalidad, 
               s.responsable, s.created_at, s.notas,
               f.nom_fallecido, f.dom_velacion
        FROM servicios s
        LEFT JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
        LEFT JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
        WHERE (s.estatus = 'cerrado' OR s.cerrado = 1) AND s.eliminado = 0
        ORDER BY s.id_servicio DESC";

$resultado = mysqli_query($conexion, $sql);

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
                                <div class="page-title"><h3>Historial de Servicios Finalizados</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">
                    <div class="widget-content widget-content-area br-8">
                        
                        <table id="tabla-historial" class="table dt-table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Fallecido</th>
                                    <th>Tipo de Servicio</th>
                                    <th>Fecha Registro</th>
                                    <th>Responsable</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($resultado)): 
                                    // Limpiar notas para pasar al modal
                                    $notas_limpias = htmlspecialchars(json_encode($row['notas']), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td><span class="badge badge-secondary">#<?php echo $row['folio']; ?></span></td>
                                    
                                    <td>
                                        <p class="mb-0 fw-bold text-dark"><?php echo $row['nom_fallecido']; ?></p>
                                        <small class="text-muted">Velación: <?php echo substr($row['dom_velacion'], 0, 20); ?>...</small>
                                    </td>
                                    
                                    <td><?php echo $row['tipo_servicio']; ?> (<?php echo ucfirst($row['modalidad']); ?>)</td>
                                    
                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                    
                                    <td><?php echo $row['responsable']; ?></td>
                                    
                                    <td class="text-center">
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick='verDetalles(<?php echo $notas_limpias; ?>, "<?php echo $row['folio']; ?>", "<?php echo $row['nom_fallecido']; ?>")'>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                        
                                        <a href="#" class="btn btn-outline-dark btn-sm" title="Reimprimir Expediente">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                        </a>
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

    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Expediente Finalizado: <span id="mdlFolio" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="mdlNombre" class="mb-3 fw-bold text-center"></h6>
                    <label class="text-muted">Bitácora y Notas de Cierre:</label>
                    <div class="p-3 bg-light rounded border" style="max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-family: monospace;" id="mdlNotas"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        // Inicializar DataTable
        $("#tabla-historial").DataTable({
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": {
                "oPaginate": { "sPrevious": "<", "sNext": ">" },
                "sInfo": "Mostrando _PAGE_ de _PAGES_",
                "sSearch": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-search\'><circle cx=\'11\' cy=\'11\' r=\'8\'></circle><line x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'></line></svg>",
                "sSearchPlaceholder": "Buscar...",
                "sLengthMenu": "Filas :  _MENU_",
            },
            "stripeClasses": [],
            "lengthMenu": [10, 20, 50],
            "pageLength": 10, 
            "order": [[ 0, "desc" ]] 
        });

        // Función JS para abrir modal y llenar datos dinámicamente
        function verDetalles(notas, folio, nombre) {
            document.getElementById("mdlFolio").innerText = "#" + folio;
            document.getElementById("mdlNombre").innerText = nombre;
            // Si notas viene null, poner texto default
            document.getElementById("mdlNotas").innerText = notas ? notas : "Sin notas registradas.";
            
            var myModal = new bootstrap.Modal(document.getElementById("modalDetalle"));
            myModal.show();
        }
    </script>
    ';
    include '../../includes/footer.php'; 
    ?>
</div>