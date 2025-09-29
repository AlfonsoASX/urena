<?php
// app/modules/articulos.php — Inventario de artículos con kardex
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'listar';

// Permisos: administradora controla catálogo, auxiliar movimientos.
$inventoryRoles = ['administradora','auxiliar'];
require_role($inventoryRoles);

function articulos_proveedores(): array {
  try {
    return qall("SELECT id_proveedor, nombre FROM proveedores WHERE COALESCE(eliminado,0)=0 ORDER BY nombre ASC");
  } catch (Exception $e) {
    return [];
  }
}

function articulos_categoria_distinct(): array {
  try {
    $rows = qall("SELECT DISTINCT categoria FROM articulos ORDER BY categoria ASC");
    $out = [];
    foreach ($rows as $r) {
      $cat = $r['categoria'] ?: 'general';
      $out[$cat] = ucfirst($cat);
    }
    if (empty($out)) {
      $out = ['general' => 'General'];
    }
    return $out;
  } catch (Exception $e) {
    return ['general' => 'General'];
  }
}

function articulos_find($id) {
  return qone("SELECT * FROM articulos WHERE id=? AND COALESCE(eliminado,0)=0", [$id]);
}

function articulos_servicio_exists($id_servicio): bool {
  if (!$id_servicio) return false;
  return (bool) qone("SELECT 1 FROM servicios WHERE id_servicio=? AND COALESCE(eliminado,0)=0", [(int)$id_servicio]);
}

function articulos_log_movimiento(PDO $pdo, int $id_articulo, string $tipo, int $cantidad, ?string $referencia, ?string $origen, ?string $destino, ?string $notas): void {
  $stmt = $pdo->prepare("INSERT INTO articulos_movimientos (id_articulo,tipo,cantidad,referencia,origen,destino,notas) VALUES (?,?,?,?,?,?,?)");
  $stmt->execute([$id_articulo,$tipo,$cantidad,$referencia,$origen,$destino,$notas]);
}

function articulos_actualizar_stock(PDO $pdo, int $id_articulo, int $delta): void {
  if ($delta === 0) return;
  $stmt = $pdo->prepare("SELECT existencias FROM articulos WHERE id=? FOR UPDATE");
  $stmt->execute([$id_articulo]);
  $current = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$current) {
    throw new RuntimeException('Artículo no encontrado.');
  }
  $nuevo = (int)$current['existencias'] + $delta;
  if ($nuevo < 0) {
    throw new RuntimeException('La salida generaría existencias negativas.');
  }
  $upd = $pdo->prepare("UPDATE articulos SET existencias=?, updated_at=CURRENT_TIMESTAMP() WHERE id=?");
  $upd->execute([$nuevo,$id_articulo]);
}

function articulos_log(string $tabla, array $payload): void {
  $user = current_user();
  $detalle = json_encode($payload, JSON_UNESCAPED_UNICODE);
  try {
    q("INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP())",
      [$tabla, $payload['accion'] ?? 'update', $user['usuario'] ?? 'system', $detalle]);
  } catch (Exception $e) {
    // silencioso si la tabla no existe
  }
}

$adminOnly = ['nuevo','guardar','editar','actualizar','baja'];
if (in_array($action, $adminOnly, true)) {
  require_role(['administradora']);
}

