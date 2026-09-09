<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();

$action = $action ?? 'listar';
$roles = ['administradora','auxiliar','vendedor','coordinador','caja'];
require_role($roles);

function servicios_date_to_input(?string $value): string {
  if (!$value) return '';
  $ts = strtotime($value);
  if (!$ts) return '';
  return date('Y-m-d', $ts);
}

function servicios_form_defaults(): array {
  return [
    'fallecido_nombre' => '',
    'fallecido_fecha' => '',
    'fallecido_municipio' => '',
    'fallecido_hospital' => '',
    'fallecido_hospital_direccion' => '',
    'fallecido_dom_velacion' => '',
    'contratante_nombre' => '',
    'contratante_tel' => '',
    'contratante_email' => '',
    'contratante_direccion' => '',
    'tipo_servicio' => 'funerario',
    'tipo_disposicion' => 'inhumacion',
    'responsable' => '',
    'origen_tipo' => 'directo',
    'id_contrato_futuro' => '',
    'id_usuario_vendedor' => '',
    'fecha_hora_programada' => '',
    'lugar_tipo' => 'capilla',
    'lugar_detalle' => '',
    'id_usuario_auxiliar' => '',
    'auxiliares' => '',
    'monto_total' => '0.00',
    'anticipo_inicial' => '0.00',
    'forma_pago_inicial' => 'efectivo',
    'requiere_factura' => 0,
    'observaciones_comerciales' => '',
    'notas' => '',
  ];
}

function servicios_form_values(array $source): array {
  $values = servicios_form_defaults();
  foreach ($values as $key => $_) {
    if (array_key_exists($key, $source)) {
      $values[$key] = $source[$key];
    }
  }
  if (!empty($source['fecha_hora_programada'])) {
    $values['fecha_hora_programada'] = so_datetime_to_input($source['fecha_hora_programada']);
  }
  if (!empty($source['fallecido_fecha'])) {
    $values['fallecido_fecha'] = servicios_date_to_input($source['fallecido_fecha']);
  }
  if (!empty($source['fallecido'])) {
    $values['fallecido_nombre'] = $source['fallecido'];
  }
  $values['requiere_factura'] = !empty($source['requiere_factura']) ? 1 : 0;
  return $values;
}

function servicios_collect_form_data(): array {
  $origenTipo = trim($_POST['origen_tipo'] ?? 'directo');
  if ($origenTipo !== 'futuro') {
    $origenTipo = 'directo';
  }

  return [
    'fallecido_nombre' => trim($_POST['fallecido_nombre'] ?? ''),
    'fallecido_fecha' => trim($_POST['fallecido_fecha'] ?? '') ?: null,
    'fallecido_municipio' => trim($_POST['fallecido_municipio'] ?? ''),
    'fallecido_hospital' => trim($_POST['fallecido_hospital'] ?? '') ?: null,
    'fallecido_hospital_direccion' => trim($_POST['fallecido_hospital_direccion'] ?? '') ?: null,
    'fallecido_dom_velacion' => trim($_POST['fallecido_dom_velacion'] ?? ''),
    'contratante_nombre' => trim($_POST['contratante_nombre'] ?? ''),
    'contratante_tel' => trim($_POST['contratante_tel'] ?? '') ?: null,
    'contratante_email' => trim($_POST['contratante_email'] ?? '') ?: null,
    'contratante_direccion' => trim($_POST['contratante_direccion'] ?? '') ?: null,
    'tipo_servicio' => trim($_POST['tipo_servicio'] ?? ''),
    'tipo_disposicion' => trim($_POST['tipo_disposicion'] ?? ''),
    'responsable' => trim($_POST['responsable'] ?? ''),
    'origen_tipo' => $origenTipo,
    'id_contrato_futuro' => $origenTipo === 'futuro' ? ((int)($_POST['id_contrato_futuro'] ?? 0) ?: null) : null,
    'id_usuario_vendedor' => (int)($_POST['id_usuario_vendedor'] ?? 0) ?: null,
    'fecha_hora_programada' => trim($_POST['fecha_hora_programada'] ?? '') ?: null,
    'lugar_tipo' => trim($_POST['lugar_tipo'] ?? '') ?: null,
    'lugar_detalle' => trim($_POST['lugar_detalle'] ?? '') ?: null,
    'id_usuario_auxiliar' => (int)($_POST['id_usuario_auxiliar'] ?? 0) ?: null,
    'auxiliares' => trim($_POST['auxiliares'] ?? ''),
    'monto_total' => so_parse_money($_POST['monto_total'] ?? 0),
    'anticipo_inicial' => so_parse_money($_POST['anticipo_inicial'] ?? 0),
    'forma_pago_inicial' => trim($_POST['forma_pago_inicial'] ?? 'efectivo'),
    'requiere_factura' => so_parse_checkbox($_POST['requiere_factura'] ?? null),
    'observaciones_comerciales' => trim($_POST['observaciones_comerciales'] ?? '') ?: null,
    'notas' => trim($_POST['notas'] ?? '') ?: null,
  ];
}

