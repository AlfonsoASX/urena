<?php
// app/modules/servicios.php — Servicios de velación con folio, inventarios y kardex
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$action = $action ?? 'listar';
$roles = ['administradora','auxiliar','vendedor','cobrador'];
require_role($roles);

function servicios_log($accion, array $extra = []): void {
  $user = current_user();
  $detalle = json_encode(array_merge(['accion'=>$accion], $extra), JSON_UNESCAPED_UNICODE);
  try {
    q("INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES ('servicios', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$accion, $user['usuario'] ?? 'sistema', $detalle]);
  } catch (Exception $e) {}
}

function servicios_generar_folio(PDO $pdo): string {
  $prefix = 'SERV-'.date('Ym').'-';
  $stmt = $pdo->prepare("SELECT folio FROM servicios WHERE folio LIKE ? ORDER BY folio DESC LIMIT 1");
  $stmt->execute([$prefix.'%']);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && preg_match('/(\d{4})$/', $row['folio'], $m)) {
    $seq = (int)$m[1] + 1;
  } else {
    $seq = 1;
  }
  return $prefix.str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function servicios_fetch($id) {
  return qone("SELECT s.*, COALESCE(f.nom_fallecido,'') AS fallecido
               FROM servicios s
               LEFT JOIN servicio_fallecido sf ON sf.id_servicio=s.id_servicio
               LEFT JOIN fallecido f ON f.id_fallecido=sf.id_fallecido
               WHERE s.id_servicio=? AND COALESCE(s.eliminado,0)=0", [$id]);
}

function servicios_fallecidos(): array {
  try {
    return qall("SELECT id_fallecido, nom_fallecido FROM fallecido WHERE COALESCE(eliminado,0)=0 ORDER BY nom_fallecido ASC");
  } catch (Exception $e) {
    return [];
  }
}

function servicios_equipo_disponible(): array {
  try {
    return qall("SELECT id_equipo, equipo FROM equipos WHERE COALESCE(eliminado,0)=0 AND estatus='disponible' ORDER BY equipo ASC");
  } catch (Exception $e) {
    return [];
  }
}

function servicios_cajas_disponibles(): array {
  try {
    return qall("SELECT codigo, modelo, estado FROM cajas WHERE COALESCE(eliminado,0)=0 AND estado IN ('nuevo','reciclado') ORDER BY modelo ASC");
  } catch (Exception $e) {
    return [];
  }
}

function servicios_caja_activa($codigo): ?array {
  return qone("SELECT c.codigo, c.modelo, c.estado, c.reciclado, c.es_rentado
               FROM cajas c
               WHERE c.codigo=? AND COALESCE(c.eliminado,0)=0", [$codigo]);
}

function servicios_caja_en_uso($codigo): bool {
  $row = qone("SELECT tipo FROM cajas_movimientos WHERE codigo=? ORDER BY id_mov DESC LIMIT 1", [$codigo]);
  return $row && $row['tipo']==='asignacion';
}

function servicios_cajas_asignadas($id_servicio): array {
  return qall("SELECT m.codigo, c.modelo, m.created_at, c.es_rentado, c.reciclado
               FROM cajas_movimientos m
               JOIN cajas c ON c.codigo=m.codigo
               WHERE m.id_servicio=? AND m.tipo='asignacion'
               ORDER BY m.id_mov DESC", [$id_servicio]);
}

function servicios_equipo_asignado($id_servicio): array {
  return qall("SELECT em.id_equipo, e.equipo, em.created_at
               FROM equipos_movimientos em
               JOIN equipos e ON e.id_equipo=em.id_equipo
               WHERE em.id_servicio=? AND em.tipo='asignacion'
               ORDER BY em.id_mov DESC", [$id_servicio]);
}

function servicios_articulos_asignados($id_servicio): array {
  return qall("SELECT s.id_articulo, a.articulo, s.cantidad, s.created_at
               FROM articulos_salida_servicio s
               JOIN articulos a ON a.id=s.id_articulo
               WHERE s.id_servicio=?
               ORDER BY s.id_salida DESC", [$id_servicio]);
}

function servicios_registrar_mov_caja(PDO $pdo, string $codigo, string $tipo, ?int $id_servicio = null, ?string $notas = null): void {
  $stmt = $pdo->prepare("INSERT INTO cajas_movimientos (codigo,tipo,id_servicio,notas) VALUES (?,?,?,?)");
  $stmt->execute([$codigo,$tipo,$id_servicio,$notas]);
}

function servicios_registrar_mov_equipo(PDO $pdo, string $id_equipo, string $tipo, ?int $id_servicio=null, ?string $origen=null, ?string $destino=null, ?string $notas=null): void {
  $stmt = $pdo->prepare("INSERT INTO equipos_movimientos (id_equipo,tipo,id_servicio,origen,destino,notas) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$id_equipo,$tipo,$id_servicio,$origen,$destino,$notas]);
}

switch ($action) {
case 'listar':
  $tab = ($_GET['tab'] ?? 'abiertos') === 'cerrados' ? 'cerrados' : 'abiertos';
  $q = trim($_GET['q'] ?? '');

  $where = ["s.eliminado=0", "s.estatus=?"];
  $params = [$tab==='abiertos' ? 'abierto' : 'cerrado'];
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(s.folio LIKE ? OR f.nom_fallecido LIKE ? OR s.responsable LIKE ? OR s.contratante_nombre LIKE ? OR s.id_servicio = ? )";
    array_push($params, $like,$like,$like,$like, ctype_digit($q)?(int)$q:-1);
  }
  $rows = qall("SELECT s.id_servicio, s.folio, DATE(s.created_at) AS fecha, s.tipo_servicio, s.tipo_disposicion, s.responsable,
                       s.contratante_nombre, COALESCE(f.nom_fallecido,'') AS fallecido
                FROM servicios s
                LEFT JOIN servicio_fallecido sf ON sf.id_servicio=s.id_servicio
                LEFT JOIN fallecido f ON f.id_fallecido=sf.id_fallecido
                WHERE ".implode(' AND ', $where)."
                ORDER BY s.id_servicio DESC
                LIMIT 300", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Servicios</h1>
    <div class="d-flex gap-2">
      <a href="?r=servicios.nuevo" class="btn btn-success btn-sm">Nuevo servicio</a>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='abiertos'?'active':'' ?>" href="?r=servicios.listar&tab=abiertos">Abiertos</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='cerrados'?'active':'' ?>" href="?r=servicios.listar&tab=cerrados">Cerrados</a></li>
  </ul>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="servicios.listar">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
      <?= form_input('q','Buscar',$q,['placeholder'=>'Folio, fallecido, responsable, contratante']) ?>
    </div>
    <div class="col-md-3 d-grid align-items-end">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fallecido</th>
          <th>Fecha</th>
          <th>Servicio</th>
          <th>Disposición</th>
          <th>Responsable</th>
          <th>Contratante</th>
          <th style="width:220px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['folio'] ?: '#'.$r['id_servicio']) ?></strong></td>
          <td><?= e($r['fallecido'] ?: '—') ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td><?= e($r['tipo_servicio']) ?></td>
          <td><span class="badge bg-secondary text-uppercase"><?= e($r['tipo_disposicion']) ?></span></td>
          <td><?= e($r['responsable']) ?></td>
          <td><?= e($r['contratante_nombre']) ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="?r=servicios.ver&id=<?= (int)$r['id_servicio'] ?>">Abrir</a>
            <?php if ($tab==='abiertos'): ?>
              <a class="btn btn-outline-success btn-sm" href="?r=servicios.cerrar&id=<?= (int)$r['id_servicio'] ?>" onclick="return confirm('¿Cerrar servicio? Se registrará devolución de recursos.');">Cerrar</a>
            <?php endif; ?>
            <a class="btn btn-outline-danger btn-sm" href="?r=servicios.borrar&id=<?= (int)$r['id_servicio'] ?>&tab=<?= $tab ?>" onclick="return confirm('¿Borrar lógicamente este servicio?');">Borrar</a>
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
  render('Servicios', ob_get_clean());
  break;

case 'nuevo':
  $fallecidos = servicios_fallecidos();
  $fall_opts = ['' => 'Crear nuevo fallecido'];
  foreach ($fallecidos as $f) {
    $fall_opts[$f['id_fallecido']] = $f['nom_fallecido'];
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo servicio de velación</h1>
      <form method="post" action="?r=servicios.guardar">
        <?= csrf_field() ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('tipo_servicio','Tipo de servicio',['funerario'=>'Funerario','capilla'=>'Capilla','otro'=>'Otro'],'funerario',['required'=>true]) ?></div>
          <div class="col-md-6"><?= form_select('tipo_disposicion','Tipo de disposición',['cremacion'=>'Cremación','inhumacion'=>'Inhumación'],'inhumacion',['required'=>true]) ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('responsable','Responsable','',['required'=>true]) ?></div>
          <div class="col-md-6"><?= form_input('auxiliares','Auxiliares','') ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('contratante_nombre','Contratante','',['required'=>true]) ?></div>
          <div class="col-md-3"><?= form_input('contratante_tel','Teléfono','',['placeholder'=>'Opcional']) ?></div>
          <div class="col-md-3"><?= form_input('contratante_email','Email','',['type'=>'email','placeholder'=>'Opcional']) ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_fallecido','Fallecido existente',$fall_opts,'',['class'=>'form-select']) ?></div>
          <div class="col-md-6"><?= form_input('nom_fallecido','Nombre del fallecido (si es nuevo)','',['maxlength'=>120]) ?></div>
        </div>
        <?= form_input('notas','Notas','') ?>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary">Guardar servicio</button>
          <a class="btn btn-light" href="?r=servicios.listar">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo servicio', ob_get_clean());
  break;

case 'guardar':
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('servicios.nuevo'); }
  $tipo_servicio = trim($_POST['tipo_servicio'] ?? '');
  $tipo_disposicion = trim($_POST['tipo_disposicion'] ?? '');
  $responsable = trim($_POST['responsable'] ?? '');
  $auxiliares = trim($_POST['auxiliares'] ?? '');
  $contratante = trim($_POST['contratante_nombre'] ?? '');
  $tel = trim($_POST['contratante_tel'] ?? '') ?: null;
  $email = trim($_POST['contratante_email'] ?? '') ?: null;
  $id_fallecido = (int)($_POST['id_fallecido'] ?? 0);
  $nom_fallecido = trim($_POST['nom_fallecido'] ?? '');
  $notas = trim($_POST['notas'] ?? '');

  if ($tipo_servicio==='' || $tipo_disposicion==='' || $responsable==='' || $contratante==='') {
    flash("<div class='alert alert-danger'>Completa los campos obligatorios.</div>");
    redirect('servicios.nuevo');
  }
  if ($id_fallecido <= 0 && $nom_fallecido==='') {
    flash("<div class='alert alert-danger'>Debes seleccionar o registrar al fallecido.</div>");
    redirect('servicios.nuevo');
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $folio = servicios_generar_folio($pdo);
    q("INSERT INTO servicios (folio, tipo_servicio, tipo_disposicion, responsable, auxiliares, contratante_nombre, contratante_tel, contratante_email, notas, estatus, eliminado, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'abierto', 0, CURRENT_TIMESTAMP())",
      [$folio,$tipo_servicio,$tipo_disposicion,$responsable,$auxiliares,$contratante,$tel,$email,$notas]);
    $id_serv = (int)$pdo->lastInsertId();

    if ($id_fallecido <= 0) {
      q("INSERT INTO fallecido (nom_fallecido, fecha) VALUES (?, CURRENT_DATE())", [$nom_fallecido]);
      $id_fallecido = (int)$pdo->lastInsertId();
    }
    q("INSERT INTO servicio_fallecido (id_fallecido,id_servicio) VALUES (?,?)", [$id_fallecido,$id_serv]);

    servicios_log('crear', ['id_servicio'=>$id_serv,'folio'=>$folio]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Servicio creado con folio $folio.</div>");
    redirect('servicios.ver&id='.$id_serv);
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo crear el servicio.</div>");
    redirect('servicios.nuevo');
  }
  break;

case 'ver':
  $id = (int)($_GET['id'] ?? 0);
  $svc = servicios_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  $cajas_asig = servicios_cajas_asignadas($id);
  $equipos_asig = servicios_equipo_asignado($id);
  $articulos = servicios_articulos_asignados($id);
  $cajas_disp = servicios_cajas_disponibles();
  $equipos_disp = servicios_equipo_disponible();

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h5 m-0">Servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?> · <?= e($svc['fallecido'] ?: 'Sin fallecido') ?></h1>
    <div class="d-flex gap-2">
      <?php if ($svc['estatus']==='abierto'): ?>
        <a href="?r=servicios.cerrar&id=<?= (int)$svc['id_servicio'] ?>" class="btn btn-success btn-sm" onclick="return confirm('¿Cerrar servicio y devolver recursos?');">Cerrar servicio</a>
      <?php endif; ?>
      <a href="?r=servicios.listar" class="btn btn-light btn-sm">Volver</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="mb-3">Datos generales</h6>
          <dl class="row mb-0">
            <dt class="col-sm-4">Folio</dt><dd class="col-sm-8"><?= e($svc['folio']) ?></dd>
            <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(date('Y-m-d', strtotime($svc['created_at']))) ?></dd>
            <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= e($svc['tipo_servicio']) ?></dd>
            <dt class="col-sm-4">Disposición</dt><dd class="col-sm-8"><?= e($svc['tipo_disposicion']) ?></dd>
            <dt class="col-sm-4">Responsable</dt><dd class="col-sm-8"><?= e($svc['responsable']) ?></dd>
            <dt class="col-sm-4">Auxiliares</dt><dd class="col-sm-8"><?= e($svc['auxiliares'] ?: '—') ?></dd>
            <dt class="col-sm-4">Contratante</dt><dd class="col-sm-8"><?= e($svc['contratante_nombre']) ?></dd>
            <dt class="col-sm-4">Contacto</dt><dd class="col-sm-8"><?= e($svc['contratante_tel'] ?: '—') ?> / <?= e($svc['contratante_email'] ?: '—') ?></dd>
            <dt class="col-sm-4">Notas</dt><dd class="col-sm-8"><?= e($svc['notas'] ?: '—') ?></dd>
            <dt class="col-sm-4">Estatus</dt><dd class="col-sm-8"><span class="badge bg-<?= $svc['estatus']==='abierto'?'warning text-dark':'success' ?>"><?= e($svc['estatus']) ?></span></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="mb-3">Ataúdes asignados</h6>
          <div class="table-responsive mb-3">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Código</th><th>Modelo</th><th>Fecha</th><th>Reciclado</th></tr></thead>
              <tbody>
                <?php foreach ($cajas_asig as $cx): ?>
                <tr>
                  <td><code><?= e($cx['codigo']) ?></code></td>
                  <td><?= e($cx['modelo']) ?></td>
                  <td><?= e(date('Y-m-d', strtotime($cx['created_at']))) ?></td>
                  <td><?= $cx['es_rentado'] ? ($cx['reciclado'] ? 'Sí' : 'Pendiente') : 'Venta' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cajas_asig)): ?>
                <tr><td colspan="4" class="text-center text-muted">Sin ataúd asignado</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($svc['estatus']==='abierto'): ?>
            <form method="post" action="?r=servicios.asignar_caja&id=<?= (int)$svc['id_servicio'] ?>">
              <?= csrf_field() ?>
              <label class="form-label">Asignar ataúd disponible</label>
              <select name="codigo" class="form-select mb-2" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($cajas_disp as $cx): ?>
                  <option value="<?= e($cx['codigo']) ?>"><?= e($cx['codigo'].' · '.$cx['modelo'].' ('.$cx['estado'].')') ?></option>
                <?php endforeach; ?>
              </select>
              <?= form_input('notas','Notas','') ?>
              <button class="btn btn-outline-primary btn-sm">Asignar ataúd</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="mb-3">Equipo asignado</h6>
          <div class="table-responsive mb-3">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Equipo</th><th>Código</th><th>Fecha</th></tr></thead>
              <tbody>
                <?php foreach ($equipos_asig as $eq): ?>
                <tr>
                  <td><?= e($eq['equipo']) ?></td>
                  <td><code><?= e($eq['id_equipo']) ?></code></td>
                  <td><?= e(date('Y-m-d', strtotime($eq['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($equipos_asig)): ?>
                <tr><td colspan="3" class="text-center text-muted">Sin equipo</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($svc['estatus']==='abierto'): ?>
            <form method="post" action="?r=servicios.asignar_equipo&id=<?= (int)$svc['id_servicio'] ?>">
              <?= csrf_field() ?>
              <label class="form-label">Asignar equipo disponible</label>
              <select name="id_equipo" class="form-select mb-2" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($equipos_disp as $eq): ?>
                  <option value="<?= e($eq['id_equipo']) ?>"><?= e($eq['equipo'].' ('.$eq['id_equipo'].')') ?></option>
                <?php endforeach; ?>
              </select>
              <?= form_input('notas','Notas','') ?>
              <button class="btn btn-outline-primary btn-sm">Asignar equipo</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="mb-3">Artículos asignados</h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead><tr><th>Artículo</th><th>Cantidad</th><th>Fecha</th></tr></thead>
              <tbody>
                <?php foreach ($articulos as $a): ?>
                <tr>
                  <td><?= e($a['articulo']) ?></td>
                  <td><?= (int)$a['cantidad'] ?></td>
                  <td><?= e(date('Y-m-d', strtotime($a['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($articulos)): ?>
                <tr><td colspan="3" class="text-center text-muted">Sin salidas registradas</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <small class="text-muted">Las salidas se registran desde el módulo de artículos.</small>
        </div>
      </div>
    </div>
  </div>
  <?php
  render('Servicio', ob_get_clean());
  break;

case 'asignar_caja':
  $id_serv = (int)($_GET['id'] ?? 0);
  $svc = servicios_fetch($id_serv);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  if ($svc['estatus'] !== 'abierto') {
    flash("<div class='alert alert-warning'>El servicio ya está cerrado.</div>");
    redirect('servicios.ver&id='.$id_serv);
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('servicios.ver&id='.$id_serv); }
  $codigo = trim($_POST['codigo'] ?? '');
  $notas = trim($_POST['notas'] ?? '');
  if ($codigo==='') {
    flash("<div class='alert alert-danger'>Selecciona un ataúd.</div>");
    redirect('servicios.ver&id='.$id_serv);
  }
  $caja = servicios_caja_activa($codigo);
  if (!$caja) {
    flash("<div class='alert alert-danger'>Ataúd no encontrado.</div>");
    redirect('servicios.ver&id='.$id_serv);
  }
  if (servicios_caja_en_uso($codigo)) {
    flash("<div class='alert alert-warning'>El ataúd está asignado a otro servicio.</div>");
    redirect('servicios.ver&id='.$id_serv);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE cajas SET estado='en_uso', reciclado=0 WHERE codigo=?");
    $stmt->execute([$codigo]);
    servicios_registrar_mov_caja($pdo, $codigo, 'asignacion', $id_serv, $notas ?: null);
    servicios_log('asignar_caja',['servicio'=>$id_serv,'codigo'=>$codigo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataúd asignado correctamente.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo asignar el ataúd.</div>");
  }
  redirect('servicios.ver&id='.$id_serv);
  break;

case 'asignar_equipo':
  $id_serv = (int)($_GET['id'] ?? 0);
  $svc = servicios_fetch($id_serv);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  if ($svc['estatus'] !== 'abierto') {
    flash("<div class='alert alert-warning'>Servicio cerrado.</div>");
    redirect('servicios.ver&id='.$id_serv);
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('servicios.ver&id='.$id_serv); }
  $id_equipo = trim($_POST['id_equipo'] ?? '');
  $notas = trim($_POST['notas'] ?? '');
  if ($id_equipo==='') {
    flash("<div class='alert alert-danger'>Selecciona equipo.</div>");
    redirect('servicios.ver&id='.$id_serv);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $eq = qone("SELECT estatus FROM equipos WHERE id_equipo=? AND COALESCE(eliminado,0)=0 FOR UPDATE", [$id_equipo]);
    if (!$eq || $eq['estatus'] !== 'disponible') {
      throw new RuntimeException('El equipo no está disponible.');
    }
    q("UPDATE equipos SET estatus='asignado', updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$id_equipo]);
    servicios_registrar_mov_equipo($pdo, $id_equipo, 'asignacion', $id_serv, null, null, $notas ?: null);
    servicios_log('asignar_equipo',['servicio'=>$id_serv,'equipo'=>$id_equipo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Equipo asignado.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>".e($e->getMessage())."</div>");
  }
  redirect('servicios.ver&id='.$id_serv);
  break;

case 'cerrar':
  $id = (int)($_GET['id'] ?? 0);
  $svc = servicios_fetch($id);
  if (!$svc) {
    flash("<div class='alert alert-warning'>Servicio no encontrado.</div>");
    redirect('servicios.listar');
  }
  if ($svc['estatus'] === 'cerrado') {
    flash("<div class='alert alert-info'>El servicio ya estaba cerrado.</div>");
    redirect('servicios.ver&id='.$id);
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE servicios SET estatus='cerrado', updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?", [$id]);
    // Devolución de ataúdes rentados
    $cajas = servicios_cajas_asignadas($id);
    foreach ($cajas as $cx) {
      servicios_registrar_mov_caja($pdo, $cx['codigo'], 'devolucion', $id, 'Cierre de servicio');
      if ($cx['es_rentado']) {
        q("UPDATE cajas SET reciclado=1, ciclos_uso=ciclos_uso+1, estado='reciclado' WHERE codigo=?", [$cx['codigo']]);
      } else {
        q("UPDATE cajas SET estado='fuera_uso' WHERE codigo=?", [$cx['codigo']]);
      }
    }
    // Devolución de equipo
    $equipos = servicios_equipo_asignado($id);
    foreach ($equipos as $eq) {
      servicios_registrar_mov_equipo($pdo, $eq['id_equipo'], 'devolucion', $id, null, 'almacen', 'Cierre de servicio');
      q("UPDATE equipos SET estatus='disponible', updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$eq['id_equipo']]);
    }
    servicios_log('cerrar',['id_servicio'=>$id]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Servicio cerrado y recursos devueltos.</div>");
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo cerrar el servicio.</div>");
  }
  redirect('servicios.ver&id='.$id);
  break;

case 'borrar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id<=0) redirect('servicios.listar');
  q("UPDATE servicios SET eliminado=1 WHERE id_servicio=?", [$id]);
  servicios_log('baja',['id_servicio'=>$id]);
  flash("<div class='alert alert-success'>Servicio borrado lógicamente.</div>");
  $tab = $_GET['tab'] ?? 'abiertos';
  redirect('servicios.listar&tab='.$tab);
  break;

default:
  redirect('servicios.listar');
}
