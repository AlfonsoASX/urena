<?php

require_once __DIR__.'/../core/migrations.php';

function udm_assert(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

function udm_run(PDO $pdo, string $file): void {
  $migration = migrations_get($file);
  foreach ($migration['statements'] as $statement) {
    $stmt=$pdo->prepare($statement);
    $stmt->execute();
    do { if ($stmt->columnCount()>0) $stmt->fetchAll(PDO::FETCH_ASSOC); } while ($stmt->nextRowset());
    $stmt->closeCursor();
  }
}

$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '8889';
$user = getenv('TEST_DB_USER') ?: 'root';
$pass = getenv('TEST_DB_PASS') ?: 'root';
$database = 'urena_users_test_'.bin2hex(random_bytes(4));
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

try {
  $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE TABLE usuarios (id INT AUTO_INCREMENT PRIMARY KEY,usuario VARCHAR(50) NOT NULL,nombre VARCHAR(255) NOT NULL,pass VARCHAR(255) NOT NULL,perfil VARCHAR(50) NOT NULL) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO usuarios (id,usuario,nombre,pass,perfil) VALUES
    (71,'pantalla','Pantalla','x','usuario'),(72,'ma.ureña','Mario Ureña','x','administradora'),(119,'je.ureña','Jessica Ureña','x','administradora')");
  $pdo->exec("CREATE TABLE futuro_puestos (id_puesto INT PRIMARY KEY,nom_puesto VARCHAR(30)) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_puestos VALUES (1,'PROMOTOR'),(2,'COBRADOR'),(3,'GERENTE')");
  $pdo->exec("CREATE TABLE futuro_personal (
    id_personal INT PRIMARY KEY,nombre VARCHAR(50) NOT NULL,apellido_p VARCHAR(50) NOT NULL,apellido_m VARCHAR(50) NOT NULL,
    tel_casa BIGINT NOT NULL,tel_cel BIGINT NOT NULL,fecha_ing DATE NOT NULL,id_puesto INT NOT NULL,id_nivel_ventas INT NOT NULL,
    jefe_directo INT NOT NULL,recomendante INT NULL,foto VARCHAR(50),estatus VARCHAR(10) NOT NULL,fecha_reg TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    contratos INT NULL,notas VARCHAR(255) NOT NULL,id INT NULL
  ) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_personal (id_personal,nombre,apellido_p,apellido_m,tel_casa,tel_cel,fecha_ing,id_puesto,id_nivel_ventas,jefe_directo,estatus,notas,id) VALUES
    (1,'Mario','Ureña','',0,0,'2020-01-01',3,0,3,'ACTIVO','',72),
    (3,'Jessica','Ureña','',0,0,'2020-01-01',3,0,1,'ACTIVO','',119),
    (10,'Venta','Mario','',0,0,'2020-01-01',1,0,1,'ACTIVO','',NULL),
    (11,'Venta','Jessica','',0,0,'2020-01-01',1,0,3,'ACTIVO','',NULL),
    (84,'Sayra','Cobro','',0,0,'2020-01-01',2,0,1,'ACTIVO','',71),
    (86,'Josue','Cobro','',0,0,'2020-01-01',2,0,1,'ACTIVO','',71)");

  $pdo->exec("CREATE TABLE futuro_contratos (id_contrato INT PRIMARY KEY,costo_final DECIMAL(12,2),estatus VARCHAR(20)) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_contratos VALUES (100,1000,'ACTIVO'),(101,1000,'ACTIVO'),(102,1000,'ACTIVO'),(103,1000,'ACTIVO')");
  $pdo->exec("CREATE TABLE futuro_contrato_vendedor (id_cont_vend INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_contrato_vendedor (id_contrato,id_personal) VALUES (100,10),(101,11),(103,3)");
  $pdo->exec("CREATE TABLE futuro_contrato_lider (id_cont_lider INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_contrato_jefecuad (id_cont_jefecuad INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_contrato_cobrador (id_cont_cob INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_abonos (id_abono INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,cant_abono DECIMAL(12,2),fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_abonos (id_contrato,cant_abono) VALUES (100,200),(101,300)");
  $pdo->exec("CREATE TABLE futuro_abono_cobrador (id_abono_cobrador INT AUTO_INCREMENT PRIMARY KEY,id_abono INT,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_gestion (id_gestion INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_comision_semanal (id_bono_sem INT AUTO_INCREMENT PRIMARY KEY,id_comisionista INT NOT NULL,comisionista VARCHAR(20) NOT NULL,cant_com_final DECIMAL(12,2)) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_comision_semanal (id_comisionista,comisionista,cant_com_final) VALUES (0,'LIDER',48)");
  $pdo->exec("CREATE TABLE futuro_bono_4ventas (id_bono_4ventas INT AUTO_INCREMENT PRIMARY KEY,id_personal INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_bono_reco (id_bono_reco INT AUTO_INCREMENT PRIMARY KEY,id_personal INT NOT NULL,recomendado INT NOT NULL) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE futuro_contrato_promocion_condiciones (
    id_condicion INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT,id_vendedor INT NOT NULL,id_lider INT NULL,id_gerente INT NULL
  ) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_contrato_promocion_condiciones (id_contrato,id_vendedor,id_lider,id_gerente) VALUES (100,10,1,3)");
  $pdo->exec("CREATE TABLE futuro_abono_distribucion (
    id_distribucion BIGINT AUTO_INCREMENT PRIMARY KEY,id_abono INT,id_contrato INT,tipo VARCHAR(30),etapa VARCHAR(20),id_personal INT NULL,monto DECIMAL(12,2)
  ) ENGINE=InnoDB");
  $pdo->exec("INSERT INTO futuro_abono_distribucion (id_abono,id_contrato,tipo,etapa,id_personal,monto) VALUES (1,100,'vendedor','posterior',10,100)");
  $pdo->exec("CREATE TABLE vw_titular_contrato (id_contrato INT PRIMARY KEY,titular VARCHAR(200)) ENGINE=InnoDB");

  udm_run($pdo,'2026_08_usuarios_directorio_etapa1.sql');
  udm_assert((int)$pdo->query('SELECT COUNT(*) FROM usuarios_personal_legacy')->fetchColumn()===6,'No se concilio todo el personal.');
  udm_assert((int)$pdo->query("SELECT COUNT(*) FROM usuarios_personal_legacy WHERE id_usuario=71")->fetchColumn()===0,'El enlace duplicado a pantalla no debio conservarse.');
  udm_assert((int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE acceso_sistema=0")->fetchColumn()===4,'Los faltantes deben ser usuarios sin acceso.');

  $mario=(int)$pdo->query("SELECT id FROM usuarios WHERE usuario='ma.ureña'")->fetchColumn();
  $jessica=(int)$pdo->query("SELECT id FROM usuarios WHERE usuario='je.ureña'")->fetchColumn();
  $ventaJessica=(int)$pdo->query("SELECT id_usuario FROM usuarios_personal_legacy WHERE id_personal=11")->fetchColumn();
  $usuariosAntes=(int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
  udm_run($pdo,'2026_08_usuarios_directorio_etapa1.sql');
  udm_assert((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===$usuariosAntes,'Repetir etapa 1 duplico usuarios.');

  $cobrador84=(int)$pdo->query("SELECT id_usuario FROM usuarios_personal_legacy WHERE id_personal=84")->fetchColumn();
  $cobrador86=(int)$pdo->query("SELECT id_usuario FROM usuarios_personal_legacy WHERE id_personal=86")->fetchColumn();
  $pdo->exec("UPDATE usuarios SET jefe_directo_id={$cobrador86} WHERE id={$cobrador84}");
  $pdo->exec("UPDATE usuarios SET jefe_directo_id={$cobrador84} WHERE id={$cobrador86}");
  $cycleBlocked=false;
  try { udm_run($pdo,'2026_08_usuarios_directorio_etapa2.sql'); }
  catch (PDOException $e) { $cycleBlocked=str_contains($e->getMessage(),'ciclo en la jerarquia'); }
  udm_assert($cycleBlocked,'La etapa 2 debio bloquear un ciclo fuera de la cadena inmediata del vendedor.');
  $pdo->exec("UPDATE usuarios SET jefe_directo_id=NULL WHERE id IN ({$cobrador84},{$cobrador86})");

  udm_run($pdo,'2026_08_usuarios_directorio_etapa2.sql');
  udm_assert((int)$pdo->query("SELECT jefe_directo_id FROM usuarios WHERE id={$ventaJessica}")->fetchColumn()===$jessica,'El vendedor sin lider debio asignarse a je.ureña.');
  udm_assert((int)$pdo->query("SELECT COUNT(*) FROM futuro_contratos WHERE id_usuario_vendedor IS NOT NULL AND id_usuario_lider IS NOT NULL AND id_usuario_gerente IS NOT NULL")->fetchColumn()===4,'No se congelo la jerarquia en contratos.');
  $contratoRespaldo=$pdo->query('SELECT id_usuario_lider,id_usuario_gerente FROM futuro_contratos WHERE id_contrato=101')->fetch(PDO::FETCH_ASSOC);
  udm_assert((int)$contratoRespaldo['id_usuario_lider']===$jessica && (int)$contratoRespaldo['id_usuario_gerente']===$jessica,'je.ureña debe cubrir lider y gerente en el contrato sin jerarquia.');
  $sinVendedor=$pdo->query('SELECT id_usuario_vendedor,id_usuario_lider,id_usuario_gerente FROM futuro_contratos WHERE id_contrato=102')->fetch(PDO::FETCH_ASSOC);
  udm_assert(count(array_unique(array_map('intval',$sinVendedor)))===1 && (int)$sinVendedor['id_usuario_vendedor']===$jessica,'El contrato historico sin vendedor debe usar a je.ureña como respaldo completo.');
  $vendedorConOtroPuesto=$pdo->query('SELECT id_usuario_vendedor,id_usuario_lider,id_usuario_gerente FROM futuro_contratos WHERE id_contrato=103')->fetch(PDO::FETCH_ASSOC);
  udm_assert((int)$vendedorConOtroPuesto['id_usuario_vendedor']===$jessica && (int)$vendedorConOtroPuesto['id_usuario_lider']===$jessica && (int)$vendedorConOtroPuesto['id_usuario_gerente']===$jessica,'La identidad historica del vendedor debe conservarse aunque hoy tenga otro puesto.');
  udm_assert((int)$pdo->query("SELECT COUNT(*) FROM futuro_contrato_promocion_condiciones WHERE id_usuario_vendedor IS NOT NULL AND id_usuario_lider IS NOT NULL AND id_usuario_gerente IS NOT NULL")->fetchColumn()===1,'No se conservaron los beneficiarios de condiciones historicas.');
  udm_assert((int)$pdo->query("SELECT COUNT(*) FROM futuro_abono_distribucion WHERE id_usuario IS NOT NULL")->fetchColumn()===1,'No se migro el beneficiario de distribuciones historicas.');
  udm_assert((int)$pdo->query("SELECT id_usuario_comisionista FROM futuro_comision_semanal WHERE id_comisionista=0")->fetchColumn()===$jessica,'La comision historica de lider sin identidad debe asignarse a je.ureña.');
  udm_assert((float)$pdo->query('SELECT SUM(cant_abono) FROM futuro_abonos')->fetchColumn()===500.0,'La migracion altero los importes.');
  udm_run($pdo,'2026_08_usuarios_directorio_etapa2.sql');
  udm_assert((int)$pdo->query('SELECT COUNT(*) FROM futuro_contratos')->fetchColumn()===4,'Repetir etapa 2 altero contratos.');

  echo "OK: conciliacion, bloqueo, backfill e idempotencia de usuarios.\n";
} finally {
  $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
