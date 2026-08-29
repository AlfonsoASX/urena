<?php

date_default_timezone_set('America/Mexico_City');
require_once __DIR__.'/../core/cortes_cobradores.php';

function cut_assert($condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$config = ['dia_semana'=>5, 'hora_corte'=>'18:00'];
$tz = new DateTimeZone('America/Mexico_City');
$beforeBoundary = cortes_cobradores_periodo_actual($config, new DateTimeImmutable('2026-08-28 17:59:59', $tz));
cut_assert($beforeBoundary['inicio_sql'] === '2026-08-21 18:00:00', 'El periodo anterior al limite es incorrecto.');
cut_assert($beforeBoundary['fin_sql'] === '2026-08-28 18:00:00', 'El cierre anterior es incorrecto.');

$atBoundary = cortes_cobradores_periodo_actual($config, new DateTimeImmutable('2026-08-28 18:00:00', $tz));
cut_assert($atBoundary['inicio_sql'] === '2026-08-28 18:00:00', 'El limite debe pertenecer al nuevo corte.');
$periods = cortes_cobradores_periodos($config, 10, new DateTimeImmutable('2026-08-28 20:00:00', $tz));
cut_assert(count($periods) === 10, 'Deben generarse diez cortes.');
for ($i = 1; $i < count($periods); $i++) {
  cut_assert($periods[$i]['fin_sql'] === $periods[$i - 1]['inicio_sql'], 'Los cortes deben ser contiguos.');
}

$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '8889';
$user = getenv('TEST_DB_USER') ?: 'root';
$pass = getenv('TEST_DB_PASS') ?: 'root';
$database = 'urena_cut_test_'.bin2hex(random_bytes(4));
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

try {
  $GLOBALS['cfg'] = [
    'dsn' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    'user' => $user,
    'pass' => $pass,
    'db_connect_timeout' => 3,
    'schema_snapshot_file' => sys_get_temp_dir().'/urena_cut_snapshot_'.bin2hex(random_bytes(4)).'.php',
  ];
  require_once __DIR__.'/../core/db.php';

  q("CREATE TABLE futuro_personal (id_personal INT PRIMARY KEY,nombre VARCHAR(80),apellido_p VARCHAR(80),apellido_m VARCHAR(80)) ENGINE=InnoDB");
  q("CREATE TABLE titulares (id_titular INT PRIMARY KEY,nombre VARCHAR(80),apellido_p VARCHAR(80),apellido_m VARCHAR(80)) ENGINE=InnoDB");
  q("CREATE TABLE titular_contrato (id_titular_contrato INT AUTO_INCREMENT PRIMARY KEY,id_titular INT,id_contrato INT) ENGINE=InnoDB");
  q("CREATE TABLE futuro_abonos (id_abono INT AUTO_INCREMENT PRIMARY KEY,id_contrato INT NOT NULL,saldo DECIMAL(12,2) NOT NULL DEFAULT 0,cant_abono DECIMAL(12,2) NOT NULL,fecha_registro TIMESTAMP NOT NULL) ENGINE=InnoDB");
  q("CREATE TABLE futuro_abono_cobrador (id_abono_cobrador INT AUTO_INCREMENT PRIMARY KEY,id_abono INT NOT NULL,id_personal INT NOT NULL,fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
  q("INSERT INTO futuro_personal VALUES (1,'Ana','Corte',''),(2,'Luis','Ruta','')");
  q("INSERT INTO titulares VALUES (1,'Cliente','Prueba','')");
  q("INSERT INTO titular_contrato (id_titular,id_contrato) VALUES (1,10),(1,20),(1,30),(1,40),(1,50)");

  q("INSERT INTO futuro_abonos (id_contrato,cant_abono,fecha_registro) VALUES
     (10,100,'2026-08-28 18:00:00'),
     (20,50,'2026-08-29 09:00:00'),
     (30,999,'2026-09-04 18:00:00'),
     (40,1,'2026-08-28 17:59:59'),
     (50,25,'2026-08-30 10:00:00')");
  q("INSERT INTO futuro_abono_cobrador (id_abono,id_personal) VALUES (1,1),(2,1),(2,2),(3,1),(4,1),(5,2)");

  $selected = cortes_cobradores_periodo_actual($config, new DateTimeImmutable('2026-08-30 12:00:00', $tz));
  $summary = cortes_cobradores_resumen($selected);
  $byId = [];
  foreach ($summary as $row) $byId[(int)$row['id_personal']] = $row;
  cut_assert(count($summary) === 2, 'Deben aparecer dos cobradores.');
  cut_assert(abs((float)$byId[1]['total'] - 100.0) < 0.001, 'El total del primer cobrador es incorrecto.');
  cut_assert(abs((float)$byId[2]['total'] - 75.0) < 0.001, 'La ultima relacion debe definir al cobrador.');

  $totals = cortes_cobradores_total_personal($selected, 2);
  cut_assert($totals['pagos'] === 2 && abs($totals['total'] - 75.0) < 0.001, 'El acumulado de portada es incorrecto.');
  cut_assert(count(cortes_cobradores_movimientos($selected)) === 3, 'Los limites del corte deben ser inicio inclusivo y fin exclusivo.');

  $admin->exec("SET time_zone='+00:00'");
  $admin->exec("INSERT INTO `{$database}`.futuro_abonos (id_contrato,cant_abono,fecha_registro) VALUES (60,10,'2024-05-10 00:00:00')");
  $admin->exec("UPDATE `{$database}`.futuro_abonos SET fecha_registro=DATE_ADD(fecha_registro,INTERVAL 6 HOUR) WHERE id_contrato=60");
  $admin->exec("SET time_zone='-06:00'");
  $preserved = $admin->query("SELECT fecha_registro FROM `{$database}`.futuro_abonos WHERE id_contrato=60")->fetchColumn();
  cut_assert($preserved === '2024-05-10 00:00:00', 'La correccion debe conservar la fecha local del abono sin hora.');

  echo "OK: zona horaria, limites, historico, ultima asignacion y acumulados.\n";
} finally {
  $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}
