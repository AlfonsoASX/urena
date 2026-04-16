<?php

function db_cfg(string $key, $default = null) {
  return $GLOBALS['cfg'][$key] ?? $default;
}

function db_schema_bootstrap_mode(): string {
  $mode = strtolower((string)db_cfg('schema_bootstrap_mode', 'ondemand'));
  return in_array($mode, ['always', 'ondemand'], true) ? $mode : 'ondemand';
}

function db_schema_bootstrap_on_request(): bool {
  return db_schema_bootstrap_mode() === 'always';
}

function db_connect_timeout(): int {
  return max(1, (int)db_cfg('db_connect_timeout', 3));
}

function db_perf_enabled(): bool {
  return (bool)db_cfg('perf_log_enabled', false);
}

function db_perf_threshold_ms(): float {
  return max(1, (float)db_cfg('perf_log_threshold_ms', 250));
}

function db_perf_log_path(): string {
  return (string)db_cfg('perf_log_file', __DIR__.'/../storage/logs/perf.log');
}

function db_schema_snapshot_path(): string {
  return (string)db_cfg('schema_snapshot_file', __DIR__.'/../storage/cache/schema_snapshot.php');
}

function db_database_name(): string {
  static $dbName = null;
  if ($dbName !== null) return $dbName;

  $dsn = (string)db_cfg('dsn', '');
  if (preg_match('/(?:^|;)dbname=([^;]+)/', $dsn, $match)) {
    $dbName = trim((string)$match[1]);
  }
  if ($dbName !== null && $dbName !== '') return $dbName;

  $dbName = '';
  try {
    $started = microtime(true);
    $value = db()->query("SELECT DATABASE()")->fetchColumn();
    db_perf_record_query("SELECT DATABASE()", (microtime(true) - $started) * 1000);
    if (is_string($value) && $value !== '') {
      $dbName = $value;
    }
  } catch (Throwable $e) {
  }

  return $dbName;
}

function db_perf_boot(): void {
  if (!isset($GLOBALS['_db_perf']) || !is_array($GLOBALS['_db_perf'])) {
    $GLOBALS['_db_perf'] = [
      'request_started_at' => microtime(true),
      'query_count' => 0,
      'sql_ms' => 0.0,
      'slowest_query_ms' => 0.0,
      'slowest_query' => null,
      'connect_ms' => 0.0,
      'connect_logged' => false,
      'queries' => [],
    ];
  }
}

function db_perf_state(): array {
  db_perf_boot();
  return $GLOBALS['_db_perf'];
}

function db_perf_log_entry(array $entry): void {
  $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json === false) return;

  error_log($json);

  $path = db_perf_log_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  @file_put_contents($path, $json.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function db_perf_record_connect(float $ms): void {
  db_perf_boot();
  $GLOBALS['_db_perf']['connect_ms'] = round($ms, 3);
  if (!db_perf_enabled()) return;
  if ($GLOBALS['_db_perf']['connect_logged']) return;
  if ($ms < db_perf_threshold_ms()) return;

  $GLOBALS['_db_perf']['connect_logged'] = true;
  db_perf_log_entry([
    'type' => 'perf_connect',
    'ts' => date('c'),
    'route' => $_GET['r'] ?? 'cli',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'connect_ms' => round($ms, 3),
    'dsn' => preg_replace('/;dbname=.*$/', '', (string)db_cfg('dsn', '')),
  ]);
}

function db_perf_record_query(string $sql, float $ms, array $params = []): void {
  db_perf_boot();
  $GLOBALS['_db_perf']['query_count']++;
  $GLOBALS['_db_perf']['sql_ms'] += $ms;
  if ($ms >= ($GLOBALS['_db_perf']['slowest_query_ms'] ?? 0)) {
    $GLOBALS['_db_perf']['slowest_query_ms'] = $ms;
    $GLOBALS['_db_perf']['slowest_query'] = trim(preg_replace('/\s+/', ' ', $sql));
  }

  if (!db_perf_enabled()) return;
  if ($ms < db_perf_threshold_ms()) return;

  db_perf_log_entry([
    'type' => 'perf_query',
    'ts' => date('c'),
    'route' => $_GET['r'] ?? 'cli',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'query_ms' => round($ms, 3),
    'sql' => trim(preg_replace('/\s+/', ' ', $sql)),
    'params_count' => count($params),
  ]);
}

function db_perf_log_request(): void {
  db_perf_boot();
  if (!db_perf_enabled()) return;

  $totalMs = (microtime(true) - ($GLOBALS['_db_perf']['request_started_at'] ?? microtime(true))) * 1000;
  if ($totalMs < db_perf_threshold_ms()) return;

  db_perf_log_entry([
    'type' => 'perf_request',
    'ts' => date('c'),
    'route' => $_GET['r'] ?? 'cli',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'total_ms' => round($totalMs, 3),
    'connect_ms' => round((float)($GLOBALS['_db_perf']['connect_ms'] ?? 0), 3),
    'query_count' => (int)($GLOBALS['_db_perf']['query_count'] ?? 0),
    'sql_ms' => round((float)($GLOBALS['_db_perf']['sql_ms'] ?? 0), 3),
    'slowest_query_ms' => round((float)($GLOBALS['_db_perf']['slowest_query_ms'] ?? 0), 3),
    'slowest_query' => $GLOBALS['_db_perf']['slowest_query'] ?? null,
    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
  ]);
}

function db_perf_recent(int $limit = 100): array {
  $path = db_perf_log_path();
  if (!is_file($path)) return [];
  $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines) || empty($lines)) return [];
  $lines = array_slice($lines, -1 * max(1, $limit));
  $rows = [];
  foreach (array_reverse($lines) as $line) {
    $decoded = json_decode($line, true);
    if (is_array($decoded)) {
      $rows[] = $decoded;
    }
  }
  return $rows;
}

