<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();
so_bootstrap();

$action = $action ?? 'listar';
require_role(['administradora','vendedor','caja','coordinador']);

function servicio_pagos_texto_pdf(string $str): string {
  if (function_exists('mb_convert_encoding')) {
    return mb_convert_encoding((string)$str, 'ISO-8859-1', 'UTF-8');
  }
  return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$str);
}

switch ($action) {
case 'guardar':
  $id = (int)($_GET['id'] ?? $_POST['id_servicio'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('servicio_pagos.nuevo&id='.$id); }
  $monto = so_parse_money($_POST['monto'] ?? 0);
  $forma = trim($_POST['forma_pago'] ?? '');
  $tipo = trim($_POST['tipo_pago'] ?? 'abono');
  $referencia = trim($_POST['referencia'] ?? '') ?: null;
  $fechaPago = trim($_POST['fecha_pago'] ?? '') ?: so_now_local();
  $observaciones = trim($_POST['observaciones'] ?? '') ?: null;
  if ($monto <= 0) {
    flash("<div class='alert alert-danger'>El monto debe ser mayor a cero.</div>");
    redirect('servicio_pagos.nuevo&id='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    so_guardar_pago($pdo, $id, $monto, $forma, $tipo, $referencia, $observaciones, $fechaPago);
    $pdo->commit();
    flash("<div class='alert alert-success'>Pago registrado correctamente.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo registrar el pago: ".e($e->getMessage())."</div>");
  }
  redirect('servicio_pagos.listar&id='.$id);
  break;

case 'nuevo':
  $id = (int)($_GET['id'] ?? 0);
  $svc = so_servicio_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  $totales = so_servicio_totales($id);
  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
      <h1 class="h4 m-0">Nuevo pago del servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
      <div class="text-muted">Saldo pendiente <?= e(so_moneda($totales['saldo'])) ?></div>
    </div>
    <a class="btn btn-light btn-sm" href="?r=servicio_pagos.listar&id=<?= (int)$id ?>">Volver</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="?r=servicio_pagos.guardar&id=<?= (int)$id ?>">
        <?= csrf_field() ?>
        <?= form_input('fecha_pago','Fecha de pago',so_datetime_to_input(so_now_local()),['type'=>'datetime-local','required'=>true]) ?>
        <?= form_input('monto','Monto',$totales['saldo'] > 0 ? $totales['saldo'] : '0.00',['type'=>'number','step'=>'0.01','min'=>'0.01','required'=>true]) ?>
        <?= form_select('forma_pago','Forma de pago',so_servicio_tipo_pago_options(),'efectivo',['class'=>'form-select','required'=>true]) ?>
        <?= form_select('tipo_pago','Tipo de pago',so_servicio_pago_tipo_options(),'abono',['class'=>'form-select','required'=>true]) ?>
        <?= form_input('referencia','Referencia','') ?>
        <div class="mb-3">
          <label class="form-label" for="observaciones">Observaciones</label>
          <textarea class="form-control" name="observaciones" id="observaciones" rows="3"></textarea>
        </div>
        <button class="btn btn-primary">Guardar pago</button>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo pago de servicio', ob_get_clean());
  break;

case 'recibo':
  require_once __DIR__.'/../fpdf/fpdf.php';
  $idPago = (int)($_GET['id_pago'] ?? 0);
  $pago = qone("
    SELECT sp.*, s.folio, s.contratante_nombre, COALESCE(f.nom_fallecido,'') AS fallecido
    FROM servicio_pagos sp
    JOIN servicios s ON s.id_servicio = sp.id_servicio
    LEFT JOIN servicio_fallecido sf ON sf.id_servicio=s.id_servicio
    LEFT JOIN fallecido f ON f.id_fallecido=sf.id_fallecido
    WHERE sp.id_pago=?
    LIMIT 1
  ", [$idPago]);
  if (!$pago) {
    die('Pago no encontrado.');
  }
  $totales = so_servicio_totales((int)$pago['id_servicio']);
  $pdf = new FPDF('P','mm');
  $pdf->AddPage();
  $pdf->SetFont('Arial', 'B', 15);
  $pdf->Cell(0, 10, servicio_pagos_texto_pdf('Recibo de pago de servicio funerario'), 0, 1, 'C');
  $pdf->Ln(3);
  $pdf->SetFont('Arial', '', 11);
  $rows = [
    'Folio de servicio:' => $pago['folio'],
    'Contratante:' => $pago['contratante_nombre'],
    'Fallecido:' => $pago['fallecido'] ?: '—',
    'Fecha de pago:' => date('d/m/Y H:i', strtotime($pago['fecha_pago'])),
    'Tipo de pago:' => $pago['tipo_pago'],
    'Forma de pago:' => $pago['forma_pago'] ?: '—',
    'Referencia:' => $pago['referencia'] ?: '—',
    'Monto:' => so_moneda($pago['monto']),
    'Saldo pendiente:' => so_moneda($totales['saldo']),
  ];
  foreach ($rows as $label => $value) {
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(60, 8, servicio_pagos_texto_pdf($label), 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(120, 8, servicio_pagos_texto_pdf((string)$value), 0, 1);
  }
  if (!empty($pago['observaciones'])) {
    $pdf->Ln(4);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, servicio_pagos_texto_pdf('Observaciones:'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, servicio_pagos_texto_pdf((string)$pago['observaciones']));
  }
  $pdf->Output('I', 'Recibo_Servicio_'.$idPago.'.pdf');
  exit;

case 'listar':
default:
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    $svc = so_servicio_fetch($id);
    if (!$svc) {
      flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
      redirect('servicios.listar');
    }
    $rows = so_servicio_pagos($id);
    $totales = so_servicio_totales($id);

    ob_start(); ?>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
      <div>
        <h1 class="h4 m-0">Pagos del servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
        <div class="text-muted"><?= e($svc['contratante_nombre']) ?> · Saldo <?= e(so_moneda($totales['saldo'])) ?></div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.ver&id=<?= (int)$id ?>">Expediente</a>
        <a class="btn btn-primary btn-sm" href="?r=servicio_pagos.nuevo&id=<?= (int)$id ?>">Nuevo pago</a>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total</div><div class="fs-5 fw-semibold"><?= e(so_moneda($totales['total'])) ?></div></div></div></div>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Pagado</div><div class="fs-5 fw-semibold"><?= e(so_moneda($totales['pagado'])) ?></div></div></div></div>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Saldo</div><div class="fs-5 fw-semibold"><?= e(so_moneda($totales['saldo'])) ?></div></div></div></div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm align-middle">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Forma</th><th>Referencia</th><th>Monto</th><th>Registrado por</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', strtotime($row['fecha_pago']))) ?></td>
            <td><?= e($row['tipo_pago']) ?></td>
            <td><?= e($row['forma_pago'] ?: '—') ?></td>
            <td><?= e($row['referencia'] ?: '—') ?></td>
            <td><?= e(so_moneda($row['monto'])) ?></td>
            <td><?= e($row['usuario_nombre']) ?></td>
            <td><a class="btn btn-outline-secondary btn-sm" href="?r=servicio_pagos.recibo&id_pago=<?= (int)$row['id_pago'] ?>">Recibo</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted">Sin pagos registrados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    render('Pagos de servicio', ob_get_clean());
  }

  $q = trim($_GET['q'] ?? '');
  $where = ['1=1'];
  $params = [];
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(s.folio LIKE ? OR s.contratante_nombre LIKE ? OR COALESCE(f.nom_fallecido,'') LIKE ?)";
    array_push($params, $like, $like, $like);
  }
  $rows = qall("
    SELECT sp.id_pago, sp.fecha_pago, sp.tipo_pago, sp.forma_pago, sp.monto, sp.id_servicio,
           s.folio, s.contratante_nombre, COALESCE(f.nom_fallecido,'') AS fallecido
    FROM servicio_pagos sp
    JOIN servicios s ON s.id_servicio=sp.id_servicio
    LEFT JOIN servicio_fallecido sf ON sf.id_servicio=s.id_servicio
    LEFT JOIN fallecido f ON f.id_fallecido=sf.id_fallecido
    WHERE ".implode(' AND ', $where)."
    ORDER BY sp.fecha_pago DESC, sp.id_pago DESC
    LIMIT 200
  ", $params);
  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Pagos de servicios funerarios</h1>
    <a class="btn btn-outline-secondary btn-sm" href="?r=servicios.listar">Ir a servicios</a>
  </div>
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="servicio_pagos.listar">
    <div class="col-md-10"><?= form_input('q','Buscar',$q,['placeholder'=>'Folio, contratante o fallecido']) ?></div>
    <div class="col-md-2 d-grid align-items-end"><button class="btn btn-outline-primary">Filtrar</button></div>
  </form>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead><tr><th>Fecha</th><th>Servicio</th><th>Contratante</th><th>Fallecido</th><th>Tipo</th><th>Monto</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($row['fecha_pago']))) ?></td>
          <td><?= e($row['folio']) ?></td>
          <td><?= e($row['contratante_nombre']) ?></td>
          <td><?= e($row['fallecido'] ?: '—') ?></td>
          <td><?= e($row['tipo_pago']) ?></td>
          <td><?= e(so_moneda($row['monto'])) ?></td>
          <td class="d-flex gap-2">
            <a class="btn btn-outline-primary btn-sm" href="?r=servicio_pagos.listar&id=<?= (int)$row['id_servicio'] ?>">Abrir</a>
            <a class="btn btn-outline-secondary btn-sm" href="?r=servicio_pagos.recibo&id_pago=<?= (int)$row['id_pago'] ?>">Recibo</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted">Sin pagos registrados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Pagos de servicios', ob_get_clean());
}