function servicios_validate_form_data(array $data): ?string {
  if (
    $data['fallecido_nombre'] === '' ||
    $data['fallecido_municipio'] === '' ||
    $data['fallecido_dom_velacion'] === '' ||
    $data['tipo_servicio'] === '' ||
    $data['tipo_disposicion'] === '' ||
    $data['responsable'] === '' ||
    $data['contratante_nombre'] === ''
  ) {
    return 'Completa los campos obligatorios del fallecido, contratante y servicio.';
  }
  if ($data['origen_tipo'] === 'futuro' && empty($data['id_contrato_futuro'])) {
    return 'Selecciona el contrato de futuros relacionado.';
  }
  return null;
}

function servicios_render_form(string $title, string $actionUrl, array $values, bool $isEdit = false, array $svc = []): void {
  $contratos = so_contratos_futuros_options();
  $vendedores = so_user_options(['vendedor']);
  $auxiliares = so_user_options(['auxiliar']);
  $totales = !empty($svc['id_servicio']) ? so_servicio_totales((int)$svc['id_servicio']) : ['pagado' => 0, 'saldo' => 0];

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0"><?= e($title) ?></h1>
      <div class="text-muted small">Captura organizada por secciones para registrar el servicio completo en una sola pantalla.</div>
    </div>
    <a class="btn btn-light btn-sm" href="?r=servicios.listar">Volver</a>
  </div>

  <form method="post" action="<?= e($actionUrl) ?>">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">1. Datos del fallecido</h2>
        <div class="row g-3">
          <div class="col-lg-6"><?= form_input('fallecido_nombre','Nombre del fallecido',$values['fallecido_nombre'],['required'=>true]) ?></div>
          <div class="col-lg-3"><?= form_input('fallecido_fecha','Fecha del fallecimiento',$values['fallecido_fecha'],['type'=>'date']) ?></div>
          <div class="col-lg-3"><?= form_input('fallecido_municipio','Municipio',$values['fallecido_municipio'],['required'=>true]) ?></div>
          <div class="col-lg-6"><?= form_input('fallecido_hospital','Hospital',$values['fallecido_hospital']) ?></div>
          <div class="col-lg-6"><?= form_input('fallecido_hospital_direccion','Direccion del hospital',$values['fallecido_hospital_direccion']) ?></div>
          <div class="col-12"><?= form_input('fallecido_dom_velacion','Domicilio de velacion',$values['fallecido_dom_velacion'],['required'=>true]) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">2. Contratante y contacto</h2>
        <div class="row g-3">
          <div class="col-lg-6"><?= form_input('contratante_nombre','Contratante',$values['contratante_nombre'],['required'=>true]) ?></div>
          <div class="col-lg-3"><?= form_input('contratante_tel','Telefono',$values['contratante_tel']) ?></div>
          <div class="col-lg-3"><?= form_input('contratante_email','Correo',$values['contratante_email'],['type'=>'email']) ?></div>
          <div class="col-12"><?= form_input('contratante_direccion','Direccion',$values['contratante_direccion']) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">3. Datos del servicio</h2>
        <div class="row g-3">
          <div class="col-lg-3"><?= form_select('tipo_servicio','Tipo de servicio',[
            'funerario' => 'Funerario',
            'capilla' => 'Capilla',
            'traslado' => 'Traslado',
            'otro' => 'Otro',
          ],$values['tipo_servicio'],['class'=>'form-select','required'=>true]) ?></div>
          <div class="col-lg-3"><?= form_select('tipo_disposicion','Tipo de disposicion',[
            'cremacion' => 'Cremacion',
            'inhumacion' => 'Inhumacion',
          ],$values['tipo_disposicion'],['class'=>'form-select','required'=>true]) ?></div>
          <div class="col-lg-3"><?= form_select('origen_tipo','Origen del servicio',[
            'directo' => 'Directo',
            'futuro' => 'Derivado de futuros',
          ],$values['origen_tipo'],['class'=>'form-select','required'=>true]) ?></div>
          <div class="col-lg-3"><?= form_select('id_contrato_futuro','Contrato de futuros relacionado',$contratos,(string)$values['id_contrato_futuro'],['class'=>'form-select','placeholder'=>'Sin vincular']) ?></div>
          <div class="col-lg-4"><?= form_input('responsable','Responsable general',$values['responsable'],['required'=>true]) ?></div>
          <div class="col-lg-4"><?= form_select('id_usuario_vendedor','Vendedor / Promotor',$vendedores,(string)$values['id_usuario_vendedor'],['class'=>'form-select']) ?></div>
          <div class="col-lg-4"><?= form_input('auxiliares','Auxiliares (texto libre)',$values['auxiliares']) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">4. Programacion y logistica</h2>
        <div class="row g-3">
          <div class="col-lg-4"><?= form_input('fecha_hora_programada','Fecha y hora programada',$values['fecha_hora_programada'],['type'=>'datetime-local']) ?></div>
          <div class="col-lg-4"><?= form_select('lugar_tipo','Lugar de velacion',so_lugar_tipo_options(),$values['lugar_tipo'],['class'=>'form-select']) ?></div>
          <div class="col-lg-4"><?= form_select('id_usuario_auxiliar','Auxiliar principal',$auxiliares,(string)$values['id_usuario_auxiliar'],['class'=>'form-select']) ?></div>
          <div class="col-12"><?= form_input('lugar_detalle','Detalle del lugar',$values['lugar_detalle'],['placeholder'=>'Capilla, domicilio o iglesia']) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3">5. Importe y facturacion</h2>
        <div class="row g-3">
          <div class="col-lg-3"><?= form_input('monto_total','Monto total',$values['monto_total'],['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
          <div class="col-lg-3">
            <?php if (!$isEdit): ?>
              <?= form_input('anticipo_inicial','Anticipo inicial',$values['anticipo_inicial'],['type'=>'number','step'=>'0.01','min'=>'0']) ?>
            <?php else: ?>
              <label class="form-label d-block">Estado de pago</label>
              <div class="alert alert-light mb-0">
                Pagado actual: <strong><?= e(so_moneda($totales['pagado'])) ?></strong><br>
                Saldo pendiente: <strong><?= e(so_moneda($totales['saldo'])) ?></strong>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-lg-3"><?= form_select('forma_pago_inicial','Forma de pago inicial',so_servicio_tipo_pago_options(),$values['forma_pago_inicial'],['class'=>'form-select']) ?></div>
          <div class="col-lg-3 d-flex align-items-center">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="requiere_factura" id="requiere_factura" value="1" <?= !empty($values['requiere_factura']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="requiere_factura">Requiere factura</label>
            </div>
          </div>
          <div class="col-12"><?= form_input('observaciones_comerciales','Observaciones comerciales',$values['observaciones_comerciales']) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">6. Notas operativas</h2>
        <div class="mb-0">
          <label class="form-label" for="notas">Notas</label>
          <textarea class="form-control" name="notas" id="notas" rows="4"><?= e($values['notas']) ?></textarea>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary"><?= $isEdit ? 'Actualizar servicio' : 'Guardar servicio' ?></button>
      <a class="btn btn-outline-secondary" href="?r=servicios.listar">Cancelar</a>
    </div>
  </form>
  <?php
  render($title, ob_get_clean());
}

switch ($action) {
case 'listar':
  $tab = $_GET['tab'] ?? 'activos';
  if ($tab === 'abiertos') $tab = 'activos';
  if ($tab === 'cerrados') $tab = 'finalizados';
  $tab = in_array($tab, ['activos','finalizados','cancelados','todos'], true) ? $tab : 'activos';
  $q = trim($_GET['q'] ?? '');

  $where = ["COALESCE(s.eliminado,0)=0"];
  $params = [];
  if ($tab === 'activos') {
    $where[] = so_servicio_activos_sql();
  } elseif ($tab === 'finalizados') {
    $where[] = "COALESCE(s.estatus,'registrado') IN ('finalizado','cerrado')";
  } elseif ($tab === 'cancelados') {
    $where[] = "COALESCE(s.estatus,'registrado') = 'cancelado'";
  }
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(s.folio LIKE ? OR s.contratante_nombre LIKE ? OR s.responsable LIKE ? OR COALESCE(s.fallecido_nombre,'') LIKE ? OR s.id_servicio = ?)";
    array_push($params, $like, $like, $like, $like, ctype_digit($q) ? (int)$q : -1);
  }

  $rows = qall("
    SELECT s.id_servicio, s.folio, s.estatus, s.origen_tipo, s.tipo_servicio, s.tipo_disposicion,
           s.contratante_nombre, s.responsable, s.fecha_hora_programada, s.monto_total, s.saldo_pendiente,
           DATE(s.created_at) AS fecha,
           COALESCE(s.fallecido_nombre,'') AS fallecido
    FROM servicios s
    WHERE ".implode(' AND ', $where)."
    ORDER BY COALESCE(s.fecha_hora_programada, s.created_at) DESC, s.id_servicio DESC
    LIMIT 300
  ", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Servicios funerarios</h1>
    <div class="d-flex gap-2">
      <a href="?r=servicios.nuevo" class="btn btn-success btn-sm">Nuevo servicio</a>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='activos'?'active':'' ?>" href="?r=servicios.listar&tab=activos">Activos</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='finalizados'?'active':'' ?>" href="?r=servicios.listar&tab=finalizados">Finalizados</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='cancelados'?'active':'' ?>" href="?r=servicios.listar&tab=cancelados">Cancelados</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='todos'?'active':'' ?>" href="?r=servicios.listar&tab=todos">Todos</a></li>
  </ul>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="servicios.listar">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-10">
      <?= form_input('q','Buscar',$q,['placeholder'=>'Folio, fallecido, contratante, responsable']) ?>
    </div>
    <div class="col-md-2 d-grid align-items-end">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fallecido</th>
          <th>Fecha</th>
          <th>Origen</th>
          <th>Estatus</th>
          <th>Contratante</th>
          <th>Total</th>
          <th>Saldo</th>
          <th style="width:280px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><strong><?= e($row['folio'] ?: '#'.$row['id_servicio']) ?></strong></td>
          <td><?= e($row['fallecido'] ?: '—') ?></td>
          <td><?= e($row['fecha_hora_programada'] ? date('Y-m-d H:i', strtotime($row['fecha_hora_programada'])) : $row['fecha']) ?></td>
          <td><span class="badge bg-light text-dark text-uppercase"><?= e($row['origen_tipo'] ?: 'directo') ?></span></td>
          <td><span class="badge bg-<?= so_servicio_badge($row['estatus'] ?? '') ?>"><?= e(ucwords(str_replace('_', ' ', so_servicio_estatus_norm($row['estatus'] ?? '')))) ?></span></td>
          <td><?= e($row['contratante_nombre']) ?></td>
          <td><?= e(so_moneda($row['monto_total'] ?? 0)) ?></td>
          <td><?= e(so_moneda($row['saldo_pendiente'] ?? 0)) ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="?r=servicios.ver&id=<?= (int)$row['id_servicio'] ?>">Abrir</a>
            <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.editar&id=<?= (int)$row['id_servicio'] ?>">Editar</a>
            <a class="btn btn-outline-info btn-sm" href="?r=servicio_pagos.listar&id=<?= (int)$row['id_servicio'] ?>">Pagos</a>
            <a class="btn btn-outline-success btn-sm" href="?r=servicio_cierres.ver&id=<?= (int)$row['id_servicio'] ?>">Cierre</a>
            <a class="btn btn-outline-danger btn-sm" href="?r=servicios.borrar&id=<?= (int)$row['id_servicio'] ?>&tab=<?= e($tab) ?>" onclick="return confirm('¿Borrar lógicamente este servicio?');">Borrar</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Servicios funerarios', ob_get_clean());
  break;

case 'nuevo':
  servicios_render_form('Nuevo servicio', '?r=servicios.guardar', servicios_form_defaults());
  break;

case 'guardar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicios.nuevo'); }
  $data = servicios_collect_form_data();
  $error = servicios_validate_form_data($data);
  if ($error) {
    flash("<div class='alert alert-danger'>".e($error)."</div>");
    redirect('servicios.nuevo');
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $folio = so_generar_folio($pdo);

    q("
      INSERT INTO servicios (
        folio, tipo_servicio, tipo_disposicion, responsable, auxiliares,
        contratante_nombre, contratante_tel, contratante_email, contratante_direccion,
        fallecido_nombre, fallecido_fecha, fallecido_municipio, fallecido_hospital,
        fallecido_hospital_direccion, fallecido_dom_velacion,
        notas, origen_tipo, id_contrato_futuro, estatus, fecha_hora_programada,
        lugar_tipo, lugar_detalle, id_usuario_vendedor, id_usuario_auxiliar,
        monto_total, anticipo, saldo_pendiente, forma_pago_inicial, requiere_factura,
        observaciones_comerciales, eliminado, created_at, updated_at
      ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'registrado', ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()
      )
    ", [
      $folio, $data['tipo_servicio'], $data['tipo_disposicion'], $data['responsable'], $data['auxiliares'],
      $data['contratante_nombre'], $data['contratante_tel'], $data['contratante_email'], $data['contratante_direccion'],
      $data['fallecido_nombre'], $data['fallecido_fecha'], $data['fallecido_municipio'], $data['fallecido_hospital'],
      $data['fallecido_hospital_direccion'], $data['fallecido_dom_velacion'],
      $data['notas'], $data['origen_tipo'], $data['id_contrato_futuro'], $data['fecha_hora_programada'],
      $data['lugar_tipo'], $data['lugar_detalle'], $data['id_usuario_vendedor'], $data['id_usuario_auxiliar'],
      $data['monto_total'], $data['monto_total'], $data['forma_pago_inicial'] ?: null, $data['requiere_factura'], $data['observaciones_comerciales']
    ]);
    $id_servicio = (int)$pdo->lastInsertId();

    if ($data['fecha_hora_programada'] || $data['lugar_tipo'] || $data['lugar_detalle'] || $data['id_usuario_auxiliar']) {
      so_guardar_asignacion($id_servicio, [
        'fecha_hora_inicio' => $data['fecha_hora_programada'],
        'lugar_tipo' => $data['lugar_tipo'],
        'lugar_detalle' => $data['lugar_detalle'],
        'id_usuario_auxiliar' => $data['id_usuario_auxiliar'],
        'id_vehiculo' => null,
        'id_sala' => null,
        'instrucciones' => $data['notas'],
      ]);
      q("UPDATE servicios SET estatus='registrado' WHERE id_servicio=?", [$id_servicio]);
    }

    if ($data['anticipo_inicial'] > 0) {
      so_guardar_pago($pdo, $id_servicio, $data['anticipo_inicial'], $data['forma_pago_inicial'], 'anticipo', null, 'Anticipo inicial del servicio');
    } else {
      so_recalcular_saldo($id_servicio);
    }

    so_log_bitacora($id_servicio, 'creacion', 'Servicio creado', [
      'folio' => $folio,
      'origen_tipo' => $data['origen_tipo'],
      'monto_total' => $data['monto_total'],
      'fallecido_nombre' => $data['fallecido_nombre'],
    ]);

    $pdo->commit();
    flash("<div class='alert alert-success'>Servicio creado con folio ".e($folio).".</div>");
    redirect('servicios.ver&id='.$id_servicio);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo crear el servicio: ".e($e->getMessage())."</div>");
    redirect('servicios.nuevo');
  }
  break;

case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  servicios_render_form('Editar servicio', '?r=servicios.actualizar&id='.$id, servicios_form_values($svc), true, $svc);
  break;

case 'actualizar':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicios.editar&id='.$id); }
  $data = servicios_collect_form_data();
  $error = servicios_validate_form_data($data);
  if ($error) {
    flash("<div class='alert alert-danger'>".e($error)."</div>");
    redirect('servicios.editar&id='.$id);
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("
      UPDATE servicios
      SET tipo_servicio=?, tipo_disposicion=?, responsable=?, auxiliares=?,
          contratante_nombre=?, contratante_tel=?, contratante_email=?, contratante_direccion=?,
          fallecido_nombre=?, fallecido_fecha=?, fallecido_municipio=?, fallecido_hospital=?,
          fallecido_hospital_direccion=?, fallecido_dom_velacion=?,
          notas=?, origen_tipo=?, id_contrato_futuro=?, fecha_hora_programada=?,
          lugar_tipo=?, lugar_detalle=?, id_usuario_vendedor=?, id_usuario_auxiliar=?,
          monto_total=?, forma_pago_inicial=?, requiere_factura=?, observaciones_comerciales=?,
          updated_at=CURRENT_TIMESTAMP()
      WHERE id_servicio=?
    ", [
      $data['tipo_servicio'], $data['tipo_disposicion'], $data['responsable'], $data['auxiliares'],
      $data['contratante_nombre'], $data['contratante_tel'], $data['contratante_email'], $data['contratante_direccion'],
      $data['fallecido_nombre'], $data['fallecido_fecha'], $data['fallecido_municipio'], $data['fallecido_hospital'],
      $data['fallecido_hospital_direccion'], $data['fallecido_dom_velacion'],
      $data['notas'], $data['origen_tipo'], $data['id_contrato_futuro'], $data['fecha_hora_programada'],
      $data['lugar_tipo'], $data['lugar_detalle'], $data['id_usuario_vendedor'], $data['id_usuario_auxiliar'],
      $data['monto_total'], $data['forma_pago_inicial'], $data['requiere_factura'], $data['observaciones_comerciales'],
      $id
    ]);
    so_guardar_asignacion($id, [
      'fecha_hora_inicio' => $data['fecha_hora_programada'],
      'lugar_tipo' => $data['lugar_tipo'],
      'lugar_detalle' => $data['lugar_detalle'],
      'id_usuario_auxiliar' => $data['id_usuario_auxiliar'],
      'id_vehiculo' => $svc['id_vehiculo'] ?? null,
      'id_sala' => $svc['id_sala'] ?? null,
      'instrucciones' => $data['notas'],
    ]);
    q("UPDATE servicios SET estatus=? WHERE id_servicio=?", [$svc['estatus_norm'], $id]);
    so_recalcular_saldo($id);
    so_log_bitacora($id, 'actualizacion', 'Servicio actualizado', ['monto_total' => $data['monto_total'], 'fallecido_nombre' => $data['fallecido_nombre']]);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo actualizar el servicio: ".e($e->getMessage())."</div>");
    redirect('servicios.editar&id='.$id);
  }
  flash("<div class='alert alert-success'>Servicio actualizado.</div>");
  redirect('servicios.ver&id='.$id);
  break;

