<?php
// app/modules/pagos.php

require_once __DIR__."/../core/db.php";
require_once __DIR__."/../core/helpers.php";
require_once __DIR__."/../core/auth.php";

require_login();

switch ($action) {

/* =========================================================
 * LISTADO DE CONTRATOS
 * =======================================================*/
case 'contratos':
  $q       = trim($_GET['q'] ?? '');
  $estatus = trim($_GET['estatus'] ?? '');

  $where = "1=1";
  $params = [];

  if ($estatus !== '') {
    $where .= " AND c.estatus = ?";
    $params[] = $estatus;
  }

  if ($q !== '') {
    if (ctype_digit($q)) {
      $where .= " AND c.id_contrato = ?";
      $params[] = (int)$q;
    } else {
      $like = '%'.$q.'%';
      $where .= " AND (
        EXISTS (
          SELECT 1 FROM titular_contrato tc
          JOIN titulares t ON t.id_titular=tc.id_titular
          WHERE tc.id_contrato = c.id_contrato
            AND (t.nombre LIKE ? OR t.apellido_p LIKE ? OR t.apellido_m LIKE ?)
        )
        OR
        EXISTS (
          SELECT 1 FROM futuro_contrato_vendedor fcv
          JOIN futuro_personal fp ON fp.id_personal=fcv.id_personal
          WHERE fcv.id_contrato = c.id_contrato
            AND (fp.nombre LIKE ? OR fp.apellido_p LIKE ? OR fp.apellido_m LIKE ?)
        )
      )";
      array_push($params,$like,$like,$like,$like,$like,$like);
    }
  }

  $rows = qall("
    SELECT
      c.id_contrato,
      c.costo_final,
      c.estatus,
      DATE(c.fecha_registro) AS fecha,
      (
        SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
        FROM titular_contrato tc
        JOIN titulares t ON t.id_titular=tc.id_titular
        WHERE tc.id_contrato = c.id_contrato
        ORDER BY tc.id_titular_contrato DESC LIMIT 1
      ) AS titular,
      (
        SELECT CONCAT(fp.nombre,' ',fp.apellido_p,' ',fp.apellido_m)
        FROM futuro_contrato_vendedor fcv
        JOIN futuro_personal fp ON fp.id_personal=fcv.id_personal
        WHERE fcv.id_contrato = c.id_contrato
        ORDER BY fcv.id_contrato_vendedor DESC LIMIT 1
      ) AS vendedor,
      (
        SELECT CONCAT(fp2.nombre,' ',fp2.apellido_p,' ',fp2.apellido_m)
        FROM futuro_abonos a
        JOIN futuro_abono_cobrador ac ON ac.id_abono=a.id_abono
        JOIN futuro_personal fp2 ON fp2.id_personal=ac.id_personal
        WHERE a.id_contrato = c.id_contrato
        ORDER BY a.id_abono DESC LIMIT 1
      ) AS cobrador
    FROM futuro_contratos c
    WHERE $where
    ORDER BY c.id_contrato DESC
    LIMIT 200
  ", $params);

  ob_start(); ?>
  <h1 class="h4 mb-3">Contratos</h1>
  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead>
        <tr>
          <th>ID</th><th>Fecha</th><th>Estatus</th>
          <th>Titular</th><th>Vendedor</th><th>Cobrador</th>
          <th>Costo</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td>#<?= (int)$r['id_contrato'] ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td><?= e($r['estatus']) ?></td>
          <td><?= e($r['titular']) ?></td>
          <td><?= e($r['vendedor']) ?></td>
          <td><?= e($r['cobrador']) ?></td>
          <td>$<?= number_format((float)$r['costo_final'],2) ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="?r=pagos.nuevo_abono&id_contrato=<?= (int)$r['id_contrato'] ?>">Abonos</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Contratos', ob_get_clean());
break;


/* =========================================================
 * NUEVO ABONO
 * =======================================================*/
case 'nuevo_abono':
  $idc = (int)($_GET['id_contrato'] ?? 0);
  $contr = qone("SELECT id_contrato, costo_final, estatus, DATE(fecha_registro) AS fecha FROM futuro_contratos WHERE id_contrato=?", [$idc]);
  if (!$contr) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Contrato no encontrado.</div>";
    redirect('pagos.contratos');
  }
  $saldo = __saldo_contrato($idc);
  $cobradores = __get_personal_activo();

  $hist = qall("
    SELECT a.id_abono, a.cant_abono, DATE(a.fecha_registro) AS fecha,
           CONCAT(fp.nombre,' ',fp.apellido_p,' ',fp.apellido_m) AS cobrador
    FROM futuro_abonos a
    JOIN futuro_abono_cobrador ac ON ac.id_abono=a.id_abono
    JOIN futuro_personal fp ON fp.id_personal=ac.id_personal
    WHERE a.id_contrato=?
    ORDER BY a.id_abono DESC
    LIMIT 200
  ", [$idc]);

  ob_start(); ?>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h5 mb-3">Nuevo abono — Contrato #<?= (int)$contr['id_contrato'] ?></h1>
          <div class="mb-3">
            <div><strong>Costo:</strong> $<?= number_format($saldo['costo_final'],2) ?></div>
            <div><strong>Pagado:</strong> $<?= number_format($saldo['pagado'],2) ?></div>
            <div><strong>Saldo:</strong> <span class="text-success">$<?= number_format($saldo['saldo'],2) ?></span></div>
          </div>
          <form method="post" action="?r=pagos.guardar_abono&id_contrato=<?= (int)$contr['id_contrato'] ?>">
            <?= form_input('cant_abono','Monto del abono', '', ['type'=>'number','step'=>'0.01','required'=>true]) ?>
            <div class="mb-3">
              <label class="form-label">Cobrador</label>
              <select name="id_cobrador" class="form-select" required>
                <option value="">— Selecciona —</option>
                <?php foreach($cobradores as $p): ?>
                  <option value="<?= (int)$p['id_personal'] ?>"><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-primary">Guardar abono</button>
            <a class="btn btn-link" href="?r=pagos.contratos">Cancelar</a>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <h2 class="h6">Historial de abonos</h2>
      <ul class="list-group list-group-flush">
        <?php foreach($hist as $h): ?>
          <li class="list-group-item">
            #<?= (int)$h['id_abono'] ?> · <?= e($h['fecha']) ?> · $<?= number_format((float)$h['cant_abono'],2) ?> · Cobrador: <?= e($h['cobrador']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php
  render('Nuevo abono', ob_get_clean());
break;


/* =========================================================
 * GUARDAR ABONO
 * =======================================================*/
case 'guardar_abono':
  $idc = (int)($_GET['id_contrato'] ?? 0);
  $monto = (float)($_POST['cant_abono'] ?? 0);
  $cobrador = (int)($_POST['id_cobrador'] ?? 0);

  if ($idc>0 && $monto>0 && $cobrador>0) {
    q("INSERT INTO futuro_abonos (id_contrato,cant_abono,fecha_registro)
        VALUES (?,?,NOW())", [$idc,$monto]);
    $id_abono = pdo()->lastInsertId();

    q("INSERT INTO futuro_abono_cobrador (id_abono,id_personal) VALUES (?,?)", [$id_abono,$cobrador]);

    $_SESSION['_alerts'] = "<div class='alert alert-success'>Abono registrado con cobrador.</div>";
  }
  redirect("pagos.nuevo_abono&id_contrato=$idc");
break;


/* =========================================================
 * CORTE POR PERSONA
 * =======================================================*/
case 'corte':
  $tipo = trim($_GET['tipo'] ?? 'cobrador');
  $per  = (int)($_GET['id_personal'] ?? 0);
  $f1   = trim($_GET['f1'] ?? '');
  $f2   = trim($_GET['f2'] ?? '');

  $rows = [];
  $total = 0.0;

  if ($per > 0) {
    if ($tipo === 'cobrador') {
      $rows = qall("
        SELECT a.id_abono, a.id_contrato, DATE(a.fecha_registro) AS fecha, a.cant_abono,
               CONCAT(fp.nombre,' ',fp.apellido_p,' ',fp.apellido_m) AS cobrador,
               (SELECT CONCAT(fpv.nombre,' ',fpv.apellido_p,' ',fpv.apellido_m)
                  FROM futuro_contrato_vendedor fcv
                  JOIN futuro_personal fpv ON fpv.id_personal=fcv.id_personal
                 WHERE fcv.id_contrato=a.id_contrato LIMIT 1) AS vendedor,
               (SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
                  FROM titular_contrato tc
                  JOIN titulares t ON t.id_titular=tc.id_titular
                 WHERE tc.id_contrato=a.id_contrato LIMIT 1) AS titular
        FROM futuro_abonos a
        JOIN futuro_abono_cobrador ac ON ac.id_abono=a.id_abono
        JOIN futuro_personal fp ON fp.id_personal=ac.id_personal
        WHERE ac.id_personal=? ".($f1?"AND a.fecha_registro>='$f1'":"")." ".($f2?"AND a.fecha_registro<='$f2'":"")."
        ORDER BY a.id_abono DESC
      ", [$per]);
    } else {
      $rows = qall("
        SELECT a.id_abono, a.id_contrato, DATE(a.fecha_registro) AS fecha, a.cant_abono,
               (SELECT CONCAT(fp.nombre,' ',fp.apellido_p,' ',fp.apellido_m)
                  FROM futuro_abono_cobrador ac
                  JOIN futuro_personal fp ON fp.id_personal=ac.id_personal
                 WHERE ac.id_abono=a.id_abono LIMIT 1) AS cobrador,
               CONCAT(fpv.nombre,' ',fpv.apellido_p,' ',fpv.apellido_m) AS vendedor,
               (SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
                  FROM titular_contrato tc
                  JOIN titulares t ON t.id_titular=tc.id_titular
                 WHERE tc.id_contrato=a.id_contrato LIMIT 1) AS titular
        FROM futuro_abonos a
        JOIN futuro_contrato_vendedor fcv ON fcv.id_contrato=a.id_contrato
        JOIN futuro_personal fpv ON fpv.id_personal=fcv.id_personal
        WHERE fcv.id_personal=? ".($f1?"AND a.fecha_registro>='$f1'":"")." ".($f2?"AND a.fecha_registro<='$f2'":"")."
        ORDER BY a.id_abono DESC
      ", [$per]);
    }
    foreach ($rows as $r) { $total += (float)$r['cant_abono']; }
  }

  ob_start(); ?>
  <h1 class="h4 mb-3">Corte de pagos</h1>
  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead>
        <tr>
          <th>ID Abono</th><th>Contrato</th><th>Titular</th>
          <th>Vendedor</th><th>Cobrador</th>
          <th>Fecha</th><th class="text-end">Monto</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id_abono'] ?></td>
          <td>#<?= (int)$r['id_contrato'] ?></td>
          <td><?= e($r['titular']) ?></td>
          <td><?= e($r['vendedor']) ?></td>
          <td><?= e($r['cobrador']) ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td class="text-end">$<?= number_format((float)$r['cant_abono'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="6" class="text-end">Total</th>
          <th class="text-end">$<?= number_format($total,2) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php
  render('Corte', ob_get_clean());
break;

default:
  redirect('pagos.contratos');
}
