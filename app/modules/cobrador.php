<?php
// app/modules/cobrador.php — Panel operativo para cobradores con Búsqueda y Ordenamiento
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/cortes_cobradores.php';

require_login();

$roles = ['cobrador','administradora'];
require_role($roles);

$action = $action ?? 'panel';

// -------------------------------------------------------------
// Funciones utilitarias del módulo
// -------------------------------------------------------------

function cobrador_personal_id(): int {
  $u = current_user();
  return (int)($u['id'] ?? 0);
}

function cobrador_saldo_contrato(PDO $pdo, int $id_contrato, float $monto_nuevo = 0.0, bool $lock = false): array {
  $sqlContrato = "SELECT id_contrato, costo_final FROM futuro_contratos WHERE id_contrato=?".($lock ? " FOR UPDATE" : "");
  $stmt = $pdo->prepare($sqlContrato);
  $stmt->execute([$id_contrato]);
  $c = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$c) {
    throw new RuntimeException('Contrato no encontrado.');
  }

  $sqlSum = "SELECT COALESCE(SUM(cant_abono),0) AS pagado FROM futuro_abonos WHERE id_contrato=?".($lock ? " FOR UPDATE" : "");
  $stmt2 = $pdo->prepare($sqlSum);
  $stmt2->execute([$id_contrato]);
  $s = $stmt2->fetch(PDO::FETCH_ASSOC);

  $costo = (float)$c['costo_final'];
  $pagado = (float)($s['pagado'] ?? 0);
  $saldo = $costo - ($pagado + (float)$monto_nuevo);
  if ($saldo < 0) $saldo = 0;

  return ['costo_final' => $costo, 'pagado' => $pagado, 'saldo' => $saldo];
}
/**
 * Obtiene la lista de contratos con filtros, ordenamiento y PAGINACIÓN
 */