function db_schema_snapshot_clear_file(): void {
  $path = db_schema_snapshot_path();
  if (is_file($path)) {
    @unlink($path);
  }
  unset($GLOBALS['_db_schema_snapshot']);
}

function db_schema_snapshot_load(): array {
  if (isset($GLOBALS['_db_schema_snapshot']) && is_array($GLOBALS['_db_schema_snapshot'])) {
    return $GLOBALS['_db_schema_snapshot'];
  }
  $path = db_schema_snapshot_path();
  if (!is_file($path)) {
    $GLOBALS['_db_schema_snapshot'] = [];
    return $GLOBALS['_db_schema_snapshot'];
  }
  $snapshot = @include $path;
  if (!is_array($snapshot)) {
    $snapshot = [];
  }
  $GLOBALS['_db_schema_snapshot'] = $snapshot;
  return $snapshot;
}

function db_schema_snapshot_write(array $snapshot): void {
  $path = db_schema_snapshot_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  $content = "<?php\nreturn ".var_export($snapshot, true).";\n";
  @file_put_contents($path, $content, LOCK_EX);
  $GLOBALS['_db_schema_snapshot'] = $snapshot;
}

function db_schema_tracked_tables(): array {
  return [
    'usuarios', 'roles', 'usuarios_roles', 'permisos', 'roles_permisos',
    'servicios', 'fallecido', 'servicio_fallecido', 'servicio_asignacion', 'servicio_seguimiento',
    'servicio_cierre', 'servicio_pagos', 'servicio_bitacora', 'servicio_articulos', 'kardex_articulos',
    'servicio_caja', 'renta_cajas', 'servicio_equipo',
    'articulos', 'articulos_movimientos', 'articulos_salida_servicio', 'articulos_traspasos', 'articulos_traspaso_det',
    'cajas', 'cajas_movimientos', 'equipos', 'equipos_movimientos', 'vehiculos', 'salas',
    'proveedores',
    'futuro_contratos', 'futuro_abonos', 'futuro_comision_semanal', 'futuro_gestion', 'futuro_personal',
    'futuro_contrato_cobrador', 'futuro_contrato_vendedor', 'futuro_logs',
    'titular_contrato', 'titular_dom', 'titulares', 'domicilios',
    'vw_titular_contrato', 'vw_cajas_estado',
  ];
}

