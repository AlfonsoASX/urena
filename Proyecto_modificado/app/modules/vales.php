<?php
// app/modules/vales.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + BUSCADOR
 * =======================================================*/
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $f1 = trim($_GET['f1'] ?? ''); // desde (YYYY-MM-DD)
  $f2 = trim($_GET['f2'] ?? ''); // hasta (YYYY-MM-DD)

  $where = "1=1";
  $params = [];
  if ($q !== '') {
    $where .= " AND (responsable LIKE ? OR solicitante LIKE ?)";
    $params[] = '%'.$q.'%';
    $params[] = '%'.$q.'%';
  }
  if ($f1 !== '') { $where .= " AND fecha >= ?"; $params[] = $f1; }
  if ($f2 !== '') { $where .= " AND fecha <= ?"; $params[] = $f2; }

  $rows = qall("
    SELECT v.id_vale, v.responsable, v.solicitante, DATE(v.fecha) AS fecha,
           COALESCE(SUM(a.cantidad),0) AS piezas
    FROM vales_salida v
    LEFT JOIN articulos_vale_salida a ON a.id_vale = v.id_vale
    WHERE $where
    GROUP BY v.id_vale, v.responsable, v.solicitante, v.fecha
    ORDER BY v.id_vale DESC
    LIMIT 500
  ", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Vales de salida</h1>
    <div class="d-flex gap-2">
      <a href="?r=vales.nuevo" class="btn btn-success btn-sm">Nuevo vale</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get" action="">
    <input type="hidden" name="r" value="vales.listar">
    <div class="col-md-4">
      <input name="q" type="search" class="form-control" placeholder="Buscar por responsable o solicitante..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
      <input name="f1" type="date" class="form-control" value="<?= e($f1) ?>" placeholder="Desde">
    </div>
    <div class="col-md-3">
      <input name="f2" type="date" class="form-control" value="<?= e($f2) ?>" placeholder="Hasta">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <table class="table table-striped table-hover table-sm">
    <thead>
      <tr>
        <th>ID</th>
        <th>Fecha</th>
        <th>Responsable</th>
        <th>Solicitante</th>
        <th class="text-end">Piezas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= $r['id_vale'] ?></td>
        <td><?= e($r['fecha']) ?></td>
        <td><?= e($r['responsable']) ?></td>
        <td><?= e($r['solicitante']) ?></td>
        <td class="text-end"><?= (int)$r['piezas'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="text-center text-muted">Sin registros</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php
  render('Vales de salida', ob_get_clean());
break;

/* =========================================================
 * NUEVO
 * =======================================================*/
case 'nuevo':
  // catálogo de artículos activos
  $arts = qall("SELECT id, articulo, marca, existencias FROM articulos WHERE eliminado=0 ORDER BY articulo ASC LIMIT 1000");
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo vale de salida</h1>
      <form method="post" action="?r=vales.guardar" id="valeForm">
        <div class="row g-2">
          <div class="col-md-4">
            <?= form_input('responsable','Responsable','', ['required'=>true]) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('solicitante','Solicitante','', ['required'=>true]) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?>
          </div>
        </div>

        <hr>

        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="itemsTbl">
            <thead class="table-light">
              <tr>
                <th style="width:65%">Artículo</th>
                <th style="width:20%">Cantidad</th>
                <th style="width:15%"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select name="item_id[]" class="form-select" required>
                    <option value="">-- Selecciona artículo --</option>
                    <?php foreach($arts as $a): ?>
                      <option value="<?= $a['id'] ?>">
                        <?= e($a['articulo'].' · '.$a['marca'].' (exist: '.$a['existencias'].')') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input name="cantidad[]" type="number" min="1" value="1" class="form-control" required></td>
                <td class="text-center">
                  <button type="button" class="btn btn-outline-danger btn-sm btnDel">Quitar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between">
          <button type="button" id="btnAdd" class="btn btn-outline-secondary">Agregar artículo</button>
          <div class="d-grid">
            <button class="btn btn-primary">Guardar vale</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      const tbl = document.getElementById('itemsTbl').getElementsByTagName('tbody')[0];
      document.getElementById('btnAdd').addEventListener('click', function(){
        const tr = tbl.rows[0].cloneNode(true);
        // reset
        tr.querySelector('select').selectedIndex = 0;
        tr.querySelector('input[type="number"]').value = 1;
        tbl.appendChild(tr);
      });
      tbl.addEventListener('click', function(e){
        if(e.target && e.target.classList.contains('btnDel')){
          if(tbl.rows.length === 1) return; // deja al menos una fila
          e.target.closest('tr').remove();
        }
      });
    })();
  </script>
  <?php
  render('Nuevo vale', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (transaccional)
 * =======================================================*/
case 'guardar':
  $resp = trim($_POST['responsable'] ?? '');
  $soli = trim($_POST['solicitante'] ?? '');
  $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
  $ids = $_POST['item_id'] ?? [];
  $cants = $_POST['cantidad'] ?? [];

  if ($resp==='' || $soli==='' || empty($ids) || empty($cants) || count($ids) != count($cants)) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Completa responsable, solicitante y al menos un artículo.</div>";
    redirect('vales.nuevo');
  }

  // Normaliza y agrupa cantidades por artículo (evita duplicados)
  $items = [];
  for ($i=0; $i<count($ids); $i++) {
    $id = (int)$ids[$i];
    $c = max(1, (int)$cants[$i]);
    if ($id <= 0) continue;
    if (!isset($items[$id])) $items[$id] = 0;
    $items[$id] += $c;
  }
  if (empty($items)) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Selecciona al menos un artículo válido.</div>";
    redirect('vales.nuevo');
  }

  // Valida existencias
  $faltantes = [];
  foreach ($items as $id => $cant) {
    $r = qone("SELECT existencias, articulo, marca FROM articulos WHERE id=? AND eliminado=0", [$id]);
    if (!$r) { $faltantes[] = "ID $id (no existe)"; continue; }
    if ((int)$r['existencias'] < $cant) {
      $faltantes[] = e($r['articulo'].' · '.$r['marca'])." (exist: ".$r['existencias'].", solicitado: $cant)";
    }
  }
  if (!empty($faltantes)) {
    $msg = "<ul class='mb-0'><li>".implode("</li><li>", $faltantes)."</li></ul>";
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>No hay existencias suficientes para:<br>$msg</div>";
    redirect('vales.nuevo');
  }

  // Transacción: cabezal + renglones + descuentos
  $pdo = db();
  try {
    $pdo->beginTransaction();

    q("INSERT INTO vales_salida (responsable, solicitante, fecha) VALUES (?, ?, ?)",
      [$resp, $soli, $fecha]);
    $id_vale = $pdo->lastInsertId();

    foreach ($items as $id_art => $cant) {
      q("INSERT INTO articulos_vale_salida (id, cantidad, id_vale, fecha) VALUES (?, ?, ?, ?)",
        [$id_art, $cant, $id_vale, $fecha]);
      // Descuenta existencias
      q("UPDATE articulos SET existencias = existencias - ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?",
        [$cant, $id_art]);
    }

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Vale #$id_vale creado y existencias actualizadas.</div>";
    redirect('vales.listar');

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!empty($GLOBALS['cfg']['debug'])) {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Error al guardar vale: ".e($e->getMessage())."</div>";
    } else {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo guardar el vale.</div>";
    }
    redirect('vales.nuevo');
  }
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('vales.listar');
}
