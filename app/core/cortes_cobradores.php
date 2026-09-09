<?php

function cortes_cobradores_timezone(): DateTimeZone {
  static $timezone = null;
  if ($timezone === null) $timezone = new DateTimeZone('America/Mexico_City');
  return $timezone;
}

function cortes_cobradores_dias(): array {
  return [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miercoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sabado',
    7 => 'Domingo',
  ];
}

function cortes_cobradores_tabla_disponible(): bool {
  return db_table_exists('futuro_corte_configuracion');
}

function cortes_cobradores_configuracion(): ?array {
  if (!cortes_cobradores_tabla_disponible()) return null;
  $row = qone("SELECT dia_semana,hora_corte,updated_at FROM futuro_corte_configuracion WHERE id_configuracion=1 LIMIT 1");
  if (!$row) return null;

  $dia = (int)($row['dia_semana'] ?? 0);
  $hora = substr((string)($row['hora_corte'] ?? ''), 0, 5);
  if ($dia < 1 || $dia > 7 || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) return null;

  return [
    'dia_semana' => $dia,
    'hora_corte' => $hora,
    'updated_at' => $row['updated_at'] ?? null,
  ];
}

function cortes_cobradores_guardar_configuracion(int $dia, string $hora, ?int $usuarioId): void {
  if (!cortes_cobradores_tabla_disponible()) {
    throw new RuntimeException('Falta ejecutar la migracion de cortes de cobradores.');
  }
  $hora = trim($hora);
  if ($dia < 1 || $dia > 7) throw new RuntimeException('Selecciona un dia valido.');
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new RuntimeException('Selecciona una hora valida.');

  q("INSERT INTO futuro_corte_configuracion
      (id_configuracion,dia_semana,hora_corte,actualizado_por)
      VALUES (1,?,?,?)
      ON DUPLICATE KEY UPDATE dia_semana=VALUES(dia_semana),hora_corte=VALUES(hora_corte),
        actualizado_por=VALUES(actualizado_por),updated_at=CURRENT_TIMESTAMP", [
    $dia, $hora.':00', $usuarioId,
  ]);
}