case 'ver':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  $asignacion = so_servicio_asignacion($id);
  $seguimiento = so_servicio_seguimiento($id);
  $cierre = so_servicio_cierre($id);
  $totales = so_servicio_totales($id);
  $pagos = so_servicio_pagos($id);
  $bitacora = so_servicio_bitacora($id);
  $cajas = so_servicio_cajas_asignadas($id);
  $equipos = so_servicio_equipos_asignados($id);
  $articulos = so_servicio_articulos_asignados($id);

  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
      <h1 class="h4 m-0">Servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
      <div class="text-muted"><?= e($svc['fallecido'] ?: 'Sin fallecido') ?> · <?= e($svc['contratante_nombre']) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.editar&id=<?= (int)$id ?>">Editar</a>
      <a class="btn btn-outline-primary btn-sm" href="?r=servicio_asignaciones.ver&id=<?= (int)$id ?>">Asignacion</a>
      <a class="btn btn-outline-warning btn-sm" href="?r=servicio_seguimiento.ver&id=<?= (int)$id ?>">Seguimiento</a>
      <a class="btn btn-outline-info btn-sm" href="?r=servicio_pagos.listar&id=<?= (int)$id ?>">Pagos</a>
      <a class="btn btn-outline-success btn-sm" href="?r=servicio_cierres.ver&id=<?= (int)$id ?>">Cierre</a>
      <a class="btn btn-light btn-sm" href="?r=servicios.listar">Volver</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Estatus</div>
        <div class="fs-5 fw-semibold"><span class="badge bg-<?= so_servicio_badge($svc['estatus'] ?? '') ?>"><?= e(ucwords(str_replace('_', ' ', $svc['estatus_norm']))) ?></span></div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Monto total</div>
        <div class="fs-5 fw-semibold"><?= e(so_moneda($totales['total'])) ?></div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Pagado</div>
        <div class="fs-5 fw-semibold"><?= e(so_moneda($totales['pagado'])) ?></div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Saldo</div>
        <div class="fs-5 fw-semibold"><?= e(so_moneda($totales['saldo'])) ?></div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Datos del fallecido</h2>
          <dl class="row mb-0">
            <dt class="col-sm-5">Nombre</dt><dd class="col-sm-7"><?= e($svc['fallecido'] ?: '—') ?></dd>
            <dt class="col-sm-5">Fecha</dt><dd class="col-sm-7"><?= e($svc['fallecido_fecha'] ?: '—') ?></dd>
            <dt class="col-sm-5">Municipio</dt><dd class="col-sm-7"><?= e($svc['fallecido_municipio'] ?: '—') ?></dd>
            <dt class="col-sm-5">Hospital</dt><dd class="col-sm-7"><?= e($svc['fallecido_hospital'] ?: '—') ?></dd>
            <dt class="col-sm-5">Dir. hospital</dt><dd class="col-sm-7"><?= e($svc['fallecido_hospital_direccion'] ?: '—') ?></dd>
            <dt class="col-sm-5">Velacion</dt><dd class="col-sm-7"><?= e($svc['fallecido_dom_velacion'] ?: '—') ?></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Datos generales</h2>
          <dl class="row mb-0">
            <dt class="col-sm-5">Folio</dt><dd class="col-sm-7"><?= e($svc['folio']) ?></dd>
            <dt class="col-sm-5">Fecha</dt><dd class="col-sm-7"><?= e(date('Y-m-d H:i', strtotime($svc['created_at']))) ?></dd>
            <dt class="col-sm-5">Origen</dt><dd class="col-sm-7"><?= e($svc['origen_tipo']) ?><?= $svc['id_contrato_futuro'] ? ' · Contrato #'.e($svc['id_contrato_futuro']) : '' ?></dd>
            <dt class="col-sm-5">Tipo</dt><dd class="col-sm-7"><?= e($svc['tipo_servicio']) ?> / <?= e($svc['tipo_disposicion']) ?></dd>
            <dt class="col-sm-5">Responsable</dt><dd class="col-sm-7"><?= e($svc['responsable']) ?></dd>
            <dt class="col-sm-5">Vendedor</dt><dd class="col-sm-7"><?= e($svc['vendedor_nombre'] ?: '—') ?></dd>
            <dt class="col-sm-5">Auxiliar</dt><dd class="col-sm-7"><?= e($svc['auxiliar_nombre'] ?: '—') ?></dd>
            <dt class="col-sm-5">Contratante</dt><dd class="col-sm-7"><?= e($svc['contratante_nombre']) ?></dd>
            <dt class="col-sm-5">Contacto</dt><dd class="col-sm-7"><?= e($svc['contratante_tel'] ?: '—') ?> / <?= e($svc['contratante_email'] ?: '—') ?></dd>
            <dt class="col-sm-5">Direccion</dt><dd class="col-sm-7"><?= e($svc['contratante_direccion'] ?: '—') ?></dd>
            <dt class="col-sm-5">Factura</dt><dd class="col-sm-7"><?= !empty($svc['requiere_factura']) ? 'Si' : 'No' ?></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Etapas del servicio</h2>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="post" action="?r=servicios.cambiar_estatus&id=<?= (int)$id ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="estatus" value="registrado">
              <button class="btn btn-outline-secondary btn-sm">Registrado</button>
            </form>
            <form method="post" action="?r=servicios.cambiar_estatus&id=<?= (int)$id ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="estatus" value="preparacion">
              <button class="btn btn-outline-info btn-sm">Preparacion</button>
            </form>
            <form method="post" action="?r=servicios.cambiar_estatus&id=<?= (int)$id ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="estatus" value="en_proceso">
              <button class="btn btn-outline-warning btn-sm">En proceso</button>
            </form>
            <form method="post" action="?r=servicios.cambiar_estatus&id=<?= (int)$id ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="estatus" value="finalizado">
              <button class="btn btn-outline-success btn-sm">Finalizado</button>
            </form>
          </div>
          <dl class="row mb-0">
            <dt class="col-sm-5">Fecha programada</dt><dd class="col-sm-7"><?= e($svc['fecha_hora_programada'] ? date('Y-m-d H:i', strtotime($svc['fecha_hora_programada'])) : '—') ?></dd>
            <dt class="col-sm-5">Lugar</dt><dd class="col-sm-7"><?= e(($svc['lugar_tipo'] ?: '—').' '.($svc['lugar_detalle'] ?: '')) ?></dd>
            <dt class="col-sm-5">Seguimiento</dt><dd class="col-sm-7"><?= e($seguimiento['hora_inicio_real'] ?? 'Sin iniciar') ?><?= !empty($seguimiento['hora_fin_real']) ? ' · '.e($seguimiento['hora_fin_real']) : '' ?></dd>
            <dt class="col-sm-5">Cierre</dt><dd class="col-sm-7"><?= !empty($cierre['fecha_cierre']) ? e($cierre['fecha_cierre']) : 'Pendiente' ?></dd>
            <dt class="col-sm-5">Observaciones</dt><dd class="col-sm-7"><?= e($svc['notas'] ?: '—') ?></dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Ataudes asignados</h2>
          <ul class="list-group list-group-flush">
            <?php foreach ($cajas as $caja): ?>
              <li class="list-group-item px-0"><?= e($caja['codigo'].' · '.$caja['modelo']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($cajas)): ?>
              <li class="list-group-item px-0 text-muted">Sin ataudes asignados</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Equipos asignados</h2>
          <ul class="list-group list-group-flush">
            <?php foreach ($equipos as $equipo): ?>
              <li class="list-group-item px-0"><?= e($equipo['equipo'].' · '.$equipo['id_equipo']) ?></li>
            <?php endforeach; ?>
            <?php if (empty($equipos)): ?>
              <li class="list-group-item px-0 text-muted">Sin equipos asignados</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Articulos consumidos</h2>
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

  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Ultimos pagos</h2>
            <a class="btn btn-outline-info btn-sm" href="?r=servicio_pagos.nuevo&id=<?= (int)$id ?>">Registrar pago</a>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Fecha</th><th>Tipo</th><th>Forma</th><th>Monto</th><th></th></tr></thead>
              <tbody>
                <?php foreach (array_slice($pagos, 0, 5) as $pago): ?>
                <tr>
                  <td><?= e(date('Y-m-d H:i', strtotime($pago['fecha_pago']))) ?></td>
                  <td><?= e($pago['tipo_pago']) ?></td>
                  <td><?= e($pago['forma_pago'] ?: '—') ?></td>
                  <td><?= e(so_moneda($pago['monto'])) ?></td>
                  <td><a class="btn btn-outline-secondary btn-sm" href="?r=servicio_pagos.recibo&id_pago=<?= (int)$pago['id_pago'] ?>">Recibo</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pagos)): ?>
                <tr><td colspan="5" class="text-center text-muted">Sin pagos registrados</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Bitacora</h2>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>Detalle</th></tr></thead>
              <tbody>
                <?php foreach ($bitacora as $item): ?>
                <tr>
                  <td><?= e(date('Y-m-d H:i', strtotime($item['created_at']))) ?></td>
                  <td><?= e($item['tipo_evento']) ?></td>
                  <td><?= e($item['usuario_nombre']) ?></td>
                  <td><?= e($item['descripcion']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bitacora)): ?>
                <tr><td colspan="4" class="text-center text-muted">Sin bitacora</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
  render('Servicio', ob_get_clean());
  break;

