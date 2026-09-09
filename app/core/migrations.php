<?php

/**
 * Centro de migraciones. Los archivos SQL y respaldos viven fuera del web root.
 */

function migrations_project_root(): string {
  return dirname(__DIR__, 2);
}

function migrations_source_dirs(): array {
  return [
    migrations_project_root().'/database/migrations',
    migrations_project_root().'/var/migration_center/pending',
  ];
}

function migrations_private_root(): string {
  return migrations_project_root().'/var/migration_center';
}

function migrations_ensure_private_dirs(): void {
  foreach ([
    migrations_private_root(),
    migrations_private_root().'/pending',
    migrations_private_root().'/backups',
  ] as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
      throw new RuntimeException('No fue posible preparar el almacenamiento privado de migraciones.');
    }
    @chmod($dir, 0700);
  }
}

function migrations_valid_filename(string $filename): bool {
  return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,180}\.sql$/', $filename);
}

function migrations_find_file(string $filename): ?string {
  if (!migrations_valid_filename($filename)) return null;
  foreach (migrations_source_dirs() as $dir) {
    $base = realpath($dir);
    if ($base === false) continue;
    $path = realpath($dir.'/'.$filename);
    if ($path !== false && is_file($path) && str_starts_with($path, $base.DIRECTORY_SEPARATOR)) {
      return $path;
    }
  }
  return null;
}

function migrations_strip_sql_comments(string $sql): string {
  $sql = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
  $sql = preg_replace('/^\s*(?:--(?=\s|$)|#).*$/m', ' ', $sql) ?? $sql;
  return trim($sql);
}

function migrations_split_block(string $sql, string $delimiter): array {
  $statements = [];
  $buffer = '';
  $length = strlen($sql);
  $delimiterLength = strlen($delimiter);
  $quote = null;
  $lineComment = false;
  $blockComment = false;

  for ($i = 0; $i < $length; $i++) {
    $char = $sql[$i];
    $next = $i + 1 < $length ? $sql[$i + 1] : '';

    if ($lineComment) {
      $buffer .= $char;
      if ($char === "\n") $lineComment = false;
      continue;
    }
    if ($blockComment) {
      $buffer .= $char;
      if ($char === '*' && $next === '/') {
        $buffer .= '/';
        $i++;
        $blockComment = false;
      }
      continue;
    }
    if ($quote !== null) {
      $buffer .= $char;
      if ($char === '\\' && $i + 1 < $length) {
        $buffer .= $sql[++$i];
        continue;
      }
      if ($char === $quote) {
        if ($i + 1 < $length && $sql[$i + 1] === $quote) {
          $buffer .= $sql[++$i];
        } else {
          $quote = null;
        }
      }
      continue;
    }

    if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
      $lineComment = true;
      $buffer .= $char;
      continue;
    }
    if ($char === '/' && $next === '*') {
      $blockComment = true;
      $buffer .= '/*';
      $i++;
      continue;
    }
    if (in_array($char, ["'", '"', '`'], true)) {
      $quote = $char;
      $buffer .= $char;
      continue;
    }
    if ($delimiterLength > 0 && substr($sql, $i, $delimiterLength) === $delimiter) {
      if (migrations_strip_sql_comments($buffer) !== '') $statements[] = trim($buffer);
      $buffer = '';
      $i += $delimiterLength - 1;
      continue;
    }
    $buffer .= $char;
  }

  if (migrations_strip_sql_comments($buffer) !== '') $statements[] = trim($buffer);
  return $statements;
}

function migrations_split_sql(string $sql): array {
  $statements = [];
  $delimiter = ';';
  $block = '';
  foreach (preg_split('/\R/', $sql) ?: [] as $line) {
    if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
      if (trim($block) !== '') {
        $statements = array_merge($statements, migrations_split_block($block, $delimiter));
        $block = '';
      }
      $delimiter = $match[1];
      continue;
    }
    $block .= $line."\n";
  }
  if (trim($block) !== '') {
    $statements = array_merge($statements, migrations_split_block($block, $delimiter));
  }
  return array_values(array_filter($statements, fn($sql) => migrations_strip_sql_comments($sql) !== ''));
}