function cortes_cobradores_periodo_actual(array $config, ?DateTimeImmutable $ahora = null): array {
  $timezone = cortes_cobradores_timezone();
  $ahora = ($ahora ?: new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
  $dia = (int)$config['dia_semana'];
  [$hora, $minuto] = array_map('intval', explode(':', (string)$config['hora_corte']));

  $diasAtras = ((int)$ahora->format('N') - $dia + 7) % 7;
  $inicio = $ahora->modify("-{$diasAtras} days")->setTime($hora, $minuto, 0);
  if ($ahora < $inicio) $inicio = $inicio->modify('-7 days');
  $fin = $inicio->modify('+7 days');

  return cortes_cobradores_periodo_desde_limites($inicio, $fin, true, 0);
}

function cortes_cobradores_periodo_desde_limites(
  DateTimeImmutable $inicio,
  DateTimeImmutable $fin,
  bool $actual,
  int $indice
): array {
  return [
    'clave' => $inicio->format('Ymd_Hi'),
    'indice' => $indice,
    'actual' => $actual,
    'inicio' => $inicio,
    'fin' => $fin,
    'inicio_sql' => $inicio->format('Y-m-d H:i:s'),
    'fin_sql' => $fin->format('Y-m-d H:i:s'),
  ];
}

function cortes_cobradores_periodos(array $config, int $cantidad = 10, ?DateTimeImmutable $ahora = null): array {
  $cantidad = max(1, min(52, $cantidad));
  $actual = cortes_cobradores_periodo_actual($config, $ahora);
  $periodos = [];
  for ($i = 0; $i < $cantidad; $i++) {
    $inicio = $actual['inicio']->modify("-{$i} weeks");
    $fin = $inicio->modify('+7 days');
    $periodos[] = cortes_cobradores_periodo_desde_limites($inicio, $fin, $i === 0, $i);
  }
  return $periodos;
}

function cortes_cobradores_seleccionar_periodo(array $periodos, string $clave): array {
  foreach ($periodos as $periodo) {
    if (hash_equals((string)$periodo['clave'], $clave)) return $periodo;
  }
  return $periodos[0];
}

function cortes_cobradores_formatear_limite(DateTimeImmutable $fecha): string {
  $dias = ['dom','lun','mar','mie','jue','vie','sab'];
  return $dias[(int)$fecha->format('w')].' '.$fecha->format('d/m/Y H:i');
}

function cortes_cobradores_movimientos(array $periodo, int $idUsuario = 0): array {
  $wherePersonal = '';
  $params = [$periodo['inicio_sql'], $periodo['fin_sql']];
  if ($idUsuario > 0) {
    $wherePersonal = ' AND ac.id_usuario=?';
    $params[] = $idUsuario;
  }

  return qall("
    SELECT a.id_abono,a.id_contrato,a.fecha_registro,a.cant_abono,
           ac.id_usuario AS id_cobrador,u.nombre AS cobrador,
           (
             SELECT TRIM(CONCAT(COALESCE(t.nombre,''),' ',COALESCE(t.apellido_p,''),' ',COALESCE(t.apellido_m,'')))
             FROM titular_contrato tc
             JOIN titulares t ON t.id_titular=tc.id_titular
             WHERE tc.id_contrato=a.id_contrato
             ORDER BY tc.id_titular_contrato DESC
             LIMIT 1
           ) AS titular
    FROM futuro_abonos a
    JOIN futuro_abono_cobrador ac ON ac.id_abono_cobrador=(
      SELECT ac2.id_abono_cobrador
      FROM futuro_abono_cobrador ac2
      WHERE ac2.id_abono=a.id_abono
      ORDER BY ac2.id_abono_cobrador DESC
      LIMIT 1
    )
    JOIN usuarios u ON u.id=ac.id_usuario
    WHERE a.fecha_registro>=? AND a.fecha_registro<? {$wherePersonal}
    ORDER BY a.fecha_registro DESC,a.id_abono DESC
    LIMIT 20000
  ", $params);
}

function cortes_cobradores_resumen(array $periodo): array {
  $resumen = [];
  foreach (cortes_cobradores_movimientos($periodo) as $row) {
    $id = (int)($row['id_cobrador'] ?? 0);
    if ($id <= 0) continue;
    if (!isset($resumen[$id])) {
      $resumen[$id] = [
        'id_usuario' => $id,
        'nombre' => trim((string)($row['cobrador'] ?? '')),
        'total' => 0.0,
        'pagos' => 0,
        'contratos' => [],
      ];
    }
    $resumen[$id]['total'] += (float)$row['cant_abono'];
    $resumen[$id]['pagos']++;
    $resumen[$id]['contratos'][(int)$row['id_contrato']] = true;
  }
  $rows = array_values($resumen);
  usort($rows, fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']));
  return $rows;
}

function cortes_cobradores_totales_periodos(array $periodos): array {
  $totales = [];
  foreach ($periodos as $periodo) {
    $row = qone("
      SELECT COUNT(*) AS pagos,COUNT(DISTINCT ac.id_usuario) AS cobradores,
             COALESCE(SUM(a.cant_abono),0) AS total
      FROM futuro_abonos a
      JOIN futuro_abono_cobrador ac ON ac.id_abono_cobrador=(
        SELECT ac2.id_abono_cobrador FROM futuro_abono_cobrador ac2
        WHERE ac2.id_abono=a.id_abono ORDER BY ac2.id_abono_cobrador DESC LIMIT 1
      )
      WHERE a.fecha_registro>=? AND a.fecha_registro<?
    ", [$periodo['inicio_sql'], $periodo['fin_sql']]);
    $totales[$periodo['clave']] = [
      'pagos' => (int)($row['pagos'] ?? 0),
      'cobradores' => (int)($row['cobradores'] ?? 0),
      'total' => (float)($row['total'] ?? 0),
    ];
  }
  return $totales;
}

function cortes_cobradores_total_personal(array $periodo, int $idUsuario): array {
  if ($idUsuario <= 0) return ['pagos'=>0,'contratos'=>0,'total'=>0.0];
  $row = qone("
    SELECT COUNT(*) AS pagos,COUNT(DISTINCT a.id_contrato) AS contratos,
           COALESCE(SUM(a.cant_abono),0) AS total
    FROM futuro_abonos a
    JOIN futuro_abono_cobrador ac ON ac.id_abono_cobrador=(
      SELECT ac2.id_abono_cobrador FROM futuro_abono_cobrador ac2
      WHERE ac2.id_abono=a.id_abono ORDER BY ac2.id_abono_cobrador DESC LIMIT 1
    )
    WHERE ac.id_usuario=? AND a.fecha_registro>=? AND a.fecha_registro<?
  ", [$idUsuario, $periodo['inicio_sql'], $periodo['fin_sql']]);
  return [
    'pagos' => (int)($row['pagos'] ?? 0),
    'contratos' => (int)($row['contratos'] ?? 0),
    'total' => (float)($row['total'] ?? 0),
  ];
}
