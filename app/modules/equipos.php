<?php
// app/modules/equipos.php — Inventario operativo con kardex
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'listar';
$roles = ['administradora','auxiliar'];
require_role($roles);

function equipos_find($id_equipo) {
  return qone("SELECT * FROM equipos WHERE id_equipo=? AND COALESCE(eliminado,0)=0", [$id_equipo]);
}

function equipos_movimientos($id_equipo): array {
  return qall("SELECT tipo, id_servicio, origen, destino, notas, created_at
               FROM equipos_movimientos WHERE id_equipo=? ORDER BY id_mov DESC", [$id_equipo]);
}

function equipos_log(string $accion, array $payload=[]): void {
  $user = current_user();
  $detalle = json_encode(array_merge(['accion'=>$accion], $payload), JSON_UNESCAPED_UNICODE);
  try {
    q("INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES ('equipos', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$accion, $user['usuario'] ?? 'sistema', $detalle]);
  } catch (Exception $e) {}
}

function equipos_registrar_mov(PDO $pdo, string $id_equipo, string $tipo, ?int $id_servicio=null, ?string $origen=null, ?string $destino=null, ?string $notas=null): void {
  $stmt = $pdo->prepare("INSERT INTO equipos_movimientos (id_equipo,tipo,id_servicio,origen,destino,notas) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$id_equipo,$tipo,$id_servicio,$origen,$destino,$notas]);
}

function servicio_exists($id_servicio): bool {
  if ($id_servicio <=0) return false;
  return (bool) qone("SELECT 1 FROM servicios WHERE id_servicio=? AND COALESCE(eliminado,0)=0", [$id_servicio]);
}

$adminOnly = ['nuevo','guardar','editar','actualizar','baja'];
if (in_array($action, $adminOnly, true)) {
  require_role(['administradora']);
}

switch ($action) {
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $estatus = trim($_GET['est'] ?? 'todos');
  $where = ['COALESCE(eliminado,0)=0'];
  $params = [];
  if ($estatus !== '' && $estatus !== 'todos') {
    $where[] = "estatus = ?";
    $params[] = $estatus;
  }
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(id_equipo LIKE ? OR equipo LIKE ? OR ubicacion LIKE ?)";
    array_push($params,$like,$like,$like);
  }
  $rows = qall("SELECT id_equipo, equipo, estatus, ubicacion, DATE(updated_at) AS actualizado
                FROM equipos WHERE ".implode(' AND ', $where)."
                ORDER BY equipo ASC LIMIT 300", $params);
  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Equipos operativos</h1>
    <?php if (user_has_role('administradora')): ?>
      <a href="?r=equipos.nuevo" class="btn btn-success btn-sm">Nuevo equipo</a>
    <?php endif; ?>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="equipos.listar">
    <div class="col-md-4"><?= form_input('q','Buscar',$q,['placeholder'=>'Código o nombre']) ?></div>
    <div class="col-md-4"><?= form_select('est','Estatus',['todos'=>'Todos','disponible'=>'Disponible','asignado'=>'Asignado','mantenimiento'=>'Mantenimiento','baja'=>'Baja'],$estatus,['class'=>'form-select']) ?></div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead><tr><th>Código</th><th>Equipo</th><th>Estatus</th><th>Ubicación</th><th>Actualizado</th><th style="width:230px">Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['id_equipo']) ?></code></td>
          <td><?= e($r['equipo']) ?></td>
          <td><span class="badge bg-<?= $r['estatus']==='disponible'?'success':($r['estatus']==='asignado'?'warning text-dark':($r['estatus']==='mantenimiento'?'info text-dark':'secondary')) ?>"><?= e($r['estatus']) ?></span></td>
          <td><?= e($r['ubicacion'] ?: '—') ?></td>
          <td><?= e($r['actualizado'] ?: '—') ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="?r=equipos.historial&id_equipo=<?= urlencode($r['id_equipo']) ?>">Kardex</a>
            <?php if (user_has_role('administradora')): ?>
              <a class="btn btn-outline-primary btn-sm" href="?r=equipos.editar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Editar</a>
              <a class="btn btn-outline-danger btn-sm" href="?r=equipos.baja&id_equipo=<?= urlencode($r['id_equipo']) ?>" onclick="return confirm('¿Dar de baja lógica este equipo?');">Baja</a>
            <?php endif; ?>
            <a class="btn btn-outline-success btn-sm" href="?r=equipos.asignar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Asignar</a>
            <a class="btn btn-outline-info btn-sm" href="?r=equipos.retornar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Retornar</a>
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
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo equipo</h1>
      <form method="post" action="?r=equipos.guardar">
        <?= csrf_field() ?>
        <?= form_input('id_equipo','Código *','',['required'=>true,'maxlength'=>50]) ?>
        <?= form_input('equipo','Nombre *','',['required'=>true,'maxlength'=>100]) ?>
        <?= form_select('estatus','Estatus inicial',['disponible'=>'Disponible','mantenimiento'=>'Mantenimiento'],'disponible',['class'=>'form-select']) ?>
        <?= form_input('ubicacion','Ubicación','') ?>
        <?= form_input('foto','Foto (ruta opcional)','') ?>
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
  $estatus = trim($_POST['estatus'] ?? 'disponible');
  $ubicacion = trim($_POST['ubicacion'] ?? '');
  $foto = trim($_POST['foto'] ?? '') ?: null;
  if ($id_equipo==='' || $equipo==='') {
    flash("<div class='alert alert-danger'>Código y nombre son obligatorios.</div>");
    redirect('equipos.nuevo');
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("INSERT INTO equipos (id_equipo, equipo, estatus, ubicacion, foto, eliminado, updated_at)
       VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP())",
      [$id_equipo,$equipo,$estatus,$ubicacion,$foto]);
    equipos_registrar_mov($pdo,$id_equipo,'alta',null,null,null,'Registro inicial');
    equipos_log('alta',['id_equipo'=>$id_equipo]);
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
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar equipo <?= e($eq['id_equipo']) ?></h1>
      <form method="post" action="?r=equipos.actualizar&id_equipo=<?= urlencode($eq['id_equipo']) ?>">
        <?= csrf_field() ?>
        <?= form_input('equipo','Nombre',$eq['equipo'],['required'=>true]) ?>
        <?= form_select('estatus','Estatus',['disponible'=>'Disponible','asignado'=>'Asignado','mantenimiento'=>'Mantenimiento','baja'=>'Baja'],$eq['estatus'],['class'=>'form-select']) ?>
        <?= form_input('ubicacion','Ubicación',$eq['ubicacion'] ?? '') ?>
        <?= form_input('foto','Foto',$eq['foto'] ?? '') ?>
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
  $estatus = trim($_POST['estatus'] ?? 'disponible');
  $ubicacion = trim($_POST['ubicacion'] ?? '');
  $foto = trim($_POST['foto'] ?? '') ?: null;
  if ($equipo==='') {
    flash("<div class='alert alert-danger'>El nombre es obligatorio.</div>");
    redirect('equipos.editar&id_equipo='.$id);
  }
  q("UPDATE equipos SET equipo=?, estatus=?, ubicacion=?, foto=?, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?",
    [$equipo,$estatus,$ubicacion,$foto,$id]);
  equipos_log('actualizar',['id_equipo'=>$id]);
  flash("<div class='alert alert-success'>Equipo actualizado.</div>");
  redirect('equipos.listar');
  break;

case 'baja':
  $id = trim($_GET['id_equipo'] ?? '');
  if ($id==='') redirect('equipos.listar');
  q("UPDATE equipos SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$id]);
  equipos_log('baja',['id_equipo'=>$id]);
  flash("<div class='alert alert-success'>Equipo dado de baja.</div>");
  redirect('equipos.listar');
  break;

case 'asignar':
  $id = trim($_GET['id_equipo'] ?? '');
  $eq = equipos_find($id);
  if (!$eq) {
    flash("<div class='alert alert-warning'>Equipo no encontrado.</div>");
    redirect('equipos.listar');
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Asignar equipo <?= e($eq['equipo']) ?> (<?= e($eq['id_equipo']) ?>)</h1>
      <form method="post" action="?r=equipos.guardar_asignacion&id_equipo=<?= urlencode($eq['id_equipo']) ?>">
        <?= csrf_field() ?>
        <div class="alert alert-light">Estatus actual: <strong><?= e($eq['estatus']) ?></strong></div>
        <?= form_input('id_servicio','Servicio destino','',['type'=>'number','min'=>1,'required'=>true]) ?>
        <?= form_input('origen','Origen','almacén',['required'=>true]) ?>
        <?= form_input('destino','Destino','servicio',['required'=>true]) ?>
        <?= form_input('notas','Notas','') ?>
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
  $origen = trim($_POST['origen'] ?? 'almacén');
  $destino = trim($_POST['destino'] ?? 'servicio');
  $notas = trim($_POST['notas'] ?? '');
  if (!servicio_exists($id_servicio)) {
    flash("<div class='alert alert-danger'>Servicio no encontrado.</div>");
    redirect('equipos.asignar&id_equipo='.$id);
  }
  if ($eq['estatus'] === 'asignado') {
    flash("<div class='alert alert-warning'>El equipo ya está asignado.</div>");
    redirect('equipos.asignar&id_equipo='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE equipos SET estatus='asignado', ubicacion=?, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$destino,$id]);
    equipos_registrar_mov($pdo,$id,'asignacion',$id_servicio,$origen,$destino,$notas ?: null);
    equipos_log('asignar',['id_equipo'=>$id,'servicio'=>$id_servicio]);
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
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Retornar equipo <?= e($eq['equipo']) ?> (<?= e($eq['id_equipo']) ?>)</h1>
      <form method="post" action="?r=equipos.guardar_retorno&id_equipo=<?= urlencode($eq['id_equipo']) ?>">
        <?= csrf_field() ?>
        <?= form_input('id_servicio','Servicio origen','',['type'=>'number','min'=>1,'required'=>true]) ?>
        <?= form_input('destino','Destino','almacén',['required'=>true]) ?>
        <?= form_input('notas','Notas','') ?>
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
  $destino = trim($_POST['destino'] ?? 'almacén');
  $notas = trim($_POST['notas'] ?? '');
  if (!servicio_exists($id_servicio)) {
    flash("<div class='alert alert-danger'>Servicio no encontrado.</div>");
    redirect('equipos.retornar&id_equipo='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE equipos SET estatus='disponible', ubicacion=?, updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$destino,$id]);
    equipos_registrar_mov($pdo,$id,'devolucion',$id_servicio,null,$destino,$notas ?: null);
    equipos_log('retorno',['id_equipo'=>$id,'servicio'=>$id_servicio]);
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
    <h1 class="h5 m-0">Kardex · <?= e($eq['equipo']) ?> (<?= e($eq['id_equipo']) ?>)</h1>
    <a href="?r=equipos.listar" class="btn btn-light btn-sm">Volver</a>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead><tr><th>Fecha</th><th>Movimiento</th><th>Servicio</th><th>Origen</th><th>Destino</th><th>Notas</th></tr></thead>
      <tbody>
        <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?></td>
          <td><?= e($m['tipo']) ?></td>
          <td><?= $m['id_servicio'] ? '<a href="?r=servicios.ver&id='.$m['id_servicio'].'">#'.$m['id_servicio'].'</a>' : '—' ?></td>
          <td><?= e($m['origen'] ?? '—') ?></td>
          <td><?= e($m['destino'] ?? '—') ?></td>
          <td><?= e($m['notas'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movs)): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin movimientos</td></tr>
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