function db_schema_snapshot_refresh(): void {
  $snapshot = [
    'generated_at' => date('c'),
    'tables' => [],
    'columns' => [],
    'indexes' => [],
  ];

  $schema = db_database_name();
  $trackedTables = array_values(array_unique(array_filter(db_schema_tracked_tables(), fn($table) => preg_match('/^[a-zA-Z0-9_]+$/', (string)$table))));
  foreach ($trackedTables as $tracked) {
    $snapshot['tables'][$tracked] = false;
    $snapshot['columns'][$tracked] = [];
    $snapshot['indexes'][$tracked] = [];
  }
  if ($schema === '' || empty($trackedTables)) {
    db_schema_snapshot_write($snapshot);
    db_invalidate_schema_cache();
    return;
  }

  $placeholders = implode(',', array_fill(0, count($trackedTables), '?'));
  $params = array_merge([$schema], $trackedTables);
  $pdo = db();

  try {
    $started = microtime(true);
    $stmt = $pdo->prepare("
      SELECT TABLE_NAME
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_perf_record_query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (...)",
      (microtime(true) - $started) * 1000,
      $params
    );
    foreach ($rows as $row) {
      $table = (string)($row['TABLE_NAME'] ?? '');
      if ($table !== '') {
        $snapshot['tables'][$table] = true;
      }
    }
  } catch (Throwable $e) {
  }

  try {
    $started = microtime(true);
    $stmt = $pdo->prepare("
      SELECT TABLE_NAME, COLUMN_NAME
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})
      ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_perf_record_query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (...)",
      (microtime(true) - $started) * 1000,
      $params
    );
    foreach ($rows as $row) {
      $table = (string)($row['TABLE_NAME'] ?? '');
      $column = (string)($row['COLUMN_NAME'] ?? '');
      if ($table !== '' && $column !== '' && isset($snapshot['columns'][$table])) {
        $snapshot['columns'][$table][] = $column;
      }
    }
  } catch (Throwable $e) {
  }

  try {
    $started = microtime(true);
    $stmt = $pdo->prepare("
      SELECT TABLE_NAME, INDEX_NAME
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_perf_record_query("SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (...)",
      (microtime(true) - $started) * 1000,
      $params
    );
    foreach ($rows as $row) {
      $table = (string)($row['TABLE_NAME'] ?? '');
      $index = (string)($row['INDEX_NAME'] ?? '');
      if ($table !== '' && $index !== '' && isset($snapshot['indexes'][$table])) {
        $snapshot['indexes'][$table][$index] = true;
      }
    }
  } catch (Throwable $e) {
  }

  foreach ($snapshot['indexes'] as $table => $indexes) {
    if (is_array($indexes)) {
      $snapshot['indexes'][$table] = array_keys($indexes);
    }
  }

  db_schema_snapshot_write($snapshot);
  db_invalidate_schema_cache();
}

function db_schema_snapshot_table_exists(string $table): ?bool {
  $snapshot = db_schema_snapshot_load();
  if (isset($snapshot['tables'][$table])) return (bool)$snapshot['tables'][$table];
  return null;
}

function db_schema_snapshot_column_exists(string $table, string $column): ?bool {
  $snapshot = db_schema_snapshot_load();
  if (!isset($snapshot['columns'][$table]) || !is_array($snapshot['columns'][$table])) return null;
  return in_array($column, $snapshot['columns'][$table], true);
}

function db_schema_snapshot_index_exists(string $table, string $index): ?bool {
  $snapshot = db_schema_snapshot_load();
  if (!isset($snapshot['indexes'][$table]) || !is_array($snapshot['indexes'][$table])) return null;
  return in_array($index, $snapshot['indexes'][$table], true);
}

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;

  db_perf_boot();
  $cfg = $GLOBALS['cfg'];
  $timeout = db_connect_timeout();
  $started = microtime(true);

  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => $timeout,
  ];

  $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], $options);
  db_perf_record_connect((microtime(true) - $started) * 1000);
  return $pdo;
}

function db_enable_big_selects(): void {
  static $enabled = false;
  if ($enabled) return;
  $pdo = db();
  $pdo->exec("SET SQL_BIG_SELECTS=1");
  $enabled = true;
}

function db_schema_cache_bucket(string $name): array {
  if (!isset($GLOBALS['_db_schema_cache']) || !is_array($GLOBALS['_db_schema_cache'])) {
    $GLOBALS['_db_schema_cache'] = [];
  }
  if (!isset($GLOBALS['_db_schema_cache'][$name]) || !is_array($GLOBALS['_db_schema_cache'][$name])) {
    $GLOBALS['_db_schema_cache'][$name] = [];
  }
  return $GLOBALS['_db_schema_cache'][$name];
}

