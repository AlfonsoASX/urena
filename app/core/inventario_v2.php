<?php

require_once __DIR__.'/db.php';

function iv2_bootstrap(bool $force = false): void {
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
      CREATE TABLE IF NOT EXISTS servicio_articulos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        id_articulo INT NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        id_usuario INT DEFAULT NULL,
        responsable VARCHAR(120) DEFAULT NULL,
        observaciones TEXT DEFAULT NULL
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'id_articulo' => "INT NOT NULL",
    'cantidad' => "INT NOT NULL DEFAULT 0",
    'fecha' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'id_usuario' => "INT DEFAULT NULL",
    'responsable' => "VARCHAR(120) DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
  ] as $column => $ddl) {
    db_add_column_if_missing('servicio_articulos', $column, $ddl);
  }
  db_add_index_if_missing('servicio_articulos', 'idx_servicio_articulos_servicio', 'INDEX idx_servicio_articulos_servicio (id_servicio)');
  db_add_index_if_missing('servicio_articulos', 'idx_servicio_articulos_articulo', 'INDEX idx_servicio_articulos_articulo (id_articulo)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS kardex_articulos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_articulo INT NOT NULL,
        tipo_mov VARCHAR(30) NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        stock_restante INT NOT NULL DEFAULT 0,
        id_servicio INT DEFAULT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        id_usuario INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_articulo' => "INT NOT NULL",
    'tipo_mov' => "VARCHAR(30) NOT NULL",
    'cantidad' => "INT NOT NULL DEFAULT 0",
    'stock_restante' => "INT NOT NULL DEFAULT 0",
    'id_servicio' => "INT DEFAULT NULL",
    'fecha' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'id_usuario' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
  ] as $column => $ddl) {
    db_add_column_if_missing('kardex_articulos', $column, $ddl);
  }
  db_add_index_if_missing('kardex_articulos', 'idx_kardex_articulos_articulo', 'INDEX idx_kardex_articulos_articulo (id_articulo)');
  db_add_index_if_missing('kardex_articulos', 'idx_kardex_articulos_servicio', 'INDEX idx_kardex_articulos_servicio (id_servicio)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_caja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        codigo_caja VARCHAR(80) NOT NULL,
        tipo_asignacion VARCHAR(20) NOT NULL DEFAULT 'permanente',
        fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        id_usuario INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'codigo_caja' => "VARCHAR(80) NOT NULL",
    'tipo_asignacion' => "VARCHAR(20) NOT NULL DEFAULT 'permanente'",
    'fecha_asignacion' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'id_usuario' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('servicio_caja', $column, $ddl);
  }
  db_add_index_if_missing('servicio_caja', 'uniq_servicio_caja_servicio', 'UNIQUE INDEX uniq_servicio_caja_servicio (id_servicio)');
  db_add_index_if_missing('servicio_caja', 'idx_servicio_caja_codigo', 'INDEX idx_servicio_caja_codigo (codigo_caja)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS renta_cajas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        codigo_caja VARCHAR(80) NOT NULL,
        fecha_salida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_devolucion DATETIME DEFAULT NULL,
        estado_devolucion VARCHAR(30) DEFAULT NULL,
        id_usuario_salida INT DEFAULT NULL,
        id_usuario_recepcion INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'codigo_caja' => "VARCHAR(80) NOT NULL",
    'fecha_salida' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'fecha_devolucion' => "DATETIME DEFAULT NULL",
    'estado_devolucion' => "VARCHAR(30) DEFAULT NULL",
    'id_usuario_salida' => "INT DEFAULT NULL",
    'id_usuario_recepcion' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('renta_cajas', $column, $ddl);
  }
  db_add_index_if_missing('renta_cajas', 'idx_renta_cajas_servicio', 'INDEX idx_renta_cajas_servicio (id_servicio)');
  db_add_index_if_missing('renta_cajas', 'idx_renta_cajas_codigo', 'INDEX idx_renta_cajas_codigo (codigo_caja)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_equipo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        id_equipo VARCHAR(80) NOT NULL,
        fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_liberacion DATETIME DEFAULT NULL,
        id_usuario INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'id_equipo' => "VARCHAR(80) NOT NULL",
    'fecha_asignacion' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'fecha_liberacion' => "DATETIME DEFAULT NULL",
    'id_usuario' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('servicio_equipo', $column, $ddl);
  }
  db_add_index_if_missing('servicio_equipo', 'idx_servicio_equipo_servicio', 'INDEX idx_servicio_equipo_servicio (id_servicio)');
  db_add_index_if_missing('servicio_equipo', 'idx_servicio_equipo_equipo', 'INDEX idx_servicio_equipo_equipo (id_equipo)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_articulo_evento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        id_servicio_articulo INT DEFAULT NULL,
        id_articulo INT NOT NULL,
        tipo_evento VARCHAR(20) NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        id_usuario INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'id_servicio_articulo' => "INT DEFAULT NULL",
    'id_articulo' => "INT NOT NULL",
    'tipo_evento' => "VARCHAR(20) NOT NULL",
    'cantidad' => "INT NOT NULL DEFAULT 0",
    'id_usuario' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('servicio_articulo_evento', $column, $ddl);
  }
  db_add_index_if_missing('servicio_articulo_evento', 'idx_servicio_articulo_evento_servicio', 'INDEX idx_servicio_articulo_evento_servicio (id_servicio)');
  db_add_index_if_missing('servicio_articulo_evento', 'idx_servicio_articulo_evento_articulo', 'INDEX idx_servicio_articulo_evento_articulo (id_articulo)');
  db_add_index_if_missing('servicio_articulo_evento', 'idx_servicio_articulo_evento_base', 'INDEX idx_servicio_articulo_evento_base (id_servicio_articulo)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_equipo_evento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        id_servicio_equipo INT DEFAULT NULL,
        id_equipo VARCHAR(80) NOT NULL,
        tipo_evento VARCHAR(20) NOT NULL,
        id_usuario INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'id_servicio_equipo' => "INT DEFAULT NULL",
    'id_equipo' => "VARCHAR(80) NOT NULL",
    'tipo_evento' => "VARCHAR(20) NOT NULL",
    'id_usuario' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('servicio_equipo_evento', $column, $ddl);
  }
  db_add_index_if_missing('servicio_equipo_evento', 'idx_servicio_equipo_evento_servicio', 'INDEX idx_servicio_equipo_evento_servicio (id_servicio)');
  db_add_index_if_missing('servicio_equipo_evento', 'idx_servicio_equipo_evento_equipo', 'INDEX idx_servicio_equipo_evento_equipo (id_equipo)');
  db_add_index_if_missing('servicio_equipo_evento', 'idx_servicio_equipo_evento_base', 'INDEX idx_servicio_equipo_evento_base (id_servicio_equipo)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_caja_evento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        codigo_caja VARCHAR(80) NOT NULL,
        tipo_evento VARCHAR(20) NOT NULL,
        id_usuario INT DEFAULT NULL,
        observaciones TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'id_servicio' => "INT NOT NULL",
    'codigo_caja' => "VARCHAR(80) NOT NULL",
    'tipo_evento' => "VARCHAR(20) NOT NULL",
    'id_usuario' => "INT DEFAULT NULL",
    'observaciones' => "TEXT DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('servicio_caja_evento', $column, $ddl);
  }
  db_add_index_if_missing('servicio_caja_evento', 'idx_servicio_caja_evento_servicio', 'INDEX idx_servicio_caja_evento_servicio (id_servicio)');
  db_add_index_if_missing('servicio_caja_evento', 'idx_servicio_caja_evento_codigo', 'INDEX idx_servicio_caja_evento_codigo (codigo_caja)');

  $ready = true;
  $suppressed = false;
}

function iv2_repair_now(): void {
  iv2_bootstrap(true);
}

db_register_schema_repair('inventario_v2', 'iv2_repair_now');

function iv2_table_columns(string $table): array {
  if (!db_table_exists($table)) return [];
  try {
    $rows = qall("SHOW COLUMNS FROM {$table}");
  } catch (Throwable $e) {
    return [];
  }
  $columns = [];
  foreach ($rows as $row) {
    if (!empty($row['Field'])) {
      $columns[] = (string)$row['Field'];
    }
  }
  return $columns;
}

function iv2_has_column(string $table, string $column): bool {
  return db_column_exists($table, $column);
}

function iv2_existing_column(string $table, array $candidates): ?string {
  foreach ($candidates as $candidate) {
    $candidate = trim((string)$candidate);
    if ($candidate !== '' && db_column_exists($table, $candidate)) {
      return $candidate;
    }
  }
  return null;
}

function iv2_identifier_ref(?string $alias, string $column): string {
  return $alias ? "{$alias}.{$column}" : $column;
}

