<?php
// app/modules/cajas.php — Inventario de ataúdes con reciclado y kardex
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'listar';
$roles = ['administradora','auxiliar'];
require_role($roles);

function cajas_proveedores(): array {
  try {
    return qall("SELECT id_proveedor, nombre FROM proveedores WHERE COALESCE(eliminado,0)=0 ORDER BY nombre ASC");
  } catch (Exception $e) {
    return [];
  }
}

function caja_find($codigo) {
  return qone("SELECT * FROM cajas WHERE codigo=? AND COALESCE(eliminado,0)=0", [$codigo]);
}

function caja_movimientos($codigo): array {
  return qall("SELECT tipo, id_servicio, notas, created_at FROM cajas_movimientos WHERE codigo=? ORDER BY id_mov DESC", [$codigo]);
}

function caja_log(string $accion, array $payload = []): void {
  $user = current_user();
  $detalle = json_encode(array_merge(['accion'=>$accion], $payload), JSON_UNESCAPED_UNICODE);
  try {
    q("INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES ('cajas', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$accion, $user['usuario'] ?? 'sistema', $detalle]);
  } catch (Exception $e) {}
}

function caja_registrar_mov(PDO $pdo, string $codigo, string $tipo, ?int $id_servicio=null, ?string $notas=null): void {
  $stmt = $pdo->prepare("INSERT INTO cajas_movimientos (codigo,tipo,id_servicio,notas) VALUES (?,?,?,?)");
  $stmt->execute([$codigo,$tipo,$id_servicio,$notas]);
}

$adminOnly = ['nuevo','guardar','editar','actualizar','baja'];
if (in_array($action, $adminOnly, true)) {
  require_role(['administradora']);
}

switch ($action) {
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $estado = trim($_GET['estado'] ?? 'todos');
  $rentado = trim($_GET['rentado'] ?? 'todos');
  $proveedor = (int)($_GET['id_proveedor'] ?? 0);

  $where = ['COALESCE(c.eliminado,0)=0'];
  $params = [];
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(c.codigo LIKE ? OR c.modelo LIKE ? OR c.color LIKE ? OR c.ubicacion LIKE ? OR c.proveedor LIKE ?)";
    array_push($params, $like,$like,$like,$like,$like);
  }
  if ($estado !== '' && $estado !== 'todos') {
    $where[] = "c.estado = ?";
    $params[] = $estado;
  }
  if ($rentado === 'si') {
    $where[] = "c.es_rentado = 1";
  } elseif ($rentado === 'no') {
    $where[] = "c.es_rentado = 0";
  }
  if ($proveedor > 0) {
    $where[] = "c.id_proveedor = ?";
    $params[] = $proveedor;
  }

  $rows = qall("SELECT c.codigo, c.modelo, c.estado, c.ubicacion, c.color, c.ciclos_uso, c.reciclado, c.es_rentado,
                       c.id_proveedor, c.proveedor, DATE(c.updated_at) AS actualizado,
                       p.nombre AS proveedor_nombre
                FROM cajas c
                LEFT JOIN proveedores p ON p.id_proveedor=c.id_proveedor
                WHERE ".implode(' AND ', $where)."
                ORDER BY c.created_at DESC
                LIMIT 300", $params);
  $proveedores = cajas_proveedores();
  $prov_opts = ['0'=>'Todos'];
  foreach ($proveedores as $prov) {
    $prov_opts[(string)$prov['id_proveedor']] = $prov['nombre'];
  }

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Ataúdes</h1>
    <?php if (user_has_role('administradora')): ?>
      <a href="?r=cajas.nuevo" class="btn btn-success btn-sm">Nuevo ataúd</a>
    <?php endif; ?>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="cajas.listar">
    <div class="col-md-3"><?= form_input('q','Buscar',$q,['placeholder'=>'Código, modelo, color']) ?></div>
    <div class="col-md-3"><?= form_select('estado','Estado',['todos'=>'Todos','nuevo'=>'Nuevo','en_uso'=>'En uso','reciclado'=>'Reciclado','fuera_uso'=>'Fuera de uso'],$estado,['class'=>'form-select']) ?></div>
    <div class="col-md-3"><?= form_select('rentado','Renta',['todos'=>'Todos','si'=>'Rentados','no'=>'Venta'],$rentado,['class'=>'form-select']) ?></div>
    <div class="col-md-3"><?= form_select('id_proveedor','Proveedor',$prov_opts,(string)$proveedor,['class'=>'form-select']) ?></div>
    <div class="col-12 col-md-2 d-grid mt-2 mt-md-0">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>Código</th>
          <th>Modelo</th>
          <th>Estado</th>
          <th>Rentado</th>
          <th>Proveedor</th>
          <th>Ubicación</th>
          <th>Ciclos</th>
          <th>Actualizado</th>
          <th style="width:220px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['codigo']) ?></code></td>
          <td><?= e($r['modelo']) ?></td>
          <td><span class="badge bg-<?= $r['estado']==='en_uso'?'warning text-dark':($r['estado']==='reciclado'?'info text-dark':($r['estado']==='nuevo'?'success':'secondary')) ?>"><?= e($r['estado']) ?></span></td>
          <td><?= $r['es_rentado'] ? 'Sí' : 'No' ?></td>
          <td><?= e($r['proveedor_nombre'] ?? $r['proveedor'] ?? '—') ?></td>
          <td><?= e($r['ubicacion'] ?: '—') ?></td>
          <td><?= (int)$r['ciclos_uso'] ?></td>
          <td><?= e($r['actualizado'] ?: '—') ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="?r=cajas.historial&codigo=<?= urlencode($r['codigo']) ?>">Kardex</a>
            <?php if (user_has_role('administradora')): ?>
              <a class="btn btn-outline-primary btn-sm" href="?r=cajas.editar&codigo=<?= urlencode($r['codigo']) ?>">Editar</a>
              <a class="btn btn-outline-danger btn-sm" href="?r=cajas.baja&codigo=<?= urlencode($r['codigo']) ?>" onclick="return confirm('¿Dar de baja lógica este ataúd?');">Baja</a>
            <?php endif; ?>
            <?php if ($r['es_rentado'] && $r['estado']!=='en_uso'): ?>
              <a class="btn btn-outline-success btn-sm" href="?r=cajas.reciclar&codigo=<?= urlencode($r['codigo']) ?>" onclick="return confirm('Marcar como reciclado listo para renta?');">Reciclar</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Ataúdes', ob_get_clean());
  break;

case 'nuevo':
  $proveedores = cajas_proveedores();
  $prov_opts = ['' => 'Sin proveedor'];
  foreach ($proveedores as $prov) {
    $prov_opts[$prov['id_proveedor']] = $prov['nombre'];
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo ataúd</h1>
      <form method="post" action="?r=cajas.guardar">
        <?= csrf_field() ?>
        <?= form_input('codigo','Código', '', ['required'=>true,'maxlength'=>50]) ?>
        <?= form_input('modelo','Modelo','',['required'=>true]) ?>
        <?= form_select('estado','Estado',['nuevo'=>'Nuevo','en_uso'=>'En uso','reciclado'=>'Reciclado','fuera_uso'=>'Fuera de uso'],'nuevo',['class'=>'form-select']) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('ubicacion','Ubicación','') ?></div>
          <div class="col-md-6"><?= form_input('color','Color','') ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_proveedor','Proveedor',$prov_opts,'',['class'=>'form-select']) ?></div>
          <div class="col-md-3"><?= form_input('costo','Costo','0',['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
          <div class="col-md-3"><?= form_select('es_rentado','¿Es para renta?',['0'=>'No','1'=>'Sí'],'0',['class'=>'form-select']) ?></div>
        </div>
        <?= form_input('notas','Notas','') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Guardar</button>
          <a href="?r=cajas.listar" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo ataúd', ob_get_clean());
  break;

case 'guardar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('cajas.nuevo'); }
  $codigo = trim($_POST['codigo'] ?? '');
  $modelo = trim($_POST['modelo'] ?? '');
  $estado = trim($_POST['estado'] ?? 'nuevo');
  $ubicacion = trim($_POST['ubicacion'] ?? '');
  $color = trim($_POST['color'] ?? '');
  $id_proveedor = (int)($_POST['id_proveedor'] ?? 0) ?: null;
  $costo = (float)($_POST['costo'] ?? 0);
  $es_rentado = (int)($_POST['es_rentado'] ?? 0) ? 1 : 0;
  $notas = trim($_POST['notas'] ?? '');
  if ($codigo==='' || $modelo==='') {
    flash("<div class='alert alert-danger'>Código y modelo son obligatorios.</div>");
    redirect('cajas.nuevo');
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("INSERT INTO cajas (codigo, modelo, estado, ubicacion, color, id_proveedor, costo, es_rentado, reciclado, ciclos_uso, proveedor, notas, eliminado, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, '', ?, 0, CURRENT_TIMESTAMP())",
      [$codigo,$modelo,$estado,$ubicacion,$color,$id_proveedor,$costo,$es_rentado,$notas]);
    caja_registrar_mov($pdo, $codigo, 'alta', null, 'Registro inicial');
    caja_log('alta',['codigo'=>$codigo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataúd registrado.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo guardar el ataúd.</div>");
  }
  redirect('cajas.listar');
  break;

case 'editar':
  $codigo = trim($_GET['codigo'] ?? '');
  $caja = caja_find($codigo);
  if (!$caja) {
    flash("<div class='alert alert-warning'>Ataúd no encontrado.</div>");
    redirect('cajas.listar');
  }
  $proveedores = cajas_proveedores();
  $prov_opts = ['' => 'Sin proveedor'];
  foreach ($proveedores as $prov) {
    $prov_opts[$prov['id_proveedor']] = $prov['nombre'];
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar ataúd <?= e($caja['codigo']) ?></h1>
      <form method="post" action="?r=cajas.actualizar&codigo=<?= urlencode($caja['codigo']) ?>">
        <?= csrf_field() ?>
        <?= form_input('modelo','Modelo',$caja['modelo'],['required'=>true]) ?>
        <?= form_select('estado','Estado',['nuevo'=>'Nuevo','en_uso'=>'En uso','reciclado'=>'Reciclado','fuera_uso'=>'Fuera de uso'],$caja['estado'],['class'=>'form-select']) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('ubicacion','Ubicación',$caja['ubicacion']) ?></div>
          <div class="col-md-6"><?= form_input('color','Color',$caja['color']) ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_proveedor','Proveedor',$prov_opts,(string)($caja['id_proveedor'] ?? ''),['class'=>'form-select']) ?></div>
          <div class="col-md-3"><?= form_input('costo','Costo',$caja['costo'] ?? '0',['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
          <div class="col-md-3"><?= form_select('es_rentado','¿Es para renta?',['0'=>'No','1'=>'Sí'],(string)$caja['es_rentado'],['class'=>'form-select']) ?></div>
        </div>
        <?= form_input('notas','Notas',$caja['notas'] ?? '') ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('ciclos_uso','Ciclos de uso',$caja['ciclos_uso'],['type'=>'number','min'=>0]) ?></div>
          <div class="col-md-6"><?= form_select('reciclado','¿Reciclado?',['0'=>'No','1'=>'Sí'],(string)$caja['reciclado'],['class'=>'form-select']) ?></div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Actualizar</button>
          <a class="btn btn-light" href="?r=cajas.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Editar ataúd', ob_get_clean());
  break;

case 'actualizar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('cajas.listar'); }
  $codigo = trim($_GET['codigo'] ?? '');
  $caja = caja_find($codigo);
  if (!$caja) {
    flash("<div class='alert alert-warning'>Ataúd no encontrado.</div>");
    redirect('cajas.listar');
  }
  $modelo = trim($_POST['modelo'] ?? '');
  $estado = trim($_POST['estado'] ?? 'nuevo');
  $ubicacion = trim($_POST['ubicacion'] ?? '');
  $color = trim($_POST['color'] ?? '');
  $id_proveedor = (int)($_POST['id_proveedor'] ?? 0) ?: null;
  $costo = (float)($_POST['costo'] ?? 0);
  $es_rentado = (int)($_POST['es_rentado'] ?? 0) ? 1 : 0;
  $notas = trim($_POST['notas'] ?? '');
  $ciclos = max(0, (int)($_POST['ciclos_uso'] ?? 0));
  $reciclado = (int)($_POST['reciclado'] ?? 0) ? 1 : 0;
  if ($modelo==='') {
    flash("<div class='alert alert-danger'>El modelo es obligatorio.</div>");
    redirect('cajas.editar&codigo='.$codigo);
  }
  q("UPDATE cajas
     SET modelo=?, estado=?, ubicacion=?, color=?, id_proveedor=?, costo=?, es_rentado=?, notas=?, ciclos_uso=?, reciclado=?, updated_at=CURRENT_TIMESTAMP()
     WHERE codigo=?",
    [$modelo,$estado,$ubicacion,$color,$id_proveedor,$costo,$es_rentado,$notas,$ciclos,$reciclado,$codigo]);
  caja_log('actualizar',['codigo'=>$codigo]);
  flash("<div class='alert alert-success'>Ataúd actualizado.</div>");
  redirect('cajas.listar');
  break;

case 'baja':
  $codigo = trim($_GET['codigo'] ?? '');
  if ($codigo==='') redirect('cajas.listar');
  q("UPDATE cajas SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE codigo=?", [$codigo]);
  caja_log('baja',['codigo'=>$codigo]);
  flash("<div class='alert alert-success'>Ataúd dado de baja lógica.</div>");
  redirect('cajas.listar');
  break;

case 'reciclar':
  $codigo = trim($_GET['codigo'] ?? '');
  $caja = caja_find($codigo);
  if (!$caja) {
    flash("<div class='alert alert-warning'>Ataúd no encontrado.</div>");
    redirect('cajas.listar');
  }
  q("UPDATE cajas SET reciclado=1, estado='reciclado', updated_at=CURRENT_TIMESTAMP() WHERE codigo=?", [$codigo]);
  caja_log('reciclar',['codigo'=>$codigo]);
  flash("<div class='alert alert-success'>Ataúd marcado como reciclado.</div>");
  redirect('cajas.listar');
  break;

case 'historial':
  $codigo = trim($_GET['codigo'] ?? '');
  $caja = caja_find($codigo);
  if (!$caja) {
    flash("<div class='alert alert-warning'>Ataúd no encontrado.</div>");
    redirect('cajas.listar');
  }
  $movs = caja_movimientos($codigo);
  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h5 m-0">Kardex · <?= e($caja['codigo']) ?> (<?= e($caja['modelo']) ?>)</h1>
    <a href="?r=cajas.listar" class="btn btn-light btn-sm">Volver</a>
  </div>
  <div class="mb-3">
    <span class="badge bg-secondary">Estado: <?= e($caja['estado']) ?></span>
    <span class="badge bg-info text-dark">Ciclos: <?= (int)$caja['ciclos_uso'] ?></span>
    <span class="badge bg-<?= $caja['es_rentado']?'success':'dark' ?>"><?= $caja['es_rentado']?'Rentado':'Venta' ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead><tr><th>Fecha</th><th>Movimiento</th><th>Servicio</th><th>Notas</th></tr></thead>
      <tbody>
        <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?></td>
          <td><?= e($m['tipo']) ?></td>
          <td><?= $m['id_servicio'] ? '<a href="?r=servicios.ver&id='.$m['id_servicio'].'">#'.$m['id_servicio'].'</a>' : '—' ?></td>
          <td><?= e($m['notas'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movs)): ?>
        <tr><td colspan="4" class="text-center text-muted">Sin movimientos registrados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Kardex de ataúd', ob_get_clean());
  break;

default:
  redirect('cajas.listar');
}