function db_schema_cache_set(string $name, string $key, $value): void {
  if (!isset($GLOBALS['_db_schema_cache']) || !is_array($GLOBALS['_db_schema_cache'])) {
    $GLOBALS['_db_schema_cache'] = [];
  }
  if (!isset($GLOBALS['_db_schema_cache'][$name]) || !is_array($GLOBALS['_db_schema_cache'][$name])) {
    $GLOBALS['_db_schema_cache'][$name] = [];
  }
  $GLOBALS['_db_schema_cache'][$name][$key] = $value;
}

function db_schema_cache_delete_prefix(string $name, string $prefix): void {
  $bucket = db_schema_cache_bucket($name);
  foreach (array_keys($bucket) as $key) {
    if (strpos((string)$key, $prefix) === 0) {
      unset($GLOBALS['_db_schema_cache'][$name][$key]);
    }
  }
}

function db_invalidate_schema_cache(?string $table = null, ?string $column = null): void {
  if ($table === null) {
    unset($GLOBALS['_db_schema_cache']);
    return;
  }
  db_schema_cache_delete_prefix('tables', $table);
  db_schema_cache_delete_prefix('columns', $table.'.');
  db_schema_cache_delete_prefix('indexes', $table.'.');
  if ($column !== null) {
    unset($GLOBALS['_db_schema_cache']['columns'][$table.'.'.$column]);
  }
}

