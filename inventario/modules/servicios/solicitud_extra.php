<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";

// 2. PROCESAR SOLICITUD (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_servicio = intval($_POST['id_servicio']);
    $id_articulo = intval($_POST['id_articulo']);
    $cantidad    = intval($_POST['cantidad']);
    
    if ($id_servicio > 0 && $id_articulo > 0 && $cantidad > 0) {
        
        mysqli_begin_transaction($conexion);
        
        try {
            // A. Obtener datos del servicio (Para poner en "Solicitante")
            $q_serv = mysqli_query($conexion, "SELECT folio, responsable FROM servicios WHERE id_servicio = $id_servicio");
            $d_serv = mysqli_fetch_assoc($q_serv);
            $folio_servicio = $d_serv['folio'];
            
            // B. Verificar Stock
            $q_art = mysqli_query($conexion, "SELECT articulo, existencias FROM articulos WHERE id = $id_articulo FOR UPDATE");
            $d_art = mysqli_fetch_assoc($q_art);
            
            if ($d_art['existencias'] < $cantidad) {
                throw new Exception("Stock insuficiente. El artículo '{$d_art['articulo']}' solo tiene {$d_art['existencias']} unidades.");
            }
            
            // C. Generar Vale de Salida (Encabezado)
            $responsable_usuario = $_SESSION['usuario_nombre'] ?? 'Sistema';
            // En el campo solicitante guardamos la referencia del servicio
            $solicitante_txt = "Servicio #$folio_servicio ({$d_serv['responsable']})";
            
            $sql_vale = "INSERT INTO vales_salida (responsable, solicitante, fecha) 
                         VALUES ('$responsable_usuario', '$solicitante_txt', NOW())";
            
            if (!mysqli_query($conexion, $sql_vale)) throw new Exception("Error al crear vale.");
            $id_vale = mysqli_insert_id($conexion);
            
            // D. Insertar Detalle del Vale
            // Nota: En tu tabla 'articulos_vale_salida', el campo que relaciona al articulo se llama 'id'
            $sql_det = "INSERT INTO articulos_vale_salida (id, cantidad, id_vale, fecha) 
                        VALUES ($id_articulo, $cantidad, $id_vale, NOW())";
            mysqli_query($conexion, $sql_det);
            
            // E. Descontar Inventario
            $sql_update = "UPDATE articulos SET existencias = existencias - $cantidad WHERE id = $id_articulo";
            mysqli_query($conexion, $sql_update);
            
            // F. Registrar en Notas del Servicio (Opcional, para trazabilidad)
            $nota = "\n[".date('d/m H:i')."]: Salida Almacén - $cantidad x {$d_art['articulo']} (Vale #$id_vale)";
            mysqli_query($conexion, "UPDATE servicios SET notas = CONCAT(IFNULL(notas,''), '$nota') WHERE id_servicio = $id_servicio");
            
            mysqli_commit($conexion);
            $mensaje = "Salida registrada con éxito. Vale #$id_vale generado.";
            $tipo_msg = "success";
            
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error: " . $e->getMessage();
            $tipo_msg = "danger";
        }
    } else {
        $mensaje = "Por favor complete todos los campos requeridos.";
        $tipo_msg = "warning";
    }
}

// 3. CONSULTAS PARA LLENAR SELECTS
// Servicios Activos (Para saber a dónde se va el material)
$sql_servicios = "SELECT s.id_servicio, s.folio, f.nom_fallecido 
                  FROM servicios s 
                  JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
                  JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
                  WHERE s.estatus IN ('abierto', 'en_curso') AND s.eliminado = 0";
$res_servicios = mysqli_query($conexion, $sql_servicios);

// Artículos con Stock
$sql_articulos = "SELECT id, articulo, marca, existencias FROM articulos WHERE existencias > 0 AND eliminado = 0 ORDER BY articulo ASC";
$res_articulos = mysqli_query($conexion, $sql_articulos);

// Historial Reciente (Últimos 5 movimientos)
$sql_historial = "SELECT v.id_vale, v.solicitante, v.fecha, av.cantidad, a.articulo
                  FROM vales_salida v
                  JOIN articulos_vale_salida av ON v.id_vale = av.id_vale
                  JOIN articulos a ON av.id = a.id
                  ORDER BY v.id_vale DESC LIMIT 5";
$res_historial = mysqli_query($conexion, $sql_historial);

// 4. UI
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
                                <div class="page-title"><h3>Solicitud de Insumos Extra</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                
                <div class="col-xl-6 col-lg-12 col-md-12 col-12 layout-spacing">
                    
                    <?php if($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> mb-4"><?php echo $mensaje; ?></div>
                    <?php endif; ?>

                    <div class="widget widget-content-area br-4">
                        <div class="widget-header">
                            <h4>Registrar Salida de Almacén</h4>
                            <p class="text-muted">Seleccione el servicio activo al que se cargarán los insumos.</p>
                        </div>
                        
                        <form method="POST" action="">
                            
                            <div class="form-group mb-4">
                                <label>Servicio Destino *</label>
                                <select name="id_servicio" class="form-control" required>
                                    <option value="">-- Seleccione Servicio --</option>
                                    <?php while($serv = mysqli_fetch_assoc($res_servicios)): ?>
                                        <option value="<?php echo $serv['id_servicio']; ?>">
                                            <?php echo "#" . $serv['folio'] . " - " . $serv['nom_fallecido']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-row mb-4">
                                <div class="form-group col-md-8">
                                    <label>Artículo *</label>
                                    <select name="id_articulo" class="form-control" required>
                                        <option value="">-- Seleccione Artículo --</option>
                                        <?php while($art = mysqli_fetch_assoc($res_articulos)): ?>
                                            <option value="<?php echo $art['id']; ?>">
                                                <?php echo $art['articulo'] . " (" . $art['marca'] . ") - Stock: " . $art['existencias']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Cantidad *</label>
                                    <input type="number" name="cantidad" class="form-control" min="1" value="1" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-save"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                Generar Vale de Salida
                            </button>

                        </form>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-12 col-md-12 col-12 layout-spacing">
                    <div class="widget widget-content-area br-4">
                        <div class="widget-header">
                            <h4>Últimas Salidas Registradas</h4>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped mb-4">
                                <thead>
                                    <tr>
                                        <th>Vale</th>
                                        <th>Artículo</th>
                                        <th>Cant.</th>
                                        <th>Destino / Solicitante</th>
                                        <th>Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if(mysqli_num_rows($res_historial) > 0) {
                                        while($hist = mysqli_fetch_assoc($res_historial)): 
                                    ?>
                                    <tr>
                                        <td><span class="badge badge-info">#<?php echo $hist['id_vale']; ?></span></td>
                                        <td><?php echo $hist['articulo']; ?></td>
                                        <td class="text-center fw-bold"><?php echo $hist['cantidad']; ?></td>
                                        <td><small><?php echo substr($hist['solicitante'], 0, 20); ?>...</small></td>
                                        <td><?php echo date('H:i', strtotime($hist['fecha'])); ?></td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>No hay movimientos recientes</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center">
                            <a href="../inventario/movimientos.php" class="btn btn-outline-dark btn-sm">Ver Historial Completo</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</div>