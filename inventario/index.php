<?php
// 1. LÓGICA PHP (Conexión y Consultas)
// ---------------------------------------------------------
require_once 'config/db.php';       // Asegúrate que db.php define BASE_URL y $conexion
require_once 'config/global.php';   // Funciones helper (formato_moneda, etc)
require_once 'includes/auth_check.php'; // Validar sesión

// --- A. CONSULTAS PARA WIDGETS ---

// 1. Totales Generales (Para Widget Summary)
// Ingresos (Suma de abonos)
$sql_ingresos = "SELECT SUM(cant_abono) as total FROM futuro_abonos";
$row_ing = mysqli_fetch_assoc(mysqli_query($conexion, $sql_ingresos));
$ingresos_totales = $row_ing['total'] ?? 0;

// Egresos (De la tabla futuro_ingresos_egresos)
$sql_egresos = "SELECT SUM(egresos) as total FROM futuro_ingresos_egresos";
$row_egr = mysqli_fetch_assoc(mysqli_query($conexion, $sql_egresos));
$egresos_totales = $row_egr['total'] ?? 0;

// Beneficio (Profit)
$profit = $ingresos_totales - $egresos_totales;

// 2. Contratos (Orders)
$sql_orders_count = "SELECT COUNT(*) as total FROM futuro_contratos WHERE estatus != 'cancelado'";
$row_orders = mysqli_fetch_assoc(mysqli_query($conexion, $sql_orders_count));
$total_orders = $row_orders['total'];

// 3. Últimos Pagos (Transactions Widget)
$sql_transacciones = "SELECT fa.cant_abono, fa.fecha_registro, t.nombre, t.apellido_p 
                      FROM futuro_abonos fa
                      JOIN futuro_contratos fc ON fa.id_contrato = fc.id_contrato
                      JOIN titular_contrato tc ON fc.id_contrato = tc.id_contrato
                      JOIN titulares t ON tc.id_titular = t.id_titular
                      ORDER BY fa.fecha_registro DESC LIMIT 5";
$res_transacciones = mysqli_query($conexion, $sql_transacciones);

// 4. Últimos Contratos (Recent Orders Widget)
$sql_recent_orders = "SELECT fc.id_contrato, fc.costo_final, fc.estatus, t.nombre, t.apellido_p, fc.tipo_contrato
                      FROM futuro_contratos fc
                      JOIN titular_contrato tc ON fc.id_contrato = tc.id_contrato
                      JOIN titulares t ON tc.id_titular = t.id_titular
                      ORDER BY fc.fecha_registro DESC LIMIT 6";
$res_recent_orders = mysqli_query($conexion, $sql_recent_orders);

// 5. Top Selling (Agrupado por tipo de servicio)
$sql_top_selling = "SELECT tipo_contrato, COUNT(*) as cantidad, SUM(costo_final) as total_venta 
                    FROM futuro_contratos 
                    WHERE estatus != 'cancelado' 
                    GROUP BY tipo_contrato 
                    ORDER BY cantidad DESC LIMIT 5";
$res_top_selling = mysqli_query($conexion, $sql_top_selling);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    <title><?php echo APP_NAME; ?> - Panel Principal </title>
    
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>src/assets/img/favicon.ico"/>
    
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/light/loader.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/dark/loader.css" rel="stylesheet" type="text/css" />
    <script src="<?php echo BASE_URL; ?>layouts/vertical-light-menu/loader.js"></script>
    
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>src/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/light/plugins.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo BASE_URL; ?>layouts/vertical-light-menu/css/dark/plugins.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo BASE_URL; ?>src/plugins/src/apex/apexcharts.css" rel="stylesheet" type="text/css">
    <link href="<?php echo BASE_URL; ?>src/assets/css/light/components/list-group.css" rel="stylesheet" type="text/css">
    <link href="<?php echo BASE_URL; ?>src/assets/css/light/dashboard/dash_2.css" rel="stylesheet" type="text/css" />

    <link href="<?php echo BASE_URL; ?>src/assets/css/dark/components/list-group.css" rel="stylesheet" type="text/css">
    <link href="<?php echo BASE_URL; ?>src/assets/css/dark/dashboard/dash_2.css" rel="stylesheet" type="text/css" />
    </head>