function iv2_order_sql(string $table, ?string $alias, array $candidates): string {
  $clauses = [];
  foreach ($candidates as $candidate) {
    $candidate = trim((string)$candidate);
    if ($candidate === '' || !db_column_exists($table, $candidate)) continue;
    $clauses[] = iv2_identifier_ref($alias, $candidate).' DESC';
  }
  return empty($clauses) ? '' : ' ORDER BY '.implode(', ', $clauses);
}

function iv2_search_term(?string $query): string {
  return trim((string)$query);
}

function iv2_limit(?int $limit, ?int $default = null, int $max = 100): ?int {
  if ($limit === null || $limit <= 0) {
    $limit = $default;
  }
  if ($limit === null || $limit <= 0) return null;
  return max(1, min($max, $limit));
}

function iv2_append_limit(string $sql, ?int $limit): string {
  if ($limit === null) return $sql;
  return $sql.' LIMIT '.(int)$limit;
}

function iv2_servicio_caja_id_column(): ?string {
  return iv2_existing_column('servicio_caja', ['id', 'id_serv_cod']);
}

function iv2_servicio_caja_codigo_column(): string {
  return iv2_existing_column('servicio_caja', ['codigo_caja', 'codigo']) ?? 'codigo_caja';
}

function iv2_servicio_caja_codigo_columns(): array {
  $columns = [];
  foreach (['codigo_caja', 'codigo'] as $candidate) {
    if (db_column_exists('servicio_caja', $candidate)) {
      $columns[] = $candidate;
    }
  }
  return array_values(array_unique($columns));
}

function iv2_servicio_caja_codigo_expr(?string $alias = null): string {
  $parts = [];
  foreach (iv2_servicio_caja_codigo_columns() as $column) {
    $parts[] = 'NULLIF('.iv2_identifier_ref($alias, $column).", '')";
  }
  if (empty($parts)) {
    return iv2_identifier_ref($alias, 'codigo_caja');
  }
  return count($parts) === 1 ? $parts[0] : 'COALESCE('.implode(', ', $parts).')';
}

function iv2_servicio_equipo_id_column(): ?string {
  return iv2_existing_column('servicio_equipo', ['id', 'id_serv_eq']);
}

function iv2_renta_cajas_id_column(): ?string {
  return iv2_existing_column('renta_cajas', ['id', 'id_renta']);
}

function iv2_servicio_caja_codigo_value(array $row): string {
  return trim((string)($row['codigo_caja'] ?? $row['codigo'] ?? $row['codigo_resuelto'] ?? ''));
}

function iv2_servicio_articulo_id_column(): string {
  return 'id';
}

function iv2_caja_estado_usado(array $box, ?int $reciclado = null): string {
  if (($reciclado ?? (int)($box['reciclado'] ?? 0)) === 1) {
    return 'reciclado';
  }
  if ((int)($box['veces_usado'] ?? $box['ciclos_uso'] ?? 0) > 0) {
    return 'reciclado';
  }
  return 'nuevo';
}

function iv2_caja_ui_badge(array $box): string {
  return iv2_caja_estado_usado($box) === 'reciclado' ? 'Usado' : 'Nuevo';
}

function iv2_registrar_evento_articulo(int $id_servicio, ?int $idServicioArticulo, int $idArticulo, string $tipoEvento, int $cantidad, ?int $id_usuario = null, ?string $observaciones = null): void {
  if (!db_table_exists('servicio_articulo_evento')) return;
  q(
    "INSERT INTO servicio_articulo_evento (id_servicio, id_servicio_articulo, id_articulo, tipo_evento, cantidad, id_usuario, observaciones)
     VALUES (?, ?, ?, ?, ?, ?, ?)",
    [$id_servicio, $idServicioArticulo, $idArticulo, $tipoEvento, $cantidad, $id_usuario, $observaciones]
  );
}

function iv2_registrar_evento_equipo(int $id_servicio, ?int $idServicioEquipo, string $idEquipo, string $tipoEvento, ?int $id_usuario = null, ?string $observaciones = null): void {
  if (!db_table_exists('servicio_equipo_evento')) return;
  q(
    "INSERT INTO servicio_equipo_evento (id_servicio, id_servicio_equipo, id_equipo, tipo_evento, id_usuario, observaciones)
     VALUES (?, ?, ?, ?, ?, ?)",
    [$id_servicio, $idServicioEquipo, $idEquipo, $tipoEvento, $id_usuario, $observaciones]
  );
}

function iv2_registrar_evento_caja(int $id_servicio, string $codigo, string $tipoEvento, ?int $id_usuario = null, ?string $observaciones = null): void {
  if (!db_table_exists('servicio_caja_evento')) return;
  q(
    "INSERT INTO servicio_caja_evento (id_servicio, codigo_caja, tipo_evento, id_usuario, observaciones)
     VALUES (?, ?, ?, ?, ?)",
    [$id_servicio, $codigo, $tipoEvento, $id_usuario, $observaciones]
  );
}

function iv2_articulo_row(int $idArticulo, bool $forUpdate = false): ?array {
  if ($idArticulo <= 0 || !db_table_exists('articulos')) return null;
  $sql = "
    SELECT id, articulo, COALESCE(existencias,0) AS existencias
    FROM articulos
    WHERE id=? AND COALESCE(eliminado,0)=0
    LIMIT 1
  ";
  if ($forUpdate) $sql .= " FOR UPDATE";
  return qone($sql, [$idArticulo]);
}

function iv2_servicio_articulo_rows(int $id_servicio, bool $includeZero = false): array {
  iv2_bootstrap();
  if (!iv2_servicio_tiene_registros('servicio_articulos', $id_servicio)) return [];
  $rows = qall(
    "SELECT sa.id AS id_servicio_articulo, sa.id_articulo, a.articulo, sa.cantidad AS cantidad_base,
            COALESCE(sa.responsable, u.nombre, u.usuario, '') AS responsable,
            sa.fecha AS created_at,
            sa.observaciones
     FROM servicio_articulos sa
     JOIN articulos a ON a.id = sa.id_articulo
     LEFT JOIN usuarios u ON u.id = sa.id_usuario
     WHERE sa.id_servicio=?
     ORDER BY sa.id DESC",
    [$id_servicio]
  );

  $ajustes = [];
  if (db_table_exists('servicio_articulo_evento')) {
    foreach (qall(
      "SELECT id_servicio_articulo, SUM(COALESCE(cantidad,0)) AS cantidad
       FROM servicio_articulo_evento
       WHERE id_servicio=? AND tipo_evento IN ('cancelacion','devolucion')
       GROUP BY id_servicio_articulo",
      [$id_servicio]
    ) as $evento) {
      $ajustes[(int)($evento['id_servicio_articulo'] ?? 0)] = (int)($evento['cantidad'] ?? 0);
    }
  }

  $out = [];
  foreach ($rows as $row) {
    $rowId = (int)($row['id_servicio_articulo'] ?? 0);
    $cantidadBase = (int)($row['cantidad_base'] ?? 0);
    $cantidadAjustada = min($cantidadBase, (int)($ajustes[$rowId] ?? 0));
    $cantidadActiva = max(0, $cantidadBase - $cantidadAjustada);
    if (!$includeZero && $cantidadActiva <= 0) {
      continue;
    }
    $row['cantidad'] = $cantidadActiva;
    $row['cantidad_base'] = $cantidadBase;
    $row['cantidad_ajustada'] = $cantidadAjustada;
    $row['cantidad_cancelable'] = $cantidadActiva;
    $out[] = $row;
  }
  return $out;
}

function iv2_now(): string {
  return date('Y-m-d H:i:s');
}

function iv2_estatus_cerrado_value(string $estatus): ?int {
  $estatus = trim($estatus);
  if ($estatus === '') return null;
  if (in_array($estatus, ['finalizado', 'cerrado', 'cancelado'], true)) return 1;
  if (in_array($estatus, ['registrado', 'preparacion', 'en_proceso'], true)) return 0;
  return null;
}

function iv2_servicio_activo_sql(string $alias = 's'): string {
  return "COALESCE({$alias}.eliminado,0)=0
          AND COALESCE({$alias}.cerrado,0)=0
          AND COALESCE({$alias}.estatus,'registrado') NOT IN ('finalizado','cancelado','cerrado')";
}

function iv2_servicio_cerrado_sql(string $alias = 's'): string {
  return "COALESCE({$alias}.eliminado,0)=0
          AND (
            COALESCE({$alias}.cerrado,0)=1
            OR COALESCE({$alias}.estatus,'registrado') IN ('finalizado','cancelado','cerrado')
          )";
}

function iv2_servicio_row(int $id_servicio, bool $forUpdate = false): ?array {
  iv2_bootstrap();
  if ($id_servicio <= 0) return null;

  $cols = [
    'id_servicio',
    "COALESCE(estatus,'registrado') AS estatus",
    "COALESCE(eliminado,0) AS eliminado",
    "COALESCE(cerrado,0) AS cerrado",
    "closed_at",
  ];
  $sql = "SELECT ".implode(', ', $cols)." FROM servicios WHERE id_servicio=? LIMIT 1";
  if ($forUpdate) $sql .= " FOR UPDATE";
  return qone($sql, [$id_servicio]);
}