switch ($action) {
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $categoria = trim($_GET['categoria'] ?? '');
  $proveedor = (int)($_GET['id_proveedor'] ?? 0);
  $stock = trim($_GET['stock'] ?? ''); // bajo|agotado

  $where = ['a.eliminado=0'];
  $params = [];

  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(a.articulo LIKE ? OR a.marca LIKE ? OR a.id = ? )";
    array_push($params, $like,$like, ctype_digit($q)?(int)$q:-1);
  }
  if ($categoria !== '') {
    $where[] = "a.categoria = ?";
    $params[] = $categoria;
  }
  if ($proveedor > 0) {
    $where[] = "a.id_proveedor = ?";
    $params[] = $proveedor;
  }
  if ($stock === 'bajo') {
    $where[] = "a.existencias BETWEEN 1 AND 5";
  } elseif ($stock === 'agotado') {
    $where[] = "a.existencias <= 0";
  }

  $sql = "SELECT a.id, a.articulo, a.marca, a.existencias, a.categoria, a.updated_at,
                 p.nombre AS proveedor
          FROM articulos a
          LEFT JOIN proveedores p ON p.id_proveedor=a.id_proveedor
          WHERE ".implode(' AND ', $where)."
          ORDER BY a.id DESC
          LIMIT 300";
  $rows = qall($sql, $params);

  $categorias = ['' => 'Todas'] + articulos_categoria_distinct();
  $proveedores = articulos_proveedores();
  $proveedor_opts = ['0' => 'Todos'];
  foreach ($proveedores as $prov) {
    $proveedor_opts[(string)$prov['id_proveedor']] = $prov['nombre'];
  }

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Artículos</h1>
    <?php if (user_has_role('administradora')): ?>
      <div class="d-flex gap-2">
        <a href="?r=articulos.nuevo" class="btn btn-success btn-sm">Nuevo artículo</a>
      </div>
    <?php endif; ?>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get">
    <input type="hidden" name="r" value="articulos.listar">
    <div class="col-md-4">
      <?= form_input('q','Buscar', $q, ['placeholder'=>'ID, nombre o marca']) ?>
    </div>
    <div class="col-md-3">
      <?= form_select('categoria','Categoría',$categorias,$categoria,['class'=>'form-select']) ?>
    </div>
    <div class="col-md-3">
      <?= form_select('id_proveedor','Proveedor',$proveedor_opts,(string)$proveedor,['class'=>'form-select']) ?>
    </div>
    <div class="col-md-2">
      <?= form_select('stock','Stock',[''=>'Todos','bajo'=>'Bajo (&le;5)','agotado'=>'Agotado'],$stock,['class'=>'form-select']) ?>
    </div>
    <div class="col-12 col-md-2 d-grid">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Artículo</th>
          <th>Marca</th>
          <th>Categoría</th>
          <th>Proveedor</th>
          <th class="text-end">Existencias</th>
          <th>Actualizado</th>
          <th style="width:220px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td>#<?= (int)$r['id'] ?></td>
          <td><?= e($r['articulo']) ?></td>
          <td><?= e($r['marca']) ?></td>
          <td><span class="badge bg-secondary text-uppercase"><?= e($r['categoria']) ?></span></td>
          <td><?= e($r['proveedor'] ?? '—') ?></td>
          <td class="text-end <?= $r['existencias']<=0?'text-danger fw-bold':($r['existencias']<=5?'text-warning fw-semibold':'') ?>">
            <?= (int)$r['existencias'] ?>
          </td>
          <td><?= e(date('Y-m-d', strtotime($r['updated_at'] ?? date('Y-m-d')))) ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="?r=articulos.historial&id=<?= (int)$r['id'] ?>">Kardex</a>
            <a class="btn btn-outline-success btn-sm" href="?r=articulos.ajustar&id=<?= (int)$r['id'] ?>&tipo=entrada">Entrada</a>
            <a class="btn btn-outline-warning btn-sm" href="?r=articulos.ajustar&id=<?= (int)$r['id'] ?>&tipo=salida">Salida</a>
            <a class="btn btn-outline-info btn-sm" href="?r=articulos.ajustar&id=<?= (int)$r['id'] ?>&tipo=traspaso">Traspaso</a>
            <?php if (user_has_role('administradora')): ?>
              <a class="btn btn-outline-primary btn-sm" href="?r=articulos.editar&id=<?= (int)$r['id'] ?>">Editar</a>
              <a class="btn btn-outline-danger btn-sm" href="?r=articulos.baja&id=<?= (int)$r['id'] ?>" onclick="return confirm('¿Dar de baja lógica este artículo?');">Baja</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Artículos', ob_get_clean());
  break;

