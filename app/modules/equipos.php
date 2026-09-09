<?php
// app/modules/equipos.php — Inventario operativo con kardex y estados normalizados
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/inventario_v2.php';
require_once __DIR__.'/../core/almacenes.php';

require_login();
almacenes_ensure_schema();

$action = $action ?? 'listar';
$roles = ['administradora', 'auxiliar'];
$equiposViewPermissions = ['equipos.ver','equipos.admin'];
$equiposAdminPermission = 'equipos.admin';
require_permission_or_legacy_role($equiposViewPermissions, $roles);
$canAdminEquipos = user_has_permission_or_legacy_role($equiposAdminPermission, ['administradora']);
$canMoveEquipos = user_has_permission_or_legacy_role($equiposAdminPermission, $roles);

function equipos_find($id_equipo) {
  return qone("SELECT * FROM equipos WHERE id_equipo=? AND COALESCE(eliminado,0)=0", [$id_equipo]);
}

function equipos_movimientos(string $id_equipo): array {
  $movs = [];
  if (db_table_exists('equipos_movimientos')) {
    try {
      foreach (qall("SELECT tipo, id_servicio, origen, destino, notas, created_at FROM equipos_movimientos WHERE id_equipo=? ORDER BY id_mov DESC", [$id_equipo]) as $row) {
        $movs[] = [
          'tipo' => $row['tipo'],
          'id_servicio' => $row['id_servicio'],
          'origen' => $row['origen'],
          'destino' => $row['destino'],
          'notas' => $row['notas'],
          'created_at' => $row['created_at'],
          'fuente' => 'inventario',
        ];
      }
    } catch (Throwable $e) {
    }
  }
  if (db_table_exists('servicio_equipo_evento')) {
    try {
      foreach (qall("SELECT tipo_evento, id_servicio, observaciones, created_at FROM servicio_equipo_evento WHERE id_equipo=? ORDER BY id DESC", [$id_equipo]) as $row) {
        $movs[] = [
          'tipo' => $row['tipo_evento'],
          'id_servicio' => $row['id_servicio'],
          'origen' => null,
          'destino' => null,
          'notas' => $row['observaciones'],
          'created_at' => $row['created_at'],
          'fuente' => 'servicio',
        ];
      }
    } catch (Throwable $e) {
    }
  }
  usort($movs, static function (array $a, array $b): int {
    return strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? ''));
  });
  return $movs;
}

function equipos_log(string $accion, array $payload = []): void {
  $user = current_user();
  $detalle = json_encode(array_merge(['accion' => $accion], $payload), JSON_UNESCAPED_UNICODE);
  try {
    q(
      "INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES ('equipos', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$accion, $user['usuario'] ?? 'sistema', $detalle]
    );
  } catch (Exception $e) {
  }
}

function equipos_registrar_mov(PDO $pdo, string $id_equipo, string $tipo, ?int $id_servicio = null, ?string $origen = null, ?string $destino = null, ?string $notas = null, ?int $idAlmacenOrigen = null, ?int $idAlmacenDestino = null): void {
  $columns = ['id_equipo','tipo','id_servicio','origen','destino','notas'];
  $values = [$id_equipo, $tipo, $id_servicio, $origen, $destino, $notas];
  if (db_column_exists('equipos_movimientos', 'id_almacen_origen')) {
    $columns[] = 'id_almacen_origen';
    $values[] = $idAlmacenOrigen;
  }
  if (db_column_exists('equipos_movimientos', 'id_almacen_destino')) {
    $columns[] = 'id_almacen_destino';
    $values[] = $idAlmacenDestino;
  }
  $placeholders = implode(',', array_fill(0, count($columns), '?'));
  $stmt = $pdo->prepare("INSERT INTO equipos_movimientos (".implode(',', $columns).") VALUES ({$placeholders})");
  $stmt->execute($values);
}

function servicio_exists($id_servicio): bool {
  if ($id_servicio <= 0) return false;
  return (bool)qone("SELECT 1 FROM servicios WHERE id_servicio=? AND COALESCE(eliminado,0)=0", [$id_servicio]);
}

function equipo_estado_normalizado(?string $estatus): string {
  $estatus = trim((string)$estatus);
  if ($estatus === '' || $estatus === 'disponible') return 'disponible';
  if (in_array($estatus, ['ocupado', 'asignado'], true)) return 'ocupado';
  if ($estatus === 'mantenimiento') return 'mantenimiento';
  if ($estatus === 'baja') return 'baja';
  return $estatus;
}

