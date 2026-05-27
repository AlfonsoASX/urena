<?php
// app/modules/almacenes.php - Catalogo de almacenes
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/almacenes.php';

require_login();
$almacenesRoles = ['administradora','auxiliar'];
require_role($almacenesRoles);

$action = $action ?? 'listar';
almacenes_ensure_schema();

function almacenes_find(int $id): ?array {
  return qone("SELECT * FROM almacenes WHERE id_almacen=? AND COALESCE(eliminado,0)=0", [$id]);
}

function almacenes_log(string $accion, array $payload = []): void {
  $user = current_user();
  $detalle = json_encode(array_merge(['accion' => $accion], $payload), JSON_UNESCAPED_UNICODE);
  try {
    q(
      "INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES ('almacenes', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$accion, $user['usuario'] ?? 'sistema', $detalle]
    );
  } catch (Throwable $e) {
  }
}

$adminOnly = ['nuevo','guardar','editar','actualizar','baja','activar'];
if (in_array($action, $adminOnly, true)) {
  require_role($almacenesRoles);
}

switch ($action) {
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $estado = trim($_GET['estado'] ?? 'activos');
  $where = ['COALESCE(eliminado,0)=0'];
  $params = [];
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(nombre LIKE ? OR codigo LIKE ? OR descripcion LIKE ?)";
    array_push($params, $like, $like, $like);
  }
  if ($estado === 'activos') {
    $where[] = 'COALESCE(activo,1)=1';
  } elseif ($estado === 'inactivos') {
    $where[] = 'COALESCE(activo,1)=0';
  }
  $rows = qall(
    "SELECT id_almacen, codigo, nombre, descripcion, activo, created_at, updated_at
     FROM almacenes
     WHERE ".implode(' AND ', $where)."
     ORDER BY activo DESC, nombre ASC
     LIMIT 300",
    $params
  );

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Almacenes</h1>
      <div class="text-muted small">Catalogo central para articulos, ataudes y equipos.</div>
    </div>
    <?php if (user_has_role($almacenesRoles)): ?>
      <a href="?r=almacenes.nuevo" class="btn btn-success btn-sm">Nuevo almacen</a>
    <?php endif; ?>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get">
    <input type="hidden" name="r" value="almacenes.listar">
    <div class="col-md-6"><?= form_input('q','Buscar',$q,['placeholder'=>'Nombre, codigo o descripcion']) ?></div>
    <div class="col-md-3"><?= form_select('estado','Estatus',['activos'=>'Activos','inactivos'=>'Inactivos','todos'=>'Todos'],$estado,['class'=>'form-select']) ?></div>
    <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead><tr><th>ID</th><th>Codigo</th><th>Nombre</th><th>Descripcion</th><th>Estatus</th><th>Actualizado</th><th style="width:220px">Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>#<?= (int)$row['id_almacen'] ?></td>
          <td><code><?= e($row['codigo'] ?: '—') ?></code></td>
          <td><?= e($row['nombre']) ?></td>
          <td><?= e($row['descripcion'] ?: '—') ?></td>
          <td><span class="badge text-bg-<?= !empty($row['activo']) ? 'success' : 'secondary' ?>"><?= !empty($row['activo']) ? 'Activo' : 'Inactivo' ?></span></td>
          <td><?= e(!empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : '—') ?></td>
          <td class="d-flex flex-wrap gap-2">
            <?php if (user_has_role($almacenesRoles)): ?>
              <a class="btn btn-outline-primary btn-sm" href="?r=almacenes.editar&id=<?= (int)$row['id_almacen'] ?>">Editar</a>
              <?php if (!empty($row['activo'])): ?>
                <a class="btn btn-outline-warning btn-sm" href="?r=almacenes.baja&id=<?= (int)$row['id_almacen'] ?>" onclick="return confirm('¿Inactivar este almacen?');">Inactivar</a>
              <?php else: ?>
                <a class="btn btn-outline-success btn-sm" href="?r=almacenes.activar&id=<?= (int)$row['id_almacen'] ?>">Activar</a>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted">Sin almacenes registrados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Almacenes', ob_get_clean());
  break;

case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo almacen</h1>
      <form method="post" action="?r=almacenes.guardar">
        <?= csrf_field() ?>
        <?= form_input('nombre','Nombre','',['required'=>true,'maxlength'=>120]) ?>
        <?= form_input('codigo','Codigo','',['maxlength'=>40,'placeholder'=>'Opcional']) ?>
        <div class="mb-2">
          <label class="form-label" for="descripcion">Descripcion</label>
          <textarea class="form-control" name="descripcion" id="descripcion" rows="3"></textarea>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Guardar</button>
          <a href="?r=almacenes.listar" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo almacen', ob_get_clean());
  break;