function iv2_servicio_abierto(array $servicio): bool {
  if (empty($servicio) || !empty($servicio['eliminado'])) return false;
  if (isset($servicio['cerrado']) && (int)$servicio['cerrado'] === 1) return false;
  $estatus = trim((string)($servicio['estatus'] ?? 'registrado'));
  return !in_array($estatus, ['finalizado', 'cerrado', 'cancelado'], true);
}

function iv2_assert_servicio_abierto(PDO $pdo, int $id_servicio): array {
  $servicio = iv2_servicio_row($id_servicio, true);
  if (!$servicio) {
    throw new RuntimeException('Servicio no encontrado.');
  }
  if (!iv2_servicio_abierto($servicio)) {
    throw new RuntimeException('El servicio ya no admite movimientos de inventario.');
  }
  return $servicio;
}

function iv2_update_servicio_estatus(int $id_servicio, string $estatus, ?int $cerrado = null, ?string $closedAt = null): void {
  if ($id_servicio <= 0) return;
  if ($cerrado === null) {
    $cerrado = iv2_estatus_cerrado_value($estatus);
  }
  $set = ["estatus=?"];
  $params = [$estatus];

  if ($cerrado !== null) {
    $set[] = "cerrado=?";
    $params[] = $cerrado;
  }
  if ($closedAt !== null) {
    $set[] = "closed_at=?";
    $params[] = $closedAt;
  }
  if ($closedAt === null && $cerrado === 0) {
    $set[] = "closed_at=NULL";
  }
  $set[] = "updated_at=CURRENT_TIMESTAMP()";
  $params[] = $id_servicio;
  q("UPDATE servicios SET ".implode(', ', $set)." WHERE id_servicio=?", $params);
}

function iv2_normalize_items(array $items): array {
  $normalized = [];
  foreach ($items as $item) {
    if (!is_array($item)) continue;
    $idArticulo = (int)($item['id_articulo'] ?? $item['id'] ?? 0);
    $cantidad = (int)($item['cantidad'] ?? 0);
    if ($idArticulo <= 0 || $cantidad <= 0) continue;
    if (!isset($normalized[$idArticulo])) {
      $normalized[$idArticulo] = ['id_articulo' => $idArticulo, 'cantidad' => 0];
    }
    $normalized[$idArticulo]['cantidad'] += $cantidad;
  }
  return array_values($normalized);
}

function iv2_registrar_consumos(PDO $pdo, int $id_servicio, array $items, ?int $id_usuario = null, ?string $responsable = null, ?string $observaciones = null): array {
  iv2_bootstrap();
  iv2_assert_servicio_abierto($pdo, $id_servicio);

  $items = iv2_normalize_items($items);
  if (empty($items)) {
    throw new RuntimeException('No se recibieron consumos validos.');
  }

  $out = [];
  foreach ($items as $item) {
    $articulo = qone(
      "SELECT id, articulo, COALESCE(existencias,0) AS existencias
       FROM articulos
       WHERE id=? AND COALESCE(eliminado,0)=0
       LIMIT 1 FOR UPDATE",
      [$item['id_articulo']]
    );
    if (!$articulo) {
      throw new RuntimeException('Articulo no encontrado.');
    }
    $cantidad = (int)$item['cantidad'];
    $existencias = (int)$articulo['existencias'];
    if ($existencias < $cantidad) {
      throw new RuntimeException('Stock insuficiente para '.$articulo['articulo'].'.');
    }
    $restante = $existencias - $cantidad;

    q(
      "INSERT INTO servicio_articulos (id_servicio, id_articulo, cantidad, fecha, id_usuario, responsable, observaciones)
       VALUES (?, ?, ?, ?, ?, ?, ?)",
      [$id_servicio, $item['id_articulo'], $cantidad, iv2_now(), $id_usuario, $responsable, $observaciones]
    );
    q("UPDATE articulos SET existencias=?, updated_at=CURRENT_TIMESTAMP() WHERE id=?", [$restante, $item['id_articulo']]);
    q(
      "INSERT INTO kardex_articulos (id_articulo, tipo_mov, cantidad, stock_restante, id_servicio, fecha, id_usuario, observaciones)
       VALUES (?, 'salida_servicio', ?, ?, ?, ?, ?, ?)",
      [$item['id_articulo'], $cantidad, $restante, $id_servicio, iv2_now(), $id_usuario, $observaciones]
    );

    $out[] = [
      'id_articulo' => (int)$item['id_articulo'],
      'articulo' => $articulo['articulo'],
      'cantidad' => $cantidad,
      'stock_restante' => $restante,
    ];
  }

  return $out;
}

function iv2_box_row(string $codigo, bool $forUpdate = false): ?array {
  if ($codigo === '' || !db_table_exists('cajas')) return null;
  $columns = [
    'codigo',
    iv2_has_column('cajas', 'modelo') ? "modelo" : "'' AS modelo",
    iv2_has_column('cajas', 'estatus_logico') ? "estatus_logico" : "NULL AS estatus_logico",
    iv2_has_column('cajas', 'disponible') ? "disponible" : "NULL AS disponible",
    iv2_has_column('cajas', 'reciclado') ? "reciclado" : "NULL AS reciclado",
    iv2_has_column('cajas', 'veces_usado') ? "veces_usado" : "NULL AS veces_usado",
    iv2_has_column('cajas', 'ciclos_uso') ? "ciclos_uso" : "NULL AS ciclos_uso",
    iv2_has_column('cajas', 'estado') ? "estado" : "NULL AS estado",
    iv2_has_column('cajas', 'es_rentado') ? "es_rentado" : "0 AS es_rentado",
    iv2_has_column('cajas', 'eliminado') ? "COALESCE(eliminado,0) AS eliminado" : "0 AS eliminado",
  ];
  $sql = "SELECT ".implode(', ', $columns)." FROM cajas WHERE codigo=? LIMIT 1";
  if ($forUpdate) $sql .= " FOR UPDATE";
  return qone($sql, [$codigo]);
}

function iv2_box_available(array $box): bool {
  if (empty($box) || !empty($box['eliminado'])) return false;
  if ($box['estatus_logico'] !== null) {
    if (trim((string)$box['estatus_logico']) !== 'disponible') return false;
    if ($box['disponible'] !== null && (int)$box['disponible'] !== 1) return false;
    return true;
  }
  if ($box['disponible'] !== null) {
    return (int)$box['disponible'] === 1;
  }
  $estado = trim((string)($box['estado'] ?? ''));
  if ($estado === '') return true;
  return in_array($estado, ['nuevo', 'reciclado', 'disponible'], true);
}

function iv2_box_assignment_type(array $box, ?string $requested = null): string {
  $requested = trim((string)$requested);
  if (in_array($requested, ['renta', 'permanente'], true)) {
    return $requested;
  }
  return !empty($box['es_rentado']) ? 'renta' : 'permanente';
}

function iv2_box_apply_state(string $codigo, string $tipoAsignacion, ?int $reciclado = null, bool $returning = false, string $estadoDevolucion = 'usable'): void {
  if (!db_table_exists('cajas')) return;
  $set = [];
  $params = [];

  if ($returning) {
    $estadoDevolucion = trim($estadoDevolucion) ?: 'usable';
    $esUsable = $estadoDevolucion === 'usable';
    if (iv2_has_column('cajas', 'estatus_logico')) {
      $estatusLogico = $esUsable ? 'disponible' : ($estadoDevolucion === 'mantenimiento' ? 'mantenimiento' : 'baja');
      $set[] = "estatus_logico=?";
      $params[] = $estatusLogico;
    }
    if (iv2_has_column('cajas', 'disponible')) {
      $set[] = "disponible=".($esUsable ? '1' : '0');
    }
    if (iv2_has_column('cajas', 'veces_usado')) {
      $set[] = "veces_usado=COALESCE(veces_usado,0)+1";
    } elseif (iv2_has_column('cajas', 'ciclos_uso')) {
      $set[] = "ciclos_uso=COALESCE(ciclos_uso,0)+1";
    }
    if (iv2_has_column('cajas', 'reciclado') && $reciclado !== null) {
      $set[] = "reciclado=?";
      $params[] = $reciclado;
    }
    if (iv2_has_column('cajas', 'estado')) {
      if ($esUsable) {
        $estado = (($reciclado === null || $reciclado === 1) ? 'reciclado' : 'nuevo');
      } else {
        $estado = 'fuera_uso';
      }
      $set[] = "estado=?";
      $params[] = $estado;
    }
  } else {
    if (iv2_has_column('cajas', 'estatus_logico')) {
      $set[] = "estatus_logico=?";
      $params[] = $tipoAsignacion === 'renta' ? 'rentado' : 'baja';
    }
    if (iv2_has_column('cajas', 'disponible')) {
      $set[] = "disponible=0";
    }
    if (iv2_has_column('cajas', 'reciclado')) {
      $set[] = "reciclado=0";
    }
    if (iv2_has_column('cajas', 'estado')) {
      $set[] = "estado=?";
      $params[] = $tipoAsignacion === 'renta' ? 'en_uso' : 'fuera_uso';
    }
  }

  if (iv2_has_column('cajas', 'updated_at')) {
    $set[] = "updated_at=CURRENT_TIMESTAMP()";
  }
  if (empty($set)) return;
  $params[] = $codigo;
  q("UPDATE cajas SET ".implode(', ', $set)." WHERE codigo=?", $params);
}

