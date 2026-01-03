<?php
// app/modules/pagos.php (versión optimizada sin MAX_JOIN_SIZE)
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'contratos';

/** ---------------------------------------------
 * Catálogos básicos
 * ---------------------------------------------*/
function __get_personal_activo() {
  return qall("SELECT id_personal, CONCAT(nombre,' ',apellido_p,' ',apellido_m) AS nombre
               FROM futuro_personal
               WHERE (estatus IS NULL OR estatus NOT IN ('baja','inactivo'))
               ORDER BY nombre ASC");
}

/** ---------------------------------------------
 * Saldos de contrato (costo_final - SUM(abonos))
 * ---------------------------------------------*/
function __saldo_contrato($id_contrato) {
  $c = qone("SELECT costo_final FROM futuro_contratos WHERE id_contrato=?", [$id_contrato]);
  if (!$c) return null;
  $sum = qone("SELECT COALESCE(SUM(cant_abono),0) AS pagado FROM futuro_abonos WHERE id_contrato=?", [$id_contrato]);
  $pagado = (float)$sum['pagado'];
  $saldo  = (float)$c['costo_final'] - $pagado;
  if ($saldo < 0) $saldo = 0.0;
  return ['costo_final'=>(float)$c['costo_final'], 'pagado'=>$pagado, 'saldo'=>$saldo];
}

switch ($action) {

/* =========================================================
 * CONTRATOS (lista con saldo + búsqueda) — SIN JOINS GRANDES
 * =======================================================*/
case 'contratos':
  $q       = trim($_GET['q'] ?? '');        // folio (num), titular o vendedor (texto)
  $estatus = trim($_GET['estatus'] ?? '');  // activo/cancelado/pagado o vacío

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
      // Filtra por titular O por vendedor usando EXISTS (evita megajoins)
      $where .= " AND (
        EXISTS (
          SELECT 1
          FROM titular_contrato tc
          JOIN titulares t ON t.id_titular = tc.id_titular
          WHERE tc.id_contrato = c.id_contrato
            AND (t.nombre LIKE ? OR t.apellido_p LIKE ? OR t.apellido_m LIKE ?)
        )
        OR
        EXISTS (
          SELECT 1
          FROM futuro_contrato_vendedor fcv
          JOIN futuro_personal fp ON fp.id_personal = fcv.id_personal
          WHERE fcv.id_contrato = c.id_contrato
            AND (fp.nombre LIKE ? OR fp.apellido_p LIKE ? OR fp.apellido_m LIKE ?)
        )
      )";
      array_push($params, $like,$like,$like,$like,$like,$like);
    }
  }

  // Trae la lista base sin joins (LIMIT razonable)
  $rows = qall("
    SELECT
      c.id_contrato,
      c.costo_final,
      c.estatus,
      DATE(c.fecha_registro) AS fecha,
      /* Titular por subconsulta (rápida y sin expandir resultado) */
      (
        SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
        FROM titular_contrato tc
        JOIN titulares t ON t.id_titular=tc.id_titular
        WHERE tc.id_contrato = c.id_contrato
        ORDER BY tc.id_titular_contrato DESC
        LIMIT 1
      ) AS titular,
      /* Vendedor por subconsulta */
      (
        SELECT CONCAT(fp.nombre,' ',fp.apellido_p,' ',fp.apellido_m)
        FROM futuro_contrato_vendedor fcv
        JOIN futuro_personal fp ON fp.id_personal = fcv.id_personal
        WHERE fcv.id_contrato = c.id_contrato
        ORDER BY fcv.id_cont_vend DESC
        LIMIT 1
      ) AS vendedor
    FROM futuro_contratos c
    WHERE $where
    ORDER BY c.id_contrato DESC
    LIMIT 300
  ", $params);

  // Calcula saldos (consulta por contrato; OK con LIMIT 300)
  foreach ($rows as &$r) {
    $s = __saldo_contrato($r['id_contrato']);
    $r['_costo']  = $s ? $s['costo_final'] : 0;
    $r['_pagado'] = $s ? $s['pagado'] : 0;
    $r['_saldo']  = $s ? $s['saldo'] : 0;
  } unset($r);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Contratos (pre-vendidos)</h1>
    <div class="d-flex gap-2">
      <a href="?r=pagos.corte" class="btn btn-outline-primary btn-sm">Corte por persona</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="pagos.contratos">
    <div class="col-md-6">
      <input class="form-control" type="search" name="q" placeholder="Buscar por folio, titular o vendedor..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
      <select class="form-select" name="estatus">
        <option value="">Todos los estatus</option>
        <option value="activo"   <?= $estatus==='activo'?'selected':'' ?>>Activo</option>
        <option value="cancelado"<?= $estatus==='cancelado'?'selected':'' ?>>Cancelado</option>
        <option value="pagado"   <?= $estatus==='pagado'?'selected':'' ?>>Pagado</option>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fecha</th>
          <th>Titular</th>
          <th>Vendedor</th>
          <th class="text-end">Costo</th>
          <th class="text-end">Pagado</th>
          <th class="text-end">Saldo</th>
          <th style="width:160px">Herramientas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td>#<?= (int)$r['id_contrato'] ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td><?= e($r['titular'] ?: '—') ?></td>
          <td><?= e($r['vendedor'] ?: '—') ?></td>
          <td class="text-end">$<?= number_format((float)$r['_costo'],2) ?></td>
          <td class="text-end">$<?= number_format((float)$r['_pagado'],2) ?></td>
          <td class="text-end"><strong>$<?= number_format((float)$r['_saldo'],2) ?></strong></td>
          <td class="d-flex gap-2">
            <a class="btn btn-outline-success btn-sm" href="?r=pagos.nuevo_abono&id_contrato=<?= (int)$r['id_contrato'] ?>">Abonar</a>
            <a class="btn btn-outline-secondary btn-sm" href="?r=pagos.editar_contrato&id_contrato=<?= (int)$r['id_contrato'] ?>">Editar</a>
            <a class="btn btn-outline-secondary btn-sm" href="?r=cobrador.estado&id_contrato=<?= (int)$r['id_contrato'] ?>">Estado de cuenta</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="text-center text-muted">Sin contratos</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Contratos', ob_get_clean());
break;

/* =========================================================
 * NUEVO ABONO (formulario)
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

  $hist = qall("SELECT id_abono, cant_abono, DATE(fecha_registro) AS fecha
                FROM futuro_abonos WHERE id_contrato=?
                ORDER BY id_abono DESC LIMIT 200", [$idc]);

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
            <?= form_input('cant_abono','Monto del abono', '', ['type'=>'number','step'=>'0.01','min'=>'0.01','required'=>true]) ?>
            <?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?>
            <div class="mb-3">
              <label class="form-label">Cobrador (opcional)</label>
              <select name="id_cobrador" class="form-select">
                <option value="">— Sin cobrador —</option>
                <?php foreach($cobradores as $c): ?>
                  <option value="<?= (int)$c['id_personal'] ?>"><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?= form_input('notas','Notas (opcional)') ?>
            <div class="d-grid">
              <button class="btn btn-primary">Guardar abono</button>
            </div>
          </form>
          <a class="btn btn-link mt-2" href="?r=pagos.contratos">Volver</a>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-3">Historial de abonos</h6>
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th class="text-end">Monto</th>
              </tr>
            </thead>
            <tbody>


<div class="table-responsive">
  <table class="table table-sm table-striped">
    <thead>
      <tr>
        <th>ID Abono</th>
        <th>Fecha</th>
        <th>Monto</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($hist as $h): ?>
      <tr>
        <td>#<?= (int)$h['id_abono'] ?></td>
        <td><?= e($h['fecha']) ?></td>
        <td>$<?= number_format((float)$h['cant_abono'],2) ?></td>
        <td class="text-end">
          <a href="?r=pagos.borrar_abono&id_abono=<?= (int)$h['id_abono'] ?>&id_contrato=<?= (int)$contr['id_contrato'] ?>"
             class="btn btn-sm btn-outline-danger"
             onclick="return confirm('¿Seguro que deseas eliminar este abono?')">
            Eliminar
          </a>
          <a class="btn btn-sm btn-outline-info" href="?r=cobrador.ticket&id_abono=<?= (int)$h['id_abono'] ?>">Imprimir ticket</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($hist)): ?>
      <tr>
        <td colspan="5" class="text-center text-muted">Sin abonos registrados</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>




              <?php if (empty($hist)): ?>
              <tr><td colspan="3" class="text-center text-muted">Sin abonos</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php
  render('Nuevo abono', ob_get_clean());
break;

/* =========================================================
 * GUARDAR ABONO (POST + transacción + link a cobrador)
 * =======================================================*/
case 'guardar_abono':
  $idc = (int)($_GET['id_contrato'] ?? 0);
  $monto = (float)($_POST['cant_abono'] ?? 0);
  $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
  $id_cobr = (int)($_POST['id_cobrador'] ?? 0);

  if ($idc<=0 || $monto<=0) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('pagos.contratos');
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();

    q("INSERT INTO futuro_abonos (id_contrato, saldo, cant_abono, fecha_registro)
       VALUES (?, 0, ?, ?)", [$idc, $monto, $fecha]);

    $id_abono = $pdo->lastInsertId();

    if ($id_cobr > 0) {
      q("INSERT INTO futuro_abono_cobrador (id_abono, id_personal, fecha_registro)
         VALUES (?, ?, CURRENT_TIMESTAMP())", [$id_abono, $id_cobr]);
    }

    $s = __saldo_contrato($idc);
    if ($s && $s['saldo'] <= 0.00001) {
      q("UPDATE futuro_contratos SET estatus='pagado' WHERE id_contrato=?", [$idc]);
    }

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Abono registrado.</div>";
    redirect('pagos.nuevo_abono&id_contrato='.$idc);

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo registrar el abono.</div>";
    redirect('pagos.nuevo_abono&id_contrato='.$idc);
  }
break;


case 'borrar_abono':
  $id_abono   = (int)($_GET['id_abono'] ?? 0);
  $id_contrato = (int)($_GET['id_contrato'] ?? 0);

  if ($id_abono > 0 && $id_contrato > 0) {
    // Primero borrar relación con cobrador
    q("DELETE FROM futuro_abono_cobrador WHERE id_abono=?", [$id_abono]);

    // Luego borrar el abono
    q("DELETE FROM futuro_abonos WHERE id_abono=? AND id_contrato=?", [$id_abono,$id_contrato]);

    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Abono eliminado correctamente.</div>";
  }

  redirect("pagos.nuevo_abono&id_contrato=$id_contrato");
break;



/* =========================================================
 * CORTE por persona (cobrador o vendedor)
 * =======================================================*/
case 'corte':
  $tipo = trim($_GET['tipo'] ?? 'cobrador'); // 'cobrador' | 'vendedor'
  $per  = (int)($_GET['id_personal'] ?? 0);
  $f1   = trim($_GET['f1'] ?? '');
  $f2   = trim($_GET['f2'] ?? '');

  $personas = __get_personal_activo();

  $rows = [];
  $total = 0.0;

if ($per > 0) {
    $params = [$per];
    $condiciones = [];

    // Filtros de fecha
    if ($f1 !== '') {
        $condiciones[] = "a.fecha_registro >= ?";
        $params[] = $f1;
    }
    if ($f2 !== '') {
        $condiciones[] = "a.fecha_registro <= ?";
        $params[] = $f2;
    }

    if ($tipo === 'cobrador') {
        $sql = "
            SELECT a.id_abono, a.id_contrato,
                   (SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
                      FROM titular_contrato tc
                      JOIN titulares t ON t.id_titular=tc.id_titular
                     WHERE tc.id_contrato=a.id_contrato
                     ORDER BY tc.id_titular_contrato DESC
                     LIMIT 1) AS titular,
                   DATE(a.fecha_registro) AS fecha,
                   a.cant_abono
            FROM futuro_abonos a
            JOIN futuro_abono_cobrador ac ON ac.id_abono=a.id_abono
            WHERE ac.id_personal=?
        ";

    } else { // vendedor
        $sql = "
            SELECT a.id_abono, a.id_contrato,
                   (SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
                      FROM titular_contrato tc
                      JOIN titulares t ON t.id_titular=tc.id_titular
                     WHERE tc.id_contrato=a.id_contrato
                     ORDER BY tc.id_titular_contrato DESC
                     LIMIT 1) AS titular,
                   DATE(a.fecha_registro) AS fecha,
                   a.cant_abono
            FROM futuro_abonos a
            JOIN futuro_contrato_vendedor fcv ON fcv.id_contrato=a.id_contrato
            WHERE fcv.id_personal=?
        ";
    }

    // Agregar condiciones de fecha si existen
    if (!empty($condiciones)) {
        $sql .= " AND " . implode(" AND ", $condiciones);
    }

    $sql .= " ORDER BY a.id_abono DESC LIMIT 1000";

    $rows = qall($sql, $params);

    foreach ($rows as $r) {
        $total += (float)$r['cant_abono'];
    }
}


  ob_start(); ?>
  <h1 class="h4 mb-3">Corte de pagos por persona</h1>
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="pagos.corte">
    <div class="col-md-3">
      <select name="tipo" class="form-select">
        <option value="cobrador" <?= $tipo==='cobrador'?'selected':'' ?>>Cobrador</option>
        <option value="vendedor" <?= $tipo==='vendedor'?'selected':'' ?>>Vendedor</option>
      </select>
    </div>
    <div class="col-md-4">
      <select name="id_personal" class="form-select" required>
        <option value="0">— Selecciona persona —</option>
        <?php foreach($personas as $p): ?>
          <option value="<?= (int)$p['id_personal'] ?>" <?= $per===(int)$p['id_personal']?'selected':'' ?>>
            <?= e($p['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="f1" class="form-control" value="<?= e($f1) ?>" placeholder="Desde">
    </div>
    <div class="col-md-2">
      <input type="date" name="f2" class="form-control" value="<?= e($f2) ?>" placeholder="Hasta">
    </div>
    <div class="col-md-1 d-grid">
      <button class="btn btn-outline-primary">Calcular</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead>
        <tr>
          <th>ID Abono</th>
          <th>Contrato</th>
          <th>Titular</th>
          <th>Fecha</th>
          <th class="text-end">Monto</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id_abono'] ?></td>
          <td>#<?= (int)$r['id_contrato'] ?></td>
          <td><?= e($r['titular'] ?? '') ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td class="text-end">$<?= number_format((float)$r['cant_abono'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows) && $per>0): ?>
        <tr><td colspan="5" class="text-center text-muted">Sin abonos en el rango</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="4" class="text-end">Total</th>
          <th class="text-end">$<?= number_format($total,2) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <a class="btn btn-link" href="?r=pagos.contratos">Volver</a>
  <?php
  render('Corte por persona', ob_get_clean());
break;

/* =========================================================
 * COMISIONES (captura manual)
 * =======================================================*/
case 'comisiones':
  $idc = (int)($_GET['id_contrato'] ?? 0);
  $vendedores = __get_personal_activo();

  $info = null; $saldo=null;
  if ($idc>0) {
    $info = qone("SELECT id_contrato, costo_final, DATE(fecha_registro) AS fecha FROM futuro_contratos WHERE id_contrato=?", [$idc]);
    if ($info) $saldo = __saldo_contrato($idc);
  }

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Capturar comisión (manual)</h1>
      <?php if($info): ?>
        <div class="alert alert-light border small">
          <div><strong>Contrato:</strong> #<?= (int)$info['id_contrato'] ?> · <?= e($info['fecha']) ?></div>
          <div><strong>Costo:</strong> $<?= number_format($saldo['costo_final'],2) ?> ·
               <strong>Pagado:</strong> $<?= number_format($saldo['pagado'],2) ?> ·
               <strong>Saldo:</strong> $<?= number_format($saldo['saldo'],2) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="?r=pagos.guardar_comision">
        <div class="row g-2">
          <div class="col-md-4">
            <?= form_input('id_contrato','Contrato #', $idc>0?$idc:'', ['type'=>'number','min'=>'1','required'=>true]) ?>
          </div>
          <div class="col-md-8">
            <label class="form-label">Vendedor</label>
            <select name="id_vendedor" class="form-select" required>
              <option value="">— Selecciona vendedor —</option>
              <?php foreach($vendedores as $v): ?>
                <option value="<?= (int)$v['id_personal'] ?>"><?= e($v['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row g-2">
          <div class="col-md-4"><?= form_input('cant_comision','Comisión a pagar', '', ['type'=>'number','step'=>'0.01','min'=>'0','required'=>true]) ?></div>
          <div class="col-md-4"><?= form_input('descuento','Descuento (si aplica)', '0', ['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
          <div class="col-md-4"><?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?></div>
        </div>

        <?= form_input('estatus','Estatus','pendiente') ?>
        <?= form_input('notas','Notas (opcional)') ?>

        <div class="d-grid mt-2">
          <button class="btn btn-primary">Guardar comisión</button>
        </div>
      </form>
      <a class="btn btn-link mt-2" href="?r=pagos.contratos">Volver</a>
    </div>
  </div>
  <?php
  render('Comisiones', ob_get_clean());
break;

/* =========================================================
 * GUARDAR COMISION (POST) → futuro_comision_semanal
 * =======================================================*/
case 'guardar_comision':
  $idc  = (int)($_POST['id_contrato'] ?? 0);
  $idv  = (int)($_POST['id_vendedor'] ?? 0);
  $monto= (float)($_POST['cant_comision'] ?? 0);
  $desc = (float)($_POST['descuento'] ?? 0);
  $fecha= trim($_POST['fecha'] ?? date('Y-m-d'));
  $estatus = trim($_POST['estatus'] ?? 'pendiente');

  if ($idc<=0 || $idv<=0) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Contrato y vendedor son obligatorios.</div>";
    redirect('pagos.comisiones&id_contrato='.$idc);
  }

  $final = max(0, $monto - $desc);

  q("INSERT INTO futuro_comision_semanal
     (id_abono, id_contrato, estatus, cant_comision, saldo_comision, descuento, cant_com_final, comisionista, id_comisionista, fecha_registro)
     VALUES (0, ?, ?, ?, 0, ?, ?, 'vendedor', ?, ?)",
     [$idc, $estatus, $monto, $desc, $final, $idv, $fecha]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Comisión registrada.</div>";
  redirect('pagos.comisiones&id_contrato='.$idc);
break;

/* =========================================================
 * EDITAR CONTRATO (formulario)
 * =======================================================*/
case 'editar_contrato':
  $idc = (int)($_GET['id_contrato'] ?? 0);
  $c = qone("SELECT id_contrato, tipo_contrato, tipo_pago, costo_contrato, descuento, costo_final,
                    periodo_pago, compromiso_pago, estatus
             FROM futuro_contratos WHERE id_contrato=?", [$idc]);
  if (!$c) { $_SESSION['_alerts']="<div class='alert alert-warning'>Contrato no encontrado.</div>"; redirect('pagos.contratos'); }

  // catálogo de “adicionales” activos
  $catalogo = qall("SELECT id_mos_cat_com, codigo, concepto
                    FROM futuro_mostrar_catalogo_promociones
                    WHERE estatus=1 ORDER BY concepto ASC");

  // adicionales ya asociados al contrato
  $adic_sel = qall("SELECT id_mos_cat_com FROM futuro_cont_cat_com WHERE id_contrato=?", [$idc]);
  $adic_sel_ids = array_map(fn($r)=> (int)$r['id_mos_cat_com'], $adic_sel);

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar contrato #<?= (int)$idc ?></h1>

      <form method="post" action="?r=pagos.guardar_contrato&id_contrato=<?= (int)$idc ?>">
        <div class="row g-3">
          <div class="col-md-4">
            <?= form_input('tipo_contrato','Paquete / Tipo de contrato', $c['tipo_contrato'] ?? '') ?>
          </div>
          <div class="col-md-4">
            <?= form_input('tipo_pago','Tipo de pago', $c['tipo_pago'] ?? '') ?>
          </div>
          <div class="col-md-4">
            <?= form_input('estatus','Estatus', $c['estatus'] ?? 'activo') ?>
          </div>

          <div class="col-md-4">
            <?= form_input('costo_contrato','Costo base', (string)$c['costo_contrato'], ['type'=>'number','step'=>'0.01','min'=>'0','id'=>'costo_contrato']) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('descuento','Descuento', (string)$c['descuento'], ['type'=>'number','step'=>'0.01','min'=>'0','id'=>'descuento']) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('costo_final','Costo final', (string)$c['costo_final'], ['type'=>'number','step'=>'0.01','min'=>'0','id'=>'costo_final']) ?>
            <div class="form-text">Se recalcula como <em>Costo base − Descuento</em>. Puedes ajustarlo manualmente si lo requieres.</div>
          </div>

          <div class="col-md-6">
            <?= form_input('periodo_pago','Periodo de pago (p.ej. semanal/quincenal/mensual)', $c['periodo_pago'] ?? '') ?>
          </div>
          <div class="col-md-6">
            <?= form_input('compromiso_pago','Compromiso de pago', (string)($c['compromiso_pago'] ?? ''), ['type'=>'number','step'=>'0.01','min'=>'0']) ?>
          </div>

          <div class="col-12">
            <label class="form-label">Adicionales</label>
            <div class="border rounded p-2" style="max-height:280px;overflow:auto">
              <?php foreach($catalogo as $row): $id = (int)$row['id_mos_cat_com']; ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="adicional_ids[]" id="ad<?= $id ?>"
                         value="<?= $id ?>" <?= in_array($id,$adic_sel_ids,true)?'checked':'' ?>>
                  <label class="form-check-label" for="ad<?= $id ?>">
                    <strong><?= e($row['concepto']) ?></strong>
                    <span class="text-muted">[<?= e($row['codigo']) ?>]</span>
                  </label>
                </div>
              <?php endforeach; ?>
              <?php if (empty($catalogo)): ?>
                <div class="text-muted small">No hay adicionales activos en el catálogo.</div>
              <?php endif; ?>
            </div>
            <div class="form-text">Estos adicionales se guardan en la relación <code>futuro_cont_cat_com</code> y no alteran el costo automáticamente (no existe campo de precio en el catálogo).</div>
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-primary">Guardar cambios</button>
          </div>
        </div>
      </form>

      <a class="btn btn-link mt-2" href="?r=pagos.contratos">Volver</a>
    </div>
  </div>

  <script>
  // Recalcular costo_final al vuelo
  (function(){
    const base = document.getElementById('costo_contrato');
    const desc = document.getElementById('descuento');
    const fin  = document.getElementById('costo_final');
    function recalc(){
      const b = parseFloat(base.value||0), d = parseFloat(desc.value||0);
      const r = Math.max(0, (b - d));
      if(!isNaN(r)) fin.value = r.toFixed(2);
    }
    base?.addEventListener('input', recalc);
    desc?.addEventListener('input', recalc);
  })();
  </script>
  <?php
  render('Editar contrato', ob_get_clean());
break;

/* =========================================================
 * GUARDAR CONTRATO (POST)
 * =======================================================*/
case 'guardar_contrato':
  $idc = (int)($_GET['id_contrato'] ?? 0);
  if ($idc<=0) { $_SESSION['_alerts']="<div class='alert alert-danger'>Contrato inválido.</div>"; redirect('pagos.contratos'); }

  $tipo_contrato  = trim($_POST['tipo_contrato'] ?? '');
  $tipo_pago      = trim($_POST['tipo_pago'] ?? '');
  $estatus        = trim($_POST['estatus'] ?? 'activo');
  $costo_contrato = (float)($_POST['costo_contrato'] ?? 0);
  $descuento      = (float)($_POST['descuento'] ?? 0);
  $costo_final    = (float)($_POST['costo_final'] ?? ($costo_contrato - $descuento));
  $periodo_pago   = trim($_POST['periodo_pago'] ?? '');
  $compromiso     = $_POST['compromiso_pago'] === '' ? null : (float)$_POST['compromiso_pago'];

  $adicional_ids  = isset($_POST['adicional_ids']) && is_array($_POST['adicional_ids'])
                    ? array_map('intval', $_POST['adicional_ids']) : [];

  $pdo = db();
  try{
    $pdo->beginTransaction();

    // Actualiza contrato
    q("UPDATE futuro_contratos
       SET tipo_contrato=?, tipo_pago=?, costo_contrato=?, descuento=?, costo_final=?,
           periodo_pago=?, compromiso_pago=?, estatus=?
       WHERE id_contrato=?",
      [$tipo_contrato, $tipo_pago, $costo_contrato, $descuento, max(0,$costo_final),
       $periodo_pago, $compromiso, $estatus, $idc]);

    // Sincroniza adicionales (borrar todo e insertar selección actual)
    q("DELETE FROM futuro_cont_cat_com WHERE id_contrato=?", [$idc]);
    if (!empty($adicional_ids)) {
      foreach ($adicional_ids as $idm) {
        q("INSERT INTO futuro_cont_cat_com (id_contrato, id_mos_cat_com) VALUES (?,?)", [$idc, $idm]);
      }
    }

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Contrato actualizado.</div>";
  } catch(Exception $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo guardar: ".e($e->getMessage())."</div>";
  }

  redirect("pagos.editar_contrato&id_contrato=".$idc);
break;


/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('pagos.contratos');
}
