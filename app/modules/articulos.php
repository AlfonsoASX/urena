<?php
// app/modules/articulos.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

/**
 * Auto-migración suave para corregir “error al dar de baja”.
 * Agrega columna articulos.eliminado si no existe.
 */
function articulos_ensure_columns() {
  try {
    q("ALTER TABLE articulos ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0");
  } catch (Exception $e) { /* ignorar */ }
}
articulos_ensure_columns();

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + BUSCADOR (en vivo) + Acciones
 * =======================================================*/
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $params = [];
  $where = "eliminado=0";
  if ($q !== '') {
    if (ctype_digit($q)) {
      // Búsqueda por ID exacto si es numérico
      $where .= " AND (id = ? OR articulo LIKE ? OR marca LIKE ?)";
      $params[] = (int)$q;
      $params[] = '%'.$q.'%';
      $params[] = '%'.$q.'%';
    } else {
      $where .= " AND (articulo LIKE ? OR marca LIKE ?)";
      $params[] = '%'.$q.'%';
      $params[] = '%'.$q.'%';
    }
  }

  $rows = qall("SELECT id, articulo, marca, existencias, DATE(updated_at) AS fecha
                FROM articulos
                WHERE $where
                ORDER BY id DESC
                LIMIT 500", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Artículos</h1>
    <div class="d-flex gap-2">
      <a href="?r=articulos.nuevo" class="btn btn-success btn-sm">Nuevo artículo</a>
    </div>
  </div>

  <div class="row g-2 align-items-center mb-2">
    <div class="col-md-6">
      <input id="artSearch" type="search" class="form-control" placeholder="Buscar por ID, artículo o marca..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-6 text-md-end small text-muted">
      <?= count($rows) ?> resultado<?= count($rows)===1?'':'s' ?>
    </div>
  </div>

  <table class="table table-striped table-hover table-sm">
    <thead>
      <tr>
        <th>ID</th>
        <th>Artículo</th>
        <th>Marca</th>
        <th class="text-end">Existencias</th>
        <th>Actualización</th>
        <th style="width:180px">Herramientas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= e($r['articulo']) ?></td>
        <td><?= e($r['marca']) ?></td>
        <td class="text-end"><?= (int)$r['existencias'] ?></td>
        <td><?= e($r['fecha']) ?></td>
        <td class="d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm" href="?r=articulos.editar&id=<?= $r['id'] ?>">Editar</a>
          <a class="btn btn-outline-danger btn-sm"
             href="?r=articulos.baja&id=<?= $r['id'] ?>&q=<?= urlencode($q) ?>"
             onclick="return confirm('¿Seguro que deseas dar de baja este artículo? Ya no aparecerá en la lista.');">
             Borrar
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="6" class="text-center text-muted">Sin registros</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    (function(){
      const input = document.getElementById('artSearch');
      if(!input) return;
      let t=null;
      function go(q){
        const base = "?r=articulos.listar";
        const url  = q ? base + "&q=" + encodeURIComponent(q) : base;
        window.location = url;
      }
      input.addEventListener('keyup', function(ev){
        if (ev.key === 'Enter') { go(input.value.trim()); return; }
        clearTimeout(t);
        t = setTimeout(function(){ go(input.value.trim()); }, 350); // debounce
      });
      input.addEventListener('keydown', function(ev){
        if(ev.key === 'Escape'){ input.value=''; go(''); }
      });
    })();
  </script>
  <?php
  render('Artículos', ob_get_clean());
break;

/* =========================================================
 * NUEVO (formulario)
 * =======================================================*/
case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo artículo</h1>
      <form method="post" action="?r=articulos.guardar" enctype="multipart/form-data">
        <?= form_input('articulo','Artículo','', ['required'=>true]) ?>
        <?= form_input('marca','Marca') ?>
        <?= form_input('existencias','Existencias',0,['type'=>'number']) ?>
        <?= form_input('foto','Foto (nombre de archivo en /storage/uploads)','', ['type'=>'text']) ?>
        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-light" href="?r=articulos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo artículo', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (POST)
 * =======================================================*/
case 'guardar':
  $art  = trim($_POST['articulo'] ?? '');
  $marca= trim($_POST['marca'] ?? '');
  $exist= (int)($_POST['existencias'] ?? 0);
  $foto = trim($_POST['foto'] ?? null);

  if ($art==='') {
    $_SESSION['_alerts']="<div class='alert alert-danger'>El campo Artículo es obligatorio.</div>";
    redirect('articulos.nuevo');
  }
  if ($exist < 0) $exist = 0;

  q("INSERT INTO articulos (articulo, marca, existencias, foto, updated_at, eliminado)
     VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP(), 0)",
     [$art, $marca, $exist, $foto ?: null]);

  $_SESSION['_alerts']="<div class='alert alert-success'>Artículo guardado correctamente.</div>";
  redirect('articulos.listar');
break;

/* =========================================================
 * EDITAR (formulario)
 * =======================================================*/
case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $r  = qone("SELECT * FROM articulos WHERE id=? AND eliminado=0", [$id]);
  if (!$r) {
    $_SESSION['_alerts']="<div class='alert alert-warning'>Artículo no encontrado.</div>";
    redirect('articulos.listar');
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar artículo #<?= $r['id'] ?></h1>
      <form method="post" action="?r=articulos.actualizar&id=<?= $r['id'] ?>">
        <?= form_input('articulo','Artículo',$r['articulo'], ['required'=>true]) ?>
        <?= form_input('marca','Marca',$r['marca']) ?>
        <?= form_input('existencias','Existencias',$r['existencias'],['type'=>'number']) ?>
        <?= form_input('foto','Foto (nombre de archivo en /storage/uploads)',$r['foto'] ?? '', ['type'=>'text']) ?>
        <button class="btn btn-primary">Actualizar</button>
        <a class="btn btn-light" href="?r=articulos.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Editar artículo', ob_get_clean());
break;

/* =========================================================
 * ACTUALIZAR (POST)
 * =======================================================*/
case 'actualizar':
  $id   = (int)($_GET['id'] ?? 0);
  $art  = trim($_POST['articulo'] ?? '');
  $marca= trim($_POST['marca'] ?? '');
  $exist= (int)($_POST['existencias'] ?? 0);
  $foto = trim($_POST['foto'] ?? null);

  if ($id<=0 || $art==='') {
    $_SESSION['_alerts']="<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('articulos.listar');
  }
  if ($exist < 0) $exist = 0;

  q("UPDATE articulos
     SET articulo=?, marca=?, existencias=?, foto=?, updated_at=CURRENT_TIMESTAMP()
     WHERE id=? AND eliminado=0",
     [$art,$marca,$exist,($foto?:null),$id]);

  $_SESSION['_alerts']="<div class='alert alert-success'>Artículo actualizado.</div>";
  redirect('articulos.listar');
break;

/* =========================================================
 * BAJA (borrado lógico)
 * =======================================================*/
case 'baja':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    q("UPDATE articulos SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE id=?", [$id]);
    $_SESSION['_alerts']="<div class='alert alert-success'>Artículo dado de baja.</div>";
  }
  $qParam = isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '';
  redirect('articulos.listar'.$qParam);
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('articulos.listar');
}
