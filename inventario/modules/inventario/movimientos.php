<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

// 2. CONSULTAS DE HISTORIAL

// TAB 1: SALIDAS DE CONSUMIBLES (Vales)
$sql_salidas = "SELECT v.id_vale, v.fecha, v.responsable, v.solicitante,
                       a.articulo, a.marca, av.cantidad
                FROM vales_salida v
                JOIN articulos_vale_salida av ON v.id_vale = av.id_vale
                JOIN articulos a ON av.id = a.id
                ORDER BY v.fecha DESC LIMIT 500";
$res_salidas = mysqli_query($conexion, $sql_salidas);

// TAB 2: MOVIMIENTOS DE ATAÚDES (Rentas/Asignaciones)
$sql_cajas = "SELECT r.id_renta, r.codigo, r.fecha_salida, r.fecha_devolucion, r.estado_devolucion,
                     c.modelo, c.color,
                     s.folio, u.usuario as usuario_responsable
              FROM renta_cajas r
              JOIN cajas c ON r.codigo = c.codigo
              LEFT JOIN servicios s ON r.id_servicio = s.id_servicio
              LEFT JOIN usuarios u ON r.creado_por = u.id
              ORDER BY r.fecha_salida DESC LIMIT 500";
$res_cajas = mysqli_query($conexion, $sql_cajas);

// TAB 3: ENTRADAS / COMPRAS
$sql_compras = "SELECT * FROM compra_articulos ORDER BY created_at DESC LIMIT 500";
$res_compras = mysqli_query($conexion, $sql_compras);

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
                <div class="breadcrumbs-container" data-page-heading="Analytics">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title"><h3>Kardex de Movimientos</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">
                    
                    <div class="widget-content widget-content-area br-8">
                        
                        <div class="simple-pill">
                            <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="pills-consumibles-tab" data-bs-toggle="pill" data-bs-target="#pills-consumibles" type="button" role="tab" aria-selected="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-cart"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                        Salidas (Consumibles)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="pills-ataudes-tab" data-bs-toggle="pill" data-bs-target="#pills-ataudes" type="button" role="tab" aria-selected="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-box"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                        Movimientos Ataúdes
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="pills-compras-tab" data-bs-toggle="pill" data-bs-target="#pills-compras" type="button" role="tab" aria-selected="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                        Entradas / Compras
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="pills-tabContent">
                                
                                <div class="tab-pane fade show active" id="pills-consumibles" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="tabla-consumibles" class="table dt-table-hover" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>Vale #</th>
                                                    <th>Fecha/Hora</th>
                                                    <th>Artículo</th>
                                                    <th>Cant.</th>
                                                    <th>Solicitante / Destino</th>
                                                    <th>Responsable</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = mysqli_fetch_assoc($res_salidas)): ?>
                                                <tr>
                                                    <td><span class="badge badge-light-dark">#<?php echo $row['id_vale']; ?></span></td>
                                                    <td><?php echo date('d/m H:i', strtotime($row['fecha'])); ?></td>
                                                    <td>
                                                        <span class="fw-bold"><?php echo $row['articulo']; ?></span><br>
                                                        <small class="text-muted"><?php echo $row['marca']; ?></small>
                                                    </td>
                                                    <td class="text-danger fw-bold">- <?php echo $row['cantidad']; ?></td>
                                                    <td><?php echo $row['solicitante']; ?></td>
                                                    <td><?php echo $row['responsable']; ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-ataudes" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="tabla-ataudes" class="table dt-table-hover" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Modelo</th>
                                                    <th>Fecha Salida</th>
                                                    <th>Destino (Servicio)</th>
                                                    <th>Estatus Retorno</th>
                                                    <th>Fecha Retorno</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = mysqli_fetch_assoc($res_cajas)): 
                                                    // Determinar estado
                                                    $estado_badge = 'warning';
                                                    $estado_txt = 'En Uso';
                                                    
                                                    if (!empty($row['fecha_devolucion'])) {
                                                        if($row['estado_devolucion'] == 'baja') {
                                                            $estado_badge = 'dark'; 
                                                            $estado_txt = 'Consumido (Baja)';
                                                        } elseif($row['estado_devolucion'] == 'usable') {
                                                            $estado_badge = 'success';
                                                            $estado_txt = 'Retornado (OK)';
                                                        } else {
                                                            $estado_badge = 'danger';
                                                            $estado_txt = ucfirst($row['estado_devolucion']);
                                                        }
                                                    }
                                                ?>
                                                <tr>
                                                    <td><code><?php echo $row['codigo']; ?></code></td>
                                                    <td><?php echo $row['modelo']; ?> <br> <small><?php echo $row['color']; ?></small></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_salida'])); ?></td>
                                                    <td>
                                                        <?php if($row['folio']): ?>
                                                            <a href="../servicios/ver.php?folio=<?php echo $row['folio']; ?>" class="text-primary">#<?php echo $row['folio']; ?></a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Directo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge badge-<?php echo $estado_badge; ?>"><?php echo $estado_txt; ?></span></td>
                                                    <td>
                                                        <?php echo $row['fecha_devolucion'] ? date('d/m/Y H:i', strtotime($row['fecha_devolucion'])) : '--'; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-compras" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="tabla-compras" class="table dt-table-hover" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>ID Compra</th>
                                                    <th>Fecha</th>
                                                    <th>Artículo</th>
                                                    <th>Marca</th>
                                                    <th>Cant. Ingresada</th>
                                                    <th>Costo Unit.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = mysqli_fetch_assoc($res_compras)): ?>
                                                <tr>
                                                    <td><?php echo $row['id_compra']; ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                                    <td class="fw-bold"><?php echo $row['articulo']; ?></td>
                                                    <td><?php echo $row['marca']; ?></td>
                                                    <td class="text-success fw-bold">+ <?php echo $row['cantidad']; ?></td>
                                                    <td>$<?php echo number_format($row['costo'], 2); ?></td>
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
            </div>

        </div>
    </div>

    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/table/datatable/datatables.js"></script>
    <script>
        // Configuración común para las tablas
        var dtConfig = {
            "dom": "<\"dt--top-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"l><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3\"f>>>" +
            "<\"table-responsive\"tr>" +
            "<\"dt--bottom-section\"<\"row\"<\"col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center\"i><\"col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center\"p>>>",
            "oLanguage": { "oPaginate": { "sPrevious": "<", "sNext": ">" }, "sSearch": "Buscar:", "sLengthMenu": "Ver _MENU_" },
            "stripeClasses": [],
            "lengthMenu": [10, 25, 50],
            "pageLength": 10,
            "order": [[ 1, "desc" ]] // Ordenar por fecha desc
        };

        $("#tabla-consumibles").DataTable(dtConfig);
        $("#tabla-ataudes").DataTable(dtConfig);
        $("#tabla-compras").DataTable(dtConfig);
    </script>
    ';
    include '../../includes/footer.php'; 
    ?>
</div>