function db_table_exists(string $table): bool {
  $cache = db_schema_cache_bucket('tables');
  if (isset($cache[$table])) return (bool)$cache[$table];
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;

  $snapshotValue = db_schema_snapshot_table_exists($table);
  if ($snapshotValue !== null) {
    db_schema_cache_set('tables', $table, $snapshotValue);
    return $snapshotValue;
  }

  $schema = db_database_name();
  try {
    $started = microtime(true);
    $stmt = db()->prepare("
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
      LIMIT 1
    ");
    $stmt->execute([$schema, $table]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    db_perf_record_query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1",
      (microtime(true) - $started) * 1000,
      [$schema, $table]
    );
    db_schema_cache_set('tables', $table, (bool)$row);
  } catch (Throwable $e) {
    db_schema_cache_set('tables', $table, false);
  }
  $cache = db_schema_cache_bucket('tables');
  return (bool)($cache[$table] ?? false);
}

function db_column_exists(string $table, string $column): bool {
  $key = $table.'.'.$column;
  $cache = db_schema_cache_bucket('columns');
  if (isset($cache[$key])) return (bool)$cache[$key];
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) return false;

  $snapshotValue = db_schema_snapshot_column_exists($table, $column);
  if ($snapshotValue !== null) {
    db_schema_cache_set('columns', $key, $snapshotValue);
    return $snapshotValue;
  }

  $schema = db_database_name();
  try {
    $started = microtime(true);
    $stmt = db()->prepare("
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $stmt->execute([$schema, $table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    db_perf_record_query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
      (microtime(true) - $started) * 1000,
      [$schema, $table, $column]
    );
    db_schema_cache_set('columns', $key, (bool)$row);
  } catch (Throwable $e) {
    db_schema_cache_set('columns', $key, false);
  }
  $cache = db_schema_cache_bucket('columns');
  return (bool)($cache[$key] ?? false);
}

function db_index_exists(string $table, string $index): bool {
  $key = $table.'.'.$index;
  $cache = db_schema_cache_bucket('indexes');
  if (isset($cache[$key])) return (bool)$cache[$key];
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) return false;

  $snapshotValue = db_schema_snapshot_index_exists($table, $index);
  if ($snapshotValue !== null) {
    db_schema_cache_set('indexes', $key, $snapshotValue);
    return $snapshotValue;
  }

  $schema = db_database_name();
  try {
    $started = microtime(true);
    $stmt = db()->prepare("
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
      LIMIT 1
    ");
    $stmt->execute([$schema, $table, $index]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    db_perf_record_query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1",
      (microtime(true) - $started) * 1000,
      [$schema, $table, $index]
    );
    db_schema_cache_set('indexes', $key, (bool)$row);
  } catch (Throwable $e) {
    db_schema_cache_set('indexes', $key, false);
  }
  $cache = db_schema_cache_bucket('indexes');
  return (bool)($cache[$key] ?? false);
}

function db_add_column_if_missing(string $table, string $column, string $ddl): void {
  if (!db_table_exists($table) || db_column_exists($table, $column)) return;
  try {
    q("ALTER TABLE {$table} ADD COLUMN {$column} {$ddl}");
    db_invalidate_schema_cache($table, $column);
    db_schema_snapshot_clear_file();
  } catch (Throwable $e) {
  }
}

function db_add_index_if_missing(string $table, string $index, string $ddl): void {
  if (!db_table_exists($table) || db_index_exists($table, $index)) return;
  try {
    q("ALTER TABLE {$table} ADD {$ddl}");
    db_invalidate_schema_cache($table);
    db_schema_snapshot_clear_file();
  } catch (Throwable $e) {
  }
}

function db_register_schema_repair(string $key, callable $callback): void {
  $GLOBALS['_db_schema_repairs'][$key] = $callback;
}

function db_run_registered_repairs(): void {
  static $running = false;
  if ($running) return;
  $running = true;
  foreach (($GLOBALS['_db_schema_repairs'] ?? []) as $callback) {
    try {
      $callback();
    } catch (Throwable $e) {
      db_log_error($e, ['scope' => 'schema_repair']);
    }
  }
  db_invalidate_schema_cache();
  try {
    db_schema_snapshot_refresh();
  } catch (Throwable $e) {
    db_log_error($e, ['scope' => 'schema_snapshot_refresh']);
  }
  $running = false;
}

function db_is_schema_exception(Throwable $e): bool {
  $message = strtolower($e->getMessage());
  foreach ([
    'unknown column',
    'base table or view not found',
    'doesn\'t exist',
    'duplicate column name',
    'unknown table',
    'table doesn\'t exist',
  ] as $needle) {
    if (strpos($message, $needle) !== false) return true;
  }
  return false;
}

function db_log_error(Throwable $e, array $context = []): void {
  $line = '['.date('Y-m-d H:i:s').'] '.$e->getMessage();
  if (!empty($context)) {
    $line .= ' | '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  error_log($line);
}

function db_rollback_open_transaction(): void {
  try {
    $pdo = db();
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
  } catch (Throwable $e) {
  }
}

function db_user_error_message(Throwable $e): string {
  if ($e instanceof PDOException) {
    return "Ocurrio un problema de base de datos. No se confirmaron cambios y la operacion fue cancelada de forma segura.";
  }
  return "Ocurrio un error inesperado. No se confirmaron cambios y la operacion fue cancelada de forma segura.";
}

function db_prepare_execute(string $sql, array $params = [], string $mode = 'execute') {
  static $repairAttempts = [];
  $pdo = db();
  $started = microtime(true);
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $elapsedMs = (microtime(true) - $started) * 1000;
    db_perf_record_query($sql, $elapsedMs, $params);
    if ($mode === 'fetch_all') return $st->fetchAll();
    if ($mode === 'fetch_one') {
      $row = $st->fetch();
      return $row ?: null;
    }
    return true;
  } catch (Throwable $e) {
    $elapsedMs = (microtime(true) - $started) * 1000;
    db_perf_record_query($sql, $elapsedMs, $params);
    $fingerprint = md5($sql.'|'.$e->getMessage());
    if (db_is_schema_exception($e) && empty($repairAttempts[$fingerprint])) {
      $repairAttempts[$fingerprint] = true;
      // DDL causes implicit MySQL commit — never run repairs inside an active transaction
      // to avoid corrupting the transaction state (PDO in_txn flag vs MySQL reality).
      if (!$pdo->inTransaction()) {
        db_run_registered_repairs();
        return db_prepare_execute($sql, $params, $mode);
      }
    }
    db_log_error($e, ['sql' => $sql, 'params' => $params, 'mode' => $mode, 'route' => $_GET['r'] ?? null]);
    throw $e;
  }
}

function q($sql, $params = []) {
  return db_prepare_execute($sql, $params, 'execute');
}

function qall($sql, $params = []) {
  return db_prepare_execute($sql, $params, 'fetch_all');
}

function qone($sql, $params = []) {
  return db_prepare_execute($sql, $params, 'fetch_one');
}
