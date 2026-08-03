<?php
// Helpers compartidos para promociones y calculo de pagos de futuros.

function futuros_promociones_ensure_schema(bool $force = false): void {
  static $ready = false;
  if ($ready && !$force) return;

  $hadCatalogo = db_table_exists('futuro_mostrar_catalogo_promociones');
  $hadRelacion = db_table_exists('futuro_cont_cat_com');
  $hadReglas = db_table_exists('futuro_promocion_reglas');

  q("
    CREATE TABLE IF NOT EXISTS futuro_mostrar_catalogo_promociones (
      id_mos_cat_com INT(10) NOT NULL AUTO_INCREMENT,
      codigo VARCHAR(20) NOT NULL,
      concepto VARCHAR(250) NOT NULL,
      estatus INT(1) NOT NULL DEFAULT 1,
      fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id_mos_cat_com)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  ");

  q("
    CREATE TABLE IF NOT EXISTS futuro_cont_cat_com (
      id_cont_cat_com INT(10) NOT NULL AUTO_INCREMENT,
      id_contrato INT(10) NOT NULL,
      id_mos_cat_com INT(10) NOT NULL,
      fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id_cont_cat_com),
      KEY idx_futuro_cont_cat_com_contrato (id_contrato),
      KEY idx_futuro_cont_cat_com_promocion (id_mos_cat_com)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  ");

  q("
    CREATE TABLE IF NOT EXISTS futuro_promocion_reglas (
      id_regla INT(11) NOT NULL AUTO_INCREMENT,
      id_mos_cat_com INT(10) NOT NULL,
      tipo_contrato VARCHAR(100) COLLATE utf8mb4_general_ci NOT NULL,
      vendedor_desde INT(11) DEFAULT NULL,
      vendedor_hasta INT(11) DEFAULT NULL,
      supervisor_desde INT(11) DEFAULT NULL,
      supervisor_hasta INT(11) DEFAULT NULL,
      empresa_desde INT(11) NOT NULL,
      activo TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id_regla),
      UNIQUE KEY uq_promocion_paquete (id_mos_cat_com, tipo_contrato),
      KEY idx_promocion_reglas_promocion (id_mos_cat_com),
      KEY idx_promocion_reglas_paquete (tipo_contrato)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  ");
  if (!$hadCatalogo || !$hadRelacion || !$hadReglas) {
    db_invalidate_schema_cache('futuro_mostrar_catalogo_promociones');
    db_invalidate_schema_cache('futuro_cont_cat_com');
    db_invalidate_schema_cache('futuro_promocion_reglas');
    db_schema_snapshot_clear_file();
  }

  $adds = [
    'vendedor_desde' => "INT(11) DEFAULT NULL",
    'vendedor_hasta' => "INT(11) DEFAULT NULL",
    'supervisor_desde' => "INT(11) DEFAULT NULL",
    'supervisor_hasta' => "INT(11) DEFAULT NULL",
    'empresa_desde' => "INT(11) NOT NULL DEFAULT 1",
    'activo' => "TINYINT(1) NOT NULL DEFAULT 1",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ];
  foreach ($adds as $column => $ddl) {
    db_add_column_if_missing('futuro_promocion_reglas', $column, $ddl);
  }
  db_add_index_if_missing('futuro_promocion_reglas', 'uq_promocion_paquete', "UNIQUE KEY uq_promocion_paquete (id_mos_cat_com, tipo_contrato)");
  db_add_index_if_missing('futuro_promocion_reglas', 'idx_promocion_reglas_promocion', "KEY idx_promocion_reglas_promocion (id_mos_cat_com)");
  db_add_index_if_missing('futuro_promocion_reglas', 'idx_promocion_reglas_paquete', "KEY idx_promocion_reglas_paquete (tipo_contrato)");

  $ready = true;
}

function futuros_promociones_label(?string $codigo, ?string $concepto): string {
  $codigo = trim((string)$codigo);
  $concepto = trim((string)$concepto);
  if ($codigo === '' && $concepto === '') return 'Sin promocion';
  if ($codigo !== '' && $concepto !== '' && $codigo !== $concepto) return $codigo.' - '.$concepto;
  return $codigo !== '' ? $codigo : $concepto;
}

function futuros_promociones_paquetes(): array {
  $rows = qall("
    SELECT DISTINCT TRIM(tipo_contrato) AS tipo_contrato
    FROM futuro_contratos
    WHERE tipo_contrato IS NOT NULL AND TRIM(tipo_contrato) <> ''
    ORDER BY tipo_contrato ASC
  ");
  $paquetes = [];
  foreach ($rows as $row) {
    $tipo = trim((string)($row['tipo_contrato'] ?? ''));
    if ($tipo !== '') $paquetes[] = $tipo;
  }
  if (empty($paquetes)) {
    $paquetes = ['SERVICIO COMPLETO', 'CREMACION DIRECTA', 'CREMACION'];
  }
  return array_values(array_unique($paquetes));
}

function futuros_promociones_catalogo(bool $soloActivas = false): array {
  futuros_promociones_ensure_schema();
  $where = $soloActivas ? "WHERE estatus = 1" : "";
  return qall("
    SELECT id_mos_cat_com, codigo, concepto, estatus, fecha_registro
    FROM futuro_mostrar_catalogo_promociones
    $where
    ORDER BY estatus DESC, codigo ASC, concepto ASC
  ");
}

function futuros_promociones_opciones(bool $soloActivas = true): array {
  $opts = [];
  foreach (futuros_promociones_catalogo($soloActivas) as $p) {
    $opts[(int)$p['id_mos_cat_com']] = futuros_promociones_label($p['codigo'] ?? '', $p['concepto'] ?? '');
  }
  return $opts;
}

function futuros_promociones_reglas_por_promocion(int $idPromocion): array {
  futuros_promociones_ensure_schema();
  $rows = qall("
    SELECT *
    FROM futuro_promocion_reglas
    WHERE id_mos_cat_com = ?
    ORDER BY tipo_contrato ASC
  ", [$idPromocion]);
  $out = [];
  foreach ($rows as $row) {
    $out[(string)$row['tipo_contrato']] = $row;
  }
  return $out;
}

function futuros_promociones_int_or_null($value): ?int {
  if ($value === null) return null;
  $value = trim((string)$value);
  if ($value === '') return null;
  return (int)$value;
}

function futuros_promociones_validar_regla(array $regla): array {
  $tipo = trim((string)($regla['tipo_contrato'] ?? ''));
  $vDesde = futuros_promociones_int_or_null($regla['vendedor_desde'] ?? null);
  $vHasta = futuros_promociones_int_or_null($regla['vendedor_hasta'] ?? null);
  $sDesde = futuros_promociones_int_or_null($regla['supervisor_desde'] ?? null);
  $sHasta = futuros_promociones_int_or_null($regla['supervisor_hasta'] ?? null);
  $eDesde = futuros_promociones_int_or_null($regla['empresa_desde'] ?? null);

  $errores = [];
  if ($tipo === '') $errores[] = 'Paquete vacio.';
  if ($eDesde === null || $eDesde < 1) $errores[] = "{$tipo}: empresa_desde es obligatorio.";

  foreach ([['Vendedor', $vDesde, $vHasta], ['Supervisor', $sDesde, $sHasta]] as [$label, $desde, $hasta]) {
    if (($desde === null && $hasta !== null) || ($desde !== null && $hasta === null)) {
      $errores[] = "{$tipo}: completa desde y hasta para {$label}.";
    }
    if ($desde !== null && ($desde < 1 || $hasta < 1 || $desde > $hasta)) {
      $errores[] = "{$tipo}: rango de {$label} invalido.";
    }
  }

  if (empty($errores) && $eDesde !== null) {
    $esperado = 1;
    if ($vDesde !== null) {
      if ($vDesde !== $esperado) $errores[] = "{$tipo}: vendedor debe iniciar en pago {$esperado}.";
      $esperado = $vHasta + 1;
    }
    if ($sDesde !== null) {
      if ($sDesde !== $esperado) $errores[] = "{$tipo}: supervisor debe iniciar en pago {$esperado}.";
      $esperado = $sHasta + 1;
    }
    if ($eDesde !== $esperado) {
      $errores[] = "{$tipo}: empresa debe iniciar en pago {$esperado}.";
    }
  }

  return $errores;
}

function futuros_promociones_guardar_reglas(int $idPromocion, array $reglas): array {
  futuros_promociones_ensure_schema();
  $errores = [];
  foreach ($reglas as $regla) {
    $errores = array_merge($errores, futuros_promociones_validar_regla($regla));
  }
  if (!empty($errores)) return $errores;

  foreach ($reglas as $regla) {
    q("
      INSERT INTO futuro_promocion_reglas
        (id_mos_cat_com, tipo_contrato, vendedor_desde, vendedor_hasta, supervisor_desde, supervisor_hasta, empresa_desde, activo)
      VALUES (?, ?, ?, ?, ?, ?, ?, 1)
      ON DUPLICATE KEY UPDATE
        vendedor_desde = VALUES(vendedor_desde),
        vendedor_hasta = VALUES(vendedor_hasta),
        supervisor_desde = VALUES(supervisor_desde),
        supervisor_hasta = VALUES(supervisor_hasta),
        empresa_desde = VALUES(empresa_desde),
        activo = 1,
        updated_at = CURRENT_TIMESTAMP()
    ", [
      $idPromocion,
      trim((string)$regla['tipo_contrato']),
      futuros_promociones_int_or_null($regla['vendedor_desde'] ?? null),
      futuros_promociones_int_or_null($regla['vendedor_hasta'] ?? null),
      futuros_promociones_int_or_null($regla['supervisor_desde'] ?? null),
      futuros_promociones_int_or_null($regla['supervisor_hasta'] ?? null),
      futuros_promociones_int_or_null($regla['empresa_desde'] ?? null),
    ]);
  }
  return [];
}

function futuros_promociones_resolver_row(array $row): array {
  $numero = (int)($row['numero_pago'] ?? 0);
  $monto = (float)($row['cant_abono'] ?? 0);
  $beneficiarioTipo = 'sin_regla';
  $beneficiarioNombre = 'Sin regla';
  $beneficiarioId = null;
  $observacion = '';
  $asignable = false;

  if (empty($row['id_promocion'])) {
    $observacion = 'Contrato sin promocion.';
  } elseif (empty($row['id_regla'])) {
    $observacion = 'La promocion no tiene regla para este paquete.';
  } elseif ($numero <= 0) {
    $observacion = 'No se pudo calcular el numero de pago.';
  } else {
    $vDesde = futuros_promociones_int_or_null($row['vendedor_desde'] ?? null);
    $vHasta = futuros_promociones_int_or_null($row['vendedor_hasta'] ?? null);
    $sDesde = futuros_promociones_int_or_null($row['supervisor_desde'] ?? null);
    $sHasta = futuros_promociones_int_or_null($row['supervisor_hasta'] ?? null);
    $eDesde = futuros_promociones_int_or_null($row['empresa_desde'] ?? null);

    if ($vDesde !== null && $vHasta !== null && $numero >= $vDesde && $numero <= $vHasta) {
      $beneficiarioTipo = 'vendedor';
      $beneficiarioId = !empty($row['id_vendedor']) ? (int)$row['id_vendedor'] : null;
      $beneficiarioNombre = $row['nombre_vendedor'] ?: 'Vendedor sin asignar';
      $observacion = $beneficiarioId ? '' : 'El contrato no tiene vendedor asignado.';
      $asignable = $beneficiarioId !== null;
    } elseif ($sDesde !== null && $sHasta !== null && $numero >= $sDesde && $numero <= $sHasta) {
      $beneficiarioTipo = 'supervisor';
      $beneficiarioId = !empty($row['id_supervisor']) ? (int)$row['id_supervisor'] : null;
      $beneficiarioNombre = $row['nombre_supervisor'] ?: 'Supervisor sin asignar';
      $observacion = $beneficiarioId ? '' : 'El vendedor no tiene jefe_directo asignado.';
      $asignable = $beneficiarioId !== null;
    } elseif ($eDesde !== null && $numero >= $eDesde) {
      $beneficiarioTipo = 'empresa';
      $beneficiarioNombre = 'Empresa';
      $observacion = '';
      $asignable = false;
    } else {
      $observacion = 'El numero de pago no cae en ningun rango de la regla.';
    }
  }

  $row['promocion'] = futuros_promociones_label($row['promo_codigo'] ?? '', $row['promo_concepto'] ?? '');
  $row['beneficiario_tipo'] = $beneficiarioTipo;
  $row['beneficiario_id'] = $beneficiarioId;
  $row['beneficiario_nombre'] = $beneficiarioNombre;
  $row['monto_asignado'] = ($asignable || $beneficiarioTipo === 'empresa') ? $monto : 0.0;
  $row['observacion'] = $observacion;
  $row['asignable_persona'] = $asignable;
  return $row;
}

function futuros_promociones_pagos_resueltos(?string $fechaInicio, ?string $fechaFin, int $limit = 3000): array {
  futuros_promociones_ensure_schema();
  $limit = max(1, min(10000, (int)$limit));
  $where = [];
  $params = [];
  if ($fechaInicio !== null && $fechaInicio !== '') {
    $where[] = 'a.fecha_registro >= ?';
    $params[] = $fechaInicio.' 00:00:00';
  }
  if ($fechaFin !== null && $fechaFin !== '') {
    $where[] = 'a.fecha_registro <= ?';
    $params[] = $fechaFin.' 23:59:59';
  }
  if (empty($where)) {
    $where[] = 'a.fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
  }
  $rows = qall("
    SELECT
      a.id_abono,
      a.id_contrato,
      a.fecha_registro,
      a.cant_abono,
      c.tipo_contrato,
      (
        SELECT COUNT(*)
        FROM futuro_abonos a2
        WHERE a2.id_contrato = a.id_contrato
          AND (
            a2.fecha_registro < a.fecha_registro
            OR (a2.fecha_registro = a.fecha_registro AND a2.id_abono <= a.id_abono)
          )
      ) AS numero_pago,
      (
        SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m)
        FROM titular_contrato tc
        JOIN titulares t ON t.id_titular = tc.id_titular
        WHERE tc.id_contrato = a.id_contrato
        ORDER BY tc.id_titular_contrato DESC
        LIMIT 1
      ) AS titular,
      cv.id_personal AS id_vendedor,
      CONCAT(COALESCE(pv.nombre,''),' ',COALESCE(pv.apellido_p,''),' ',COALESCE(pv.apellido_m,'')) AS nombre_vendedor,
      sup.id_personal AS id_supervisor,
      CONCAT(COALESCE(sup.nombre,''),' ',COALESCE(sup.apellido_p,''),' ',COALESCE(sup.apellido_m,'')) AS nombre_supervisor,
      pc.id_personal AS id_cobrador,
      CONCAT(COALESCE(pc.nombre,''),' ',COALESCE(pc.apellido_p,''),' ',COALESCE(pc.apellido_m,'')) AS nombre_cobrador,
      promo.id_mos_cat_com AS id_promocion,
      promo.codigo AS promo_codigo,
      promo.concepto AS promo_concepto,
      reg.id_regla,
      reg.vendedor_desde,
      reg.vendedor_hasta,
      reg.supervisor_desde,
      reg.supervisor_hasta,
      reg.empresa_desde
    FROM futuro_abonos a
    JOIN futuro_contratos c ON c.id_contrato = a.id_contrato
    LEFT JOIN futuro_contrato_vendedor cv
      ON cv.id_cont_vend = (
        SELECT cv2.id_cont_vend
        FROM futuro_contrato_vendedor cv2
        WHERE cv2.id_contrato = c.id_contrato
        ORDER BY cv2.id_cont_vend DESC
        LIMIT 1
      )
    LEFT JOIN futuro_personal pv ON pv.id_personal = cv.id_personal
    LEFT JOIN futuro_personal sup ON sup.id_personal = pv.jefe_directo
    LEFT JOIN futuro_abono_cobrador ac
      ON ac.id_abono_cobrador = (
        SELECT ac2.id_abono_cobrador
        FROM futuro_abono_cobrador ac2
        WHERE ac2.id_abono = a.id_abono
        ORDER BY ac2.id_abono_cobrador DESC
        LIMIT 1
      )
    LEFT JOIN futuro_personal pc ON pc.id_personal = ac.id_personal
    LEFT JOIN futuro_cont_cat_com cpromo
      ON cpromo.id_cont_cat_com = (
        SELECT cpromo2.id_cont_cat_com
        FROM futuro_cont_cat_com cpromo2
        WHERE cpromo2.id_contrato = c.id_contrato
        ORDER BY cpromo2.id_cont_cat_com DESC
        LIMIT 1
      )
    LEFT JOIN futuro_mostrar_catalogo_promociones promo ON promo.id_mos_cat_com = cpromo.id_mos_cat_com
    LEFT JOIN futuro_promocion_reglas reg
      ON reg.id_mos_cat_com = promo.id_mos_cat_com
     AND CONVERT(reg.tipo_contrato USING utf8mb4) COLLATE utf8mb4_general_ci =
         CONVERT(c.tipo_contrato USING utf8mb4) COLLATE utf8mb4_general_ci
     AND COALESCE(reg.activo,1) = 1
    WHERE ".implode(' AND ', $where)."
    ORDER BY a.fecha_registro ASC, a.id_abono ASC
    LIMIT {$limit}
  ", $params);

  return array_map('futuros_promociones_resolver_row', $rows);
}