function equipo_estado_ocupado(?string $estatus): bool {
  return equipo_estado_normalizado($estatus) === 'ocupado';
}

function equipo_estado_label(?string $estatus): string {
  return match (equipo_estado_normalizado($estatus)) {
    'disponible' => 'Disponible',
    'ocupado' => 'Ocupado',
    'mantenimiento' => 'Mantenimiento',
    'baja' => 'Baja',
    default => ucwords(str_replace('_', ' ', trim((string)$estatus))),
  };
}

function equipo_estado_classes(?string $estatus): string {
  return match (equipo_estado_normalizado($estatus)) {
    'disponible' => 'success',
    'ocupado' => 'warning text-dark',
    'mantenimiento' => 'info text-dark',
    'baja' => 'secondary',
    default => 'secondary',
  };
}

function equipo_movimiento_label(string $tipo): string {
  return ucwords(str_replace('_', ' ', trim($tipo)));
}

$catalogAdminOnly = ['nuevo', 'guardar', 'editar', 'actualizar', 'baja'];
if (in_array($action, $catalogAdminOnly, true)) {
  require_permission_or_legacy_role($equiposAdminPermission, ['administradora']);
}
$movementOnly = ['asignar', 'guardar_asignacion', 'retornar', 'guardar_retorno'];
if (in_array($action, $movementOnly, true)) {
  require_permission_or_legacy_role($equiposAdminPermission, $roles);
}

switch ($action) {
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $estatus = trim($_GET['est'] ?? 'todos');
  $almacen = (int)($_GET['id_almacen'] ?? 0);
  $where = ['COALESCE(equipos.eliminado,0)=0'];
  $params = [];
  if ($estatus !== '' && $estatus !== 'todos') {
    if ($estatus === 'ocupado') {
      $where[] = "COALESCE(equipos.estatus,'disponible') IN ('ocupado','asignado')";
    } else {
      $where[] = "COALESCE(equipos.estatus,'disponible') = ?";
      $params[] = $estatus;
    }
  }
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(equipos.id_equipo LIKE ? OR equipos.equipo LIKE ? OR equipos.ubicacion LIKE ? OR al.nombre LIKE ?)";
    array_push($params, $like, $like, $like, $like);
  }
  if ($almacen > 0) {
    $where[] = "equipos.id_almacen = ?";
    $params[] = $almacen;
  }
  $rows = qall(
    "SELECT equipos.id_equipo, equipos.equipo,
            COALESCE(equipos.estatus,'disponible') AS estatus_raw,
            CASE
              WHEN COALESCE(equipos.estatus,'disponible') IN ('ocupado','asignado') THEN 'ocupado'
              ELSE COALESCE(equipos.estatus,'disponible')
            END AS estatus_ui,
            equipos.id_almacen,
            al.nombre AS almacen_nombre,
            equipos.ubicacion,
            DATE(equipos.updated_at) AS actualizado
     FROM equipos
     LEFT JOIN almacenes al ON al.id_almacen = equipos.id_almacen
     WHERE ".implode(' AND ', $where)."
     ORDER BY equipo ASC
     LIMIT 300",
    $params
  );
  $disponibles = 0;
  $ocupados = 0;
  $mantenimiento = 0;
  foreach ($rows as $row) {
    $estadoUi = equipo_estado_normalizado($row['estatus_ui'] ?? $row['estatus_raw'] ?? '');
    if ($estadoUi === 'disponible') $disponibles++;
    if ($estadoUi === 'ocupado') $ocupados++;
    if ($estadoUi === 'mantenimiento') $mantenimiento++;
  }
  $almacen_opts = ['0' => 'Todos'] + array_diff_key(almacenes_options(null, 'Todos'), ['' => true]);
  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Equipos operativos</h1>
      <div class="text-muted small">Listado con kardex completo y estado operativo normalizado.</div>
    </div>
    <?php if ($canAdminEquipos): ?>
      <a href="?r=equipos.nuevo" class="btn btn-success btn-sm">Nuevo equipo</a>
    <?php endif; ?>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge text-bg-success"><?= $disponibles ?> disponibles</span>
    <span class="badge text-bg-warning text-dark"><?= $ocupados ?> ocupados</span>
    <span class="badge text-bg-info text-dark"><?= $mantenimiento ?> en mantenimiento</span>
    <span class="badge text-bg-light border"><?= count($rows) ?> visibles</span>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="equipos.listar">
    <div class="col-md-5"><?= form_input('q', 'Buscar', $q, ['placeholder' => 'Código o nombre']) ?></div>
    <div class="col-md-4"><?= form_select('est', 'Estatus', ['todos' => 'Todos', 'disponible' => 'Disponible', 'ocupado' => 'Ocupado', 'mantenimiento' => 'Mantenimiento', 'baja' => 'Baja'], $estatus, ['class' => 'form-select']) ?></div>
    <div class="col-md-3"><?= form_select('id_almacen', 'Almacén', $almacen_opts, (string)$almacen, ['class' => 'form-select']) ?></div>
    <div class="col-md-3 d-grid">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>Código</th>
          <th>Equipo</th>
          <th>Estatus</th>
          <th>Almacén</th>
          <th>Actualizado</th>
          <th style="width:250px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['id_equipo']) ?></code></td>
          <td><?= e($r['equipo']) ?></td>
          <td><span class="badge bg-<?= equipo_estado_classes($r['estatus_ui'] ?? $r['estatus_raw'] ?? '') ?>"><?= e(equipo_estado_label($r['estatus_ui'] ?? $r['estatus_raw'] ?? '')) ?></span></td>
          <td><?= e(($r['almacen_nombre'] ?? $r['ubicacion'] ?? '') ?: '—') ?></td>
          <td><?= e($r['actualizado'] ?: '—') ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="?r=equipos.historial&id_equipo=<?= urlencode($r['id_equipo']) ?>">Kardex</a>
            <?php if ($canAdminEquipos): ?>
              <a class="btn btn-outline-primary btn-sm" href="?r=equipos.editar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Editar</a>
              <a class="btn btn-outline-danger btn-sm" href="?r=equipos.baja&id_equipo=<?= urlencode($r['id_equipo']) ?>" onclick="return confirm('¿Dar de baja lógica este equipo?');">Baja</a>
            <?php endif; ?>
            <?php if ($canMoveEquipos): ?>
              <a class="btn btn-outline-success btn-sm" href="?r=equipos.asignar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Asignar</a>
              <a class="btn btn-outline-info btn-sm" href="?r=equipos.retornar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Retornar</a>
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
  render('Equipos', ob_get_clean());
  break;