function iv2_servicio_caja_actual(int $id_servicio, bool $forUpdate = false): ?array {
  iv2_bootstrap();
  if (!db_table_exists('servicio_caja')) return null;
  $codigoExpr = iv2_servicio_caja_codigo_expr('sc');
  $sql = "
    SELECT sc.*, {$codigoExpr} AS codigo_resuelto, c.modelo,
           COALESCE(c.reciclado,0) AS reciclado,
           COALESCE(c.veces_usado, c.ciclos_uso, 0) AS veces_usado
    FROM servicio_caja sc
    LEFT JOIN cajas c ON c.codigo = {$codigoExpr}
    WHERE sc.id_servicio=? AND TRIM(COALESCE({$codigoExpr}, '')) <> ''
  ";
  $sql .= iv2_order_sql('servicio_caja', 'sc', [
    'fecha_asignacion',
    'updated_at',
    'created_at',
    iv2_servicio_caja_id_column(),
  ]);
  $sql .= " LIMIT 1";
  if ($forUpdate) $sql .= " FOR UPDATE";
  return qone($sql, [$id_servicio]);
}

function iv2_servicio_caja_limpiar(int $id_servicio, ?string $tipoAsignacion = null, ?string $observaciones = null): void {
  if (!db_table_exists('servicio_caja')) return;
  // Eliminar la fila en lugar de poner codigo=''.
  // Poner '' dispara la FK (servicio_caja.codigo → cajas.codigo) porque '' no existe en cajas.
  // iv2_servicio_caja_actual busca filas con codigo <> '', así que sin fila = sin ataúd asignado.
  q("DELETE FROM servicio_caja WHERE id_servicio=?", [$id_servicio]);
}

function iv2_renta_abierta(int $id_servicio, string $codigo, bool $forUpdate = false): ?array {
  iv2_bootstrap();
  if (!db_table_exists('renta_cajas')) return null;
  $idColumn = iv2_renta_cajas_id_column();
  $sql = "
    SELECT *, ".($idColumn ? "{$idColumn} AS row_id" : "NULL AS row_id")."
    FROM renta_cajas
    WHERE id_servicio=? AND codigo_caja=? AND fecha_devolucion IS NULL
  ";
  $sql .= iv2_order_sql('renta_cajas', null, [
    'fecha_salida',
    'updated_at',
    'created_at',
    $idColumn,
  ]);
  $sql .= " LIMIT 1";
  if ($forUpdate) $sql .= " FOR UPDATE";
  return qone($sql, [$id_servicio, $codigo]);
}

function iv2_devolver_caja_internal(PDO $pdo, int $id_servicio, string $codigo, string $estadoDevolucion, ?int $reciclado, ?string $observaciones, ?int $id_usuario): array {
  $codigo = trim($codigo);
  if ($codigo === '') {
    throw new RuntimeException('Codigo de caja invalido.');
  }
  iv2_servicio_row($id_servicio, true);
  $renta = iv2_renta_abierta($id_servicio, $codigo, true);
  if (!$renta) {
    throw new RuntimeException('No existe una renta abierta para esa caja.');
  }
  $box = iv2_box_row($codigo, true);
  if (!$box) {
    throw new RuntimeException('Caja no encontrada.');
  }
  $rowId = (int)($renta['row_id'] ?? $renta['id'] ?? 0);
  $idColumn = iv2_renta_cajas_id_column() ?? 'id';

  q(
    "UPDATE renta_cajas
     SET fecha_devolucion=?, estado_devolucion=?, id_usuario_recepcion=?, observaciones=?, updated_at=CURRENT_TIMESTAMP()
     WHERE {$idColumn}=?",
    [iv2_now(), $estadoDevolucion ?: 'usable', $id_usuario, $observaciones, $rowId]
  );
  iv2_box_apply_state($codigo, 'renta', $reciclado, true, $estadoDevolucion);
  iv2_registrar_evento_caja($id_servicio, $codigo, 'devolucion', $id_usuario, $observaciones);
  iv2_servicio_caja_limpiar($id_servicio, 'renta', $observaciones);

  return [
    'id_servicio' => $id_servicio,
    'codigo_caja' => $codigo,
    'fecha_devolucion' => iv2_now(),
    'estado_devolucion' => $estadoDevolucion ?: 'usable',
    'estado_caja' => ($estadoDevolucion ?: 'usable') === 'usable' ? 'disponible' : ($estadoDevolucion ?: 'usable'),
  ];
}

function iv2_devolver_caja(PDO $pdo, int $id_servicio, string $codigo, string $estadoDevolucion = 'usable', ?int $reciclado = null, ?string $observaciones = null, ?int $id_usuario = null): array {
  iv2_bootstrap();
  return iv2_devolver_caja_internal($pdo, $id_servicio, $codigo, $estadoDevolucion, $reciclado, $observaciones, $id_usuario);
}

function iv2_asignar_caja(PDO $pdo, int $id_servicio, string $codigo, ?string $tipoAsignacion = null, ?int $id_usuario = null, ?string $observaciones = null): array {
  iv2_bootstrap();
  iv2_assert_servicio_abierto($pdo, $id_servicio);

  $codigo = trim($codigo);
  if ($codigo === '') {
    throw new RuntimeException('Selecciona una caja valida.');
  }

  $box = iv2_box_row($codigo, true);
  if (!$box) {
    throw new RuntimeException('Caja no encontrada.');
  }
  if (!iv2_box_available($box)) {
    throw new RuntimeException('La caja no esta disponible.');
  }
  $servicioCajaCodeColumns = iv2_servicio_caja_codigo_columns();

  $rentaActiva = qone(
    "SELECT id_servicio
     FROM renta_cajas
     WHERE codigo_caja=? AND fecha_devolucion IS NULL AND id_servicio<>?
     ".iv2_order_sql('renta_cajas', null, ['fecha_salida', 'updated_at', 'created_at', iv2_renta_cajas_id_column()])."
     LIMIT 1 FOR UPDATE",
    [$codigo, $id_servicio]
  );
  if ($rentaActiva) {
    throw new RuntimeException('La caja ya esta asignada a otro servicio.');
  }

  $codigoFilters = [];
  $codigoParams = [];
  foreach ($servicioCajaCodeColumns as $codeColumn) {
    $codigoFilters[] = "{$codeColumn}=?";
    $codigoParams[] = $codigo;
  }
  $codigoFiltersSql = empty($codigoFilters) ? "codigo_caja=?" : '('.implode(' OR ', $codigoFilters).')';
  if (empty($codigoParams)) {
    $codigoParams[] = $codigo;
  }
  // Solo bloquear si el servicio que tiene la asignación permanente sigue abierto.
  // Servicios cerrados/finalizados dejan filas stale en servicio_caja que no deben
  // impedir reasignar una caja que ya figura como disponible en el catálogo.
  $otraPermanente = qone(
    "SELECT sc.id_servicio
     FROM servicio_caja sc
     JOIN servicios s ON s.id_servicio = sc.id_servicio
     WHERE {$codigoFiltersSql} AND sc.tipo_asignacion='permanente' AND sc.id_servicio<>?
     AND COALESCE(s.eliminado,0)=0
     AND COALESCE(s.cerrado,0)=0
     AND COALESCE(s.estatus,'registrado') NOT IN ('finalizado','cerrado','cancelado')
     LIMIT 1",
    array_merge($codigoParams, [$id_servicio])
  );
  if ($otraPermanente) {
    throw new RuntimeException('La caja ya fue consumida por otro servicio.');
  }

  $tipoAsignacion = iv2_box_assignment_type($box, $tipoAsignacion);
  $actual = iv2_servicio_caja_actual($id_servicio, true);
  $actualCodigo = $actual ? iv2_servicio_caja_codigo_value($actual) : '';
  if ($actual && $actualCodigo !== '' && $actualCodigo !== $codigo) {
    if (($actual['tipo_asignacion'] ?? 'permanente') !== 'renta') {
      throw new RuntimeException('El servicio ya tiene una caja permanente asignada.');
    }
    iv2_devolver_caja_internal($pdo, $id_servicio, $actualCodigo, 'reasignada', 1, 'Reasignacion de caja', $id_usuario);
  }

  if ($actual) {
    $set = [];
    $params = [];
    foreach (iv2_servicio_caja_codigo_columns() as $codeColumn) {
      $set[] = "{$codeColumn}=?";
      $params[] = $codigo;
    }
    $set[] = "tipo_asignacion=?";
    $params[] = $tipoAsignacion;
    $set[] = "fecha_asignacion=?";
    $params[] = iv2_now();
    $set[] = "id_usuario=?";
    $params[] = $id_usuario;
    $set[] = "observaciones=?";
    $params[] = $observaciones;
    if (db_column_exists('servicio_caja', 'updated_at')) {
      $set[] = "updated_at=CURRENT_TIMESTAMP()";
    }
    $params[] = $id_servicio;
    q(
      "UPDATE servicio_caja
       SET ".implode(', ', $set)."
       WHERE id_servicio=?",
      $params
    );
  } else {
    $insertColumns = ['id_servicio'];
    $insertValues = [$id_servicio];
    foreach (iv2_servicio_caja_codigo_columns() as $codeColumn) {
      $insertColumns[] = $codeColumn;
      $insertValues[] = $codigo;
    }
    foreach ([
      'tipo_asignacion' => $tipoAsignacion,
      'fecha_asignacion' => iv2_now(),
      'id_usuario' => $id_usuario,
      'observaciones' => $observaciones,
    ] as $column => $value) {
      if (!db_column_exists('servicio_caja', $column)) continue;
      $insertColumns[] = $column;
      $insertValues[] = $value;
    }
    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    q(
      "INSERT INTO servicio_caja (".implode(', ', $insertColumns).")
       VALUES ({$placeholders})",
      $insertValues
    );
  }

  if ($tipoAsignacion === 'renta') {
    $renta = iv2_renta_abierta($id_servicio, $codigo, true);
    if ($renta) {
      $rentaIdColumn = iv2_renta_cajas_id_column() ?? 'id';
      $rentaRowId = (int)($renta['row_id'] ?? $renta['id'] ?? 0);
      q(
        "UPDATE renta_cajas
         SET fecha_salida=?, id_usuario_salida=?, observaciones=?, updated_at=CURRENT_TIMESTAMP()
         WHERE {$rentaIdColumn}=?",
        [iv2_now(), $id_usuario, $observaciones, $rentaRowId]
      );
    } else {
      q(
        "INSERT INTO renta_cajas (id_servicio, codigo_caja, fecha_salida, id_usuario_salida, observaciones)
         VALUES (?, ?, ?, ?, ?)",
        [$id_servicio, $codigo, iv2_now(), $id_usuario, $observaciones]
      );
    }
  }

  iv2_box_apply_state($codigo, $tipoAsignacion, null, false);
  iv2_registrar_evento_caja($id_servicio, $codigo, 'asignacion', $id_usuario, $observaciones);
  iv2_update_servicio_estatus($id_servicio, 'preparacion', 0, null);

  return [
    'id_servicio' => $id_servicio,
    'codigo_caja' => $codigo,
    'tipo_asignacion' => $tipoAsignacion,
    'modelo' => $box['modelo'] ?? '',
  ];
}

