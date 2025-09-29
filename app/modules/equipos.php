<?php
// app/modules/equipos.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

/**
 * Auto-migraciones suaves para robustez:
 * - agrega columna eliminado a equipos
 * - normaliza longitudes de columnas usadas
 */
function equipos_ensure_schema() {
  try { q("ALTER TABLE equipos ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e){}
  // Asegurar estatus con default si aplica (MariaDB permite IF NOT EXISTS en ADD COLUMN desde 10.3+)
  try { q("ALTER TABLE equipos ADD COLUMN IF NOT EXISTS ubicacion VARCHAR(50) DEFAULT NULL"); } catch(Exception $e){}
  // No rompemos tu PK: id_equipo es VARCHAR(50) y es la clave natural.
}
equipos_ensure_schema();

/** Pequeños helpers */
function __equipos_personal() {
  return qall("SELECT id_personal, CONCAT(nombre,' ',apellido_p,' ',apellido_m) AS nombre
               FROM futuro_personal
               WHERE COALESCE(estatus,'') NOT IN ('baja','inactivo')
               ORDER BY nombre ASC");
}
function __servicio_exists($id_servicio) {
  return (bool) qone("SELECT 1 FROM servicios WHERE id_servicio=?", [(int)$id_servicio]);
}

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + BUSCADOR EN VIVO + FILTRO ESTATUS
 * =======================================================*/
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $est = trim($_GET['est'] ?? 'todos'); // disponible | asignado | mantenimiento | baja | todos

  $where = "eliminado=0";
  $params = [];

  if ($est !== '' && $est !== 'todos') {
    $where .= " AND estatus = ?";
    $params[] = $est;
  }

  if ($q !== '') {
    $like = '%'.$q.'%';
    $where .= " AND (id_equipo LIKE ? OR equipo LIKE ? OR ubicacion LIKE ?)";
    array_push($params, $like, $like, $like);
  }

  $rows = qall("
    SELECT id_equipo, equipo, estatus, COALESCE(ubicacion,'') AS ubicacion,
           DATE(updated_at) AS actualizado
    FROM equipos
    WHERE $where
    ORDER BY equipo ASC
    LIMIT 500
  ", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Equipos</h1>
    <div class="d-flex gap-2">
      <a href="?r=equipos.nuevo" class="btn btn-success btn-sm">Nuevo equipo</a>
    </div>
  </div>

  <div class="row g-2 align-items-center mb-2">
    <div class="col-md-5">
      <input id="eqSearch" type="search" class="form-control" placeholder="Buscar por código, nombre o ubicación..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
      <select id="eqEst" class="form-select">
        <?php
          $opts = ['todos'=>'Todos','disponible'=>'Disponible','asignado'=>'Asignado','mantenimiento'=>'Mantenimiento','baja'=>'Baja'];
          foreach($opts as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $est===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 text-md-end small text-muted">
      <?= count($rows) ?> resultado<?= count($rows)===1?'':'s' ?>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead>
        <tr>
          <th>Código</th>
          <th>Equipo</th>
          <th>Estatus</th>
          <th>Ubicación</th>
          <th>Actualizado</th>
          <th style="width:260px">Herramientas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><code><?= e($r['id_equipo']) ?></code></td>
          <td><?= e($r['equipo']) ?></td>
          <td>
            <?php
              $badge = 'bg-secondary';
              if ($r['estatus']==='disponible') $badge='bg-success';
              elseif ($r['estatus']==='asignado') $badge='bg-warning';
              elseif ($r['estatus']==='mantenimiento') $badge='bg-info';
              elseif ($r['estatus']==='baja') $badge='bg-dark';
            ?>
            <span class="badge <?= $badge ?>"><?= e($r['estatus']) ?></span>
          </td>
          <td><?= e($r['ubicacion'] ?: '—') ?></td>
          <td><?= e($r['actualizado']) ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="?r=equipos.editar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Editar</a>
            <a class="btn btn-outline-secondary btn-sm" href="?r=equipos.asignar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Asignar a servicio</a>
            <a class="btn btn-outline-success btn-sm" href="?r=equipos.retornar&id_equipo=<?= urlencode($r['id_equipo']) ?>">Retornar</a>
            <a class="btn btn-outline-danger btn-sm"
               href="?r=equipos.baja&id_equipo=<?= urlencode($r['id_equipo']) ?>&q=<?= urlencode($q) ?>&est=<?= urlencode($est) ?>"
               onclick="return confirm('¿Dar de baja lógica este equipo?');">Borrar</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    (function(){
      const qEl = document.getElementById('eqSearch');
      const eEl = document.getElementById('eqEst');
      let t=null;
      function go(){
        const q = qEl.value.trim();
        const est = eEl.value;
        let url = "?r=equipos.listar";
        if (est) url += "&est="+encodeURIComponent(est);
        if (q)   url += "&q="+encodeURIComponent(q);
        window.location = url;
      }
      qEl.addEventListener('keyup', function(ev){
        if (ev.key==='Enter'){ go(); return; }
        clearTimeout(t); t=setTimeout(go, 350);
      });
      qEl.addEventListener('keydown', function(ev){
        if (ev.key==='Escape'){ qEl.value=''; go(); }
      });
      eEl.addEventListener('change', go);
    })();
  </script>
  <?php
  render('Equipos', ob_get_clean());
break;

/* =========================================================
 * NUEVO
 * =======================================================*/
case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo equipo</h1>
      <form method="post" action="?r=equipos.guardar">
        <?= form_input('id_equipo','Código / Placa *','', ['required'=>true, 'maxlength'=>50]) ?>
        <?= form_input('equipo','Nombre del equipo *','', ['required'=>true, 'maxlength'=>50]) ?>
        <div class="row g-2">
          <div class="col-md-6">
            <?= form_select('estatus','Estatus',[
              'disponible'=>'Disponible',
              'asignado'=>'Asignado',
              'mantenimiento'=>'Mantenimiento',
              'baja'=>'Baja'
            ], 'disponible') ?>
          </div>
          <div class="col-md-6"><?= form_input('ubicacion','Ubicación','') ?></div>
        </div>
        <?= form_input('foto','Foto (ruta/archivo opcional)','') ?>
        <button class="btn btn-primary mt-2">Guardar</button>
        <a class="btn btn-light mt-2" href="?r=equipos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo equipo', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (POST)
 * =======================================================*/
case 'guardar':
  $id   = trim($_POST['id_equipo'] ?? '');
  $nom  = trim($_POST['equipo'] ?? '');
  $est  = trim($_POST['estatus'] ?? 'disponible');
  $ubi  = trim($_POST['ubicacion'] ?? '');
  $foto = trim($_POST['foto'] ?? '');

  if ($id==='' || $nom==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Código y nombre son obligatorios.</div>";
    redirect('equipos.nuevo');
  }

  $dup = qone("SELECT 1 FROM equipos WHERE id_equipo=?", [$id]);
  if ($dup) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Ya existe un equipo con ese código.</div>";
    redirect('equipos.nuevo');
  }

  q("INSERT INTO equipos (id_equipo, equipo, estatus, foto, ubicacion, updated_at, eliminado)
     VALUES (?, ?, ?, ?, ?, CURRENT_DATE(), 0)", [$id, $nom, $est, ($foto?:null), ($ubi?:null)]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Equipo creado.</div>";
  redirect('equipos.listar');
break;

/* =========================================================
 * EDITAR
 * =======================================================*/
case 'editar':
  $id = trim($_GET['id_equipo'] ?? '');
  $r  = qone("SELECT * FROM equipos WHERE id_equipo=? AND eliminado=0", [$id]);
  if (!$r) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Equipo no encontrado.</div>";
    redirect('equipos.listar');
  }

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar equipo <code><?= e($r['id_equipo']) ?></code></h1>
      <form method="post" action="?r=equipos.actualizar&id_equipo=<?= urlencode($r['id_equipo']) ?>">
        <?= form_input('equipo','Nombre del equipo *',$r['equipo'], ['required'=>true]) ?>
        <div class="row g-2">
          <div class="col-md-6">
            <?= form_select('estatus','Estatus',[
              'disponible'=>'Disponible',
              'asignado'=>'Asignado',
              'mantenimiento'=>'Mantenimiento',
              'baja'=>'Baja'
            ], $r['estatus']) ?>
          </div>
          <div class="col-md-6"><?= form_input('ubicacion','Ubicación',$r['ubicacion'] ?? '') ?></div>
        </div>
        <?= form_input('foto','Foto (ruta/archivo)',$r['foto'] ?? '') ?>
        <button class="btn btn-primary mt-2">Actualizar</button>
        <a class="btn btn-light mt-2" href="?r=equipos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Editar equipo', ob_get_clean());
break;

/* =========================================================
 * ACTUALIZAR (POST)
 * =======================================================*/
case 'actualizar':
  $id  = trim($_GET['id_equipo'] ?? '');
  $nom = trim($_POST['equipo'] ?? '');
  $est = trim($_POST['estatus'] ?? 'disponible');
  $ubi = trim($_POST['ubicacion'] ?? '');
  $foto= trim($_POST['foto'] ?? '');

  if ($id==='' || $nom==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('equipos.listar');
  }

  q("UPDATE equipos
     SET equipo=?, estatus=?, ubicacion=?, foto=?, updated_at=CURRENT_DATE()
     WHERE id_equipo=? AND eliminado=0",
     [$nom, $est, ($ubi?:null), ($foto?:null), $id]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Equipo actualizado.</div>";
  redirect('equipos.listar');
break;

/* =========================================================
 * ASIGNAR A SERVICIO (GET form)
 * =======================================================*/
case 'asignar':
  $id = trim($_GET['id_equipo'] ?? '');
  $r  = qone("SELECT id_equipo, equipo, estatus FROM equipos WHERE id_equipo=? AND eliminado=0", [$id]);
  if (!$r) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Equipo no encontrado.</div>";
    redirect('equipos.listar');
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Asignar equipo <code><?= e($r['id_equipo']) ?></code> a servicio</h1>
      <?php if ($r['estatus']==='asignado'): ?>
        <div class="alert alert-warning">Este equipo ya está marcado como asignado.</div>
      <?php endif; ?>
      <form method="post" action="?r=equipos.guardar_asignacion&id_equipo=<?= urlencode($r['id_equipo']) ?>">
        <?= form_input('id_servicio','Servicio #','', ['type'=>'number','min'=>'1','required'=>true]) ?>
        <?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?>
        <button class="btn btn-primary">Guardar asignación</button>
        <a class="btn btn-light" href="?r=equipos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Asignar equipo', ob_get_clean());
break;

/* =========================================================
 * GUARDAR ASIGNACIÓN (POST)
 * =======================================================*/
case 'guardar_asignacion':
  $id_eq = trim($_GET['id_equipo'] ?? '');
  $id_serv = (int)($_POST['id_servicio'] ?? 0);
  $fecha   = trim($_POST['fecha'] ?? date('Y-m-d'));

  if ($id_eq==='' || $id_serv<=0) {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('equipos.listar');
  }
  if (!__servicio_exists($id_serv)) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Servicio no existe.</div>";
    redirect('equipos.asignar&id_equipo='.$id_eq);
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();

    // registra vínculo equipo-servicio
    q("INSERT INTO servicio_equipo (id_servicio, id_equipo, fecha) VALUES (?, ?, ?)", [$id_serv, $id_eq, $fecha]);

    // marca equipo como asignado
    q("UPDATE equipos SET estatus='asignado', updated_at=CURRENT_DATE() WHERE id_equipo=? AND eliminado=0", [$id_eq]);

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Equipo asignado al servicio #$id_serv.</div>";
    redirect('equipos.listar');
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo asignar el equipo.</div>";
    redirect('equipos.asignar&id_equipo='.$id_eq);
  }
break;

/* =========================================================
 * RETORNAR (GET form) — marcar regreso al inventario
 * =======================================================*/
case 'retornar':
  $id = trim($_GET['id_equipo'] ?? '');
  $r  = qone("SELECT id_equipo, equipo, estatus FROM equipos WHERE id_equipo=? AND eliminado=0", [$id]);
  if (!$r) { $_SESSION['_alerts'] = "<div class='alert alert-warning'>Equipo no encontrado.</div>"; redirect('equipos.listar'); }

  $personas = __equipos_personal();

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Retornar equipo <code><?= e($r['id_equipo']) ?></code> al inventario</h1>
      <form method="post" action="?r=equipos.guardar_retorno&id_equipo=<?= urlencode($r['id_equipo']) ?>">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Responsable</label>
            <input class="form-control" name="responsable" placeholder="Nombre responsable" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Auxiliar</label>
            <select name="auxiliar" class="form-select" required>
              <option value="">— Selecciona auxiliar —</option>
              <?php foreach($personas as $p): ?>
                <option value="<?= e($p['nombre']) ?>"><?= e($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?>
          </div>
        </div>
        <?= form_input('notas','Notas (opcional)') ?>
        <div class="d-grid mt-2">
          <button class="btn btn-success">Guardar retorno</button>
        </div>
        <a class="btn btn-light mt-2" href="?r=equipos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Retornar equipo', ob_get_clean());
break;

/* =========================================================
 * GUARDAR RETORNO (POST)
 * Crea registro en 'entrada' y enlaza en 'equipo_entrada' y 'servicio_equipo_entrada'
 * =======================================================*/
case 'guardar_retorno':
  $id_eq = trim($_GET['id_equipo'] ?? '');
  $resp  = trim($_POST['responsable'] ?? '');
  $aux   = trim($_POST['auxiliar'] ?? '');
  $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
  $notas = trim($_POST['notas'] ?? '');

  if ($id_eq==='' || $resp==='' || $aux==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Responsable y Auxiliar son obligatorios.</div>";
    redirect('equipos.retornar&id_equipo='.$id_eq);
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();

    // 1) Crear una 'entrada' general
    q("INSERT INTO entrada (responsable, auxiliar, notas, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP())",
      [$resp, $aux, ($notas?:'Retorno de equipo '.$id_eq)]);
    $id_ent = $pdo->lastInsertId();

    // 2) Enlazar el equipo a la entrada (bitácora)
    q("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES (?, ?, ?)", [$id_eq, $id_ent, $fecha]);

    // 3) Registrar retorno en servicio_equipo_entrada (traza por auxiliar)
    q("INSERT INTO servicio_equipo_entrada (id_equipo, auxiliar, created_at) VALUES (?, ?, CURRENT_TIMESTAMP())", [$id_eq, $aux]);

    // 4) Marcar equipo disponible de nuevo
    q("UPDATE equipos SET estatus='disponible', updated_at=CURRENT_DATE() WHERE id_equipo=? AND eliminado=0", [$id_eq]);

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Retorno registrado. Equipo disponible.</div>";
    redirect('equipos.listar');
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo registrar el retorno.</div>";
    redirect('equipos.retornar&id_equipo='.$id_eq);
  }
break;

/* =========================================================
 * BAJA LÓGICA
 * =======================================================*/
case 'baja':
  $id = trim($_GET['id_equipo'] ?? '');
  if ($id!=='') {
    q("UPDATE equipos SET eliminado=1, updated_at=CURRENT_DATE() WHERE id_equipo=?", [$id]);
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Equipo dado de baja.</div>";
  }
  $qParam  = isset($_GET['q'])  ? '&q='.urlencode($_GET['q'])   : '';
  $eParam  = isset($_GET['est'])? '&est='.urlencode($_GET['est']): '';
  redirect('equipos.listar'.$qParam.$eParam);
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('equipos.listar');
}
