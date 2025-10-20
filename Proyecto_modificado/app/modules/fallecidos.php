<?php
// app/modules/fallecidos.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

/**
 * Auto-migración suave:
 * - Agrega columna 'eliminado' si no existe.
 */
function fallecidos_ensure_columns() {
  try { q("ALTER TABLE fallecido ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e){}
}
fallecidos_ensure_columns();

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + BUSCADOR (en vivo) + filtros simples
 * =======================================================*/
case 'listar':
  $q  = trim($_GET['q'] ?? '');   // nombre, municipio, hospital, domicilio
  $f1 = trim($_GET['f1'] ?? '');  // desde (YYYY-MM-DD) por fecha
  $f2 = trim($_GET['f2'] ?? '');  // hasta

  $where  = "f.eliminado=0";
  $params = [];

  if ($q !== '') {
    if (ctype_digit($q)) {
      $where .= " AND (f.id_fallecido = ? OR f.nom_fallecido LIKE ? OR f.municipio LIKE ? OR f.hospital LIKE ? OR f.dom_velacion LIKE ?)";
      $params[] = (int)$q;
      $like = '%'.$q.'%'; array_push($params,$like,$like,$like,$like);
    } else {
      $where .= " AND (f.nom_fallecido LIKE ? OR f.municipio LIKE ? OR f.hospital LIKE ? OR f.dom_velacion LIKE ?)";
      $like = '%'.$q.'%'; array_push($params,$like,$like,$like,$like);
    }
  }
  if ($f1 !== '') { $where .= " AND f.fecha >= ?"; $params[] = $f1; }
  if ($f2 !== '') { $where .= " AND f.fecha <= ?"; $params[] = $f2; }

  $rows = qall("
    SELECT f.id_fallecido, f.nom_fallecido, f.municipio, f.hospital, f.dom_velacion, DATE(f.fecha) AS fecha,
           (SELECT COUNT(*) FROM servicio_fallecido sf WHERE sf.id_fallecido=f.id_fallecido) AS servicios_vinc
    FROM fallecido f
    WHERE $where
    ORDER BY f.id_fallecido DESC
    LIMIT 500
  ", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Fallecidos</h1>
    <div class="d-flex gap-2">
      <a href="?r=fallecidos.nuevo" class="btn btn-success btn-sm">Nuevo fallecido</a>
    </div>
  </div>

  <div class="row g-2 align-items-center mb-2">
    <div class="col-md-5">
      <input id="falSearch" type="search" class="form-control" placeholder="Buscar por ID, nombre, municipio, hospital o domicilio..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
      <input id="falF1" type="date" class="form-control" value="<?= e($f1) ?>" placeholder="Desde">
    </div>
    <div class="col-md-3">
      <input id="falF2" type="date" class="form-control" value="<?= e($f2) ?>" placeholder="Hasta">
    </div>
    <div class="col-md-1 text-md-end small text-muted">
      <?= count($rows) ?> resultado<?= count($rows)===1?'':'s' ?>
    </div>
  </div>

  <table class="table table-striped table-hover table-sm">
    <thead>
      <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Municipio</th>
        <th>Hospital</th>
        <th>Velación</th>
        <th>Fecha</th>
        <th class="text-center" style="width:160px">Herramientas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= $r['id_fallecido'] ?></td>
        <td><?= e($r['nom_fallecido']) ?></td>
        <td><?= e($r['municipio']) ?></td>
        <td><?= e($r['hospital']) ?></td>
        <td><?= e($r['dom_velacion']) ?></td>
        <td><?= e($r['fecha']) ?></td>
        <td class="text-center">
          <div class="btn-group">
            <a class="btn btn-outline-primary btn-sm" href="?r=fallecidos.editar&id=<?= $r['id_fallecido'] ?>">Editar</a>
            <?php if ((int)$r['servicios_vinc'] === 0): ?>
              <a class="btn btn-outline-danger btn-sm"
                 href="?r=fallecidos.baja&id=<?= $r['id_fallecido'] ?>&q=<?= urlencode($q) ?>&f1=<?= urlencode($f1) ?>&f2=<?= urlencode($f2) ?>"
                 onclick="return confirm('¿Dar de baja este registro? No podrá usarse en nuevos servicios.');">
                 Borrar
              </a>
            <?php else: ?>
              <button class="btn btn-outline-secondary btn-sm" disabled title="Vinculado a servicios">Borrar</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted">Sin registros</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    (function(){
      const qEl = document.getElementById('falSearch');
      const f1El = document.getElementById('falF1');
      const f2El = document.getElementById('falF2');
      let t=null;
      function go(){
        const q = qEl.value.trim();
        const f1 = f1El.value.trim();
        const f2 = f2El.value.trim();
        let url = "?r=fallecidos.listar";
        if (q)  url += "&q="+encodeURIComponent(q);
        if (f1) url += "&f1="+encodeURIComponent(f1);
        if (f2) url += "&f2="+encodeURIComponent(f2);
        window.location = url;
      }
      qEl.addEventListener('keyup', function(ev){
        if (ev.key==='Enter'){ go(); return; }
        clearTimeout(t); t=setTimeout(go, 400);
      });
      qEl.addEventListener('keydown', function(ev){
        if (ev.key==='Escape'){ qEl.value=''; go(); }
      });
      f1El.addEventListener('change', go);
      f2El.addEventListener('change', go);
    })();
  </script>
  <?php
  render('Fallecidos', ob_get_clean());
break;

/* =========================================================
 * NUEVO (formulario)
 * =======================================================*/
case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo fallecido</h1>
      <form method="post" action="?r=fallecidos.guardar">
        <?= form_input('nom_fallecido','Nombre del fallecido *','', ['required'=>true]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('municipio','Municipio *','', ['required'=>true]) ?></div>
          <div class="col-md-6"><?= form_input('hospital','Hospital (opcional)') ?></div>
        </div>
        <?= form_input('dom_velacion','Domicilio de velación *','', ['required'=>true]) ?>
        <?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?>
        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-light" href="?r=fallecidos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo fallecido', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (POST)
 * =======================================================*/
case 'guardar':
  $nom  = trim($_POST['nom_fallecido'] ?? '');
  $mun  = trim($_POST['municipio'] ?? '');
  $hos  = trim($_POST['hospital'] ?? '');
  $dom  = trim($_POST['dom_velacion'] ?? '');
  $fec  = trim($_POST['fecha'] ?? date('Y-m-d'));

  if ($nom==='' || $mun==='' || $dom==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Nombre, Municipio y Domicilio de velación son obligatorios.</div>";
    redirect('fallecidos.nuevo');
  }

  q("INSERT INTO fallecido (nom_fallecido, dom_velacion, hospital, municipio, fecha, eliminado)
     VALUES (?, ?, ?, ?, ?, 0)",
    [$nom, $dom, ($hos?:null), $mun, $fec]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Fallecido registrado.</div>";
  redirect('fallecidos.listar');
break;

/* =========================================================
 * EDITAR (formulario)
 * =======================================================*/
case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $r  = qone("SELECT * FROM fallecido WHERE id_fallecido=? AND eliminado=0", [$id]);
  if (!$r) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Registro no encontrado.</div>";
    redirect('fallecidos.listar');
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar fallecido #<?= $r['id_fallecido'] ?></h1>
      <form method="post" action="?r=fallecidos.actualizar&id=<?= $r['id_fallecido'] ?>">
        <?= form_input('nom_fallecido','Nombre del fallecido *',$r['nom_fallecido'], ['required'=>true]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('municipio','Municipio *',$r['municipio'], ['required'=>true]) ?></div>
          <div class="col-md-6"><?= form_input('hospital','Hospital (opcional)',$r['hospital'] ?? '') ?></div>
        </div>
        <?= form_input('dom_velacion','Domicilio de velación *',$r['dom_velacion'], ['required'=>true]) ?>
        <?= form_input('fecha','Fecha', $r['fecha'], ['type'=>'date','required'=>true]) ?>
        <button class="btn btn-primary">Actualizar</button>
        <a class="btn btn-light" href="?r=fallecidos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Editar fallecido', ob_get_clean());
break;

/* =========================================================
 * ACTUALIZAR (POST)
 * =======================================================*/
case 'actualizar':
  $id  = (int)($_GET['id'] ?? 0);
  $nom = trim($_POST['nom_fallecido'] ?? '');
  $mun = trim($_POST['municipio'] ?? '');
  $hos = trim($_POST['hospital'] ?? '');
  $dom = trim($_POST['dom_velacion'] ?? '');
  $fec = trim($_POST['fecha'] ?? date('Y-m-d'));

  if ($id<=0 || $nom==='' || $mun==='' || $dom==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('fallecidos.listar');
  }

  q("UPDATE fallecido
     SET nom_fallecido=?, municipio=?, hospital=?, dom_velacion=?, fecha=?
     WHERE id_fallecido=? AND eliminado=0",
     [$nom,$mun,($hos?:null),$dom,$fec,$id]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Registro actualizado.</div>";
  redirect('fallecidos.listar');
break;

/* =========================================================
 * BAJA (borrado lógico) — bloquea si está vinculado
 * =======================================================*/
case 'baja':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    $vinc = qone("SELECT COUNT(*) AS n FROM servicio_fallecido WHERE id_fallecido=?", [$id]);
    if ((int)$vinc['n'] > 0) {
      $_SESSION['_alerts'] = "<div class='alert alert-warning'>No se puede dar de baja: el registro está vinculado a servicios.</div>";
      $qParam  = isset($_GET['q'])  ? '&q='.urlencode($_GET['q'])   : '';
      $f1Param = isset($_GET['f1']) ? '&f1='.urlencode($_GET['f1']) : '';
      $f2Param = isset($_GET['f2']) ? '&f2='.urlencode($_GET['f2']) : '';
      redirect('fallecidos.listar'.$qParam.$f1Param.$f2Param);
    } else {
      q("UPDATE fallecido SET eliminado=1 WHERE id_fallecido=?", [$id]);
      $_SESSION['_alerts'] = "<div class='alert alert-success'>Registro dado de baja.</div>";
    }
  }
  $qParam  = isset($_GET['q'])  ? '&q='.urlencode($_GET['q'])   : '';
  $f1Param = isset($_GET['f1']) ? '&f1='.urlencode($_GET['f1']) : '';
  $f2Param = isset($_GET['f2']) ? '&f2='.urlencode($_GET['f2']) : '';
  redirect('fallecidos.listar'.$qParam.$f1Param.$f2Param);
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('fallecidos.listar');
}
