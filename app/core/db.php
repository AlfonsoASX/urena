<?php

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfg = $GLOBALS['cfg'];
  $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("SET SQL_BIG_SELECTS=1");
  return $pdo;
}

function db_table_exists(string $table): bool {
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
  try {
    qone("SELECT 1 FROM {$table} LIMIT 1");
    $cache[$table] = true;
  } catch (Throwable $e) {
    $cache[$table] = false;
  }
  return $cache[$table];
}

function db_column_exists(string $table, string $column): bool {
  static $cache = [];
  $key = $table.'.'.$column;
  if (isset($cache[$key])) return $cache[$key];
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) return false;
  try {
    $row = qone("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
    $cache[$key] = (bool)$row;
  } catch (Throwable $e) {
    $cache[$key] = false;
  }
  return $cache[$key];
}

function db_add_column_if_missing(string $table, string $column, string $ddl): void {
  if (!db_table_exists($table) || db_column_exists($table, $column)) return;
  try {
    q("ALTER TABLE {$table} ADD COLUMN {$column} {$ddl}");
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
  $pdo->exec("SET SQL_BIG_SELECTS=1");
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ($mode === 'fetch_all') return $st->fetchAll();
    if ($mode === 'fetch_one') {
      $row = $st->fetch();
      return $row ?: null;
    }
    return true;
  } catch (Throwable $e) {
    $fingerprint = md5($sql.'|'.$e->getMessage());
    if (db_is_schema_exception($e) && empty($repairAttempts[$fingerprint])) {
      $repairAttempts[$fingerprint] = true;
      db_run_registered_repairs();
      return db_prepare_execute($sql, $params, $mode);
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
