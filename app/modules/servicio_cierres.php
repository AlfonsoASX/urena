<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();

$action = $action ?? 'ver';
require_role(['administradora','auxiliar','coordinador','caja']);

switch ($action) {
case 'devolver_caja':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_cierres.ver&id='.$id); }
  $codigo = trim($_POST['codigo_caja'] ?? '');
  $estadoDevolucion = trim($_POST['estado_devolucion'] ?? 'usable') ?: 'usable';
  $reciclado = so_parse_checkbox($_POST['reciclado'] ?? null);
  $observaciones = trim($_POST['observaciones'] ?? '') ?: null;
  $pdo = db();
  try {
    $pdo->beginTransaction();
    iv2_devolver_caja($pdo, $id, $codigo, $estadoDevolucion, $reciclado, $observaciones, so_current_user_id());
    so_log_bitacora($id, 'devolucion_caja', 'Caja devuelta', ['codigo' => $codigo, 'estado_devolucion' => $estadoDevolucion]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Devolucion de caja registrada.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo registrar la devolucion: ".e($e->getMessage())."</div>");
  }
  redirect('servicio_cierres.ver&id='.$id);
  break;

case 'guardar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_cierres.ver&id='.$id); }

  $articulosInput = [];
  foreach ((array)($_POST['articulos'] ?? []) as $idServicioArticulo => $row) {
    $cantidad = max(0, (int)($row['cantidad'] ?? 0));
    if ($cantidad <= 0) continue;
    $articulosInput[] = [
      'id_servicio_articulo' => (int)$idServicioArticulo,
      'cantidad' => $cantidad,
      'observaciones' => trim((string)($row['observaciones'] ?? '')) ?: 'Devolucion de articulo en cierre',
    ];
  }

  $opcionesRecursos = [
    'accion_caja' => trim((string)($_POST['accion_caja'] ?? 'retorna')) ?: 'retorna',
    'reciclado_caja' => so_parse_checkbox($_POST['reciclado_caja'] ?? 1),
    'observaciones_caja' => trim((string)($_POST['observaciones_caja'] ?? '')) ?: 'Cierre de servicio',
    'ids_equipo' => array_values(array_unique(array_filter(array_map('trim', (array)($_POST['ids_equipo'] ?? []))))),
    'liberar_todos_equipos' => so_parse_checkbox($_POST['liberar_todos_equipos'] ?? null),
    'articulos' => $articulosInput,
  ];

  $data = [
    'observaciones_cierre' => trim($_POST['observaciones_cierre'] ?? '') ?: null,
    'articulos_devueltos_json' => $articulosInput ? so_json($articulosInput) : null,
    'caja_reciclada' => so_parse_checkbox($_POST['reciclado_caja'] ?? null),
    'pago_confirmado' => so_parse_checkbox($_POST['pago_confirmado'] ?? null),
    'auxiliar_confirma' => so_parse_checkbox($_POST['auxiliar_confirma'] ?? null),
    'id_usuario_auxiliar' => (int)($_POST['id_usuario_auxiliar'] ?? 0) ?: ((int)($svc['id_usuario_auxiliar'] ?? 0) ?: null),
    'id_usuario_administrador' => so_current_user_id(),
    'fecha_cierre' => trim($_POST['fecha_cierre'] ?? '') ?: so_now_local(),
  ];

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultadoRecursos = so_cerrar_servicio_recursos($pdo, $id, $opcionesRecursos);
    so_guardar_cierre($id, $data);
    so_log_bitacora($id, 'cierre_estructurado', 'Cierre estructurado aplicado', $resultadoRecursos);
    $pdo->commit();
    flash("<div class='alert alert-success'>Cierre guardado y recursos procesados.</div>");
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
  $equiposGrupo = so_servicio_equipos_asignados_grupo($id);
  $articulos = so_servicio_articulos_asignados($id);
  $auxiliares = so_user_options(['administradora','auxiliar','coordinador']);
  $cajaActiva = $cajas[0] ?? null;

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
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Monto total</div><div class="fs-5 fw-semibold"><?= e(so_moneda($totales['total'])) ?></div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Pagado</div><div class="fs-5 fw-semibold"><?= e(so_moneda($totales['pagado'])) ?></div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Saldo pendiente</div><div class="fs-5 fw-semibold"><?= e(so_moneda($totales['saldo'])) ?></div></div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h2 class="h6 mb-3">Cierre estructurado</h2>
        <form method="post" action="?r=servicio_cierres.guardar&id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <?= form_input('fecha_cierre','Fecha y hora de cierre',so_datetime_to_input($cierre['fecha_cierre'] ?? $svc['closed_at'] ?? so_now_local()),['type'=>'datetime-local']) ?>
          <?= form_select('id_usuario_auxiliar','Auxiliar que confirma',$auxiliares,(string)($cierre['id_usuario_auxiliar'] ?? $svc['id_usuario_auxiliar'] ?? ''),['class'=>'form-select']) ?>

          <div class="border rounded p-3 mb-3 bg-light">
            <div class="fw-semibold mb-2">Ataud</div>
            <?php if ($cajaActiva): ?>
              <div class="mb-2"><?= e($cajaActiva['codigo'].' · '.($cajaActiva['modelo'] ?: 'Sin modelo')) ?> <span class="badge text-bg-secondary"><?= e($cajaActiva['ui_estado'] ?? 'Nuevo') ?></span></div>
              <?= form_select('accion_caja','Accion al cerrar',[
                'retorna' => 'Retorna al inventario reutilizable',
                'no_retorna' => 'No retorna al inventario',
              ],'retorna',['class'=>'form-select']) ?>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="reciclado_caja" id="reciclado_caja" value="1" checked>
                <label class="form-check-label" for="reciclado_caja">Mostrar como usado / reciclado al regresar</label>
              </div>
              <?= form_input('observaciones_caja','Observaciones del ataud','',['placeholder'=>'Opcional']) ?>
            <?php else: ?>
              <div class="text-muted">No hay ataud activo.</div>
            <?php endif; ?>
          </div>

          <div class="border rounded p-3 mb-3">
            <div class="fw-semibold mb-2">Equipos</div>
            <?php if (!empty($equiposGrupo)): ?>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="liberar_todos_equipos" id="liberar_todos_equipos" value="1">
                <label class="form-check-label" for="liberar_todos_equipos">Liberar todos los equipos activos</label>
              </div>
              <?php foreach ($equiposGrupo as $grupo): ?>
                <div class="border rounded p-2 mb-2 bg-light">
                  <div class="fw-semibold"><?= e($grupo['equipo']) ?> · <?= (int)$grupo['cantidad'] ?></div>
                  <div class="small text-muted mb-2">IDs: <?= e(implode(', ', $grupo['ids'] ?? [])) ?></div>
                </div>
              <?php endforeach; ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead><tr><th></th><th>Grupo</th><th>ID pieza</th></tr></thead>
                  <tbody>
                    <?php foreach ($equipos as $equipo): ?>
                      <tr>
                        <td><input class="form-check-input" type="checkbox" name="ids_equipo[]" value="<?= e($equipo['id_equipo']) ?>"></td>
                        <td><?= e($equipo['equipo'] ?: 'Equipo') ?></td>
                        <td><code><?= e($equipo['id_equipo']) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-muted">No hay equipos activos.</div>
            <?php endif; ?>
          </div>

          <div class="border rounded p-3 mb-3">
            <div class="fw-semibold mb-2">Devolucion parcial de articulos</div>
            <?php if (!empty($articulos)): ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th>Articulo</th><th>Activo</th><th>Regresar</th><th>Observaciones</th></tr></thead>
                  <tbody>
                    <?php foreach ($articulos as $articulo): ?>
                      <tr>
                        <td><?= e($articulo['articulo']) ?></td>
                        <td><?= (int)$articulo['cantidad'] ?></td>
                        <td style="max-width: 100px;"><input class="form-control form-control-sm" type="number" min="0" max="<?= (int)$articulo['cantidad'] ?>" name="articulos[<?= (int)$articulo['id_servicio_articulo'] ?>][cantidad]" value="0"></td>
                        <td><input class="form-control form-control-sm" type="text" name="articulos[<?= (int)$articulo['id_servicio_articulo'] ?>][observaciones]" placeholder="Opcional"></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-muted">No hay articulos activos.</div>
            <?php endif; ?>
          </div>

          <div class="mb-2">
            <label class="form-label" for="observaciones_cierre">Observaciones de cierre</label>
            <textarea class="form-control" name="observaciones_cierre" id="observaciones_cierre" rows="5"><?= e($cierre['observaciones_cierre'] ?? '') ?></textarea>
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
      </div></div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h2 class="h6 mb-3">Resumen de recursos activos</h2>
        <div class="small text-muted mb-2">Ataud</div>
        <ul class="list-group list-group-flush mb-3">
          <?php if ($cajaActiva): ?>
            <li class="list-group-item px-0"><?= e($cajaActiva['codigo'].' · '.$cajaActiva['modelo']) ?> <span class="badge text-bg-secondary"><?= e($cajaActiva['ui_estado'] ?? 'Nuevo') ?></span></li>
          <?php else: ?>
            <li class="list-group-item px-0 text-muted">Sin ataud activo</li>
          <?php endif; ?>
        </ul>

        <div class="small text-muted mb-2">Equipos</div>
        <ul class="list-group list-group-flush mb-3">
          <?php foreach ($equipos as $equipo): ?>
            <li class="list-group-item px-0"><?= e($equipo['equipo'].' · '.$equipo['id_equipo']) ?></li>
          <?php endforeach; ?>
          <?php if (empty($equipos)): ?>
            <li class="list-group-item px-0 text-muted">Sin equipos activos</li>
          <?php endif; ?>
        </ul>

        <div class="small text-muted mb-2">Articulos</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($articulos as $articulo): ?>
            <li class="list-group-item px-0"><?= e($articulo['articulo'].' x'.$articulo['cantidad']) ?></li>
          <?php endforeach; ?>
          <?php if (empty($articulos)): ?>
            <li class="list-group-item px-0 text-muted">Sin articulos activos</li>
          <?php endif; ?>
        </ul>
      </div></div>
    </div>
  </div>
  <?php
  render('Cierre de servicio', ob_get_clean());
}
