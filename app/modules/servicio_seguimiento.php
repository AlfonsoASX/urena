<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();

$action = $action ?? 'ver';
require_role(['administradora', 'auxiliar', 'vendedor', 'coordinador']);

function seguimiento_redirect(int $id): void {
  redirect('servicio_seguimiento.ver&id='.$id);
}

function seguimiento_incidencias_lista(?string $texto): array {
  $texto = trim((string)$texto);
  if ($texto === '') return [];
  $items = preg_split("/\r\n|\r|\n/", $texto) ?: [];
  return array_values(array_filter(array_map('trim', $items), static fn($item): bool => $item !== ''));
}

function seguimiento_payload(array $existente, array $input, string $accionRapida = ''): array {
  $accionRapida = trim($accionRapida);
  $preservar = $accionRapida !== '';

  $pick = static function (string $key) use ($existente, $input, $preservar) {
    if ($preservar && !array_key_exists($key, $input)) {
      return $existente[$key] ?? null;
    }
    $value = trim((string)($input[$key] ?? ''));
    return $value !== '' ? $value : null;
  };

  $confirmado = $preservar && !array_key_exists('confirmado_por_auxiliar', $input)
    ? (!empty($existente['confirmado_por_auxiliar']) ? 1 : 0)
    : so_parse_checkbox($input['confirmado_por_auxiliar'] ?? null);

  $data = [
    'hora_inicio_real' => $pick('hora_inicio_real'),
    'hora_fin_real' => $pick('hora_fin_real'),
    'observaciones_iniciales' => $pick('observaciones_iniciales'),
    'observaciones_finales' => $pick('observaciones_finales'),
    'incidencias' => $pick('incidencias'),
    'confirmado_por_auxiliar' => $confirmado,
    'confirmado_por_usuario' => so_current_user_id(),
  ];

  if ($accionRapida === 'iniciar') {
    $data['hora_inicio_real'] = so_now_local();
  } elseif ($accionRapida === 'finalizar') {
    $data['hora_fin_real'] = so_now_local();
  } elseif ($accionRapida === 'confirmar') {
    $data['confirmado_por_auxiliar'] = 1;
  }

  return $data;
}

function seguimiento_flash_message(string $accionRapida, string $estatus): string {
  return match ($accionRapida) {
    'iniciar' => 'Hora de inicio registrada. Estatus actual: '.e($estatus).'.',
    'finalizar' => 'Hora de finalización registrada. Estatus actual: '.e($estatus).'.',
    'confirmar' => 'Confirmación del auxiliar actualizada. Estatus actual: '.e($estatus).'.',
    default => 'Seguimiento actualizado. Estatus actual: '.e($estatus).'.',
  };
}

switch ($action) {
case 'guardar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); seguimiento_redirect($id); }
  $accionRapida = trim((string)($_POST['accion_rapida'] ?? ''));
  $existente = so_servicio_seguimiento($id) ?? [];
  $payload = seguimiento_payload($existente, $_POST, $accionRapida);
  $estatus = so_guardar_seguimiento($id, $payload);
  flash("<div class='alert alert-success'>".seguimiento_flash_message($accionRapida, $estatus)."</div>");
  seguimiento_redirect($id);
  break;

case 'agregar_consumo':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); seguimiento_redirect($id); }
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
  seguimiento_redirect($id);
  break;