case 'nuevo':
  $proveedores = articulos_proveedores();
  $opts = ['' => 'Selecciona proveedor'];
  foreach ($proveedores as $prov) {
    $opts[$prov['id_proveedor']] = $prov['nombre'];
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo artículo</h1>
      <form method="post" action="?r=articulos.guardar">
        <?= csrf_field() ?>
        <?= form_input('articulo','Artículo','',['required'=>true,'maxlength'=>120]) ?>
        <?= form_input('marca','Marca') ?>
        <?= form_select('categoria','Categoría',['general'=>'General','urna'=>'Urna','insumo'=>'Insumo','decoracion'=>'Decoración'],'general',['class'=>'form-select']) ?>
        <?= form_select('id_proveedor','Proveedor',$opts,'',['class'=>'form-select','placeholder'=>'Sin proveedor']) ?>
        <?= form_input('existencias','Existencias iniciales',0,['type'=>'number','min'=>0,'required'=>true]) ?>
        <?= form_input('foto','Foto (ruta opcional)','') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Guardar</button>
          <a class="btn btn-light" href="?r=articulos.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo artículo', ob_get_clean());
  break;

case 'guardar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('articulos.nuevo'); }

  $articulo = trim($_POST['articulo'] ?? '');
  $marca = trim($_POST['marca'] ?? '');
  $categoria = trim($_POST['categoria'] ?? 'general');
  $existencias = max(0, (int)($_POST['existencias'] ?? 0));
  $foto = trim($_POST['foto'] ?? '') ?: null;
  $id_proveedor = (int)($_POST['id_proveedor'] ?? 0) ?: null;

  if ($articulo === '') {
    flash("<div class='alert alert-danger'>El campo artículo es obligatorio.</div>");
    redirect('articulos.nuevo');
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("INSERT INTO articulos (articulo, marca, categoria, existencias, foto, id_proveedor, updated_at, eliminado)
       VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(), 0)",
      [$articulo,$marca,$categoria,$existencias,$foto,$id_proveedor]);
    $id = (int)$pdo->lastInsertId();
    if ($existencias > 0) {
      articulos_log_movimiento($pdo, $id, 'entrada', $existencias, 'alta_inicial', null, null, 'Alta de artículo');
    }
    articulos_log('articulos', ['accion'=>'alta','id'=>$id,'articulo'=>$articulo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Artículo registrado.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo registrar el artículo.</div>");
  }
  redirect('articulos.listar');
  break;

case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $art = articulos_find($id);
  if (!$art) {
    flash("<div class='alert alert-warning'>Artículo no encontrado.</div>");
    redirect('articulos.listar');
  }
  $proveedores = articulos_proveedores();
  $opts = ['' => 'Sin proveedor'];
  foreach ($proveedores as $prov) {
    $opts[$prov['id_proveedor']] = $prov['nombre'];
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar artículo #<?= (int)$art['id'] ?></h1>
      <form method="post" action="?r=articulos.actualizar&id=<?= (int)$art['id'] ?>">
        <?= csrf_field() ?>
        <?= form_input('articulo','Artículo',$art['articulo'],['required'=>true]) ?>
        <?= form_input('marca','Marca',$art['marca']) ?>
        <?= form_select('categoria','Categoría',['general'=>'General','urna'=>'Urna','insumo'=>'Insumo','decoracion'=>'Decoración'],$art['categoria'],['class'=>'form-select']) ?>
        <?= form_select('id_proveedor','Proveedor',$opts,(string)($art['id_proveedor'] ?? ''),['class'=>'form-select']) ?>
        <?= form_input('existencias','Existencias',$art['existencias'],['type'=>'number','min'=>0,'required'=>true]) ?>
        <?= form_input('foto','Foto (ruta opcional)',$art['foto'] ?? '') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Actualizar</button>
          <a class="btn btn-light" href="?r=articulos.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Editar artículo', ob_get_clean());
  break;

case 'actualizar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('articulos.listar'); }
  $id = (int)($_GET['id'] ?? 0);
  $art = articulos_find($id);
  if (!$art) {
    flash("<div class='alert alert-warning'>Artículo no encontrado.</div>");
    redirect('articulos.listar');
  }
  $articulo = trim($_POST['articulo'] ?? '');
  $marca = trim($_POST['marca'] ?? '');
  $categoria = trim($_POST['categoria'] ?? 'general');
  $existencias = max(0, (int)($_POST['existencias'] ?? 0));
  $foto = trim($_POST['foto'] ?? '') ?: null;
  $id_proveedor = (int)($_POST['id_proveedor'] ?? 0) ?: null;
  if ($articulo === '') {
    flash("<div class='alert alert-danger'>El campo artículo es obligatorio.</div>");
    redirect('articulos.editar&id='.$id);
  }
  q("UPDATE articulos
     SET articulo=?, marca=?, categoria=?, existencias=?, foto=?, id_proveedor=?, updated_at=CURRENT_TIMESTAMP()
     WHERE id=?",
    [$articulo,$marca,$categoria,$existencias,$foto,$id_proveedor,$id]);
  articulos_log('articulos',['accion'=>'actualizar','id'=>$id]);
  flash("<div class='alert alert-success'>Artículo actualizado.</div>");
  redirect('articulos.listar');
  break;

case 'baja':
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) redirect('articulos.listar');
  q("UPDATE articulos SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE id=?", [$id]);
  articulos_log('articulos',['accion'=>'baja','id'=>$id]);
  flash("<div class='alert alert-success'>Artículo dado de baja.</div>");
  redirect('articulos.listar');
  break;

case 'ajustar':
  $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
  $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'entrada';
  $tipo = in_array($tipo,['entrada','salida','traspaso'],true) ? $tipo : 'entrada';
  $art = articulos_find($id);
  if (!$art) {
    flash("<div class='alert alert-warning'>Artículo no encontrado.</div>");
    redirect('articulos.listar');
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>");
      redirect('articulos.ajustar&id='.$id.'&tipo='.$tipo);
    }
    $cantidad = max(0, (int)($_POST['cantidad'] ?? 0));
    $notas = trim($_POST['notas'] ?? '');
    if ($cantidad <= 0) {
      flash("<div class='alert alert-warning'>La cantidad debe ser mayor a cero.</div>");
      redirect('articulos.ajustar&id='.$id.'&tipo='.$tipo);
    }
    $pdo = db();
    try {
      $pdo->beginTransaction();
      if ($tipo === 'entrada') {
        $referencia = trim($_POST['referencia'] ?? '');
        $destino = trim($_POST['destino'] ?? 'almacen');
        articulos_actualizar_stock($pdo, $id, $cantidad);
        articulos_log_movimiento($pdo, $id, 'entrada', $cantidad, $referencia ?: null, null, $destino ?: null, $notas ?: null);
        articulos_log('articulos_movimientos',['accion'=>'entrada','id_articulo'=>$id,'cantidad'=>$cantidad]);
        $pdo->commit();
        flash("<div class='alert alert-success'>Entrada registrada.</div>");
      } elseif ($tipo === 'salida') {
        $id_servicio = (int)($_POST['id_servicio'] ?? 0);
        if (!articulos_servicio_exists($id_servicio)) {
          throw new RuntimeException('Servicio no encontrado.');
        }
        if ($cantidad > $art['existencias']) {
          throw new RuntimeException('No hay existencias suficientes.');
        }
        $responsable = trim($_POST['responsable'] ?? '');
        q("INSERT INTO articulos_salida_servicio (id_servicio,id_articulo,cantidad,responsable) VALUES (?,?,?,?)",
          [$id_servicio,$id,$cantidad,$responsable ?: (current_user()['usuario'] ?? 'sistema')]);
        articulos_actualizar_stock($pdo, $id, -$cantidad);
        articulos_log_movimiento($pdo, $id, 'salida', $cantidad, 'servicio:'.$id_servicio, null, null, $notas ?: null);
        articulos_log('articulos_movimientos',['accion'=>'salida','id_articulo'=>$id,'cantidad'=>$cantidad,'servicio'=>$id_servicio]);
        $pdo->commit();
        flash("<div class='alert alert-success'>Salida a servicio registrada.</div>");
      } else { // traspaso
        $origen = trim($_POST['origen'] ?? '');
        $destino = trim($_POST['destino'] ?? '');
        if ($origen === '' || $destino === '') {
          throw new RuntimeException('Debes indicar origen y destino.');
        }
        $responsable = trim($_POST['responsable'] ?? (current_user()['usuario'] ?? 'sistema'));
        q("INSERT INTO articulos_traspasos (origen,destino,responsable) VALUES (?,?,?)", [$origen,$destino,$responsable]);
        $traspasoId = (int)$pdo->lastInsertId();
        q("INSERT INTO articulos_traspaso_det (id_traspaso,id_articulo,cantidad) VALUES (?,?,?)", [$traspasoId,$id,$cantidad]);
        articulos_log_movimiento($pdo, $id, 'traspaso_out', $cantidad, 'traspaso:'.$traspasoId, $origen, $destino, $notas ?: null);
        articulos_log_movimiento($pdo, $id, 'traspaso_in', $cantidad, 'traspaso:'.$traspasoId, $origen, $destino, $notas ?: null);
        articulos_log('articulos_movimientos',['accion'=>'traspaso','id_articulo'=>$id,'id_traspaso'=>$traspasoId]);
        $pdo->commit();
        flash("<div class='alert alert-success'>Traspaso registrado.</div>");
      }
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
    }
    redirect('articulos.listar');
  }

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Movimiento de artículo · <?= e($art['articulo']) ?></h1>
      <form method="post" action="?r=articulos.ajustar">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
        <div class="alert alert-light">Existencias actuales: <strong><?= (int)$art['existencias'] ?></strong></div>
        <?= form_input('cantidad','Cantidad',1,['type'=>'number','min'=>1,'required'=>true]) ?>
        <?php if ($tipo==='entrada'): ?>
          <?= form_input('referencia','Referencia (folio, factura)','') ?>
          <?= form_input('destino','Destino / Almacén','principal') ?>
        <?php elseif ($tipo==='salida'): ?>
          <?= form_input('id_servicio','Servicio destino','',['type'=>'number','min'=>1,'required'=>true,'help'=>'Folio de servicio al que se asigna.']) ?>
          <?= form_input('responsable','Responsable','', ['placeholder'=>'Nombre de quien retira']) ?>
        <?php else: ?>
          <?= form_input('origen','Origen','', ['required'=>true]) ?>
          <?= form_input('destino','Destino','', ['required'=>true]) ?>
          <?= form_input('responsable','Responsable', current_user()['usuario'] ?? '', ['required'=>true]) ?>
        <?php endif; ?>
        <?= form_input('notas','Notas','', ['placeholder'=>'Opcional']) ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Registrar</button>
          <a href="?r=articulos.listar" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Movimiento de artículo', ob_get_clean());
  break;

case 'historial':
  $id = (int)($_GET['id'] ?? 0);
  $art = articulos_find($id);
  if (!$art) {
    flash("<div class='alert alert-warning'>Artículo no encontrado.</div>");
    redirect('articulos.listar');
  }
  $movs = qall("SELECT tipo, cantidad, referencia, origen, destino, notas, created_at
                FROM articulos_movimientos
                WHERE id_articulo=?
                ORDER BY id_mov DESC
                LIMIT 200", [$id]);
  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h5 m-0">Kardex · <?= e($art['articulo']) ?> (#<?= (int)$art['id'] ?>)</h1>
    <a class="btn btn-light btn-sm" href="?r=articulos.listar">Volver</a>
  </div>
  <div class="mb-3">
    <span class="badge bg-secondary">Categoría: <?= e($art['categoria']) ?></span>
    <span class="badge bg-info text-dark">Existencias: <?= (int)$art['existencias'] ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th class="text-end">Cantidad</th>
          <th>Referencia</th>
          <th>Origen</th>
          <th>Destino</th>
          <th>Notas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?></td>
          <td><span class="badge bg-<?= $m['tipo']==='entrada'?'success':($m['tipo']==='salida'?'danger':'info') ?>"><?= e($m['tipo']) ?></span></td>
          <td class="text-end"><?= (int)$m['cantidad'] ?></td>
          <td><?= e($m['referencia'] ?? '—') ?></td>
          <td><?= e($m['origen'] ?? '—') ?></td>
          <td><?= e($m['destino'] ?? '—') ?></td>
          <td><?= e($m['notas'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movs)): ?>
        <tr><td colspan="7" class="text-center text-muted">Sin movimientos</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Kardex de artículo', ob_get_clean());
  break;

default:
  redirect('articulos.listar');
}
