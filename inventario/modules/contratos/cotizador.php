<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

// CONFIGURACIÓN DE NEGOCIO
$FACTOR_UTILIDAD = 2.5; // El precio de venta será el Costo de Almacén x 2.5
$TASA_INTERES = 0.10;   // 10% de incremento si es a crédito

// 2. CONSULTA DE INVENTARIO (ATAÚDES DISPONIBLES)
$sql_cajas = "SELECT codigo, modelo, color, costo, reciclado 
              FROM cajas 
              WHERE estatus_logico = 'disponible' AND eliminado = 0 
              ORDER BY costo ASC";
$res_cajas = mysqli_query($conexion, $sql_cajas);

// 3. UI
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<style>
    @media print {
        .sidebar-wrapper, .header-container, .secondary-nav, .btn-no-print, .footer-wrapper { 
            display: none !important; 
        }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .layout-px-spacing { padding: 0 !important; }
        .card-cotizacion { border: 1px solid #000 !important; box-shadow: none !important; }
        body { background: #fff !important; color: #000 !important; }
        .input-group-text { background: #fff; border: none; font-weight: bold; }
        input, select { border: none !important; background: transparent !important; padding: 0 !important; height: auto !important; }
    }
    
    .card-option { cursor: pointer; transition: all 0.3s; border: 2px solid transparent; }
    .card-option:hover { transform: translateY(-3px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .card-option.selected { border-color: #4361ee; background-color: #eaf1ff; }
</style>

<div id="content" class="main-content">
    <div class="layout-px-spacing">

        <div class="middle-content container-xxl p-0">
            
            <div class="secondary-nav btn-no-print">
                <div class="breadcrumbs-container" data-page-heading="Sales">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title"><h3>Cotizador de Servicios</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                
                <div class="col-xl-7 col-lg-7 col-md-12 col-12 layout-spacing btn-no-print">
                    <form id="formCotizador">
                        
                        <div class="widget widget-content-area br-4 mb-3">
                            <h5 class="text-primary mb-3">1. Servicio Básico</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tipo de Plan</label>
                                        <select id="tipo_servicio" class="form-control" onchange="calcularTotal()">
                                            <option value="12000">Inhumación (Sepultura) - $12,000 Base</option>
                                            <option value="14500">Cremación Directa - $14,500 Base</option>
                                            <option value="18000">Cremación con Velación - $18,000 Base</option>
                                            <option value="8000">Solo Traslado - $8,000 Base</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Servicios Adicionales</label>
                                        <div class="n-chk">
                                            <label class="new-control new-checkbox checkbox-primary">
                                              <input type="checkbox" class="new-control-input extra-item" value="2500" onchange="calcularTotal()">
                                              <span class="new-control-indicator"></span>Cafetería Premium (+$2,500)
                                            </label>
                                        </div>
                                        <div class="n-chk">
                                            <label class="new-control new-checkbox checkbox-primary">
                                              <input type="checkbox" class="new-control-input extra-item" value="1800" onchange="calcularTotal()">
                                              <span class="new-control-indicator"></span>Carroza de Lujo (+$1,800)
                                            </label>
                                        </div>
                                        <div class="n-chk">
                                            <label class="new-control new-checkbox checkbox-primary">
                                              <input type="checkbox" class="new-control-input extra-item" value="3000" onchange="calcularTotal()">
                                              <span class="new-control-indicator"></span>Gestoría de Trámites (+$3,000)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="widget widget-content-area br-4 mb-3">
                            <h5 class="text-primary mb-3">2. Selección de Ataúd / Urna</h5>
                            <p class="text-muted small">Seleccione una opción del inventario disponible.</p>
                            
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Seleccionar</th>
                                            <th>Modelo</th>
                                            <th>Precio Venta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="card-option selected" onclick="selectCaja(this, 0, 'Sin Ataúd (O incluido en base)')">
                                            <td class="text-center"><input type="radio" name="caja_opt" checked></td>
                                            <td><strong>Sin Ataúd Extra</strong></td>
                                            <td>$0.00</td>
                                        </tr>
                                        <?php while($caja = mysqli_fetch_assoc($res_cajas)): 
                                            // Lógica de Precio: Costo * Factor
                                            $precio_venta = $caja['costo'] * $FACTOR_UTILIDAD;
                                            $nombre_full = $caja['modelo'] . " - " . $caja['color'];
                                        ?>
                                        <tr class="card-option" onclick="selectCaja(this, <?php echo $precio_venta; ?>, '<?php echo $nombre_full; ?>')">
                                            <td class="text-center"><input type="radio" name="caja_opt"></td>
                                            <td>
                                                <strong><?php echo $caja['modelo']; ?></strong><br>
                                                <small><?php echo $caja['color']; ?></small>
                                            </td>
                                            <td class="text-success fw-bold">
                                                $<?php echo number_format($precio_venta, 2); ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </form>
                </div>

                <div class="col-xl-5 col-lg-5 col-md-12 col-12 layout-spacing">
                    
                    <div class="widget widget-content-area br-4 card-cotizacion h-100 bg-white">
                        <div class="text-center mb-4 pt-3">
                            <img src="https://urena.control.mx/img/logo.png" style="height: 50px;" alt="Logo">
                            <h4 class="mt-2">Cotización de Servicio</h4>
                            <p class="text-muted">Folio: PRE-<?php echo rand(1000,9999); ?> | Fecha: <?php echo date('d/m/Y'); ?></p>
                        </div>

                        <div class="px-3">
                            <div class="row mb-2">
                                <div class="col-8">Servicio Base:</div>
                                <div class="col-4 text-end fw-bold" id="lblBase">$0.00</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-8">Ataúd Seleccionado:<br><small class="text-muted" id="lblCajaNombre">Ninguno</small></div>
                                <div class="col-4 text-end fw-bold" id="lblCajaPrecio">$0.00</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-8">Extras / Adicionales:</div>
                                <div class="col-4 text-end fw-bold" id="lblExtras">$0.00</div>
                            </div>
                            
                            <hr>
                            
                            <div class="row mb-3">
                                <div class="col-6"><h5>PRECIO CONTADO:</h5></div>
                                <div class="col-6 text-end"><h4 class="text-success" id="lblTotalContado">$0.00</h4></div>
                            </div>

                            <div class="bg-light p-3 rounded mb-3">
                                <h6 class="text-primary fw-bold">PLAN A CRÉDITO (<?php echo $TASA_INTERES * 100; ?>% Interés)</h6>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Precio Lista:</span>
                                    <span id="lblTotalCredito" class="fw-bold">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Enganche (20%):</span>
                                    <span id="lblEnganche" class="text-danger fw-bold">$0.00</span>
                                </div>
                                
                                <hr class="my-2">
                                
                                <p class="mb-1 small text-muted">Mensualidades sugeridas (Saldo Restante):</p>
                                <ul class="list-unstyled">
                                    <li class="d-flex justify-content-between">
                                        <span>3 Meses:</span>
                                        <strong id="lblMes3">$0.00</strong>
                                    </li>
                                    <li class="d-flex justify-content-between">
                                        <span>6 Meses:</span>
                                        <strong id="lblMes6">$0.00</strong>
                                    </li>
                                    <li class="d-flex justify-content-between">
                                        <span>12 Meses:</span>
                                        <strong id="lblMes12">$0.00</strong>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="mt-4 text-center btn-no-print">
                                <button class="btn btn-dark w-100 mb-2" onclick="window.print()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    Imprimir Cotización
                                </button>
                                <a href="crear.php" class="btn btn-success w-100">
                                    Convertir en Contrato
                                </a>
                            </div>

                            <p class="text-muted text-center mt-3 small">Precios sujetos a cambio sin previo aviso. Esta cotización tiene una vigencia de 5 días.</p>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php 
    $extra_scripts = '
    <script>
        // Variables globales
        let precioCaja = 0;
        const TASA_INTERES = '.$TASA_INTERES.';

        function formatMoney(amount) {
            return "$" + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, "$&,");
        }

        function selectCaja(row, precio, nombre) {
            // Estilos visuales de selección
            document.querySelectorAll(".card-option").forEach(el => {
                el.classList.remove("selected");
                el.querySelector("input").checked = false;
            });
            row.classList.add("selected");
            row.querySelector("input").checked = true;

            // Actualizar datos
            precioCaja = parseFloat(precio);
            document.getElementById("lblCajaNombre").innerText = nombre;
            document.getElementById("lblCajaPrecio").innerText = formatMoney(precioCaja);
            
            calcularTotal();
        }

        function calcularTotal() {
            // 1. Obtener Base
            let base = parseFloat(document.getElementById("tipo_servicio").value) || 0;
            document.getElementById("lblBase").innerText = formatMoney(base);

            // 2. Obtener Extras
            let extras = 0;
            document.querySelectorAll(".extra-item:checked").forEach(chk => {
                extras += parseFloat(chk.value);
            });
            document.getElementById("lblExtras").innerText = formatMoney(extras);

            // 3. Totales Contado
            let totalContado = base + precioCaja + extras;
            document.getElementById("lblTotalContado").innerText = formatMoney(totalContado);

            // 4. Totales Crédito
            let totalCredito = totalContado * (1 + TASA_INTERES);
            document.getElementById("lblTotalCredito").innerText = formatMoney(totalCredito);

            // 5. Enganche (20% del total crédito)
            let enganche = totalCredito * 0.20;
            document.getElementById("lblEnganche").innerText = formatMoney(enganche);

            // 6. Amortización
            let saldoRestante = totalCredito - enganche;
            document.getElementById("lblMes3").innerText = formatMoney(saldoRestante / 3);
            document.getElementById("lblMes6").innerText = formatMoney(saldoRestante / 6);
            document.getElementById("lblMes12").innerText = formatMoney(saldoRestante / 12);
        }

        // Ejecutar al inicio
        window.addEventListener("load", function() {
            calcularTotal();
        });
    </script>
    ';
    include '../../includes/footer.php'; 
    ?>
</div>