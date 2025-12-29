<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
// ==============================================================================
require_once '../../config/db.php';       // Conexión a BD
require_once '../../config/global.php';   // Helpers (formato_moneda, badge_estatus)
require_once '../../includes/auth_check.php'; // Validar sesión iniciada

// 2. CONSULTA DE DATOS
// ==============================================================================
// Utilizamos la VISTA 'vw_titular_contrato' definida en tu estructura SQL
// para obtener el nombre del cliente sin hacer JOINs complejos manualmente.
$sql = "SELECT fc.id_contrato, fc.tipo_contrato, fc.tipo_pago, 
               fc.costo_final, fc.estatus, fc.fecha_registro,
               vwt.titular
        FROM futuro_contratos fc
        LEFT JOIN vw_titular_contrato vwt ON fc.id_contrato = vwt.id_contrato
        WHERE fc.estatus != 'eliminado'
        ORDER BY fc.fecha_registro DESC";

$resultado = mysqli_query($conexion, $sql);

// 3. UI: HEADER Y MENÚ
// ==============================================================================
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
                                <div class="page-title"><h3>Historial de Contratos</h3></div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <a href="crear.php" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus-circle"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                Nuevo Contrato
                            </a>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">
                    <div class="widget-content widget-content-area br-8">
                        
                        <table id="tabla-contratos" class="table dt-table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Titular / Cliente</th>
                                    <th>Servicio</th>
                                    <th>Fecha Registro</th>
                                    <th>Costo Final</th>
                                    <th>Estatus</th>
                                    <th class="text-center dt-no-sorting">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($resultado)): 
                                    // Generar Folio Visual (Relleno con ceros, ej: 00045)
                                    $folio_visual = str_pad($row['id_contrato'], 5, "0", STR_PAD_LEFT);
                                    
                                    // Lógica para botones de acción
                                    $es_cancelado = ($row['estatus'] == 'cancelado');
                                ?>
                                <tr>
                                    <td><span class="text-primary fw-bold">#<?php echo $folio_visual; ?></span></td>
                                    
                                    <td>
                                        <div class="d-flex">
                                            <div class="usr-img-frame me-2 rounded-circle">
                                                <img alt="avatar" class="img-fluid rounded-circle" src="<?php echo BASE_URL; ?>src/assets/img/profile-12.jpeg">
                                            </div>
                                            <p class="align-self-center mb-0 admin-name">
                                                <?php echo $row['titular'] ? $row['titular'] : '<span class="text-muted">Sin Titular</span>'; ?>
                                            </p>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php echo ucfirst($row['tipo_contrato']); ?>
                                        <br>
                                        <span class="badge badge-light-secondary mt-1"><?php echo ucfirst($row['tipo_pago']); ?></span>
                                    </td>
                                    
                                    <td><?php echo date('d/m/Y', strtotime($row['fecha_registro'])); ?></td>
                                    
                                    <td><?php echo formato_moneda($row['costo_final']); ?></td>
                                    
                                    <td><?php echo badge_estatus($row['estatus']); ?></td>
                                    
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink<?php echo $row['id_contrato']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-horizontal"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                            </a>

                                            <div class="dropdown-menu" aria-labelledby="dropdownMenuLink<?php echo $row['id_contrato']; ?>">
                                                
                                                <a class="dropdown-item" href="ver.php?id=<?php echo $row['id_contrato']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> 
                                                    Ver Detalle
                                                </a>
                                                
                                                <a class="dropdown-item" href="pdf_contrato.php?id=<?php echo $row['id_contrato']; ?>" target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> 
                                                    Imprimir Contrato
                                                </a>

                                                <?php if (!$es_cancelado): ?>
                                                <a class="dropdown-item" href="../cobranza/registrar.php?id_contrato=<?php echo $row['id_contrato']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg> 
                                                    Registrar Abono
                                                </a>
                                                
                                                <div class="dropdown-divider"></div>
                                                
                                                <a class="dropdown-item text-warning" href="../servicios/asignar.php?id_contrato=<?php echo $row['id_contrato']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-activity"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg> 
                                                    <strong>Activar Servicio</strong>
                                                </a>
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

    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        $("#tabla-contratos").DataTable({
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": {
                "oPaginate": { "sPrevious": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-arrow-left\'><line x1=\'19\' y1=\'12\' x2=\'5\' y2=\'12\'></line><polyline points=\'12 19 5 12 12 5\'></polyline></svg>", "sNext": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-arrow-right\'><line x1=\'5\' y1=\'12\' x2=\'19\' y2=\'12\'></line><polyline points=\'12 5 19 12 12 19\'></polyline></svg>" },
                "sInfo": "Mostrando página _PAGE_ de _PAGES_",
                "sSearch": "<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'feather feather-search\'><circle cx=\'11\' cy=\'11\' r=\'8\'></circle><line x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'></line></svg>",
                "sSearchPlaceholder": "Buscar folio, cliente...",
                "sLengthMenu": "Resultados :  _MENU_",
            },
            "stripeClasses": [],
            "lengthMenu": [10, 20, 50, 100],
            "pageLength": 10,
            "order": [[ 0, "desc" ]] // Ordenar por Folio descendente
        });
    </script>
    ';
    
    include '../../includes/footer.php'; 
    ?>
</div>