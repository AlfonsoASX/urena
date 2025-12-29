<?php
// app/modules/cobrador.php — Panel operativo para cobradores con Búsqueda y Ordenamiento
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

$roles = ['cobrador','administradora'];
require_role($roles);

$action = $action ?? 'panel';

// -------------------------------------------------------------
// Funciones utilitarias del módulo
// -------------------------------------------------------------

function cobrador_personal_id(): int {
  $u = current_user();
  $r = qone("SELECT id_personal FROM futuro_personal WHERE id=?", [$u['id']]);
  return (int)($r['id_personal'] ?? 0);
}

/**
 * Obtiene la lista de contratos con filtros de búsqueda y ordenamiento
 */
function cobrador_contratos(int $id_personal, bool $soloAsignados = true, string $busqueda = '', string $orden = 'prioridad'): array {
  
  // Lista blanca de ordenamientos permitidos para evitar inyección SQL
  $orderBySQL = match($orden) {
    'id'      => 'c.id_contrato DESC',
    'titular' => 't.titular ASC',
    'monto'   => 'c.costo_final DESC',
    'estatus' => 'c.estatus ASC',
    default   => 'fecha_proxima_visita IS NULL ASC, fecha_proxima_visita ASC' // Lógica original: primero los agendados
  };

  $sql = "
    SELECT 
      c.id_contrato,
      c.costo_final,
      c.estatus,
      t.titular,
      TRIM(CONCAT(
        IFNULL(d.calle, ''), ' ', 
        IFNULL(CONCAT('#', d.num_ext), ''), ', ', 
        IFNULL(d.colonia, ''), ', ', 
        IFNULL(d.municipio, '')
      )) AS direccion,

      -- Subconsultas para datos dinámicos de gestión
      (
        SELECT g.fecha_registro 
        FROM futuro_gestion g 
        WHERE g.id_contrato = c.id_contrato 
        ORDER BY g.fecha_registro DESC 
        LIMIT 1
      ) AS fecha_ultima_gestion,

      (
        SELECT g.notas 
        FROM futuro_gestion g 
        WHERE g.id_contrato = c.id_contrato 
        ORDER BY g.fecha_registro DESC 
        LIMIT 1
      ) AS ultima_nota,

      (
        SELECT g.fecha_proxima_visita 
        FROM futuro_gestion g 
        WHERE g.id_contrato = c.id_contrato 
        ORDER BY g.fecha_registro DESC 
        LIMIT 1
      ) AS fecha_proxima_visita

    FROM futuro_contratos c
    " . ($soloAsignados ? "INNER JOIN futuro_contrato_cobrador fc ON fc.id_contrato = c.id_contrato" : "") . "
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
    LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
    LEFT JOIN titular_dom td ON td.id_titular = tc.id_titular
    LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
    WHERE 1=1
  ";

  $params = [];

  // 1. Filtro de asignación (Solo mis contratos)
  if ($soloAsignados) {
    $sql .= " AND fc.id_personal = ?";
    $params[] = $id_personal;
  }

  // 2. Filtro de búsqueda (ID o Nombre del Titular)
  if (!empty($busqueda)) {
    $sql .= " AND (c.id_contrato LIKE ? OR t.titular LIKE ?)";
    $term = "%$busqueda%";
    $params[] = $term; // Para id_contrato
    $params[] = $term; // Para titular
  }

  // 3. Agrupación y Ordenamiento
  $sql .= " GROUP BY c.id_contrato ORDER BY $orderBySQL";

  return qall($sql, $params);
}

