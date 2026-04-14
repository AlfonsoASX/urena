<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();
so_bootstrap();

$action = $action ?? 'ver';
require_role(['administradora','auxiliar','coordinador','caja']);

switch ($action) {
case 'guardar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_cierres.ver&id='.$id); }

  $articulosTexto = trim($_POST['articulos_devueltos'] ?? '');
  $lineas = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $articulosTexto))));
  $data = [
    'observaciones_cierre' => trim($_POST['observaciones_cierre'] ?? '') ?: null,
    'articulos_devueltos_json' => $lineas ? so_json($lineas) : null,
    'caja_reciclada' => so_parse_checkbox($_POST['caja_reciclada'] ?? null),
    'pago_confirmado' => so_parse_checkbox($_POST['pago_confirmado'] ?? null),
    'auxiliar_confirma' => so_parse_checkbox($_POST['auxiliar_confirma'] ?? null),
    'id_usuario_auxiliar' => (int)($_POST['id_usuario_auxiliar'] ?? 0) ?: ((int)($svc['id_usuario_auxiliar'] ?? 0) ?: null),
    'id_usuario_administrador' => so_current_user_id(),
    'fecha_cierre' => trim($_POST['fecha_cierre'] ?? '') ?: so_now_local(),
  ];

  $pdo = db();
  try {
    $pdo->beginTransaction();
    so_guardar_cierre($id, $data);
    so_cerrar_servicio_recursos($pdo, $id);
    $pdo->commit();
    flash("<div class='alert alert-success'>Cierre guardado y recursos devueltos.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo guardar el cierre: ".e($e->getMessage())."</div>");
  }
  redirect('servicio_cierres.ver&id='.$id);
  break;

case 'imprimir':
  $id = (int)($_GET['id'] ?? 0);
  redirect('servicio_reportes.reporte_cierre_pdf&id='.$id);
  break;

case 'ver':
default:
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  $cierre = so_servicio_cierre($id) ?? [];
  $totales = so_servicio_totales($id);
  $cajas = so_servicio_cajas_asignadas($id);
  $equipos = so_servicio_equipos_asignados($id);
  $articulos = so_servicio_articulos_asignados($id);
  $auxiliares = so_user_options(['administradora','auxiliar','coordinador']);
  $articulosDevueltos = '';
  if (!empty($cierre['articulos_devueltos_json'])) {
    $decoded = json_decode((string)$cierre['articulos_devueltos_json'], true);
    if (is_array($decoded)) $articulosDevueltos = implode("\n", $decoded);
  }

  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
      <h1 class="h4 m-0">Cierre del servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
      <div class="text-muted"><?= e($svc['fallecido'] ?: 'Sin fallecido') ?> · Saldo <?= e(so_moneda($totales['saldo'])) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.ver&id=<?= (int)$id ?>">Expediente</a>
      <a class="btn btn-outline-info btn-sm" href="?r=servicio_pagos.listar&id=<?= (int)$id ?>">Pagos</a>
      <a class="btn btn-outline-dark btn-sm" href="?r=servicio_cierres.imprimir&id=<?= (int)$id ?>">Reporte PDF</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Monto total</div>
        <div class="fs-5 fw-semibold"><?= e(so_moneda($totales['total'])) ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Pagado</div>
        <div class="fs-5 fw-semibold"><?= e(so_moneda($totales['pagado'])) ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Saldo pendiente</div>
        <div class="fs-5 fw-semibold"><?= e(so_moneda($totales['saldo'])) ?></div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Formulario de cierre</h2>
          <form method="post" action="?r=servicio_cierres.guardar&id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <?= form_input('fecha_cierre','Fecha y hora de cierre',so_datetime_to_input($cierre['fecha_cierre'] ?? $svc['closed_at'] ?? so_now_local()),['type'=>'datetime-local']) ?>
            <?= form_select('id_usuario_auxiliar','Auxiliar que confirma',$auxiliares,(string)($cierre['id_usuario_auxiliar'] ?? $svc['id_usuario_auxiliar'] ?? ''),['class'=>'form-select']) ?>
            <div class="mb-2">
              <label class="form-label" for="articulos_devueltos">Articulos devueltos o reciclados</label>
              <textarea class="form-control" name="articulos_devueltos" id="articulos_devueltos" rows="4" placeholder="Uno por linea"><?= e($articulosDevueltos) ?></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label" for="observaciones_cierre">Observaciones de cierre</label>
              <textarea class="form-control" name="observaciones_cierre" id="observaciones_cierre" rows="5"><?= e($cierre['observaciones_cierre'] ?? '') ?></textarea>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="caja_reciclada" id="caja_reciclada" value="1" <?= !empty($cierre['caja_reciclada']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="caja_reciclada">Marcar ataud como reciclado si aplica</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="auxiliar_confirma" id="auxiliar_confirma" value="1" <?= !empty($cierre['auxiliar_confirma']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="auxiliar_confirma">Confirmacion del auxiliar</label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="pago_confirmado" id="pago_confirmado" value="1" <?= !empty($cierre['pago_confirmado']) ? 'checked' : ($totales['saldo'] <= 0 ? 'checked' : '') ?>>
              <label class="form-check-label" for="pago_confirmado">Pago total confirmado</label>
            </div>
            <button class="btn btn-success">Guardar cierre</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Resumen de recursos</h2>
          <div class="small text-muted mb-2">Ataudes asignados</div>
          <ul class="list-group list-group-flush mb-3">
            <?php foreach ($cajas as $caja): ?>
              <li class="list-group-item px-0"><?= e($caja['codigo'].' · '.$caja['modelo']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($cajas)): ?>
              <li class="list-group-item px-0 text-muted">Sin ataudes registrados</li>
            <?php endif; ?>
          </ul>

          <div class="small text-muted mb-2">Equipos asignados</div>
          <ul class="list-group list-group-flush mb-3">
            <?php foreach ($equipos as $equipo): ?>
              <li class="list-group-item px-0"><?= e($equipo['equipo'].' · '.$equipo['id_equipo']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($equipos)): ?>
              <li class="list-group-item px-0 text-muted">Sin equipos registrados</li>
            <?php endif; ?>
          </ul>

          <div class="small text-muted mb-2">Articulos consumidos</div>
          <ul class="list-group list-group-flush">
            <?php foreach ($articulos as $articulo): ?>
              <li class="list-group-item px-0"><?= e($articulo['articulo'].' x'.$articulo['cantidad']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($articulos)): ?>
              <li class="list-group-item px-0 text-muted">Sin articulos registrados</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <?php
  render('Cierre de servicio', ob_get_clean());
}