function migrations_first_keyword(string $sql): string {
  $clean = migrations_strip_sql_comments($sql);
  return preg_match('/^([A-Z]+)/i', $clean, $match) ? strtoupper($match[1]) : '';
}

function migrations_metadata(string $sql): array {
  $meta = ['name' => '', 'description' => '', 'backup' => []];
  if (preg_match('/^\s*--\s*@name:\s*(.+)$/mi', $sql, $match)) $meta['name'] = trim($match[1]);
  if (preg_match('/^\s*--\s*@description:\s*(.+)$/mi', $sql, $match)) $meta['description'] = trim($match[1]);
  if (preg_match('/^\s*--\s*@backup:\s*(.+)$/mi', $sql, $match)) {
    $meta['backup'] = array_values(array_filter(array_map('trim', explode(',', $match[1]))));
  }
  return $meta;
}

function migrations_detect_tables(string $sql): array {
  $tables = migrations_metadata($sql)['backup'];
  $patterns = [
    '/\b(?:ALTER|CREATE|DROP|TRUNCATE)\s+TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i',
    '/^\s*(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([A-Za-z0-9_]+)`?/mi',
  ];
  foreach ($patterns as $pattern) {
    if (preg_match_all($pattern, $sql, $matches)) {
      $tables = array_merge($tables, $matches[1]);
    }
  }
  $tables = array_filter(array_map('trim', $tables), fn($table) => preg_match('/^[A-Za-z0-9_]+$/', $table));
  $tables = array_values(array_unique($tables));
  sort($tables);
  return $tables;
}

function migrations_risk_flags(string $sql): array {
  $flags = [];
  $checks = [
    'Elimina una base de datos' => '/\bDROP\s+DATABASE\b/i',
    'Elimina una tabla' => '/\bDROP\s+TABLE\b/i',
    'Vacía una tabla' => '/\bTRUNCATE\b/i',
    'Elimina columnas o índices' => '/\bALTER\s+TABLE\b[\s\S]{0,500}\bDROP\s+(?:COLUMN|INDEX|KEY)\b/i',
    'Elimina registros' => '/\bDELETE\s+FROM\b/i',
    'Actualiza registros' => '/^\s*UPDATE\s+`?[A-Za-z0-9_]+`?\b/mi',
  ];
  foreach ($checks as $label => $pattern) {
    if (preg_match($pattern, $sql)) $flags[] = $label;
  }
  return $flags;
}

function migrations_assert_allowed(string $sql): void {
  $sql = migrations_strip_sql_comments($sql);
  $blocked = [
    '/\bDROP\s+DATABASE\b/i' => 'No se permite eliminar bases de datos.',
    '/\b(?:CREATE|ALTER)\s+DATABASE\b/i' => 'No se permite crear o cambiar bases de datos.',
    '/^\s*USE\s+/mi' => 'No se permite cambiar de base de datos.',
    '/\b(?:CREATE|ALTER|DROP)\s+USER\b/i' => 'No se permite administrar usuarios de base de datos.',
    '/\b(?:GRANT|REVOKE)\b/i' => 'No se permite cambiar privilegios.',
    '/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i' => 'No se permite escribir archivos desde SQL.',
    '/\bLOAD\s+DATA\b/i' => 'No se permite cargar archivos desde SQL.',
    '/\bSET\s+GLOBAL\b/i' => 'No se permite cambiar la configuracion global del servidor.',
    '/^\s*(?:SHUTDOWN|KILL|RESET|FLUSH|LOCK\s+TABLES)\b/mi' => 'No se permiten comandos de administracion del servidor.',
  ];
  foreach ($blocked as $pattern => $message) {
    if (preg_match($pattern, $sql)) throw new RuntimeException($message);
  }
}

function migrations_assert_backup_scope(string $sql): void {
  $meta = migrations_metadata($sql);
  $tables = migrations_detect_tables($sql);
  $mutatesExisting = preg_match('/^\s*(?:ALTER|DROP|TRUNCATE|RENAME|UPDATE|DELETE|REPLACE|CALL)\b/mi', migrations_strip_sql_comments($sql));
  if ($mutatesExisting && empty($tables)) {
    throw new RuntimeException('No se pudo determinar que tablas respaldar. Agrega una linea -- @backup: tabla1,tabla2.');
  }
  if (preg_match('/^\s*CALL\b/mi', migrations_strip_sql_comments($sql)) && empty($meta['backup'])) {
    throw new RuntimeException('Una migracion con CALL debe declarar -- @backup: tabla1,tabla2.');
  }
}