case 'cambiar_estatus':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicios.ver&id='.$id); }
  $estatus = trim($_POST['estatus'] ?? '');
  $permitidos = array_keys(so_servicio_estatus_options());
  if (!in_array($estatus, $permitidos, true)) {
    flash("<div class='alert alert-danger'>Estatus invalido.</div>");
    redirect('servicios.ver&id='.$id);
  }
  iv2_update_servicio_estatus($id, $estatus, null, null);
  so_log_bitacora($id, 'cambio_estatus', 'Estatus cambiado', ['estatus' => $estatus]);
  flash("<div class='alert alert-success'>Estatus actualizado.</div>");
  redirect('servicios.ver&id='.$id);
  break;

case 'asignar_caja':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_asignaciones.ver&id='.$id); }
  $codigo = trim($_POST['codigo'] ?? '');
  $notas = trim($_POST['notas'] ?? '');
  $tipo_asignacion = trim($_POST['tipo_asignacion'] ?? '') ?: null;
  if ($codigo === '') {
    flash("<div class='alert alert-danger'>Selecciona un ataud.</div>");
    redirect('servicio_asignaciones.ver&id='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_asignar_caja($pdo, $id, $codigo, $tipo_asignacion, so_current_user_id(), $notas ?: null);
    so_guardar_codigo_caja_asignacion($id, $codigo);
    so_log_bitacora($id, 'asignacion_caja', 'Ataud asignado', [
      'codigo' => $codigo,
      'tipo_asignacion' => $resultado['tipo_asignacion'] ?? $tipo_asignacion,
    ]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataud asignado correctamente.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
  }
  redirect('servicio_asignaciones.ver&id='.$id);
  break;

case 'asignar_equipo':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_asignaciones.ver&id='.$id); }
  $id_equipo = trim($_POST['id_equipo'] ?? '');
  $notas = trim($_POST['notas'] ?? '');
  if ($id_equipo === '') {
    flash("<div class='alert alert-danger'>Selecciona equipo.</div>");
    redirect('servicio_asignaciones.ver&id='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    iv2_asignar_equipo($pdo, $id, $id_equipo, so_current_user_id(), $notas ?: null);
    so_log_bitacora($id, 'asignacion_equipo', 'Equipo asignado', ['id_equipo' => $id_equipo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo asignado.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
  }
  redirect('servicio_asignaciones.ver&id='.$id);
  break;

case 'cerrar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) redirect('servicios.listar');
  redirect('servicio_cierres.ver&id='.$id);
  break;

case 'borrar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) redirect('servicios.listar');
  q("UPDATE servicios SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?", [$id]);
  so_log_bitacora($id, 'baja', 'Servicio dado de baja logica');
  flash("<div class='alert alert-success'>Servicio borrado logicamente.</div>");
  $tab = $_GET['tab'] ?? 'activos';
  redirect('servicios.listar&tab='.$tab);
  break;

default:
  redirect('servicios.listar');
}