<body class="layout-boxed">
    
    <div id="load_screen"> <div class="loader"> <div class="loader-content">
        <div class="spinner-grow align-self-center"></div>
    </div></div></div>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-container" id="container">

        <div class="overlay"></div>
        <div class="search-overlay"></div>

        <?php include 'includes/sidebar.php'; ?>
        
        <div id="content" class="main-content">
            <div class="layout-px-spacing">

                <div class="middle-content container-xxl p-0">

                    <div class="secondary-nav">
                        <div class="breadcrumbs-container" data-page-heading="Sales">
                            <header class="header navbar navbar-expand-sm">
                                <a href="javascript:void(0);" class="btn-toggle sidebarCollapse" data-placement="bottom">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-menu"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                                </a>
                                <div class="d-flex breadcrumb-content">
                                    <div class="page-header">
                                        <div class="page-title">
                                            <h3>Dashboard Funeraria</h3>
                                        </div>
                                    </div>
                                </div>
                            </header>
                        </div>
                    </div>
                    <div class="row layout-top-spacing">

                        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6 col-12 layout-spacing">
                            <div class="widget widget-three">
                                <div class="widget-heading">
                                    <h5 class="">Resumen Financiero</h5>
                                </div>
                                <div class="widget-content">
                                    <div class="order-summary">
                                        
                                        <div class="summary-list">
                                            <div class="w-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-bag"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                            </div>
                                            <div class="w-summary-details">
                                                <div class="w-summary-info">
                                                    <h6>Ingresos (Cobranza)</h6>
                                                    <p class="summary-count"><?php echo formato_moneda($ingresos_totales); ?></p>
                                                </div>
                                                <div class="w-summary-stats">
                                                    <div class="progress">
                                                        <div class="progress-bar bg-gradient-secondary" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="summary-list">
                                            <div class="w-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-tag"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7" y2="7"></line></svg>
                                            </div>
                                            <div class="w-summary-details">
                                                <div class="w-summary-info">
                                                    <h6>Balance (Profit)</h6>
                                                    <p class="summary-count"><?php echo formato_moneda($profit); ?></p>
                                                </div>
                                                <div class="w-summary-stats">
                                                    <div class="progress">
                                                        <div class="progress-bar bg-gradient-success" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="summary-list">
                                            <div class="w-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-credit-card"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                            </div>
                                            <div class="w-summary-details">
                                                <div class="w-summary-info">
                                                    <h6>Gastos / Egresos</h6>
                                                    <p class="summary-count"><?php echo formato_moneda($egresos_totales); ?></p>
                                                </div>
                                                <div class="w-summary-stats">
                                                    <div class="progress">
                                                        <div class="progress-bar bg-gradient-warning" role="progressbar" style="width: 80%" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 col-12 layout-spacing">
                            <div class="widget widget-one widget">
                                <div class="widget-content">
                                    <div class="w-numeric-value">
                                        <div class="w-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-cart"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                        </div>
                                        <div class="w-content">
                                            <span class="w-value"><?php echo $total_orders; ?></span>
                                            <span class="w-numeric-title">Contratos Totales</span>
                                        </div>
                                    </div>
                                    <div class="w-chart">
                                        <div id="total-orders"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-12 col-md-12 col-sm-12 col-12 layout-spacing">
                            <div class="widget widget-chart-two">
                                <div class="widget-heading">
                                    <h5 class="">Tendencia de Ventas</h5>
                                </div>
                                <div class="widget-content">
                                    <div id="chart-2" class=""></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 col-12 layout-spacing">
                            <div class="widget widget-table-one">
                                <div class="widget-heading">
                                    <h5 class="">Últimos Pagos</h5>
                                </div>
                                <div class="widget-content">
                                    <?php while($row = mysqli_fetch_assoc($res_transacciones)): ?>
                                    <div class="transactions-list">
                                        <div class="t-item">
                                            <div class="t-company-name">
                                                <div class="t-icon">
                                                    <div class="icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                                    </div>
                                                </div>
                                                <div class="t-name">
                                                    <h4><?php echo $row['nombre'] . ' ' . $row['apellido_p']; ?></h4>
                                                    <p class="meta-date"><?php echo fecha_es($row['fecha_registro']); ?></p>
                                                </div>
                                            </div>
                                            <div class="t-rate rate-inc">
                                                <p><span>+<?php echo formato_moneda($row['cant_abono']); ?></span></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12 layout-spacing">
                            <div class="widget widget-table-two">
                                <div class="widget-heading">
                                    <h5 class="">Últimos Contratos Registrados</h5>
                                </div>
                                <div class="widget-content">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th><div class="th-content">Titular</div></th>
                                                    <th><div class="th-content">Servicio</div></th>
                                                    <th><div class="th-content">Folio</div></th>
                                                    <th><div class="th-content th-heading">Precio</div></th>
                                                    <th><div class="th-content">Estatus</div></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($order = mysqli_fetch_assoc($res_recent_orders)): ?>
                                                <tr>
                                                    <td><div class="td-content customer-name"><span><?php echo $order['nombre'].' '.$order['apellido_p']; ?></span></div></td>
                                                    <td><div class="td-content product-brand text-primary"><?php echo $order['tipo_contrato']; ?></div></td>
                                                    <td><div class="td-content product-invoice">#<?php echo $order['id_contrato']; ?></div></td>
                                                    <td><div class="td-content pricing"><span class=""><?php echo formato_moneda($order['costo_final']); ?></span></div></td>
                                                    <td><div class="td-content"><?php echo badge_estatus($order['estatus']); ?></div></td>
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
            
            <div class="footer-wrapper">
                <div class="footer-section f-section-1">
                    <p class="">Copyright © <span class="dynamic-year"><?php echo date('Y'); ?></span> <a target="_blank" href="#"><?php echo APP_NAME; ?></a>, All rights reserved.</p>
                </div>
            </div>
            </div>
        </div>
    <script src="<?php echo BASE_URL; ?>src/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/plugins/src/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/plugins/src/mousetrap/mousetrap.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/plugins/src/waves/waves.min.js"></script>
    <script src="<?php echo BASE_URL; ?>layouts/vertical-light-menu/app.js"></script>
    <script src="<?php echo BASE_URL; ?>src/plugins/src/apex/apexcharts.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/assets/js/dashboard/dash_2.js"></script>
    </body>
</html>