function migrations_is_read_only_sql(string $sql): bool {
  $statements = migrations_split_sql($sql);
  if (count($statements) !== 1) return false;
  $keyword = migrations_first_keyword($statements[0]);
  if (!in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'], true)) return false;
  return !preg_match('/\b(?:FOR\s+UPDATE|INTO\s+(?:OUTFILE|DUMPFILE)|SLEEP\s*\(|BENCHMARK\s*\()/i', $statements[0]);
}

function migrations_tables_ready(): bool {
  return db_table_exists('sistema_migraciones') && db_table_exists('sistema_migracion_auditoria');
}

function migrations_initialize(): void {
  migrations_ensure_private_dirs();
  $pdo = db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_migraciones (
    id_migracion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    archivo VARCHAR(190) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    checksum CHAR(64) NOT NULL,
    estado VARCHAR(20) NOT NULL,
    usuario_id INT NULL,
    usuario VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    respaldo VARCHAR(255) NULL,
    sentencias_total INT NOT NULL DEFAULT 0,
    sentencias_ejecutadas INT NOT NULL DEFAULT 0,
    error TEXT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    PRIMARY KEY (id_migracion),
    KEY idx_migraciones_archivo (archivo),
    KEY idx_migraciones_checksum (checksum),
    KEY idx_migraciones_estado (estado)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_migracion_auditoria (
    id_evento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_migracion BIGINT UNSIGNED NULL,
    usuario_id INT NULL,
    usuario VARCHAR(120) NOT NULL,
    accion VARCHAR(40) NOT NULL,
    archivo VARCHAR(190) NULL,
    checksum CHAR(64) NULL,
    exito TINYINT(1) NOT NULL DEFAULT 0,
    detalle TEXT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_evento),
    KEY idx_migracion_auditoria_fecha (created_at),
    KEY idx_migracion_auditoria_migracion (id_migracion)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  db_schema_snapshot_clear_file();
  db_invalidate_schema_cache();
  migrations_audit('inicializar', null, null, true, 'Centro de migraciones inicializado.');
}

function migrations_current_actor(): array {
  $user = current_user() ?: [];
  return [
    'id' => isset($user['id']) ? (int)$user['id'] : null,
    'name' => (string)($user['usuario'] ?? $user['nombre'] ?? 'desconocido'),
    'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
  ];
}

function migrations_verify_password(string $password): bool {
  $user = current_user();
  if (!$user || $password === '') return false;
  $row = qone('SELECT pass FROM usuarios WHERE id=? LIMIT 1', [(int)$user['id']]);
  return $row && password_verify($password, (string)$row['pass']);
}

function migrations_audit(string $action, ?array $migration, ?int $migrationId, bool $success, string $detail): void {
  if (!migrations_tables_ready()) return;
  $actor = migrations_current_actor();
  q("INSERT INTO sistema_migracion_auditoria
      (id_migracion,usuario_id,usuario,accion,archivo,checksum,exito,detalle,ip)
      VALUES (?,?,?,?,?,?,?,?,?)", [
    $migrationId, $actor['id'], $actor['name'], $action,
    $migration['filename'] ?? null, $migration['checksum'] ?? null,
    $success ? 1 : 0, mb_substr($detail, 0, 60000), $actor['ip'],
  ]);
}

function migrations_history_by_file(): array {
  if (!migrations_tables_ready()) return [];
  $rows = qall("SELECT m.* FROM sistema_migraciones m
    JOIN (SELECT archivo,MAX(id_migracion) id_migracion FROM sistema_migraciones GROUP BY archivo) x
      ON x.id_migracion=m.id_migracion");
  $result = [];
  foreach ($rows as $row) $result[$row['archivo']] = $row;
  return $result;
}

function migrations_recent_history(int $limit = 50): array {
  if (!migrations_tables_ready()) return [];
  $limit = max(1, min(200, $limit));
  return qall("SELECT * FROM sistema_migraciones ORDER BY id_migracion DESC LIMIT {$limit}");
}

function migrations_recent_audit(int $limit = 50): array {
  if (!migrations_tables_ready()) return [];
  $limit = max(1, min(200, $limit));
  return qall("SELECT * FROM sistema_migracion_auditoria ORDER BY id_evento DESC LIMIT {$limit}");
}

function migrations_file_info(string $path): array {
  $sql = (string)file_get_contents($path);
  $meta = migrations_metadata($sql);
  $filename = basename($path);
  migrations_assert_allowed($sql);
  migrations_assert_backup_scope($sql);
  return [
    'filename' => $filename,
    'path' => $path,
    'name' => $meta['name'] ?: preg_replace('/\.sql$/i', '', $filename),
    'description' => $meta['description'],
    'checksum' => hash('sha256', $sql),
    'statements' => migrations_split_sql($sql),
    'tables' => migrations_detect_tables($sql),
    'risks' => migrations_risk_flags($sql),
    'size' => strlen($sql),
    'sql' => $sql,
  ];
}

function migrations_list(): array {
  $files = [];
  foreach (migrations_source_dirs() as $dir) {
    if (!is_dir($dir)) continue;
    foreach (glob($dir.'/*.sql') ?: [] as $path) {
      $filename = basename($path);
      if (migrations_valid_filename($filename) && !isset($files[$filename])) {
        try {
          $files[$filename] = migrations_file_info($path);
        } catch (Throwable $e) {
          $files[$filename] = [
            'filename' => $filename, 'path' => $path, 'name' => $filename,
            'description' => '', 'checksum' => hash_file('sha256', $path) ?: '',
            'statements' => [], 'tables' => [], 'risks' => ['Archivo no válido'],
            'size' => filesize($path) ?: 0, 'sql' => '', 'validation_error' => $e->getMessage(),
          ];
        }
      }
    }
  }
  ksort($files, SORT_NATURAL);
  return array_values($files);
}

function migrations_get(string $filename): array {
  $path = migrations_find_file($filename);
  if ($path === null) throw new RuntimeException('Migración no encontrada.');
  return migrations_file_info($path);
}

function migrations_save_pending(string $name, string $sql): array {
  $name = trim($name);
  $sql = trim($sql);
  if ($name === '' || $sql === '') throw new RuntimeException('Nombre y SQL son obligatorios.');
  if (strlen($sql) > 1024 * 1024) throw new RuntimeException('La migración supera el límite de 1 MB.');
  migrations_assert_allowed($sql);
  migrations_assert_backup_scope($sql);
  $statements = migrations_split_sql($sql);
  if (empty($statements)) throw new RuntimeException('No se encontraron sentencias SQL ejecutables.');
  $slug = strtolower($name);
  $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
  $slug = trim(preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '', '_');
  if ($slug === '') $slug = 'migracion';
  migrations_ensure_private_dirs();
  $filename = date('Ymd_His').'_'.$slug.'.sql';
  $path = migrations_private_root().'/pending/'.$filename;
  $header = "-- @name: ".$name."\n-- Creada desde el Centro de migraciones.\n\n";
  if (file_put_contents($path, $header.$sql."\n", LOCK_EX) === false) {
    throw new RuntimeException('No fue posible guardar la migración.');
  }
  @chmod($path, 0600);
  $migration = migrations_file_info($path);
  migrations_audit('preparar', $migration, null, true, 'Migración preparada con '.count($statements).' sentencias.');
  return $migration;
}

function migrations_table_estimates(array $tables): array {
  $result = [];
  foreach ($tables as $table) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
    $row = qone("SELECT TABLE_TYPE,TABLE_ROWS,DATA_LENGTH,INDEX_LENGTH
      FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1", [$table]);
    $result[$table] = $row ?: null;
  }
  return $result;
}

function migrations_backup(array $migration): string {
  migrations_ensure_private_dirs();
  $tables = [];
  foreach ($migration['tables'] as $table) {
    if (db_table_exists($table)) $tables[] = $table;
  }
  if (empty($tables)) {
    $filename = date('Ymd_His').'_'.substr($migration['checksum'], 0, 12).'_'.bin2hex(random_bytes(3)).'_sin_tablas.sql';
    $path = migrations_private_root().'/backups/'.$filename;
    file_put_contents($path, "-- La migracion no modifica tablas existentes.\n", LOCK_EX);
    @chmod($path, 0600);
    return $filename;
  }

  $filename = date('Ymd_His').'_'.substr($migration['checksum'], 0, 12).'_'.bin2hex(random_bytes(3)).'.sql';
  $path = migrations_private_root().'/backups/'.$filename;
  $handle = @fopen($path, 'xb');
  if (!$handle) throw new RuntimeException('No fue posible crear el respaldo previo.');
  @chmod($path, 0600);
  $pdo = db();
  $inTransaction = false;
  try {
    fwrite($handle, "-- Respaldo previo generado por el Centro de migraciones\n");
    fwrite($handle, '-- Fecha UTC: '.gmdate('c')."\n");
    fwrite($handle, '-- Migracion: '.$migration['filename']."\n");
    fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    $inTransaction = true;

    foreach ($tables as $table) {
      $quotedTable = '`'.str_replace('`', '``', $table).'`';
      $createRow = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_ASSOC);
      $createSql = '';
      foreach ((array)$createRow as $key => $value) {
        if (stripos((string)$key, 'Create ') === 0) $createSql = (string)$value;
      }
      if ($createSql === '') throw new RuntimeException("No se pudo leer la estructura de {$table}.");
      fwrite($handle, "-- Tabla {$table}\nDROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n");

      $statement = $pdo->query("SELECT * FROM {$quotedTable}");
      $columns = [];
      $batch = [];
      while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        if (empty($columns)) $columns = array_keys($row);
        $values = [];
        foreach ($columns as $column) {
          $value = $row[$column];
          $values[] = $value === null ? 'NULL' : $pdo->quote((string)$value);
        }
        $batch[] = '('.implode(',', $values).')';
        if (count($batch) >= 100) {
          $columnSql = implode(',', array_map(fn($column) => '`'.str_replace('`', '``', $column).'`', $columns));
          fwrite($handle, "INSERT INTO {$quotedTable} ({$columnSql}) VALUES\n".implode(",\n", $batch).";\n");
          $batch = [];
        }
      }
      if (!empty($batch)) {
        $columnSql = implode(',', array_map(fn($column) => '`'.str_replace('`', '``', $column).'`', $columns));
        fwrite($handle, "INSERT INTO {$quotedTable} ({$columnSql}) VALUES\n".implode(",\n", $batch).";\n");
      }
      fwrite($handle, "\n");
    }
    $pdo->commit();
    $inTransaction = false;
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    return $filename;
  } catch (Throwable $e) {
    if ($inTransaction && $pdo->inTransaction()) $pdo->rollBack();
    fclose($handle);
    @unlink($path);
    throw $e;
  }
}

