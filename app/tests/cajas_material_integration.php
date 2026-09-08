<?php

$serverDsn = getenv('TEST_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=8889;charset=utf8mb4';
$user = getenv('TEST_MYSQL_USER') ?: 'root';
$pass = getenv('TEST_MYSQL_PASS') ?: 'root';
$database = 'urena_cajas_material_test_'.getmypid();
$admin = new PDO($serverDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

try {
  $GLOBALS['cfg'] = [
    'dsn' => $serverDsn.';dbname='.$database,
    'user' => $user,
    'pass' => $pass,
    'perf_log_enabled' => false,
  ];
  require_once __DIR__.'/../core/db.php';
  require_once __DIR__.'/../core/migrations.php';
  require_once __DIR__.'/../core/inventario_schema.php';

  $pdo = db();
  $pdo->exec("CREATE TABLE cajas (codigo VARCHAR(60) PRIMARY KEY,modelo VARCHAR(120) NOT NULL) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO cajas (codigo,modelo) VALUES ('LEGACY-1','Modelo anterior')");

  $migration = migrations_get('2026_09_cajas_material.sql');
  $run = static function () use ($pdo, $migration): void {
    foreach ($migration['statements'] as $statement) {
      $stmt = $pdo->prepare($statement);
      $stmt->execute();
      do {
        if ($stmt->columnCount() > 0) $stmt->fetchAll(PDO::FETCH_ASSOC);
      } while ($stmt->nextRowset());
      $stmt->closeCursor();
    }
  };

  $run();
  $columns = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cajas' AND COLUMN_NAME='material'")->fetchColumn();
  if ($columns !== 1) throw new RuntimeException('No se agrego el material a cajas.');
  if ($pdo->query("SELECT material FROM cajas WHERE codigo='LEGACY-1'")->fetchColumn() !== null) {
    throw new RuntimeException('Se clasifico automaticamente un ataud anterior.');
  }
  $run();
  $indexes = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cajas' AND INDEX_NAME='idx_cajas_material'")->fetchColumn();
  if ($indexes !== 1) throw new RuntimeException('La migracion repetida duplico el indice de material.');

  foreach (['metalico','madera','tapizado'] as $material) {
    if (inventario_caja_material_normalizar($material) !== $material) throw new RuntimeException('Material valido rechazado: '.$material);
  }
  if (inventario_caja_material_normalizar('METÁLICO') !== 'metalico') throw new RuntimeException('No se normalizo metalico con acento.');
  if (inventario_caja_material_normalizar('plastico') !== null) throw new RuntimeException('Se acepto un material no permitido.');

  echo "OK: material de ataudes validado y migracion idempotente.\n";
} finally {
  $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