case 'guardar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('almacenes.nuevo'); }
  $nombre = trim($_POST['nombre'] ?? '');
  $codigo = trim($_POST['codigo'] ?? '') ?: null;
  $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
  if ($nombre === '') {
    flash("<div class='alert alert-warning'>El nombre del almacen es obligatorio.</div>");
    redirect('almacenes.nuevo');
  }
  try {
    q(
      "INSERT INTO almacenes (codigo, nombre, descripcion, activo, eliminado, created_at, updated_at)
       VALUES (?, ?, ?, 1, 0, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())",
      [$codigo, $nombre, $descripcion]
    );
    almacenes_log('alta', ['nombre' => $nombre, 'codigo' => $codigo]);
    flash("<div class='alert alert-success'>Almacen registrado.</div>");
  } catch (Throwable $e) {
    flash("<div class='alert alert-danger'>No se pudo registrar el almacen. Revisa que el codigo no este duplicado.</div>");
  }
  redirect('almacenes.listar');
  break;

case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $row = almacenes_find($id);
  if (!$row) {
    flash("<div class='alert alert-warning'>Almacen no encontrado.</div>");
    redirect('almacenes.listar');
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar almacen #<?= (int)$row['id_almacen'] ?></h1>
      <form method="post" action="?r=almacenes.actualizar&id=<?= (int)$row['id_almacen'] ?>">
        <?= csrf_field() ?>
        <?= form_input('nombre','Nombre',$row['nombre'],['required'=>true,'maxlength'=>120]) ?>
        <?= form_input('codigo','Codigo',$row['codigo'] ?? '',['maxlength'=>40,'placeholder'=>'Opcional']) ?>
        <div class="mb-2">
          <label class="form-label" for="descripcion">Descripcion</label>
          <textarea class="form-control" name="descripcion" id="descripcion" rows="3"><?= e($row['descripcion'] ?? '') ?></textarea>
        </div>
        <?= form_select('activo','Estatus',['1'=>'Activo','0'=>'Inactivo'],(string)$row['activo'],['class'=>'form-select']) ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Actualizar</button>
          <a href="?r=almacenes.listar" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Editar almacen', ob_get_clean());
  break;

case 'actualizar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('almacenes.listar'); }
  $id = (int)($_GET['id'] ?? 0);
  $row = almacenes_find($id);
  if (!$row) {
    flash("<div class='alert alert-warning'>Almacen no encontrado.</div>");
    redirect('almacenes.listar');
  }
  $nombre = trim($_POST['nombre'] ?? '');
  $codigo = trim($_POST['codigo'] ?? '') ?: null;
  $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
  $activo = (int)($_POST['activo'] ?? 1) ? 1 : 0;
  if ($nombre === '') {
    flash("<div class='alert alert-warning'>El nombre del almacen es obligatorio.</div>");
    redirect('almacenes.editar&id='.$id);
  }
  try {
    q(
      "UPDATE almacenes
       SET codigo=?, nombre=?, descripcion=?, activo=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_almacen=?",
      [$codigo, $nombre, $descripcion, $activo, $id]
    );
    almacenes_log('actualizar', ['id_almacen' => $id]);
    flash("<div class='alert alert-success'>Almacen actualizado.</div>");
  } catch (Throwable $e) {
    flash("<div class='alert alert-danger'>No se pudo actualizar el almacen. Revisa que el codigo no este duplicado.</div>");
  }
  redirect('almacenes.listar');
  break;

case 'baja':
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) redirect('almacenes.listar');
  q("UPDATE almacenes SET activo=0, updated_at=CURRENT_TIMESTAMP() WHERE id_almacen=? AND COALESCE(eliminado,0)=0", [$id]);
  almacenes_log('inactivar', ['id_almacen' => $id]);
  flash("<div class='alert alert-success'>Almacen inactivado.</div>");
  redirect('almacenes.listar');
  break;

case 'activar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) redirect('almacenes.listar');
  q("UPDATE almacenes SET activo=1, updated_at=CURRENT_TIMESTAMP() WHERE id_almacen=? AND COALESCE(eliminado,0)=0", [$id]);
  almacenes_log('activar', ['id_almacen' => $id]);
  flash("<div class='alert alert-success'>Almacen activado.</div>");
  redirect('almacenes.listar');
  break;

default:
  redirect('almacenes.listar');
}
