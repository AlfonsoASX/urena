<?php
// app/modules/cajas.php — Inventario de ataúdes con kardex y cambios de estado
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/inventario_v2.php';
require_once __DIR__.'/../core/servicios_operativos.php';
require_once __DIR__.'/../core/almacenes.php';

require_login();
almacenes_ensure_schema();

$action = $action ?? 'listar';
$roles = ['administradora','auxiliar'];
$cajasViewPermissions = ['cajas.ver','cajas.admin'];
$cajasAdminPermission = 'cajas.admin';
require_permission_or_legacy_role($cajasViewPermissions, $roles);
$canAdminCajas = user_has_permission_or_legacy_role($cajasAdminPermission, ['administradora']);
$canManageCajas = user_has_permission_or_legacy_role($cajasAdminPermission, $roles);

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

function caja_material_options(bool $includeAll = false, bool $includePrompt = false): array {
  $options = inventario_caja_materiales();
  if ($includeAll) return ['todos' => 'Todos'] + $options;
  if ($includePrompt) return ['' => 'Selecciona el material'] + $options;
  return $options;
}

function cajas_uso_expr(string $alias = 'c'): string {
  if (db_column_exists('cajas', 'veces_usado')) {
    return "COALESCE({$alias}.veces_usado,0)";
  }
  if (db_column_exists('cajas', 'ciclos_uso')) {
    return "COALESCE({$alias}.ciclos_uso,0)";
  }
  return '0';
}

function caja_condicion(array $caja): string {
  if (function_exists('iv2_caja_estado_usado')) {
    return iv2_caja_estado_usado($caja);
  }
  return !empty($caja['reciclado']) || (int)($caja['veces_usado'] ?? $caja['ciclos_uso'] ?? 0) > 0 ? 'reciclado' : 'nuevo';
}

function caja_condicion_label(array $caja): string {
  return caja_condicion($caja) === 'reciclado' ? 'Usado' : 'Nuevo';
}

function caja_estado_label(string $estado): string {
  return match ($estado) {
    'nuevo' => 'Nuevo',
    'en_uso' => 'En uso',
    'reciclado' => 'Usado / reciclado',
    'fuera_uso' => 'Fuera de uso',
    default => ucwords(str_replace('_', ' ', trim($estado))),
  };
}

function caja_estado_classes(string $estado): string {
  return match ($estado) {
    'nuevo' => 'success',
    'en_uso' => 'warning text-dark',
    'reciclado' => 'info text-dark',
    'fuera_uso' => 'secondary',
    default => 'secondary',
  };
}

function caja_movimiento_label(string $tipo): string {
  return ucwords(str_replace('_', ' ', trim($tipo)));
}

function caja_safe_return_target(?string $target): string {
  $target = trim((string)$target);
  return strpos($target, 'cajas.') === 0 ? $target : 'cajas.listar';
}

function caja_list_return_target(array $state = []): string {
  $query = [];
  $q = trim((string)($state['q'] ?? ''));
  $estado = trim((string)($state['estado'] ?? 'todos'));
  $rentado = trim((string)($state['rentado'] ?? 'todos'));
  $condicion = trim((string)($state['condicion'] ?? 'todos'));
  $material = trim((string)($state['material'] ?? 'todos'));
  $idProveedor = (int)($state['id_proveedor'] ?? 0);
  $idAlmacen = (int)($state['id_almacen'] ?? 0);
  if ($q !== '') $query['q'] = $q;
  if ($estado !== '' && $estado !== 'todos') $query['estado'] = $estado;
  if ($rentado !== '' && $rentado !== 'todos') $query['rentado'] = $rentado;
  if ($condicion !== '' && $condicion !== 'todos') $query['condicion'] = $condicion;
  if ($material !== '' && $material !== 'todos') $query['material'] = $material;
  if ($idProveedor > 0) $query['id_proveedor'] = $idProveedor;
  if ($idAlmacen > 0) $query['id_almacen'] = $idAlmacen;
  return 'cajas.listar'.(!empty($query) ? '&'.http_build_query($query) : '');
}

function caja_estado_mapping(array $caja, string $estado, ?int $recicladoOverride = null): array {
  $estado = trim($estado);
  if (!in_array($estado, ['nuevo', 'en_uso', 'reciclado', 'fuera_uso'], true)) {
    $estado = 'nuevo';
  }
  $recicladoActual = (int)($caja['reciclado'] ?? 0);
  $esRentado = !empty($caja['es_rentado']);
  $reciclado = $recicladoOverride;
  $estatusLogico = null;
  $disponible = null;

  switch ($estado) {
    case 'nuevo':
      $reciclado = 0;
      $estatusLogico = 'disponible';
      $disponible = 1;
      break;
    case 'en_uso':
      if ($reciclado === null) $reciclado = 0;
      $estatusLogico = $esRentado ? 'rentado' : 'baja';
      $disponible = 0;
      break;
    case 'reciclado':
      $reciclado = 1;
      $estatusLogico = 'disponible';
      $disponible = 1;
      break;
    case 'fuera_uso':
      if ($reciclado === null) $reciclado = $recicladoActual;
      $estatusLogico = 'baja';
      $disponible = 0;
      break;
  }

  return [
    'estado' => $estado,
    'reciclado' => $reciclado,
    'estatus_logico' => $estatusLogico,
    'disponible' => $disponible,
  ];
}

