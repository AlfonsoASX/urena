<?php
// app/modules/cobrador.php — Panel operativo para cobradores
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

// Solo ciertos roles pueden acceder
$roles = ['cobrador','administradora'];
require_role($roles);

$action = $action ?? 'panel';

// -------------------------------------------------------------
// Funciones utilitarias del módulo
// -------------------------------------------------------------

function cobrador_personal_id(): int {

  $u = current_user();
  $r = qone("SELECT id_personal FROM futuro_personal WHERE id=?", [$u['id']]);
  return (int)($r['id_personal'] ?? 0);

  //return $_SESSION['user']['id'];
}

function cobrador_contratos(int $id_personal, bool $soloAsignados = true): array {
  $baseSQL = "
    SELECT 
      c.id_contrato,
      t.titular,
      TRIM(CONCAT(
        d.calle, ' ',
        IFNULL(CONCAT('#', d.num_ext), ''), ', ',
        IFNULL(d.colonia, ''), ', ',
        IFNULL(d.municipio, '')
      )) AS direccion,
      c.costo_final,
      c.estatus
    FROM futuro_contratos c
    " . ($soloAsignados ? "
      INNER JOIN futuro_contrato_cobrador fc ON fc.id_contrato = c.id_contrato
    " : "") . "
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
    LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
    LEFT JOIN titular_dom td ON td.id_titular = tc.id_titular
    LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
    " . ($soloAsignados ? "WHERE fc.id_personal = ?" : "") . "
    GROUP BY c.id_contrato
    ORDER BY c.id_contrato DESC
  ";

  return $soloAsignados
    ? qall($baseSQL, [$id_personal])
    : qall($baseSQL);
}