function iv2_cancelar_caja(PDO $pdo, int $id_servicio, ?string $codigo = null, ?int $id_usuario = null, ?string $observaciones = null): array {
  iv2_bootstrap();
  iv2_assert_servicio_abierto($pdo, $id_servicio);
  $actual = iv2_servicio_caja_actual($id_servicio, true);
  if (!$actual) {
    throw new RuntimeException('El servicio no tiene un ataud activo para cancelar.');
  }
  $codigoActual = trim((string)($codigo ?: iv2_servicio_caja_codigo_value($actual)));
  if ($codigoActual === '') {
    throw new RuntimeException('No se encontro el codigo del ataud asignado.');
  }
  if ($codigo !== null && trim((string)$codigo) !== $codigoActual) {
    throw new RuntimeException('El ataud indicado no coincide con el activo del servicio.');
  }

  $box = iv2_box_row($codigoActual, true);
  if ($box) {
    if (($actual['tipo_asignacion'] ?? 'permanente') === 'renta') {
      $renta = iv2_renta_abierta($id_servicio, $codigoActual, true);
      if ($renta) {
        $idColumn = iv2_renta_cajas_id_column() ?? 'id';
        $rowId = (int)($renta['row_id'] ?? $renta['id'] ?? 0);
        q(
          "UPDATE renta_cajas
           SET fecha_devolucion=?, estado_devolucion='cancelada', id_usuario_recepcion=?, observaciones=?, updated_at=CURRENT_TIMESTAMP()
           WHERE {$idColumn}=?",
          [iv2_now(), $id_usuario, $observaciones, $rowId]
        );
      }
      iv2_box_apply_state($codigoActual, 'renta', 1, true, 'usable');
    } else {
      iv2_box_apply_state($codigoActual, 'permanente', 1, true, 'usable');
    }
  }

  iv2_servicio_caja_limpiar($id_servicio, $actual['tipo_asignacion'] ?? null, $observaciones);
  iv2_registrar_evento_caja($id_servicio, $codigoActual, 'cancelacion', $id_usuario, $observaciones);

  return [
    'id_servicio' => $id_servicio,
    'codigo_caja' => $codigoActual,
    'tipo_asignacion' => $actual['tipo_asignacion'] ?? 'permanente',
    'estado_caja' => 'disponible',
  ];
}

function iv2_cerrar_caja_servicio(PDO $pdo, int $id_servicio, string $accion, ?int $reciclado = null, ?string $observaciones = null, ?int $id_usuario = null): ?array {
  iv2_bootstrap();
  $actual = iv2_servicio_caja_actual($id_servicio, true);
  if (!$actual) return null;
  $codigo = iv2_servicio_caja_codigo_value($actual);
  if ($codigo === '') return null;
  $box = iv2_box_row($codigo, true);
  if (!$box) {
    iv2_servicio_caja_limpiar($id_servicio, $actual['tipo_asignacion'] ?? null, $observaciones);
    return null;
  }

  $accion = trim($accion) ?: 'retorna';
  if ($accion === 'retorna') {
    if (($actual['tipo_asignacion'] ?? 'permanente') === 'renta' && iv2_renta_abierta($id_servicio, $codigo, true)) {
      $resultado = iv2_devolver_caja_internal($pdo, $id_servicio, $codigo, 'usable', $reciclado ?? 1, $observaciones, $id_usuario);
    } else {
      iv2_box_apply_state($codigo, 'permanente', $reciclado ?? 1, true, 'usable');
      iv2_registrar_evento_caja($id_servicio, $codigo, 'devolucion', $id_usuario, $observaciones);
      iv2_servicio_caja_limpiar($id_servicio, $actual['tipo_asignacion'] ?? null, $observaciones);
      $resultado = [
        'id_servicio' => $id_servicio,
        'codigo_caja' => $codigo,
        'tipo_asignacion' => $actual['tipo_asignacion'] ?? 'permanente',
        'estado_caja' => 'disponible',
      ];
    }
  } else {
    iv2_registrar_evento_caja($id_servicio, $codigo, 'cierre_sin_retorno', $id_usuario, $observaciones);
    iv2_servicio_caja_limpiar($id_servicio, $actual['tipo_asignacion'] ?? null, $observaciones);
    $resultado = [
      'id_servicio' => $id_servicio,
      'codigo_caja' => $codigo,
      'tipo_asignacion' => $actual['tipo_asignacion'] ?? 'permanente',
      'estado_caja' => 'sin_retorno',
    ];
  }

  if ($box) {
    $resultado['ui_estado'] = iv2_caja_ui_badge(iv2_box_row($codigo, false) ?: $box);
  }
  return $resultado;
}

function iv2_equipo_state_column(): ?string {
  return 'estatus';
}

function iv2_equipo_row(string $idEquipo, bool $forUpdate = false): ?array {
  if ($idEquipo === '' || !db_table_exists('equipos')) return null;
  $columns = [
    'id_equipo',
    'equipo',
    "estatus AS estado_actual",
    'ubicacion',
    iv2_has_column('equipos', 'id_almacen') ? "id_almacen" : "NULL AS id_almacen",
    "COALESCE(eliminado,0) AS eliminado",
  ];
  $sql = "SELECT ".implode(', ', $columns)." FROM equipos WHERE id_equipo=? LIMIT 1";
  if ($forUpdate) $sql .= " FOR UPDATE";
  return qone($sql, [$idEquipo]);
}

function iv2_equipo_disponible(array $equipo): bool {
  if (empty($equipo) || !empty($equipo['eliminado'])) return false;
  $estado = trim((string)($equipo['estado_actual'] ?? ''));
  if ($estado === '') return true;
  return $estado === 'disponible';
}

