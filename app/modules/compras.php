<?php
// app/modules/compras.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + FILTRO BÁSICO (texto / fechas)
 * =======================================================*/
case 'listar':
  $q  = trim($_GET['q'] ?? '');      // busca por artículo o marca
  $f1 = trim($_GET['f1'] ?? '');     // desde (YYYY-MM-DD)
  $f2 = trim($_GET['f2'] ?? '');     // hasta (YYYY-MM-DD)

  $where = "1=1";
  $params = [];

  if ($q !== '') {
    $where .= " AND (articulo LIKE ? OR marca LIKE ?)";
    $like = '%'.$q.'%';
    $params[] = $like; $params[] = $like;
  }
  if ($f1 !== '') { $where .= " AND created_at >= ?"; $params[] = $f1; }
  if ($f2 !== '') { $where .= " AND created_at <= ?"; $params[] = $f2; }

  $rows = qall("
    SELECT id_compra, articulo, marca, cantidad, costo, DATE(created_at) AS fecha
    FROM compra_articulos
    WHERE $where
    ORDER BY id_compra DESC
    LIMIT 500
  ", $params);

  // totales simples
  $total_pzas = 0; $total_importe = 0.0;
  foreach ($rows as $r) { $total_pzas += (int)$r['cantidad']; $total_importe += (float)$r['costo']; }

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Compras (Entradas de artículos)</h1>
    <div class="d-flex gap-2">
      <a href="?r=compras.nuevo" class="btn btn-success btn-sm">Nueva compra</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get" action="">
    <input type="hidden" name="r" value="compras.listar">
    <div class="col-md-4">
      <input name="q" type="search" class="form-control" placeholder="Buscar por artículo o marca..." value="<?= e($q) ?>">
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

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Artículo</th>
          <th>Marca</th>
          <th class="text-end">Cantidad</th>
          <th class="text-end">Importe</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?= $r['id_compra'] ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td><?= e($r['articulo']) ?></td>
          <td><?= e($r['marca']) ?></td>
          <td class="text-end"><?= (int)$r['cantidad'] ?></td>
          <td class="text-end">$<?= number_format((float)$r['costo'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="4" class="text-end">Totales</th>
          <th class="text-end"><?= (int)$total_pzas ?></th>
          <th class="text-end">$<?= number_format($total_importe, 2) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php
  render('Compras', ob_get_clean());
break;

/* =========================================================
 * NUEVO (multirrenglón)
 * =======================================================*/
case 'nuevo':
  // catálogo de artículos activos
  // (usa baja lógica de articulos: eliminado=0)
  $arts = qall("SELECT id, articulo, marca, existencias FROM articulos WHERE eliminado=0 ORDER BY articulo ASC LIMIT 1000");
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nueva compra (entrada de artículos)</h1>
      <div class="alert alert-info small">
        Cada renglón se registra en <code>compra_articulos</code> y **suma** a las existencias del artículo seleccionado.
      </div>
      <form method="post" action="?r=compras.guardar" id="compraForm">
        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="itemsTbl">
            <thead class="table-light">
              <tr>
                <th style="width:55%">Artículo</th>
                <th style="width:15%">Cantidad</th>
                <th style="width:20%">Importe (línea)</th>
                <th style="width:10%"></th>
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
                <td><input name="costo[]" type="number" min="0" step="0.01" value="0.00" class="form-control" required></td>
                <td class="text-center">
                  <button type="button" class="btn btn-outline-danger btn-sm btnDel">Quitar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between">
          <div class="d-flex gap-2">
            <button type="button" id="btnAdd" class="btn btn-outline-secondary">Agregar renglón</button>
            <a class="btn btn-outline-primary" href="?r=articulos.nuevo" title="Crear artículo">Nuevo artículo</a>
          </div>
          <div class="d-grid">
            <button class="btn btn-primary">Guardar compra</button>
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
        tr.querySelector('select').selectedIndex = 0;
        tr.querySelector('input[name="cantidad[]"]').value = 1;
        tr.querySelector('input[name="costo[]"]').value = '0.00';
        tbl.appendChild(tr);
      });
      tbl.addEventListener('click', function(e){
        if(e.target && e.target.classList.contains('btnDel')){
          if(tbl.rows.length === 1) return; // al menos una fila
          e.target.closest('tr').remove();
        }
      });
    })();
  </script>
  <?php
  render('Nueva compra', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (transacción)
 * =======================================================*/
case 'guardar':
  $ids   = $_POST['item_id'] ?? [];
  $cants = $_POST['cantidad'] ?? [];
  $costs = $_POST['costo'] ?? [];

  if (empty($ids) || empty($cants) || empty($costs) ||
      !(count($ids)===count($cants) && count($ids)===count($costs))) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Agrega al menos un renglón válido.</div>";
    redirect('compras.nuevo');
  }

  // Normaliza y agrupa por artículo (por si repiten el mismo renglón)
  $items = []; // id_art => ['cantidad'=>X, 'costo'=>Y total]
  for ($i=0; $i<count($ids); $i++) {
    $id = (int)$ids[$i];
    $cant = max(1, (int)$cants[$i]);
    $costo = max(0, (float)$costs[$i]);
    if ($id <= 0) continue;
    if (!isset($items[$id])) $items[$id] = ['cantidad'=>0, 'costo'=>0.0];
    $items[$id]['cantidad'] += $cant;
    $items[$id]['costo']    += $costo; // costo total acumulado por artículo
  }
  if (empty($items)) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Selecciona artículos válidos.</div>";
    redirect('compras.nuevo');
  }

  // Verifica que existan los artículos (y no estén dados de baja)
  foreach ($items as $id => $info) {
    $r = qone("SELECT id, articulo, marca FROM articulos WHERE id=? AND eliminado=0", [$id]);
    if (!$r) {
      $_SESSION['_alerts'] = "<div class='alert alert-warning'>El artículo con ID $id no existe o está dado de baja.</div>";
      redirect('compras.nuevo');
    }
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();

    // Inserta cada renglón en compra_articulos y suma existencias
    foreach ($items as $id => $info) {
      $art = qone("SELECT articulo, marca FROM articulos WHERE id=?", [$id]); // ya validado
      q("INSERT INTO compra_articulos (articulo, marca, cantidad, costo, created_at)
         VALUES (?, ?, ?, ?, CURRENT_DATE())",
        [$art['articulo'], $art['marca'], (int)$info['cantidad'], (float)$info['costo']]);

      q("UPDATE articulos SET existencias = existencias + ?, updated_at = CURRENT_TIMESTAMP()
         WHERE id = ?", [(int)$info['cantidad'], $id]);
    }

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Compra guardada y existencias actualizadas.</div>";
    redirect('compras.listar');

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!empty($GLOBALS['cfg']['debug'])) {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Error al guardar compra: ".e($e->getMessage())."</div>";
    } else {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo guardar la compra.</div>";
    }
    redirect('compras.nuevo');
  }
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('compras.listar');
}
