<?php

$serverDsn = getenv('TEST_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=8889;charset=utf8mb4';
$user = getenv('TEST_MYSQL_USER') ?: 'root';
$pass = getenv('TEST_MYSQL_PASS') ?: 'root';
$database = 'urena_migration_test_'.getmypid();
$admin = new PDO($serverDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

$backupPath = null;
$snapshotPath = sys_get_temp_dir().'/urena_migration_snapshot_'.getmypid().'.php';
register_shutdown_function(function () use ($admin, $database, &$backupPath, $snapshotPath) {
  try { $admin->exec("DROP DATABASE IF EXISTS `{$database}`"); } catch (Throwable $e) {}
  if ($backupPath && is_file($backupPath)) @unlink($backupPath);
  if (is_file($snapshotPath)) @unlink($snapshotPath);
});

$GLOBALS['cfg'] = [
  'dsn' => $serverDsn.';dbname='.$database,
  'user' => $user,
  'pass' => $pass,
  'schema_snapshot_file' => $snapshotPath,
  'perf_log_enabled' => false,
  'modo_libre' => false,
];

require __DIR__.'/../core/db.php';
require __DIR__.'/../core/helpers.php';
require __DIR__.'/../core/auth.php';
require __DIR__.'/../core/migrations.php';

function assert_true(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$pdo = db();
$pdo->exec("CREATE TABLE usuarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  pass VARCHAR(255) NOT NULL,
  perfil VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$hash = password_hash('prueba-segura', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO usuarios (usuario,nombre,pass,perfil) VALUES (?,?,?,?)');
$stmt->execute(['admin_test', 'Administrador prueba', $hash, 'administradora']);
$_SESSION['user'] = [
  'id' => (int)$pdo->lastInsertId(), 'usuario' => 'admin_test',
  'nombre' => 'Administrador prueba', 'perfil' => 'administradora', 'pass' => $hash,
];

$pdo->exec("CREATE TABLE futuro_mostrar_catalogo_promociones (
  id_mos_cat_com INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL, concepto VARCHAR(250) NOT NULL,
  estatus TINYINT NOT NULL DEFAULT 1, fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("CREATE TABLE futuro_cont_cat_com (
  id_cont_cat_com INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_contrato INT NOT NULL, id_mos_cat_com INT NOT NULL,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("CREATE TABLE futuro_promocion_reglas (
  id_regla INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_mos_cat_com INT NOT NULL,
  tipo_contrato VARCHAR(50) NOT NULL,
  vendedor_desde INT NOT NULL DEFAULT 0,
  vendedor_hasta INT NOT NULL DEFAULT 0,
  supervisor_desde INT NOT NULL DEFAULT 0,
  supervisor_hasta INT NOT NULL DEFAULT 0,
  empresa_desde INT NOT NULL DEFAULT 0,
  KEY idx_promocion_reglas_paquete (id_mos_cat_com,tipo_contrato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$pdo->exec("INSERT INTO futuro_mostrar_catalogo_promociones (codigo,concepto) VALUES ('ANTERIOR','Plan anterior')");
$pdo->exec("INSERT INTO futuro_promocion_reglas
  (id_mos_cat_com,tipo_contrato,vendedor_desde,vendedor_hasta,supervisor_desde,supervisor_hasta,empresa_desde)
  VALUES (1,'Paquete',1,2,3,4,5),(1,'Servicio',2,3,4,5,6)");

migrations_initialize();
assert_true(migrations_tables_ready(), 'No se inicializaron las tablas de control.');
assert_true(migrations_verify_password('prueba-segura'), 'Fallo la reautenticacion.');

$migration = migrations_get('2026_08_promociones_planes_pagos.sql');
assert_true(count($migration['statements']) === 17, 'El parser no separo las 17 sentencias esperadas.');
assert_true(!in_array('CURRENT_TIMESTAMP', $migration['tables'], true), 'Se detecto un nombre de tabla falso.');
assert_true(in_array('futuro_promocion_reglas', $migration['tables'], true), 'No se detecto la tabla principal.');

$before = (int)$pdo->query('SELECT COUNT(*) FROM futuro_promocion_reglas')->fetchColumn();
$result = migrations_execute($migration);
$backupPath = migrations_backup_path($result['backup']);
assert_true($backupPath !== null && filesize($backupPath) > 0, 'No se genero el respaldo.');
assert_true(str_contains((string)file_get_contents($backupPath), 'Plan anterior'), 'El respaldo no contiene los datos anteriores.');

$afterOld = (int)$pdo->query('SELECT COUNT(*) FROM futuro_promocion_reglas WHERE id_mos_cat_com=1')->fetchColumn();
assert_true($before === 2 && $afterOld === 2, 'La migracion elimino reglas anteriores.');
assert_true(db_column_exists('futuro_promocion_reglas', 'pago_inicial'), 'Falta la nueva columna pago_inicial.');
assert_true(!db_column_exists('futuro_promocion_reglas', 'tipo_contrato'), 'No se retiro tipo_contrato de reglas.');
assert_true((int)$pdo->query("SELECT COUNT(*) FROM futuro_mostrar_catalogo_promociones WHERE codigo IN ('NORMAL','PROMO300','PROMO500','PROMO1000')")->fetchColumn() === 4, 'No se crearon los cuatro planes.');
assert_true((int)$pdo->query("SELECT COUNT(*) FROM sistema_migraciones WHERE estado='completada'")->fetchColumn() === 1, 'No se registro la ejecucion.');

$blocked = false;
try { migrations_execute($migration); } catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(), 'ya fue ejecutada'); }
assert_true($blocked, 'No se bloqueo una ejecucion duplicada.');
assert_true(migrations_is_read_only_sql('SELECT 1'), 'Se rechazo una consulta segura.');
assert_true(!migrations_is_read_only_sql('DELETE FROM usuarios'), 'Se acepto una consulta destructiva como diagnostico.');

echo "OK: inicializacion, respaldo, migracion, auditoria y bloqueo de duplicados.\n";
