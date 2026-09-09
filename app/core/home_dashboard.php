<?php

require_once __DIR__.'/db.php';
require_once __DIR__.'/servicios_operativos.php';

function home_kpi_int(string $sql, array $params = []): int {
  try {
    $row = qone($sql, $params);
    if (!$row) return 0;
    return (int)array_values($row)[0];
  } catch (Throwable $e) {
    return 0;
  }
}

function home_kpi_float(string $sql, array $params = []): float {
  try {
    $row = qone($sql, $params);
    if (!$row) return 0.0;
    return (float)array_values($row)[0];
  } catch (Throwable $e) {
    return 0.0;
  }
}

function home_dashboard_kpis(): array {
  return [
    'servicios_abiertos' => home_kpi_int("SELECT COUNT(*) FROM servicios s WHERE ".so_servicio_activos_sql()),
    'servicios_cerrados' => home_kpi_int("SELECT COUNT(*) FROM servicios s WHERE ".so_servicio_cerrados_sql()),
    'pagos_servicio_hoy' => home_kpi_float("SELECT COALESCE(SUM(monto),0) FROM servicio_pagos WHERE DATE(fecha_pago)=CURDATE()"),
    'articulos_total' => home_kpi_int("SELECT COUNT(*) FROM articulos WHERE COALESCE(eliminado,0)=0"),
    'articulos_bajos' => home_kpi_int("SELECT COUNT(*) FROM articulos WHERE COALESCE(eliminado,0)=0 AND COALESCE(existencias,0) <= 5"),
    'cajas_total' => home_kpi_int("SELECT COUNT(*) FROM cajas WHERE COALESCE(eliminado,0)=0"),
    'contratos_total' => home_kpi_int("SELECT COUNT(*) FROM futuro_contratos"),
    'abonos_hoy' => home_kpi_float("SELECT COALESCE(SUM(cant_abono),0) FROM futuro_abonos WHERE DATE(fecha_registro)=CURDATE()"),
    'comisiones_pendientes' => home_kpi_int("SELECT COUNT(*) FROM futuro_comision_semanal WHERE LOWER(COALESCE(estatus,'')) IN ('pendiente','por_pagar','por pagar')"),
  ];
}

function home_dashboard_widgets(): array {
  $widgets = [
    'abonos30' => [],
    'abonos12' => [],
    'citas_hoy' => [],
    'gestiones_hoy' => [],
    'ultimos_abonos' => [],
    'ultimas_comisiones' => [],
  ];

  try {
    $widgets['abonos30'] = qall("
      SELECT DATE(fecha_registro) AS fecha, COALESCE(SUM(cant_abono),0) AS total
      FROM futuro_abonos
      WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      GROUP BY DATE(fecha_registro)
      ORDER BY fecha ASC
    ");
  } catch (Throwable $e) {
  }

  try {
    $widgets['abonos12'] = qall("
      SELECT DATE_FORMAT(fecha_registro, '%Y-%m') AS mes, COALESCE(SUM(cant_abono),0) AS total
      FROM futuro_abonos
      WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
      ORDER BY mes ASC
    ");
  } catch (Throwable $e) {
  }

  try {
    $widgets['citas_hoy'] = qall("
      SELECT g.id_contrato, g.fecha_proxima_visita,
             COALESCE(t.titular,'') AS titular,
             COALESCE(d.colonia,'') AS colonia,
             COALESCE(p.nombre,'') AS cobrador,
             CASE
               WHEN g.fecha_proxima_visita IS NULL THEN 'sin_programar'
               WHEN g.fecha_proxima_visita < CURDATE() THEN 'vencida'
               WHEN DATE(g.fecha_registro)=CURDATE() THEN 'atendida'
               ELSE 'pendiente'
             END AS estado
      FROM futuro_gestion g
      JOIN futuro_contratos c ON c.id_contrato = g.id_contrato
      LEFT JOIN vw_titular_contrato t ON t.id_contrato = g.id_contrato
      LEFT JOIN usuarios p ON p.id = g.id_usuario
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titular_dom td ON td.id_titular = tc.id_titular
      LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
      WHERE DATE(g.fecha_proxima_visita)=CURDATE()
      ORDER BY g.fecha_proxima_visita ASC
      LIMIT 50
    ");
  } catch (Throwable $e) {
  }

  try {
    $widgets['gestiones_hoy'] = qall("
      SELECT g.latitud, g.longitud,
             COALESCE(t.titular,'') AS titular,
             COALESCE(p.nombre,'') AS cobrador
      FROM futuro_gestion g
      LEFT JOIN vw_titular_contrato t ON t.id_contrato = g.id_contrato
      LEFT JOIN usuarios p ON p.id = g.id_usuario
      WHERE DATE(g.fecha_registro)=CURDATE()
        AND g.latitud IS NOT NULL AND g.longitud IS NOT NULL
        AND g.latitud!=0 AND g.longitud!=0
      LIMIT 200
    ");
  } catch (Throwable $e) {
  }

  try {
    $widgets['ultimos_abonos'] = qall("
      SELECT a.id_abono, a.id_contrato, a.cant_abono, DATE_FORMAT(a.fecha_registro,'%Y-%m-%d') AS fecha,
             (
               SELECT CONCAT(t.nombre,' ',t.apellido_p)
               FROM titulares t
               JOIN titular_contrato tc ON tc.id_titular = t.id_titular
               WHERE tc.id_contrato = a.id_contrato
               LIMIT 1
             ) AS titular
      FROM futuro_abonos a
      ORDER BY a.id_abono DESC
      LIMIT 10
    ");
  } catch (Throwable $e) {
  }

  try {
    $widgets['ultimas_comisiones'] = qall("
      SELECT id_bono_sem, id_contrato, COALESCE(cant_comision,0) AS monto,
             LOWER(COALESCE(estatus,'')) AS estatus,
             DATE_FORMAT(fecha_registro,'%Y-%m-%d') AS fecha
      FROM futuro_comision_semanal
      ORDER BY id_bono_sem DESC
      LIMIT 10
    ");
  } catch (Throwable $e) {
  }

  return $widgets;
}
