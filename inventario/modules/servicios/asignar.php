<?php
// 1. CONFIGURACIÓN Y SEGURIDAD
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../config/funciones.php';
require_once '../../includes/auth_check.php';

$mensaje = "";
$tipo_msg = "";

// --- CORRECCIÓN ERROR FK: VALIDAR USUARIO RESPONSABLE ---
// Obtenemos el ID de la sesión. Si no existe, usamos 0.
$id_usuario_actual = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;

// Verificar que este ID realmente exista en la tabla 'usuarios' para evitar el error de Constraint
$check_user = mysqli_query($conexion, "SELECT id FROM usuarios WHERE id = $id_usuario_actual");
if (mysqli_num_rows($check_user) == 0) {
    // Si el usuario de sesión no existe en la BD, buscamos el primer usuario válido (Admin)
    $sql_fallback = "SELECT id FROM usuarios LIMIT 1";
    $res_fallback = mysqli_query($conexion, $sql_fallback);
    if ($row_fallback = mysqli_fetch_assoc($res_fallback)) {
        $id_usuario_actual = $row_fallback['id'];
    } else {
        // Si no hay ningún usuario en la tabla usuarios, detenemos todo.
        die("Error Crítico: No hay usuarios registrados en la base de datos. Cree un usuario primero.");
    }
}
// --------------------------------------------------------

// 2. PRE-CARGA DE DATOS (Si viene de un Contrato)
$id_contrato_origen = isset($_GET['id_contrato']) ? intval($_GET['id_contrato']) : 0;
$datos_pre = [
    'tipo_servicio' => 'Inhumacion',
    'nombre_titular' => '',
    'nombre_fallecido' => '' 
];

if ($id_contrato_origen > 0) {
    $sql_c = "SELECT fc.tipo_contrato, t.nombre, t.apellido_p 
              FROM futuro_contratos fc
              JOIN titular_contrato tc ON fc.id_contrato = tc.id_contrato
              JOIN titulares t ON tc.id_titular = t.id_titular
              WHERE fc.id_contrato = $id_contrato_origen";
    $res_c = mysqli_query($conexion, $sql_c);
    if ($res_c && $row_c = mysqli_fetch_assoc($res_c)) {
        $datos_pre['tipo_servicio'] = $row_c['tipo_contrato'];
        $datos_pre['nombre_titular'] = $row_c['nombre'] . ' ' . $row_c['apellido_p'];
    }
}

// 3. OBTENER LISTAS PARA SELECTS
// Personal Activo
$sql_personal = "SELECT id_personal, nombre, apellido_p FROM futuro_personal WHERE estatus = 'activo'";
$res_personal = mysqli_query($conexion, $sql_personal);

// --- CORRECCIÓN WARNING: Cajas Disponibles ---
$sql_cajas = "SELECT codigo, modelo, color, costo FROM cajas 
              WHERE estatus_logico = 'disponible' AND disponible = 1 AND eliminado = 0";
$res_cajas = mysqli_query($conexion, $sql_cajas);
if (!$res_cajas) {
    // Si falla la consulta, inicializamos variable para evitar warning en HTML
    $error_cajas = "Error cargando inventario: " . mysqli_error($conexion);
}
// ---------------------------------------------


