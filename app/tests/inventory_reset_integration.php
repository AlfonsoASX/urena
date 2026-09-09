<?php

$serverDsn = getenv('TEST_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=8889;charset=utf8mb4';
$user = getenv('TEST_MYSQL_USER') ?: 'root';
$pass = getenv('TEST_MYSQL_PASS') ?: 'root';
$database = 'urena_inventory_reset_test_'.getmypid();
$admin = new PDO($serverDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$snapshotPath = sys_get_temp_dir().'/urena_inventory_reset_snapshot_'.getmypid().'.php';

register_shutdown_function(function () use ($admin, $database, $snapshotPath) {
  try {
    $pdo = new PDO($GLOBALS['cfg']['dsn'], $GLOBALS['cfg']['user'], $GLOBALS['cfg']['pass']);
    foreach ($pdo->query('SELECT respaldo FROM sistema_migraciones WHERE respaldo IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $filename) {
      $path = migrations_backup_path((string)$filename);
      if ($path) @unlink($path);
    }
  } catch (Throwable $e) {}
  try { $admin->exec("DROP DATABASE IF EXISTS `{$database}`"); } catch (Throwable $e) {}
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

function reset_assert(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$pdo = db();
$pdo->exec("CREATE TABLE usuarios (id INT AUTO_INCREMENT PRIMARY KEY,usuario VARCHAR(80),nombre VARCHAR(120),pass VARCHAR(255),perfil VARCHAR(40)) ENGINE=InnoDB");
$hash = password_hash('prueba-segura', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO usuarios (usuario,nombre,pass,perfil) VALUES (?,?,?,?)');
$stmt->execute(['admin_reset', 'Admin reset', $hash, 'administradora']);
$_SESSION['user'] = ['id'=>(int)$pdo->lastInsertId(),'usuario'=>'admin_reset','nombre'=>'Admin reset','perfil'=>'administradora','pass'=>$hash];

$pdo->exec("CREATE TABLE servicios (id_servicio INT PRIMARY KEY,folio VARCHAR(30)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE proveedores (id_proveedor INT PRIMARY KEY,nombre VARCHAR(120)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE compra_articulos (id_compra INT AUTO_INCREMENT PRIMARY KEY,articulo VARCHAR(120),cantidad INT) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE almacenes (id_almacen INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40),nombre VARCHAR(120)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE articulos (id INT AUTO_INCREMENT PRIMARY KEY,articulo VARCHAR(120),existencias INT,id_almacen INT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE equipos (id_equipo VARCHAR(60) PRIMARY KEY,equipo VARCHAR(120),id_almacen INT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE cajas (codigo VARCHAR(60) PRIMARY KEY,modelo VARCHAR(120),id_almacen INT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE servicio_asignacion (id_asignacion INT AUTO_INCREMENT PRIMARY KEY,id_servicio INT,codigo_caja VARCHAR(60) NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE servicio_articulos (id INT AUTO_INCREMENT PRIMARY KEY,id_servicio INT,id_articulo INT,CONSTRAINT fk_sa_art FOREIGN KEY(id_articulo) REFERENCES articulos(id)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE servicio_equipo (id INT AUTO_INCREMENT PRIMARY KEY,id_servicio INT,id_equipo VARCHAR(60),CONSTRAINT fk_se_eq FOREIGN KEY(id_equipo) REFERENCES equipos(id_equipo)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE servicio_caja (id INT AUTO_INCREMENT PRIMARY KEY,id_servicio INT,codigo_caja VARCHAR(60),CONSTRAINT fk_sc_caja FOREIGN KEY(codigo_caja) REFERENCES cajas(codigo)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE articulos_movimientos (id_mov INT AUTO_INCREMENT PRIMARY KEY,id_articulo INT) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE equipos_movimientos (id_mov INT AUTO_INCREMENT PRIMARY KEY,id_equipo VARCHAR(60)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE cajas_movimientos (id_mov INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(60)) ENGINE=InnoDB");

$pdo->exec("INSERT INTO servicios VALUES (1,'SERV-1')");
$pdo->exec("INSERT INTO proveedores VALUES (1,'Proveedor historico')");
$pdo->exec("INSERT INTO compra_articulos (articulo,cantidad) VALUES ('Compra historica',10)");
$pdo->exec("INSERT INTO almacenes (codigo,nombre) VALUES ('ALM-1','Almacen anterior')");
$pdo->exec("INSERT INTO articulos (articulo,existencias,id_almacen) VALUES ('Articulo anterior',5,1)");
$pdo->exec("INSERT INTO equipos VALUES ('EQ-1','Equipo anterior',1)");
$pdo->exec("INSERT INTO cajas VALUES ('AT-1','Ataud anterior',1)");
$pdo->exec("INSERT INTO servicio_asignacion (id_servicio,codigo_caja) VALUES (1,'AT-1')");
$pdo->exec("INSERT INTO servicio_articulos (id_servicio,id_articulo) VALUES (1,1)");
$pdo->exec("INSERT INTO servicio_equipo (id_servicio,id_equipo) VALUES (1,'EQ-1')");
$pdo->exec("INSERT INTO servicio_caja (id_servicio,codigo_caja) VALUES (1,'AT-1')");
$pdo->exec("INSERT INTO articulos_movimientos (id_articulo) VALUES (1)");
$pdo->exec("INSERT INTO equipos_movimientos (id_equipo) VALUES ('EQ-1')");
$pdo->exec("INSERT INTO cajas_movimientos (codigo) VALUES ('AT-1')");

migrations_initialize();
$migration = migrations_get('2026_08_reset_inventario.sql');
reset_assert(count($migration['statements']) > 20, 'La migracion destructiva no fue analizada completa.');

// Una dependencia desconocida debe impedir el borrado y revertir todo el bloque DML.
$pdo->exec("CREATE TABLE inventario_bloqueo (id INT AUTO_INCREMENT PRIMARY KEY,id_articulo INT,CONSTRAINT fk_bloqueo_art FOREIGN KEY(id_articulo) REFERENCES articulos(id)) ENGINE=InnoDB");
$pdo->exec("INSERT INTO inventario_bloqueo (id_articulo) VALUES (1)");
$failed = false;
try { migrations_execute($migration); } catch (Throwable $e) { $failed = true; }
reset_assert($failed, 'La relacion desconocida no bloqueo el borrado.');
reset_assert((int)$pdo->query('SELECT COUNT(*) FROM articulos')->fetchColumn() === 1, 'No se revirtio el borrado fallido.');
reset_assert((string)$pdo->query('SELECT codigo_caja FROM servicio_asignacion LIMIT 1')->fetchColumn() === 'AT-1', 'No se revirtio la asignacion del servicio.');

$pdo->exec('DROP TABLE inventario_bloqueo');
$result = migrations_execute($migration);
$backup = migrations_backup_path($result['backup']);
reset_assert($backup !== null && str_contains((string)file_get_contents($backup), 'Articulo anterior'), 'El respaldo no contiene el inventario anterior.');
foreach (['almacenes','articulos','equipos','cajas','servicio_articulos','servicio_equipo','servicio_caja','articulos_movimientos','equipos_movimientos','cajas_movimientos'] as $table) {
  reset_assert((int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() === 0, "No se vacio {$table}.");
}
reset_assert($pdo->query('SELECT codigo_caja FROM servicio_asignacion LIMIT 1')->fetchColumn() === null, 'No se limpio la caja de servicio_asignacion.');
reset_assert((int)$pdo->query('SELECT COUNT(*) FROM servicios')->fetchColumn() === 1, 'Se borro un servicio.');
reset_assert((int)$pdo->query('SELECT COUNT(*) FROM proveedores')->fetchColumn() === 1, 'Se borro un proveedor.');
reset_assert((int)$pdo->query('SELECT COUNT(*) FROM compra_articulos')->fetchColumn() === 1, 'Se borro una compra historica.');

echo "OK: bloqueo revierte todo; reinicio conserva servicios, proveedores y compras.\n";