function migrations_execute_statement(PDO $pdo, string $sql): int {
  $keyword = migrations_first_keyword($sql);
  if (in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'], true)) {
    $statement = $pdo->query($sql);
    $count = 0;
    while ($statement->fetch(PDO::FETCH_NUM)) $count++;
    return $count;
  }
  $affected = $pdo->exec($sql);
  if ($affected === false) throw new RuntimeException('La base de datos rechazó una sentencia.');
  return (int)$affected;
}

function migrations_execute(array $migration): array {
  if (!migrations_tables_ready()) throw new RuntimeException('Inicializa primero el Centro de migraciones.');
  if (empty($migration['statements'])) throw new RuntimeException('La migración no contiene sentencias ejecutables.');
  migrations_assert_allowed($migration['sql']);

  $previous = qone("SELECT * FROM sistema_migraciones WHERE archivo=? AND estado='completada' ORDER BY id_migracion DESC LIMIT 1", [$migration['filename']]);
  if ($previous) {
    if (hash_equals((string)$previous['checksum'], $migration['checksum'])) {
      throw new RuntimeException('Esta migración ya fue ejecutada correctamente.');
    }
    throw new RuntimeException('El archivo cambió después de haberse ejecutado. Se bloqueó por integridad.');
  }

  $pdo = db();
  $lock = $pdo->query("SELECT GET_LOCK('urena_migration_center',0)")->fetchColumn();
  if ((int)$lock !== 1) throw new RuntimeException('Ya existe otra migración en ejecución.');

  $actor = migrations_current_actor();
  $migrationId = null;
  $backup = null;
  $executed = 0;
  @set_time_limit(0);
  try {
    $backup = migrations_backup($migration);
    q("INSERT INTO sistema_migraciones
      (archivo,nombre,checksum,estado,usuario_id,usuario,ip,respaldo,sentencias_total,sentencias_ejecutadas,fecha_inicio)
      VALUES (?,?,?,?,?,?,?,?,?,?,NOW())", [
      $migration['filename'], $migration['name'], $migration['checksum'], 'ejecutando',
      $actor['id'], $actor['name'], $actor['ip'], $backup, count($migration['statements']), 0,
    ]);
    $migrationId = (int)$pdo->lastInsertId();
    migrations_audit('respaldo', $migration, $migrationId, true, 'Respaldo generado: '.$backup);

    foreach ($migration['statements'] as $statement) {
      migrations_execute_statement($pdo, $statement);
      $executed++;
      q('UPDATE sistema_migraciones SET sentencias_ejecutadas=? WHERE id_migracion=?', [$executed, $migrationId]);
    }
    q("UPDATE sistema_migraciones SET estado='completada',fecha_fin=NOW(),sentencias_ejecutadas=? WHERE id_migracion=?", [$executed, $migrationId]);
    db_schema_snapshot_clear_file();
    db_invalidate_schema_cache();
    try { db_schema_snapshot_refresh(); } catch (Throwable $e) {}
    migrations_audit('ejecutar', $migration, $migrationId, true, "Migración completada; {$executed} sentencias.");
    return ['id' => $migrationId, 'backup' => $backup, 'executed' => $executed];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      try { $pdo->rollBack(); } catch (Throwable $rollbackError) {}
    }
    if ($migrationId) {
      q("UPDATE sistema_migraciones SET estado='fallida',fecha_fin=NOW(),sentencias_ejecutadas=?,error=? WHERE id_migracion=?", [
        $executed, mb_substr($e->getMessage(), 0, 60000), $migrationId,
      ]);
    }
    migrations_audit('ejecutar', $migration, $migrationId, false, $e->getMessage());
    throw $e;
  } finally {
    try { $pdo->query("SELECT RELEASE_LOCK('urena_migration_center')"); } catch (Throwable $e) {}
  }
}