function cobrador_registrar_pago(int $id_contrato, float $monto, int $id_personal): ?int {
  $pdo = db();

  try {
    $pdo->beginTransaction();
    q("INSERT INTO futuro_abonos (id_contrato, saldo, cant_abono, fecha_registro)
       VALUES (?,?,?,CURRENT_TIMESTAMP())", [$id_contrato,0,$monto]);

    $id_abono = $pdo->lastInsertId();

//    error_log('--'.$id_abono.'--');


    q("INSERT INTO futuro_abono_cobrador (id_abono, id_personal, fecha_registro)
       VALUES (?,?,CURRENT_TIMESTAMP())", [$id_abono,$id_personal]);
    $pdo->commit();
    return $id_abono;
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[cobrador_registrar_pago] ".$e->getMessage());
    return null;
  }
}

function cobrador_ticket_data(int $id_abono): ?array {
  return qone("
    SELECT a.id_abono, a.id_contrato, a.cant_abono, a.fecha_registro, t.titular
    FROM futuro_abonos a
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = a.id_contrato
    WHERE a.id_abono = ?
  ", [$id_abono]);
}

// -------------------------------------------------------------
// Controlador principal por acción
// -------------------------------------------------------------

switch ($action) {
case 'panel':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h4 mb-3">Panel del Cobrador</h1>
      <p>Bienvenido, <strong><?= e(current_user()['nombre']) ?></strong></p>
      <div class="list-group">
        <a href="?r=cobrador.contratos" class="list-group-item list-group-item-action">
          📄 Ver contratos asignados
        </a>
        <a href="?r=cobrador.contratos&filtro=todos" class="list-group-item list-group-item-action">
          📋 Ver todos los contratos
        </a>
      </div>
    </div>
  </div>
  <?php
  render('Panel del cobrador', ob_get_clean());
  break;

case 'contratos':
  $id_personal = cobrador_personal_id();
  $filtro = ($_GET['filtro'] ?? '') === 'todos' ? false : true;
  $rows = cobrador_contratos($id_personal, $filtro);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0"><?= $filtro ? 'Mis contratos' : 'Todos los contratos' ?></h1>
    <div class="d-flex gap-2">
      <a href="?r=cobrador.contratos" class="btn btn-outline-primary btn-sm">Mis contratos</a>
      <a href="?r=cobrador.contratos&filtro=todos" class="btn btn-outline-secondary btn-sm">Todos</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Titular</th>
          <th>Dirección</th>
          <th>Monto</th>
          <th>Estatus</th>
          <th style="width:220px">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): 
        $direccion = trim($r['direccion'] ?? '');
        $link_maps = $direccion !== '' 
          ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion . ', León, Guanajuato') 
          : null;
      ?>
        <tr>
          <td><code><?= e($r['id_contrato']) ?></code></td>
          <td><?= e($r['titular'] ?? '—') ?></td>
          <td>
            <?= e($direccion ?: '—') ?>
            <?php if ($link_maps): ?>
              <a href="<?= $link_maps ?>" target="_blank" 
                 class="btn btn-outline-info btn-sm ms-1" title="Abrir en Google Maps">
                📍
              </a>
            <?php endif; ?>
          </td>
          <td>$<?= number_format($r['costo_final'], 2) ?></td>
          <td>
            <span class="badge bg-<?= $r['estatus'] === 'activo' ? 'success' : 'secondary' ?>">
              <?= e($r['estatus']) ?>
            </span>
          </td>
          <td>
            <a href="?r=cobrador.pago&id_contrato=<?= urlencode($r['id_contrato']) ?>" 
               class="btn btn-sm btn-outline-success">
              💰 Registrar pago
            </a>
            <?php if ($link_maps): ?>
              <a href="<?= $link_maps ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                🗺️ Ruta
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin registros</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Contratos del cobrador', ob_get_clean());
  break;

case 'pago':
  $id_contrato = (int)($_GET['id_contrato'] ?? 0);
  if ($id_contrato <= 0) {
    flash("<div class='alert alert-warning'>Contrato inválido.</div>");
    redirect('cobrador.contratos');
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>");
      redirect('cobrador.pago&id_contrato='.$id_contrato);
    }
    $monto = (float)($_POST['monto'] ?? 0);
    $id_personal = cobrador_personal_id();
    if ($monto <= 0) {
      flash("<div class='alert alert-danger'>El monto debe ser mayor que 0.</div>");
      redirect('cobrador.pago&id_contrato='.$id_contrato);
    }
    
    //echo "hola $id_contrato, $monto, $id_personal";
    $id_abono = cobrador_registrar_pago($id_contrato, $monto, $id_personal);

    flash( "hola $id_contrato, $monto, $id_personal");



    if ($id_abono) {
      flash("<div class='alert alert-success'>Pago registrado correctamente.</div>");
      redirect('cobrador.ticket&id_abono='.$id_abono);
    } else {

      flash("<div class='alert alert-danger'>No se pudo registrar el pago.</div>");
      redirect('cobrador.pago&id_contrato='.$id_contrato);
    }
  }

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Registrar pago · Contrato #<?= e($id_contrato) ?></h1>
      <form method="post" action="?r=cobrador.pago&id_contrato=<?= urlencode($id_contrato) ?>">
        <?= csrf_field() ?>
        <?= form_input('monto','Monto a pagar','',['type'=>'number','step'=>'0.01','min'=>'0.01','required'=>true]) ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Guardar pago</button>
          <a class="btn btn-light" href="?r=cobrador.contratos">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Registrar pago', ob_get_clean());
  break;

case 'ticket':
  $id_abono = (int)($_GET['id_abono'] ?? 0);
  $data = cobrador_ticket_data($id_abono);
  if (!$data) {
    flash("<div class='alert alert-warning'>Ticket no encontrado.</div>");
    redirect('cobrador.contratos');
  }
  ob_start(); ?>
  <div class="text-center">
    <h3>RECIBO DE PAGO</h3>
    <hr>
    <p><strong>Contrato:</strong> <?= e($data['id_contrato']) ?></p>
    <p><strong>Titular:</strong> <?= e($data['titular']) ?></p>
    <p><strong>Monto:</strong> $<?= number_format($data['cant_abono'],2) ?></p>
    <p><strong>Fecha:</strong> <?= e($data['fecha_registro']) ?></p>
    <p><strong>Cobrador:</strong> <?= e(current_user()['nombre']) ?></p>
    <hr>
    <p>Gracias por su pago</p>
  </div>
  <script>window.print();</script>
  <?php
  render('Ticket de pago', ob_get_clean());
  break;

default:
  redirect('cobrador.panel');
}