function cobrador_registrar_gestion(int $id_contrato, ?float $monto, int $id_personal, float $lat, float $lng, ?string $fecha_proxima, ?string $notas): ?int {
  $pdo = db();
  try {
    $pdo->beginTransaction();

    $id_abono = null;
    // Si hay monto, registramos el abono financiero
    if ($monto && $monto > 0) {
      q("INSERT INTO futuro_abonos (id_contrato, saldo, cant_abono, fecha_registro)
         VALUES (?,?,?,CURRENT_TIMESTAMP())", [$id_contrato, 0, $monto]);
      $id_abono = $pdo->lastInsertId();
      
      // Relacionar abono con cobrador
      q("INSERT INTO futuro_abono_cobrador (id_abono, id_personal, fecha_registro)
         VALUES (?,?,CURRENT_TIMESTAMP())", [$id_abono, $id_personal]);
    }

    // Registrar la gestión (visita)
    q("INSERT INTO futuro_gestion (id_contrato, id_personal, cant_abono, latitud, longitud, fecha_proxima_visita, notas)
       VALUES (?,?,?,?,?,?,?)", [$id_contrato, $id_personal, $monto, $lat, $lng, $fecha_proxima, $notas]);
    $id_gestion = $pdo->lastInsertId();

    $pdo->commit();
    return $id_abono ?: $id_gestion;

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[cobrador_registrar_gestion] ".$e->getMessage());
    return null;
  }
}

function cobrador_ticket_visita(string $titular, int $id_contrato, ?string $fecha_proxima): void { ?>
  <div class="text-center">
    <h3>VISITA REGISTRADA</h3>
    <hr>
    <p><strong>Contrato:</strong> <?= e($id_contrato) ?></p>
    <p><strong>Titular:</strong> <?= e($titular) ?></p>
    <p><strong>Fecha:</strong> <?= date('Y-m-d H:i') ?></p>
    <?php if ($fecha_proxima): ?>
      <p><strong>Próxima visita:</strong> <?= e($fecha_proxima) ?></p>
    <?php endif; ?>
    <hr>
    <p>El cobrador realizó una visita a su domicilio.</p>
    <p>Si no fue atendido, programaremos una nueva visita.</p>
  </div>
  <div id="botones" class="form-inline mt-2 float-right">
     <a href="#" onclick="window.print(); return false;" class="btn btn-primary">Imprimir</a>
  </div>
<?php }

// -------------------------------------------------------------
// Controlador principal
// -------------------------------------------------------------

switch ($action) {

case 'panel':
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h4 mb-3">Panel del Cobrador</h1>
      <p>Bienvenido, <strong><?= e(current_user()['nombre']) ?></strong></p>
      <div class="list-group">
        <a href="?r=cobrador.contratos" class="list-group-item list-group-item-action">
          📄 Ver contratos asignados
        </a>
        <a href="?r=cobrador.contratos&filtro=todos" class="list-group-item list-group-item-action">
          📋 Ver todos los contratos
        </a>
      </div>
    </div>
  </div>
  <?php render('Panel del cobrador', ob_get_clean());
  break;

case 'contratos':
  $id_personal = cobrador_personal_id();
  $filtro = ($_GET['filtro'] ?? '') === 'todos' ? false : true; // True = Solo asignados
  
  // Capturar parámetros de búsqueda y orden
  $busqueda = trim($_GET['q'] ?? '');
  $orden    = $_GET['sort'] ?? 'prioridad';
  
  $rows = cobrador_contratos($id_personal, $filtro, $busqueda, $orden);

  ob_start(); ?>
  
  <div class="row mb-3 align-items-center g-2">
    <div class="col-md-4">
      <h1 class="h4 m-0"><?= $filtro ? 'Mis contratos' : 'Todos los contratos' ?></h1>
    </div>
    
    <div class="col-md-5">
      <form action="" method="get" class="d-flex">
        <input type="hidden" name="r" value="cobrador.contratos">
        <?php if(!$filtro): ?><input type="hidden" name="filtro" value="todos"><?php endif; ?>
        <input type="hidden" name="sort" value="<?= e($orden) ?>">
        
        <div class="input-group">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar titular o ID..." value="<?= e($busqueda) ?>">
          <button class="btn btn-primary btn-sm" type="submit">🔍</button>
          <?php if($busqueda): ?>
             <a href="?r=cobrador.contratos<?= $filtro ? '' : '&filtro=todos' ?>" class="btn btn-outline-secondary btn-sm" title="Limpiar búsqueda">×</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="col-md-3 text-md-end">
      <div class="btn-group btn-group-sm">
        <a href="?r=cobrador.contratos&q=<?=e($busqueda)?>" class="btn <?= $filtro ? 'btn-primary' : 'btn-outline-primary' ?>">Asignados</a>
        <a href="?r=cobrador.contratos&filtro=todos&q=<?=e($busqueda)?>" class="btn <?= !$filtro ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
      </div>
    </div>
  </div>

  <?php 
  // Helper local para crear enlaces de ordenamiento que no rompan la búsqueda actual
  $sortLink = function($col, $label) use ($orden, $filtro, $busqueda) {
      $active = $orden === $col;
      $icon = $active ? ($col === 'prioridad' || $col === 'estatus' ? ' ▲' : ' ▼') : ''; // Flecha simple
      $url = "?r=cobrador.contratos&sort=$col";
      if (!$filtro) $url .= "&filtro=todos";
      if ($busqueda) $url .= "&q=" . urlencode($busqueda);
      
      $class = $active ? 'text-dark fw-bold text-decoration-none' : 'text-muted text-decoration-none';
      return "<a href='$url' class='$class'>$label$icon</a>";
  };
  ?>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th><?= $sortLink('id', 'ID') ?></th>
          <th><?= $sortLink('titular', 'Titular') ?></th>
          <th>Dirección</th>
          <th><?= $sortLink('monto', 'Costo') ?></th>
          <th><?= $sortLink('estatus', 'Estatus') ?></th>
          <th><?= $sortLink('prioridad', '📅 Prox. Visita') ?></th>
          <th>🕒 Ult. Gestión</th>
          <th>📝 Nota</th>
          <th style="width:120px">Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): 
        $direccion = trim($r['direccion'] ?? '');
        // Limpiar comas duplicadas en dirección si faltan datos
        $direccion = trim($direccion, ', ');
        $link_maps = $direccion ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion . ', León, Guanajuato') : null;

        // Lógica de semáforo para fechas
        $fecha = $r['fecha_proxima_visita'];
        $clase = 'text-muted';
        $texto = '—';
        if ($fecha) {
          $hoy = date('Y-m-d');
          if ($fecha > $hoy) { 
              $clase = 'text-success fw-semibold'; 
              $texto = '🟢 ' . date('d/m', strtotime($fecha)); 
          } elseif ($fecha == $hoy) { 
              $clase = 'text-primary fw-bold'; 
              $texto = '🔵 HOY'; 
          } else { 
              $clase = 'text-danger fw-bold'; 
              $texto = '🔴 ' . date('d/m', strtotime($fecha)); 
          }
        }
      ?>
        <tr>
          <td><small class="font-monospace"><?= e($r['id_contrato']) ?></small></td>
          <td class="fw-bold"><?= e($r['titular'] ?? '—') ?></td>
          <td>
             <div class="d-flex align-items-center">
                <small class="d-block text-truncate" style="max-width: 180px;" title="<?= e($direccion) ?>">
                    <?= e($direccion ?: 'Sin dirección') ?>
                </small>
             </div>
          </td>
          <td><small>$<?= number_format($r['costo_final'], 2) ?></small></td>
          <td>
            <span class="badge bg-<?= $r['estatus'] === 'activo' ? 'success' : 'secondary' ?>">
                <?= substr(e($r['estatus']), 0, 3) ?>
            </span>
          </td>
          <td class="<?= $clase ?>"><small><?= $texto ?></small></td>
          <td><small class="text-muted"><?= $r['fecha_ultima_gestion'] ? date('d/m', strtotime($r['fecha_ultima_gestion'])) : '—' ?></small></td>
          <td>
            <small class="d-block text-truncate text-muted" style="max-width: 120px;" title="<?= e($r['ultima_nota']) ?>">
                <?= e($r['ultima_nota'] ?? '—') ?>
            </small>
          </td>
          <td>
            <div class="btn-group btn-group-sm">
              <a href="?r=cobrador.gestion&id_contrato=<?= $r['id_contrato'] ?>" class="btn btn-outline-success" title="Registrar Pago/Visita">💲</a>
              <?php if ($link_maps): ?>
                <a href="<?= $link_maps ?>" target="_blank" class="btn btn-outline-primary" title="Ver en Mapa">🗺️</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      
      <?php if (empty($rows)): ?>
        <tr>
            <td colspan="9" class="text-center py-4 text-muted">
                No se encontraron contratos.
                <?php if($busqueda): ?><br><a href="?r=cobrador.contratos">Mostrar todos</a><?php endif; ?>
            </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php render('Contratos del cobrador', ob_get_clean());
  break;

case 'gestion':
  $id_contrato = (int)($_GET['id_contrato'] ?? 0);
  if ($id_contrato <= 0) { 
      flash("<div class='alert alert-warning'>Contrato inválido.</div>"); 
      redirect('cobrador.contratos'); 
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf_verify(); } catch (RuntimeException $e) { 
        flash("<div class='alert alert-danger'>Sesión inválida.</div>"); 
        redirect('cobrador.gestion&id_contrato='.$id_contrato); 
    }

    $monto = trim($_POST['monto']) !== '' ? (float)$_POST['monto'] : null;
    $lat = (float)($_POST['latitud'] ?? 0);
    $lng = (float)($_POST['longitud'] ?? 0);
    $fecha_proxima = $_POST['fecha_proxima_visita'] ?: null;
    $notas = $_POST['notas'] ?? '';
    $id_personal = cobrador_personal_id();

    $id_resultado = cobrador_registrar_gestion($id_contrato, $monto, $id_personal, $lat, $lng, $fecha_proxima, $notas);

    if ($monto && $id_resultado) {
        redirect('cobrador.ticket&id_abono='.$id_resultado);
    } else {
      $t = qone("SELECT titular FROM vw_titular_contrato WHERE id_contrato=?", [$id_contrato]);
      render('Ticket de visita', (function() use($t,$id_contrato,$fecha_proxima){
          ob_start();
          cobrador_ticket_visita($t['titular'],$id_contrato,$fecha_proxima);
          return ob_get_clean();
      })());
      exit;
    }
  }

  // Vista del formulario
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
          <h1 class="h5 m-0">Gestión Contrato #<?= e($id_contrato) ?></h1>
          <a href="?r=cobrador.contratos" class="btn btn-sm btn-outline-secondary">Volver</a>
      </div>
      
      <form method="post" action="?r=cobrador.gestion&id_contrato=<?= urlencode($id_contrato) ?>" id="formGestion">
        <?= csrf_field() ?>
        
        <div class="mb-3">
            <label class="form-label">Monto abonado ($)</label>
            <input type="number" step="0.01" name="monto" class="form-control form-control-lg" placeholder="0.00 (Dejar vacío si solo es visita)">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha próxima visita</label>
            <input type="date" name="fecha_proxima_visita" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="3" maxlength="255"></textarea>
        </div>

        <input type="hidden" name="latitud" id="latitud">
        <input type="hidden" name="longitud" id="longitud">
        <script>
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('latitud').value = pos.coords.latitude.toFixed(6);
            document.getElementById('longitud').value = pos.coords.longitude.toFixed(6);
          }, err => console.warn("No se pudo obtener ubicación"));
        }
        </script>

        <div class="d-grid gap-2">
          <button class="btn btn-primary btn-lg">Guardar Gestión</button>
        </div>
      </form>
    </div>
  </div>
  <?php render('Registrar gestión', ob_get_clean());
  break;

