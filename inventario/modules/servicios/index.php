<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../includes/auth_check.php';

// 2. CONSULTA DE SERVICIOS ACTIVOS
// Obtenemos servicios que NO estén eliminados ni cerrados administrativamente.
// Hacemos JOIN con fallecido para mostrar quién es el servicio.
$sql = "SELECT s.id_servicio, s.folio, s.tipo_servicio, s.modalidad, 
               s.responsable, s.estatus, s.created_at,
               f.nom_fallecido, f.dom_velacion, f.municipio
        FROM servicios s
        LEFT JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
        LEFT JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
        WHERE s.eliminado = 0 AND s.cerrado = 0
        ORDER BY s.created_at DESC";

$resultado = mysqli_query($conexion, $sql);

// 3. RENDERIZADO DE INTERFAZ
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
                <div class="breadcrumbs-container" data-page-heading="Analytics">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title"><h3>Tablero de Servicios Activos</h3></div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <a href="asignar.php" class="btn btn-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-activity"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                                Nueva Asignación
                            </a>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                <div class="col-xl-12 col-lg-12 col-sm-12  layout-spacing">
                    <div class="widget-content widget-content-area br-8">
                        
                        <table id="tabla-servicios" class="table dt-table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Fallecido / Ubicación</th>
                                    <th>Tipo & Responsable</th>
                                    <th>Fecha Inicio</th>
                                    <th>Etapa Actual</th>
                                    <th class="text-center dt-no-sorting">Gestión</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($resultado)): 
                                    // Lógica visual para el estatus
                                    $estatus_color = 'info';
                                    $estatus_texto = ucfirst($row['estatus']);
                                    
                                    if($row['estatus'] == 'abierto') { $estatus_color = 'primary'; $estatus_texto = 'En Preparación'; }
                                    if($row['estatus'] == 'en_curso') { $estatus_color = 'success'; $estatus_texto = 'En Velación'; }
                                    if($row['estatus'] == 'por_cerrar') { $estatus_color = 'warning'; $estatus_texto = 'Por Finalizar'; }
                                ?>
                                <tr>
                                    <td><span class="badge badge-light-dark">#<?php echo $row['folio']; ?></span></td>
                                    
                                    <td>
                                        <p class="mb-0 fw-bold text-dark"><?php echo $row['nom_fallecido'] ? $row['nom_fallecido'] : 'Sin asignar'; ?></p>
                                        <small class="text-muted">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-map-pin"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> 
                                            <?php echo substr($row['dom_velacion'], 0, 30) . '...'; ?>
                                        </small>
                                    </td>
                                    
                                    <td>
                                        <span class="d-block"><?php echo $row['tipo_servicio']; ?> (<?php echo $row['modalidad']; ?>)</span>
                                        <small class="text-info">Coord: <?php echo $row['responsable']; ?></small>
                                    </td>
                                    
                                    <td><?php echo date('d M, H:i', strtotime($row['created_at'])); ?></td>
                                    
                                    <td><span class="badge badge-<?php echo $estatus_color; ?>"><?php echo $estatus_texto; ?></span></td>
                                    
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <a class="dropdown-toggle" href="#" role="button" id="servDrop<?php echo $row['id_servicio']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-horizontal"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                            </a>

                                            <div class="dropdown-menu" aria-labelledby="servDrop<?php echo $row['id_servicio']; ?>">
                                                
                                                <h6 class="dropdown-header">Logística</h6>
                                                <a class="dropdown-item" href="asignar.php?id=<?php echo $row['id_servicio']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-box"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> Asignar Recursos
                                                </a>
                                                
                                                <div class="dropdown-divider"></div>

                                                <h6 class="dropdown-header">Ejecución</h6>
                                                <a class="dropdown-item" href="seguimiento.php?id=<?php echo $row['id_servicio']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clipboard"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> Bitácora / Notas
                                                </a>
                                                <a class="dropdown-item" href="orden_servicio.php?id=<?php echo $row['id_servicio']; ?>" target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Imprimir Orden
                                                </a>

                                                <div class="dropdown-divider"></div>

                                                <a class="dropdown-item text-danger" href="cierre.php?id=<?php echo $row['id_servicio']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check-square"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg> 
                                                    <strong>Cerrar Servicio</strong>
                                                </a>
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

    <?php 
    // SCRIPTS
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        $("#tabla-servicios").DataTable({
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": {
                "oPaginate": { "sPrevious": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-arrow-left\'><line x1=\'19\' y1=\'12\' x2=\'5\' y2=\'12\'></line><polyline points=\'12 19 5 12 12 5\'></polyline></svg>", "sNext": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-arrow-right\'><line x1=\'5\' y1=\'12\' x2=\'19\' y2=\'12\'></line><polyline points=\'12 5 19 12 12 19\'></polyline></svg>" },
                "sInfo": "Página _PAGE_ de _PAGES_",
                "sSearch": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-search\'><circle cx=\'11\' cy=\'11\' r=\'8\'></circle><line x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'></line></svg>",
                "sSearchPlaceholder": "Buscar servicio...",
                "sLengthMenu": "Ver :  _MENU_",
            },
            "stripeClasses": [],
            "lengthMenu": [5, 10, 20, 50],
            "pageLength": 10,
            "order": [[ 3, "desc" ]] // Ordenar por Fecha Inicio
        });
    </script>
    ';
    
    include '../../includes/footer.php'; 
    ?>
</div>