function caja_aplicar_estado(PDO $pdo, array $caja, string $estado, string $tipoMovimiento = 'ajuste_estado', ?string $notas = null, ?int $recicladoOverride = null): array {
  $codigo = trim((string)($caja['codigo'] ?? ''));
  if ($codigo === '') {
    throw new RuntimeException('Ataúd no válido.');
  }
  $map = caja_estado_mapping($caja, $estado, $recicladoOverride);
  $set = [];
  $params = [];
  if (db_column_exists('cajas', 'estado')) {
    $set[] = 'estado=?';
    $params[] = $map['estado'];
  }
  if (db_column_exists('cajas', 'reciclado')) {
    $set[] = 'reciclado=?';
    $params[] = $map['reciclado'];
  }
  if (db_column_exists('cajas', 'estatus_logico') && $map['estatus_logico'] !== null) {
    $set[] = 'estatus_logico=?';
    $params[] = $map['estatus_logico'];
  }
  if (db_column_exists('cajas', 'disponible') && $map['disponible'] !== null) {
    $set[] = 'disponible=?';
    $params[] = $map['disponible'];
  }
  if (db_column_exists('cajas', 'updated_at')) {
    $set[] = 'updated_at=CURRENT_TIMESTAMP()';
  }
  if (!empty($set)) {
    $params[] = $codigo;
    q("UPDATE cajas SET ".implode(', ', $set)." WHERE codigo=?", $params);
  }
  if ($tipoMovimiento !== '') {
    caja_registrar_mov($pdo, $codigo, $tipoMovimiento, null, $notas);
  }
  return $map;
}

function caja_kardex(string $codigo): array {
  $movs = [];
  if (db_table_exists('cajas_movimientos')) {
    try {
      foreach (qall("SELECT tipo, id_servicio, notas, created_at FROM cajas_movimientos WHERE codigo=? ORDER BY id_mov DESC", [$codigo]) as $row) {
        $movs[] = [
          'tipo' => $row['tipo'],
          'id_servicio' => $row['id_servicio'],
          'notas' => $row['notas'],
          'created_at' => $row['created_at'],
          'fuente' => 'inventario',
        ];
      }
    } catch (Throwable $e) {
    }
  }
  if (db_table_exists('servicio_caja_evento')) {
    try {
      foreach (qall("SELECT tipo_evento, id_servicio, observaciones, created_at FROM servicio_caja_evento WHERE codigo_caja=? ORDER BY id DESC", [$codigo]) as $row) {
        $movs[] = [
          'tipo' => $row['tipo_evento'],
          'id_servicio' => $row['id_servicio'],
          'notas' => $row['observaciones'],
          'created_at' => $row['created_at'],
          'fuente' => 'servicio',
        ];
      }
    } catch (Throwable $e) {
    }
  }
  usort($movs, static function (array $a, array $b): int {
    return strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? ''));
  });
  return $movs;
}

function cajas_activos_map(array $codigos): array {
  $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos))));
  $map = [];
  foreach ($codigos as $codigo) {
    $map[$codigo] = false;
  }
  if (empty($codigos)) {
    return $map;
  }
  $placeholders = implode(', ', array_fill(0, count($codigos), '?'));

  if (db_table_exists('servicio_caja')) {
    try {
      $expr = function_exists('iv2_servicio_caja_codigo_expr') ? iv2_servicio_caja_codigo_expr('sc') : "sc.codigo_caja";
      $whereServicio = function_exists('iv2_servicio_activo_sql') ? iv2_servicio_activo_sql('s') : "COALESCE(s.eliminado,0)=0 AND COALESCE(s.cerrado,0)=0";
      $rows = qall(
        "SELECT DISTINCT TRIM(COALESCE({$expr}, '')) AS codigo
         FROM servicio_caja sc
         JOIN servicios s ON s.id_servicio = sc.id_servicio
         WHERE TRIM(COALESCE({$expr}, '')) IN ({$placeholders})
           AND {$whereServicio}",
        $codigos
      );
      foreach ($rows as $row) {
        $codigo = trim((string)($row['codigo'] ?? ''));
        if ($codigo !== '') $map[$codigo] = true;
      }
    } catch (Throwable $e) {
    }
  }

  if (db_table_exists('renta_cajas')) {
    try {
      $whereServicio = function_exists('iv2_servicio_activo_sql') ? iv2_servicio_activo_sql('s') : "COALESCE(s.eliminado,0)=0 AND COALESCE(s.cerrado,0)=0";
      $rows = qall(
        "SELECT DISTINCT rc.codigo_caja AS codigo
         FROM renta_cajas rc
         JOIN servicios s ON s.id_servicio = rc.id_servicio
         WHERE rc.codigo_caja IN ({$placeholders})
           AND rc.fecha_devolucion IS NULL
           AND {$whereServicio}",
        $codigos
      );
      foreach ($rows as $row) {
        $codigo = trim((string)($row['codigo'] ?? ''));
        if ($codigo !== '') $map[$codigo] = true;
      }
    } catch (Throwable $e) {
    }
  }

  if (db_table_exists('cajas_movimientos')) {
    try {
      $rows = qall(
        "SELECT cm.codigo, cm.tipo
         FROM cajas_movimientos cm
         JOIN (
           SELECT codigo, MAX(id_mov) AS max_id
           FROM cajas_movimientos
           WHERE codigo IN ({$placeholders})
           GROUP BY codigo
         ) last_mov ON last_mov.max_id = cm.id_mov",
        $codigos
      );
      foreach ($rows as $row) {
        if (($row['tipo'] ?? '') === 'asignacion') {
          $codigo = trim((string)($row['codigo'] ?? ''));
          if ($codigo !== '') $map[$codigo] = true;
        }
      }
    } catch (Throwable $e) {
    }
  }

  return $map;
}

