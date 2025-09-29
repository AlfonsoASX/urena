<?php
// app/modules/servicios.php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

/**
 * Auto-migraciones suaves para 'servicios':
 * - agrega estatus ('abierto'/'cerrado')
 * - agrega eliminado (borrado lógico)
 */
function servicios_ensure_schema() {
  try { q("ALTER TABLE servicios ADD COLUMN IF NOT EXISTS estatus VARCHAR(10) NOT NULL DEFAULT 'abierto'"); } catch(Exception $e){}
  try { q("ALTER TABLE servicios ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e){}
}
servicios_ensure_schema();

/** Helpers */
function __equipos_disponibles() {
  // Equipos “disponible” y sin baja lógica
  try {
    return qall("SELECT id_equipo, equipo FROM equipos WHERE COALESCE(eliminado,0)=0 AND estatus='disponible' ORDER BY equipo ASC");
  } catch (Exception $e) {
    return [];
  }
}

$action = $action ?? 'listar';

switch ($action) {

/* =========================================================
 * LISTAR (abiertos / cerrados) + buscador + columna Fallecido
 * =======================================================*/
case 'listar':
  $tab = ($_GET['tab'] ?? 'abiertos') === 'cerrados' ? 'cerrados' : 'abiertos';
  $q = trim($_GET['q'] ?? '');

  $where = "s.eliminado=0 AND s.estatus=?";
  $params = [$tab === 'abiertos' ? 'abierto' : 'cerrado'];

  if ($q !== '') {
    $like = '%'.$q.'%';
    // buscar por fallecido, responsable, auxiliares o id_servicio
    $where .= " AND (f.nom_fallecido LIKE ? OR s.responsable LIKE ? OR s.auxiliares LIKE ? OR s.id_servicio = ?)";
    array_push($params, $like, $like, $like, ctype_digit($q) ? (int)$q : -1);
  }

  // LEFT JOIN simple para traer nombre del fallecido
  $rows = qall("
    SELECT
      s.id_servicio,
      DATE(s.created_at) AS fecha,
      s.tipo_servicio,
      s.tipo_venta,
      s.responsable,
      s.auxiliares,
      COALESCE(f.nom_fallecido,'') AS fallecido
    FROM servicios s
    LEFT JOIN servicio_fallecido sf ON sf.id_servicio = s.id_servicio
    LEFT JOIN fallecido f ON f.id_fallecido = sf.id_fallecido
    WHERE $where
    ORDER BY s.id_servicio DESC
    LIMIT 300
  ", $params);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h4 m-0">Servicios</h1>
    <div class="d-flex gap-2">
      <a href="?r=servicios.nuevo" class="btn btn-success btn-sm">Nuevo servicio</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['_alerts'])) { echo $_SESSION['_alerts']; unset($_SESSION['_alerts']); } ?>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link <?= $tab==='abiertos'?'active':'' ?>" href="?r=servicios.listar&tab=abiertos">Abiertos</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab==='cerrados'?'active':'' ?>" href="?r=servicios.listar&tab=cerrados">Cerrados</a>
    </li>
  </ul>

  <div class="row g-2 mb-2">
    <div class="col-md-8">
      <input id="svcSearch" type="search" class="form-control" placeholder="Buscar por fallecido, responsable, auxiliar o folio..." value="<?= e($q) ?>">
    </div>
    <div class="col-md-4 text-md-end small text-muted">
      <?= count($rows) ?> resultado<?= count($rows)===1?'':'s' ?>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fecha</th>
          <th>Fallecido</th>
          <th>Servicio</th>
          <th>Venta</th>
          <th>Responsable</th>
          <th>Auxiliares</th>
          <th style="width:220px">Herramientas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td>#<?= (int)$r['id_servicio'] ?></td>
          <td><?= e($r['fecha']) ?></td>
          <td><?= e($r['fallecido'] ?: '—') ?></td>
          <td><?= e($r['tipo_servicio']) ?></td>
          <td><?= e($r['tipo_venta']) ?></td>
          <td><?= e($r['responsable']) ?></td>
          <td><?= e($r['auxiliares']) ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="?r=servicios.ver&id=<?= (int)$r['id_servicio'] ?>">Abrir</a>
            <?php if ($tab==='abiertos'): ?>
              <a class="btn btn-outline-success btn-sm"
                 href="?r=servicios.cerrar&id=<?= (int)$r['id_servicio'] ?>&tab=<?= $tab ?>"
                 onclick="return confirm('¿Marcar este servicio como CERRADO?');">Cerrar</a>
            <?php endif; ?>
            <a class="btn btn-outline-danger btn-sm"
               href="?r=servicios.borrar&id=<?= (int)$r['id_servicio'] ?>&tab=<?= $tab ?>&q=<?= urlencode($q) ?>"
               onclick="return confirm('¿Borrar (lógico) este servicio? Ya no aparecerá en la lista.');">Borrar</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    (function(){
      const qEl = document.getElementById('svcSearch');
      let t=null;
      function go(){
        const q = qEl.value.trim();
        const url = "?r=servicios.listar&tab=<?= $tab ?>"+(q?("&q="+encodeURIComponent(q)):"");
        window.location = url;
      }
      qEl.addEventListener('keyup', function(ev){
        if (ev.key==='Enter'){ go(); return; }
        clearTimeout(t); t=setTimeout(go, 300);
      });
      qEl.addEventListener('keydown', function(ev){
        if (ev.key==='Escape'){ qEl.value=''; go(); }
      });
    })();
  </script>
  <?php
  render('Servicios', ob_get_clean());
break;

/* =========================================================
 * NUEVO (formulario)
 * =======================================================*/
case 'nuevo':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo servicio</h1>
      <form method="post" action="?r=servicios.guardar">
        <div class="row g-2">
          <div class="col-md-6">
            <?= form_select('tipo_servicio','Tipo de servicio *', ['cremación'=>'Cremación','inhumación'=>'Inhumación'],'cremación') ?>
          </div>
          <div class="col-md-6">
            <?= form_select('tipo_venta','Tipo de venta *', ['contado'=>'Contado','crédito'=>'Crédito'],'contado') ?>
          </div>
        </div>

        <?= form_input('nom_fallecido','Nombre del fallecido *','', ['required'=>true, 'maxlength'=>100]) ?>

        <div class="row g-2">
          <div class="col-md-6"><?= form_input('responsable','Responsable del servicio *','', ['required'=>true]) ?></div>
          <div class="col-md-6"><?= form_input('auxiliares','Auxiliares (coma-separado)','') ?></div>
        </div>

        <div class="row g-2">
          <div class="col-md-6"><?= form_input('contratante','Nombre del contratante','') ?></div>
          <div class="col-md-6"><?= form_input('contacto','Contacto del contratante','') ?></div>
        </div>

        <div class="row g-2">
          <div class="col-md-3"><?= form_input('velas','Velas','0', ['type'=>'number','min'=>'0']) ?></div>
          <div class="col-md-3"><?= form_input('despensa','Despensa','0', ['type'=>'number','min'=>'0']) ?></div>
          <div class="col-md-6"><?= form_input('notas','Notas adicionales','') ?></div>
        </div>

        <button class="btn btn-primary mt-2">Guardar servicio</button>
        <a class="btn btn-light mt-2" href="?r=servicios.listar">Cancelar</a>
      </form>
    </div>
  </div>
  <?php
  render('Nuevo servicio', ob_get_clean());
break;

/* =========================================================
 * GUARDAR (POST) — crea servicio + fallecido + vínculo
 * =======================================================*/
case 'guardar':
  $tipo_servicio = trim($_POST['tipo_servicio'] ?? '');
  $tipo_venta    = trim($_POST['tipo_venta'] ?? '');
  $nom_fallecido = trim($_POST['nom_fallecido'] ?? '');
  $responsable   = trim($_POST['responsable'] ?? '');
  $auxiliares    = trim($_POST['auxiliares'] ?? '');
  $contratante   = trim($_POST['contratante'] ?? '');
  $contacto      = trim($_POST['contacto'] ?? '');
  $velas         = (int)($_POST['velas'] ?? 0);
  $despensa      = (int)($_POST['despensa'] ?? 0);
  $notas         = trim($_POST['notas'] ?? '');

  if ($tipo_servicio==='' || $tipo_venta==='' || $nom_fallecido==='' || $responsable==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Campos obligatorios faltantes.</div>";
    redirect('servicios.nuevo');
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();

    // 1) Servicio
    q("INSERT INTO servicios (id_evento, tipo_servicio, tipo_venta, velas, despensa, notas, responsable, auxiliares, created_at, estatus, eliminado)
       VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(), 'abierto', 0)",
       [$tipo_servicio, $tipo_venta, $velas, $despensa,
        // empacamos contratante/contacto en notas si se proporcionan (campo dedicado no existe)
        trim($notas.($contratante||$contacto ? " | Contratante: $contratante ($contacto)" : '')),
        $responsable, $auxiliares]);
    $id_serv = $pdo->lastInsertId();

    // 2) Fallecido
    q("INSERT INTO fallecido (nom_fallecido, dom_velacion, hospital, municipio, fecha)
       VALUES (?, '', NULL, '', CURRENT_DATE())", [$nom_fallecido]);
    $id_fall = $pdo->lastInsertId();

    // 3) Vínculo
    q("INSERT INTO servicio_fallecido (id_fallecido, id_servicio) VALUES (?, ?)", [$id_fall, $id_serv]);

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Servicio creado. Folio #$id_serv.</div>";
    redirect('servicios.ver&id='.$id_serv);
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo guardar el servicio.</div>";
    redirect('servicios.nuevo');
  }
break;

/* =========================================================
 * VER (detalle) — equipo asignado + asignar equipo disponible
 * =======================================================*/
case 'ver':
  $id = (int)($_GET['id'] ?? 0);
  $svc = qone("
    SELECT s.id_servicio, DATE(s.created_at) AS fecha, s.tipo_servicio, s.tipo_venta,
           s.velas, s.despensa, s.notas, s.responsable, s.auxiliares, s.estatus
    FROM servicios s
    WHERE s.id_servicio=? AND s.eliminado=0
  ", [$id]);

  if (!$svc) {
    $_SESSION['_alerts'] = "<div class='alert alert-warning'>Servicio no encontrado.</div>";
    redirect('servicios.listar');
  }

  $fall = qone("
    SELECT f.nom_fallecido
    FROM servicio_fallecido sf
    JOIN fallecido f ON f.id_fallecido=sf.id_fallecido
    WHERE sf.id_servicio=?
    ORDER BY sf.id_falle_serv DESC
    LIMIT 1
  ", [$id]);

  // Equipo ya asignado a este servicio
  $equipos_asignados = qall("
    SELECT se.id_serv_eq, se.id_equipo, e.equipo, DATE(se.fecha) AS fecha
    FROM servicio_equipo se
    LEFT JOIN equipos e ON e.id_equipo=se.id_equipo
    WHERE se.id_servicio=?
    ORDER BY se.id_serv_eq DESC
  ", [$id]);

  // Equipos disponibles para asignar
  $equipos_disp = __equipos_disponibles();

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <h1 class="h5 m-0">Servicio #<?= (int)$svc['id_servicio'] ?> · <?= e($fall['nom_fallecido'] ?? '—') ?></h1>
    <div class="d-flex gap-2">
      <?php if ($svc['estatus']==='abierto'): ?>
        <a href="?r=servicios.cerrar&id=<?= (int)$svc['id_servicio'] ?>" class="btn btn-outline-success btn-sm"
           onclick="return confirm('¿Marcar este servicio como CERRADO?');">Cerrar</a>
      <?php endif; ?>
      <a href="?r=servicios.listar" class="btn btn-light btn-sm">Volver</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['_alerts'])) { echo $_SESSION['_alerts']; unset($_SESSION['_alerts']); } ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="mb-3">Datos del servicio</h6>
          <div class="row">
            <div class="col-6"><strong>Fecha:</strong> <?= e($svc['fecha']) ?></div>
            <div class="col-6"><strong>Estatus:</strong> <?= e($svc['estatus']) ?></div>
            <div class="col-6"><strong>Tipo:</strong> <?= e($svc['tipo_servicio']) ?></div>
            <div class="col-6"><strong>Venta:</strong> <?= e($svc['tipo_venta']) ?></div>
            <div class="col-6"><strong>Responsable:</strong> <?= e($svc['responsable']) ?></div>
            <div class="col-6"><strong>Auxiliares:</strong> <?= e($svc['auxiliares'] ?: '—') ?></div>
            <div class="col-6"><strong>Velas:</strong> <?= (int)$svc['velas'] ?></div>
            <div class="col-6"><strong>Despensa:</strong> <?= (int)$svc['despensa'] ?></div>
            <div class="col-12"><strong>Notas:</strong> <?= e($svc['notas'] ?: '—') ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="mb-3">Equipo asignado</h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead>
                <tr>
                  <th>Equipo</th>
                  <th>Código</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($equipos_asignados as $eq): ?>
                <tr>
                  <td><?= e($eq['equipo'] ?: '—') ?></td>
                  <td><code><?= e($eq['id_equipo']) ?></code></td>
                  <td><?= e($eq['fecha']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($equipos_asignados)): ?>
                  <tr><td colspan="3" class="text-muted text-center">Sin equipo asignado</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <hr>

          <h6 class="mb-3">Asignar equipo disponible</h6>
          <?php if (empty($equipos_disp)): ?>
            <div class="alert alert-warning">No hay equipos “disponibles”.</div>
          <?php else: ?>
            <form method="post" action="?r=servicios.asignar_equipo&id=<?= (int)$svc['id_servicio'] ?>">
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label">Equipo</label>
                  <select name="id_equipo" class="form-select" required>
                    <option value="">— Selecciona —</option>
                    <?php foreach($equipos_disp as $e): ?>
                      <option value="<?= e($e['id_equipo']) ?>"><?= e($e['equipo']) ?> (<?= e($e['id_equipo']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <?= form_input('fecha','Fecha', date('Y-m-d'), ['type'=>'date','required'=>true]) ?>
                </div>
              </div>
              <div class="d-grid mt-2">
                <button class="btn btn-primary">Asignar</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php
  render('Servicio', ob_get_clean());
break;

/* =========================================================
 * CERRAR servicio
 * =======================================================*/
case 'cerrar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    q("UPDATE servicios SET estatus='cerrado' WHERE id_servicio=? AND eliminado=0", [$id]);
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Servicio cerrado.</div>";
  }
  $tab = $_GET['tab'] ?? 'abiertos';
  redirect('servicios.listar&tab='.$tab);
break;

/* =========================================================
 * BORRAR (lógico)
 * =======================================================*/
case 'borrar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id>0) {
    q("UPDATE servicios SET eliminado=1 WHERE id_servicio=?", [$id]);
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Servicio borrado.</div>";
  }
  $tab = $_GET['tab'] ?? 'abiertos';
  $q   = $_GET['q'] ?? '';
  $qs = '&tab='.$tab.($q!=='' ? '&q='.urlencode($q) : '');
  redirect('servicios.listar'.$qs);
break;

/* =========================================================
 * ASIGNAR EQUIPO (desde detalle)
 * =======================================================*/
case 'asignar_equipo':
  $id_serv = (int)($_GET['id'] ?? 0);
  $id_equipo = trim($_POST['id_equipo'] ?? '');
  $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));

  if ($id_serv<=0 || $id_equipo==='') {
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>Datos incompletos.</div>";
    redirect('servicios.ver&id='.$id_serv);
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();

    // 1) Vincular
    q("INSERT INTO servicio_equipo (id_servicio, id_equipo, fecha) VALUES (?, ?, ?)", [$id_serv, $id_equipo, $fecha]);

    // 2) Marcar equipo como asignado
    q("UPDATE equipos SET estatus='asignado', updated_at=CURRENT_DATE() WHERE id_equipo=? AND COALESCE(eliminado,0)=0", [$id_equipo]);

    $pdo->commit();
    $_SESSION['_alerts'] = "<div class='alert alert-success'>Equipo asignado correctamente.</div>";
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo asignar el equipo.</div>";
  }

  redirect('servicios.ver&id='.$id_serv);
break;

/* =========================================================
 * DEFAULT
 * =======================================================*/
default:
  redirect('servicios.listar');
}
