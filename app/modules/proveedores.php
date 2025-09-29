<?php
// app/modules/proveedores.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

/**
 * Auto-migración suave:
 * - Crea tabla proveedores si no existe.
 * - Agrega columnas comunes si faltan.
 */
function proveedores_ensure_schema() {
  // Crea tabla si no existe
  q("
    CREATE TABLE IF NOT EXISTS proveedores (
      id_proveedor INT(11) NOT NULL AUTO_INCREMENT,
      nombre VARCHAR(100) NOT NULL,
      rfc VARCHAR(20) DEFAULT NULL,
      contacto VARCHAR(100) DEFAULT NULL,
      telefono VARCHAR(20) DEFAULT NULL,
      email VARCHAR(100) DEFAULT NULL,
      direccion VARCHAR(255) DEFAULT NULL,
      notas VARCHAR(255) DEFAULT NULL,
      estatus VARCHAR(10) NOT NULL DEFAULT 'activo',
      eliminado TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id_proveedor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
  ");

  // Refuerzos idempotentes (por si la tabla existía con menos columnas)
  $adds = [
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS rfc VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS contacto VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS direccion VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS notas VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS estatus VARCHAR(10) NOT NULL DEFAULT 'activo'",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ];
  foreach ($adds as $sql) { try { q($sql); } catch (Exception $e) {} }
}
proveedores_ensure_schema();

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + BUSCADOR (en vivo) + filtro de estatus
 * =======================================================*/
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $estatus = trim($_GET['estatus'] ?? 'activos'); // activos | inactivos | todos

  $where = "eliminado=0";
  $params = [];

  if ($estatus === 'activos') {
    $where .= " AND estatus='activo'";
  } elseif ($estatus === 'inactivos') {
    $where .= " AND estatus='inactivo'";
  }

  if ($q !== '') {
    $like = '%'.$q.'%';
    $where .= " AND (nombre LIKE ? OR rfc LIKE ? OR contacto LIKE ? OR telefono LIKE ? OR email LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
  }

  $rows = qall("
    SELECT id_proveedor, nombre, rfc, contacto, telefono, email, estatus,
           DATE(updated_at) AS actualizado
    FROM proveedores
    WHERE $where
    ORDER BY nombre ASC
    LIMIT 500
  ", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Proveedores</h1>
    <div class="d-flex gap-2">
      <a href="?r=proveedores.nuevo" class="btn btn-success btn-sm">Nuevo proveedor</a>
    </div>
  </div>

  <div class="row g-2 align-items-center mb-2">
    <div class="col-md-5">
      <input id="provSearch" type="search" class="form-control" placeholder="Buscar por nombre, RFC, contacto, teléfono o email..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
      <select id="provEstatus" class="form-select">
        <option value="activos"   <?= $estatus==='activos'?'selected':'' ?>>Activos</option>
        <option value="inactivos" <?= $estatus==='inactivos'?'selected':'' ?>>Inactivos</option>
        <option value="todos"     <?= $estatus==='todos'?'selected':'' ?>>Todos</option>
      </select>
    </div>
    <div class="col-md-3 text-md-end small text-muted">
      <?= count($rows) ?> resultado<?= count($rows)===1?'':'s' ?>
    </div>
  </div>

  <table class="table table-striped table-hover table-sm">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>RFC</th>
        <th>Contacto</th>
        <th>Teléfono</th>
        <th>Email</th>
        <th>Estatus</th>
        <th>Actualizado</th>
        <th style="width:230px">Herramientas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= e($r['nombre']) ?></td>
        <td><?= e($r['rfc'] ?? '—') ?></td>
        <td><?= e($r['contacto'] ?? '—') ?></td>
        <td><?= e($r['telefono'] ?? '—') ?></td>
        <td><?= e($r['email'] ?? '—') ?></td>
        <td>
          <span class="badge <?= $r['estatus']==='activo' ? 'bg-success' : 'bg-secondary' ?>">
            <?= e($r['estatus']) ?>
          </span>
        </td>
        <td><?= e($r['actualizado']) ?></td>
        <td class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-primary btn-sm" href="?r=proveedores.editar&id=<?= (int)$r['id_proveedor'] ?>">Editar</a>
          <?php if ($r['estatus']==='activo'): ?>
            <a class="btn btn-outline-warning btn-sm"
               href="?r=proveedores.inactivar&id=<?= (int)$r['id_proveedor'] ?>&q=<?= urlencode($q) ?>&estatus=<?= urlencode($estatus) ?>"
               onclick="return confirm('¿Marcar proveedor como INACTIVO?');">
               Inactivar
            </a>
          <?php else: ?>
            <a class="btn btn-outline-success btn-sm"
               href="?r=proveedores.activar&id=<?= (int)$r['id_proveedor'] ?>&q=<?= urlencode($q) ?>&estatus=<?= urlencode($estatus) ?>"
               onclick="return confirm('¿Reactivar proveedor (ACTIVO)?');">
               Activar
            </a>
          <?php endif; ?>
          <a class="btn btn-outline-danger btn-sm"
             href="?r=proveedores.baja&id=<?= (int)$r['id_proveedor'] ?>&q=<?= urlencode($q) ?>&estatus=<?= urlencode($estatus) ?>"
             onclick="return confirm('¿Dar de baja (borrado lógico) este proveedor?');">
             Borrar
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    (function(){
      const qEl = document.getElementById('provSearch');
      const sEl = document.getElementById('provEstatus');
      let t=null;
      function go(){
        const q = qEl.value.trim();
        const est = sEl.value;
        let url = "?r=proveedores.listar";
        if (est) url += "&estatus="+encodeURIComponent(est);
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
      sEl.addEventListener('change', go);
    })();
  </script>
  <?php
  render('Proveedores', ob_get_clean());
break;

/* =========================================================
 * NUEVO (formulario)
 * =======================================================*/
case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo proveedor</h1>
      <form method="post" action="?r=proveedores.guardar">
        <?= form_input('nombre','Nombre comercial *','', ['required'=>true]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('rfc','RFC') ?></div>
          <div class="col-md-6"><?= form_input('contacto','Contacto') ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('telefono','Teléfono') ?></div>
          <div class="col-md-6"><?= form_input('email','Email') ?></div>
        </div>
        <?= form_input('direccion','Dirección') ?>
        <?= form_input('notas','Notas') ?>
        <div class="row g-2">
          <div class="col-md-4">
            <?= form_select('estatus','Estatus', ['activo'=>'Activo','inactivo'=>'Inactivo'],'activo') ?>
          </div>
        </div>
        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-light" href="?r=proveedores.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo proveedor', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (POST)
 * =======================================================*/
case 'guardar':
  $nombre   = trim($_POST['nombre'] ?? '');
  $rfc      = trim($_POST['rfc'] ?? '');
  $contacto = trim($_POST['contacto'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $direccion= trim($_POST['direccion'] ?? '');
  $notas    = trim($_POST['notas'] ?? '');
  $estatus  = trim($_POST['estatus'] ?? 'activo');

  if ($nombre==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>El nombre del proveedor es obligatorio.</div>";
    redirect('proveedores.nuevo');
  }

  // Unicidad simple por nombre (si necesitas, añade índice único)
  $dup = qone("SELECT 1 FROM proveedores WHERE eliminado=0 AND nombre=? LIMIT 1", [$nombre]);
  if ($dup) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Ya existe un proveedor con ese nombre.</div>";
    redirect('proveedores.nuevo');
  }

  q("INSERT INTO proveedores (nombre, rfc, contacto, telefono, email, direccion, notas, estatus, eliminado, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
     [$nombre, ($rfc?:null), ($contacto?:null), ($telefono?:null), ($email?:null), ($direccion?:null), ($notas?:null), $estatus]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Proveedor creado.</div>";
  redirect('proveedores.listar');
break;

/* =========================================================
 * EDITAR (formulario)
 * =======================================================*/
case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $r  = qone("SELECT * FROM proveedores WHERE id_proveedor=? AND eliminado=0", [$id]);
  if (!$r) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Proveedor no encontrado.</div>";
    redirect('proveedores.listar');
  }

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar proveedor: <?= e($r['nombre']) ?></h1>
      <form method="post" action="?r=proveedores.actualizar&id=<?= (int)$r['id_proveedor'] ?>">
        <?= form_input('nombre','Nombre comercial *',$r['nombre'], ['required'=>true]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('rfc','RFC',$r['rfc'] ?? '') ?></div>
          <div class="col-md-6"><?= form_input('contacto','Contacto',$r['contacto'] ?? '') ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('telefono','Teléfono',$r['telefono'] ?? '') ?></div>
          <div class="col-md-6"><?= form_input('email','Email',$r['email'] ?? '') ?></div>
        </div>
        <?= form_input('direccion','Dirección',$r['direccion'] ?? '') ?>
        <?= form_input('notas','Notas',$r['notas'] ?? '') ?>
        <?= form_select('estatus','Estatus', ['activo'=>'Activo','inactivo'=>'Inactivo'], $r['estatus']) ?>
        <button class="btn btn-primary mt-2">Actualizar</button>
        <a class="btn btn-light mt-2" href="?r=proveedores.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Editar proveedor', ob_get_clean());
break;

/* =========================================================
 * ACTUALIZAR (POST)
 * =======================================================*/
case 'actualizar':
  $id       = (int)($_GET['id'] ?? 0);
  $nombre   = trim($_POST['nombre'] ?? '');
  $rfc      = trim($_POST['rfc'] ?? '');
  $contacto = trim($_POST['contacto'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $direccion= trim($_POST['direccion'] ?? '');
  $notas    = trim($_POST['notas'] ?? '');
  $estatus  = trim($_POST['estatus'] ?? 'activo');

  if ($id<=0 || $nombre==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('proveedores.listar');
  }

  // Verifica duplicado de nombre en otros registros
  $dup = qone("SELECT 1 FROM proveedores WHERE eliminado=0 AND nombre=? AND id_proveedor<>? LIMIT 1", [$nombre, $id]);
  if ($dup) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Otro proveedor ya usa ese nombre.</div>";
    redirect('proveedores.editar&id='.$id);
  }

  q("UPDATE proveedores
     SET nombre=?, rfc=?, contacto=?, telefono=?, email=?, direccion=?, notas=?, estatus=?, updated_at=CURRENT_TIMESTAMP
     WHERE id_proveedor=? AND eliminado=0",
     [$nombre, ($rfc?:null), ($contacto?:null), ($telefono?:null), ($email?:null), ($direccion?:null), ($notas?:null), $estatus, $id]);

  $_SESSION['_alerts'] = "<div class='alert alert-success'>Proveedor actualizado.</div>";
  redirect('proveedores.listar');
break;

/* =========================================================
 * INACTIVAR / ACTIVAR (cambio de estatus)
 * =======================================================*/
case 'inactivar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    q("UPDATE proveedores SET estatus='inactivo', updated_at=CURRENT_TIMESTAMP WHERE id_proveedor=? AND eliminado=0", [$id]);
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Proveedor inactivado.</div>";
  }
  $qParam = isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '';
  $esParam = isset($_GET['estatus']) ? '&estatus='.urlencode($_GET['estatus']) : '';
  redirect('proveedores.listar'.$qParam.$esParam);
break;

case 'activar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    q("UPDATE proveedores SET estatus='activo', updated_at=CURRENT_TIMESTAMP WHERE id_proveedor=? AND eliminado=0", [$id]);
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Proveedor activado.</div>";
  }
  $qParam = isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '';
  $esParam = isset($_GET['estatus']) ? '&estatus='.urlencode($_GET['estatus']) : '';
  redirect('proveedores.listar'.$qParam.$esParam);
break;

/* =========================================================
 * BAJA (borrado lógico)
 * =======================================================*/
case 'baja':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    q("UPDATE proveedores SET eliminado=1, updated_at=CURRENT_TIMESTAMP WHERE id_proveedor=?", [$id]);
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Proveedor dado de baja.</div>";
  }
  $qParam = isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '';
  $esParam = isset($_GET['estatus']) ? '&estatus='.urlencode($_GET['estatus']) : '';
  redirect('proveedores.listar'.$qParam.$esParam);
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('proveedores.listar');
}
