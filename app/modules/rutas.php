<?php
// app/modules/rutas.php — con asignación masiva de cobrador
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/usuarios_directorio.php';

require_login();

$action = $action ?? 'contratos';

/* ---------------------------------------------
 * Utilidades
 * ---------------------------------------------*/
function __get_personal_activo() {
  return usuarios_directorio_activos(['cobrador']);
}

function __titular_contrato($id_contrato) {
  $r = qone("SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m) AS titular
             FROM titular_contrato tc
             JOIN titulares t ON t.id_titular=tc.id_titular
             WHERE tc.id_contrato=? ORDER BY tc.id_titular_contrato DESC LIMIT 1", [$id_contrato]);
  return $r['titular'] ?? '—';
}

function __vendedor_contrato($id_contrato) {
  $r = qone("SELECT u.nombre AS vendedor FROM futuro_contratos c LEFT JOIN usuarios u ON u.id=c.id_usuario_vendedor WHERE c.id_contrato=?", [$id_contrato]);
  return $r['vendedor'] ?? '—';
}

function __cobrador_actual($id_contrato) {
  $r = qone("SELECT u.id AS id_usuario,u.nombre AS cobrador
             FROM futuro_contrato_cobrador fcc
             JOIN usuarios u ON u.id=fcc.id_usuario
             WHERE fcc.id_contrato=?
             ORDER BY fcc.id_cont_cob DESC
             LIMIT 1", [$id_contrato]);
  return $r ?: ['id_usuario'=>0,'cobrador'=>'—'];
}

/* ---------------------------------------------
 * Rutas
 * ---------------------------------------------*/
switch ($action) {

/* =========================================================
 * VISTA: CONTRATOS (asignar cobrador por fila + MASIVO)
 * =======================================================*/
case 'contratos':
  $q = trim($_GET['q'] ?? ''); // buscar por folio, titular o vendedor
  $where = "1=1";
  $params = [];

  if ($q !== '') {
    if (ctype_digit($q)) {
      $where .= " AND c.id_contrato=?";
      $params[] = (int)$q;
    } else {
      $like = '%'.$q.'%';
      $where .= " AND ( 
        EXISTS (
          SELECT 1 FROM titular_contrato tc
          JOIN titulares t ON t.id_titular=tc.id_titular
          WHERE tc.id_contrato=c.id_contrato AND (t.nombre LIKE ? OR t.apellido_p LIKE ? OR t.apellido_m LIKE ?)
        )
        OR
        EXISTS (SELECT 1 FROM usuarios uv WHERE uv.id=c.id_usuario_vendedor AND uv.nombre LIKE ?)
      )";
      array_push($params, $like,$like,$like,$like);
    }
  }

$contratos = qall("
  SELECT
    c.id_contrato,
    DATE(c.fecha_registro) AS fecha,
    c.estatus,
    c.costo_final,
    CONCAT_WS(' ',t.nombre,t.apellido_p,t.apellido_m) AS titular,
    COALESCE(uv.nombre,'—') AS vendedor,
    COALESCE(uc.nombre,'—') AS cobrador,
    COALESCE(uc.id,0) AS id_cobr,

    d.colonia AS colonia_orden,
    CONCAT(
      d.calle,' ', d.num_ext,
      IF(d.num_int IS NOT NULL AND d.num_int<>'', CONCAT(' Int. ', d.num_int), ''),
      ', ', d.colonia, ', ', d.municipio
    ) AS direccion

  FROM futuro_contratos c

  /* Último titular por contrato (evita ORDER BY ... LIMIT 1 por fila) */
  LEFT JOIN (
    SELECT tc1.id_contrato, tc1.id_titular
    FROM titular_contrato tc1
    JOIN (
      SELECT id_contrato, MAX(id_titular_contrato) AS max_tc
      FROM titular_contrato
      GROUP BY id_contrato
    ) tcm ON tcm.id_contrato = tc1.id_contrato
         AND tcm.max_tc     = tc1.id_titular_contrato
  ) tc ON tc.id_contrato = c.id_contrato
  LEFT JOIN titulares t ON t.id_titular = tc.id_titular

  /* Último domicilio por titular */
  LEFT JOIN (
    SELECT td1.id_titular, td1.id_domicilio
    FROM titular_dom td1
    JOIN (
      SELECT id_titular, MAX(id_titular_dom) AS max_td
      FROM titular_dom
      GROUP BY id_titular
    ) tdm ON tdm.id_titular = td1.id_titular
         AND tdm.max_td     = td1.id_titular_dom
  ) td ON td.id_titular = tc.id_titular

  /* Domicilio */
  LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
  LEFT JOIN usuarios uv ON uv.id = c.id_usuario_vendedor

  /* Último cobrador asignado */
  LEFT JOIN (
    SELECT id_contrato, MAX(id_cont_cob) AS max_fcc
    FROM futuro_contrato_cobrador
    GROUP BY id_contrato
  ) fccm ON fccm.id_contrato = c.id_contrato
  LEFT JOIN futuro_contrato_cobrador fcc ON fcc.id_cont_cob = fccm.max_fcc
  LEFT JOIN usuarios uc ON uc.id = fcc.id_usuario

  WHERE $where
  ORDER BY d.colonia ASC, c.id_contrato DESC
  LIMIT 500
", $params);



  $cobradores = __get_personal_activo();

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Rutas · Contratos y cobradores</h1>
    <div>
      <a class="btn btn-outline-primary btn-sm" href="?r=rutas.cobradores">Ver por cobrador</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="rutas.contratos">
    <div class="col-md-9">
      <input class="form-control" type="search" name="q" placeholder="Folio, titular o vendedor..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-3 d-grid">
      <button class="btn btn-outline-primary">Buscar</button>
    </div>
  </form>

  <!-- Formulario MASIVO (envolvemos la tabla) -->
  <form method="post" action="?r=rutas.asignar_masivo">
    <div class="row g-2 mb-2">
      <div class="col-md-3 text-md-end text-muted d-flex align-items-center justify-content-md-end">
        <small>Tip: marca contratos y usa el botón para asignación masiva.</small>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th style="width:36px">
              <input type="checkbox" id="chk-all" title="Seleccionar todos">
            </th>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Dirección</th>
            <th>Titular</th>
            <th>Cobrador asignado</th>
            <th style="width:260px">Asignar/Modificar (rápido)</th>
            <th class="text-end">Costo</th>
            <th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contratos as $row): ?>
          <tr>
            <td>
              <input type="checkbox" name="ids[]" value="<?= (int)$row['id_contrato'] ?>" class="chk-one">
            </td>
            <td>#<?= (int)$row['id_contrato'] ?></td>
            <td><?= e($row['fecha']) ?></td>
            <td><?= e($row['direccion'] ?: '—') ?></td>
            <td><?= e($row['titular']) ?></td>
            <td><?= e($row['cobrador']) ?></td>
            <td>
              <form class="d-flex gap-2" method="post" action="?r=rutas.asignar">
                <input type="hidden" name="id_contrato" value="<?= (int)$row['id_contrato'] ?>">
                <select name="id_usuario" class="form-select form-select-sm" onchange="this.form.submit()">
                  <option value="0">— Selecciona cobrador —</option>
                  <?php foreach($cobradores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= ((int)$row['id_cobr']===(int)$p['id'])?'selected':'' ?>>
                      <?= e($p['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-sm btn-primary">Guardar</button></noscript>
              </form>
            </td>
            <td class="text-end">$<?= number_format((float)$row['costo_final'],2) ?></td>
            <td><?= e($row['estatus']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($contratos)): ?>
          <tr><td colspan="9" class="text-center text-muted">Sin contratos</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <select name="id_usuario" class="form-select" required>
          <option value="">— Asignar a cobrador… —</option>
          <?php foreach($cobradores as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-grid">
        <button class="btn btn-primary">Asignar a seleccionados</button>
      </div>
    </div>
  </form>

  <script>
  // Seleccionar/Deseleccionar todos
  (function(){
    const all = document.getElementById('chk-all');
    const boxes = document.querySelectorAll('.chk-one');
    if (all) {
      all.addEventListener('change', () => {
        boxes.forEach(b => { b.checked = all.checked; });
      });
    }
  })();
  </script>
  <?php
  render('Rutas · Contratos', ob_get_clean());
break;

/* =========================================================
 * ACCIÓN: ASIGNAR COBRADOR (POST - individual)
 * =======================================================*/
case 'asignar':
  $id_contrato = (int)($_POST['id_contrato'] ?? 0);
  $id_usuario = (int)($_POST['id_usuario'] ?? 0);

  $cobrador = usuarios_directorio_usuario($id_usuario, true);
  if ($id_contrato<=0 || !$cobrador || ($cobrador['puesto'] ?? '') !== 'cobrador') {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Selecciona un cobrador válido.</div>";
    redirect('rutas.contratos');
  }

  q("INSERT INTO futuro_contrato_cobrador (id_contrato,id_usuario,fecha_registro)
     VALUES (?,?,CURRENT_TIMESTAMP())", [$id_contrato,$id_usuario]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Cobrador asignado al contrato #".(int)$id_contrato.".</div>";
  redirect('rutas.contratos');
break;

/* =========================================================
 * ACCIÓN: ASIGNACIÓN MASIVA (POST)
 * =======================================================*/
case 'asignar_masivo':
  $ids = $_POST['ids'] ?? [];
  $id_usuario = (int)($_POST['id_usuario'] ?? 0);

  if (!is_array($ids) || empty($ids)) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Selecciona al menos un contrato.</div>";
    redirect('rutas.contratos');
  }
  $cobrador = usuarios_directorio_usuario($id_usuario, true);
  if (!$cobrador || ($cobrador['puesto'] ?? '') !== 'cobrador') {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Selecciona un cobrador válido.</div>";
    redirect('rutas.contratos');
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO futuro_contrato_cobrador (id_contrato,id_usuario,fecha_registro) VALUES (?,?,CURRENT_TIMESTAMP())");
    $count = 0;
    foreach ($ids as $idc) {
      $idc = (int)$idc;
      if ($idc > 0) {
        $stmt->execute([$idc,$id_usuario]);
        $count++;
      }
    }
    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Asignación masiva realizada: {$count} contrato(s) actualizados.</div>";
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo completar la asignación masiva.</div>";
  }

  redirect('rutas.contratos');
break;

/* =========================================================
 * VISTA: COBRADORES (lista + detalle por cobrador)
 * =======================================================*/
case 'cobradores':
  $idp = (int)($_GET['id_usuario'] ?? 0);

  if ($idp > 0) {
    $pers = usuarios_directorio_usuario($idp);

    $rows = qall("
      SELECT c.id_contrato, DATE(c.fecha_registro) AS fecha, c.estatus, c.costo_final,
             CONCAT_WS(' ',t.nombre,t.apellido_p,t.apellido_m) AS titular,
             COALESCE(uv.nombre,'—') AS vendedor
      FROM futuro_contratos c
      JOIN (
        SELECT id_contrato,MAX(id_cont_cob) AS max_fcc
        FROM futuro_contrato_cobrador
        GROUP BY id_contrato
      ) fccm ON fccm.id_contrato=c.id_contrato
      JOIN futuro_contrato_cobrador fcc ON fcc.id_cont_cob=fccm.max_fcc AND fcc.id_usuario=?
      LEFT JOIN (
        SELECT tc1.id_contrato,tc1.id_titular
        FROM titular_contrato tc1
        JOIN (
          SELECT id_contrato,MAX(id_titular_contrato) AS max_tc
          FROM titular_contrato
          GROUP BY id_contrato
        ) tcm ON tcm.max_tc=tc1.id_titular_contrato
      ) tc ON tc.id_contrato=c.id_contrato
      LEFT JOIN titulares t ON t.id_titular=tc.id_titular
      LEFT JOIN usuarios uv ON uv.id=c.id_usuario_vendedor
      ORDER BY c.id_contrato DESC
      LIMIT 500
    ", [$idp]);

    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 m-0">Rutas · Cobrador: <?= e($pers['nombre'] ?? 'Desconocido') ?></h1>
      <a class="btn btn-outline-secondary btn-sm" href="?r=rutas.cobradores">← Volver a lista</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Folio</th><th>Fecha</th><th>Titular</th><th>Vendedor</th><th class="text-end">Costo</th><th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id_contrato'] ?></td>
            <td><?= e($r['fecha']) ?></td>
            <td><?= e($r['titular']) ?></td>
            <td><?= e($r['vendedor']) ?></td>
            <td class="text-end">$<?= number_format((float)$r['costo_final'],2) ?></td>
            <td><?= e($r['estatus']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted">Este cobrador no tiene contratos asignados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    render('Rutas · Cobrador', ob_get_clean());
    break;
  }

  $cobradores = qall("
    SELECT u.id AS id_usuario,u.nombre,COUNT(fcc.id_contrato) AS contratos
    FROM usuarios u
    LEFT JOIN (
      SELECT r.id_contrato,r.id_usuario
      FROM futuro_contrato_cobrador r
      JOIN (
        SELECT id_contrato,MAX(id_cont_cob) AS max_fcc
        FROM futuro_contrato_cobrador
        GROUP BY id_contrato
      ) latest ON latest.max_fcc=r.id_cont_cob
    ) fcc ON fcc.id_usuario=u.id
    WHERE UPPER(COALESCE(u.estatus,'ACTIVO'))='ACTIVO' AND u.puesto='cobrador'
    GROUP BY u.id,u.nombre
    ORDER BY u.nombre ASC
  ");

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Rutas · Cobradores</h1>
    <div>
      <a class="btn btn-outline-primary btn-sm" href="?r=rutas.contratos">Ver contratos</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>Nombre</th>
          <th class="text-end"># Contratos asignados</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($cobradores as $p): ?>
        <tr>
          <td><?= e($p['nombre']) ?></td>
          <td class="text-end"><?= (int)$p['contratos'] ?></td>
          <td>
            <a class="btn btn-outline-secondary btn-sm" href="?r=rutas.cobradores&id_usuario=<?= (int)$p['id_usuario'] ?>">Ver contratos</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cobradores)): ?>
        <tr><td colspan="3" class="text-center text-muted">No hay cobradores activos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Rutas · Cobradores', ob_get_clean());
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('rutas.contratos');
}