function iv2_asignar_equipo(PDO $pdo, int $id_servicio, string $idEquipo, ?int $id_usuario = null, ?string $observaciones = null): array {
  iv2_bootstrap();
  iv2_assert_servicio_abierto($pdo, $id_servicio);

  $idEquipo = trim($idEquipo);
  if ($idEquipo === '') {
    throw new RuntimeException('Selecciona un equipo valido.');
  }

  $equipo = iv2_equipo_row($idEquipo, true);
  if (!$equipo) {
    throw new RuntimeException('Equipo no encontrado.');
  }
  if (!iv2_equipo_disponible($equipo)) {
    throw new RuntimeException('El equipo no esta disponible.');
  }
  $open = qone(
    "SELECT ".(iv2_servicio_equipo_id_column() ? iv2_servicio_equipo_id_column()." AS row_id," : "NULL AS row_id,")." id_servicio
     FROM servicio_equipo
     WHERE id_equipo=? AND fecha_liberacion IS NULL
     ".iv2_order_sql('servicio_equipo', null, ['fecha_asignacion', 'updated_at', 'created_at', iv2_servicio_equipo_id_column()])."
     LIMIT 1 FOR UPDATE",
    [$idEquipo]
  );
  if ($open) {
    throw new RuntimeException('El equipo ya tiene una asignacion abierta.');
  }

  q(
    "INSERT INTO servicio_equipo (id_servicio, id_equipo, fecha_asignacion, id_usuario, observaciones)
     VALUES (?, ?, ?, ?, ?)",
    [$id_servicio, $idEquipo, iv2_now(), $id_usuario, $observaciones]
  );

  $setEquipo = ["estatus='ocupado'"];
  $paramsEquipo = [];
  if (iv2_has_column('equipos', 'ubicacion')) {
    $setEquipo[] = 'ubicacion=?';
    $paramsEquipo[] = 'Servicio #'.$id_servicio;
  }
  if (iv2_has_column('equipos', 'id_almacen')) {
    $setEquipo[] = 'id_almacen=NULL';
  }
  if (iv2_has_column('equipos', 'updated_at')) {
    $setEquipo[] = 'updated_at=CURRENT_TIMESTAMP()';
  }
  $paramsEquipo[] = $idEquipo;
  q("UPDATE equipos SET ".implode(', ', $setEquipo)." WHERE id_equipo=?", $paramsEquipo);
  $rowId = (int)$pdo->lastInsertId();
  iv2_registrar_evento_equipo($id_servicio, $rowId > 0 ? $rowId : null, $idEquipo, 'asignacion', $id_usuario, $observaciones);

  iv2_update_servicio_estatus($id_servicio, 'preparacion', 0, null);

  return [
    'id_servicio' => $id_servicio,
    'id_equipo' => $idEquipo,
    'equipo' => $equipo['equipo'] ?? '',
  ];
}

function iv2_asignar_equipos(PDO $pdo, int $id_servicio, array $idsEquipo, ?int $id_usuario = null, ?string $observaciones = null): array {
  $out = [];
  foreach ($idsEquipo as $idEquipo) {
    $idEquipo = trim((string)$idEquipo);
    if ($idEquipo === '') continue;
    $out[] = iv2_asignar_equipo($pdo, $id_servicio, $idEquipo, $id_usuario, $observaciones);
  }
  if (empty($out)) {
    throw new RuntimeException('No se seleccionaron equipos validos.');
  }
  return $out;
}

function iv2_asignar_equipos_por_grupo(PDO $pdo, int $id_servicio, string $grupo, int $cantidad, ?int $id_usuario = null, ?string $observaciones = null): array {
  iv2_bootstrap();
  iv2_assert_servicio_abierto($pdo, $id_servicio);
  $grupo = trim($grupo);
  $cantidad = max(0, $cantidad);
  if ($grupo === '' || $cantidad <= 0) {
    throw new RuntimeException('Indica un grupo y una cantidad valida de equipos.');
  }
  $disponibles = iv2_disponibilidad_equipos($grupo, max($cantidad, 200), false, $grupo);
  if (count($disponibles) < $cantidad) {
    throw new RuntimeException('No hay suficientes piezas disponibles para '.$grupo.'.');
  }
  $ids = array_map(static fn(array $row): string => (string)$row['id_equipo'], array_slice($disponibles, 0, $cantidad));
  return iv2_asignar_equipos($pdo, $id_servicio, $ids, $id_usuario, $observaciones);
}

function iv2_registrar_movimiento_equipo(string $idEquipo, string $tipo, ?int $idServicio = null, ?string $origen = null, ?string $destino = null, ?string $notas = null, ?int $idAlmacenOrigen = null, ?int $idAlmacenDestino = null): void {
  if (!db_table_exists('equipos_movimientos')) return;
  $columns = ['id_equipo','tipo','id_servicio','origen','destino','notas'];
  $values = [$idEquipo, $tipo, $idServicio, $origen, $destino, $notas];
  if (db_column_exists('equipos_movimientos', 'id_almacen_origen')) {
    $columns[] = 'id_almacen_origen';
    $values[] = $idAlmacenOrigen;
  }
  if (db_column_exists('equipos_movimientos', 'id_almacen_destino')) {
    $columns[] = 'id_almacen_destino';
    $values[] = $idAlmacenDestino;
  }
  $placeholders = implode(',', array_fill(0, count($columns), '?'));
  q("INSERT INTO equipos_movimientos (".implode(',', $columns).") VALUES ({$placeholders})", $values);
}

function iv2_cancelar_equipos(PDO $pdo, int $id_servicio, array $idsEquipo, ?int $id_usuario = null, ?string $observaciones = null, string $tipoEvento = 'cancelacion', ?string $destinoUbicacion = null, ?int $idAlmacenDestino = null): array {
  iv2_bootstrap();
  $idColumn = iv2_servicio_equipo_id_column() ?? 'id';
  $idsEquipo = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string)$value), $idsEquipo))));
  if (empty($idsEquipo)) {
    throw new RuntimeException('Selecciona al menos un equipo.');
  }
  $out = [];
  foreach ($idsEquipo as $idEquipo) {
    $row = qone(
      "SELECT se.{$idColumn} AS row_id, se.id_equipo, se.id_servicio, se.fecha_liberacion, e.equipo
       FROM servicio_equipo se
       LEFT JOIN equipos e ON e.id_equipo = se.id_equipo
       WHERE se.id_servicio=? AND se.id_equipo=? AND se.fecha_liberacion IS NULL
       ".iv2_order_sql('servicio_equipo', 'se', ['fecha_asignacion', 'updated_at', 'created_at', $idColumn])."
       LIMIT 1 FOR UPDATE",
      [$id_servicio, $idEquipo]
    );
    if (!$row) {
      throw new RuntimeException('El equipo '.$idEquipo.' ya no esta activo en este servicio.');
    }
    q(
      "UPDATE servicio_equipo
       SET fecha_liberacion=?, id_usuario=?, observaciones=COALESCE(?, observaciones), updated_at=CURRENT_TIMESTAMP()
       WHERE {$idColumn}=?",
      [iv2_now(), $id_usuario, $observaciones, $row['row_id']]
    );
    $setEquipo = ["estatus='disponible'"];
    $paramsEquipo = [];
    if (iv2_has_column('equipos', 'ubicacion')) {
      $setEquipo[] = 'ubicacion=?';
      $paramsEquipo[] = $destinoUbicacion ?: 'almacen';
    }
    if (iv2_has_column('equipos', 'id_almacen')) {
      $setEquipo[] = 'id_almacen=?';
      $paramsEquipo[] = $idAlmacenDestino;
    }
    if (iv2_has_column('equipos', 'updated_at')) {
      $setEquipo[] = 'updated_at=CURRENT_TIMESTAMP()';
    }
    $paramsEquipo[] = $idEquipo;
    q("UPDATE equipos SET ".implode(', ', $setEquipo)." WHERE id_equipo=?", $paramsEquipo);
    iv2_registrar_evento_equipo($id_servicio, (int)$row['row_id'], $idEquipo, $tipoEvento, $id_usuario, $observaciones);
    iv2_registrar_movimiento_equipo($idEquipo, $tipoEvento === 'devolucion' ? 'devolucion' : $tipoEvento, $id_servicio, 'servicio', $destinoUbicacion ?: 'almacen', $observaciones, null, $idAlmacenDestino);
    $out[] = [
      'id_equipo' => $idEquipo,
      'equipo' => $row['equipo'] ?? '',
      'tipo_evento' => $tipoEvento,
    ];
  }
  return $out;
}