case 'nuevo':
  $almacen_opts = almacenes_options(null, 'Sin almacen');
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo equipo</h1>
      <form method="post" action="?r=equipos.guardar">
        <?= csrf_field() ?>
        <?= form_input('id_equipo', 'Código *', '', ['required' => true, 'maxlength' => 50]) ?>
        <?= form_input('equipo', 'Nombre *', '', ['required' => true, 'maxlength' => 100]) ?>
        <?= form_select('estatus', 'Estatus inicial', ['disponible' => 'Disponible', 'mantenimiento' => 'Mantenimiento'], 'disponible', ['class' => 'form-select']) ?>
        <?= form_select('id_almacen', 'Almacén', $almacen_opts, '', ['class' => 'form-select']) ?>
        <?= form_input('foto', 'Foto (ruta opcional)', '') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Guardar</button>
          <a class="btn btn-light" href="?r=equipos.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo equipo', ob_get_clean());
  break;

case 'guardar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('equipos.nuevo'); }
  $id_equipo = trim($_POST['id_equipo'] ?? '');
  $equipo = trim($_POST['equipo'] ?? '');
  $estatus = equipo_estado_normalizado($_POST['estatus'] ?? 'disponible');
  try {
    [$id_almacen, $almacen_nombre] = almacen_resolver_post('id_almacen', false);
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-warning'>".e($e->getMessage())."</div>");
    redirect('equipos.nuevo');
  }
  $ubicacion = $almacen_nombre ?: '';
  $foto = trim($_POST['foto'] ?? '') ?: null;
  if ($id_equipo === '' || $equipo === '') {
    flash("<div class='alert alert-danger'>Código y nombre son obligatorios.</div>");
    redirect('equipos.nuevo');
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q(
      "INSERT INTO equipos (id_equipo, equipo, estatus, ubicacion, id_almacen, foto, eliminado, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP())",
      [$id_equipo, $equipo, $estatus, $ubicacion, $id_almacen, $foto]
    );
    equipos_registrar_mov($pdo, $id_equipo, 'alta', null, null, $almacen_nombre, 'Registro inicial', null, $id_almacen);
    equipos_log('alta', ['id_equipo' => $id_equipo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo registrado.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo guardar el equipo.</div>");
  }
  redirect('equipos.listar');
  break;

case 'editar':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  $almacen_opts = almacenes_options((int)($eq['id_almacen'] ?? 0), 'Sin almacen');
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar equipo <?= e($eq['id_equipo']) ?></h1>
      <form method="post" action="?r=equipos.actualizar&id_equipo=<?= urlencode($eq['id_equipo']) ?>">
        <?= csrf_field() ?>
        <?= form_input('equipo', 'Nombre', $eq['equipo'], ['required' => true]) ?>
        <?= form_select('estatus', 'Estatus', ['disponible' => 'Disponible', 'ocupado' => 'Ocupado', 'mantenimiento' => 'Mantenimiento', 'baja' => 'Baja'], equipo_estado_normalizado($eq['estatus'] ?? ''), ['class' => 'form-select']) ?>
        <?= form_select('id_almacen', 'Almacén', $almacen_opts, (string)($eq['id_almacen'] ?? ''), ['class' => 'form-select']) ?>
        <?= form_input('foto', 'Foto', $eq['foto'] ?? '') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Actualizar</button>
          <a class="btn btn-light" href="?r=equipos.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Editar equipo', ob_get_clean());
  break;

case 'actualizar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('equipos.listar'); }
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  $equipo = trim($_POST['equipo'] ?? '');
  $estatus = equipo_estado_normalizado($_POST['estatus'] ?? 'disponible');
  try {
    [$id_almacen, $almacen_nombre] = almacen_resolver_post('id_almacen', false);
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-warning'>".e($e->getMessage())."</div>");
    redirect('equipos.editar&id_equipo='.$id);
  }
  $ubicacion = $almacen_nombre ?: (string)($eq['ubicacion'] ?? '');
  $foto = trim($_POST['foto'] ?? '') ?: null;
  if ($equipo === '') {
    flash("<div class='alert alert-danger'>El nombre es obligatorio.</div>");
    redirect('equipos.editar&id_equipo='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q(
      "UPDATE equipos SET equipo=?, estatus=?, ubicacion=?, id_almacen=?, foto=?, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?",
      [$equipo, $estatus, $ubicacion, $id_almacen, $foto, $id]
    );
    if (equipo_estado_normalizado($eq['estatus'] ?? '') !== $estatus) {
      equipos_registrar_mov(
        $pdo,
        $id,
        'ajuste_estado',
        null,
        null,
        null,
        'Edición manual: '.equipo_estado_label($eq['estatus'] ?? '').' -> '.equipo_estado_label($estatus)
      );
    }
    equipos_log('actualizar', ['id_equipo' => $id]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo actualizado.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo actualizar el equipo.</div>");
  }
  redirect('equipos.listar');
  break;

case 'baja':
  $id = trim($_GET['id_equipo'] ?? '');
  if ($id === '') redirect('equipos.listar');
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE equipos SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$id]);
    equipos_registrar_mov($pdo, $id, 'baja', null, null, null, 'Baja lógica manual');
    equipos_log('baja', ['id_equipo' => $id]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo dado de baja.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo dar de baja el equipo.</div>");
  }
  redirect('equipos.listar');
  break;

case 'asignar':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  $almacen_opts = almacenes_options((int)($eq['id_almacen'] ?? 0), 'Selecciona almacen');
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Asignar equipo <?= e($eq['equipo']) ?> (<?= e($eq['id_equipo']) ?>)</h1>
      <form method="post" action="?r=equipos.guardar_asignacion&id_equipo=<?= urlencode($eq['id_equipo']) ?>">
        <?= csrf_field() ?>
        <div class="alert alert-light">Estatus actual: <strong><?= e(equipo_estado_label($eq['estatus'] ?? '')) ?></strong></div>
        <?= form_input('id_servicio', 'Servicio destino', '', ['type' => 'number', 'min' => 1, 'required' => true]) ?>
        <?= form_select('id_almacen_origen', 'Almacén origen', $almacen_opts, (string)($eq['id_almacen'] ?? ''), ['class' => 'form-select']) ?>
        <?= form_input('notas', 'Notas', '') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Asignar</button>
          <a class="btn btn-light" href="?r=equipos.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Asignar equipo', ob_get_clean());
  break;

case 'guardar_asignacion':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('equipos.asignar&id_equipo='.$id); }
  $id_servicio = (int)($_POST['id_servicio'] ?? 0);
  try {
    [$idAlmacenOrigen, $origen] = almacen_resolver_post('id_almacen_origen', false);
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-warning'>".e($e->getMessage())."</div>");
    redirect('equipos.asignar&id_equipo='.$id);
  }
  if ($idAlmacenOrigen === null && !empty($eq['id_almacen'])) {
    $idAlmacenOrigen = (int)$eq['id_almacen'];
    $origen = almacen_nombre($idAlmacenOrigen);
  }
  $destino = 'Servicio #'.$id_servicio;
  $notas = trim($_POST['notas'] ?? '');
  if (!servicio_exists($id_servicio)) {
    flash("<div class='alert alert-danger'>Servicio no encontrado.</div>");
    redirect('equipos.asignar&id_equipo='.$id);
  }
  if (equipo_estado_ocupado($eq['estatus'] ?? '')) {
    flash("<div class='alert alert-warning'>El equipo ya está ocupado.</div>");
    redirect('equipos.asignar&id_equipo='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE equipos SET estatus='ocupado', ubicacion=?, id_almacen=NULL, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$destino, $id]);
    equipos_registrar_mov($pdo, $id, 'asignacion', $id_servicio, $origen ?: null, $destino, $notas ?: null, $idAlmacenOrigen, null);
    equipos_log('asignar', ['id_equipo' => $id, 'servicio' => $id_servicio]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo asignado.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo asignar el equipo.</div>");
  }
  redirect('equipos.listar');
  break;

case 'retornar':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  $almacen_opts = almacenes_options(null, 'Selecciona almacen');
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Retornar equipo <?= e($eq['equipo']) ?> (<?= e($eq['id_equipo']) ?>)</h1>
      <form method="post" action="?r=equipos.guardar_retorno&id_equipo=<?= urlencode($eq['id_equipo']) ?>">
        <?= csrf_field() ?>
        <?= form_input('id_servicio', 'Servicio origen', '', ['type' => 'number', 'min' => 1, 'required' => true]) ?>
        <?= form_select('id_almacen_destino', 'Almacén destino', $almacen_opts, '', ['class' => 'form-select', 'required' => true]) ?>
        <?= form_input('notas', 'Notas', '') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Registrar retorno</button>
          <a class="btn btn-light" href="?r=equipos.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Retorno de equipo', ob_get_clean());
  break;

case 'guardar_retorno':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('equipos.retornar&id_equipo='.$id); }
  $id_servicio = (int)($_POST['id_servicio'] ?? 0);
  try {
    [$idAlmacenDestino, $destino] = almacen_resolver_post('id_almacen_destino', true);
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-warning'>".e($e->getMessage())."</div>");
    redirect('equipos.retornar&id_equipo='.$id);
  }
  $notas = trim($_POST['notas'] ?? '');
  if (!servicio_exists($id_servicio)) {
    flash("<div class='alert alert-danger'>Servicio no encontrado.</div>");
    redirect('equipos.retornar&id_equipo='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE equipos SET estatus='disponible', ubicacion=?, id_almacen=?, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$destino, $idAlmacenDestino, $id]);
    equipos_registrar_mov($pdo, $id, 'devolucion', $id_servicio, null, $destino, $notas ?: null, null, $idAlmacenDestino);
    equipos_log('retorno', ['id_equipo' => $id, 'servicio' => $id_servicio]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo retornado.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo registrar el retorno.</div>");
  }
  redirect('equipos.listar');
  break;