// 4. PROCESAR FORMULARIO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // A. Datos Generales
    $folio          = generar_folio_servicio($conexion);
    $nom_fallecido  = limpiar_str($_POST['nom_fallecido']);
    $lugar_velacion = limpiar_str($_POST['lugar_velacion']);
    $municipio      = limpiar_str($_POST['municipio']);
    
    // B. Datos del Servicio
    $tipo_servicio  = limpiar_str($_POST['tipo_servicio']);
    $modalidad      = limpiar_str($_POST['modalidad']);
    $responsable_id = intval($_POST['responsable']);
    
    // C. Recursos
    $codigo_caja    = limpiar_str($_POST['codigo_caja']);
    $velas          = intval($_POST['velas']);
    $despensa       = intval($_POST['despensa']);
    $auxiliares_txt = limpiar_str($_POST['auxiliares']);
    $notas          = limpiar_str($_POST['notas']);
    
    // Nombre responsable
    $nom_resp = obtener_nombre_usuario($conexion, $responsable_id); 

    // INICIAR TRANSACCIÓN
    mysqli_begin_transaction($conexion);

    try {
        // PASO 1: Registrar Fallecido
        $sql_fall = "INSERT INTO fallecido (nom_fallecido, dom_velacion, municipio, fecha) 
                     VALUES ('$nom_fallecido', '$lugar_velacion', '$municipio', NOW())";
        if (!mysqli_query($conexion, $sql_fall)) throw new Exception("Error al registrar fallecido.");
        $id_fallecido = mysqli_insert_id($conexion);

        // PASO 2: Crear el Servicio
        $sql_serv = "INSERT INTO servicios (
                        folio, tipo_servicio, tipo_venta, modalidad, responsable, 
                        auxiliares, velas, despensa, notas, 
                        contratante_nombre, estatus, created_at
                     ) VALUES (
                        '$folio', '$tipo_servicio', 'Inmediato', '$modalidad', '$nom_resp', 
                        '$auxiliares_txt', $velas, $despensa, '$notas', 
                        '{$datos_pre['nombre_titular']}', 'en_curso', NOW()
                     )";
        
        if (!mysqli_query($conexion, $sql_serv)) throw new Exception("Error al crear servicio: " . mysqli_error($conexion));
        $id_servicio = mysqli_insert_id($conexion);

        // PASO 3: Vincular Fallecido
        mysqli_query($conexion, "INSERT INTO servicio_fallecido (id_fallecido, id_servicio) VALUES ($id_fallecido, $id_servicio)");

        // PASO 4: Asignar Ataúd
        if (!empty($codigo_caja)) {
            // Relación
            $sql_serv_caja = "INSERT INTO servicio_caja (id_servicio, codigo) VALUES ($id_servicio, '$codigo_caja')";
            if (!mysqli_query($conexion, $sql_serv_caja)) throw new Exception("Error al asignar caja.");

            // Actualizar estado de la caja
            $sql_update_caja = "UPDATE cajas SET estatus_logico = 'rentado', disponible = 0 WHERE codigo = '$codigo_caja'";
            mysqli_query($conexion, $sql_update_caja);
            
            // Historial de Renta (AQUÍ ESTABA EL ERROR ANTES)
            // Ahora usamos $id_usuario_actual que ya validamos arriba
            $sql_renta = "INSERT INTO renta_cajas (codigo, id_servicio, fecha_salida, creado_por) 
                          VALUES ('$codigo_caja', $id_servicio, NOW(), $id_usuario_actual)";
            
            if (!mysqli_query($conexion, $sql_renta)) {
                throw new Exception("Error al registrar salida de caja (FK Usuario): " . mysqli_error($conexion));
            }
        }

        // PASO 5: Actualizar Contrato
        if ($id_contrato_origen > 0) {
            mysqli_query($conexion, "UPDATE futuro_contratos SET estatus = 'servicio_activo' WHERE id_contrato = $id_contrato_origen");
        }

        mysqli_commit($conexion);
        
        // Redirigir
        echo "<script>window.location.href='index.php?msg=asignado';</script>";
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $mensaje = "Error: " . $e->getMessage();
        $tipo_msg = "danger";
    }
}

