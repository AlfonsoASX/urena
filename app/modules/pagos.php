<?php
// app/modules/pagos.php (versión optimizada sin MAX_JOIN_SIZE)
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/futuros_promociones.php';

require_login();
futuros_promociones_ensure_schema();

$action = $action ?? 'contratos';
if (in_array($action, ['reporte_base', 'reporte_comisiones', 'reporte_cobradores', 'corte'], true)) {
  db_enable_big_selects();
}

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

function __mes_reporte($value): string {
  $value = trim((string)$value);
  return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : date('Y-m');
}

function __rango_mes(string $mes): array {
  $inicio = $mes.'-01';
  return [$inicio, date('Y-m-t', strtotime($inicio))];
}

function __nombre_personal(int $id_personal): string {
  if ($id_personal <= 0) return '';
  $row = qone("
    SELECT TRIM(CONCAT(COALESCE(nombre,''),' ',COALESCE(apellido_p,''),' ',COALESCE(apellido_m,''))) AS nombre
    FROM futuro_personal
    WHERE id_personal=?
  ", [$id_personal]);
  return trim((string)($row['nombre'] ?? ''));
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
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead>
                <tr>
                  <th>ID Abono</th>
                  <th>Fecha</th>
                  <th class="text-end">Monto</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($hist as $h): ?>
                <tr>
                  <td>#<?= (int)$h['id_abono'] ?></td>
                  <td><?= e($h['fecha']) ?></td>
                  <td class="text-end">$<?= number_format((float)$h['cant_abono'],2) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex flex-wrap gap-1">
                      <a href="?r=pagos.borrar_abono&id_abono=<?= (int)$h['id_abono'] ?>&id_contrato=<?= (int)$contr['id_contrato'] ?>"
                         class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Seguro que deseas eliminar este abono?')">
                        Eliminar
                      </a>
                      <a class="btn btn-sm btn-outline-info" href="?r=cobrador.ticket&id_abono=<?= (int)$h['id_abono'] ?>">Imprimir ticket</a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($hist)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted">Sin abonos registrados</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
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
  $id_abono    = (int)($_GET['id_abono'] ?? 0);
  $id_contrato = (int)($_GET['id_contrato'] ?? 0);

  if ($id_abono > 0 && $id_contrato > 0) {
    // 1. Borrar relación con cobrador
    q("DELETE FROM futuro_abono_cobrador WHERE id_abono=?", [$id_abono]);
    // 2. Borrar dependencias en futuro_comcob
    q("DELETE FROM futuro_comcob WHERE id_abono=?", [$id_abono]);
    // 3. NUEVO: Borrar dependencias en futuro_comision_semanal
    q("DELETE FROM futuro_comision_semanal WHERE id_abono=?", [$id_abono]);
    // 4. Finalmente borrar el abono
    q("DELETE FROM futuro_abonos WHERE id_abono=? AND id_contrato=?", [$id_abono, $id_contrato]);

    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Abono eliminado correctamente.</div>";
  }

  redirect("pagos.nuevo_abono&id_contrato=$id_contrato");
break;



/* =========================================================
 * CORTE por persona
 * =======================================================*/
case 'corte':
  $tipo = trim($_GET['tipo'] ?? 'cobrador');
  if (!in_array($tipo, ['cobrador','vendedor'], true)) $tipo = 'cobrador';
  $per  = (int)($_GET['id_personal'] ?? 0);
  $f1   = trim($_GET['f1'] ?? date('Y-m-01'));
  $f2   = trim($_GET['f2'] ?? date('Y-m-t'));

  $personas = __get_personal_activo();
  $rows = [];
  $total = 0.0;

  if ($per > 0 && $tipo === 'cobrador') {
    $where = ["ac.id_personal = ?"];
    $params = [$per];
    if ($f1 !== '') {
      $where[] = "a.fecha_registro >= ?";
      $params[] = $f1.' 00:00:00';
    }
    if ($f2 !== '') {
      $where[] = "a.fecha_registro <= ?";
      $params[] = $f2.' 23:59:59';
    }

    $rows = qall("
      SELECT a.id_abono, a.id_contrato,
             (SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
                FROM titular_contrato tc
                JOIN titulares t ON t.id_titular=tc.id_titular
               WHERE tc.id_contrato=a.id_contrato
               ORDER BY tc.id_titular_contrato DESC
               LIMIT 1) AS titular,
             a.fecha_registro AS fecha,
             a.cant_abono,
             'cobrador' AS tipo_calculo,
             NULL AS numero_pago,
             NULL AS promocion
      FROM futuro_abonos a
      JOIN futuro_abono_cobrador ac
        ON ac.id_abono_cobrador = (
          SELECT ac2.id_abono_cobrador
          FROM futuro_abono_cobrador ac2
          WHERE ac2.id_abono = a.id_abono
          ORDER BY ac2.id_abono_cobrador DESC
          LIMIT 1
        )
      WHERE ".implode(' AND ', $where)."
      ORDER BY a.fecha_registro DESC, a.id_abono DESC
      LIMIT 1000
    ", $params);
  } elseif ($per > 0) {
    foreach (futuros_promociones_pagos_resueltos($f1, $f2, 5000) as $row) {
      if (($row['beneficiario_tipo'] ?? '') !== 'vendedor') continue;
      if ((int)($row['beneficiario_id'] ?? 0) !== $per) continue;
      $rows[] = [
        'id_abono' => $row['id_abono'],
        'id_contrato' => $row['id_contrato'],
        'titular' => $row['titular'] ?? '',
        'fecha' => $row['fecha_registro'],
        'cant_abono' => $row['monto_asignado'],
        'tipo_calculo' => 'vendedor',
        'numero_pago' => $row['numero_pago'],
        'promocion' => $row['promocion'],
      ];
    }
  }

  foreach ($rows as $r) {
    $total += (float)$r['cant_abono'];
  }

  ob_start(); ?>
  <h1 class="h4 mb-3">Corte de pagos por persona</h1>
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="pagos.corte">
    <div class="col-md-3">
      <label class="form-label">Tipo</label>
      <select name="tipo" class="form-select">
        <option value="cobrador" <?= $tipo==='cobrador'?'selected':'' ?>>Cobrador</option>
        <option value="vendedor" <?= $tipo==='vendedor'?'selected':'' ?>>Vendedor</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Persona</label>
      <select name="id_personal" class="form-select" required>
        <option value="0">-- Selecciona persona --</option>
        <?php foreach($personas as $p): ?>
          <option value="<?= (int)$p['id_personal'] ?>" <?= $per===(int)$p['id_personal']?'selected':'' ?>>
            <?= e($p['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="f1" class="form-control" value="<?= e($f1) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="f2" class="form-control" value="<?= e($f2) ?>">
    </div>
    <div class="col-md-1 d-grid align-self-end">
      <button class="btn btn-outline-primary">Calcular</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>ID Abono</th>
          <th>Contrato</th>
          <th>Titular</th>
          <th>Fecha</th>
          <th>No. pago</th>
          <th>Promocion</th>
          <th class="text-end">Monto</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id_abono'] ?></td>
          <td>#<?= (int)$r['id_contrato'] ?></td>
          <td><?= e($r['titular'] ?? '') ?></td>
          <td><?= e(date('Y-m-d', strtotime($r['fecha']))) ?></td>
          <td><?= $r['numero_pago'] ? '#'.(int)$r['numero_pago'] : '-' ?></td>
          <td><?= e($r['promocion'] ?: '-') ?></td>
          <td class="text-end">$<?= number_format((float)$r['cant_abono'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows) && $per>0): ?>
        <tr><td colspan="7" class="text-center text-muted">Sin abonos en el rango</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="6" class="text-end">Total</th>
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
 * REPORTE DE COMISIONES A VENDEDORES
 * =======================================================*/
case 'reporte_comisiones':
  $mes_fallback = __mes_reporte($_GET['mes'] ?? '');
  [$default_f1, $default_f2] = __rango_mes($mes_fallback);
  $f1 = trim($_GET['f1'] ?? $default_f1);
  $f2 = trim($_GET['f2'] ?? $default_f2);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f1)) $f1 = $default_f1;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f2)) $f2 = $default_f2;
  if ($f1 > $f2) {
    [$f1, $f2] = [$f2, $f1];
  }
  $id_personal = (int)($_GET['id_personal'] ?? ($_GET['id_vendedor'] ?? 0));

  $pagos_resueltos = futuros_promociones_pagos_resueltos($f1, $f2, 10000);
  $reglas_count = (int)(qone("SELECT COUNT(*) AS total FROM futuro_promocion_reglas WHERE COALESCE(activo,1)=1")['total'] ?? 0);
  $resumen = [];
  $detalle = [];
  $alertas = [
    'sin_promocion' => 0,
    'sin_regla' => 0,
    'sin_vendedor' => 0,
    'fuera_rango' => 0,
  ];

  foreach ($pagos_resueltos as $row) {
    $tipo = (string)($row['beneficiario_tipo'] ?? '');
    $obs = strtolower((string)($row['observacion'] ?? ''));
    $asignable = !empty($row['asignable_persona']);
    $beneficiarioId = (int)($row['beneficiario_id'] ?? 0);
    $monto = (float)($row['monto_asignado'] ?? 0);

    if ($tipo === 'vendedor' && $asignable && $beneficiarioId > 0 && $monto > 0) {
      if (!isset($resumen[$beneficiarioId])) {
        $resumen[$beneficiarioId] = [
          'id_personal' => $beneficiarioId,
          'nombre' => trim((string)($row['beneficiario_nombre'] ?? $row['nombre_vendedor'] ?? '')),
          'total' => 0.0,
          'pagos' => 0,
          'contratos' => [],
        ];
      }
      $resumen[$beneficiarioId]['total'] += $monto;
      $resumen[$beneficiarioId]['pagos']++;
      $resumen[$beneficiarioId]['contratos'][(int)$row['id_contrato']] = true;
      if ($id_personal === $beneficiarioId) {
        $detalle[] = $row;
      }
      continue;
    }

    if (str_contains($obs, 'sin promocion') || str_contains($obs, 'sin promoción')) {
      $alertas['sin_promocion']++;
    } elseif (str_contains($obs, 'no tiene regla')) {
      $alertas['sin_regla']++;
    } elseif ($tipo === 'vendedor' && !$asignable) {
      $alertas['sin_vendedor']++;
    } elseif (str_contains($obs, 'ningun rango') || str_contains($obs, 'ningún rango')) {
      $alertas['fuera_rango']++;
    }
  }

  $resumen_rows = array_values($resumen);
  usort($resumen_rows, fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']));

  $total_general = array_reduce($resumen_rows, fn($sum, $row) => $sum + (float)$row['total'], 0.0);
  $contratos_general = [];
  foreach ($resumen_rows as $row) {
    foreach ($row['contratos'] as $idContrato => $_) {
      $contratos_general[$idContrato] = true;
    }
	  }
	  $detalle_total = array_reduce($detalle, fn($sum, $row) => $sum + (float)$row['monto_asignado'], 0.0);
	  $detalle_nombre = $id_personal > 0 ? ($resumen[$id_personal]['nombre'] ?? __nombre_personal($id_personal)) : '';
	  $detalle_contratos = [];
	  foreach ($detalle as $row) {
	    $detalle_contratos[(int)$row['id_contrato']] = true;
	  }

	  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Comisiones vendedores</h1>
      <div class="text-muted small">Calculado por promocion, paquete y numero real de pago del contrato.</div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
	      <form class="row g-2 align-items-end" method="get">
	        <input type="hidden" name="r" value="pagos.reporte_comisiones">
	        <?php if ($id_personal > 0): ?>
	          <input type="hidden" name="id_personal" value="<?= (int)$id_personal ?>">
	        <?php endif; ?>
	        <div class="col-md-3">
	          <label class="form-label">Desde</label>
	          <input type="date" name="f1" class="form-control" value="<?= e($f1) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="f2" class="form-control" value="<?= e($f2) ?>" required>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary">Generar</button>
        </div>
        <?php if ($id_personal > 0): ?>
          <div class="col-md-3 d-grid">
            <a class="btn btn-outline-secondary" href="?r=pagos.reporte_comisiones&f1=<?= e(urlencode($f1)) ?>&f2=<?= e(urlencode($f2)) ?>">Ver resumen</a>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

	  <?php if ($reglas_count === 0): ?>
	    <div class="alert alert-warning">
	      No hay reglas activas en promociones. El reporte no genera comisiones de vendedores hasta que las promociones tengan rangos configurados.
	    </div>
	  <?php endif; ?>

	  <?php if ($id_personal <= 0 && array_sum($alertas) > 0): ?>
	    <div class="alert alert-info small">
	      Pagos no incluidos en comision:
	      <?= (int)$alertas['sin_promocion'] ?> sin promocion,
      <?= (int)$alertas['sin_regla'] ?> sin regla,
      <?= (int)$alertas['sin_vendedor'] ?> sin vendedor asignado,
      <?= (int)$alertas['fuera_rango'] ?> fuera de rango.
	    </div>
	  <?php endif; ?>

	  <?php if ($id_personal <= 0): ?>
	    <div class="row g-2 mb-3">
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Total vendedores</span><strong>$<?= number_format($total_general, 2) ?></strong></div></div>
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Contratos con comision</span><strong><?= count($contratos_general) ?></strong></div></div>
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Vendedores</span><strong><?= count($resumen_rows) ?></strong></div></div>
	    </div>

	    <div class="table-responsive mb-4">
	      <table class="table table-sm table-striped table-hover align-middle">
	        <thead class="table-light">
	          <tr>
	            <th>Vendedor</th>
	            <th class="text-end">Contratos con comision</th>
	            <th class="text-end">Pagos</th>
	            <th class="text-end">Ganancia</th>
	          </tr>
	        </thead>
	        <tbody>
	          <?php foreach ($resumen_rows as $row): ?>
	          <tr>
	            <td>
	              <a href="?r=pagos.reporte_comisiones&f1=<?= e(urlencode($f1)) ?>&f2=<?= e(urlencode($f2)) ?>&id_personal=<?= (int)$row['id_personal'] ?>">
	                <?= e($row['nombre'] ?: 'Vendedor #'.(int)$row['id_personal']) ?>
	              </a>
	            </td>
	            <td class="text-end"><?= count($row['contratos']) ?></td>
	            <td class="text-end"><?= (int)$row['pagos'] ?></td>
	            <td class="text-end fw-semibold">$<?= number_format((float)$row['total'], 2) ?></td>
	          </tr>
	          <?php endforeach; ?>
	          <?php if (empty($resumen_rows)): ?>
	          <tr><td colspan="4" class="text-center text-muted">Sin comisiones de vendedores para este rango.</td></tr>
	          <?php endif; ?>
	        </tbody>
	        <tfoot>
	          <tr class="table-light">
	            <th colspan="3" class="text-end">Total</th>
	            <th class="text-end">$<?= number_format($total_general, 2) ?></th>
	          </tr>
	        </tfoot>
	      </table>
	    </div>
	  <?php endif; ?>

	  <?php if ($id_personal > 0): ?>
	    <h2 class="h5 mb-2">Detalle de <?= e($detalle_nombre ?: 'vendedor #'.$id_personal) ?></h2>
	    <div class="row g-2 mb-3">
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Total del vendedor</span><strong>$<?= number_format($detalle_total, 2) ?></strong></div></div>
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Contratos con comision</span><strong><?= count($detalle_contratos) ?></strong></div></div>
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Pagos/comisiones</span><strong><?= count($detalle) ?></strong></div></div>
	    </div>
	    <div class="table-responsive">
	      <table class="table table-sm table-hover table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Abono</th>
            <th>Contrato</th>
            <th>Titular</th>
            <th>Paquete</th>
            <th>Promocion</th>
            <th>No. pago</th>
            <th>Cobrador</th>
            <th class="text-end">Monto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detalle as $r): ?>
          <tr>
            <td><?= e(date('Y-m-d', strtotime($r['fecha_registro']))) ?></td>
            <td>#<?= (int)$r['id_abono'] ?></td>
            <td>#<?= (int)$r['id_contrato'] ?></td>
            <td><?= e($r['titular'] ?? '-') ?></td>
            <td><?= e($r['tipo_contrato'] ?? '-') ?></td>
            <td><?= e($r['promocion'] ?? '-') ?></td>
            <td>#<?= (int)$r['numero_pago'] ?></td>
            <td><?= e(trim($r['nombre_cobrador'] ?? '') ?: '-') ?></td>
            <td class="text-end fw-semibold">$<?= number_format((float)$r['monto_asignado'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($detalle)): ?>
          <tr><td colspan="9" class="text-center text-muted p-3">No hay detalle para este vendedor en el rango seleccionado.</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="table-dark">
            <td colspan="8" class="text-end">Total detalle</td>
            <td class="text-end fw-bold">$<?= number_format($detalle_total, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>

  <?php
  render('Comisiones vendedores', ob_get_clean());
break;

/* =========================================================
 * REPORTE DE COBRADORES
 * =======================================================*/
case 'reporte_cobradores':
  $mes_fallback = __mes_reporte($_GET['mes'] ?? '');
  [$default_f1, $default_f2] = __rango_mes($mes_fallback);
  $f1 = trim($_GET['f1'] ?? $default_f1);
  $f2 = trim($_GET['f2'] ?? $default_f2);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f1)) $f1 = $default_f1;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f2)) $f2 = $default_f2;
  if ($f1 > $f2) {
    [$f1, $f2] = [$f2, $f1];
  }
  $id_personal = (int)($_GET['id_personal'] ?? 0);

  $rows = qall("
    SELECT a.id_abono, a.id_contrato, a.fecha_registro, a.cant_abono,
           ac.id_personal AS id_cobrador,
           TRIM(CONCAT(COALESCE(fp.nombre,''),' ',COALESCE(fp.apellido_p,''),' ',COALESCE(fp.apellido_m,''))) AS nombre_cobrador,
           (
             SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
             FROM titular_contrato tc
             JOIN titulares t ON t.id_titular = tc.id_titular
             WHERE tc.id_contrato = a.id_contrato
             ORDER BY tc.id_titular_contrato DESC
             LIMIT 1
           ) AS titular
    FROM futuro_abonos a
    JOIN futuro_abono_cobrador ac
      ON ac.id_abono_cobrador = (
        SELECT ac2.id_abono_cobrador
        FROM futuro_abono_cobrador ac2
        WHERE ac2.id_abono = a.id_abono
        ORDER BY ac2.id_abono_cobrador DESC
        LIMIT 1
      )
    LEFT JOIN futuro_personal fp ON fp.id_personal = ac.id_personal
    WHERE a.fecha_registro >= ? AND a.fecha_registro <= ?
    ORDER BY a.fecha_registro DESC, a.id_abono DESC
    LIMIT 10000
  ", [$f1.' 00:00:00', $f2.' 23:59:59']);

  $sin_cobrador = qone("
    SELECT COUNT(*) AS total, COALESCE(SUM(a.cant_abono),0) AS monto
    FROM futuro_abonos a
    LEFT JOIN futuro_abono_cobrador ac
      ON ac.id_abono_cobrador = (
        SELECT ac2.id_abono_cobrador
        FROM futuro_abono_cobrador ac2
        WHERE ac2.id_abono = a.id_abono
        ORDER BY ac2.id_abono_cobrador DESC
        LIMIT 1
      )
    WHERE a.fecha_registro >= ? AND a.fecha_registro <= ?
      AND ac.id_abono_cobrador IS NULL
  ", [$f1.' 00:00:00', $f2.' 23:59:59']);

  $resumen = [];
  $detalle = [];
  foreach ($rows as $row) {
    $idCobrador = (int)($row['id_cobrador'] ?? 0);
    if ($idCobrador <= 0) continue;
    if (!isset($resumen[$idCobrador])) {
      $resumen[$idCobrador] = [
        'id_personal' => $idCobrador,
        'nombre' => trim((string)($row['nombre_cobrador'] ?? '')),
        'total' => 0.0,
        'pagos' => 0,
      ];
    }
    $resumen[$idCobrador]['total'] += (float)$row['cant_abono'];
    $resumen[$idCobrador]['pagos']++;
    if ($id_personal === $idCobrador) {
      $detalle[] = $row;
    }
  }

  $resumen_rows = array_values($resumen);
  usort($resumen_rows, fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']));
	  $total_general = array_reduce($resumen_rows, fn($sum, $row) => $sum + (float)$row['total'], 0.0);
	  $detalle_total = array_reduce($detalle, fn($sum, $row) => $sum + (float)$row['cant_abono'], 0.0);
	  $detalle_nombre = $id_personal > 0 ? ($resumen[$id_personal]['nombre'] ?? __nombre_personal($id_personal)) : '';
	  $detalle_contratos = [];
	  foreach ($detalle as $row) {
	    $detalle_contratos[(int)$row['id_contrato']] = true;
	  }

	  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Reporte cobradores</h1>
      <div class="text-muted small">Total de abonos registrados por cobrador en el rango seleccionado.</div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
	      <form class="row g-2 align-items-end" method="get">
	        <input type="hidden" name="r" value="pagos.reporte_cobradores">
	        <?php if ($id_personal > 0): ?>
	          <input type="hidden" name="id_personal" value="<?= (int)$id_personal ?>">
	        <?php endif; ?>
	        <div class="col-md-3">
	          <label class="form-label">Desde</label>
	          <input type="date" name="f1" class="form-control" value="<?= e($f1) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="f2" class="form-control" value="<?= e($f2) ?>" required>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary">Generar</button>
        </div>
        <?php if ($id_personal > 0): ?>
          <div class="col-md-3 d-grid">
            <a class="btn btn-outline-secondary" href="?r=pagos.reporte_cobradores&f1=<?= e(urlencode($f1)) ?>&f2=<?= e(urlencode($f2)) ?>">Ver resumen</a>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <?php if ((int)($sin_cobrador['total'] ?? 0) > 0): ?>
    <div class="alert alert-warning small">
      Hay <?= (int)$sin_cobrador['total'] ?> abono(s) del rango sin cobrador asignado por $<?= number_format((float)$sin_cobrador['monto'], 2) ?>. No se incluyen en el total por cobrador.
	    </div>
	  <?php endif; ?>

	  <?php if ($id_personal <= 0): ?>
	    <div class="row g-2 mb-3">
	      <div class="col-md-6"><div class="card card-body py-2"><span class="text-muted small">Total cobrado</span><strong>$<?= number_format($total_general, 2) ?></strong></div></div>
	      <div class="col-md-6"><div class="card card-body py-2"><span class="text-muted small">Cobradores</span><strong><?= count($resumen_rows) ?></strong></div></div>
	    </div>

	    <div class="table-responsive mb-4">
	      <table class="table table-sm table-striped table-hover align-middle">
	        <thead class="table-light">
	          <tr>
	            <th>Cobrador</th>
	            <th class="text-end">Pagos</th>
	            <th class="text-end">Total cobrado</th>
	          </tr>
	        </thead>
	        <tbody>
	          <?php foreach ($resumen_rows as $row): ?>
	          <tr>
	            <td>
	              <a href="?r=pagos.reporte_cobradores&f1=<?= e(urlencode($f1)) ?>&f2=<?= e(urlencode($f2)) ?>&id_personal=<?= (int)$row['id_personal'] ?>">
	                <?= e($row['nombre'] ?: 'Cobrador #'.(int)$row['id_personal']) ?>
	              </a>
	            </td>
	            <td class="text-end"><?= (int)$row['pagos'] ?></td>
	            <td class="text-end fw-semibold">$<?= number_format((float)$row['total'], 2) ?></td>
	          </tr>
	          <?php endforeach; ?>
	          <?php if (empty($resumen_rows)): ?>
	          <tr><td colspan="3" class="text-center text-muted">Sin cobros para este rango.</td></tr>
	          <?php endif; ?>
	        </tbody>
	        <tfoot>
	          <tr class="table-light">
	            <th colspan="2" class="text-end">Total</th>
	            <th class="text-end">$<?= number_format($total_general, 2) ?></th>
	          </tr>
	        </tfoot>
	      </table>
	    </div>
	  <?php endif; ?>

	  <?php if ($id_personal > 0): ?>
	    <h2 class="h5 mb-2">Detalle de <?= e($detalle_nombre ?: 'cobrador #'.$id_personal) ?></h2>
	    <div class="row g-2 mb-3">
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Total del cobrador</span><strong>$<?= number_format($detalle_total, 2) ?></strong></div></div>
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Contratos cobrados</span><strong><?= count($detalle_contratos) ?></strong></div></div>
	      <div class="col-md-4"><div class="card card-body py-2"><span class="text-muted small">Pagos</span><strong><?= count($detalle) ?></strong></div></div>
	    </div>
	    <div class="table-responsive">
	      <table class="table table-sm table-hover table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Abono</th>
            <th>Contrato</th>
            <th>Titular</th>
            <th class="text-end">Monto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detalle as $r): ?>
          <tr>
            <td><?= e(date('Y-m-d', strtotime($r['fecha_registro']))) ?></td>
            <td>#<?= (int)$r['id_abono'] ?></td>
            <td>#<?= (int)$r['id_contrato'] ?></td>
            <td><?= e($r['titular'] ?? '-') ?></td>
            <td class="text-end fw-semibold">$<?= number_format((float)$r['cant_abono'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($detalle)): ?>
          <tr><td colspan="5" class="text-center text-muted p-3">No hay detalle para este cobrador en el rango seleccionado.</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="table-dark">
            <td colspan="4" class="text-end">Total detalle</td>
            <td class="text-end fw-bold">$<?= number_format($detalle_total, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>

  <?php
  render('Reporte cobradores', ob_get_clean());
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
 * REPORTE BASE DE PAGOS POR PROMOCION
 * =======================================================*/
case 'reporte_base':
  $f1 = trim($_GET['f1'] ?? '');
  $f2 = trim($_GET['f2'] ?? '');
  $rows = futuros_promociones_pagos_resueltos($f1, $f2, 5000);

  $totales = [
    'vendedor' => 0.0,
    'supervisor' => 0.0,
    'empresa' => 0.0,
    'sin_regla' => 0,
  ];
  foreach ($rows as $row) {
    $tipo = $row['beneficiario_tipo'] ?? 'sin_regla';
    if (isset($totales[$tipo]) && $tipo !== 'sin_regla') {
      $totales[$tipo] += (float)$row['monto_asignado'];
    } elseif ($tipo === 'sin_regla') {
      $totales['sin_regla']++;
    }
  }

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Reporte de pagos por promocion</h1>
      <div class="text-muted small">Cada pago se asigna por numero de abono dentro del contrato.</div>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="pagos.reporte_base">
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="f1" class="form-control" value="<?= e($f1) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="f2" class="form-control" value="<?= e($f2) ?>">
    </div>
    <div class="col-md-2 d-grid align-self-end">
      <button class="btn btn-outline-primary">Consultar</button>
    </div>
  </form>

  <div class="row g-2 mb-3">
    <div class="col-md-3"><div class="card card-body py-2"><span class="text-muted small">Vendedor</span><strong>$<?= number_format($totales['vendedor'],2) ?></strong></div></div>
    <div class="col-md-3"><div class="card card-body py-2"><span class="text-muted small">Supervisor</span><strong>$<?= number_format($totales['supervisor'],2) ?></strong></div></div>
    <div class="col-md-3"><div class="card card-body py-2"><span class="text-muted small">Empresa</span><strong>$<?= number_format($totales['empresa'],2) ?></strong></div></div>
    <div class="col-md-3"><div class="card card-body py-2"><span class="text-muted small">Sin regla</span><strong><?= (int)$totales['sin_regla'] ?> pago(s)</strong></div></div>
  </div>

  <div class="table-responsive">
    <table id="tablaReporte" class="table table-sm table-striped table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Pago</th>
          <th>Fecha</th>
          <th>Contrato</th>
          <th>Paquete</th>
          <th>Promocion</th>
          <th>No. pago</th>
          <th class="text-end">Monto</th>
          <th>Cobrador</th>
          <th>Vendedor</th>
          <th>Beneficiario</th>
          <th>Observacion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <?php
            $badge = match($r['beneficiario_tipo']) {
              'vendedor' => 'success',
              'supervisor' => 'primary',
              'empresa' => 'dark',
              default => 'secondary',
            };
          ?>
          <tr>
            <td>#<?= (int)$r['id_abono'] ?></td>
            <td><?= e(date('Y-m-d', strtotime($r['fecha_registro']))) ?></td>
            <td>#<?= (int)$r['id_contrato'] ?></td>
            <td><?= e($r['tipo_contrato'] ?? '-') ?></td>
            <td><?= e($r['promocion'] ?? '-') ?></td>
            <td>#<?= (int)$r['numero_pago'] ?></td>
            <td class="text-end">$<?= number_format((float)$r['cant_abono'],2) ?></td>
            <td><?= e(trim($r['nombre_cobrador'] ?? '') ?: '-') ?></td>
            <td><?= e(trim($r['nombre_vendedor'] ?? '') ?: '-') ?></td>
            <td><span class="badge text-bg-<?= $badge ?>"><?= e($r['beneficiario_nombre']) ?></span></td>
            <td class="small text-muted"><?= e($r['observacion'] ?: '-') ?></td>
          </tr>
	        <?php endforeach; ?>
	        <?php if(empty($rows)): ?>
	          <tr>
	            <td class="text-center text-muted">Sin resultados</td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	            <td></td>
	          </tr>
	        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"/>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
  <script>
  $(document).ready(function() {
    $('#tablaReporte').DataTable({
      pageLength: 25,
      order: [[1, "asc"]],
      language: { url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json" }
    });
  });
  </script>
  <?php
  render('Reporte de pagos por promocion', ob_get_clean());
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

  $promociones_opts = futuros_promociones_opciones(true);
  $promo_actual = qone("SELECT id_mos_cat_com FROM futuro_cont_cat_com WHERE id_contrato=? ORDER BY id_cont_cat_com DESC LIMIT 1", [$idc]);
  $id_promocion_sel = (int)($promo_actual['id_mos_cat_com'] ?? 0);

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
            <?= form_select('id_promocion', 'Promocion', [0 => '-- Sin promocion --'] + $promociones_opts, $id_promocion_sel, ['class'=>'form-select']) ?>
            <div class="form-text">La promocion define a quien corresponde cada pago en los reportes de futuros.</div>
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

  $id_promocion = (int)($_POST['id_promocion'] ?? 0);

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

    q("DELETE FROM futuro_cont_cat_com WHERE id_contrato=?", [$idc]);
    if ($id_promocion > 0) {
      q("INSERT INTO futuro_cont_cat_com (id_contrato, id_mos_cat_com) VALUES (?,?)", [$idc, $id_promocion]);
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