case 'agregar_incidencia':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); seguimiento_redirect($id); }
  $texto = trim($_POST['incidencia'] ?? '');
  if ($texto === '') {
    flash("<div class='alert alert-warning'>Captura una incidencia.</div>");
    seguimiento_redirect($id);
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
  seguimiento_redirect($id);
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
  $asignacion = so_servicio_asignacion($id) ?? [];
  $articulos = so_servicio_articulos_asignados($id);
  $articulosDisp = so_servicios_articulos_disponibles();
  $cajas = so_servicio_cajas_asignadas($id);
  $equipos = so_servicio_equipos_asignados($id);
  $cajaActiva = $cajas[0] ?? null;
  $incidencias = array_reverse(seguimiento_incidencias_lista($seguimiento['incidencias'] ?? ''));
  $estatusBadge = so_servicio_badge($svc['estatus'] ?? '');
  $estatusLabel = ucwords(str_replace('_', ' ', $svc['estatus_norm'] ?? 'registrado'));
  $auxiliarNombre = trim((string)($asignacion['auxiliar_nombre'] ?? $svc['auxiliar_nombre'] ?? ''));
  $lugarTipo = trim((string)($asignacion['lugar_tipo'] ?? $svc['lugar_tipo'] ?? ''));
  $lugarDetalle = trim((string)($asignacion['lugar_detalle'] ?? $svc['lugar_detalle'] ?? ''));
  $inicioReal = $seguimiento['hora_inicio_real'] ?? null;
  $finReal = $seguimiento['hora_fin_real'] ?? null;
  $totalArticulos = 0;
  foreach ($articulos as $articulo) {
    $totalArticulos += (int)($articulo['cantidad'] ?? 0);
  }

  ob_start(); ?>
  <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3">
    <div>
      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <h1 class="h4 m-0">Seguimiento del servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
        <span class="badge text-bg-<?= e($estatusBadge) ?>"><?= e($estatusLabel) ?></span>
        <?php if (!empty($seguimiento['confirmado_por_auxiliar'])): ?>
          <span class="badge text-bg-success">Auxiliar confirmado</span>
        <?php endif; ?>
      </div>
      <div class="text-muted">
        <?= e($svc['fallecido'] ?: 'Sin fallecido') ?>
        <?php if (!empty($svc['fecha_hora_programada'])): ?>
          · Programado: <?= e(date('d/m/Y H:i', strtotime($svc['fecha_hora_programada']))) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.ver&id=<?= (int)$id ?>">Expediente</a>
      <a class="btn btn-outline-primary btn-sm" href="?r=servicio_asignaciones.ver&id=<?= (int)$id ?>">Asignación</a>
      <a class="btn btn-outline-success btn-sm" href="?r=servicio_reportes.consumo_pdf&id=<?= (int)$id ?>">Consumo PDF</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Estatus actual</div>
          <div class="fs-5 fw-semibold"><?= e($estatusLabel) ?></div>
          <div class="small text-muted mt-1"><?= !empty($seguimiento['confirmado_por_auxiliar']) ? 'Confirmado por auxiliar' : 'Pendiente de confirmación' ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Inicio real</div>
          <div class="fs-6 fw-semibold"><?= e($inicioReal ? date('d/m/Y H:i', strtotime($inicioReal)) : 'Pendiente') ?></div>
          <div class="small text-muted mt-1"><?= e($auxiliarNombre ?: 'Sin auxiliar asignado') ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Finalización real</div>
          <div class="fs-6 fw-semibold"><?= e($finReal ? date('d/m/Y H:i', strtotime($finReal)) : 'Pendiente') ?></div>
          <div class="small text-muted mt-1"><?= !empty($seguimiento['confirma_nombre']) ? 'Última confirmación: '.e($seguimiento['confirma_nombre']) : 'Sin confirmación registrada' ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Recursos activos</div>
          <div class="fs-6 fw-semibold"><?= $cajaActiva ? '1 ataúd' : 'Sin ataúd' ?> · <?= count($equipos) ?> equipo(s)</div>
          <div class="small text-muted mt-1"><?= $totalArticulos ?> artículo(s) consumidos</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
            <div>
              <h2 class="h6 mb-1">Acciones rápidas</h2>
              <div class="text-muted small">Registra los hitos principales sin salir de esta pantalla.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <form method="post" action="?r=servicio_seguimiento.guardar&id=<?= (int)$id ?>" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="accion_rapida" value="iniciar">
                <button class="btn btn-outline-warning btn-sm"><?= $inicioReal ? 'Actualizar inicio a ahora' : 'Iniciar ahora' ?></button>
              </form>
              <form method="post" action="?r=servicio_seguimiento.guardar&id=<?= (int)$id ?>" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="accion_rapida" value="finalizar">
                <button class="btn btn-outline-success btn-sm"><?= $finReal ? 'Actualizar cierre a ahora' : 'Finalizar ahora' ?></button>
              </form>
              <form method="post" action="?r=servicio_seguimiento.guardar&id=<?= (int)$id ?>" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="accion_rapida" value="confirmar">
                <button class="btn btn-outline-primary btn-sm"><?= !empty($seguimiento['confirmado_por_auxiliar']) ? 'Reconfirmar auxiliar' : 'Confirmar auxiliar' ?></button>
              </form>
            </div>
          </div>
          <div class="small text-muted">
            <?php if (!empty($seguimiento['confirmado_por_auxiliar'])): ?>
              Confirmado por <?= e($seguimiento['confirma_nombre'] ?: 'usuario actual') ?>.
            <?php else: ?>
              Aún no hay confirmación auxiliar registrada.
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h2 class="h6 mb-1">Registro detallado</h2>
              <div class="text-muted small">Edita tiempos, observaciones y la bitácora completa del servicio.</div>
            </div>
          </div>
          <form method="post" action="?r=servicio_seguimiento.guardar&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-md-6"><?= form_input('hora_inicio_real', 'Hora de inicio real', so_datetime_to_input($inicioReal), ['type' => 'datetime-local']) ?></div>
              <div class="col-md-6"><?= form_input('hora_fin_real', 'Hora de finalización real', so_datetime_to_input($finReal), ['type' => 'datetime-local']) ?></div>
              <div class="col-lg-6">
                <label class="form-label" for="observaciones_iniciales">Observaciones iniciales</label>
                <textarea class="form-control" name="observaciones_iniciales" id="observaciones_iniciales" rows="4"><?= e($seguimiento['observaciones_iniciales'] ?? '') ?></textarea>
              </div>
              <div class="col-lg-6">
                <label class="form-label" for="observaciones_finales">Observaciones finales</label>
                <textarea class="form-control" name="observaciones_finales" id="observaciones_finales" rows="4"><?= e($seguimiento['observaciones_finales'] ?? '') ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" for="incidencias">Bitácora / incidencias</label>
                <textarea class="form-control" name="incidencias" id="incidencias" rows="6"><?= e($seguimiento['incidencias'] ?? '') ?></textarea>
                <div class="form-text">Las incidencias rápidas también se agregan aquí automáticamente.</div>
              </div>
            </div>
            <div class="form-check mt-3 mb-3">
              <input class="form-check-input" type="checkbox" name="confirmado_por_auxiliar" id="confirmado_por_auxiliar" value="1" <?= !empty($seguimiento['confirmado_por_auxiliar']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="confirmado_por_auxiliar">Confirmación del auxiliar</label>
            </div>
            <button class="btn btn-primary">Guardar seguimiento</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3">Resumen operativo</h2>
          <dl class="row mb-0 small">
            <dt class="col-5 text-muted">Lugar</dt>
            <dd class="col-7"><?= e(trim($lugarTipo.' '.$lugarDetalle) ?: 'Sin definir') ?></dd>
            <dt class="col-5 text-muted">Auxiliar</dt>
            <dd class="col-7"><?= e($auxiliarNombre ?: 'Sin asignar') ?></dd>
            <dt class="col-5 text-muted">Ataúd</dt>
            <dd class="col-7"><?= $cajaActiva ? e($cajaActiva['codigo'].' · '.($cajaActiva['modelo'] ?: 'Sin modelo')) : 'Sin ataúd activo' ?></dd>
            <dt class="col-5 text-muted">Equipos</dt>
            <dd class="col-7"><?= count($equipos) ? e(implode(', ', array_map(static fn($item): string => trim((string)($item['equipo'] ?? '')).' '.trim((string)($item['id_equipo'] ?? '')), $equipos))) : 'Sin equipos activos' ?></dd>
            <dt class="col-5 text-muted">Consumos</dt>
            <dd class="col-7"><?= count($articulos) ? e(count($articulos).' registro(s) / '.$totalArticulos.' pieza(s)') : 'Sin consumos' ?></dd>
          </dl>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3">Incidencia rápida</h2>
          <form method="post" action="?r=servicio_seguimiento.agregar_incidencia&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <div class="mb-2">
              <label class="form-label" for="incidencia">Nueva incidencia</label>
              <textarea class="form-control" name="incidencia" id="incidencia" rows="4" placeholder="Describe el incidente, ajuste o hallazgo relevante."></textarea>
            </div>
            <button class="btn btn-outline-warning w-100">Agregar incidencia</button>
          </form>
          <hr class="my-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0">Últimas incidencias</h3>
            <span class="badge text-bg-light border"><?= count($incidencias) ?></span>
          </div>
          <?php if (!empty($incidencias)): ?>
            <div class="list-group list-group-flush small">
              <?php foreach (array_slice($incidencias, 0, 6) as $item): ?>
                <div class="list-group-item px-0"><?= e($item) ?></div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-muted small">No hay incidencias registradas.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-3">Consumo adicional</h2>
          <form method="post" action="?r=servicio_seguimiento.agregar_consumo&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <label class="form-label">Artículo</label>
            <select name="id_articulo" class="form-select mb-2" required>
              <option value="">Selecciona</option>
              <?php foreach ($articulosDisp as $articulo): ?>
                <option value="<?= (int)$articulo['id'] ?>"><?= e($articulo['articulo'].' · stock '.$articulo['existencias']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= form_input('cantidad', 'Cantidad', 1, ['type' => 'number', 'min' => 1, 'required' => true]) ?>
            <?= form_input('responsable', 'Responsable', current_user()['usuario'] ?? '', ['required' => true]) ?>
            <?= form_input('notas', 'Notas', '', ['placeholder' => 'Consumo adicional']) ?>
            <button class="btn btn-outline-primary w-100">Registrar consumo</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Incidencias registradas</h2>
            <span class="badge text-bg-light border"><?= count($incidencias) ?></span>
          </div>
          <?php if (!empty($incidencias)): ?>
            <div class="list-group list-group-flush">
              <?php foreach ($incidencias as $item): ?>
                <div class="list-group-item px-0 small"><?= e($item) ?></div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center text-muted py-4">Sin incidencias registradas.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Consumos del servicio</h2>
            <span class="badge text-bg-light border"><?= count($articulos) ?> registros</span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Artículo</th><th>Cantidad</th><th>Responsable</th><th>Fecha</th></tr></thead>
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
    </div>
  </div>
  <?php
  render('Seguimiento de servicio', ob_get_clean());
}
