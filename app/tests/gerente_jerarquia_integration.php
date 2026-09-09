<?php

require_once __DIR__.'/../core/migrations.php';

function hierarchy_assert($condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '8889';
$user = getenv('TEST_DB_USER') ?: 'root';
$pass = getenv('TEST_DB_PASS') ?: 'root';
$database = 'urena_hierarchy_test_'.bin2hex(random_bytes(4));
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

try {
  $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE TABLE usuarios (id INT PRIMARY KEY,usuario VARCHAR(80) NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_personal (id_personal INT PRIMARY KEY,id INT,nombre VARCHAR(80),apellido_p VARCHAR(80),apellido_m VARCHAR(80),jefe_directo INT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_contratos (id_contrato INT PRIMARY KEY,estatus VARCHAR(20)) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_contrato_vendedor (id_cont_vend INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_personal INT) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_contrato_promocion_condiciones (id_condicion INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT) ENGINE=InnoDB");

  $pdo->exec("INSERT INTO usuarios VALUES (119,'je.ureña')");
  $pdo->exec("INSERT INTO futuro_personal VALUES
    (1,1,'Mario','Ureña','',0),
    (3,119,'Jessica','Ureña','',1),
    (10,10,'Vendedor','Uno','',1),
    (11,11,'Vendedor','Dos','',3),
    (12,12,'Sin','Contrato','',0)");
  $pdo->exec("INSERT INTO futuro_contratos VALUES (100,'ACTIVO'),(101,'ACTIVO'),(102,'PAGADO')");
  $pdo->exec("INSERT INTO futuro_contrato_vendedor (id_contrato,id_personal) VALUES (100,10),(101,11),(102,12)");

  $migration = migrations_get('2026_08_asignar_gerente_jerarquia.sql');
  foreach ($migration['statements'] as $statement) migrations_execute_statement($pdo, $statement);

  $bossMario = (int)$pdo->query("SELECT jefe_directo FROM futuro_personal WHERE id_personal=1")->fetchColumn();
  $bossJessica = (int)$pdo->query("SELECT jefe_directo FROM futuro_personal WHERE id_personal=3")->fetchColumn();
  $bossUnrelated = (int)$pdo->query("SELECT jefe_directo FROM futuro_personal WHERE id_personal=12")->fetchColumn();
  hierarchy_assert($bossMario === 3, 'El lider sin gerente debe apuntar a Jessica.');
  hierarchy_assert($bossJessica === 1, 'Jessica no debe convertirse en su propia jefa.');
  hierarchy_assert($bossUnrelated === 0, 'No deben cambiar personas fuera de contratos activos pendientes.');

  echo "OK: gerente asignado sin ciclos ni cambios fuera del alcance.\n";
} finally {
  $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
