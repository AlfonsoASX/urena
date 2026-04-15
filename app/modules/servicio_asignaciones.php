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

  <div id="assignmentGlobalAlert" class="mb-3"></div>

  <div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="text-muted small">Ataud activo</div><div class="fs-4 fw-semibold" id="cajasCount"><?= $cajaActiva ? 1 : 0 ?></div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="text-muted small">Equipos activos</div><div class="fs-4 fw-semibold" id="equiposCount"><?= count($equiposAsignados) ?></div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 bg-light h-100"><div class="card-body"><div class="text-muted small">Articulos activos</div><div class="fs-4 fw-semibold" id="articulosCount"><?= count($articulos) ?></div></div></div></div>
  </div>

  <ul class="nav nav-tabs mb-3" id="assignmentTabs" role="tablist">
    <?php foreach (['logistica' => 'Logistica', 'ataud' => 'Ataud', 'equipos' => 'Equipos', 'articulos' => 'Articulos'] as $key => $label): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === $key ? 'active' : '' ?>" id="tab-<?= e($key) ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= e($key) ?>" type="button" role="tab" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>" data-tab-key="<?= e($key) ?>"><?= e($label) ?></button>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade <?= $activeTab === 'logistica' ? 'show active' : '' ?>" id="pane-logistica" role="tabpanel">
      <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 m-0">Preparacion y logistica</h2>
          <span class="small text-muted">Guardado rapido sin salir de la pantalla</span>
        </div>
        <div id="asignacionAlert" class="mb-3"></div>
        <form method="post" action="?r=servicio_asignaciones.guardar&id=<?= (int)$id ?>&tab=logistica" id="jsAsignacionForm">
          <?= csrf_field() ?>
          <input type="hidden" name="tab" value="logistica">
          <?= form_input('fecha_hora_inicio','Fecha y hora de inicio',so_datetime_to_input($asignacion['fecha_hora_inicio'] ?? $svc['fecha_hora_programada'] ?? null),['type'=>'datetime-local']) ?>
          <?= form_select('lugar_tipo','Lugar',so_lugar_tipo_options(),$asignacion['lugar_tipo'] ?? $svc['lugar_tipo'] ?? 'capilla',['class'=>'form-select']) ?>
          <?= form_input('lugar_detalle','Detalle del lugar',$asignacion['lugar_detalle'] ?? $svc['lugar_detalle'] ?? '') ?>
          <?= form_select('id_usuario_auxiliar','Auxiliar asignado',$auxiliares,(string)($asignacion['id_usuario_auxiliar'] ?? $svc['id_usuario_auxiliar'] ?? ''),['class'=>'form-select']) ?>
          <?= form_input('id_vehiculo','Vehiculo asignado',$asignacion['id_vehiculo'] ?? $svc['id_vehiculo'] ?? '',['placeholder'=>'Placa o descripcion']) ?>
          <?= form_input('id_sala','Sala / ubicacion',$asignacion['id_sala'] ?? $svc['id_sala'] ?? '',['placeholder'=>'Capilla 1, Sala A']) ?>
          <div class="mb-3">
            <label class="form-label" for="instrucciones">Instrucciones</label>
            <textarea class="form-control" name="instrucciones" id="instrucciones" rows="4"><?= e($asignacion['instrucciones'] ?? $svc['notas'] ?? '') ?></textarea>
          </div>
          <button class="btn btn-primary">Guardar logistica</button>
        </form>
      </div></div>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'ataud' ? 'show active' : '' ?>" id="pane-ataud" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h2 class="h6 m-0">Buscar y asignar ataud</h2>
                <div class="small text-muted">Busca por codigo o modelo. La asignacion se hace sin recargar.</div>
              </div>
            </div>
            <div id="cajaAlert" class="mb-3"><?= $cajasState['error'] ? "<div class='alert alert-warning py-2 mb-0'>".e($cajasState['error'])."</div>" : '' ?></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="cajaSearch">Buscar ataud</label>
                <input type="search" class="form-control" id="cajaSearch" placeholder="Codigo o modelo">
                <div class="form-text" id="cajaSearchStatus">Mostrando ataudes disponibles.</div>
                <div class="list-group mt-2" id="cajaResults"></div>
              </div>
              <div class="col-md-6">
                <form method="post" action="?r=servicios.asignar_caja&id=<?= (int)$id ?>" id="jsCajaForm">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tab" value="ataud">
                  <?= form_input('codigo','Codigo del ataud','',['placeholder'=>'Selecciona abajo o escribe el codigo exacto','required'=>true]) ?>
                  <?= form_select('tipo_asignacion','Tipo de asignacion',[
                    '' => 'Automatico segun catalogo',
                    'renta' => 'Retornable / renta',
                    'permanente' => 'Permanente',
                  ],'',['class'=>'form-select','id'=>'caja_tipo_asignacion']) ?>
                  <?= form_input('notas','Notas','',['placeholder'=>'Opcional','id'=>'caja_notas']) ?>
                  <button class="btn btn-outline-primary">Asignar ataud</button>
                </form>
              </div>
            </div>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card shadow-sm"><div class="card-body" id="cajaCurrentCard">
            <h2 class="h6 mb-3">Ataud activo</h2>
            <?php if ($cajaActiva): ?>
              <div class="border rounded p-3 bg-light mb-3">
                <div class="fw-semibold"><?= e($cajaActiva['codigo'].' · '.($cajaActiva['modelo'] ?: 'Sin modelo')) ?></div>
                <div class="small text-muted mt-1"><?= e($cajaActiva['tipo_asignacion'] ?: 'permanente') ?> · <span class="badge text-bg-secondary"><?= e($cajaActiva['ui_estado'] ?? 'Nuevo') ?></span></div>
              </div>
              <form method="post" action="?r=servicio_asignaciones.cancelar_caja&id=<?= (int)$id ?>&tab=ataud" id="jsCajaCancelForm">
                <?= csrf_field() ?>
                <input type="hidden" name="codigo_caja" value="<?= e($cajaActiva['codigo']) ?>">
                <input type="hidden" name="tab" value="ataud">
                <button class="btn btn-outline-danger">Quitar del servicio</button>
              </form>
            <?php else: ?>
              <div class="text-muted">No hay ataud activo.</div>
            <?php endif; ?>
          </div></div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'equipos' ? 'show active' : '' ?>" id="pane-equipos" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h2 class="h6 m-0">Asignar equipos por grupo</h2>
                <div class="small text-muted">Captura la cantidad deseada y el sistema toma piezas reales. Puedes expandir cada grupo para ver los IDs.</div>
              </div>
            </div>
            <div id="equipoAlert" class="mb-3"></div>
            <div class="mb-3">
              <label class="form-label" for="equipoGroupSearch">Buscar grupo</label>
              <input type="search" class="form-control" id="equipoGroupSearch" placeholder="Sillas, pedestal, candeleros...">
              <div class="form-text" id="equipoSearchStatus">Mostrando grupos disponibles.</div>
            </div>
            <form method="post" action="?r=servicios.asignar_equipo&id=<?= (int)$id ?>" id="jsEquipoManualForm" class="row g-2 mb-3">
              <?= csrf_field() ?>
              <div class="col-md-8">
                <?= form_input('id_equipo','Asignar por ID de pieza','',['placeholder'=>'Fallback sin JS: ID exacto del equipo']) ?>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-outline-primary w-100 mb-2">Asignar pieza</button>
              </div>
            </form>
            <div id="equipoGroupList" class="vstack gap-3"></div>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="h6 m-0">Equipos activos</h2>
              <span class="small text-muted">Selecciona piezas para retirarlas</span>
            </div>
            <form method="post" action="?r=servicio_asignaciones.cancelar_equipos&id=<?= (int)$id ?>&tab=equipos" id="jsEquipoCancelForm">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="equipos">
              <div id="equiposAsignadosGrouped"></div>
              <div id="equiposAsignadosPieces" class="table-responsive mt-3"></div>
              <button class="btn btn-outline-danger mt-3">Quitar seleccionados</button>
            </form>
          </div></div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'articulos' ? 'show active' : '' ?>" id="pane-articulos" role="tabpanel">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-3">Agregar articulos</h2>
            <div id="articuloAlert" class="mb-3"><?= $articulosState['error'] ? "<div class='alert alert-warning py-2 mb-0'>".e($articulosState['error'])."</div>" : '' ?></div>
            <form method="post" action="?r=servicio_asignaciones.agregar_articulo&id=<?= (int)$id ?>&tab=articulos" id="jsArticuloForm">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="articulos">
              <div class="mb-2">
                <label class="form-label" for="articuloSearch">Buscar articulo</label>
                <input type="search" class="form-control" id="articuloSearch" placeholder="Flores, velas, etc.">
                <div class="form-text" id="articuloSearchStatus">Mostrando articulos con stock.</div>
              </div>
              <div class="mb-2">
                <label class="form-label" for="id_articulo">Articulo</label>
                <select name="id_articulo" id="id_articulo" class="form-select" required size="7">
                  <option value="">Selecciona</option>
                  <?php foreach ($articulosDisp as $articulo): ?>
                    <option value="<?= (int)$articulo['id'] ?>"><?= e($articulo['articulo'].' · stock '.$articulo['existencias']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?= form_input('cantidad','Cantidad',1,['type'=>'number','min'=>1,'required'=>true,'id'=>'articulo_cantidad']) ?>
              <?= form_input('responsable','Responsable',current_user()['usuario'] ?? '',['required'=>true,'id'=>'articulo_responsable']) ?>
              <?= form_input('notas','Notas','',['placeholder'=>'Flores, velas, etc.','id'=>'articulo_notas']) ?>
              <button class="btn btn-primary">Registrar salida</button>
            </form>
          </div></div>
        </div>
        <div class="col-lg-7">
          <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-3">Articulos activos del servicio</h2>
            <div id="articulosAsignadosBody"></div>
          </div></div>
        </div>
      </div>
    </div>
  </div>

  <script>
  const assignmentUi = <?= $uiBootstrap ?: '{}' ?>;

  (() => {
    const idServicio = assignmentUi.idServicio;
    const csrf = assignmentUi.csrf;
    const inventoryState = {
      cajas: Array.isArray(assignmentUi?.inventario?.cajas) ? assignmentUi.inventario.cajas : [],
      equiposGrupos: Array.isArray(assignmentUi?.inventario?.equiposGrupos) ? assignmentUi.inventario.equiposGrupos : [],
      articulos: Array.isArray(assignmentUi?.inventario?.articulos) ? assignmentUi.inventario.articulos : [],
    };
    const assignedState = {
      cajas: Array.isArray(assignmentUi?.asignados?.cajas) ? assignmentUi.asignados.cajas : [],
      equipos: Array.isArray(assignmentUi?.asignados?.equipos) ? assignmentUi.asignados.equipos : [],
      equiposGrupo: Array.isArray(assignmentUi?.asignados?.equipos_grupo) ? assignmentUi.asignados.equipos_grupo : [],
      articulos: Array.isArray(assignmentUi?.asignados?.articulos) ? assignmentUi.asignados.articulos : [],
    };
    let activeTab = assignmentUi.activeTab || 'logistica';
    const pieceCache = {};

    const els = {
      globalAlert: document.getElementById('assignmentGlobalAlert'),
      asignacionAlert: document.getElementById('asignacionAlert'),
      cajaAlert: document.getElementById('cajaAlert'),
      equipoAlert: document.getElementById('equipoAlert'),
      articuloAlert: document.getElementById('articuloAlert'),
      cajaResults: document.getElementById('cajaResults'),
      cajaSearch: document.getElementById('cajaSearch'),
      cajaSearchStatus: document.getElementById('cajaSearchStatus'),
      cajaForm: document.getElementById('jsCajaForm'),
      cajaCount: document.getElementById('cajasCount'),
      cajaCurrentCard: document.getElementById('cajaCurrentCard'),
      equipoGroupSearch: document.getElementById('equipoGroupSearch'),
      equipoGroupList: document.getElementById('equipoGroupList'),
      equipoSearchStatus: document.getElementById('equipoSearchStatus'),
      equipoManualForm: document.getElementById('jsEquipoManualForm'),
      equipoCancelForm: document.getElementById('jsEquipoCancelForm'),
      equiposAsignadosGrouped: document.getElementById('equiposAsignadosGrouped'),
      equiposAsignadosPieces: document.getElementById('equiposAsignadosPieces'),
      equiposCount: document.getElementById('equiposCount'),
      articuloSearch: document.getElementById('articuloSearch'),
      articuloSearchStatus: document.getElementById('articuloSearchStatus'),
      articuloSelect: document.getElementById('id_articulo'),
      articuloForm: document.getElementById('jsArticuloForm'),
      articulosAsignadosBody: document.getElementById('articulosAsignadosBody'),
      articulosCount: document.getElementById('articulosCount'),
      asignacionForm: document.getElementById('jsAsignacionForm'),
    };

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function setAlert(target, message, type = 'info') {
      if (!target) return;
      target.innerHTML = message ? `<div class="alert alert-${type} py-2 mb-0">${escapeHtml(message)}</div>` : '';
    }

    function setBusy(form, busy) {
      if (!form) return;
      form.querySelectorAll('button, input, select, textarea').forEach((field) => {
        if (busy) {
          field.dataset.wasDisabled = field.disabled ? '1' : '0';
          field.disabled = true;
        } else if (field.dataset.wasDisabled === '0') {
          field.disabled = false;
        }
      });
    }

    async function fetchJson(url, options = {}) {
      const config = { ...options };
      config.headers = { Accept: 'application/json', ...(config.headers || {}) };
      if (config.body && typeof config.body !== 'string') {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(config.body);
      }
      const response = await fetch(url, config);
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.ok === false) {
        throw new Error(data.message || 'No se pudo completar la operacion.');
      }
      return data;
    }

    function updateHistoryTab(tabKey) {
      activeTab = tabKey;
      const url = new URL(window.location.href);
      url.searchParams.set('tab', tabKey);
      window.history.replaceState({}, '', url.toString());
    }

    document.querySelectorAll('#assignmentTabs [data-tab-key]').forEach((button) => {
      button.addEventListener('shown.bs.tab', (event) => {
        updateHistoryTab(event.target.dataset.tabKey || 'logistica');
      });
    });

    function syncSnapshot(data) {
      assignedState.cajas = Array.isArray(data.cajas_asignadas) ? data.cajas_asignadas : assignedState.cajas;
      assignedState.equipos = Array.isArray(data.equipos_asignados) ? data.equipos_asignados : assignedState.equipos;
      assignedState.equiposGrupo = Array.isArray(data.equipos_asignados_grupo) ? data.equipos_asignados_grupo : assignedState.equiposGrupo;
      assignedState.articulos = Array.isArray(data.articulos_asignados) ? data.articulos_asignados : assignedState.articulos;
      renderAssignedCoffin();
      renderAssignedEquipment();
      renderAssignedArticles();
      refreshCounters();
    }

    function refreshCounters() {
      if (els.cajaCount) els.cajaCount.textContent = assignedState.cajas.length ? '1' : '0';
      if (els.equiposCount) els.equiposCount.textContent = String(assignedState.equipos.length);
      if (els.articulosCount) els.articulosCount.textContent = String(assignedState.articulos.length);
    }

    function renderCoffinResults(items) {
      if (!els.cajaResults) return;
      if (!items.length) {
        els.cajaResults.innerHTML = '<div class="text-muted small py-2">Sin resultados.</div>';
        return;
      }
      els.cajaResults.innerHTML = items.map((item) => {
        const uiBadge = Number(item.reciclado || 0) === 1 || Number(item.veces_usado || 0) > 0 ? 'Usado' : 'Nuevo';
        return `
          <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start" data-select-caja="${escapeHtml(item.codigo)}" data-tipo="${escapeHtml(item.tipo_asignacion_sugerida || '')}">
            <div>
              <div class="fw-semibold">${escapeHtml(item.codigo)} · ${escapeHtml(item.modelo || 'Sin modelo')}</div>
              <div class="small text-muted">${escapeHtml(item.tipo_asignacion_sugerida || 'automatico')} · ${escapeHtml(uiBadge)}</div>
            </div>
          </button>`;
      }).join('');
      els.cajaResults.querySelectorAll('[data-select-caja]').forEach((button) => {
        button.addEventListener('click', () => {
          const codigoField = els.cajaForm?.querySelector('[name="codigo"]');
          const tipoField = els.cajaForm?.querySelector('[name="tipo_asignacion"]');
          if (codigoField) codigoField.value = button.dataset.selectCaja || '';
          if (tipoField && button.dataset.tipo) tipoField.value = button.dataset.tipo;
          setAlert(els.cajaAlert, `Ataud ${button.dataset.selectCaja || ''} listo para asignar.`, 'info');
        });
      });
    }

    function attachCancelCoffinHandler() {
      document.getElementById('jsCajaCancelForm')?.addEventListener('submit', async (event) => {
        await handleJsonForm(event, `?r=servicios_api.cancelar_caja&id_servicio=${idServicio}`, (form) => ({
          _token: csrf,
          codigo_caja: form.querySelector('[name="codigo_caja"]')?.value || '',
        }), els.cajaAlert, 'Ataud retirado del servicio.');
        await loadCoffins(els.cajaSearch?.value || '');
      });
    }

    function renderAssignedCoffin() {
      if (!els.cajaCurrentCard) return;
      const current = assignedState.cajas[0] || null;
      if (!current) {
        els.cajaCurrentCard.innerHTML = '<h2 class="h6 mb-3">Ataud activo</h2><div class="text-muted">No hay ataud activo.</div>';
        return;
      }
      els.cajaCurrentCard.innerHTML = `
        <h2 class="h6 mb-3">Ataud activo</h2>
        <div class="border rounded p-3 bg-light mb-3">
          <div class="fw-semibold">${escapeHtml(current.codigo)} · ${escapeHtml(current.modelo || 'Sin modelo')}</div>
          <div class="small text-muted mt-1">${escapeHtml(current.tipo_asignacion || 'permanente')} · <span class="badge text-bg-secondary">${escapeHtml(current.ui_estado || 'Nuevo')}</span></div>
        </div>
        <form method="post" action="?r=servicio_asignaciones.cancelar_caja&id=${idServicio}&tab=ataud" id="jsCajaCancelForm">
          <input type="hidden" name="_token" value="${escapeHtml(csrf)}">
          <input type="hidden" name="codigo_caja" value="${escapeHtml(current.codigo)}">
          <input type="hidden" name="tab" value="ataud">
          <button class="btn btn-outline-danger">Quitar del servicio</button>
        </form>`;
      attachCancelCoffinHandler();
    }

    function renderEquipmentGroups(items) {
      if (!els.equipoGroupList) return;
      if (!items.length) {
        els.equipoGroupList.innerHTML = '<div class="text-muted">No hay grupos disponibles.</div>';
        return;
      }
      els.equipoGroupList.innerHTML = items.map((group) => `
        <div class="border rounded p-3" data-group-card="${escapeHtml(group.equipo)}">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
              <div class="fw-semibold">${escapeHtml(group.equipo)}</div>
              <div class="small text-muted">${escapeHtml(group.disponibles)} disponibles</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <input type="number" class="form-control form-control-sm" min="1" value="1" style="max-width: 90px;" data-group-qty="${escapeHtml(group.equipo)}">
              <button type="button" class="btn btn-sm btn-outline-primary" data-group-assign="${escapeHtml(group.equipo)}">Asignar</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-group-toggle="${escapeHtml(group.equipo)}">Ver IDs</button>
            </div>
          </div>
          <div class="small text-muted mt-2" data-group-status="${escapeHtml(group.equipo)}"></div>
          <div class="mt-2 d-none" data-group-pieces="${escapeHtml(group.equipo)}"></div>
        </div>
      `).join('');

      els.equipoGroupList.querySelectorAll('[data-group-assign]').forEach((button) => {
        button.addEventListener('click', async () => {
          const group = button.dataset.groupAssign || '';
          const qtyField = els.equipoGroupList.querySelector(`[data-group-qty="${CSS.escape(group)}"]`);
          const cantidad = Number(qtyField?.value || 0);
          try {
            setAlert(els.equipoAlert, 'Asignando equipo...', 'info');
            button.disabled = true;
            const response = await fetchJson(`?r=servicios_api.asignar_equipo&id_servicio=${idServicio}`, {
              method: 'POST',
              body: { _token: csrf, equipo_grupo: group, cantidad, observaciones: `Asignacion por grupo: ${group}` },
            });
            syncSnapshot(response.data || {});
            await loadEquipmentGroups(els.equipoGroupSearch?.value || '');
            setAlert(els.equipoAlert, response.message || 'Equipos asignados correctamente.', 'success');
          } catch (error) {
            setAlert(els.equipoAlert, error.message, 'danger');
          } finally {
            button.disabled = false;
          }
        });
      });

      els.equipoGroupList.querySelectorAll('[data-group-toggle]').forEach((button) => {
        button.addEventListener('click', async () => {
          const group = button.dataset.groupToggle || '';
          const target = els.equipoGroupList.querySelector(`[data-group-pieces="${CSS.escape(group)}"]`);
          const status = els.equipoGroupList.querySelector(`[data-group-status="${CSS.escape(group)}"]`);
          if (!target) return;
          if (!target.classList.contains('d-none')) {
            target.classList.add('d-none');
            button.textContent = 'Ver IDs';
            return;
          }
          if (!pieceCache[group]) {
            status.textContent = 'Cargando IDs disponibles...';
            try {
              const response = await fetchJson(`?r=inventario_api.disponibilidad&tipo=equipos&grupo=${encodeURIComponent(group)}&limit=120`);
              pieceCache[group] = Array.isArray(response.data?.items) ? response.data.items : [];
            } catch (error) {
              status.textContent = error.message;
              return;
            }
          }
          status.textContent = pieceCache[group].length ? `Piezas disponibles: ${pieceCache[group].length}` : 'Sin piezas disponibles.';
          target.innerHTML = pieceCache[group].length
            ? `<div class="small text-muted">${pieceCache[group].map((piece) => escapeHtml(piece.id_equipo)).join(', ')}</div>`
            : '<div class="text-muted small">Sin piezas disponibles.</div>';
          target.classList.remove('d-none');
          button.textContent = 'Ocultar IDs';
        });
      });
    }

    function renderAssignedEquipment() {
      if (els.equiposAsignadosGrouped) {
        if (!assignedState.equiposGrupo.length) {
          els.equiposAsignadosGrouped.innerHTML = '<div class="text-muted">No hay equipos activos.</div>';
        } else {
          els.equiposAsignadosGrouped.innerHTML = assignedState.equiposGrupo.map((group) => `
            <div class="border rounded p-2 mb-2 bg-light">
              <div class="fw-semibold">${escapeHtml(group.equipo)} · ${escapeHtml(group.cantidad)}</div>
              <div class="small text-muted">IDs: ${escapeHtml((group.ids || []).join(', '))}</div>
            </div>`).join('');
        }
      }
      if (els.equiposAsignadosPieces) {
        if (!assignedState.equipos.length) {
          els.equiposAsignadosPieces.innerHTML = '<div class="text-muted">No hay piezas activas.</div>';
        } else {
          els.equiposAsignadosPieces.innerHTML = `
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th></th><th>Grupo</th><th>ID pieza</th></tr></thead>
              <tbody>
                ${assignedState.equipos.map((item) => `
                  <tr>
                    <td><input class="form-check-input" type="checkbox" name="ids_equipo[]" value="${escapeHtml(item.id_equipo)}"></td>
                    <td>${escapeHtml(item.equipo || 'Equipo')}</td>
                    <td><code>${escapeHtml(item.id_equipo)}</code></td>
                  </tr>`).join('')}
              </tbody>
            </table>`;
        }
      }
    }

    function renderArticleOptions(items) {
      if (!els.articuloSelect) return;
      const currentValue = els.articuloSelect.value;
      const options = ['<option value="">Selecciona</option>'].concat(items.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.articulo)} · stock ${escapeHtml(item.existencias)}</option>`));
      els.articuloSelect.innerHTML = options.join('');
      if (currentValue) els.articuloSelect.value = currentValue;
    }

    function renderAssignedArticles() {
      if (!els.articulosAsignadosBody) return;
      if (!assignedState.articulos.length) {
        els.articulosAsignadosBody.innerHTML = '<div class="text-muted">Sin articulos activos.</div>';
        return;
      }
      els.articulosAsignadosBody.innerHTML = `
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead><tr><th>Articulo</th><th>Cantidad</th><th>Responsable</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
              ${assignedState.articulos.map((item) => `
                <tr>
                  <td>${escapeHtml(item.articulo || '')}</td>
                  <td>${escapeHtml(item.cantidad || 0)}</td>
                  <td>${escapeHtml(item.responsable || '—')}</td>
                  <td>${escapeHtml((item.created_at || '').slice(0,16).replace('T', ' '))}</td>
                  <td>
                    <form method="post" action="?r=servicio_asignaciones.cancelar_articulo&id=${idServicio}&tab=articulos" class="jsArticuloCancelInline d-flex gap-2 align-items-center justify-content-end">
                      <input type="hidden" name="_token" value="${escapeHtml(csrf)}">
                      <input type="hidden" name="id_servicio_articulo" value="${escapeHtml(item.id_servicio_articulo)}">
                      <input type="hidden" name="tab" value="articulos">
                      <input type="number" name="cantidad" min="1" max="${escapeHtml(item.cantidad || 1)}" value="1" class="form-control form-control-sm" style="max-width: 80px;">
                      <button class="btn btn-sm btn-outline-danger">Quitar</button>
                    </form>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
      els.articulosAsignadosBody.querySelectorAll('.jsArticuloCancelInline').forEach((form) => {
        form.addEventListener('submit', async (event) => {
          await handleJsonForm(event, `?r=servicios_api.cancelar_consumo&id_servicio=${idServicio}`, (localForm) => ({
            _token: csrf,
            id_servicio_articulo: localForm.querySelector('[name="id_servicio_articulo"]')?.value || '',
            cantidad: localForm.querySelector('[name="cantidad"]')?.value || '1',
          }), els.articuloAlert, 'Articulo retirado del servicio.');
          await loadArticleInventory();
        });
      });
    }

    async function loadCoffins(query = '') {
      try {
        const response = await fetchJson(`?r=inventario_api.disponibilidad&tipo=cajas&q=${encodeURIComponent(query)}&limit=20`);
        inventoryState.cajas = Array.isArray(response.data?.items) ? response.data.items : [];
        renderCoffinResults(inventoryState.cajas);
        if (els.cajaSearchStatus) {
          els.cajaSearchStatus.textContent = inventoryState.cajas.length ? `${inventoryState.cajas.length} opciones disponibles.` : 'Sin opciones disponibles.';
        }
      } catch (error) {
        setAlert(els.cajaAlert, error.message, 'danger');
      }
    }

    async function loadEquipmentGroups(query = '') {
      try {
        const response = await fetchJson(`?r=inventario_api.disponibilidad&tipo=equipos&modo=grupos&q=${encodeURIComponent(query)}&limit=50`);
        inventoryState.equiposGrupos = Array.isArray(response.data?.items) ? response.data.items : [];
        renderEquipmentGroups(inventoryState.equiposGrupos);
        if (els.equipoSearchStatus) {
          els.equipoSearchStatus.textContent = inventoryState.equiposGrupos.length ? `${inventoryState.equiposGrupos.length} grupos disponibles.` : 'Sin grupos disponibles.';
        }
      } catch (error) {
        setAlert(els.equipoAlert, error.message, 'danger');
      }
    }

    function filterArticles(query = '') {
      const term = String(query || '').trim().toLowerCase();
      const items = term
        ? inventoryState.articulos.filter((item) => String(item.articulo || '').toLowerCase().includes(term) || String(item.id || '').includes(term))
        : inventoryState.articulos.slice();
      renderArticleOptions(items);
      if (els.articuloSearchStatus) {
        els.articuloSearchStatus.textContent = items.length ? `${items.length} articulos con stock.` : 'Sin articulos coincidentes.';
      }
    }

    async function handleJsonForm(event, url, bodyBuilder, successAlertTarget, successMessage) {
      event.preventDefault();
      const form = event.currentTarget;
      setBusy(form, true);
      try {
        const response = await fetchJson(url, { method: 'POST', body: bodyBuilder(form) });
        syncSnapshot(response.data || {});
        setAlert(successAlertTarget, response.message || successMessage, 'success');
      } catch (error) {
        setAlert(successAlertTarget, error.message, 'danger');
      } finally {
        setBusy(form, false);
      }
    }

    els.asignacionForm?.addEventListener('submit', (event) => handleJsonForm(event, `?r=servicios_api.guardar_asignacion&id_servicio=${idServicio}`, (form) => ({
      _token: csrf,
      fecha_hora_inicio: form.querySelector('[name="fecha_hora_inicio"]')?.value || '',
      lugar_tipo: form.querySelector('[name="lugar_tipo"]')?.value || '',
      lugar_detalle: form.querySelector('[name="lugar_detalle"]')?.value || '',
      id_usuario_auxiliar: form.querySelector('[name="id_usuario_auxiliar"]')?.value || '',
      id_vehiculo: form.querySelector('[name="id_vehiculo"]')?.value || '',
      id_sala: form.querySelector('[name="id_sala"]')?.value || '',
      instrucciones: form.querySelector('[name="instrucciones"]')?.value || '',
    }), els.asignacionAlert, 'Logistica actualizada.'));

    els.cajaForm?.addEventListener('submit', async (event) => {
      await handleJsonForm(event, `?r=servicios_api.asignar_caja&id_servicio=${idServicio}`, (form) => ({
        _token: csrf,
        codigo: form.querySelector('[name="codigo"]')?.value || '',
        tipo_asignacion: form.querySelector('[name="tipo_asignacion"]')?.value || '',
        observaciones: form.querySelector('[name="notas"]')?.value || '',
      }), els.cajaAlert, 'Ataud asignado correctamente.');
      await loadCoffins(els.cajaSearch?.value || '');
    });

    els.equipoManualForm?.addEventListener('submit', async (event) => {
      await handleJsonForm(event, `?r=servicios_api.asignar_equipo&id_servicio=${idServicio}`, (form) => ({
        _token: csrf,
        id_equipo: form.querySelector('[name="id_equipo"]')?.value || '',
      }), els.equipoAlert, 'Equipo asignado correctamente.');
      await loadEquipmentGroups(els.equipoGroupSearch?.value || '');
      form.reset();
    });

    els.equipoCancelForm?.addEventListener('submit', async (event) => {
      await handleJsonForm(event, `?r=servicios_api.cancelar_equipos&id_servicio=${idServicio}`, (form) => ({
        _token: csrf,
        ids_equipo: Array.from(form.querySelectorAll('input[name="ids_equipo[]"]:checked')).map((field) => field.value),
      }), els.equipoAlert, 'Equipos retirados del servicio.');
      await loadEquipmentGroups(els.equipoGroupSearch?.value || '');
    });

    els.articuloForm?.addEventListener('submit', async (event) => {
      await handleJsonForm(event, `?r=servicios_api.consumos&id_servicio=${idServicio}`, (form) => ({
        _token: csrf,
        responsable: form.querySelector('[name="responsable"]')?.value || '',
        observaciones: form.querySelector('[name="notas"]')?.value || '',
        items: [{
          id_articulo: form.querySelector('[name="id_articulo"]')?.value || '',
          cantidad: form.querySelector('[name="cantidad"]')?.value || '1',
        }],
      }), els.articuloAlert, 'Articulo agregado correctamente.');
      await loadArticleInventory();
      form.querySelector('[name="cantidad"]').value = '1';
      form.querySelector('[name="notas"]').value = '';
    });

    async function loadArticleInventory() {
      try {
        const response = await fetchJson(`?r=inventario_api.disponibilidad&tipo=articulos&q=${encodeURIComponent(els.articuloSearch?.value || '')}&limit=100`);
        inventoryState.articulos = Array.isArray(response.data?.items) ? response.data.items : [];
        filterArticles(els.articuloSearch?.value || '');
      } catch (error) {
        setAlert(els.articuloAlert, error.message, 'danger');
      }
    }

    let cajaTimer = null;
    els.cajaSearch?.addEventListener('input', () => {
      clearTimeout(cajaTimer);
      cajaTimer = setTimeout(() => loadCoffins(els.cajaSearch.value || ''), 180);
    });

    let equipoTimer = null;
    els.equipoGroupSearch?.addEventListener('input', () => {
      clearTimeout(equipoTimer);
      equipoTimer = setTimeout(() => loadEquipmentGroups(els.equipoGroupSearch.value || ''), 180);
    });

    els.articuloSearch?.addEventListener('input', () => {
      filterArticles(els.articuloSearch.value || '');
    });

    renderAssignedCoffin();
    renderAssignedEquipment();
    renderAssignedArticles();
    renderCoffinResults(inventoryState.cajas);
    renderEquipmentGroups(inventoryState.equiposGrupos);
    renderArticleOptions(inventoryState.articulos);
    filterArticles('');
    refreshCounters();
    attachCancelCoffinHandler();
  })();
  </script>
  <?php
  render('Asignacion del servicio', ob_get_clean());
}