case 'ticket':
  $id_abono = (int)($_GET['id_abono'] ?? 0);
  
  // 1. Consulta completa (igual a la de imprimirL.php) para tener los mismos datos
  $sql = "
    SELECT 
        a.id_abono, a.fecha_registro, a.cant_abono, a.saldo,
        c.id_contrato, c.estatus, c.tipo_contrato, c.costo_final, c.tipo_pago,
        t.titular,
        -- Obtenemos el nombre del cobrador que registró el abono, o el usuario actual si falla
        COALESCE(CONCAT(p.nombre, ' ', p.apellido_p), ?) AS nombre_cobrador
    FROM futuro_abonos a
    INNER JOIN futuro_contratos c ON c.id_contrato = a.id_contrato
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
    LEFT JOIN futuro_abono_cobrador fac ON fac.id_abono = a.id_abono
    LEFT JOIN futuro_personal p ON p.id_personal = fac.id_personal
    WHERE a.id_abono = ?
    LIMIT 1
  ";

  // Pasamos el nombre del usuario actual como fallback por si no hay cobrador enlazado
  $nombre_actual = current_user()['nombre'] ?? 'Oficina';
  $data = qone($sql, [$nombre_actual, $id_abono]);
  
  if (!$data) { 
      flash("<div class='alert alert-warning'>Ticket no encontrado.</div>"); 
      redirect('cobrador.contratos'); 
  }
  
  ob_start(); ?>
  
  <style>
    @media print {
      .no-print, .navbar, .footer, .btn { display: none !important; }
      body { background-color: white; padding-top: 0; }
      .card { border: none !important; box-shadow: none !important; }
      .container { max-width: 100% !important; margin: 0; padding: 0; }
    }
    .ticket-label { font-weight: bold; color: #555; }
    .ticket-value { font-weight: bold; color: #000; text-align: right; }
    .ticket-row { border-bottom: 1px dashed #ddd; padding: 5px 0; display: flex; justify-content: space-between; }
  </style>

  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          
          <div class="text-center mb-4">
            <h4 class="mb-1">GRUPO UREÑA FUNERARIOS</h4>
            <small class="text-muted">Independencia No. 708, Col. San Miguel</small><br>
            <small class="text-muted">Tel. 477-7122326</small>
            <h5 class="mt-3 border-top pt-2">RECIBO DE PAGO</h5>
          </div>

          <div class="ticket-row">
            <span class="ticket-label">Folio Abono:</span>
            <span class="ticket-value"><?= str_pad($data['id_abono'], 6, "0", STR_PAD_LEFT) ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Fecha:</span>
            <span class="ticket-value"><?= date('d/m/Y H:i', strtotime($data['fecha_registro'])) ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Contrato:</span>
            <span class="ticket-value"><?= e($data['id_contrato']) ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Titular:</span>
            <span class="ticket-value text-break" style="max-width: 60%;"><?= e($data['titular']) ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Tipo:</span>
            <span class="ticket-value"><?= e($data['tipo_contrato']) ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Costo Final:</span>
            <span class="ticket-value">$<?= number_format($data['costo_final'], 2) ?></span>
          </div>
          
          <div class="ticket-row mt-3" style="border-top: 2px solid #000; border-bottom: none;">
            <span class="ticket-label fs-5">SU PAGO:</span>
            <span class="ticket-value fs-5">$<?= number_format($data['cant_abono'], 2) ?></span>
          </div>

          <div class="ticket-row">
            <span class="ticket-label">Saldo Restante:</span>
            <span class="ticket-value text-danger">$<?= number_format($data['saldo'], 2) ?></span>
          </div>
          
          <div class="mt-4 text-center">
            <p class="mb-1"><small>Cobrador:</small></p>
            <p><strong><?= e($data['nombre_cobrador']) ?></strong></p>
            <hr>
            <p class="small text-muted">Gracias por su confianza.<br>Fue un placer atenderle.</p>
          </div>

        </div>
      </div>

      <div id="botones" class="d-flex flex-column gap-2 mt-4 no-print">
          <?php 
          // Construcción de URLs
          // Asegúrate de que $_SERVER['HTTP_HOST'] sea correcto o usa tu dominio fijo 'urena.control.mx'
          $domain = $_SERVER['HTTP_HOST'] ?? 'urena.control.mx';
          $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
          $baseUrl = "$protocol://$domain"; 
          
          // URL para JSON (App Móvil)
          $url_json = $baseUrl . '/app/modules/imprimirM.php?id_abono=' . $data['id_abono'];
          $url_app = 'my.bluetoothprint.scheme://' . $url_json;
          
          // URL para PDF
          $url_pdf = '?r=modules/imprimirL&id_abono=' . $data['id_abono']; 
          // Nota: Si imprimirL está en modules, la ruta correcta en tu router parece ser directa al archivo
          // pero si usas el router index.php, sería mejor una ruta limpia. 
          // Usaremos acceso directo al archivo para el botón PDF externo:
          $link_pdf_directo = "modules/imprimirL.php?id_abono=" . $data['id_abono'];
          ?>

          <a href="<?= $url_app ?>" class="btn btn-dark btn-lg w-100">
             📱 Imprimir en App (Thermer)
          </a>

          <a href="<?= $link_pdf_directo ?>" target="_blank" class="btn btn-outline-secondary w-100">
             📄 Ver PDF 
          </a>
          
          <button onclick="window.print();" class="btn btn-link w-100">
             🖨️ Imprimir en navegador
          </button>

          <a href="?r=cobrador.contratos" class="btn btn-light w-100 mt-2">Volver al panel</a>
      </div>

    </div>
  </div>

  <?php render('Ticket de pago', ob_get_clean());
  break;

default:
  redirect('cobrador.panel');
}