function caja_log(string $accion, array $payload = []): void {
  $user = current_user();
  $detalle = json_encode(array_merge(['accion' => $accion], $payload), JSON_UNESCAPED_UNICODE);
  try {
    q(
      "INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at) VALUES ('cajas', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$accion, $user['usuario'] ?? 'sistema', $detalle]
    );
  } catch (Exception $e) {
  }
}

function caja_registrar_mov(PDO $pdo, string $codigo, string $tipo, ?int $id_servicio = null, ?string $notas = null): void {
  $stmt = $pdo->prepare("INSERT INTO cajas_movimientos (codigo,tipo,id_servicio,notas) VALUES (?,?,?,?)");
  $stmt->execute([$codigo, $tipo, $id_servicio, $notas]);
}

$catalogAdminOnly = ['nuevo', 'guardar', 'editar', 'actualizar', 'baja'];
if (in_array($action, $catalogAdminOnly, true)) {
  require_permission_or_legacy_role($cajasAdminPermission, ['administradora']);
}
$stateAdminOnly = ['reciclar', 'estado'];
if (in_array($action, $stateAdminOnly, true)) {
  require_permission_or_legacy_role($cajasAdminPermission, $roles);
}

switch ($action) {
case 'listar':
  $q = trim($_GET['q'] ?? '');
  $estado = trim($_GET['estado'] ?? 'todos');
  $rentado = trim($_GET['rentado'] ?? 'todos');
  $proveedor = (int)($_GET['id_proveedor'] ?? 0);
  $almacen = (int)($_GET['id_almacen'] ?? 0);
  $condicion = trim($_GET['condicion'] ?? 'todos');
  $material = trim($_GET['material'] ?? 'todos');

  $where = ['COALESCE(c.eliminado,0)=0'];
  $params = [];
  $usoExpr = cajas_uso_expr('c');

  if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(c.codigo LIKE ? OR c.modelo LIKE ? OR c.material LIKE ? OR c.color LIKE ? OR c.ubicacion LIKE ? OR c.proveedor LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like, $like);
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
  if ($almacen > 0) {
    $where[] = "c.id_almacen = ?";
    $params[] = $almacen;
  }
  if ($condicion === 'nuevos') {
    $where[] = "(COALESCE(c.reciclado,0)=0 AND {$usoExpr}=0)";
  } elseif ($condicion === 'usados') {
    $where[] = "(COALESCE(c.reciclado,0)=1 OR {$usoExpr}>0)";
  }
  $materialNormalizado = inventario_caja_material_normalizar($material);
  if ($material !== 'todos' && $materialNormalizado !== null) {
    $where[] = 'c.material=?';
    $params[] = $materialNormalizado;
  }

  $rows = qall(
    "SELECT c.codigo, c.modelo, c.material, c.estado, c.ubicacion, c.color,
            {$usoExpr} AS veces_usado,
            COALESCE(c.ciclos_uso,0) AS ciclos_uso,
            COALESCE(c.reciclado,0) AS reciclado,
            COALESCE(c.es_rentado,0) AS es_rentado,
            c.id_proveedor, c.id_almacen, c.proveedor, DATE(c.updated_at) AS actualizado,
            p.nombre AS proveedor_nombre,
            al.nombre AS almacen_nombre
     FROM cajas c
     LEFT JOIN proveedores p ON p.id_proveedor = c.id_proveedor
     LEFT JOIN almacenes al ON al.id_almacen = c.id_almacen
     WHERE ".implode(' AND ', $where)."
     ORDER BY c.created_at DESC
     LIMIT 300",
    $params
  );
  $activosMap = cajas_activos_map(array_column($rows, 'codigo'));
  $proveedores = cajas_proveedores();
  $prov_opts = ['0' => 'Todos'];
  foreach ($proveedores as $prov) {
    $prov_opts[(string)$prov['id_proveedor']] = $prov['nombre'];
  }
  $almacen_opts = ['0' => 'Todos'] + array_diff_key(almacenes_options(null, 'Todos'), ['' => true]);
  $returnTarget = caja_list_return_target([
    'q' => $q,
    'estado' => $estado,
    'rentado' => $rentado,
    'id_proveedor' => $proveedor,
    'condicion' => $condicion,
    'material' => $material,
    'id_almacen' => $almacen,
  ]);
  $nuevosCount = 0;
  $usadosCount = 0;
  $activosCount = 0;
  foreach ($rows as $row) {
    if (caja_condicion($row) === 'reciclado') {
      $usadosCount++;
    } else {
      $nuevosCount++;
    }
    if (!empty($activosMap[$row['codigo']])) {
      $activosCount++;
    }
  }

  ob_start(); ?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Ataúdes</h1>
      <div class="text-muted small">Inventario con estado operativo, condición de uso y kardex completo.</div>
    </div>
    <?php if ($canAdminCajas): ?>
      <a href="?r=cajas.nuevo" class="btn btn-success btn-sm">Nuevo ataúd</a>
    <?php endif; ?>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge text-bg-light border"><?= count($rows) ?> visibles</span>
    <span class="badge text-bg-success"><?= $nuevosCount ?> nuevos</span>
    <span class="badge text-bg-warning text-dark"><?= $usadosCount ?> usados</span>
    <span class="badge text-bg-secondary"><?= $activosCount ?> en servicio</span>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="r" value="cajas.listar">
    <div class="col-md-3"><?= form_input('q', 'Buscar', $q, ['placeholder' => 'Código, modelo, material o color']) ?></div>
    <div class="col-md-2"><?= form_select('material', 'Material', caja_material_options(true), $material, ['class' => 'form-select']) ?></div>
    <div class="col-md-2"><?= form_select('estado', 'Estado', ['todos' => 'Todos', 'nuevo' => 'Nuevo', 'en_uso' => 'En uso', 'reciclado' => 'Reciclado', 'fuera_uso' => 'Fuera de uso'], $estado, ['class' => 'form-select']) ?></div>
    <div class="col-md-2"><?= form_select('condicion', 'Condición', ['todos' => 'Todos', 'nuevos' => 'Nuevos', 'usados' => 'Usados'], $condicion, ['class' => 'form-select']) ?></div>
    <div class="col-md-2"><?= form_select('rentado', 'Renta', ['todos' => 'Todos', 'si' => 'Rentados', 'no' => 'Venta'], $rentado, ['class' => 'form-select']) ?></div>
    <div class="col-md-3"><?= form_select('id_proveedor', 'Proveedor', $prov_opts, (string)$proveedor, ['class' => 'form-select']) ?></div>
    <div class="col-md-3"><?= form_select('id_almacen', 'Almacén', $almacen_opts, (string)$almacen, ['class' => 'form-select']) ?></div>
    <div class="col-12 col-md-2 d-grid mt-2 mt-md-0">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <?php if ($canManageCajas): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body py-3">
        <form id="bulkStateForm" method="post" action="?r=cajas.estado" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <input type="hidden" name="return_to" value="<?= e($returnTarget) ?>">
          <div class="col-md-4">
            <?= form_select('estado_destino', 'Cambio masivo', [
              '' => 'Selecciona una acción',
              'nuevo' => 'Marcar como nuevo',
              'reciclado' => 'Marcar como usado / reciclado',
              'fuera_uso' => 'Mandar a fuera de uso',
            ], '', ['class' => 'form-select', 'required' => true]) ?>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">Seleccionados: <strong id="selectedCount">0</strong></div>
            <div class="small text-muted">Las piezas en servicio quedan bloqueadas para evitar desalineación con servicios activos.</div>
          </div>
          <div class="col-md-4 d-grid d-md-flex justify-content-md-end">
            <button class="btn btn-primary" id="bulkSubmit" disabled>Aplicar a seleccionados</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <?php if ($canManageCajas): ?>
            <th style="width:36px"><input type="checkbox" class="form-check-input" id="bulkCheckAll"></th>
          <?php endif; ?>
          <th>Código</th>
          <th>Modelo</th>
          <th>Material</th>
          <th>Estado</th>
          <th>Condición</th>
          <th>Rentado</th>
          <th>Proveedor</th>
          <th>Almacén</th>
          <th>Ciclos</th>
          <th>Actualizado</th>
          <th style="width:290px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <?php
          $codigo = (string)$r['codigo'];
          $activo = !empty($activosMap[$codigo]) || ($r['estado'] ?? '') === 'en_uso';
          $condicionLabel = caja_condicion_label($r);
          $condicionClass = $condicionLabel === 'Usado' ? 'warning text-dark' : 'success';
        ?>
        <tr>
          <?php if ($canManageCajas): ?>
            <td>
              <input
                class="form-check-input js-caja-check"
                type="checkbox"
                name="codigos[]"
                value="<?= e($codigo) ?>"
                form="bulkStateForm"
                <?= $activo ? 'disabled' : '' ?>
              >
            </td>
          <?php endif; ?>
          <td><code><?= e($codigo) ?></code></td>
          <td><?= e($r['modelo']) ?></td>
          <td><?= e(inventario_caja_material_label($r['material'] ?? null)) ?></td>
          <td><span class="badge bg-<?= caja_estado_classes((string)$r['estado']) ?>"><?= e(caja_estado_label((string)$r['estado'])) ?></span></td>
          <td>
            <span class="badge text-bg-<?= $condicionClass ?>"><?= e($condicionLabel) ?></span>
            <?php if ($activo): ?><span class="badge text-bg-secondary ms-1">En servicio</span><?php endif; ?>
          </td>
          <td><?= $r['es_rentado'] ? 'Sí' : 'No' ?></td>
          <td><?= e($r['proveedor_nombre'] ?? $r['proveedor'] ?? '—') ?></td>
          <td><?= e(($r['almacen_nombre'] ?? $r['ubicacion'] ?? '') ?: '—') ?></td>
          <td><?= (int)($r['veces_usado'] ?? $r['ciclos_uso'] ?? 0) ?></td>
          <td><?= e($r['actualizado'] ?: '—') ?></td>
          <td class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="?r=cajas.historial&codigo=<?= urlencode($codigo) ?>">Kardex</a>
            <?php if ($canAdminCajas): ?>
              <a class="btn btn-outline-primary btn-sm" href="?r=cajas.editar&codigo=<?= urlencode($codigo) ?>">Editar</a>
              <a class="btn btn-outline-danger btn-sm" href="?r=cajas.baja&codigo=<?= urlencode($codigo) ?>" onclick="return confirm('¿Dar de baja lógica este ataúd?');">Baja</a>
            <?php endif; ?>
            <?php if ($canManageCajas && !$activo && caja_condicion($r) !== 'reciclado'): ?>
              <form method="post" action="?r=cajas.estado" class="d-inline-flex">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($returnTarget) ?>">
                <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                <input type="hidden" name="estado_destino" value="reciclado">
                <button class="btn btn-outline-success btn-sm" onclick="return confirm('¿Marcar este ataúd como usado / reciclado?');">Marcar usado</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $canManageCajas ? 12 : 11 ?>" class="text-center text-muted">Sin registros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
  (() => {
    const master = document.getElementById('bulkCheckAll');
    const countEl = document.getElementById('selectedCount');
    const submit = document.getElementById('bulkSubmit');
    const checks = () => Array.from(document.querySelectorAll('.js-caja-check'));

    function sync() {
      const enabled = checks().filter((check) => !check.disabled);
      const checked = enabled.filter((check) => check.checked).length;
      if (countEl) countEl.textContent = String(checked);
      if (submit) submit.disabled = checked === 0;
      if (!master) return;
      master.checked = enabled.length > 0 && checked === enabled.length;
      master.indeterminate = checked > 0 && checked < enabled.length;
    }

    master?.addEventListener('change', () => {
      checks().forEach((check) => {
        if (!check.disabled) check.checked = master.checked;
      });
      sync();
    });
    checks().forEach((check) => check.addEventListener('change', sync));
    sync();
  })();
  </script>
  <?php
  render('Ataúdes', ob_get_clean());
  break;

case 'nuevo':
  $proveedores = cajas_proveedores();
  $prov_opts = ['' => 'Sin proveedor'];
  foreach ($proveedores as $prov) {
    $prov_opts[$prov['id_proveedor']] = $prov['nombre'];
  }
  $almacen_opts = almacenes_options(null, 'Sin almacen');
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuevo ataúd</h1>
      <form method="post" action="?r=cajas.guardar">
        <?= csrf_field() ?>
        <?= form_input('codigo', 'Código', '', ['required' => true, 'maxlength' => 50]) ?>
        <?= form_input('modelo', 'Modelo', '', ['required' => true]) ?>
        <?= form_select('material', 'Material', caja_material_options(false, true), '', ['class' => 'form-select', 'required' => true]) ?>
        <?= form_select('estado', 'Estado', ['nuevo' => 'Nuevo', 'en_uso' => 'En uso', 'reciclado' => 'Reciclado', 'fuera_uso' => 'Fuera de uso'], 'nuevo', ['class' => 'form-select']) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_almacen', 'Almacén', $almacen_opts, '', ['class' => 'form-select']) ?></div>
          <div class="col-md-6"><?= form_input('color', 'Color', '') ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_proveedor', 'Proveedor', $prov_opts, '', ['class' => 'form-select']) ?></div>
          <div class="col-md-3"><?= form_input('costo', 'Costo', '0', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?></div>
          <div class="col-md-3"><?= form_select('es_rentado', '¿Es para renta?', ['0' => 'No', '1' => 'Sí'], '0', ['class' => 'form-select']) ?></div>
        </div>
        <?= form_input('notas', 'Notas', '') ?>
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
  $material = inventario_caja_material_normalizar($_POST['material'] ?? null);
  $estado = trim($_POST['estado'] ?? 'nuevo');
  $color = trim($_POST['color'] ?? '');
  $id_proveedor = (int)($_POST['id_proveedor'] ?? 0) ?: null;
  try {
    [$id_almacen, $almacen_nombre] = almacen_resolver_post('id_almacen', false);
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-warning'>".e($e->getMessage())."</div>");
    redirect('cajas.nuevo');
  }
  $ubicacion = $almacen_nombre ?: '';
  $costo = (float)($_POST['costo'] ?? 0);
  $es_rentado = (int)($_POST['es_rentado'] ?? 0) ? 1 : 0;
  $notas = trim($_POST['notas'] ?? '');
  if ($codigo === '' || $modelo === '' || $material === null) {
    flash("<div class='alert alert-danger'>Código, modelo y material son obligatorios.</div>");
    redirect('cajas.nuevo');
  }
  $pdo = db();
  $seed = ['codigo' => $codigo, 'es_rentado' => $es_rentado, 'reciclado' => 0];
  $state = caja_estado_mapping($seed, $estado, null);
  try {
    $pdo->beginTransaction();
    q(
      "INSERT INTO cajas (codigo,modelo,material,estado,ubicacion,color,id_proveedor,id_almacen,costo,es_rentado,reciclado,ciclos_uso,proveedor,notas,eliminado,created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, '', ?, 0, CURRENT_TIMESTAMP())",
      [$codigo,$modelo,$material,$state['estado'],$ubicacion,$color,$id_proveedor,$id_almacen,$costo,$es_rentado,$state['reciclado'],$notas]
    );
    if (db_column_exists('cajas', 'estatus_logico')) {
      q("UPDATE cajas SET estatus_logico=? WHERE codigo=?", [$state['estatus_logico'], $codigo]);
    }
    if (db_column_exists('cajas', 'disponible')) {
      q("UPDATE cajas SET disponible=? WHERE codigo=?", [$state['disponible'], $codigo]);
    }
    caja_registrar_mov($pdo, $codigo, 'alta', null, 'Registro inicial');
    caja_log('alta', ['codigo' => $codigo, 'material' => $material]);
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
  $almacen_opts = almacenes_options((int)($caja['id_almacen'] ?? 0), 'Sin almacen');
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Editar ataúd <?= e($caja['codigo']) ?></h1>
      <form method="post" action="?r=cajas.actualizar&codigo=<?= urlencode($caja['codigo']) ?>">
        <?= csrf_field() ?>
        <?= form_input('modelo', 'Modelo', $caja['modelo'], ['required' => true]) ?>
        <?= form_select('material', 'Material', caja_material_options(false, true), (string)($caja['material'] ?? ''), ['class' => 'form-select', 'required' => true]) ?>
        <?= form_select('estado', 'Estado', ['nuevo' => 'Nuevo', 'en_uso' => 'En uso', 'reciclado' => 'Reciclado', 'fuera_uso' => 'Fuera de uso'], $caja['estado'], ['class' => 'form-select']) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_almacen', 'Almacén', $almacen_opts, (string)($caja['id_almacen'] ?? ''), ['class' => 'form-select']) ?></div>
          <div class="col-md-6"><?= form_input('color', 'Color', $caja['color']) ?></div>
        </div>
        <div class="row g-2">
          <div class="col-md-6"><?= form_select('id_proveedor', 'Proveedor', $prov_opts, (string)($caja['id_proveedor'] ?? ''), ['class' => 'form-select']) ?></div>
          <div class="col-md-3"><?= form_input('costo', 'Costo', $caja['costo'] ?? '0', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?></div>
          <div class="col-md-3"><?= form_select('es_rentado', '¿Es para renta?', ['0' => 'No', '1' => 'Sí'], (string)$caja['es_rentado'], ['class' => 'form-select']) ?></div>
        </div>
        <?= form_input('notas', 'Notas', $caja['notas'] ?? '') ?>
        <div class="row g-2">
          <div class="col-md-6"><?= form_input('ciclos_uso', 'Ciclos de uso', $caja['ciclos_uso'], ['type' => 'number', 'min' => 0]) ?></div>
          <div class="col-md-6"><?= form_select('reciclado', '¿Reciclado?', ['0' => 'No', '1' => 'Sí'], (string)$caja['reciclado'], ['class' => 'form-select']) ?></div>
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
  $material = inventario_caja_material_normalizar($_POST['material'] ?? null);
  $estado = trim($_POST['estado'] ?? 'nuevo');
  $color = trim($_POST['color'] ?? '');
  $id_proveedor = (int)($_POST['id_proveedor'] ?? 0) ?: null;
  try {
    [$id_almacen, $almacen_nombre] = almacen_resolver_post('id_almacen', false);
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-warning'>".e($e->getMessage())."</div>");
    redirect('cajas.editar&codigo='.$codigo);
  }
  $ubicacion = $almacen_nombre ?: (string)($caja['ubicacion'] ?? '');
  $costo = (float)($_POST['costo'] ?? 0);
  $es_rentado = (int)($_POST['es_rentado'] ?? 0) ? 1 : 0;
  $notas = trim($_POST['notas'] ?? '');
  $ciclos = max(0, (int)($_POST['ciclos_uso'] ?? 0));
  $reciclado = (int)($_POST['reciclado'] ?? 0) ? 1 : 0;
  if ($modelo === '' || $material === null) {
    flash("<div class='alert alert-danger'>El modelo y el material son obligatorios.</div>");
    redirect('cajas.editar&codigo='.$codigo);
  }
  $pdo = db();
  $state = caja_estado_mapping(array_merge($caja, ['es_rentado' => $es_rentado]), $estado, $reciclado);
  try {
    $pdo->beginTransaction();
    $set = [
      'modelo=?',
      'material=?',
      'estado=?',
      'ubicacion=?',
      'color=?',
      'id_proveedor=?',
      'id_almacen=?',
      'costo=?',
      'es_rentado=?',
      'notas=?',
      'ciclos_uso=?',
      'reciclado=?',
    ];
    $params = [
      $modelo,
      $material,
      $state['estado'],
      $ubicacion,
      $color,
      $id_proveedor,
      $id_almacen,
      $costo,
      $es_rentado,
      $notas,
      $ciclos,
      $state['reciclado'],
    ];
    if (db_column_exists('cajas', 'estatus_logico')) {
      $set[] = 'estatus_logico=?';
      $params[] = $state['estatus_logico'];
    }
    if (db_column_exists('cajas', 'disponible')) {
      $set[] = 'disponible=?';
      $params[] = $state['disponible'];
    }
    $set[] = 'updated_at=CURRENT_TIMESTAMP()';
    $params[] = $codigo;
    q("UPDATE cajas SET ".implode(', ', $set)." WHERE codigo=?", $params);
    if (($caja['estado'] ?? '') !== $state['estado'] || (int)($caja['reciclado'] ?? 0) !== (int)$state['reciclado']) {
      caja_registrar_mov(
        $pdo,
        $codigo,
        'ajuste_estado',
        null,
        'Edición manual: '.caja_estado_label((string)($caja['estado'] ?? 'nuevo')).' -> '.caja_estado_label($state['estado'])
      );
    }
    caja_log('actualizar', ['codigo' => $codigo, 'material' => $material]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataúd actualizado.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo actualizar el ataúd.</div>");
  }
  redirect('cajas.listar');
  break;

case 'baja':
  $codigo = trim($_GET['codigo'] ?? '');
  if ($codigo === '') redirect('cajas.listar');
  $pdo = db();
  try {
    $pdo->beginTransaction();
    q("UPDATE cajas SET eliminado=1, updated_at=CURRENT_TIMESTAMP() WHERE codigo=?", [$codigo]);
    caja_registrar_mov($pdo, $codigo, 'baja', null, 'Baja lógica manual');
    caja_log('baja', ['codigo' => $codigo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataúd dado de baja lógica.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo dar de baja el ataúd.</div>");
  }
  redirect('cajas.listar');
  break;

case 'reciclar':
  $codigo = trim($_GET['codigo'] ?? '');
  $caja = caja_find($codigo);
  if (!$caja) {
    flash("<div class='alert alert-warning'>Ataúd no encontrado.</div>");
    redirect('cajas.listar');
  }
  $activosMap = cajas_activos_map([$codigo]);
  if (!empty($activosMap[$codigo]) || trim((string)($caja['estado'] ?? '')) === 'en_uso') {
    flash("<div class='alert alert-warning'>No puedes marcar como usado un ataúd con servicio activo.</div>");
    redirect('cajas.listar');
  }
  $pdo = db();
  try {
    $pdo->beginTransaction();
    caja_aplicar_estado($pdo, $caja, 'reciclado', 'ajuste_estado', 'Marcado como usado manualmente.');
    caja_log('reciclar', ['codigo' => $codigo]);
    $pdo->commit();
    flash("<div class='alert alert-success'>Ataúd marcado como usado / reciclado.</div>");
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo actualizar el estado del ataúd.</div>");
  }
  redirect('cajas.listar');
  break;

case 'estado':
  if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    redirect('cajas.listar');
  }
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('cajas.listar'); }
  $returnTarget = caja_safe_return_target($_POST['return_to'] ?? 'cajas.listar');
  $estadoDestino = trim((string)($_POST['estado_destino'] ?? ''));
  if (!in_array($estadoDestino, ['nuevo', 'reciclado', 'fuera_uso'], true)) {
    flash("<div class='alert alert-warning'>Selecciona un estado válido.</div>");
    redirect($returnTarget);
  }
  $codigo = trim((string)($_POST['codigo'] ?? ''));
  $codigos = array_merge($codigo !== '' ? [$codigo] : [], (array)($_POST['codigos'] ?? []));
  $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos))));
  if (empty($codigos)) {
    flash("<div class='alert alert-warning'>Selecciona al menos un ataúd.</div>");
    redirect($returnTarget);
  }
  $activosMap = cajas_activos_map($codigos);
  $pdo = db();
  $actualizados = [];
  $omitidos = [];
  $faltantes = [];
  try {
    $pdo->beginTransaction();
    foreach ($codigos as $itemCodigo) {
      $caja = caja_find($itemCodigo);
      if (!$caja) {
        $faltantes[] = $itemCodigo;
        continue;
      }
      if (!empty($activosMap[$itemCodigo]) || trim((string)($caja['estado'] ?? '')) === 'en_uso') {
        $omitidos[] = $itemCodigo;
        continue;
      }
      caja_aplicar_estado(
        $pdo,
        $caja,
        $estadoDestino,
        'ajuste_estado',
        'Cambio manual a '.caja_estado_label($estadoDestino).'.'
      );
      $actualizados[] = $itemCodigo;
    }
    if (!empty($actualizados)) {
      caja_log('estado', ['estado_destino' => $estadoDestino, 'codigos' => $actualizados]);
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash("<div class='alert alert-danger'>No se pudo actualizar el estado de los ataúdes.</div>");
    redirect($returnTarget);
  }

  $mensajes = [];
  if (!empty($actualizados)) {
    $mensajes[] = count($actualizados).' actualizado(s) a '.strtolower(caja_estado_label($estadoDestino));
  }
  if (!empty($omitidos)) {
    $mensajes[] = count($omitidos).' omitido(s) por estar en servicio';
  }
  if (!empty($faltantes)) {
    $mensajes[] = count($faltantes).' no encontrado(s)';
  }
  if (empty($mensajes)) {
    $mensajes[] = 'No hubo cambios';
  }
  $alertType = !empty($actualizados) ? 'success' : 'warning';
  flash("<div class='alert alert-{$alertType}'>".e(implode('. ', $mensajes)).".</div>");
  redirect($returnTarget);
  break;

case 'historial':
  $codigo = trim($_GET['codigo'] ?? '');
  $caja = caja_find($codigo);
  if (!$caja) {
    flash("<div class='alert alert-warning'>Ataúd no encontrado.</div>");
    redirect('cajas.listar');
  }
  $movs = caja_kardex($codigo);
  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h5 m-0">Kardex · <?= e($caja['codigo']) ?> (<?= e($caja['modelo']) ?>)</h1>
      <div class="text-muted small">Historial consolidado de inventario y operaciones de servicio.</div>
    </div>
    <a href="?r=cajas.listar" class="btn btn-light btn-sm">Volver</a>
  </div>
  <div class="mb-3 d-flex flex-wrap gap-2">
    <span class="badge bg-<?= caja_estado_classes((string)$caja['estado']) ?>">Estado: <?= e(caja_estado_label((string)$caja['estado'])) ?></span>
    <span class="badge bg-info text-dark">Ciclos: <?= (int)($caja['veces_usado'] ?? $caja['ciclos_uso'] ?? 0) ?></span>
    <span class="badge bg-<?= !empty($caja['es_rentado']) ? 'success' : 'dark' ?>"><?= !empty($caja['es_rentado']) ? 'Rentado' : 'Venta' ?></span>
    <span class="badge text-bg-<?= caja_condicion($caja) === 'reciclado' ? 'warning text-dark' : 'success' ?>">Condición: <?= e(caja_condicion_label($caja)) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead><tr><th>Fecha</th><th>Movimiento</th><th>Servicio</th><th>Fuente</th><th>Notas</th></tr></thead>
      <tbody>
        <?php foreach ($movs as $m): ?>
        <tr>
          <td><?= !empty($m['created_at']) ? e(date('Y-m-d H:i', strtotime($m['created_at']))) : '—' ?></td>
          <td><?= e(caja_movimiento_label((string)($m['tipo'] ?? ''))) ?></td>
          <td><?= !empty($m['id_servicio']) ? '<a href="?r=servicios.ver&id='.(int)$m['id_servicio'].'">#'.(int)$m['id_servicio'].'</a>' : '—' ?></td>
          <td><span class="badge text-bg-light border"><?= e(($m['fuente'] ?? 'inventario') === 'servicio' ? 'Servicio' : 'Inventario') ?></span></td>
          <td><?= e($m['notas'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movs)): ?>
        <tr><td colspan="5" class="text-center text-muted">Sin movimientos registrados</td></tr>
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
