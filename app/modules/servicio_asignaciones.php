<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();

$action = $action ?? 'ver';
require_role(['administradora','auxiliar','vendedor','coordinador']);

function servicio_asignaciones_redirect(int $id, string $tab = 'logistica'): void {
  redirect('servicio_asignaciones.ver&id='.$id.'&tab='.$tab);
}

function servicio_asignaciones_tab(string $tab): string {
  $allowed = ['logistica', 'ataud', 'equipos', 'articulos'];
  return in_array($tab, $allowed, true) ? $tab : 'logistica';
}

switch ($action) {
case 'guardar':
case 'actualizar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $tab = servicio_asignaciones_tab((string)($_GET['tab'] ?? $_POST['tab'] ?? 'logistica'));
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); servicio_asignaciones_redirect($id, $tab); }

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
  flash("<div class='alert alert-success'>Logistica actualizada.</div>");
  servicio_asignaciones_redirect($id, $tab);
  break;

case 'agregar_articulo':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); servicio_asignaciones_redirect($id, 'articulos'); }
  $id_articulo = (int)($_POST['id_articulo'] ?? 0);
  $cantidad = max(0, (int)($_POST['cantidad'] ?? 0));
  $responsable = trim($_POST['responsable'] ?? '') ?: (current_user()['usuario'] ?? 'sistema');
  $notas = trim($_POST['notas'] ?? '') ?: 'Asignacion inicial';
  $pdo = db();
  try {
    $pdo->beginTransaction();
    so_registrar_articulo_servicio($pdo, $id, $id_articulo, $cantidad, $responsable, $notas);
    iv2_update_servicio_estatus($id, 'preparacion', null, null);
    $pdo->commit();
    flash("<div class='alert alert-success'>Articulo agregado al servicio.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
  }
  servicio_asignaciones_redirect($id, 'articulos');
  break;

case 'cancelar_articulo':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $idServicioArticulo = (int)($_POST['id_servicio_articulo'] ?? 0);
  $cantidad = max(0, (int)($_POST['cantidad'] ?? 0));
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); servicio_asignaciones_redirect($id, 'articulos'); }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_cancelar_consumo_articulo($pdo, $id, $idServicioArticulo, $cantidad, so_current_user_id(), trim($_POST['observaciones'] ?? '') ?: 'Cancelacion manual', 'cancelacion');
    so_log_bitacora($id, 'cancelacion_articulo', 'Consumo retirado del servicio', $resultado);
    $pdo->commit();
    flash("<div class='alert alert-success'>Articulo retirado del servicio.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo retirar el articulo: ".e($e->getMessage())."</div>");
  }
  servicio_asignaciones_redirect($id, 'articulos');
  break;

case 'cancelar_caja':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); servicio_asignaciones_redirect($id, 'ataud'); }
  $codigo = trim((string)($_POST['codigo_caja'] ?? $_POST['codigo'] ?? '')) ?: null;
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_cancelar_caja($pdo, $id, $codigo, so_current_user_id(), trim($_POST['observaciones'] ?? '') ?: 'Cancelacion manual');
    so_log_bitacora($id, 'cancelacion_caja', 'Ataud retirado del servicio', $resultado);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataud retirado del servicio.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo retirar el ataud: ".e($e->getMessage())."</div>");
  }
  servicio_asignaciones_redirect($id, 'ataud');
  break;

case 'cancelar_equipos':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); servicio_asignaciones_redirect($id, 'equipos'); }
  $idsEquipo = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['ids_equipo'] ?? [])))));
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_cancelar_equipos($pdo, $id, $idsEquipo, so_current_user_id(), trim($_POST['observaciones'] ?? '') ?: 'Cancelacion manual', 'cancelacion');
    so_log_bitacora($id, 'cancelacion_equipos', 'Equipos retirados del servicio', ['equipos' => $resultado]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipos retirados del servicio.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudieron retirar los equipos: ".e($e->getMessage())."</div>");
  }
  servicio_asignaciones_redirect($id, 'equipos');
  break;

case 'orden_pdf':
  $id = (int)($_GET['id'] ?? 0);
  redirect('servicio_reportes.orden_preparacion_pdf&id='.$id);
  break;