function cobrador_contratos_paginado(int $id_usuario, bool $soloAsignados = true, string $busqueda = '', string $orden = 'prioridad', int $pagina = 1, int $limite = 10): array {
    $offset = ($pagina - 1) * $limite;

    // Lista blanca de ordenamientos
    $orderBySQL = match($orden) {
        'id'      => 'c.id_contrato DESC',
        'titular' => 't.titular ASC',
        'monto'   => 'c.costo_final DESC',
        'estatus' => 'c.estatus ASC',
        default   => 'fecha_proxima_visita IS NULL ASC, fecha_proxima_visita ASC'
    };

    // Base del Query para WHERE y JOINs (Reutilizado para conteo y selección)
    $baseSql = "
        FROM futuro_contratos c
        ". ($soloAsignados ? "INNER JOIN futuro_contrato_cobrador fc ON fc.id_cont_cob=(SELECT MAX(fcx.id_cont_cob) FROM futuro_contrato_cobrador fcx WHERE fcx.id_contrato=c.id_contrato)" : "") ."
        LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
        LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
        LEFT JOIN titular_dom td ON td.id_titular = tc.id_titular
        LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
        WHERE 1=1
    ";

    $params = [];

    // 1. Filtros
    if ($soloAsignados) {
        $baseSql .= " AND fc.id_usuario = ?";
        $params[] = $id_usuario;
    }

    if (!empty($busqueda)) {
        $baseSql .= " AND (c.id_contrato LIKE ? OR t.titular LIKE ? OR TRIM(CONCAT(IFNULL(d.calle,''),' ',IFNULL(CONCAT('#', d.num_ext),''),', ',IFNULL(d.colonia,''),', ',IFNULL(d.municipio,''))) LIKE ?)";
        $term = "%$busqueda%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    // 2. Obtener el TOTAL de registros (Para la paginación)
    // Usamos COUNT(DISTINCT) porque la consulta original tenía GROUP BY
    $sqlCount = "SELECT COUNT(DISTINCT c.id_contrato) as total " . $baseSql;
    $countRow = qone($sqlCount, $params);
    $totalRegistros = (int)($countRow['total'] ?? 0);
    $totalPaginas = ceil($totalRegistros / $limite);

    // 3. Obtener los DATOS paginados
    $sqlData = "
        SELECT 
            c.id_contrato, c.costo_final, c.estatus, t.titular,
            TRIM(CONCAT(IFNULL(d.calle, ''), ' ', IFNULL(CONCAT('#', d.num_ext), ''), ', ', IFNULL(d.colonia, ''), ', ', IFNULL(d.municipio, ''))) AS direccion,
            (SELECT g.fecha_registro FROM futuro_gestion g WHERE g.id_contrato = c.id_contrato ORDER BY g.fecha_registro DESC LIMIT 1) AS fecha_ultima_gestion,
            (SELECT g.notas FROM futuro_gestion g WHERE g.id_contrato = c.id_contrato ORDER BY g.fecha_registro DESC LIMIT 1) AS ultima_nota,
            (SELECT g.fecha_proxima_visita FROM futuro_gestion g WHERE g.id_contrato = c.id_contrato ORDER BY g.fecha_registro DESC LIMIT 1) AS fecha_proxima_visita,
            COALESCE((SELECT SUM(a.cant_abono) FROM futuro_abonos a WHERE a.id_contrato = c.id_contrato),0) AS total_pagado,
            GREATEST(c.costo_final - COALESCE((SELECT SUM(a2.cant_abono) FROM futuro_abonos a2 WHERE a2.id_contrato = c.id_contrato),0), 0) AS saldo_actual
        " . $baseSql . "
        GROUP BY c.id_contrato 
        ORDER BY $orderBySQL
        LIMIT $limite OFFSET $offset
    ";

    $rows = qall($sqlData, $params);

    return [
        'rows' => $rows,
        'meta' => [
            'total' => $totalRegistros,
            'pages' => $totalPaginas,
            'current' => $pagina
        ]
    ];
}
/**
 * Obtiene la lista de contratos con filtros de búsqueda y ordenamiento
 */
function cobrador_contratos(int $id_usuario, bool $soloAsignados = true, string $busqueda = '', string $orden = 'prioridad'): array {

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
      ) AS fecha_proxima_visita,

      -- Suma de pagos y saldo actual
      COALESCE((
        SELECT SUM(a.cant_abono)
        FROM futuro_abonos a
        WHERE a.id_contrato = c.id_contrato
      ),0) AS total_pagado,

      GREATEST(
        c.costo_final - COALESCE((
          SELECT SUM(a2.cant_abono)
          FROM futuro_abonos a2
          WHERE a2.id_contrato = c.id_contrato
        ),0),
        0
      ) AS saldo_actual

    FROM futuro_contratos c
    ". ($soloAsignados ? "INNER JOIN futuro_contrato_cobrador fc ON fc.id_cont_cob=(SELECT MAX(fcx.id_cont_cob) FROM futuro_contrato_cobrador fcx WHERE fcx.id_contrato=c.id_contrato)" : "") ."
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
    LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
    LEFT JOIN titular_dom td ON td.id_titular = tc.id_titular
    LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
    WHERE 1=1
  ";

  $params = [];

  // 1. Filtro de asignación (Solo mis contratos)
  if ($soloAsignados) {
    $sql .= " AND fc.id_usuario = ?";
    $params[] = $id_usuario;
  }

  // 2. Filtro de búsqueda (ID, Nombre del Titular o Dirección)
  if (!empty($busqueda)) {
    $sql .= " AND (c.id_contrato LIKE ? OR t.titular LIKE ? OR TRIM(CONCAT(IFNULL(d.calle,''),' ',IFNULL(CONCAT('#', d.num_ext),''),', ',IFNULL(d.colonia,''),', ',IFNULL(d.municipio,''))) LIKE ?)";
    $term = "%$busqueda%";
    $params[] = $term; // id_contrato
    $params[] = $term; // titular
    $params[] = $term; // direccion
  }

  // 3. Agrupación y Ordenamiento
  $sql .= " GROUP BY c.id_contrato ORDER BY $orderBySQL";

  return qall($sql, $params);
}

