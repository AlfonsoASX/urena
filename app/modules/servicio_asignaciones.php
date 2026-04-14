<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();
so_bootstrap();

$action = $action ?? 'ver';
require_role(['administradora','auxiliar','vendedor','coordinador']);

switch ($action) {
case 'guardar':
case 'actualizar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_asignaciones.ver&id='.$id); }

  $data = [
    'fecha_hora_inicio' => trim($_POST['fecha_hora_inicio'] ?? '') ?: null,
    'lugar_tipo' => trim($_POST['lugar_tipo'] ?? '') ?: null,
    'lugar_detalle' => trim($_POST['lugar_detalle'] ?? '') ?: null,
    'id_usuario_auxiliar' => (int)($_POST['id_usuario_auxiliar'] ?? 0) ?: null,
    'id_vehiculo' => trim($_POST['id_vehiculo'] ?? '') ?: null,
    'id_sala' => trim($_POST['id_sala'] ?? '') ?: null,
    'instrucciones' => trim($_POST['instrucciones'] ?? '') ?: null,
  ];
  so_guardar_asignacion($id, $data);
  flash("<div class='alert alert-success'>Asignacion actualizada.</div>");
  redirect('servicio_asignaciones.ver&id='.$id);
  break;

case 'agregar_articulo':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_asignaciones.ver&id='.$id); }
  $id_articulo = (int)($_POST['id_articulo'] ?? 0);
  $cantidad = max(0, (int)($_POST['cantidad'] ?? 0));
  $responsable = trim($_POST['responsable'] ?? '') ?: (current_user()['usuario'] ?? 'sistema');
  $notas = trim($_POST['notas'] ?? '') ?: 'Asignacion inicial';
  $pdo = db();
  try {
    $pdo->beginTransaction();
    so_registrar_articulo_servicio($pdo, $id, $id_articulo, $cantidad, $responsable, $notas);
    q("UPDATE servicios SET estatus='preparacion', updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?", [$id]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Articulo agregado al servicio.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
  }
  redirect('servicio_asignaciones.ver&id='.$id);
  break;

case 'orden_pdf':
  $id = (int)($_GET['id'] ?? 0);
  redirect('servicio_reportes.orden_preparacion_pdf&id='.$id);
  break;

case 'ver':
default:
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  $asignacion = so_servicio_asignacion($id) ?? [];
  $auxiliares = so_user_options(['administradora','auxiliar','coordinador']);
  $cajas = so_servicios_cajas_disponibles();
  $equipos = so_servicios_equipo_disponible();
  $articulosDisp = so_servicios_articulos_disponibles();
  $articulos = so_servicio_articulos_asignados($id);
  $cajasAsignadas = so_servicio_cajas_asignadas($id);
  $equiposAsignados = so_servicio_equipos_asignados($id);

  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
      <h1 class="h4 m-0">Asignacion del servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
      <div class="text-muted"><?= e($svc['fallecido'] ?: 'Sin fallecido') ?> · <?= e($svc['contratante_nombre']) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.ver&id=<?= (int)$id ?>">Expediente</a>
      <a class="btn btn-outline-warning btn-sm" href="?r=servicio_seguimiento.ver&id=<?= (int)$id ?>">Seguimiento</a>
      <a class="btn btn-outline-dark btn-sm" href="?r=servicio_asignaciones.orden_pdf&id=<?= (int)$id ?>">Orden PDF</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Preparacion y logistica</h2>
          <form method="post" action="?r=servicio_asignaciones.guardar&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <?= form_input('fecha_hora_inicio','Fecha y hora de inicio',so_datetime_to_input($asignacion['fecha_hora_inicio'] ?? $svc['fecha_hora_programada'] ?? null),['type'=>'datetime-local']) ?>
            <?= form_select('lugar_tipo','Lugar',so_lugar_tipo_options(),$asignacion['lugar_tipo'] ?? $svc['lugar_tipo'] ?? 'capilla',['class'=>'form-select']) ?>
            <?= form_input('lugar_detalle','Detalle del lugar',$asignacion['lugar_detalle'] ?? $svc['lugar_detalle'] ?? '') ?>
            <?= form_select('id_usuario_auxiliar','Auxiliar asignado',$auxiliares,(string)($asignacion['id_usuario_auxiliar'] ?? $svc['id_usuario_auxiliar'] ?? ''),['class'=>'form-select']) ?>
            <?= form_input('id_vehiculo','Vehiculo asignado',$asignacion['id_vehiculo'] ?? $svc['id_vehiculo'] ?? '',['placeholder'=>'Placa o descripcion']) ?>
            <?= form_input('id_sala','Sala / ubicacion',$asignacion['id_sala'] ?? $svc['id_sala'] ?? '',['placeholder'=>'Capilla 1, Sala A']) ?>
            <div class="mb-2">
              <label class="form-label" for="instrucciones">Instrucciones</label>
              <textarea class="form-control" name="instrucciones" id="instrucciones" rows="4"><?= e($asignacion['instrucciones'] ?? $svc['notas'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-primary">Guardar asignacion</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Ataud y equipos</h2>
          <form method="post" action="?r=servicios.asignar_caja&id=<?= (int)$id ?>" class="mb-3">
            <?= csrf_field() ?>
            <label class="form-label">Asignar ataud</label>
            <select name="codigo" class="form-select mb-2" required>
              <option value="">Selecciona</option>
              <?php foreach ($cajas as $caja): ?>
                <option value="<?= e($caja['codigo']) ?>"><?= e($caja['codigo'].' · '.$caja['modelo'].' ('.$caja['estado'].')') ?></option>
              <?php endforeach; ?>
            </select>
            <?= form_input('notas','Notas','',['placeholder'=>'Opcional']) ?>
            <button class="btn btn-outline-primary btn-sm">Asignar ataud</button>
          </form>

          <form method="post" action="?r=servicios.asignar_equipo&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <label class="form-label">Asignar equipo</label>
            <select name="id_equipo" class="form-select mb-2" required>
              <option value="">Selecciona</option>
              <?php foreach ($equipos as $equipo): ?>
                <option value="<?= e($equipo['id_equipo']) ?>"><?= e($equipo['equipo'].' ('.$equipo['id_equipo'].')') ?></option>
              <?php endforeach; ?>
            </select>
            <?= form_input('notas','Notas','',['placeholder'=>'Opcional']) ?>
            <button class="btn btn-outline-primary btn-sm">Asignar equipo</button>
          </form>

          <hr>
          <div class="small text-muted mb-2">Ataudes asignados</div>
          <ul class="list-group list-group-flush mb-3">
            <?php foreach ($cajasAsignadas as $caja): ?>
              <li class="list-group-item px-0"><?= e($caja['codigo'].' · '.$caja['modelo']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($cajasAsignadas)): ?>
              <li class="list-group-item px-0 text-muted">Sin ataudes asignados</li>
            <?php endif; ?>
          </ul>

          <div class="small text-muted mb-2">Equipos asignados</div>
          <ul class="list-group list-group-flush">
            <?php foreach ($equiposAsignados as $equipo): ?>
              <li class="list-group-item px-0"><?= e($equipo['equipo'].' · '.$equipo['id_equipo']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($equiposAsignados)): ?>
              <li class="list-group-item px-0 text-muted">Sin equipos asignados</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Agregar articulos a preparar</h2>
          <form method="post" action="?r=servicio_asignaciones.agregar_articulo&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <label class="form-label">Articulo</label>
            <select name="id_articulo" class="form-select mb-2" required>
              <option value="">Selecciona</option>
              <?php foreach ($articulosDisp as $articulo): ?>
                <option value="<?= (int)$articulo['id'] ?>"><?= e($articulo['articulo'].' · stock '.$articulo['existencias']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= form_input('cantidad','Cantidad',1,['type'=>'number','min'=>1,'required'=>true]) ?>
            <?= form_input('responsable','Responsable',current_user()['usuario'] ?? '',['required'=>true]) ?>
            <?= form_input('notas','Notas','',['placeholder'=>'Flores, velas, etc.']) ?>
            <button class="btn btn-primary">Registrar salida</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Articulos preparados para el servicio</h2>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Articulo</th><th>Cantidad</th><th>Responsable</th><th>Fecha</th></tr></thead>
              <tbody>
                <?php foreach ($articulos as $articulo): ?>
                <tr>
                  <td><?= e($articulo['articulo']) ?></td>
                  <td><?= (int)$articulo['cantidad'] ?></td>
                  <td><?= e($articulo['responsable'] ?: '—') ?></td>
                  <td><?= e(date('Y-m-d H:i', strtotime($articulo['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($articulos)): ?>
                <tr><td colspan="4" class="text-center text-muted">Sin articulos registrados</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
  render('Asignacion de servicio', ob_get_clean());
}
