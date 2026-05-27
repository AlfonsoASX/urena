<?php

require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/inventario_v2.php';
require_once __DIR__.'/almacenes.php';

function so_table_exists(string $table): bool {
  return db_table_exists($table);
}

function so_column_exists(string $table, string $column): bool {
  return db_column_exists($table, $column);
}

function so_add_column_if_missing(string $table, string $column, string $ddl): void {
  db_add_column_if_missing($table, $column, $ddl);
}

function so_ensure_servicios_estatus_shape(): void {
  static $checked = false;
  if ($checked) return;
  $checked = true;

  if (!so_table_exists('servicios')) return;

  try {
    $meta = qone("
      SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'servicios'
        AND COLUMN_NAME = 'estatus'
      LIMIT 1
    ");
  } catch (Throwable $e) {
    return;
  }

  if (!$meta) return;
  $dataType = strtolower((string)($meta['DATA_TYPE'] ?? ''));
  $maxLength = (int)($meta['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
  if ($dataType === 'varchar' && $maxLength >= 30) return;

  try {
    q("ALTER TABLE servicios MODIFY COLUMN estatus VARCHAR(30) NULL DEFAULT 'registrado'");
    db_invalidate_schema_cache('servicios');
    db_schema_snapshot_clear_file();
  } catch (Throwable $e) {
    db_log_error($e, ['scope' => 'ensure_servicios_estatus_shape']);
  }
}

function so_ensure_runtime_schema_compat(): void {
  so_ensure_servicios_estatus_shape();
}

function so_has_value($value, bool $isDate = false): bool {
  if ($value === null) return false;
  $text = trim((string)$value);
  if ($text === '') return false;
  if ($isDate) {
    return !in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true);
  }
  return true;
}

function so_existing_column(string $table, array $candidates): ?string {
  foreach ($candidates as $candidate) {
    $candidate = trim((string)$candidate);
    if ($candidate !== '' && db_column_exists($table, $candidate)) {
      return $candidate;
    }
  }
  return null;
}

function so_backfill_servicios_fallecidos(): void {
  if (!so_table_exists('servicios') || !so_table_exists('servicio_fallecido') || !so_table_exists('fallecido')) return;
  $servicioFallecidoIdColumn = so_existing_column('servicio_fallecido', ['id_rel', 'id_falle_serv']);
  if (!$servicioFallecidoIdColumn) return;

  $legacySelect = [
    db_column_exists('fallecido', 'nom_fallecido') ? "COALESCE(f.nom_fallecido,'') AS legacy_fallecido_nombre" : "'' AS legacy_fallecido_nombre",
    db_column_exists('fallecido', 'fecha') ? "f.fecha AS legacy_fallecido_fecha" : "NULL AS legacy_fallecido_fecha",
    db_column_exists('fallecido', 'municipio') ? "COALESCE(f.municipio,'') AS legacy_fallecido_municipio" : "'' AS legacy_fallecido_municipio",
    db_column_exists('fallecido', 'hospital') ? "COALESCE(f.hospital,'') AS legacy_fallecido_hospital" : "'' AS legacy_fallecido_hospital",
    db_column_exists('fallecido', 'hospital_direccion') ? "COALESCE(f.hospital_direccion,'') AS legacy_fallecido_hospital_direccion" : "'' AS legacy_fallecido_hospital_direccion",
    db_column_exists('fallecido', 'dom_velacion') ? "COALESCE(f.dom_velacion,'') AS legacy_fallecido_dom_velacion" : "'' AS legacy_fallecido_dom_velacion",
  ];

  try {
    $rows = qall("
      SELECT s.id_servicio,
             s.fallecido_nombre, s.fallecido_fecha, s.fallecido_municipio,
             s.fallecido_hospital, s.fallecido_hospital_direccion, s.fallecido_dom_velacion,
             ".implode(",\n             ", $legacySelect)."
      FROM servicios s
      JOIN (
        SELECT id_servicio, MAX({$servicioFallecidoIdColumn}) AS row_id
        FROM servicio_fallecido
        GROUP BY id_servicio
      ) latest ON latest.id_servicio = s.id_servicio
      JOIN servicio_fallecido sf ON sf.{$servicioFallecidoIdColumn} = latest.row_id
      LEFT JOIN fallecido f ON f.id_fallecido = sf.id_fallecido
    ");
  } catch (Throwable $e) {
    return;
  }

  $map = [
    'fallecido_nombre' => 'legacy_fallecido_nombre',
    'fallecido_fecha' => 'legacy_fallecido_fecha',
    'fallecido_municipio' => 'legacy_fallecido_municipio',
    'fallecido_hospital' => 'legacy_fallecido_hospital',
    'fallecido_hospital_direccion' => 'legacy_fallecido_hospital_direccion',
    'fallecido_dom_velacion' => 'legacy_fallecido_dom_velacion',
  ];

  foreach ($rows as $row) {
    $updates = [];
    $params = [];

    foreach ($map as $target => $legacyKey) {
      $isDate = $target === 'fallecido_fecha';
      $current = $row[$target] ?? null;
      $legacy = $row[$legacyKey] ?? null;
      if (!so_has_value($current, $isDate) && so_has_value($legacy, $isDate)) {
        $updates[] = "{$target}=?";
        $params[] = $legacy;
      }
    }

    if (empty($updates)) continue;
    $params[] = $row['id_servicio'];
    try {
      q("UPDATE servicios SET ".implode(', ', $updates).", updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?", $params);
    } catch (Throwable $e) {
    }
  }
}

function so_create_tables(bool $force = false): void {
  static $ready = false;
  static $suppressed = false;
  if ($ready) return;
  if ($suppressed && !$force) return;
  if (!$force && !db_schema_bootstrap_on_request()) {
    $suppressed = true;
    return;
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicios (
        id_servicio INT AUTO_INCREMENT PRIMARY KEY,
        folio VARCHAR(40) DEFAULT NULL,
        tipo_servicio VARCHAR(40) DEFAULT NULL,
        tipo_disposicion VARCHAR(40) DEFAULT NULL,
        responsable VARCHAR(120) DEFAULT NULL,
        auxiliares VARCHAR(255) DEFAULT NULL,
        contratante_nombre VARCHAR(150) DEFAULT NULL,
        contratante_tel VARCHAR(40) DEFAULT NULL,
        contratante_email VARCHAR(120) DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        estatus VARCHAR(30) NOT NULL DEFAULT 'registrado',
        cerrado TINYINT(1) NOT NULL DEFAULT 0,
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS fallecido (
        id_fallecido INT AUTO_INCREMENT PRIMARY KEY,
        nom_fallecido VARCHAR(150) NOT NULL,
        municipio VARCHAR(120) DEFAULT NULL,
        hospital VARCHAR(150) DEFAULT NULL,
        hospital_direccion VARCHAR(255) DEFAULT NULL,
        dom_velacion VARCHAR(255) DEFAULT NULL,
        fecha DATE DEFAULT NULL,
        eliminado TINYINT(1) NOT NULL DEFAULT 0
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_fallecido (
        id_rel INT AUTO_INCREMENT PRIMARY KEY,
        id_fallecido INT NOT NULL,
        id_servicio INT NOT NULL
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_asignacion (
        id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        fecha_hora_inicio DATETIME NULL,
        lugar_tipo VARCHAR(30) DEFAULT NULL,
        lugar_detalle VARCHAR(255) DEFAULT NULL,
        id_usuario_auxiliar INT DEFAULT NULL,
        codigo_caja VARCHAR(60) DEFAULT NULL,
        id_vehiculo VARCHAR(80) DEFAULT NULL,
        id_sala VARCHAR(80) DEFAULT NULL,
        instrucciones TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_seguimiento (
        id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        hora_inicio_real DATETIME DEFAULT NULL,
        hora_fin_real DATETIME DEFAULT NULL,
        observaciones_iniciales TEXT DEFAULT NULL,
        observaciones_finales TEXT DEFAULT NULL,
        incidencias TEXT DEFAULT NULL,
        confirmado_por_auxiliar TINYINT(1) NOT NULL DEFAULT 0,
        confirmado_por_usuario INT DEFAULT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_cierre (
        id_cierre INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        observaciones_cierre TEXT DEFAULT NULL,
        articulos_devueltos_json LONGTEXT DEFAULT NULL,
        caja_reciclada TINYINT(1) NOT NULL DEFAULT 0,
        pago_confirmado TINYINT(1) NOT NULL DEFAULT 0,
        auxiliar_confirma TINYINT(1) NOT NULL DEFAULT 0,
        id_usuario_auxiliar INT DEFAULT NULL,
        id_usuario_administrador INT DEFAULT NULL,
        fecha_cierre DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_pagos (
        id_pago INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        fecha_pago DATETIME NOT NULL,
        monto DECIMAL(12,2) NOT NULL DEFAULT 0,
        forma_pago VARCHAR(30) DEFAULT NULL,
        referencia VARCHAR(120) DEFAULT NULL,
        tipo_pago VARCHAR(20) NOT NULL DEFAULT 'abono',
        observaciones TEXT DEFAULT NULL,
        id_usuario INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_bitacora (
        id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        tipo_evento VARCHAR(40) NOT NULL,
        descripcion VARCHAR(255) DEFAULT NULL,
        id_usuario INT DEFAULT NULL,
        metadata_json LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos_movimientos (
        id_mov INT AUTO_INCREMENT PRIMARY KEY,
        id_articulo INT NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        referencia VARCHAR(120) DEFAULT NULL,
        origen VARCHAR(100) DEFAULT NULL,
        destino VARCHAR(100) DEFAULT NULL,
        id_almacen_origen INT DEFAULT NULL,
        id_almacen_destino INT DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos_salida_servicio (
        id_salida INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        id_articulo INT NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        responsable VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS cajas_movimientos (
        id_mov INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(60) NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        id_servicio INT DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS equipos_movimientos (
        id_mov INT AUTO_INCREMENT PRIMARY KEY,
        id_equipo VARCHAR(60) NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        id_servicio INT DEFAULT NULL,
        origen VARCHAR(100) DEFAULT NULL,
        destino VARCHAR(100) DEFAULT NULL,
        id_almacen_origen INT DEFAULT NULL,
        id_almacen_destino INT DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS vehiculos (
        id_vehiculo VARCHAR(80) PRIMARY KEY,
        descripcion VARCHAR(120) NOT NULL,
        estatus VARCHAR(20) NOT NULL DEFAULT 'disponible',
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS salas (
        id_sala VARCHAR(80) PRIMARY KEY,
        descripcion VARCHAR(120) NOT NULL,
        estatus VARCHAR(20) NOT NULL DEFAULT 'disponible',
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  $columns = [
    'folio' => "VARCHAR(40) DEFAULT NULL",
    'tipo_servicio' => "VARCHAR(40) DEFAULT NULL",
    'tipo_disposicion' => "VARCHAR(40) DEFAULT NULL",
    'responsable' => "VARCHAR(120) DEFAULT NULL",
    'auxiliares' => "VARCHAR(255) DEFAULT NULL",
    'contratante_nombre' => "VARCHAR(150) DEFAULT NULL",
    'contratante_tel' => "VARCHAR(40) DEFAULT NULL",
    'contratante_email' => "VARCHAR(120) DEFAULT NULL",
    'notas' => "TEXT DEFAULT NULL",
    'estatus' => "VARCHAR(30) NOT NULL DEFAULT 'registrado'",
    'cerrado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'eliminado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'origen_tipo' => "VARCHAR(20) NOT NULL DEFAULT 'directo'",
    'id_contrato_futuro' => "INT DEFAULT NULL",
    'fecha_hora_programada' => "DATETIME DEFAULT NULL",
    'lugar_tipo' => "VARCHAR(30) DEFAULT NULL",
    'lugar_detalle' => "VARCHAR(255) DEFAULT NULL",
    'id_vehiculo' => "VARCHAR(80) DEFAULT NULL",
    'id_sala' => "VARCHAR(80) DEFAULT NULL",
    'id_usuario_vendedor' => "INT DEFAULT NULL",
    'id_usuario_auxiliar' => "INT DEFAULT NULL",
    'monto_total' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'anticipo' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'saldo_pendiente' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'forma_pago_inicial' => "VARCHAR(30) DEFAULT NULL",
    'requiere_factura' => "TINYINT(1) NOT NULL DEFAULT 0",
    'observaciones_comerciales' => "TEXT DEFAULT NULL",
    'contratante_direccion' => "VARCHAR(255) DEFAULT NULL",
    'fallecido_nombre' => "VARCHAR(150) DEFAULT NULL",
    'fallecido_fecha' => "DATE DEFAULT NULL",
    'fallecido_municipio' => "VARCHAR(120) DEFAULT NULL",
    'fallecido_hospital' => "VARCHAR(150) DEFAULT NULL",
    'fallecido_hospital_direccion' => "VARCHAR(255) DEFAULT NULL",
    'fallecido_dom_velacion' => "VARCHAR(255) DEFAULT NULL",
    'updated_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'closed_at' => "DATETIME DEFAULT NULL",
  ];
  foreach ($columns as $column => $ddl) {
    so_add_column_if_missing('servicios', $column, $ddl);
  }
  so_ensure_servicios_estatus_shape();

  foreach ([
    'municipio' => "VARCHAR(120) DEFAULT NULL",
    'hospital' => "VARCHAR(150) DEFAULT NULL",
    'hospital_direccion' => "VARCHAR(255) DEFAULT NULL",
    'dom_velacion' => "VARCHAR(255) DEFAULT NULL",
  ] as $column => $ddl) {
    so_add_column_if_missing('fallecido', $column, $ddl);
  }

  db_add_index_if_missing('servicio_asignacion', 'idx_servicio_asignacion_servicio', 'INDEX idx_servicio_asignacion_servicio (id_servicio)');
  db_add_index_if_missing('servicio_seguimiento', 'idx_servicio_seguimiento_servicio', 'INDEX idx_servicio_seguimiento_servicio (id_servicio)');
  db_add_index_if_missing('servicio_cierre', 'idx_servicio_cierre_servicio', 'INDEX idx_servicio_cierre_servicio (id_servicio)');
  db_add_column_if_missing('articulos_movimientos', 'id_almacen_origen', "INT DEFAULT NULL");
  db_add_column_if_missing('articulos_movimientos', 'id_almacen_destino', "INT DEFAULT NULL");
  db_add_column_if_missing('equipos_movimientos', 'id_almacen_origen', "INT DEFAULT NULL");
  db_add_column_if_missing('equipos_movimientos', 'id_almacen_destino', "INT DEFAULT NULL");
  db_add_index_if_missing('articulos_movimientos', 'idx_articulos_movimientos_articulo', 'INDEX idx_articulos_movimientos_articulo (id_articulo, created_at)');
  db_add_index_if_missing('articulos_salida_servicio', 'idx_articulos_salida_servicio_servicio', 'INDEX idx_articulos_salida_servicio_servicio (id_servicio, created_at)');
  db_add_index_if_missing('cajas_movimientos', 'idx_cajas_movimientos_codigo_servicio', 'INDEX idx_cajas_movimientos_codigo_servicio (codigo, id_servicio, created_at)');
  db_add_index_if_missing('equipos_movimientos', 'idx_equipos_movimientos_equipo_servicio', 'INDEX idx_equipos_movimientos_equipo_servicio (id_equipo, id_servicio, created_at)');

  so_backfill_servicios_fallecidos();

  $ready = true;
  $suppressed = false;
}

function so_bootstrap(bool $force = false): void {
  so_create_tables($force);
}

function so_repair_now(): void {
  so_bootstrap(true);
}

db_register_schema_repair('servicios_operativos', 'so_repair_now');

function so_role_filter_sql(array $roles): array {
  $roles = array_values(array_filter(array_map('trim', $roles)));
  if (empty($roles)) return ['', []];
  $placeholders = implode(',', array_fill(0, count($roles), '?'));

  if (auth_roles_enabled()) {
    return [
      " AND EXISTS (
        SELECT 1
        FROM usuarios_roles ur
        JOIN roles r ON r.id = ur.rol_id
        WHERE ur.usuario_id = u.id AND r.slug IN ({$placeholders})
      )",
      $roles
    ];
  }

  return [" AND COALESCE(u.perfil,'') IN ({$placeholders})", $roles];
}

function so_user_options(array $roles = []): array {
  if (!so_table_exists('usuarios')) return [];
  [$extraSql, $params] = so_role_filter_sql($roles);
  try {
    $rows = qall("
      SELECT u.id, u.nombre, u.usuario, COALESCE(u.perfil,'') AS perfil
      FROM usuarios u
      WHERE COALESCE(u.estatus,'ACTIVO') = 'ACTIVO' {$extraSql}
      ORDER BY u.nombre ASC, u.usuario ASC
    ", $params);
  } catch (Throwable $e) {
    $rows = [];
  }

  $opts = ['' => 'Sin asignar'];
  foreach ($rows as $row) {
    $label = trim((string)($row['nombre'] ?? ''));
    if ($label === '') $label = (string)($row['usuario'] ?? ('Usuario '.$row['id']));
    $opts[(string)$row['id']] = $label;
  }
  return $opts;
}

function so_contratos_futuros_options(): array {
  if (!so_table_exists('futuro_contratos')) return ['' => 'No aplica'];
  $opts = ['' => 'No aplica'];
  try {
    $rows = qall("SELECT id_contrato FROM futuro_contratos ORDER BY id_contrato DESC LIMIT 300");
  } catch (Throwable $e) {
    $rows = [];
  }
  foreach ($rows as $row) {
    $opts[(string)$row['id_contrato']] = 'Contrato #'.$row['id_contrato'];
  }
  return $opts;
}

function so_servicio_estatus_options(): array {
  return [
    'registrado' => 'Registrado',
    'preparacion' => 'Preparacion',
    'en_proceso' => 'En proceso',
    'finalizado' => 'Finalizado',
    'cancelado' => 'Cancelado',
  ];
}

function so_servicio_activos_sql(): string {
  return iv2_servicio_activo_sql('s');
}

function so_servicio_cerrados_sql(): string {
  return iv2_servicio_cerrado_sql('s');
}

function so_servicio_estatus_norm(?string $estatus): string {
  $estatus = trim((string)$estatus);
  if ($estatus === '') return 'registrado';
  if ($estatus === 'abierto') return 'registrado';
  if ($estatus === 'cerrado') return 'finalizado';
  return $estatus;
}

function so_servicio_badge(string $estatus): string {
  return match (so_servicio_estatus_norm($estatus)) {
    'registrado' => 'secondary',
    'preparacion' => 'info text-dark',
    'en_proceso' => 'warning text-dark',
    'finalizado' => 'success',
    'cancelado' => 'danger',
    default => 'secondary',
  };
}

function so_servicio_tipo_pago_options(): array {
  return [
    'efectivo' => 'Efectivo',
    'transferencia' => 'Transferencia',
    'tarjeta' => 'Tarjeta',
    'cheque' => 'Cheque',
    'otro' => 'Otro',
  ];
}

function so_servicio_pago_tipo_options(): array {
  return [
    'anticipo' => 'Anticipo',
    'abono' => 'Abono',
    'liquidacion' => 'Liquidacion',
    'devolucion' => 'Devolucion',
  ];
}

function so_lugar_tipo_options(): array {
  return [
    'capilla' => 'Capilla',
    'domicilio' => 'Domicilio',
    'iglesia' => 'Iglesia',
    'otro' => 'Otro',
  ];
}

function so_now_local(): string {
  return date('Y-m-d H:i:s');
}

function so_now_input(): string {
  return date('Y-m-d\TH:i');
}

function so_datetime_to_input(?string $value): string {
  if (!$value) return '';
  $ts = strtotime($value);
  if (!$ts) return '';
  return date('Y-m-d\TH:i', $ts);
}

function so_parse_checkbox($value): int {
  return $value ? 1 : 0;
}

function so_parse_money($value): float {
  return round((float)str_replace(',', '', (string)$value), 2);
}

function so_json(array $data): string {
  return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function so_current_user_id(): ?int {
  $user = current_user();
  if (!$user) return null;
  return isset($user['id']) ? (int)$user['id'] : null;
}

function so_log_bitacora(int $id_servicio, string $tipo, string $descripcion, array $meta = []): void {
  so_bootstrap();
  try {
    q(
      "INSERT INTO servicio_bitacora (id_servicio, tipo_evento, descripcion, id_usuario, metadata_json, created_at)
       VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP())",
      [$id_servicio, $tipo, $descripcion, so_current_user_id(), so_json($meta)]
    );
  } catch (Throwable $e) {
  }
  try {
    $user = current_user();
    q(
      "INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at)
       VALUES ('servicios_operativos', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$tipo, $user['usuario'] ?? 'sistema', so_json(['id_servicio' => $id_servicio, 'descripcion' => $descripcion] + $meta)]
    );
  } catch (Throwable $e) {
  }
}

function so_generar_folio(PDO $pdo): string {
  $prefix = 'SERV-'.date('Ym').'-';
  $stmt = $pdo->prepare("SELECT folio FROM servicios WHERE folio LIKE ? ORDER BY folio DESC LIMIT 1");
  $stmt->execute([$prefix.'%']);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && preg_match('/(\d{4})$/', $row['folio'], $match)) {
    $seq = (int)$match[1] + 1;
  } else {
    $seq = 1;
  }
  return $prefix.str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function so_recalcular_saldo(int $id_servicio): void {
  $servicio = qone("SELECT monto_total FROM servicios WHERE id_servicio=?", [$id_servicio]);
  if (!$servicio) return;
  $pagado = qone("SELECT COALESCE(SUM(monto),0) AS total FROM servicio_pagos WHERE id_servicio=?", [$id_servicio]);
  $total = (float)($servicio['monto_total'] ?? 0);
  $abonado = (float)($pagado['total'] ?? 0);
  $saldo = max(0, round($total - $abonado, 2));
  q("UPDATE servicios SET anticipo=?, saldo_pendiente=?, updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?",
    [$abonado, $saldo, $id_servicio]);
}

function so_servicio_fetch(int $id_servicio): ?array {
  so_bootstrap();
  $sql = "
    SELECT s.*, COALESCE(s.cerrado,0) AS cerrado,
           COALESCE(NULLIF(TRIM(COALESCE(s.fallecido_nombre,'')),''),'') AS fallecido,
           COALESCE(uv.nombre, uv.usuario, '') AS vendedor_nombre,
           COALESCE(ua.nombre, ua.usuario, '') AS auxiliar_nombre
    FROM servicios s
    LEFT JOIN usuarios uv ON uv.id = s.id_usuario_vendedor
    LEFT JOIN usuarios ua ON ua.id = s.id_usuario_auxiliar
    WHERE s.id_servicio=? AND COALESCE(s.eliminado,0)=0
    LIMIT 1
  ";
  $row = qone($sql, [$id_servicio]);
  if (!$row) return null;
  $row['estatus_norm'] = so_servicio_estatus_norm($row['estatus'] ?? '');
  return $row;
}

function so_servicio_pagos(int $id_servicio): array {
  so_bootstrap();
  try {
    return qall("
      SELECT sp.*,
             COALESCE(u.nombre, u.usuario, 'Sistema') AS usuario_nombre
      FROM servicio_pagos sp
      LEFT JOIN usuarios u ON u.id = sp.id_usuario
      WHERE sp.id_servicio=?
      ORDER BY sp.fecha_pago DESC, sp.id_pago DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_asignacion(int $id_servicio): ?array {
  so_bootstrap();
  return qone("
    SELECT sa.*, COALESCE(u.nombre, u.usuario, '') AS auxiliar_nombre
    FROM servicio_asignacion sa
    LEFT JOIN usuarios u ON u.id = sa.id_usuario_auxiliar
    WHERE sa.id_servicio=?
    ORDER BY sa.id_asignacion DESC
    LIMIT 1
  ", [$id_servicio]);
}

function so_servicio_seguimiento(int $id_servicio): ?array {
  so_bootstrap();
  return qone("
    SELECT ss.*, COALESCE(u.nombre, u.usuario, '') AS confirma_nombre
    FROM servicio_seguimiento ss
    LEFT JOIN usuarios u ON u.id = ss.confirmado_por_usuario
    WHERE ss.id_servicio=?
    ORDER BY ss.id_seguimiento DESC
    LIMIT 1
  ", [$id_servicio]);
}

function so_servicio_cierre(int $id_servicio): ?array {
  so_bootstrap();
  return qone("
    SELECT sc.*,
           COALESCE(ua.nombre, ua.usuario, '') AS auxiliar_nombre,
           COALESCE(um.nombre, um.usuario, '') AS administrador_nombre
    FROM servicio_cierre sc
    LEFT JOIN usuarios ua ON ua.id = sc.id_usuario_auxiliar
    LEFT JOIN usuarios um ON um.id = sc.id_usuario_administrador
    WHERE sc.id_servicio=?
    ORDER BY sc.id_cierre DESC
    LIMIT 1
  ", [$id_servicio]);
}

function so_servicio_bitacora(int $id_servicio): array {
  so_bootstrap();
  try {
    return qall("
      SELECT sb.*,
             COALESCE(u.nombre, u.usuario, 'Sistema') AS usuario_nombre
      FROM servicio_bitacora sb
      LEFT JOIN usuarios u ON u.id = sb.id_usuario
      WHERE sb.id_servicio=?
      ORDER BY sb.id_bitacora DESC
      LIMIT 50
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_totales(int $id_servicio): array {
  $svc = so_servicio_fetch($id_servicio);
  $pagado = qone("SELECT COALESCE(SUM(monto),0) AS total FROM servicio_pagos WHERE id_servicio=?", [$id_servicio]);
  $total = (float)($svc['monto_total'] ?? 0);
  $abonado = (float)($pagado['total'] ?? 0);
  return [
    'total' => $total,
    'pagado' => $abonado,
    'saldo' => max(0, round($total - $abonado, 2)),
  ];
}

function so_guardar_pago(PDO $pdo, int $id_servicio, float $monto, string $forma_pago, string $tipo_pago, ?string $referencia, ?string $observaciones, ?string $fecha_pago = null): int {
  $fecha_pago = $fecha_pago ?: so_now_local();
  q(
    "INSERT INTO servicio_pagos (id_servicio, fecha_pago, monto, forma_pago, referencia, tipo_pago, observaciones, id_usuario, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())",
    [$id_servicio, $fecha_pago, $monto, $forma_pago ?: null, $referencia ?: null, $tipo_pago, $observaciones ?: null, so_current_user_id()]
  );
  $idPago = (int)$pdo->lastInsertId();
  so_recalcular_saldo($id_servicio);
  so_log_bitacora($id_servicio, 'pago', 'Pago registrado', ['id_pago' => $idPago, 'monto' => $monto, 'tipo_pago' => $tipo_pago]);
  return $idPago;
}

function so_servicios_cajas_disponibles(): array {
  return so_inventory_section_state('cajas')['items'];
}

function so_servicios_equipo_disponible(): array {
  return so_inventory_section_state('equipos')['items'];
}

function so_servicios_articulos_disponibles(): array {
  return so_inventory_section_state('articulos')['items'];
}

function so_inventory_section_state(string $tipo, string $query = '', ?int $limit = null): array {
  try {
    switch ($tipo) {
      case 'cajas':
        $items = iv2_disponibilidad_cajas($query, $limit);
        break;
      case 'equipos':
        $items = iv2_disponibilidad_equipos($query, $limit);
        break;
      case 'articulos':
        $items = iv2_disponibilidad_articulos($query, $limit);
        break;
      default:
        throw new RuntimeException('Tipo de inventario no soportado.');
    }
    return ['items' => $items, 'error' => null];
  } catch (Throwable $e) {
    db_log_error($e, ['scope' => 'inventory_section_state', 'tipo' => $tipo]);
    $messages = [
      'cajas' => 'No se pudieron cargar los ataudes disponibles.',
      'equipos' => 'No se pudieron cargar los equipos disponibles.',
      'articulos' => 'No se pudieron cargar los articulos disponibles.',
    ];
    return [
      'items' => [],
      'error' => $messages[$tipo] ?? 'No se pudo cargar la disponibilidad.',
    ];
  }
}

function so_servicio_asignacion_codigo_columns(): array {
  $columns = [];
  foreach (['codigo_caja', 'codigo'] as $candidate) {
    if (db_column_exists('servicio_asignacion', $candidate)) {
      $columns[] = $candidate;
    }
  }
  return array_values(array_unique($columns));
}

function so_guardar_codigo_caja_asignacion(int $id_servicio, string $codigo): void {
  $codigo = trim($codigo);
  if ($id_servicio <= 0 || $codigo === '') return;

  $codeColumns = so_servicio_asignacion_codigo_columns();
  if (empty($codeColumns)) {
    return;
  }

  $existente = so_servicio_asignacion($id_servicio);
  if ($existente) {
    $set = [];
    $params = [];
    foreach ($codeColumns as $column) {
      $set[] = "{$column}=?";
      $params[] = $codigo;
    }
    $set[] = "updated_at=CURRENT_TIMESTAMP()";
    $params[] = $id_servicio;
    q(
      "UPDATE servicio_asignacion
       SET ".implode(', ', $set)."
       WHERE id_servicio=?",
      $params
    );
    return;
  }

  $columns = ['id_servicio'];
  $values = [$id_servicio];
  foreach ($codeColumns as $column) {
    $columns[] = $column;
    $values[] = $codigo;
  }
  if (db_column_exists('servicio_asignacion', 'created_by')) {
    $columns[] = 'created_by';
    $values[] = so_current_user_id();
  }
  $placeholders = implode(', ', array_fill(0, count($columns), '?'));
  q(
    "INSERT INTO servicio_asignacion (".implode(', ', $columns).")
     VALUES ({$placeholders})",
    $values
  );
}

function so_servicio_asignacion_codigo_seed(int $id_servicio, ?string $codigo = null): ?string {
  $codigo = trim((string)$codigo);
  if ($codigo !== '') return $codigo;
  $actual = function_exists('iv2_servicio_caja_actual') ? iv2_servicio_caja_actual($id_servicio, false) : null;
  $resuelto = $actual ? trim((string)($actual['codigo_resuelto'] ?? $actual['codigo_caja'] ?? $actual['codigo'] ?? '')) : '';
  if ($resuelto !== '') return $resuelto;
  return null;
}

function so_servicio_cajas_asignadas(int $id_servicio): array {
  $v2 = iv2_servicio_cajas($id_servicio);
  if (!empty($v2)) {
    foreach ($v2 as &$row) {
      $row['ui_estado'] = iv2_caja_ui_badge($row);
    }
    return $v2;
  }
  try {
    return qall("
      SELECT m.codigo, c.modelo, m.created_at, c.es_rentado, c.reciclado
      FROM cajas_movimientos m
      JOIN cajas c ON c.codigo = m.codigo
      WHERE m.id_servicio=? AND m.tipo='asignacion'
      ORDER BY m.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_equipos_asignados(int $id_servicio): array {
  $v2 = iv2_servicio_equipos($id_servicio);
  if (!empty($v2)) return $v2;
  try {
    return qall("
      SELECT em.id_equipo, e.equipo, em.created_at
      FROM equipos_movimientos em
      JOIN equipos e ON e.id_equipo = em.id_equipo
      WHERE em.id_servicio=? AND em.tipo='asignacion'
      ORDER BY em.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_equipos_asignados_grupo(int $id_servicio): array {
  $v2 = iv2_servicio_equipos_agrupados($id_servicio);
  if (!empty($v2)) return $v2;
  $rows = so_servicio_equipos_asignados($id_servicio);
  if (empty($rows)) return [];
  $groups = [];
  foreach ($rows as $row) {
    $grupo = trim((string)($row['equipo'] ?? 'Sin grupo'));
    if (!isset($groups[$grupo])) {
      $groups[$grupo] = ['equipo' => $grupo, 'cantidad' => 0, 'ids' => []];
    }
    $groups[$grupo]['cantidad']++;
    $groups[$grupo]['ids'][] = (string)$row['id_equipo'];
  }
  return array_values($groups);
}

function so_servicio_articulos_asignados(int $id_servicio): array {
  $v2 = iv2_servicio_articulos($id_servicio);
  if (!empty($v2)) return $v2;
  try {
    return qall("
      SELECT s.id_articulo, a.articulo, s.cantidad, s.responsable, s.created_at
      FROM articulos_salida_servicio s
      JOIN articulos a ON a.id=s.id_articulo
      WHERE s.id_servicio=?
      ORDER BY s.id_salida DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_caja_activa(string $codigo): ?array {
  return qone("SELECT codigo, modelo, estado, reciclado, es_rentado FROM cajas WHERE codigo=? AND COALESCE(eliminado,0)=0", [$codigo]);
}

function so_caja_en_uso(string $codigo): bool {
  $row = qone("SELECT tipo FROM cajas_movimientos WHERE codigo=? ORDER BY id_mov DESC LIMIT 1", [$codigo]);
  return $row && $row['tipo'] === 'asignacion';
}

function so_registrar_mov_caja(PDO $pdo, string $codigo, string $tipo, ?int $id_servicio = null, ?string $notas = null): void {
  $stmt = $pdo->prepare("INSERT INTO cajas_movimientos (codigo,tipo,id_servicio,notas) VALUES (?,?,?,?)");
  $stmt->execute([$codigo, $tipo, $id_servicio, $notas]);
}

function so_registrar_mov_equipo(PDO $pdo, string $id_equipo, string $tipo, ?int $id_servicio = null, ?string $origen = null, ?string $destino = null, ?string $notas = null, ?int $idAlmacenOrigen = null, ?int $idAlmacenDestino = null): void {
  $columns = ['id_equipo','tipo','id_servicio','origen','destino','notas'];
  $values = [$id_equipo, $tipo, $id_servicio, $origen, $destino, $notas];
  if (db_column_exists('equipos_movimientos', 'id_almacen_origen')) {
    $columns[] = 'id_almacen_origen';
    $values[] = $idAlmacenOrigen;
  }
  if (db_column_exists('equipos_movimientos', 'id_almacen_destino')) {
    $columns[] = 'id_almacen_destino';
    $values[] = $idAlmacenDestino;
  }
  $placeholders = implode(',', array_fill(0, count($columns), '?'));
  $stmt = $pdo->prepare("INSERT INTO equipos_movimientos (".implode(',', $columns).") VALUES ({$placeholders})");
  $stmt->execute($values);
}

function so_registrar_articulo_servicio(PDO $pdo, int $id_servicio, int $id_articulo, int $cantidad, string $responsable, ?string $notas = null): void {
  iv2_registrar_consumos($pdo, $id_servicio, [
    ['id_articulo' => $id_articulo, 'cantidad' => $cantidad],
  ], so_current_user_id(), $responsable, $notas);
  so_log_bitacora($id_servicio, 'salida_articulo', 'Salida de articulo registrada', [
    'id_articulo' => $id_articulo,
    'cantidad' => $cantidad,
    'responsable' => $responsable,
  ]);
}

function so_guardar_asignacion(int $id_servicio, array $data): void {
  so_bootstrap();
  $existente = so_servicio_asignacion($id_servicio);
  if ($existente) {
    q(
      "UPDATE servicio_asignacion
       SET fecha_hora_inicio=?, lugar_tipo=?, lugar_detalle=?, id_usuario_auxiliar=?, id_vehiculo=?, id_sala=?, instrucciones=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_servicio=?",
      [
        $data['fecha_hora_inicio'] ?: null,
        $data['lugar_tipo'] ?: null,
        $data['lugar_detalle'] ?: null,
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_vehiculo'] ?: null,
        $data['id_sala'] ?: null,
        $data['instrucciones'] ?: null,
        $id_servicio,
      ]
    );
  } else {
    $columns = ['id_servicio', 'fecha_hora_inicio', 'lugar_tipo', 'lugar_detalle', 'id_usuario_auxiliar', 'id_vehiculo', 'id_sala', 'instrucciones', 'created_by'];
    $values = [
      $id_servicio,
      $data['fecha_hora_inicio'] ?: null,
      $data['lugar_tipo'] ?: null,
      $data['lugar_detalle'] ?: null,
      $data['id_usuario_auxiliar'] ?: null,
      $data['id_vehiculo'] ?: null,
      $data['id_sala'] ?: null,
      $data['instrucciones'] ?: null,
      so_current_user_id(),
    ];
    $codigoSeed = so_servicio_asignacion_codigo_seed($id_servicio, $data['codigo_caja'] ?? null);
    foreach (so_servicio_asignacion_codigo_columns() as $codeColumn) {
      $columns[] = $codeColumn;
      $values[] = $codigoSeed ?? '';
    }
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    q(
      "INSERT INTO servicio_asignacion (".implode(', ', $columns).")
       VALUES ({$placeholders})",
      $values
    );
  }

  q(
    "UPDATE servicios
     SET fecha_hora_programada=?, lugar_tipo=?, lugar_detalle=?, id_vehiculo=?, id_sala=?, id_usuario_auxiliar=?, updated_at=CURRENT_TIMESTAMP()
     WHERE id_servicio=?",
    [
      $data['fecha_hora_inicio'] ?: null,
      $data['lugar_tipo'] ?: null,
      $data['lugar_detalle'] ?: null,
      $data['id_vehiculo'] ?: null,
      $data['id_sala'] ?: null,
      $data['id_usuario_auxiliar'] ?: null,
      $id_servicio,
    ]
  );
  iv2_update_servicio_estatus($id_servicio, 'preparacion', null, null);

  so_log_bitacora($id_servicio, 'asignacion', 'Asignacion operativa actualizada', [
    'fecha_hora_inicio' => $data['fecha_hora_inicio'] ?: null,
    'lugar_tipo' => $data['lugar_tipo'] ?: null,
    'id_usuario_auxiliar' => $data['id_usuario_auxiliar'] ?: null,
  ]);
}

function so_guardar_seguimiento(int $id_servicio, array $data): string {
  so_bootstrap();
  $existente = so_servicio_seguimiento($id_servicio);
  if ($existente) {
    q(
      "UPDATE servicio_seguimiento
       SET hora_inicio_real=?, hora_fin_real=?, observaciones_iniciales=?, observaciones_finales=?, incidencias=?, confirmado_por_auxiliar=?, confirmado_por_usuario=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_servicio=?",
      [
        $data['hora_inicio_real'] ?: null,
        $data['hora_fin_real'] ?: null,
        $data['observaciones_iniciales'] ?: null,
        $data['observaciones_finales'] ?: null,
        $data['incidencias'] ?: null,
        $data['confirmado_por_auxiliar'],
        $data['confirmado_por_usuario'] ?: null,
        $id_servicio,
      ]
    );
  } else {
    q(
      "INSERT INTO servicio_seguimiento (id_servicio, hora_inicio_real, hora_fin_real, observaciones_iniciales, observaciones_finales, incidencias, confirmado_por_auxiliar, confirmado_por_usuario)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
      [
        $id_servicio,
        $data['hora_inicio_real'] ?: null,
        $data['hora_fin_real'] ?: null,
        $data['observaciones_iniciales'] ?: null,
        $data['observaciones_finales'] ?: null,
        $data['incidencias'] ?: null,
        $data['confirmado_por_auxiliar'],
        $data['confirmado_por_usuario'] ?: null,
      ]
    );
  }

  $estatus = 'preparacion';
  if (!empty($data['hora_fin_real'])) {
    $estatus = 'finalizado';
  } elseif (!empty($data['hora_inicio_real'])) {
    $estatus = 'en_proceso';
  }
  iv2_update_servicio_estatus($id_servicio, $estatus, null, null);
  so_log_bitacora($id_servicio, 'seguimiento', 'Seguimiento operativo actualizado', ['estatus' => $estatus]);
  return $estatus;
}

function so_pendientes_devolucion_cajas(int $id_servicio): array {
  if (iv2_servicio_tiene_registros('servicio_caja', $id_servicio) || iv2_servicio_tiene_registros('renta_cajas', $id_servicio)) {
    return iv2_pendientes_devolucion_cajas($id_servicio);
  }
  try {
    return qall("
      SELECT m.codigo, c.modelo, c.es_rentado, c.reciclado
      FROM cajas_movimientos m
      JOIN cajas c ON c.codigo=m.codigo
      LEFT JOIN cajas_movimientos d
        ON d.codigo=m.codigo AND d.id_servicio=m.id_servicio AND d.tipo='devolucion'
      WHERE m.id_servicio=? AND m.tipo='asignacion' AND d.id_mov IS NULL
      ORDER BY m.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_pendientes_devolucion_equipos(int $id_servicio): array {
  if (iv2_servicio_tiene_registros('servicio_equipo', $id_servicio)) {
    $equipoIdColumn = iv2_servicio_equipo_id_column();
    $orderSql = iv2_order_sql('servicio_equipo', 'se', ['fecha_asignacion', 'updated_at', 'created_at', $equipoIdColumn]);
    try {
      return qall("
        SELECT se.id_equipo, e.equipo
        FROM servicio_equipo se
        LEFT JOIN equipos e ON e.id_equipo = se.id_equipo
        WHERE se.id_servicio=? AND se.fecha_liberacion IS NULL
        {$orderSql}
      ", [$id_servicio]);
    } catch (Throwable $e) {
      return [];
    }
  }
  try {
    return qall("
      SELECT em.id_equipo, e.equipo
      FROM equipos_movimientos em
      JOIN equipos e ON e.id_equipo=em.id_equipo
      LEFT JOIN equipos_movimientos d
        ON d.id_equipo=em.id_equipo AND d.id_servicio=em.id_servicio AND d.tipo='devolucion'
      WHERE em.id_servicio=? AND em.tipo='asignacion' AND d.id_mov IS NULL
      ORDER BY em.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_cerrar_servicio_recursos(PDO $pdo, int $id_servicio, array $opciones = []): array {
  $resultado = [
    'caja' => null,
    'equipos' => [],
    'equipos_baja' => [],
    'articulos' => [],
  ];

  $idAlmacenDestino = (int)($opciones['id_almacen_destino'] ?? 0) ?: null;
  $destinoAlmacen = trim((string)($opciones['destino_almacen'] ?? ''));
  if ($destinoAlmacen === '' && $idAlmacenDestino !== null) {
    $destinoAlmacen = almacen_nombre($idAlmacenDestino) ?: '';
  }
  if ($destinoAlmacen === '') {
    $destinoAlmacen = 'almacen';
  }

  $procesarCaja = array_key_exists('procesar_caja', $opciones) ? !empty($opciones['procesar_caja']) : true;
  if ($procesarCaja && (iv2_servicio_tiene_registros('servicio_caja', $id_servicio) || iv2_servicio_tiene_registros('renta_cajas', $id_servicio))) {
    $accionCaja = trim((string)($opciones['accion_caja'] ?? 'retorna')) ?: 'retorna';
    $recicladoCaja = array_key_exists('reciclado_caja', $opciones) ? (int)$opciones['reciclado_caja'] : 1;
    $observacionesCaja = trim((string)($opciones['observaciones_caja'] ?? '')) ?: 'Cierre de servicio';
    $resultado['caja'] = iv2_cerrar_caja_servicio($pdo, $id_servicio, $accionCaja, $recicladoCaja, $observacionesCaja, so_current_user_id());
    if ($accionCaja === 'retorna' && $idAlmacenDestino !== null && !empty($resultado['caja']['codigo_caja'])) {
      almacen_update_item_location('cajas', 'codigo', $resultado['caja']['codigo_caja'], $idAlmacenDestino, $destinoAlmacen, 'ubicacion');
    }
    iv2_assert_cierre_permitido($id_servicio);
  }

  $usaEquiposV2 = iv2_servicio_tiene_registros('servicio_equipo', $id_servicio);
  $idsEquipo = $opciones['ids_equipo'] ?? [];
  $liberarTodos = !empty($opciones['liberar_todos_equipos']);
  $idsEquipo = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string)$value), is_array($idsEquipo) ? $idsEquipo : []))));
  if ($usaEquiposV2) {
    if ($liberarTodos) {
      $pendientes = so_pendientes_devolucion_equipos($id_servicio);
      $idsEquipo = array_column($pendientes, 'id_equipo');
    }
    if (!empty($idsEquipo)) {
      $resultado['equipos'] = iv2_cancelar_equipos($pdo, $id_servicio, $idsEquipo, so_current_user_id(), 'Cierre de servicio', 'devolucion', $destinoAlmacen, $idAlmacenDestino);
    }
  }

  if (!empty($opciones['articulos']) && is_array($opciones['articulos'])) {
    foreach ($opciones['articulos'] as $item) {
      if (!is_array($item)) continue;
      $idServicioArticulo = (int)($item['id_servicio_articulo'] ?? 0);
      $cantidad = (int)($item['cantidad'] ?? 0);
      if ($idServicioArticulo <= 0 || $cantidad <= 0) continue;
      $resultado['articulos'][] = iv2_cancelar_consumo_articulo(
        $pdo,
        $id_servicio,
        $idServicioArticulo,
        $cantidad,
        so_current_user_id(),
        trim((string)($item['observaciones'] ?? '')) ?: 'Devolucion en cierre de servicio',
        'devolucion'
      );
      $last = $resultado['articulos'][count($resultado['articulos']) - 1] ?? null;
      if ($idAlmacenDestino !== null && !empty($last['id_articulo'])) {
        almacen_update_item_location('articulos', 'id', (int)$last['id_articulo'], $idAlmacenDestino, null, null);
      }
    }
  }

  if (!$usaEquiposV2) {
    $equipos = so_pendientes_devolucion_equipos($id_servicio);
    $idsSeleccionados = $liberarTodos ? array_column($equipos, 'id_equipo') : $idsEquipo;
    $idsSeleccionadosMap = array_fill_keys(array_map('strval', $idsSeleccionados), true);
    foreach ($equipos as $equipo) {
      if (empty($idsSeleccionadosMap[(string)$equipo['id_equipo']])) continue;
      so_registrar_mov_equipo($pdo, $equipo['id_equipo'], 'devolucion', $id_servicio, 'servicio', $destinoAlmacen, 'Cierre de servicio', null, $idAlmacenDestino);
      $setEquipo = ["estatus='disponible'", "ubicacion=?"];
      $paramsEquipo = [$destinoAlmacen];
      if (db_column_exists('equipos', 'id_almacen')) {
        $setEquipo[] = 'id_almacen=?';
        $paramsEquipo[] = $idAlmacenDestino;
      }
      $setEquipo[] = 'updated_at=CURRENT_TIMESTAMP()';
      $paramsEquipo[] = $equipo['id_equipo'];
      q("UPDATE equipos SET ".implode(', ', $setEquipo)." WHERE id_equipo=?", $paramsEquipo);
      $resultado['equipos'][] = [
        'id_equipo' => $equipo['id_equipo'],
        'equipo' => $equipo['equipo'] ?? '',
        'tipo_evento' => 'devolucion',
      ];
    }
  }

  $bajaPendientes = !empty($opciones['baja_equipos_pendientes']);
  $pendientesFinales = so_pendientes_devolucion_equipos($id_servicio);
  if ($bajaPendientes && !empty($pendientesFinales)) {
    $notaBaja = trim((string)($opciones['observaciones_baja_equipos'] ?? '')) ?: 'Equipo no retorna por dano al cierre de servicio';
    $idsBaja = array_column($pendientesFinales, 'id_equipo');
    if ($usaEquiposV2) {
      $resultado['equipos_baja'] = iv2_baja_equipos_no_retorno($pdo, $id_servicio, $idsBaja, so_current_user_id(), $notaBaja);
    } else {
      foreach ($pendientesFinales as $equipo) {
        so_registrar_mov_equipo($pdo, $equipo['id_equipo'], 'baja_por_dano', $id_servicio, 'servicio', 'baja', $notaBaja);
        $setEquipo = ["estatus='baja'", "ubicacion=?"];
        $paramsEquipo = ['Baja por dano / no retorno'];
        if (db_column_exists('equipos', 'id_almacen')) {
          $setEquipo[] = 'id_almacen=NULL';
        }
        $setEquipo[] = 'updated_at=CURRENT_TIMESTAMP()';
        $paramsEquipo[] = $equipo['id_equipo'];
        q("UPDATE equipos SET ".implode(', ', $setEquipo)." WHERE id_equipo=?", $paramsEquipo);
        $resultado['equipos_baja'][] = [
          'id_equipo' => $equipo['id_equipo'],
          'equipo' => $equipo['equipo'] ?? '',
          'tipo_evento' => 'baja_por_dano',
        ];
      }
    }
    $pendientesFinales = so_pendientes_devolucion_equipos($id_servicio);
  }

  $permitirEquiposPendientes = !empty($opciones['permitir_equipos_pendientes']);
  if ($usaEquiposV2 && !$permitirEquiposPendientes && !empty($pendientesFinales)) {
    throw new RuntimeException('Aun hay equipos activos por devolver antes de cerrar el servicio.');
  }
  return $resultado;
}

function so_guardar_cierre(int $id_servicio, array $data): void {
  so_bootstrap();
  iv2_assert_cierre_permitido($id_servicio);
  $existente = so_servicio_cierre($id_servicio);
  if ($existente) {
    q(
      "UPDATE servicio_cierre
       SET observaciones_cierre=?, articulos_devueltos_json=?, caja_reciclada=?, pago_confirmado=?, auxiliar_confirma=?, id_usuario_auxiliar=?, id_usuario_administrador=?, fecha_cierre=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_servicio=?",
      [
        $data['observaciones_cierre'] ?: null,
        $data['articulos_devueltos_json'] ?: null,
        $data['caja_reciclada'],
        $data['pago_confirmado'],
        $data['auxiliar_confirma'],
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_usuario_administrador'] ?: null,
        $data['fecha_cierre'] ?: null,
        $id_servicio,
      ]
    );
  } else {
    q(
      "INSERT INTO servicio_cierre (id_servicio, observaciones_cierre, articulos_devueltos_json, caja_reciclada, pago_confirmado, auxiliar_confirma, id_usuario_auxiliar, id_usuario_administrador, fecha_cierre)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [
        $id_servicio,
        $data['observaciones_cierre'] ?: null,
        $data['articulos_devueltos_json'] ?: null,
        $data['caja_reciclada'],
        $data['pago_confirmado'],
        $data['auxiliar_confirma'],
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_usuario_administrador'] ?: null,
        $data['fecha_cierre'] ?: null,
      ]
    );
  }
  iv2_update_servicio_estatus($id_servicio, 'finalizado', null, $data['fecha_cierre'] ?: so_now_local());
  so_log_bitacora($id_servicio, 'cierre', 'Cierre administrativo registrado', [
    'pago_confirmado' => $data['pago_confirmado'],
    'auxiliar_confirma' => $data['auxiliar_confirma'],
  ]);
}

function so_moneda($value): string {
  return '$'.number_format((float)$value, 2);
}

function so_texto_pdf(string $str): string {
  if (function_exists('mb_convert_encoding')) {
    return mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
  }
  return $str;
}

function so_servicio_resumen_html(int $id_servicio): string {
  $svc = so_servicio_fetch($id_servicio);
  if (!$svc) return '<p>Servicio no encontrado.</p>';

  $asignacion = so_servicio_asignacion($id_servicio);
  $seguimiento = so_servicio_seguimiento($id_servicio);
  $cierre = so_servicio_cierre($id_servicio);
  $totales = so_servicio_totales($id_servicio);
  $articulos = so_servicio_articulos_asignados($id_servicio);
  $cajas = so_servicio_cajas_asignadas($id_servicio);
  $equipos = so_servicio_equipos_asignados($id_servicio);
  $fechaProgramada = $asignacion['fecha_hora_inicio'] ?? ($svc['fecha_hora_programada'] ?? '');
  $lugarTipo = $asignacion['lugar_tipo'] ?? ($svc['lugar_tipo'] ?? '');
  $lugarDetalle = $asignacion['lugar_detalle'] ?? ($svc['lugar_detalle'] ?? '');
  $vehiculo = $asignacion['id_vehiculo'] ?? ($svc['id_vehiculo'] ?? '');
  $sala = $asignacion['id_sala'] ?? ($svc['id_sala'] ?? '');
  $fechaCierre = $cierre['fecha_cierre'] ?? ($svc['closed_at'] ?? '');

  ob_start(); ?>
  <style>
    body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222}
    h1,h2{margin:0 0 10px}
    .muted{color:#666}
    .grid{width:100%;border-collapse:collapse;margin-bottom:14px}
    .grid th,.grid td{border:1px solid #ccc;padding:6px 8px;text-align:left}
    .card{margin-bottom:14px}
  </style>
  <h1>Servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
  <p class="muted">Fallecido: <?= e($svc['fallecido'] ?: 'No capturado') ?> | Estatus: <?= e(ucwords(str_replace('_', ' ', so_servicio_estatus_norm($svc['estatus'] ?? '')))) ?></p>
  <div class="card">
    <h2>Datos del fallecido</h2>
    <table class="grid">
      <tr><th>Nombre</th><td><?= e($svc['fallecido'] ?: '—') ?></td><th>Fecha del fallecimiento</th><td><?= e($svc['fallecido_fecha'] ?: '—') ?></td></tr>
      <tr><th>Municipio</th><td><?= e($svc['fallecido_municipio'] ?: '—') ?></td><th>Hospital</th><td><?= e($svc['fallecido_hospital'] ?: '—') ?></td></tr>
      <tr><th>Direccion del hospital</th><td colspan="3"><?= e($svc['fallecido_hospital_direccion'] ?: '—') ?></td></tr>
      <tr><th>Domicilio de velacion</th><td colspan="3"><?= e($svc['fallecido_dom_velacion'] ?: '—') ?></td></tr>
    </table>
  </div>
  <table class="grid">
    <tr><th>Contratante</th><td><?= e($svc['contratante_nombre']) ?></td><th>Telefono</th><td><?= e($svc['contratante_tel'] ?: '—') ?></td></tr>
    <tr><th>Correo</th><td><?= e($svc['contratante_email'] ?: '—') ?></td><th>Direccion</th><td><?= e($svc['contratante_direccion'] ?: '—') ?></td></tr>
    <tr><th>Tipo</th><td><?= e($svc['tipo_servicio']) ?></td><th>Disposicion</th><td><?= e($svc['tipo_disposicion']) ?></td></tr>
    <tr><th>Origen</th><td><?= e($svc['origen_tipo']) ?></td><th>Contrato futuro</th><td><?= e($svc['id_contrato_futuro'] ?: '—') ?></td></tr>
    <tr><th>Vendedor</th><td><?= e($svc['vendedor_nombre'] ?: '—') ?></td><th>Auxiliar</th><td><?= e($svc['auxiliar_nombre'] ?: '—') ?></td></tr>
    <tr><th>Total</th><td><?= e(so_moneda($totales['total'])) ?></td><th>Pagado / Saldo</th><td><?= e(so_moneda($totales['pagado']).' / '.so_moneda($totales['saldo'])) ?></td></tr>
  </table>
  <div class="card">
    <h2>Preparacion</h2>
    <table class="grid">
      <tr><th>Fecha programada</th><td><?= e($fechaProgramada ?: '—') ?></td><th>Lugar</th><td><?= e(($lugarTipo ?: '—').' '.($lugarDetalle ?: '')) ?></td></tr>
      <tr><th>Vehiculo</th><td><?= e($vehiculo ?: '—') ?></td><th>Sala</th><td><?= e($sala ?: '—') ?></td></tr>
      <tr><th>Instrucciones</th><td colspan="3"><?= e($asignacion['instrucciones'] ?? '—') ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Seguimiento</h2>
    <table class="grid">
      <tr><th>Inicio real</th><td><?= e($seguimiento['hora_inicio_real'] ?? '—') ?></td><th>Fin real</th><td><?= e($seguimiento['hora_fin_real'] ?? '—') ?></td></tr>
      <tr><th>Observaciones iniciales</th><td colspan="3"><?= e($seguimiento['observaciones_iniciales'] ?? '—') ?></td></tr>
      <tr><th>Incidencias</th><td colspan="3"><?= e($seguimiento['incidencias'] ?? '—') ?></td></tr>
      <tr><th>Observaciones finales</th><td colspan="3"><?= e($seguimiento['observaciones_finales'] ?? '—') ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Recursos asignados</h2>
    <table class="grid">
      <tr><th>Ataudes</th><td><?= e(implode(', ', array_map(fn($r) => $r['codigo'].' '.$r['modelo'], $cajas)) ?: '—') ?></td></tr>
      <tr><th>Equipos</th><td><?= e(implode(', ', array_map(fn($r) => $r['equipo'].' '.$r['id_equipo'], $equipos)) ?: '—') ?></td></tr>
      <tr><th>Articulos</th><td><?= e(implode(', ', array_map(fn($r) => $r['articulo'].' x'.$r['cantidad'], $articulos)) ?: '—') ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Cierre</h2>
    <table class="grid">
      <tr><th>Fecha cierre</th><td><?= e($fechaCierre ?: '—') ?></td><th>Pago confirmado</th><td><?= !empty($cierre['pago_confirmado']) ? 'Si' : 'No' ?></td></tr>
      <tr><th>Observaciones</th><td colspan="3"><?= e($cierre['observaciones_cierre'] ?? '—') ?></td></tr>
    </table>
  </div>
  <?php
  return ob_get_clean();
}
