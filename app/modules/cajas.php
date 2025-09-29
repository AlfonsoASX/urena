<?php
// app/modules/cajas.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

/**
 * Auto-migración suave:
 * - Agrega columna 'eliminado' si no existe.
 */
function cajas_ensure_columns() {
  try { q("ALTER TABLE cajas ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e){}
}
cajas_ensure_columns();

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR + FILTROS + BUSCADOR (en vivo)
 * =======================================================*/
case 'listar':
  $q       = trim($_GET['q'] ?? '');
  $estadoF = trim($_GET['estado'] ?? 'todos'); // todos|nuevo|en_uso|reciclado|fuera_uso
  $where   = "eliminado=0";
  $params  = [];

  if ($estadoF !== '' && $estadoF !== 'todos') {
    $where .= " AND estado = ?";
    $params[] = $estadoF;
  }
  if ($q !== '') {
    $like = '%'.$q.'%';
    $where .= " AND (codigo LIKE ? OR modelo LIKE ? OR color LIKE ? OR proveedor LIKE ? OR ubicacion LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
  }

  $rows = qall("
    SELECT codigo, modelo, estado, ubicacion, color, proveedor, costo,
           DATE(updated_at) AS actualizado, DATE(created_at) AS creado
    FROM cajas
    WHERE $where
    ORDER BY created_at DESC, codigo DESC
    LIMIT 500
  ", $params);

  $estados = [
    'todos'     => 'Todos',
    'nuevo'     => 'Nuevo',
    'en_uso'    => 'En uso',
    'reciclado' => 'Reciclado',
    'fuera_uso' => 'Fuera de uso',
  ];

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Cajas (Ataúdes)</h1>
    <div class="d-flex gap-2">
      <a href="?r=cajas.nuevo" class="btn btn-success btn-sm">Nueva caja</a>
    </div>
  </div>

  <div class="row g-2 align-items-center mb-2">
    <div class="col-md-6">
      <input id="cxSearch" type="search" class="form-control" placeholder="Buscar por código, modelo, color, proveedor o ubicación..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
      <select id="cxEstado" class="form-select">
        <?php foreach($estados as $val=>$txt): ?>
          <option value="<?= e($val) ?>" <?= $val===$estadoF?'selected':'' ?>><?= e($txt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 text-md-end small text-muted">
      <?= count($rows) ?> resultado<?= count($rows)===1?'':'s' ?>
    </div>
  </div>

  <table class="table table-striped table-hover table-sm">
    <thead>
      <tr>
        <th>Código</th>
        <th>Modelo</th>
        <th>Estado</th>
        <th>Ubicación</th>
        <th>Color</th>
        <th>Proveedor</th>
        <th class="text-end">Costo</th>
        <th>Actualizado</th>
        <th style="width:220px">Herramientas</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><code><?= e($r['codigo']) ?></code></td>
        <td><?= e($r['modelo']) ?></td>
        <td>
          <span class="badge bg-<?=
            $r['estado']==='nuevo'?'success':(
            $r['estado']==='en_uso'?'warning':(
            $r['estado']==='reciclado'?'secondary':'dark')) ?>">
            <?= e($r['estado']) ?>
          </span>
        </td>
        <td><?= e($r['ubicacion']) ?></td>
        <td><?= e($r['color']) ?></td>
        <td><?= e($r['proveedor']) ?></td>
        <td class="text-end">$<?= number_format((float)$r['costo'],2) ?></td>
        <td><?= e($r['actualizado'] ?: $r['creado']) ?></td>
        <td class="d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm" href="?r=cajas.editar&codigo=<?= urlencode($r['codigo']) ?>">Editar</a>
          <!-- Cambiar estado rápido -->
          <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Estado</button>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php foreach (['nuevo','en_uso','reciclado','fuera_uso'] as $st): ?>
                <li>
                  <a class="dropdown-item" href="?r=cajas.estado&codigo=<?= urlencode($r['codigo']) ?>&estado=<?= $st ?>"
                     onclick="return confirm('¿Cambiar estado a <?= $st ?>?');">
                     <?= ucfirst($st) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <a class="btn btn-outline-danger btn-sm"
             href="?r=cajas.baja&codigo=<?= urlencode($r['codigo']) ?>&estadoF=<?= urlencode($estadoF) ?>&q=<?= urlencode($q) ?>"
             onclick="return confirm('¿Dar de baja (borrado lógico) esta caja?');">
             Borrar
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    (function(){
      const qEl = document.getElementById('cxSearch');
      const sEl = document.getElementById('cxEstado');
      let t=null;
      function go(){
        const q = qEl.value.trim();
        const estado = sEl.value;
        let url = "?r=cajas.listar";
        if (estado && estado!=='todos') url += "&estado="+encodeURIComponent(estado);
        if (q) url += "&q="+encodeURIComponent(q);
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
  render('Cajas', ob_get_clean());
break;

/* =========================================================
 * NUEVO (formulario)
 * =======================================================*/
case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nueva caja</h1>
      <form method="post" action="?r=cajas.guardar">
        <?= form_input('codigo','Código (único)','', ['required'=>true]) ?>
        <?= form_input('modelo','Modelo','', ['required'=>true]) ?>
        <?= form_select('estado','Estado',[
              'nuevo'=>'Nuevo','en_uso'=>'En uso','reciclado'=>'Reciclado','fuera_uso'=>'Fuera de uso'
            ], 'nuevo') ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('ubicacion','Ubicación') ?></div>
          <div class="col-md-6"><?= form_input('color','Color') ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('proveedor','Proveedor') ?></div>
          <div class="col-md-6"><?= form_input('costo','Costo','0.00',['type'=>'number']) ?></div>
        </div>
        <?= form_input('foto','Foto (nombre de archivo en /storage/uploads)') ?>
        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-light" href="?r=cajas.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Nueva caja', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (POST)
 * =======================================================*/
case 'guardar':
  $codigo = trim($_POST['codigo'] ?? '');
  $modelo = trim($_POST['modelo'] ?? '');
  $estado = trim($_POST['estado'] ?? 'nuevo');
  $ubic   = trim($_POST['ubicacion'] ?? '');
  $color  = trim($_POST['color'] ?? '');
  $prov   = trim($_POST['proveedor'] ?? '');
  $costo  = is_numeric($_POST['costo'] ?? '') ? (float)$_POST['costo'] : 0;
  $foto   = trim($_POST['foto'] ?? null);

  if ($codigo==='' || $modelo==='') {
    $_SESSION['_alerts']="<div class='alert alert-danger'>Código y Modelo son obligatorios.</div>";
    redirect('cajas.nuevo');
  }
  // Verifica unicidad de código
  $exists = qone("SELECT 1 FROM cajas WHERE codigo=? LIMIT 1", [$codigo]);
  if ($exists) {
    $_SESSION['_alerts']="<div class='alert alert-warning'>Ya existe una caja con el código <b>".e($codigo)."</b>.</div>";
    redirect('cajas.nuevo');
  }

  q("INSERT INTO cajas (codigo, modelo, estado, ubicacion, color, proveedor, costo, foto, created_at, updated_at, eliminado)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(), CURRENT_DATE(), 0)",
     [$codigo,$modelo,$estado,$ubic,$color,$prov,$costo,($foto?:null)]);

  $_SESSION['_alerts']="<div class='alert alert-success'>Caja creada.</div>";
  redirect('cajas.listar');
break;

/* =========================================================
 * EDITAR (formulario)
 * =======================================================*/
case 'editar':
  $codigo = trim($_GET['codigo'] ?? '');
  $r = qone("SELECT * FROM cajas WHERE codigo=? AND eliminado=0", [$codigo]);
  if (!$r) {
    $_SESSION['_alerts']="<div class='alert alert-warning'>Caja no encontrada.</div>";
    redirect('cajas.listar');
  }
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar caja <code><?= e($r['codigo']) ?></code></h1>
      <form method="post" action="?r=cajas.actualizar&codigo=<?= urlencode($r['codigo']) ?>">
        <?= form_input('modelo','Modelo',$r['modelo'], ['required'=>true]) ?>
        <?= form_select('estado','Estado',[
              'nuevo'=>'Nuevo','en_uso'=>'En uso','reciclado'=>'Reciclado','fuera_uso'=>'Fuera de uso'
            ], $r['estado']) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('ubicacion','Ubicación',$r['ubicacion']) ?></div>
          <div class="col-md-6"><?= form_input('color','Color',$r['color']) ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('proveedor','Proveedor',$r['proveedor']) ?></div>
          <div class="col-md-6"><?= form_input('costo','Costo',$r['costo'], ['type'=>'number']) ?></div>
        </div>
        <?= form_input('foto','Foto (nombre de archivo en /storage/uploads)',$r['foto'] ?? '', ['type'=>'text']) ?>
        <button class="btn btn-primary">Actualizar</button>
        <a class="btn btn-light" href="?r=cajas.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Editar caja', ob_get_clean());
break;

/* =========================================================
 * ACTUALIZAR (POST)
 * =======================================================*/
case 'actualizar':
  $codigo = trim($_GET['codigo'] ?? '');
  $modelo = trim($_POST['modelo'] ?? '');
  $estado = trim($_POST['estado'] ?? 'nuevo');
  $ubic   = trim($_POST['ubicacion'] ?? '');
  $color  = trim($_POST['color'] ?? '');
  $prov   = trim($_POST['proveedor'] ?? '');
  $costo  = is_numeric($_POST['costo'] ?? '') ? (float)$_POST['costo'] : 0;
  $foto   = trim($_POST['foto'] ?? null);

  if ($codigo==='' || $modelo==='') {
    $_SESSION['_alerts']="<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('cajas.listar');
  }

  q("UPDATE cajas
     SET modelo=?, estado=?, ubicacion=?, color=?, proveedor=?, costo=?, foto=?, updated_at=CURRENT_DATE()
     WHERE codigo=? AND eliminado=0",
     [$modelo,$estado,$ubic,$color,$prov,$costo,($foto?:null),$codigo]);

  $_SESSION['_alerts']="<div class='alert alert-success'>Caja actualizada.</div>";
  redirect('cajas.listar');
break;

/* =========================================================
 * CAMBIAR ESTADO (GET)
 * =======================================================*/
case 'estado':
  $codigo = trim($_GET['codigo'] ?? '');
  $estado = trim($_GET['estado'] ?? '');
  if ($codigo==='' || !in_array($estado, ['nuevo','en_uso','reciclado','fuera_uso'])) {
    $_SESSION['_alerts']="<div class='alert alert-danger'>Parámetros inválidos.</div>";
    redirect('cajas.listar');
  }
  q("UPDATE cajas SET estado=?, updated_at=CURRENT_DATE() WHERE codigo=? AND eliminado=0", [$estado,$codigo]);
  $_SESSION['_alerts']="<div class='alert alert-success'>Estado actualizado a <b>".e($estado)."</b>.</div>";
  redirect('cajas.listar');
break;

/* =========================================================
 * BAJA (borrado lógico)
 * =======================================================*/
case 'baja':
  $codigo = trim($_GET['codigo'] ?? '');
  if ($codigo!=='') {
    q("UPDATE cajas SET eliminado=1, updated_at=CURRENT_DATE() WHERE codigo=?", [$codigo]);
    $_SESSION['_alerts']="<div class='alert alert-success'>Caja dada de baja.</div>";
  }
  $estadoF = $_GET['estadoF'] ?? 'todos';
  $qParam  = isset($_GET['q']) ? '&q='.urlencode($_GET['q']) : '';
  redirect('cajas.listar&estado='.$estadoF.$qParam);
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('cajas.listar');
}