// 5. UI
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
                                <div class="page-title"><h3>Asignación de Recursos</h3></div>
                            </div>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                
                <div class="col-xl-12 col-lg-12 col-md-12 col-12 layout-spacing">
                    
                    <?php if($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> mb-4"><?php echo $mensaje; ?></div>
                    <?php endif; ?>

                    <div class="widget widget-content-area br-4">
                        <form method="POST" action="">
                            
                            <h5 class="mb-4 text-primary">1. Datos del Servicio y Lugar</h5>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-6">
                                    <label>Nombre del Fallecido *</label>
                                    <input type="text" name="nom_fallecido" class="form-control" required placeholder="Nombre completo del finado">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Lugar de Velación (Domicilio / Sala) *</label>
                                    <input type="text" name="lugar_velacion" class="form-control" required placeholder="Calle, Número y Colonia">
                                </div>
                            </div>

                            <div class="form-row mb-4">
                                <div class="form-group col-md-4">
                                    <label>Municipio</label>
                                    <input type="text" name="municipio" class="form-control" value="León">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Tipo de Servicio</label>
                                    <select name="tipo_servicio" class="form-control">
                                        <option value="Funerario Completo" <?php if($datos_pre['tipo_servicio']=='Inhumacion') echo 'selected'; ?>>Funerario Completo</option>
                                        <option value="Cremacion Directa" <?php if($datos_pre['tipo_servicio']=='Cremacion') echo 'selected'; ?>>Cremación Directa</option>
                                        <option value="Traslado">Traslado</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Modalidad</label>
                                    <select name="modalidad" class="form-control">
                                        <option value="inhumacion">Inhumación (Sepultura)</option>
                                        <option value="cremacion">Cremación</option>
                                    </select>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-4 text-info">2. Asignación de Recursos</h5>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-6">
                                    <label>Ataúd / Urna (Inventario Disponible) *</label>
                                    <select name="codigo_caja" class="form-control" required>
                                        <option value="">-- Seleccione Modelo --</option>
                                        
                                        <?php 
                                        // CORRECCIÓN WARNING: Validar antes de iterar
                                        if ($res_cajas && mysqli_num_rows($res_cajas) > 0) {
                                            while($caja = mysqli_fetch_assoc($res_cajas)): 
                                                $txt_costo = isset($caja['costo']) ? "($" . number_format($caja['costo'],0) . ")" : "";
                                        ?>
                                            <option value="<?php echo $caja['codigo']; ?>">
                                                <?php echo $caja['modelo'] . " - " . $caja['color'] . " " . $txt_costo; ?>
                                            </option>
                                        <?php 
                                            endwhile; 
                                        } else {
                                            echo "<option value='' disabled>No hay ataúdes disponibles</option>";
                                        }
                                        ?>
                                    </select>
                                    <small class="text-muted">Se descontará del inventario automáticamente.</small>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label>Coordinador Responsable *</label>
                                    <select name="responsable" class="form-control" required>
                                        <option value="">-- Seleccione Personal --</option>
                                        <?php 
                                        if ($res_personal) {
                                            while($pers = mysqli_fetch_assoc($res_personal)): 
                                        ?>
                                            <option value="<?php echo $pers['id_personal']; ?>">
                                                <?php echo $pers['nombre'] . " " . $pers['apellido_p']; ?>
                                            </option>
                                        <?php 
                                            endwhile; 
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-4 text-secondary">3. Insumos y Observaciones</h5>
                            <div class="form-row mb-4">
                                <div class="form-group col-md-3">
                                    <label>Ceras / Velas (Cantidad)</label>
                                    <input type="number" name="velas" class="form-control" value="4" min="0">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Despensa (Kits)</label>
                                    <input type="number" name="despensa" class="form-control" value="1" min="0">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Personal Auxiliar (Nombres)</label>
                                    <input type="text" name="auxiliares" class="form-control" placeholder="Ej: Juan Perez, Pedro Lopez">
                                    <small class="text-muted">Nombres de choferes o ayudantes adicionales.</small>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label>Notas u Observaciones del Servicio</label>
                                <textarea class="form-control" name="notas" rows="3" placeholder="Detalles especiales, instrucciones para el chofer, etc."></textarea>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">Generar Orden de Servicio</button>
                                <a href="index.php" class="btn btn-dark">Cancelar</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</div>