function iv2_baja_equipos_no_retorno(PDO $pdo, int $id_servicio, array $idsEquipo, ?int $id_usuario = null, ?string $observaciones = null): array {
  iv2_bootstrap();
  $idColumn = iv2_servicio_equipo_id_column() ?? 'id';
  $idsEquipo = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string)$value), $idsEquipo))));
  if (empty($idsEquipo)) return [];

  $out = [];
  foreach ($idsEquipo as $idEquipo) {
    $row = qone(
      "SELECT se.{$idColumn} AS row_id, se.id_equipo, e.equipo
       FROM servicio_equipo se
       LEFT JOIN equipos e ON e.id_equipo = se.id_equipo
       WHERE se.id_servicio=? AND se.id_equipo=? AND se.fecha_liberacion IS NULL
       ".iv2_order_sql('servicio_equipo', 'se', ['fecha_asignacion', 'updated_at', 'created_at', $idColumn])."
       LIMIT 1 FOR UPDATE",
      [$id_servicio, $idEquipo]
    );
    if (!$row) continue;
    $nota = trim((string)$observaciones) ?: 'Equipo no retorna por dano al cierre';
    q(
      "UPDATE servicio_equipo
       SET fecha_liberacion=?, id_usuario=?, observaciones=?, updated_at=CURRENT_TIMESTAMP()
       WHERE {$idColumn}=?",
      [iv2_now(), $id_usuario, $nota, $row['row_id']]
    );

    $setEquipo = ["estatus='baja'"];
    $paramsEquipo = [];
    if (iv2_has_column('equipos', 'ubicacion')) {
      $setEquipo[] = 'ubicacion=?';
      $paramsEquipo[] = 'Baja por dano / no retorno';
    }
    if (iv2_has_column('equipos', 'id_almacen')) {
      $setEquipo[] = 'id_almacen=NULL';
    }
    if (iv2_has_column('equipos', 'updated_at')) {
      $setEquipo[] = 'updated_at=CURRENT_TIMESTAMP()';
    }
    $paramsEquipo[] = $idEquipo;
    q("UPDATE equipos SET ".implode(', ', $setEquipo)." WHERE id_equipo=?", $paramsEquipo);

    iv2_registrar_evento_equipo($id_servicio, (int)$row['row_id'], $idEquipo, 'baja_por_dano', $id_usuario, $nota);
    iv2_registrar_movimiento_equipo($idEquipo, 'baja_por_dano', $id_servicio, 'servicio', 'baja', $nota);
    $out[] = [
      'id_equipo' => $idEquipo,
      'equipo' => $row['equipo'] ?? '',
      'tipo_evento' => 'baja_por_dano',
    ];
  }
  return $out;
}

function iv2_liberar_equipos_servicio(PDO $pdo, int $id_servicio, ?int $id_usuario = null, ?string $observaciones = null): int {
  iv2_bootstrap();
  $idColumn = iv2_servicio_equipo_id_column() ?? 'id';
  $rows = qall(
    "SELECT se.{$idColumn} AS row_id, se.id_equipo
     FROM servicio_equipo se
     WHERE se.id_servicio=? AND se.fecha_liberacion IS NULL
     ".iv2_order_sql('servicio_equipo', 'se', ['fecha_asignacion', 'updated_at', 'created_at', $idColumn]),
    [$id_servicio]
  );
  if (empty($rows)) return 0;
  return count(iv2_cancelar_equipos($pdo, $id_servicio, array_column($rows, 'id_equipo'), $id_usuario, $observaciones, 'devolucion'));
}

function iv2_pendientes_devolucion_cajas(int $id_servicio): array {
  iv2_bootstrap();
  if (!db_table_exists('renta_cajas')) return [];
  $codigoExpr = iv2_servicio_caja_codigo_expr('sc');
  $rentaIdColumn = iv2_renta_cajas_id_column();
  try {
    return qall(
      "SELECT ".($rentaIdColumn ? "rc.{$rentaIdColumn}" : "NULL")." AS id, rc.codigo_caja AS codigo, rc.fecha_salida, sc.tipo_asignacion,
              c.modelo, c.reciclado
       FROM renta_cajas rc
       LEFT JOIN servicio_caja sc ON sc.id_servicio = rc.id_servicio AND {$codigoExpr} = rc.codigo_caja
       LEFT JOIN cajas c ON c.codigo = rc.codigo_caja
       WHERE rc.id_servicio=? AND rc.fecha_devolucion IS NULL
       ".iv2_order_sql('renta_cajas', 'rc', ['fecha_salida', 'updated_at', 'created_at', $rentaIdColumn]),
      [$id_servicio]
    );
  } catch (Throwable $e) {
    return [];
  }
}

function iv2_assert_cierre_permitido(int $id_servicio): void {
  $pendientes = iv2_pendientes_devolucion_cajas($id_servicio);
  if (!empty($pendientes)) {
    throw new RuntimeException('No se puede cerrar el servicio mientras existan cajas pendientes de devolucion.');
  }
}

function iv2_disponibilidad_articulos(?string $query = null, ?int $limit = null): array {
  iv2_bootstrap();
  if (!db_table_exists('articulos')) return [];
  $params = [];
  $where = ["COALESCE(eliminado,0)=0", "COALESCE(existencias,0) > 0"];
  $query = iv2_search_term($query);
  if ($query !== '') {
    $where[] = "(articulo LIKE ? OR CAST(id AS CHAR) LIKE ?)";
    $params[] = '%'.$query.'%';
    $params[] = '%'.$query.'%';
  }
  $sql = "
    SELECT id, articulo, COALESCE(existencias,0) AS existencias
    FROM articulos
    WHERE ".implode(' AND ', $where)."
    ORDER BY articulo ASC
  ";
  return qall(iv2_append_limit($sql, iv2_limit($limit)), $params);
}

function iv2_disponibilidad_cajas(?string $query = null, ?int $limit = null): array {
  iv2_bootstrap();
  $query = iv2_search_term($query);
  $limit = iv2_limit($limit, $query === '' ? 20 : 30, 60);
  if (db_table_exists('vw_cajas_estado')) {
    $viewColumns = iv2_table_columns('vw_cajas_estado');
    if (in_array('codigo', $viewColumns, true)) {
      $select = [
        'codigo',
        in_array('modelo', $viewColumns, true) ? 'modelo' : "'' AS modelo",
        in_array('estatus_logico', $viewColumns, true) ? 'estatus_logico' : "NULL AS estatus_logico",
        in_array('disponible', $viewColumns, true) ? 'disponible' : "NULL AS disponible",
        in_array('reciclado', $viewColumns, true) ? 'reciclado' : "NULL AS reciclado",
        in_array('veces_usado', $viewColumns, true) ? 'veces_usado' : "NULL AS veces_usado",
        in_array('es_rentado', $viewColumns, true) ? 'es_rentado' : "0 AS es_rentado",
      ];
      $where = [];
      if (in_array('estatus_logico', $viewColumns, true) && in_array('disponible', $viewColumns, true)) {
        $where[] = "COALESCE(estatus_logico,'')='disponible'";
        $where[] = "COALESCE(disponible,0)=1";
      } elseif (in_array('estatus_logico', $viewColumns, true)) {
        $where[] = "COALESCE(estatus_logico,'')='disponible'";
      } elseif (in_array('disponible', $viewColumns, true)) {
        $where[] = "COALESCE(disponible,0)=1";
      }
      $params = [];
      if ($query !== '') {
        $searchParts = [];
        if (in_array('codigo', $viewColumns, true)) {
          $searchParts[] = "codigo LIKE ?";
          $params[] = '%'.$query.'%';
        }
        if (in_array('modelo', $viewColumns, true)) {
          $searchParts[] = "modelo LIKE ?";
          $params[] = '%'.$query.'%';
        }
        if (!empty($searchParts)) {
          $where[] = '('.implode(' OR ', $searchParts).')';
        }
      }
      if (!empty($where)) {
        $sql = "
          SELECT ".implode(', ', $select)."
          FROM vw_cajas_estado
          WHERE ".implode(' AND ', $where)."
          ORDER BY modelo ASC, codigo ASC
        ";
        try {
          $rows = qall(iv2_append_limit($sql, $limit), $params);
          foreach ($rows as &$row) {
            $row['tipo_asignacion_sugerida'] = !empty($row['es_rentado']) ? 'renta' : 'permanente';
          }
          return $rows;
        } catch (Throwable $e) {
        }
      }
    }
  }

  if (!db_table_exists('cajas')) return [];
  $select = [
    'codigo',
    iv2_has_column('cajas', 'modelo') ? 'modelo' : "'' AS modelo",
    iv2_has_column('cajas', 'estatus_logico') ? 'estatus_logico' : "NULL AS estatus_logico",
    iv2_has_column('cajas', 'disponible') ? 'disponible' : "NULL AS disponible",
    iv2_has_column('cajas', 'reciclado') ? 'reciclado' : "NULL AS reciclado",
    iv2_has_column('cajas', 'veces_usado') ? 'veces_usado' : "NULL AS veces_usado",
    iv2_has_column('cajas', 'ciclos_uso') ? 'ciclos_uso' : "NULL AS ciclos_uso",
    iv2_has_column('cajas', 'estado') ? 'estado' : "NULL AS estado",
    iv2_has_column('cajas', 'es_rentado') ? 'es_rentado' : "0 AS es_rentado",
  ];
  $where = ["COALESCE(eliminado,0)=0"];
  if (iv2_has_column('cajas', 'estatus_logico') && iv2_has_column('cajas', 'disponible')) {
    $where[] = "COALESCE(estatus_logico,'')='disponible'";
    $where[] = "COALESCE(disponible,0)=1";
  } elseif (iv2_has_column('cajas', 'disponible')) {
    $where[] = "COALESCE(disponible,0)=1";
  } elseif (iv2_has_column('cajas', 'estado')) {
    $where[] = "COALESCE(estado,'') IN ('nuevo','reciclado','disponible')";
  }
  $params = [];
  if ($query !== '') {
    $searchParts = ["codigo LIKE ?"];
    $params[] = '%'.$query.'%';
    if (iv2_has_column('cajas', 'modelo')) {
      $searchParts[] = "modelo LIKE ?";
      $params[] = '%'.$query.'%';
    }
    $where[] = '('.implode(' OR ', $searchParts).')';
  }
  $rows = qall(
    iv2_append_limit(
      "SELECT ".implode(', ', $select)."
       FROM cajas
       WHERE ".implode(' AND ', $where)."
       ORDER BY modelo ASC, codigo ASC",
      $limit
    ),
    $params
  );
  foreach ($rows as &$row) {
    $row['tipo_asignacion_sugerida'] = !empty($row['es_rentado']) ? 'renta' : 'permanente';
  }
  return $rows;
}