case 'historial':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  $movs = equipos_movimientos($id);
  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h5 m-0">Kardex · <?= e($eq['equipo']) ?> (<?= e($eq['id_equipo']) ?>)</h1>
      <div class="text-muted small">Historial consolidado de inventario y operaciones de servicio.</div>
    </div>
    <a href="?r=equipos.listar" class="btn btn-light btn-sm">Volver</a>
  </div>
  <div class="mb-3">
    <span class="badge bg-<?= equipo_estado_classes($eq['estatus'] ?? '') ?>">Estado: <?= e(equipo_estado_label($eq['estatus'] ?? '')) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead><tr><th>Fecha</th><th>Movimiento</th><th>Servicio</th><th>Origen</th><th>Destino</th><th>Fuente</th><th>Notas</th></tr></thead>
      <tbody>
        <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= !empty($m['created_at']) ? e(date('Y-m-d H:i', strtotime($m['created_at']))) : '—' ?></td>
          <td><?= e(equipo_movimiento_label((string)($m['tipo'] ?? ''))) ?></td>
          <td><?= !empty($m['id_servicio']) ? '<a href="?r=servicios.ver&id='.(int)$m['id_servicio'].'">#'.(int)$m['id_servicio'].'</a>' : '—' ?></td>
          <td><?= e($m['origen'] ?? '—') ?></td>
          <td><?= e($m['destino'] ?? '—') ?></td>
          <td><span class="badge text-bg-light border"><?= e(($m['fuente'] ?? 'inventario') === 'servicio' ? 'Servicio' : 'Inventario') ?></span></td>
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
  render('Kardex de equipo', ob_get_clean());
  break;

default:
  redirect('equipos.listar');
}