function migrations_run_read_only(string $sql, int $limit = 200): array {
  $sql = trim($sql);
  if (!migrations_is_read_only_sql($sql)) {
    throw new RuntimeException('Diagnóstico permite únicamente una consulta SELECT, SHOW, DESCRIBE o EXPLAIN.');
  }
  $limit = max(1, min(500, $limit));
  $statement = db()->query($sql);
  $columns = [];
  for ($i = 0; $i < $statement->columnCount(); $i++) {
    $meta = $statement->getColumnMeta($i);
    $columns[] = (string)($meta['name'] ?? ('columna_'.$i));
  }
  $rows = [];
  while (count($rows) < $limit && ($row = $statement->fetch(PDO::FETCH_ASSOC))) $rows[] = $row;
  $truncated = count($rows) === $limit && $statement->fetch(PDO::FETCH_ASSOC) !== false;
  migrations_audit('consulta', null, null, true, 'Consulta de solo lectura '.hash('sha256', $sql).'; filas: '.count($rows).'.');
  return ['columns' => $columns, 'rows' => $rows, 'truncated' => $truncated];
}

function migrations_backup_path(string $filename): ?string {
  if (!preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename)) return null;
  $base = realpath(migrations_private_root().'/backups');
  $path = realpath(migrations_private_root().'/backups/'.$filename);
  if ($base === false || $path === false || !is_file($path) || !str_starts_with($path, $base.DIRECTORY_SEPARATOR)) return null;
  return $path;
}