case 'ver':
default:
  $id = (int)($_GET['id'] ?? 0);
  $activeTab = servicio_asignaciones_tab((string)($_GET['tab'] ?? 'logistica'));
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }

  $asignacion = so_servicio_asignacion($id) ?? [];
  $auxiliares = so_user_options(['administradora','auxiliar','coordinador']);
  $cajasState = so_inventory_section_state('cajas', '', 12);
  $articulosState = so_inventory_section_state('articulos', '', 100);
  $equiposGrupos = iv2_disponibilidad_equipos('', 50, true);
  $articulosDisp = $articulosState['items'];
  $articulos = so_servicio_articulos_asignados($id);
  $cajasAsignadas = so_servicio_cajas_asignadas($id);
  $equiposAsignados = so_servicio_equipos_asignados($id);
  $equiposAsignadosGrupo = so_servicio_equipos_asignados_grupo($id);
  $cajaActiva = $cajasAsignadas[0] ?? null;

  $estatusBadge = so_servicio_badge($svc['estatus'] ?? '');
  $estatusLabel  = ucwords(str_replace('_', ' ', $svc['estatus_norm'] ?? 'registrado'));

  $uiBootstrap = json_encode([
    'idServicio' => $id,
    'csrf' => csrf_token(),
    'activeTab' => $activeTab,
    'inventario' => [
      'cajas' => $cajasState['items'],
      'equiposGrupos' => $equiposGrupos,
      'articulos' => $articulosDisp,
    ],
    'errores' => [
      'cajas' => $cajasState['error'],
      'equipos' => null,
      'articulos' => $articulosState['error'],
    ],
    'asignados' => [
      'cajas' => $cajasAsignadas,
      'equipos' => $equiposAsignados,
      'equipos_grupo' => $equiposAsignadosGrupo,
      'articulos' => $articulos,
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
    <div>
      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <h1 class="h4 m-0">Asignacion &middot; <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
        <span class="badge text-bg-<?= e($estatusBadge) ?>"><?= e($estatusLabel) ?></span>
        <?php if ($svc['tipo_servicio']): ?><span class="badge text-bg-light border text-dark"><?= e(ucfirst($svc['tipo_servicio'])) ?><?php if ($svc['tipo_disposicion'] ?? ''): ?> / <?= e(ucfirst($svc['tipo_disposicion'])) ?><?php endif; ?></span><?php endif; ?>
      </div>
      <div class="text-muted small">
        <?= e($svc['fallecido'] ?: 'Sin fallecido registrado') ?>
        <?php if ($svc['contratante_nombre'] ?? ''): ?>&nbsp;&middot; Contratante: <?= e($svc['contratante_nombre']) ?><?php endif; ?>
      </div>
      <?php if (!empty($svc['fecha_hora_programada']) && !in_array($svc['fecha_hora_programada'], ['0000-00-00 00:00:00',''])): ?>
        <div class="text-muted small mt-1">Programado: <?= e(date('d/m/Y H:i', strtotime($svc['fecha_hora_programada']))) ?></div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2 flex-shrink-0">
      <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.ver&id=<?= (int)$id ?>">&larr; Expediente</a>
      <a class="btn btn-outline-warning btn-sm" href="?r=servicio_seguimiento.ver&id=<?= (int)$id ?>">Seguimiento</a>
      <a class="btn btn-outline-dark btn-sm" href="?r=servicio_asignaciones.orden_pdf&id=<?= (int)$id ?>">Orden PDF</a>
    </div>
  </div>

  <div id="assignmentGlobalAlert" class="mb-3"></div>

  <?php
    $cajaBadge    = $cajaActiva          ? 'success' : 'secondary';
    $equBadge     = count($equiposAsignados) ? 'success' : 'secondary';
    $artBadge     = count($articulos)     ? 'success' : 'secondary';
  ?>
  <div class="row g-3 mb-3">
    <div class="col-sm-4">
      <div class="card border-<?= $cajaBadge ?> h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Ataud activo</div>
          <div class="fs-3 fw-bold text-<?= $cajaBadge ?>" id="cajasCount"><?= $cajaActiva ? 1 : 0 ?></div>
          <?php if ($cajaActiva): ?><div class="small text-truncate"><?= e($cajaActiva['codigo'].' &middot; '.($cajaActiva['modelo'] ?: 'Sin modelo')) ?></div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border-<?= $equBadge ?> h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Equipos activos</div>
          <div class="fs-3 fw-bold text-<?= $equBadge ?>" id="equiposCount"><?= count($equiposAsignados) ?></div>
          <?php if (count($equiposAsignadosGrupo)): ?><div class="small text-truncate"><?= e(implode(', ', array_column($equiposAsignadosGrupo, 'equipo'))) ?></div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border-<?= $artBadge ?> h-100">
        <div class="card-body py-2">
          <div class="text-muted small">Articulos activos</div>
          <div class="fs-3 fw-bold text-<?= $artBadge ?>" id="articulosCount"><?= count($articulos) ?></div>
          <?php if (count($articulos)): ?><div class="small"><?= count($articulos) ?> tipo(s) registrado(s)</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3" id="assignmentTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $activeTab==='logistica'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#pane-logistica" type="button" role="tab" data-tab-key="logistica">Logistica</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $activeTab==='ataud'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#pane-ataud" type="button" role="tab" data-tab-key="ataud">
        Ataud <span class="badge text-bg-<?= $cajaBadge ?> ms-1" id="badge-ataud"><?= $cajaActiva ? 1 : 0 ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $activeTab==='equipos'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#pane-equipos" type="button" role="tab" data-tab-key="equipos">
        Equipos <span class="badge text-bg-<?= $equBadge ?> ms-1" id="badge-equipos"><?= count($equiposAsignados) ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $activeTab==='articulos'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#pane-articulos" type="button" role="tab" data-tab-key="articulos">
        Articulos <span class="badge text-bg-<?= $artBadge ?> ms-1" id="badge-articulos"><?= count($articulos) ?></span>
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ── LOGISTICA ─────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab==='logistica'?'show active':'' ?>" id="pane-logistica" role="tabpanel">
      <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 m-0 fw-semibold">Preparacion y logistica</h2>
          <span class="small text-muted">Los cambios se guardan sin recargar</span>
        </div>
        <div id="asignacionAlert" class="mb-3"></div>
        <form method="post" action="?r=servicio_asignaciones.guardar&id=<?= (int)$id ?>&tab=logistica" id="jsAsignacionForm">
          <?= csrf_field() ?>
          <input type="hidden" name="tab" value="logistica">
          <div class="row g-3">
            <div class="col-md-6"><?= form_input('fecha_hora_inicio','Fecha y hora de inicio',so_datetime_to_input($asignacion['fecha_hora_inicio'] ?? $svc['fecha_hora_programada'] ?? null),['type'=>'datetime-local']) ?></div>
            <div class="col-md-6"><?= form_select('lugar_tipo','Lugar',so_lugar_tipo_options(),$asignacion['lugar_tipo'] ?? $svc['lugar_tipo'] ?? 'capilla',['class'=>'form-select']) ?></div>
            <div class="col-md-6"><?= form_input('lugar_detalle','Detalle del lugar',$asignacion['lugar_detalle'] ?? $svc['lugar_detalle'] ?? '',['placeholder'=>'Capilla Norte, Sala A, etc.']) ?></div>
            <div class="col-md-6"><?= form_select('id_usuario_auxiliar','Auxiliar asignado',$auxiliares,(string)($asignacion['id_usuario_auxiliar'] ?? $svc['id_usuario_auxiliar'] ?? ''),['class'=>'form-select','placeholder'=>'Sin asignar']) ?></div>
            <div class="col-md-6"><?= form_input('id_vehiculo','Vehiculo asignado',$asignacion['id_vehiculo'] ?? $svc['id_vehiculo'] ?? '',['placeholder'=>'Placa o descripcion']) ?></div>
            <div class="col-md-6"><?= form_input('id_sala','Sala / ubicacion',$asignacion['id_sala'] ?? $svc['id_sala'] ?? '',['placeholder'=>'Capilla 1, Sala A']) ?></div>
            <div class="col-12">
              <label class="form-label" for="instrucciones">Instrucciones</label>
              <textarea class="form-control" name="instrucciones" id="instrucciones" rows="3"><?= e($asignacion['instrucciones'] ?? $svc['notas'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary" id="btnGuardarLogistica">Guardar logistica</button>
          </div>
        </form>
      </div></div>
    </div>

    <!-- ── ATAUD ──────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab==='ataud'?'show active':'' ?>" id="pane-ataud" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-1 fw-semibold">Buscar y asignar ataud</h2>
            <p class="text-muted small mb-3">Busca por codigo o modelo. La asignacion es instantanea.</p>
            <div id="cajaAlert" class="mb-3"><?= $cajasState['error'] ? "<div class='alert alert-warning py-2 mb-0'>".e($cajasState['error'])."</div>" : '' ?></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="cajaSearch">Buscar ataud</label>
                <input type="search" class="form-control" id="cajaSearch" placeholder="Codigo o modelo..." autocomplete="off">
                <div class="form-text" id="cajaSearchStatus"></div>
                <div class="list-group mt-2" id="cajaResults" style="max-height:260px;overflow-y:auto"></div>
              </div>
              <div class="col-md-6">
                <form method="post" action="?r=servicios.asignar_caja&id=<?= (int)$id ?>" id="jsCajaForm">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tab" value="ataud">
                  <?= form_input('codigo','Codigo seleccionado','',['placeholder'=>'Selecciona de la lista','required'=>true]) ?>
                  <?= form_select('tipo_asignacion','Tipo de asignacion',[
                    ''            => 'Automatico segun catalogo',
                    'renta'       => 'Retornable / renta',
                    'permanente'  => 'Permanente',
                  ],'',['class'=>'form-select','id'=>'caja_tipo_asignacion']) ?>
                  <?= form_input('notas','Notas','',['placeholder'=>'Opcional','id'=>'caja_notas']) ?>
                  <button class="btn btn-primary w-100" id="btnAsignarCaja">Asignar ataud</button>
                </form>
              </div>
            </div>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card shadow-sm h-100"><div class="card-body" id="cajaCurrentCard">
            <h2 class="h6 mb-3 fw-semibold">Ataud activo</h2>
            <?php if ($cajaActiva): ?>
              <div class="border rounded p-3 bg-light mb-3">
                <div class="fw-semibold"><?= e($cajaActiva['codigo']) ?></div>
                <div class="text-muted small"><?= e($cajaActiva['modelo'] ?: 'Sin modelo') ?></div>
                <div class="mt-1">
                  <span class="badge text-bg-secondary"><?= e($cajaActiva['tipo_asignacion'] ?? 'permanente') ?></span>
                  <span class="badge text-bg-light border ms-1"><?= e($cajaActiva['ui_estado'] ?? 'Nuevo') ?></span>
                </div>
              </div>
              <form method="post" action="?r=servicio_asignaciones.cancelar_caja&id=<?= (int)$id ?>&tab=ataud" id="jsCajaCancelForm">
                <?= csrf_field() ?>
                <input type="hidden" name="codigo_caja" value="<?= e($cajaActiva['codigo']) ?>">
                <input type="hidden" name="tab" value="ataud">
                <button class="btn btn-outline-danger w-100" id="btnQuitarCaja">Quitar del servicio</button>
              </form>
            <?php else: ?>
              <div class="text-center text-muted py-4">
                <div class="display-6 mb-2">&#9633;</div>
                <div>Sin ataud asignado</div>
              </div>
            <?php endif; ?>
          </div></div>
        </div>
      </div>
    </div>

    <!-- ── EQUIPOS ────────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab==='equipos'?'show active':'' ?>" id="pane-equipos" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-1 fw-semibold">Asignar equipos por grupo</h2>
            <p class="text-muted small mb-3">Indica la cantidad; el sistema asigna piezas disponibles automaticamente.</p>
            <div id="equipoAlert" class="mb-3"></div>
            <div class="mb-3">
              <label class="form-label" for="equipoGroupSearch">Buscar grupo</label>
              <input type="search" class="form-control" id="equipoGroupSearch" placeholder="Sillas, pedestal, candeleros..." autocomplete="off">
              <div class="form-text" id="equipoSearchStatus"></div>
            </div>
            <div id="equipoGroupList" class="vstack gap-2"></div>
            <hr class="my-3">
            <p class="text-muted small mb-2">Asignar pieza por ID exacto</p>
            <form method="post" action="?r=servicios.asignar_equipo&id=<?= (int)$id ?>" id="jsEquipoManualForm" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="text" name="id_equipo" class="form-control form-control-sm" placeholder="ID exacto de la pieza" required>
              <button class="btn btn-sm btn-outline-primary text-nowrap" id="btnAsignarEquipoPieza">Asignar</button>
            </form>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h6 m-0 fw-semibold">Equipos activos</h2>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnSelectAllEquipos">Seleccionar todos</button>
            </div>
            <form method="post" action="?r=servicio_asignaciones.cancelar_equipos&id=<?= (int)$id ?>&tab=equipos" id="jsEquipoCancelForm">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="equipos">
              <div id="equiposAsignadosGrouped" class="mb-2"></div>
              <div id="equiposAsignadosPieces"></div>
              <button class="btn btn-outline-danger btn-sm mt-2" id="btnQuitarEquipos">Quitar seleccionados</button>
            </form>
          </div></div>
        </div>
      </div>
    </div>

    <!-- ── ARTICULOS ──────────────────────────────────── -->
    <div class="tab-pane fade <?= $activeTab==='articulos'?'show active':'' ?>" id="pane-articulos" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-3 fw-semibold">Agregar articulo</h2>
            <div id="articuloAlert" class="mb-3"><?= $articulosState['error'] ? "<div class='alert alert-warning py-2 mb-0'>".e($articulosState['error'])."</div>" : '' ?></div>
            <form method="post" action="?r=servicio_asignaciones.agregar_articulo&id=<?= (int)$id ?>&tab=articulos" id="jsArticuloForm">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="articulos">
              <input type="hidden" name="id_articulo" id="id_articulo">
              <div class="mb-2">
                <label class="form-label" for="articuloSearch">Buscar articulo</label>
                <input type="search" class="form-control" id="articuloSearch" placeholder="Flores, velas, coronas..." autocomplete="off">
                <div class="form-text" id="articuloSearchStatus"></div>
              </div>
              <div class="mb-3" style="max-height:190px;overflow-y:auto;border:1px solid #dee2e6;border-radius:.375rem">
                <div class="list-group list-group-flush" id="articuloList"></div>
              </div>
              <div id="articuloSelected" class="alert alert-info py-2 small mb-3 d-none"></div>
              <div class="row g-2">
                <div class="col-6"><?= form_input('cantidad','Cantidad',1,['type'=>'number','min'=>1,'required'=>true,'id'=>'articulo_cantidad']) ?></div>
                <div class="col-6"><?= form_input('responsable','Responsable',current_user()['usuario'] ?? '',['required'=>true,'id'=>'articulo_responsable']) ?></div>
              </div>
              <?= form_input('notas','Notas','',['placeholder'=>'Descripcion adicional...','id'=>'articulo_notas']) ?>
              <button class="btn btn-primary w-100" id="btnRegistrarArticulo" disabled>Registrar salida</button>
            </form>
          </div></div>
        </div>
        <div class="col-lg-7">
          <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-3 fw-semibold">Articulos activos del servicio</h2>
            <div id="articulosAsignadosBody"></div>
          </div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de confirmacion reutilizable -->
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-body py-3" id="confirmModalBody">¿Confirmar accion?</div>
        <div class="modal-footer py-2 border-0">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger btn-sm" id="confirmModalOk">Confirmar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  const assignmentUi = <?= $uiBootstrap ?: '{}' ?>;

  (() => {
    const idServicio = assignmentUi.idServicio;
    const csrf      = assignmentUi.csrf;
    const invState  = {
      cajas:        Array.isArray(assignmentUi?.inventario?.cajas)        ? assignmentUi.inventario.cajas        : [],
      equiposGrupos:Array.isArray(assignmentUi?.inventario?.equiposGrupos)? assignmentUi.inventario.equiposGrupos: [],
      articulos:    Array.isArray(assignmentUi?.inventario?.articulos)    ? assignmentUi.inventario.articulos    : [],
    };
    const asgState  = {
      cajas:       Array.isArray(assignmentUi?.asignados?.cajas)          ? assignmentUi.asignados.cajas          : [],
      equipos:     Array.isArray(assignmentUi?.asignados?.equipos)        ? assignmentUi.asignados.equipos        : [],
      equiposGrupo:Array.isArray(assignmentUi?.asignados?.equipos_grupo)  ? assignmentUi.asignados.equipos_grupo  : [],
      articulos:   Array.isArray(assignmentUi?.asignados?.articulos)      ? assignmentUi.asignados.articulos      : [],
    };
    let selectedArticuloId = '';
    const pieceCache = {};

    const $ = (id) => document.getElementById(id);
    const els = {
      asignacionAlert: $('asignacionAlert'),
      cajaAlert:       $('cajaAlert'),
      equipoAlert:     $('equipoAlert'),
      articuloAlert:   $('articuloAlert'),
      cajaResults:     $('cajaResults'),
      cajaSearch:      $('cajaSearch'),
      cajaSearchStatus:$('cajaSearchStatus'),
      cajaForm:        $('jsCajaForm'),
      cajaCount:       $('cajasCount'),
      cajaCurrentCard: $('cajaCurrentCard'),
      equipoGroupSearch:$('equipoGroupSearch'),
      equipoGroupList: $('equipoGroupList'),
      equipoSearchStatus:$('equipoSearchStatus'),
      equipoManualForm:$('jsEquipoManualForm'),
      equipoCancelForm:$('jsEquipoCancelForm'),
      equiposGrouped:  $('equiposAsignadosGrouped'),
      equiposPieces:   $('equiposAsignadosPieces'),
      equiposCount:    $('equiposCount'),
      articuloSearch:  $('articuloSearch'),
      articuloSearchStatus:$('articuloSearchStatus'),
      articuloList:    $('articuloList'),
      articuloSelected:$('articuloSelected'),
      articuloHidden:  $('id_articulo'),
      articuloForm:    $('jsArticuloForm'),
      articulosBody:   $('articulosAsignadosBody'),
      articulosCount:  $('articulosCount'),
      asignacionForm:  $('jsAsignacionForm'),
      btnSelectAll:    $('btnSelectAllEquipos'),
      badgeAtaud:      $('badge-ataud'),
      badgeEquipos:    $('badge-equipos'),
      badgeArticulos:  $('badge-articulos'),
    };

    /* ── Utilities ────────────────────────────── */

    function esc(v) {
      return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function setAlert(el, msg, type = 'info') {
      if (!el) return;
      if (!msg) { el.innerHTML = ''; return; }
      el.innerHTML = `<div class="alert alert-${type} alert-dismissible py-2 mb-0">
        ${esc(msg)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
      if (type === 'success') setTimeout(() => { el.querySelector('.alert') && (el.innerHTML = ''); }, 4000);
    }

    function setBusy(form, busy) {
      if (!form) return;
      form.querySelectorAll('button,input,select,textarea').forEach((f) => {
        if (busy) { f.dataset.wd = f.disabled ? '1' : '0'; f.disabled = true; }
        else if (f.dataset.wd === '0') f.disabled = false;
      });
    }

    function showConfirm(msg) {
      return new Promise((resolve) => {
        const modal = $('confirmModal');
        if (!modal) { resolve(window.confirm(msg)); return; }
        $('confirmModalBody').textContent = msg;
        const bsm = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
        const ok  = $('confirmModalOk');
        let settled = false;
        const done = (v) => {
          if (settled) return;
          settled = true;
          ok.removeEventListener('click', onOk);
          resolve(v);
        };
        // done(true) ANTES de hide() para que si Bootstrap dispara hidden.bs.modal
        // sincrónicamente (prefers-reduced-motion, etc.) no resuelva false primero.
        const onOk = () => { done(true); bsm.hide(); };
        ok.addEventListener('click', onOk);
        modal.addEventListener('hidden.bs.modal', () => done(false), { once: true });
        bsm.show();
      });
    }

    async function fetchJson(url, opts = {}) {
      const cfg = { ...opts, headers: { Accept: 'application/json', ...(opts.headers || {}) } };
      if (cfg.body && typeof cfg.body !== 'string') {
        cfg.headers['Content-Type'] = 'application/json';
        cfg.body = JSON.stringify(cfg.body);
      }
      const res  = await fetch(url, cfg);
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) throw new Error(data.message || 'Error al procesar la solicitud.');
      return data;
    }

    document.querySelectorAll('#assignmentTabs [data-tab-key]').forEach((btn) => {
      btn.addEventListener('shown.bs.tab', (e) => {
        const url = new URL(location.href);
        url.searchParams.set('tab', e.target.dataset.tabKey || 'logistica');
        history.replaceState({}, '', url);
      });
    });

    /* ── Snapshot / counters ──────────────────── */

    function syncSnapshot(data) {
      if (Array.isArray(data.cajas_asignadas))          asgState.cajas        = data.cajas_asignadas;
      if (Array.isArray(data.equipos_asignados))        asgState.equipos      = data.equipos_asignados;
      if (Array.isArray(data.equipos_asignados_grupo))  asgState.equiposGrupo = data.equipos_asignados_grupo;
      if (Array.isArray(data.articulos_asignados))      asgState.articulos    = data.articulos_asignados;
      renderCoffin(); renderEquipos(); renderArticulos(); updateBadges();
    }

    function updateBadges() {
      const set = (el, n, cls) => {
        if (!el) return;
        el.textContent = String(n);
        el.className   = `badge ms-1 ${cls}`;
      };
      const cnt = (arr) => arr.length;
      if (els.cajaCount)    els.cajaCount.textContent    = asgState.cajas.length    ? '1' : '0';
      if (els.equiposCount) els.equiposCount.textContent = String(cnt(asgState.equipos));
      if (els.articulosCount) els.articulosCount.textContent = String(cnt(asgState.articulos));
      set(els.badgeAtaud,    asgState.cajas.length    ? 1 : 0, asgState.cajas.length    ? 'text-bg-success' : 'text-bg-secondary');
      set(els.badgeEquipos,  cnt(asgState.equipos),             cnt(asgState.equipos)   ? 'text-bg-success' : 'text-bg-secondary');
      set(els.badgeArticulos,cnt(asgState.articulos),            cnt(asgState.articulos) ? 'text-bg-success' : 'text-bg-secondary');
    }

    /* ── Coffin ───────────────────────────────── */

    function renderCoffinList(items) {
      if (!els.cajaResults) return;
      if (!items.length) {
        els.cajaResults.innerHTML = '<div class="list-group-item text-muted small">Sin ataudes disponibles.</div>';
        return;
      }
      els.cajaResults.innerHTML = items.map((item) => {
        const badge = (Number(item.reciclado||0) || Number(item.veces_usado||0) > 0) ? 'warning text-dark' : 'success';
        const label = badge === 'success' ? 'Nuevo' : 'Usado';
        const tipo  = item.tipo_asignacion_sugerida || 'automatico';
        return `<button type="button" class="list-group-item list-group-item-action py-2"
          data-sel-caja="${esc(item.codigo)}" data-tipo="${esc(tipo)}">
          <div class="fw-semibold">${esc(item.codigo)}</div>
          <div class="d-flex gap-1 mt-1 flex-wrap">
            <span class="badge text-bg-light border">${esc(item.modelo || 'Sin modelo')}</span>
            <span class="badge text-bg-secondary">${esc(tipo)}</span>
            <span class="badge text-bg-${badge}">${label}</span>
          </div>
        </button>`;
      }).join('');
      els.cajaResults.querySelectorAll('[data-sel-caja]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const c = els.cajaForm?.querySelector('[name="codigo"]');
          const t = els.cajaForm?.querySelector('[name="tipo_asignacion"]');
          if (c) c.value = btn.dataset.selCaja;
          if (t && btn.dataset.tipo) t.value = btn.dataset.tipo;
          els.cajaResults.querySelectorAll('.active').forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');
          setAlert(els.cajaAlert, null);
        });
      });
    }

    function attachCoffinCancelHandler() {
      $('jsCajaCancelForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        // Capturar form y datos ANTES del primer await — e.currentTarget se vuelve
        // null después de que el dispatch síncrono termina (estándar del DOM).
        const form      = e.currentTarget;
        const codigoCaja = form?.querySelector('[name="codigo_caja"]')?.value || '';
        if (!await showConfirm('¿Retirar el ataud del servicio?')) return;
        setBusy(form, true);
        try {
          const res = await fetchJson(`?r=servicios_api.cancelar_caja&id_servicio=${idServicio}`, {
            method: 'POST',
            body: { _token: csrf, codigo_caja: codigoCaja },
          });
          syncSnapshot(res.data || {});
          await loadCoffins(els.cajaSearch?.value || '');
          setAlert(els.cajaAlert, res.message || 'Ataud retirado.', 'success');
        } catch (err) {
          setBusy(form, false);
          setAlert(els.cajaAlert, err.message, 'danger');
        }
      });
    }

    function renderCoffin() {
      if (!els.cajaCurrentCard) return;
      const cur = asgState.cajas[0] || null;
      if (!cur) {
        els.cajaCurrentCard.innerHTML = `<h2 class="h6 mb-3 fw-semibold">Ataud activo</h2>
          <div class="text-center text-muted py-4"><div class="display-6 mb-1">&#9633;</div><div>Sin ataud asignado</div></div>`;
        return;
      }
      els.cajaCurrentCard.innerHTML = `
        <h2 class="h6 mb-3 fw-semibold">Ataud activo</h2>
        <div class="border rounded p-3 bg-light mb-3">
          <div class="fw-semibold">${esc(cur.codigo)}</div>
          <div class="text-muted small">${esc(cur.modelo || 'Sin modelo')}</div>
          <div class="mt-1">
            <span class="badge text-bg-secondary">${esc(cur.tipo_asignacion || 'permanente')}</span>
            <span class="badge text-bg-light border ms-1">${esc(cur.ui_estado || 'Nuevo')}</span>
          </div>
        </div>
        <form method="post" action="?r=servicio_asignaciones.cancelar_caja&id=${idServicio}&tab=ataud" id="jsCajaCancelForm">
          <input type="hidden" name="_token" value="${esc(csrf)}">
          <input type="hidden" name="codigo_caja" value="${esc(cur.codigo)}">
          <input type="hidden" name="tab" value="ataud">
          <button class="btn btn-outline-danger w-100">Quitar del servicio</button>
        </form>`;
      attachCoffinCancelHandler();
    }

    /* ── Equipment ────────────────────────────── */

    function renderGruposDisp(items) {
      if (!els.equipoGroupList) return;
      if (!items.length) {
        els.equipoGroupList.innerHTML = '<div class="text-muted small">Sin grupos disponibles.</div>';
        return;
      }
      els.equipoGroupList.innerHTML = items.map((g) => `
        <div class="border rounded p-3" data-gcard="${esc(g.equipo)}">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
              <div class="fw-semibold">${esc(g.equipo)}</div>
              <div class="small text-muted">${esc(g.disponibles)} disponibles</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <input type="number" class="form-control form-control-sm" min="1" max="${esc(g.disponibles)}" value="1" style="max-width:72px" data-gqty="${esc(g.equipo)}">
              <button type="button" class="btn btn-sm btn-primary" data-gasign="${esc(g.equipo)}">Asignar</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-gtoggle="${esc(g.equipo)}">IDs</button>
            </div>
          </div>
          <div class="small text-muted mt-1" data-gstatus="${esc(g.equipo)}"></div>
          <div class="mt-2 d-none" data-gpieces="${esc(g.equipo)}"></div>
        </div>`).join('');

      els.equipoGroupList.querySelectorAll('[data-gasign]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const grp  = btn.dataset.gasign;
          const qty  = Number(els.equipoGroupList.querySelector(`[data-gqty="${CSS.escape(grp)}"]`)?.value || 0);
          if (qty < 1) { setAlert(els.equipoAlert, 'Indica una cantidad mayor a 0.', 'warning'); return; }
          btn.disabled = true;
          const prevText = btn.textContent;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
          try {
            const res = await fetchJson(`?r=servicios_api.asignar_equipo&id_servicio=${idServicio}`, {
              method: 'POST',
              body: { _token: csrf, equipo_grupo: grp, cantidad: qty, observaciones: `Asignacion por grupo: ${grp}` },
            });
            syncSnapshot(res.data || {});
            await loadEquipmentGroups(els.equipoGroupSearch?.value || '');
            setAlert(els.equipoAlert, res.message || 'Equipos asignados.', 'success');
          } catch (err) {
            setAlert(els.equipoAlert, err.message, 'danger');
            btn.disabled = false;
            btn.textContent = prevText;
          }
        });
      });

      els.equipoGroupList.querySelectorAll('[data-gtoggle]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const grp    = btn.dataset.gtoggle;
          const target = els.equipoGroupList.querySelector(`[data-gpieces="${CSS.escape(grp)}"]`);
          const status = els.equipoGroupList.querySelector(`[data-gstatus="${CSS.escape(grp)}"]`);
          if (!target) return;
          if (!target.classList.contains('d-none')) {
            target.classList.add('d-none'); btn.textContent = 'IDs'; return;
          }
          if (!pieceCache[grp]) {
            status.textContent = 'Cargando...';
            try {
              const res = await fetchJson(`?r=inventario_api.disponibilidad&tipo=equipos&grupo=${encodeURIComponent(grp)}&limit=120`);
              pieceCache[grp] = Array.isArray(res.data?.items) ? res.data.items : [];
            } catch (err) { status.textContent = err.message; return; }
          }
          const pzas = pieceCache[grp];
          status.textContent = pzas.length ? `${pzas.length} piezas disponibles` : 'Sin piezas disponibles';
          target.innerHTML   = pzas.length
            ? `<div class="small font-monospace text-muted">${pzas.map((p) => esc(p.id_equipo)).join(', ')}</div>`
            : '<div class="text-muted small">Sin piezas.</div>';
          target.classList.remove('d-none');
          btn.textContent = 'Ocultar';
        });
      });
    }

    function renderEquipos() {
      if (els.equiposGrouped) {
        els.equiposGrouped.innerHTML = asgState.equiposGrupo.length
          ? asgState.equiposGrupo.map((g) => `
              <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                <span class="fw-semibold">${esc(g.equipo)}</span>
                <span class="badge text-bg-primary">${esc(g.cantidad)}</span>
              </div>`).join('')
          : '<div class="text-muted small">Sin equipos activos.</div>';
      }
      if (els.equiposPieces) {
        if (!asgState.equipos.length) {
          els.equiposPieces.innerHTML = '';
        } else {
          els.equiposPieces.innerHTML = `
            <div class="table-responsive mt-2">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                  <th style="width:32px"><input type="checkbox" id="chkAll" class="form-check-input"></th>
                  <th>Grupo</th><th>ID</th>
                </tr></thead>
                <tbody>${asgState.equipos.map((it) => `
                  <tr>
                    <td><input class="form-check-input" type="checkbox" name="ids_equipo[]" value="${esc(it.id_equipo)}"></td>
                    <td class="small">${esc(it.equipo || '—')}</td>
                    <td><code class="small">${esc(it.id_equipo)}</code></td>
                  </tr>`).join('')}
                </tbody>
              </table>
            </div>`;
          $('chkAll')?.addEventListener('change', (e) => {
            document.querySelectorAll('[name="ids_equipo[]"]').forEach((c) => (c.checked = e.target.checked));
          });
        }
      }
    }

    /* ── Articles ─────────────────────────────── */

    function renderArticuloList(items) {
      if (!els.articuloList) return;
      if (!items.length) {
        els.articuloList.innerHTML = '<div class="list-group-item text-muted small">Sin articulos con stock.</div>';
        return;
      }
      els.articuloList.innerHTML = items.map((it) => {
        const active = String(it.id) === String(selectedArticuloId) ? ' active' : '';
        const badgeCls = active ? 'text-bg-light text-dark' : 'text-bg-secondary';
        return `<button type="button" class="list-group-item list-group-item-action py-2 d-flex justify-content-between align-items-center${active}"
          data-aid="${esc(it.id)}" data-anom="${esc(it.articulo)}">
          <span>${esc(it.articulo)}</span>
          <span class="badge ${badgeCls} ms-2 flex-shrink-0">Stock: ${esc(it.existencias)}</span>
        </button>`;
      }).join('');
      els.articuloList.querySelectorAll('[data-aid]').forEach((btn) => {
        btn.addEventListener('click', () => {
          selectedArticuloId = btn.dataset.aid;
          if (els.articuloHidden) els.articuloHidden.value = selectedArticuloId;
          if (els.articuloSelected) {
            els.articuloSelected.textContent = 'Seleccionado: ' + btn.dataset.anom;
            els.articuloSelected.classList.remove('d-none');
          }
          const btnReg = $('btnRegistrarArticulo');
          if (btnReg) btnReg.disabled = false;
          // re-render to update highlights without fetching again
          const current = Array.from(els.articuloList.querySelectorAll('[data-aid]')).map((b) => ({
            id: b.dataset.aid,
            articulo: b.dataset.anom,
            existencias: b.querySelector('.badge')?.textContent?.replace('Stock: ','') || '?',
          }));
          renderArticuloList(current);
        });
      });
    }

    function filterArticulos(q = '') {
      const term  = q.trim().toLowerCase();
      const items = term
        ? invState.articulos.filter((it) => it.articulo?.toLowerCase().includes(term) || String(it.id).includes(term))
        : invState.articulos.slice();
      renderArticuloList(items);
      if (els.articuloSearchStatus) els.articuloSearchStatus.textContent = items.length ? `${items.length} articulo(s) con stock` : 'Sin coincidencias.';
    }

    function renderArticulos() {
      if (!els.articulosBody) return;
      if (!asgState.articulos.length) {
        els.articulosBody.innerHTML = '<div class="text-center text-muted py-4">Sin articulos activos en este servicio.</div>';
        return;
      }
      els.articulosBody.innerHTML = `
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Articulo</th><th>Cant.</th><th>Responsable</th><th>Fecha</th><th></th></tr></thead>
            <tbody>${asgState.articulos.map((it) => `
              <tr>
                <td class="fw-semibold">${esc(it.articulo || '')}</td>
                <td>${esc(it.cantidad || 0)}</td>
                <td class="text-muted small">${esc(it.responsable || '—')}</td>
                <td class="text-muted small">${esc((it.created_at || '').slice(0,16).replace('T',' '))}</td>
                <td>
                  <form class="jsCancel d-flex gap-1 align-items-center justify-content-end">
                    <input type="hidden" name="id_sa" value="${esc(it.id_servicio_articulo)}">
                    <input type="number" name="cantidad" min="1" max="${esc(it.cantidad||1)}" value="1" class="form-control form-control-sm" style="max-width:64px">
                    <button class="btn btn-sm btn-outline-danger">Quitar</button>
                  </form>
                </td>
              </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
      els.articulosBody.querySelectorAll('.jsCancel').forEach((form) => {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!await showConfirm('¿Retirar este articulo del servicio?')) return;
          const btn = form.querySelector('button');
          setBusy(form, true);
          try {
            const res = await fetchJson(`?r=servicios_api.cancelar_consumo&id_servicio=${idServicio}`, {
              method: 'POST',
              body: { _token: csrf, id_servicio_articulo: form.querySelector('[name="id_sa"]')?.value || '', cantidad: form.querySelector('[name="cantidad"]')?.value || '1' },
            });
            syncSnapshot(res.data || {});
            await loadArticulos();
            setAlert(els.articuloAlert, res.message || 'Articulo retirado.', 'success');
          } catch (err) {
            setBusy(form, false);
            setAlert(els.articuloAlert, err.message, 'danger');
          }
        });
      });
    }

    /* ── Loaders ──────────────────────────────── */

    async function loadCoffins(q = '') {
      try {
        const res = await fetchJson(`?r=inventario_api.disponibilidad&tipo=cajas&q=${encodeURIComponent(q)}&limit=20`);
        invState.cajas = Array.isArray(res.data?.items) ? res.data.items : [];
        renderCoffinList(invState.cajas);
        if (els.cajaSearchStatus) els.cajaSearchStatus.textContent = invState.cajas.length ? `${invState.cajas.length} ataudes disponibles` : 'Sin ataudes disponibles.';
      } catch (err) { setAlert(els.cajaAlert, err.message, 'danger'); }
    }

    async function loadEquipmentGroups(q = '') {
      try {
        const res = await fetchJson(`?r=inventario_api.disponibilidad&tipo=equipos&modo=grupos&q=${encodeURIComponent(q)}&limit=50`);
        invState.equiposGrupos = Array.isArray(res.data?.items) ? res.data.items : [];
        renderGruposDisp(invState.equiposGrupos);
        if (els.equipoSearchStatus) els.equipoSearchStatus.textContent = invState.equiposGrupos.length ? `${invState.equiposGrupos.length} grupos disponibles` : 'Sin grupos.';
      } catch (err) { setAlert(els.equipoAlert, err.message, 'danger'); }
    }

    async function loadArticulos() {
      try {
        const res = await fetchJson(`?r=inventario_api.disponibilidad&tipo=articulos&q=${encodeURIComponent(els.articuloSearch?.value||'')}&limit=100`);
        invState.articulos = Array.isArray(res.data?.items) ? res.data.items : [];
        filterArticulos(els.articuloSearch?.value || '');
      } catch (err) { setAlert(els.articuloAlert, err.message, 'danger'); }
    }

    /* ── Form handlers ────────────────────────── */

    async function handleJson(event, url, buildBody, alertEl, okMsg) {
      event.preventDefault();
      const form = event.currentTarget;
      setBusy(form, true);
      try {
        const res = await fetchJson(url, { method: 'POST', body: buildBody(form) });
        syncSnapshot(res.data || {});
        setAlert(alertEl, res.message || okMsg, 'success');
        return res;
      } catch (err) {
        setAlert(alertEl, err.message, 'danger');
        throw err;
      } finally {
        setBusy(form, false);
      }
    }

    // Logistica
    els.asignacionForm?.addEventListener('submit', (e) => handleJson(e,
      `?r=servicios_api.guardar_asignacion&id_servicio=${idServicio}`,
      (f) => ({
        _token: csrf,
        fecha_hora_inicio: f.querySelector('[name="fecha_hora_inicio"]')?.value || '',
        lugar_tipo:        f.querySelector('[name="lugar_tipo"]')?.value || '',
        lugar_detalle:     f.querySelector('[name="lugar_detalle"]')?.value || '',
        id_usuario_auxiliar: f.querySelector('[name="id_usuario_auxiliar"]')?.value || '',
        id_vehiculo:       f.querySelector('[name="id_vehiculo"]')?.value || '',
        id_sala:           f.querySelector('[name="id_sala"]')?.value || '',
        instrucciones:     f.querySelector('[name="instrucciones"]')?.value || '',
      }), els.asignacionAlert, 'Logistica actualizada.'));

    // Asignar ataud
    els.cajaForm?.addEventListener('submit', async (e) => {
      try {
        await handleJson(e, `?r=servicios_api.asignar_caja&id_servicio=${idServicio}`,
          (f) => ({ _token: csrf, codigo: f.querySelector('[name="codigo"]')?.value || '', tipo_asignacion: f.querySelector('[name="tipo_asignacion"]')?.value || '', observaciones: f.querySelector('[name="notas"]')?.value || '' }),
          els.cajaAlert, 'Ataud asignado.');
        await loadCoffins(els.cajaSearch?.value || '');
        els.cajaResults?.querySelectorAll('.active').forEach((b) => b.classList.remove('active'));
      } catch (_) {}
    });

    // Asignar equipo por ID
    els.equipoManualForm?.addEventListener('submit', async (e) => {
      const localForm = e.currentTarget;
      try {
        await handleJson(e, `?r=servicios_api.asignar_equipo&id_servicio=${idServicio}`,
          (f) => ({ _token: csrf, id_equipo: f.querySelector('[name="id_equipo"]')?.value || '' }),
          els.equipoAlert, 'Equipo asignado.');
        await loadEquipmentGroups(els.equipoGroupSearch?.value || '');
        localForm.reset();
      } catch (_) {}
    });

    // Cancelar equipos
    els.equipoCancelForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      // Capturar form y datos ANTES del primer await.
      const form    = e.currentTarget;
      const checked = [...form.querySelectorAll('[name="ids_equipo[]"]:checked')];
      if (!checked.length) { setAlert(els.equipoAlert, 'Selecciona al menos una pieza para retirar.', 'warning'); return; }
      if (!await showConfirm(`¿Retirar ${checked.length} pieza(s) del servicio?`)) return;
      setBusy(form, true);
      try {
        const res = await fetchJson(`?r=servicios_api.cancelar_equipos&id_servicio=${idServicio}`, {
          method: 'POST',
          body: { _token: csrf, ids_equipo: checked.map((c) => c.value) },
        });
        syncSnapshot(res.data || {});
        await loadEquipmentGroups(els.equipoGroupSearch?.value || '');
        setAlert(els.equipoAlert, res.message || 'Equipos retirados.', 'success');
      } catch (err) {
        setAlert(els.equipoAlert, err.message, 'danger');
      } finally {
        setBusy(form, false);
      }
    });

    // Agregar articulo
    els.articuloForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectedArticuloId) { setAlert(els.articuloAlert, 'Selecciona un articulo de la lista.', 'warning'); return; }
      const localForm = e.currentTarget;
      setBusy(localForm, true);
      try {
        const res = await fetchJson(`?r=servicios_api.consumos&id_servicio=${idServicio}`, {
          method: 'POST',
          body: {
            _token: csrf,
            responsable: localForm.querySelector('[name="responsable"]')?.value || '',
            observaciones: localForm.querySelector('[name="notas"]')?.value || '',
            items: [{ id_articulo: selectedArticuloId, cantidad: localForm.querySelector('[name="cantidad"]')?.value || '1' }],
          },
        });
        syncSnapshot(res.data || {});
        await loadArticulos();
        // Reset selection
        selectedArticuloId = '';
        if (els.articuloHidden) els.articuloHidden.value = '';
        if (els.articuloSelected) els.articuloSelected.classList.add('d-none');
        const btnReg = $('btnRegistrarArticulo');
        if (btnReg) btnReg.disabled = true;
        localForm.querySelector('[name="cantidad"]').value = '1';
        localForm.querySelector('[name="notas"]').value    = '';
        setAlert(els.articuloAlert, res.message || 'Articulo registrado.', 'success');
      } catch (err) {
        setAlert(els.articuloAlert, err.message, 'danger');
      } finally {
        setBusy(localForm, false);
      }
    });

    // Select-all equipos
    els.btnSelectAll?.addEventListener('click', () => {
      const chks = [...document.querySelectorAll('[name="ids_equipo[]"]')];
      const all  = chks.every((c) => c.checked);
      chks.forEach((c) => (c.checked = !all));
    });

    /* ── Search debounce ──────────────────────── */

    let tCaja = null, tEquipo = null;
    els.cajaSearch?.addEventListener('input', () => { clearTimeout(tCaja); tCaja = setTimeout(() => loadCoffins(els.cajaSearch.value), 200); });
    els.equipoGroupSearch?.addEventListener('input', () => { clearTimeout(tEquipo); tEquipo = setTimeout(() => loadEquipmentGroups(els.equipoGroupSearch.value), 200); });
    els.articuloSearch?.addEventListener('input', () => filterArticulos(els.articuloSearch.value));

    /* ── Init ─────────────────────────────────── */

    renderCoffin();
    renderEquipos();
    renderArticulos();
    renderCoffinList(invState.cajas);
    renderGruposDisp(invState.equiposGrupos);
    filterArticulos('');
    updateBadges();
    // NOTE: attachCoffinCancelHandler is called inside renderCoffin() — no extra call needed here
  })();
  </script>
  <?php
  render('Asignacion del servicio', ob_get_clean());
}