function cobrador_registrar_gestion(int $id_contrato, ?float $monto, int $id_usuario, float $lat, float $lng, ?string $fecha_proxima, ?string $notas): ?int {
  $pdo = db();
  try {
    $pdo->beginTransaction();

    $id_abono = null;
    // Si hay monto, registramos el abono financiero
    if ($monto && $monto > 0) {
      if (!futuros_promociones_condicion_contrato($id_contrato)) {
        futuros_promociones_migrar_contrato_vigente($id_contrato);
      }

      $calc = cobrador_saldo_contrato($pdo, $id_contrato, (float)$monto, true);

      q("INSERT INTO futuro_abonos (id_contrato, saldo, cant_abono, fecha_registro)
         VALUES (?,?,?,CURRENT_TIMESTAMP())", [$id_contrato, (float)$calc['saldo'], (float)$monto]);
      $id_abono = $pdo->lastInsertId();

      // Relacionar abono con cobrador
      q("INSERT INTO futuro_abono_cobrador (id_abono,id_usuario,fecha_registro)
         VALUES (?,?,CURRENT_TIMESTAMP())", [$id_abono,$id_usuario]);
      futuros_promociones_reconstruir_distribuciones($id_contrato);
    }

    // Registrar la gestión (visita)
    q("INSERT INTO futuro_gestion (id_contrato,id_usuario,cant_abono,latitud,longitud,fecha_proxima_visita,notas)
       VALUES (?,?,?,?,?,?,?)", [$id_contrato,$id_usuario,$monto,$lat,$lng,$fecha_proxima,$notas]);
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
  $idPersonalPanel = cobrador_personal_id();
  $configCortePanel = cortes_cobradores_configuracion();
  $periodoCortePanel = null;
  $totalCortePanel = ['pagos'=>0,'contratos'=>0,'total'=>0.0];
  if ($configCortePanel && $idPersonalPanel > 0) {
    $periodoCortePanel = cortes_cobradores_periodo_actual($configCortePanel);
    $totalCortePanel = cortes_cobradores_total_personal($periodoCortePanel, $idPersonalPanel);
  }
  ob_start(); ?>
  <?php if ($periodoCortePanel): ?>
    <section class="border-top border-bottom py-3 mb-3">
      <span class="text-muted small d-block">Acumulado del corte actual</span>
      <strong class="display-6 d-block">$<?= number_format((float)$totalCortePanel['total'],2) ?></strong>
      <div class="text-muted small mt-1">
        <?= e(cortes_cobradores_formatear_limite($periodoCortePanel['inicio'])) ?> a <?= e(cortes_cobradores_formatear_limite($periodoCortePanel['fin'])) ?>
      </div>
      <div class="d-flex gap-4 mt-2 small">
        <span><strong><?= (int)$totalCortePanel['pagos'] ?></strong> pagos</span>
        <span><strong><?= (int)$totalCortePanel['contratos'] ?></strong> contratos</span>
      </div>
    </section>
  <?php elseif ($idPersonalPanel > 0): ?>
    <div class="alert alert-warning">El horario del corte semanal aun no esta configurado.</div>
  <?php endif; ?>
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
  $id_usuario = cobrador_personal_id();
  $filtro = ($_GET['filtro'] ?? '') === 'todos' ? false : true; 

  // Capturar parámetros
  $busqueda = trim($_GET['q'] ?? '');
  $orden    = $_GET['sort'] ?? 'prioridad';
  
  // Paginación: Página actual (por defecto 1) y Límite (fijo a 10)
  $pagina   = max(1, (int)($_GET['p'] ?? 1));
  $limite   = 12;

  // Llamada a la nueva función paginada
  $resultado = cobrador_contratos_paginado($id_usuario, $filtro, $busqueda, $orden, $pagina, $limite);
  $rows = $resultado['rows'];
  $meta = $resultado['meta'];

  ob_start(); ?>

  <div class="row mb-3 align-items-center g-2">
    <div class="col-12 col-md-4">
      <h1 class="h4 m-0"><?= $filtro ? 'Mis contratos' : 'Todos' ?> <small class="text-muted fs-6">(<?= $meta['total'] ?>)</small></h1>
    </div>

    <div class="col-12 col-md-8">
      <form action="" method="get" class="d-flex gap-2">
        <input type="hidden" name="r" value="cobrador.contratos">
        <?php if(!$filtro): ?><input type="hidden" name="filtro" value="todos"><?php endif; ?>
        <input type="hidden" name="sort" value="<?= e($orden) ?>">

        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?= e($busqueda) ?>">
          <button class="btn btn-primary" type="submit">🔍</button>
          <?php if($busqueda): ?>
             <a href="?r=cobrador.contratos<?= $filtro ? '' : '&filtro=todos' ?>" class="btn btn-outline-secondary">×</a>
          <?php endif; ?>
        </div>
        
        <div class="btn-group">
            <a href="?r=cobrador.contratos&q=<?=e($busqueda)?>" class="btn <?= $filtro ? 'btn-dark' : 'btn-outline-dark' ?>">Míos</a>
            <a href="?r=cobrador.contratos&filtro=todos&q=<?=e($busqueda)?>" class="btn <?= !$filtro ? 'btn-dark' : 'btn-outline-dark' ?>">Todos</a>
        </div>
      </form>
    </div>
  </div>

  <div class="mb-3 d-flex gap-2 overflow-auto pb-2">
    <span class="text-muted align-self-center small">Ordenar:</span>
    <?php 
    $sortOptions = ['prioridad' => 'Visita', 'titular' => 'Nombre', 'monto' => 'Deuda', 'id' => 'ID'];
    foreach($sortOptions as $key => $label): 
        $active = $orden === $key;
        $url = "?r=cobrador.contratos&sort=$key" . ($filtro?'':'&filtro=todos') . ($busqueda?'&q='.urlencode($busqueda):'');
    ?>
        <a href="<?= $url ?>" class="btn btn-sm rounded-pill <?= $active ? 'btn-secondary' : 'btn-outline-secondary' ?>">
            <?= $label ?> <?= $active ? '▼' : '' ?>
        </a>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <?php foreach ($rows as $r): 
        $direccion = trim($r['direccion'] ?? '');
        $direccion = trim($direccion, ', ');
        $link_maps = $direccion ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion . ', León, Guanajuato') : null;

        // Lógica de colores por fecha
        $fecha = $r['fecha_proxima_visita'];
        $claseFecha = 'text-muted';
        $textoFecha = 'Sin cita';
        if ($fecha) {
            $hoy = date('Y-m-d');
            if ($fecha > $hoy) { 
                $claseFecha = 'text-success fw-bold'; 
                $textoFecha = '🟢 ' . date('d/m', strtotime($fecha)); 
            } elseif ($fecha == $hoy) { 
                $claseFecha = 'text-primary fw-bold'; 
                $textoFecha = '🔵 HOY'; 
            } else { 
                $claseFecha = 'text-danger fw-bold'; 
                $textoFecha = '🔴 ' . date('d/m', strtotime($fecha)); 
            }
        }
    ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="badge bg-light text-dark border">#<?= e($r['id_contrato']) ?></span>
                <span class="badge bg-<?= ($r['estatus'] ?? '') === 'activo' ? 'success' : 'secondary' ?>">
                    <?= strtoupper(e($r['estatus'] ?? '')) ?>
                </span>
            </div>
            <div class="card-body">
                <h5 class="card-title mb-1 text-truncate">
                    <a href="?r=cobrador.estado&id_contrato=<?= $r['id_contrato'] ?>" class="text-decoration-none text-dark">
                        <?= e($r['titular'] ?? 'Sin Titular') ?>
                    </a>
                </h5>
                
                <p class="card-text text-muted small mb-2">
                    <i class="opacity-50">📍</i> <?= e($direccion ?: 'Sin dirección registrada') ?>
                </p>

                <div class="row g-0 mt-3 bg-light rounded p-2 text-center">
                    <div class="col border-end">
                        <small class="text-muted d-block" style="font-size:0.75rem">SALDO</small>
                        <span class="text-danger fw-bold">$<?= number_format((float)($r['saldo_actual'] ?? 0), 0) ?></span>
                    </div>
                    <div class="col border-end">
                        <small class="text-muted d-block" style="font-size:0.75rem">VISITA</small>
                        <span class="<?= $claseFecha ?>"><?= $textoFecha ?></span>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block" style="font-size:0.75rem">ULT. GESTIÓN</small>
                        <span><?= $r['fecha_ultima_gestion'] ? date('d/m', strtotime($r['fecha_ultima_gestion'])) : '—' ?></span>
                    </div>
                </div>
                
                <?php if(!empty($r['ultima_nota'])): ?>
                    <div class="alert alert-warning p-2 mt-2 mb-0 small text-truncate">
                        📝 <?= e($r['ultima_nota']) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer bg-white border-top-0 d-grid gap-2 pb-3">
                <a href="?r=cobrador.gestion&id_contrato=<?= $r['id_contrato'] ?>" class="btn btn-primary">
                    💲 Registrar Cobro / Visita
                </a>
                <div class="d-flex gap-2">
                    <a href="?r=cobrador.pagos&id_contrato=<?= $r['id_contrato'] ?>" class="btn btn-outline-dark flex-grow-1 btn-sm">
                        🧾 Historial
                    </a>
                    <?php if ($link_maps): ?>
                        <a href="<?= $link_maps ?>" target="_blank" class="btn btn-outline-primary flex-grow-1 btn-sm">
                            🗺️ Mapa
                        </a>
                    <?php else: ?>
                        <button disabled class="btn btn-outline-secondary flex-grow-1 btn-sm">Sin Mapa</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($rows)): ?>
        <div class="col-12 text-center py-5">
            <h3 class="text-muted">No se encontraron contratos</h3>
            <p>Intenta con otra búsqueda o filtro.</p>
        </div>
    <?php endif; ?>
  </div>

  <?php if ($meta['pages'] > 1): ?>
  <nav class="mt-4 mb-5">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?r=cobrador.contratos<?= $filtro?'':'&filtro=todos' ?><?= $busqueda?'&q='.urlencode($busqueda):'' ?>&sort=<?= $orden ?>&p=<?= $pagina - 1 ?>">«</a>
        </li>
        
        <?php for($i = max(1, $pagina - 2); $i <= min($meta['pages'], $pagina + 2); $i++): ?>
            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                <a class="page-link" href="?r=cobrador.contratos<?= $filtro?'':'&filtro=todos' ?><?= $busqueda?'&q='.urlencode($busqueda):'' ?>&sort=<?= $orden ?>&p=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= $pagina >= $meta['pages'] ? 'disabled' : '' ?>">
            <a class="page-link" href="?r=cobrador.contratos<?= $filtro?'':'&filtro=todos' ?><?= $busqueda?'&q='.urlencode($busqueda):'' ?>&sort=<?= $orden ?>&p=<?= $pagina + 1 ?>">»</a>
        </li>
    </ul>
    <div class="text-center text-muted small mt-2">
        Página <?= $pagina ?> de <?= $meta['pages'] ?>
    </div>
  </nav>
  <?php endif; ?>

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

    $monto = trim($_POST['monto'] ?? '') !== '' ? (float)$_POST['monto'] : null;
    $lat = (float)($_POST['latitud'] ?? 0);
    $lng = (float)($_POST['longitud'] ?? 0);
    $fecha_proxima = ($_POST['fecha_proxima_visita'] ?? '') ?: null;
    $notas = $_POST['notas'] ?? '';
    $id_usuario = cobrador_personal_id();

    $id_resultado = cobrador_registrar_gestion($id_contrato, $monto, $id_usuario, $lat, $lng, $fecha_proxima, $notas);

    if ($monto && $id_resultado) {
        redirect('cobrador.ticket&id_abono='.$id_resultado);
    } else {
      $t = qone("SELECT titular FROM vw_titular_contrato WHERE id_contrato=?", [$id_contrato]);
      render('Ticket de visita', (function() use($t,$id_contrato,$fecha_proxima){
          ob_start();
          cobrador_ticket_visita($t['titular'] ?? '—',$id_contrato,$fecha_proxima);
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
          <button type="button" class="btn btn-secondary" onclick="history.back()">Volver</button>
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

case 'pagos':
  $id_contrato = (int)($_GET['id_contrato'] ?? 0);
  if ($id_contrato <= 0) {
    flash("<div class='alert alert-warning'>Contrato inválido.</div>");
    redirect('cobrador.contratos');
  }

  // 1. Obtenemos el descuento del contrato
  $c = qone("SELECT c.id_contrato, c.costo_final, c.descuento, t.titular
             FROM futuro_contratos c
             LEFT JOIN vw_titular_contrato t ON t.id_contrato=c.id_contrato
             WHERE c.id_contrato=? LIMIT 1", [$id_contrato]);

  if (!$c) {
    flash("<div class='alert alert-warning'>Contrato no encontrado.</div>");
    redirect('cobrador.contratos');
  }

  $pagos = qall("
    SELECT a.id_abono, a.fecha_registro, a.cant_abono, a.saldo
    FROM futuro_abonos a
    WHERE a.id_contrato=?
    ORDER BY a.fecha_registro DESC, a.id_abono DESC
  ", [$id_contrato]);

  $sum = qone("SELECT COALESCE(SUM(cant_abono),0) AS total_pagado FROM futuro_abonos WHERE id_contrato=?", [$id_contrato]);
  
  $total_pagado = (float)($sum['total_pagado'] ?? 0);
  $costo_final  = (float)($c['costo_final'] ?? 0);
  $descuento    = (float)($c['descuento'] ?? 0); 
  
  // Calculamos el saldo global actual restando el descuento
  $saldo_calc = $costo_final - $total_pagado;
  if ($saldo_calc < 0) $saldo_calc = 0;

  ob_start(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 m-0">Pagos del contrato #<?= e($id_contrato) ?></h1>
      <div class="text-muted small">
        <span class="me-2"><strong>Titular:</strong> <?= e($c['titular'] ?? '—') ?></span>
        <span class="me-2"><strong>Costo:</strong> $<?= number_format($costo_final+$descuento, 2) ?></span>
        <?php if($descuento > 0): ?>
            <span class="me-2 text-success"><strong>Desc:</strong> -$<?= number_format($descuento, 2) ?></span>
        <?php endif; ?>
        <span class="me-2"><strong>Pagado:</strong> $<?= number_format($total_pagado, 2) ?></span>
        <span><strong>Saldo Actual:</strong> <span class="text-danger fw-semibold">$<?= number_format($saldo_calc, 2) ?></span></span>
      </div>
    </div>
    <div>
      <button type="button" class="btn btn-secondary" onclick="history.back()">Volver</button>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Folio</th>
              <th>Fecha</th>
              <th class="text-end">Monto</th>
              <th class="text-end">Saldo Restante</th>
              <th style="width:170px">Acciones</th>
            </tr>
          </thead>
          <tbody>
<?php 
          // 1. Inicializamos un saldo dinámico tomando el Saldo Actual global
          $saldo_dinamico = $saldo_calc; 

          foreach ($pagos as $p): 
            // 2. El saldo de esta fila es el saldo dinámico en este punto del tiempo
            $saldo_fila = $saldo_dinamico; 
            
            // Evitamos negativos por si hay inconsistencias mayores
            if ($saldo_fila < 0) $saldo_fila = 0;

            // 3. Sumamos el abono actual para que la SIGUIENTE fila (que es un pago más antiguo) 
            // tenga el saldo correcto antes de que se hiciera este pago.
            $saldo_dinamico += (float)$p['cant_abono'];
          ?>
            <tr>
              <td><small class="font-monospace">#<?= str_pad((string)$p['id_abono'], 6, '0', STR_PAD_LEFT) ?></small></td>
              <td><small><?= $p['fecha_registro'] ? date('d/m/Y H:i', strtotime($p['fecha_registro'])) : '—' ?></small></td>
              <td class="text-end"><small>$<?= number_format((float)$p['cant_abono'], 2) ?></small></td>
              
              <td class="text-end"><small class="text-danger">$<?= number_format($saldo_fila, 2) ?></small></td>
              
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="?r=cobrador.ticket&id_abono=<?= (int)$p['id_abono'] ?>" class="btn btn-outline-primary" title="Reimprimir ticket">
                    🧾 <span class="ms-1">Reimprimir</span>
                  </a>
                  <a href="modules/imprimirL.php?id_abono=<?= (int)$p['id_abono'] ?>" target="_blank" class="btn btn-outline-secondary" title="Ver PDF">
                    📄 <span class="ms-1">PDF</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($pagos)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">No hay pagos registrados para este contrato.</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php render('Pagos del contrato', ob_get_clean());
  break;

case 'estado':
  $id_contrato = (int)($_GET['id_contrato'] ?? 0);
  if ($id_contrato <= 0) {
    flash("<div class='alert alert-warning'>Contrato inválido.</div>");
    redirect('cobrador.contratos');
  }

  $c = qone("SELECT c.id_contrato, c.costo_final,c.descuento, c.estatus, t.titular,
                    TRIM(CONCAT(
                      IFNULL(d.calle, ''), ' ',
                      IFNULL(CONCAT('#', d.num_ext), ''), ', ',
                      IFNULL(d.colonia, ''), ', ',
                      IFNULL(d.municipio, '')
                    )) AS direccion
             FROM futuro_contratos c
             LEFT JOIN vw_titular_contrato t ON t.id_contrato=c.id_contrato
             LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
             LEFT JOIN titular_dom td ON td.id_titular = tc.id_titular
             LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
             WHERE c.id_contrato=? LIMIT 1", [$id_contrato]);

  if (!$c) {
    flash("<div class='alert alert-warning'>Contrato no encontrado.</div>");
    redirect('cobrador.contratos');
  }

  $sum = qone("SELECT COALESCE(SUM(cant_abono),0) AS total_pagado FROM futuro_abonos WHERE id_contrato=?", [$id_contrato]);
  $total_pagado = (float)($sum['total_pagado'] ?? 0);
  $costo_final = (float)($c['costo_final'] ?? 0);
  $descuento = (float)($c['descuento'] ?? 0);
  $saldo_calc = $costo_final - $total_pagado;
  if ($saldo_calc < 0) $saldo_calc = 0;

  $movs = qall("
    (SELECT
        'abono' AS tipo,
        a.fecha_registro AS fecha,
        a.id_abono AS folio,
        a.cant_abono AS monto,
        a.saldo AS saldo,
        NULL AS notas,
        COALESCE(p.nombre, '') AS responsable
     FROM futuro_abonos a
     LEFT JOIN futuro_abono_cobrador fac ON fac.id_abono_cobrador=(SELECT MAX(facx.id_abono_cobrador) FROM futuro_abono_cobrador facx WHERE facx.id_abono=a.id_abono)
     LEFT JOIN usuarios p ON p.id = fac.id_usuario
     WHERE a.id_contrato = ?
    )
    UNION ALL
    (SELECT
        'gestion' AS tipo,
        g.fecha_registro AS fecha,
        g.id_gestion AS folio,
        COALESCE(g.cant_abono, 0) AS monto,
        NULL AS saldo,
        g.notas AS notas,
        COALESCE(p2.nombre, '') AS responsable
     FROM futuro_gestion g
     LEFT JOIN usuarios p2 ON p2.id = g.id_usuario
     WHERE g.id_contrato = ?
    )
    ORDER BY fecha DESC, folio DESC
  ", [$id_contrato, $id_contrato]);

  $print = ($_GET['print'] ?? '') === '1';

  ob_start(); ?>

  <style>
    @media print {
      .no-print, .navbar, .footer, .btn { display: none !important; }
      body { background-color: white; padding-top: 0; }
      .card { border: none !important; box-shadow: none !important; }
      .container { max-width: 100% !important; margin: 0; padding: 0; }
    }
  </style>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 m-0">Estado de cuenta — Contrato #<?= e($id_contrato) ?></h1>
      <div class="text-muted small">
        <div><strong>Titular:</strong> <?= e($c['titular'] ?? '—') ?></div>
        <div><strong>Dirección:</strong> <?= e(trim((string)($c['direccion'] ?? '')) ?: '—') ?></div>
        <div class="mt-1">
          <span class="me-2"><strong>Costo:</strong> $<?= number_format($costo_final+$descuento, 2) ?></span>
          <span class="me-2"><strong>Descuento:</strong> $<?= number_format($descuento/2, 2) ?></span>

          <span class="me-2"><strong>Pagado:</strong> $<?= number_format($total_pagado+($descuento/2), 2) ?></span>
          <span><strong>Saldo:</strong> <span class="text-danger fw-semibold">$<?= number_format($saldo_calc, 2) ?></span></span>
        </div>
      </div>
    </div>

    <div class="no-print d-flex gap-2">
      <button onclick="window.print();" class="btn btn-sm btn-primary">🖨️ Imprimir</button>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Folio</th>
              <th class="text-end">Monto</th>
              <th class="text-end">Saldo</th>
              <th>Notas</th>
              <th>Responsable</th>

            </tr>
          </thead>
<tbody>
          <?php 
          // 1. Iniciamos el contador con tu Saldo Actual real ($6,800 en tu ejemplo)
          $saldo_dinamico = $saldo_calc; 

          foreach ($movs as $m): 
            $tipo = $m['tipo'] ?? '';
            $is_abono = $tipo === 'abono';
            
            // 2. Este es el saldo que quedó DESPUÉS de hacer este movimiento
            $saldo_mostrar = $saldo_dinamico;
            
            // 3. Preparamos el saldo para el SIGUIENTE ciclo (hacia el pasado).
            // Solo sumamos el monto si fue un pago real (abono).
            if ($is_abono) {
                $saldo_dinamico += (float)($m['monto'] ?? 0);
            }
          ?>
            <tr>
              <td><small><?= $m['fecha'] ? date('d/m/Y H:i', strtotime($m['fecha'])) : '—' ?></small></td>
              <td><small><?= $is_abono ? 'Pago' : 'Gestión' ?></small></td>
              <td><small class="font-monospace">#<?= str_pad((string)($m['folio'] ?? ''), 6, '0', STR_PAD_LEFT) ?></small></td>
              <td class="text-end"><small><?= $is_abono ? '$'.number_format((float)($m['monto'] ?? 0), 2) : '—' ?></small></td>
              <td class="text-end">
                <small class="text-danger"><?= $is_abono ? '$'.number_format($saldo_mostrar, 2) : '—' ?></small>
              </td>
              <td><small class="text-muted"><?= e($m['notas'] ?? '—') ?></small></td>
              <td><small class="text-muted"><?= e(trim((string)($m['responsable'] ?? '')) ?: '—') ?></small></td>
            </tr>
          <?php endforeach; ?>
            
            <tr>
              <td><small></small></td>
              <td><small></small>Descuento</td>
              <td><small class="font-monospace"></small></td>

              <?php 
              if(!empty($descuento))
              {
                  if($descuento>=300) 
                  { ?>
                  <td class="text-end"><small><?php echo '$'.number_format((float)($descuento/2), 2); ?></small></td>
                  <?php 
                  }
                  else
                  {
                    ?>
                  <td class="text-end"><small><?php echo '$'.number_format((float)($descuento), 2); ?></small></td>
                    <?php
                  }
              }
              else {
                  // Celda vacía para cuadrar si no hay descuento
                  echo '<td class="text-end"></td>';
              }
              ?>

              <td class="text-end">
                <small class="text-danger"></small>
              </td>
              <td><small class="text-muted">$<?= number_format($costo_final, 2) ?></small></td>
              <td><small class="text-muted"></small></td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($print): ?>
    <script>window.print();</script>
  <?php endif; ?>

  <?php render('Estado de cuenta', ob_get_clean());
  break;

case 'ticket':
  $id_abono = (int)($_GET['id_abono'] ?? 0);
  if ($id_abono <= 0) {
    flash("<div class='alert alert-warning'>Ticket inválido.</div>");
    redirect('cobrador.contratos');
  }

  $sql = "
    SELECT 
        a.id_abono, a.fecha_registro, a.cant_abono,
        c.id_contrato, c.estatus, c.tipo_contrato, c.costo_final, c.tipo_pago,
        t.titular,
        COALESCE(p.nombre, ?) AS nombre_cobrador,
        
        -- TOTAL PAGADO HASTA ESE MOMENTO: Sumamos solo los abonos anteriores o iguales a este
        COALESCE((
          SELECT SUM(a2.cant_abono)
          FROM futuro_abonos a2
          WHERE a2.id_contrato = c.id_contrato 
            AND a2.id_abono <= a.id_abono
        ),0) AS total_pagado_historico,
        
        -- SALDO RESTANTE EN ESE MOMENTO: Costo final menos lo pagado hasta ese momento
        GREATEST(
          c.costo_final - COALESCE((
            SELECT SUM(a3.cant_abono)
            FROM futuro_abonos a3
            WHERE a3.id_contrato = c.id_contrato
              AND a3.id_abono <= a.id_abono
          ),0),
          0
        ) AS saldo_historico
        
    FROM futuro_abonos a
    INNER JOIN futuro_contratos c ON c.id_contrato = a.id_contrato
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
    LEFT JOIN futuro_abono_cobrador fac ON fac.id_abono_cobrador=(SELECT MAX(facx.id_abono_cobrador) FROM futuro_abono_cobrador facx WHERE facx.id_abono=a.id_abono)
    LEFT JOIN usuarios p ON p.id = fac.id_usuario
    WHERE a.id_abono = ?
    LIMIT 1
  ";

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
            <span class="ticket-value"><?= str_pad((string)$data['id_abono'], 6, "0", STR_PAD_LEFT) ?></span>
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
            <span class="ticket-value text-break" style="max-width: 60%;"><?= e($data['titular'] ?? '—') ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Tipo:</span>
            <span class="ticket-value"><?= e($data['tipo_contrato'] ?? '—') ?></span>
          </div>
          <div class="ticket-row">
            <span class="ticket-label">Costo Final:</span>
            <span class="ticket-value">$<?= number_format((float)$data['costo_final'], 2) ?></span>
          </div>

          <div class="ticket-row mt-3" style="border-top: 2px solid #000; border-bottom: none;">
            <span class="ticket-label fs-5">SU PAGO:</span>
            <span class="ticket-value fs-5">$<?= number_format((float)$data['cant_abono'], 2) ?></span>
          </div>

          <div class="ticket-row">
            <span class="ticket-label">Saldo Restante:</span>
            <span class="ticket-value text-danger">$<?= number_format((float)$data['saldo_historico'], 2) ?></span>
          </div>

          <div class="mt-4 text-center">
            <p class="mb-1"><small>Cobrador:</small></p>
            <p><strong><?= e($data['nombre_cobrador'] ?? 'Oficina') ?></strong></p>
            <hr>
            <p class="small text-muted">Gracias por su confianza.<br>Fue un placer atenderle.</p>
          </div>

        </div>
      </div>

      <div id="botones" class="d-flex flex-column gap-2 mt-4 no-print">
          <?php 
          $domain = $_SERVER['HTTP_HOST'] ?? 'urena.control.mx';
          $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
          $baseUrl = "$protocol://$domain"; 

          $url_json = $baseUrl . '/modules/imprimirM.php?id_abono=' . (int)$data['id_abono'];
          $url_app = 'my.bluetoothprint.scheme://' . $url_json;

          $link_pdf_directo = "modules/imprimirL.php?id_abono=" . (int)$data['id_abono'];
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
          <button type="button" class="btn btn-secondary" onclick="history.back()">Volver</button>
      </div>

    </div>
  </div>

  <?php render('Ticket de pago', ob_get_clean());
  break;

default:
  redirect('cobrador.panel');
}
