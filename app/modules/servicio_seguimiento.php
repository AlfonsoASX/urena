<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();

$action = $action ?? 'ver';
require_role(['administradora','auxiliar','vendedor','coordinador']);

switch ($action) {
case 'guardar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_seguimiento.ver&id='.$id); }
  $estatus = so_guardar_seguimiento($id, [
    'hora_inicio_real' => trim($_POST['hora_inicio_real'] ?? '') ?: null,
    'hora_fin_real' => trim($_POST['hora_fin_real'] ?? '') ?: null,
    'observaciones_iniciales' => trim($_POST['observaciones_iniciales'] ?? '') ?: null,
    'observaciones_finales' => trim($_POST['observaciones_finales'] ?? '') ?: null,
    'incidencias' => trim($_POST['incidencias'] ?? '') ?: null,
    'confirmado_por_auxiliar' => so_parse_checkbox($_POST['confirmado_por_auxiliar'] ?? null),
    'confirmado_por_usuario' => so_current_user_id(),
  ]);
  flash("<div class='alert alert-success'>Seguimiento actualizado. Estatus actual: ".e($estatus).".</div>");
  redirect('servicio_seguimiento.ver&id='.$id);
  break;

case 'agregar_consumo':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_seguimiento.ver&id='.$id); }
  $id_articulo = (int)($_POST['id_articulo'] ?? 0);
  $cantidad = max(0, (int)($_POST['cantidad'] ?? 0));
  $responsable = trim($_POST['responsable'] ?? '') ?: (current_user()['usuario'] ?? 'sistema');
  $notas = trim($_POST['notas'] ?? '') ?: 'Consumo adicional durante el servicio';
  $pdo = db();
  try {
    $pdo->beginTransaction();
    so_registrar_articulo_servicio($pdo, $id, $id_articulo, $cantidad, $responsable, $notas);
    iv2_update_servicio_estatus($id, 'en_proceso', null, null);
    $pdo->commit();
    flash("<div class='alert alert-success'>Consumo adicional registrado.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
  }
  redirect('servicio_seguimiento.ver&id='.$id);
  break;

case 'agregar_incidencia':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_seguimiento.ver&id='.$id); }
  $texto = trim($_POST['incidencia'] ?? '');
  if ($texto === '') {
    flash("<div class='alert alert-warning'>Captura una incidencia.</div>");
    redirect('servicio_seguimiento.ver&id='.$id);
  }
  $seguimiento = so_servicio_seguimiento($id);
  $previo = trim((string)($seguimiento['incidencias'] ?? ''));
  $linea = '['.date('Y-m-d H:i').'] '.$texto;
  $nuevo = $previo === '' ? $linea : $previo."\n".$linea;
  so_guardar_seguimiento($id, [
    'hora_inicio_real' => $seguimiento['hora_inicio_real'] ?? null,
    'hora_fin_real' => $seguimiento['hora_fin_real'] ?? null,
    'observaciones_iniciales' => $seguimiento['observaciones_iniciales'] ?? null,
    'observaciones_finales' => $seguimiento['observaciones_finales'] ?? null,
    'incidencias' => $nuevo,
    'confirmado_por_auxiliar' => !empty($seguimiento['confirmado_por_auxiliar']) ? 1 : 0,
    'confirmado_por_usuario' => so_current_user_id(),
  ]);
  so_log_bitacora($id, 'incidencia', 'Incidencia agregada', ['texto' => $texto]);
  flash("<div class='alert alert-success'>Incidencia registrada.</div>");
  redirect('servicio_seguimiento.ver&id='.$id);
  break;

case 'ver':
default:
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  $seguimiento = so_servicio_seguimiento($id) ?? [];
  $articulos = so_servicio_articulos_asignados($id);
  $articulosDisp = so_servicios_articulos_disponibles();

  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
      <h1 class="h4 m-0">Seguimiento del servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
      <div class="text-muted"><?= e($svc['fallecido'] ?: 'Sin fallecido') ?> · Estatus <?= e(str_replace('_', ' ', $svc['estatus_norm'])) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.ver&id=<?= (int)$id ?>">Expediente</a>
      <a class="btn btn-outline-primary btn-sm" href="?r=servicio_asignaciones.ver&id=<?= (int)$id ?>">Asignacion</a>
      <a class="btn btn-outline-success btn-sm" href="?r=servicio_reportes.consumo_pdf&id=<?= (int)$id ?>">Consumo PDF</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Registro operativo</h2>
          <form method="post" action="?r=servicio_seguimiento.guardar&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <?= form_input('hora_inicio_real','Hora de inicio real',so_datetime_to_input($seguimiento['hora_inicio_real'] ?? null),['type'=>'datetime-local']) ?>
            <div class="mb-2">
              <label class="form-label" for="observaciones_iniciales">Observaciones iniciales</label>
              <textarea class="form-control" name="observaciones_iniciales" id="observaciones_iniciales" rows="3"><?= e($seguimiento['observaciones_iniciales'] ?? '') ?></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label" for="incidencias">Incidencias</label>
              <textarea class="form-control" name="incidencias" id="incidencias" rows="4"><?= e($seguimiento['incidencias'] ?? '') ?></textarea>
            </div>
            <?= form_input('hora_fin_real','Hora de finalizacion real',so_datetime_to_input($seguimiento['hora_fin_real'] ?? null),['type'=>'datetime-local']) ?>
            <div class="mb-2">
              <label class="form-label" for="observaciones_finales">Observaciones finales</label>
              <textarea class="form-control" name="observaciones_finales" id="observaciones_finales" rows="3"><?= e($seguimiento['observaciones_finales'] ?? '') ?></textarea>
            </div>
            <div class="form-check mt-3 mb-3">
              <input class="form-check-input" type="checkbox" name="confirmado_por_auxiliar" id="confirmado_por_auxiliar" value="1" <?= !empty($seguimiento['confirmado_por_auxiliar']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="confirmado_por_auxiliar">Confirmacion del auxiliar</label>
            </div>
            <button class="btn btn-primary">Guardar seguimiento</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Incidencia rapida</h2>
          <form method="post" action="?r=servicio_seguimiento.agregar_incidencia&id=<?= (int)$id ?>" class="mb-4">
            <?= csrf_field() ?>
            <div class="mb-2">
              <label class="form-label" for="incidencia">Nueva incidencia</label>
              <textarea class="form-control" name="incidencia" id="incidencia" rows="4"></textarea>
            </div>
            <button class="btn btn-outline-warning">Agregar incidencia</button>
          </form>

          <h2 class="h6 mb-3">Articulo adicional</h2>
          <form method="post" action="?r=servicio_seguimiento.agregar_consumo&id=<?= (int)$id ?>">
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
            <?= form_input('notas','Notas','',['placeholder'=>'Consumo adicional']) ?>
            <button class="btn btn-outline-primary">Registrar consumo</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mt-3">
    <div class="card-body">
      <h2 class="h6 mb-3">Consumos del servicio</h2>
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
            <tr><td colspan="4" class="text-center text-muted">Sin consumos registrados</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php
  render('Seguimiento de servicio', ob_get_clean());
}