function iv2_disponibilidad_equipos(?string $query = null, ?int $limit = null, bool $grouped = false, ?string $group = null): array {
  iv2_bootstrap();
  if (!db_table_exists('equipos')) return [];
  $query = iv2_search_term($query);
  $group = iv2_search_term($group);
  $limit = iv2_limit($limit, $query === '' ? 25 : 40, 80);
  if ($grouped) {
    $params = [];
    $where = ["COALESCE(e.eliminado,0)=0", "se.id_equipo IS NULL", "COALESCE(e.estatus,'disponible')='disponible'"];
    if ($query !== '') {
      $where[] = "e.equipo LIKE ?";
      $params[] = '%'.$query.'%';
    }
    return qall(
      iv2_append_limit(
        "SELECT e.equipo, COUNT(*) AS disponibles, MIN(e.id_equipo) AS primer_id
         FROM equipos e
         LEFT JOIN servicio_equipo se
           ON se.id_equipo = e.id_equipo AND se.fecha_liberacion IS NULL
         WHERE ".implode(' AND ', $where)."
         GROUP BY e.equipo
         ORDER BY e.equipo ASC",
        $limit
      ),
      $params
    );
  }
  $select = [
    'e.id_equipo',
    'e.equipo',
    "e.estatus AS estado_actual",
  ];
  $where = ["COALESCE(e.eliminado,0)=0", "se.id_equipo IS NULL"];
  $where[] = "COALESCE(e.estatus,'disponible')='disponible'";
  $params = [];
  if ($group !== '') {
    $where[] = "e.equipo = ?";
    $params[] = $group;
  }
  if ($query !== '') {
    $where[] = "(e.id_equipo LIKE ? OR e.equipo LIKE ?)";
    $params[] = '%'.$query.'%';
    $params[] = '%'.$query.'%';
  }
  return qall(
    iv2_append_limit(
      "SELECT ".implode(', ', $select)."
       FROM equipos e
       LEFT JOIN servicio_equipo se
         ON se.id_equipo = e.id_equipo AND se.fecha_liberacion IS NULL
       WHERE ".implode(' AND ', $where)."
       ORDER BY e.equipo ASC, e.id_equipo ASC",
      $limit
    ),
    $params
  );
}

function iv2_disponibilidad_compuesta(): array {
  return [
    'articulos' => iv2_disponibilidad_articulos(),
    'cajas' => iv2_disponibilidad_cajas(),
    'equipos' => iv2_disponibilidad_equipos(),
  ];
}

function iv2_servicio_tiene_registros(string $table, int $id_servicio): bool {
  if ($id_servicio <= 0) return false;
  $idColumn = 'id_servicio';
  try {
    return (bool)qone("SELECT 1 FROM {$table} WHERE {$idColumn}=? LIMIT 1", [$id_servicio]);
  } catch (Throwable $e) {
    return false;
  }
}

function iv2_servicio_articulos(int $id_servicio): array {
  try {
    return iv2_servicio_articulo_rows($id_servicio, false);
  } catch (Throwable $e) {
    return [];
  }
}

function iv2_servicio_cajas(int $id_servicio): array {
  iv2_bootstrap();
  if (!iv2_servicio_tiene_registros('servicio_caja', $id_servicio)) return [];
  $codigoExpr = iv2_servicio_caja_codigo_expr('sc');
  try {
    return qall(
      "SELECT {$codigoExpr} AS codigo, sc.tipo_asignacion, sc.fecha_asignacion AS created_at,
              c.modelo, c.reciclado, COALESCE(c.veces_usado, c.ciclos_uso, 0) AS veces_usado
       FROM servicio_caja sc
       LEFT JOIN cajas c ON c.codigo = {$codigoExpr}
       WHERE sc.id_servicio=? AND TRIM(COALESCE({$codigoExpr}, '')) <> ''
       ".iv2_order_sql('servicio_caja', 'sc', ['fecha_asignacion', 'updated_at', 'created_at', iv2_servicio_caja_id_column()]),
      [$id_servicio]
    );
  } catch (Throwable $e) {
    return [];
  }
}

function iv2_servicio_equipos(int $id_servicio): array {
  iv2_bootstrap();
  if (!iv2_servicio_tiene_registros('servicio_equipo', $id_servicio)) return [];
  try {
    return qall(
      "SELECT se.id_equipo, e.equipo,
              se.fecha_asignacion AS created_at,
              se.fecha_liberacion
       FROM servicio_equipo se
       LEFT JOIN equipos e ON e.id_equipo = se.id_equipo
       WHERE se.id_servicio=? AND se.fecha_liberacion IS NULL
       ".iv2_order_sql('servicio_equipo', 'se', ['fecha_asignacion', 'updated_at', 'created_at', iv2_servicio_equipo_id_column()]),
      [$id_servicio]
    );
  } catch (Throwable $e) {
    return [];
  }
}

function iv2_cancelar_consumo_articulo(PDO $pdo, int $id_servicio, int $idServicioArticulo, int $cantidad, ?int $id_usuario = null, ?string $observaciones = null, string $tipoEvento = 'cancelacion'): array {
  iv2_bootstrap();
  $cantidad = max(0, $cantidad);
  if ($idServicioArticulo <= 0 || $cantidad <= 0) {
    throw new RuntimeException('Selecciona un consumo y una cantidad valida.');
  }
  $rows = iv2_servicio_articulo_rows($id_servicio, true);
  $target = null;
  foreach ($rows as $row) {
    if ((int)($row['id_servicio_articulo'] ?? 0) === $idServicioArticulo) {
      $target = $row;
      break;
    }
  }
  if (!$target) {
    throw new RuntimeException('El consumo seleccionado no existe en este servicio.');
  }
  $disponible = (int)($target['cantidad_cancelable'] ?? $target['cantidad'] ?? 0);
  if ($disponible < $cantidad) {
    throw new RuntimeException('La cantidad excede el consumo activo disponible.');
  }
  $articulo = iv2_articulo_row((int)$target['id_articulo'], true);
  if (!$articulo) {
    throw new RuntimeException('Articulo no encontrado.');
  }
  $restante = (int)($articulo['existencias'] ?? 0) + $cantidad;
  q("UPDATE articulos SET existencias=?, updated_at=CURRENT_TIMESTAMP() WHERE id=?", [$restante, $target['id_articulo']]);
  q(
    "INSERT INTO kardex_articulos (id_articulo, tipo_mov, cantidad, stock_restante, id_servicio, fecha, id_usuario, observaciones)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
    [$target['id_articulo'], $tipoEvento === 'devolucion' ? 'entrada' : 'ajuste_cancelacion', $cantidad, $restante, $id_servicio, iv2_now(), $id_usuario, $observaciones]
  );
  iv2_registrar_evento_articulo($id_servicio, $idServicioArticulo, (int)$target['id_articulo'], $tipoEvento, $cantidad, $id_usuario, $observaciones);
  return [
    'id_servicio' => $id_servicio,
    'id_servicio_articulo' => $idServicioArticulo,
    'id_articulo' => (int)$target['id_articulo'],
    'articulo' => $target['articulo'] ?? ($articulo['articulo'] ?? ''),
    'cantidad' => $cantidad,
    'tipo_evento' => $tipoEvento,
    'stock_restante' => $restante,
  ];
}

function iv2_servicio_equipos_agrupados(int $id_servicio): array {
  $rows = iv2_servicio_equipos($id_servicio);
  if (empty($rows)) return [];
  $groups = [];
  foreach ($rows as $row) {
    $grupo = trim((string)($row['equipo'] ?? 'Sin grupo'));
    if (!isset($groups[$grupo])) {
      $groups[$grupo] = [
        'equipo' => $grupo,
        'cantidad' => 0,
        'ids' => [],
      ];
    }
    $groups[$grupo]['cantidad']++;
    $groups[$grupo]['ids'][] = (string)$row['id_equipo'];
  }
  return array_values($groups);
}
