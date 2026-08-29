<?php
// Reglas economicas, condiciones congeladas y distribucion de pagos de futuros.

function futuros_promociones_ensure_schema(bool $force = false): void {
  static $ready = false;
  if ($ready && !$force) return;

  $required = [
    'futuro_mostrar_catalogo_promociones' => ['id_mos_cat_com', 'codigo', 'concepto', 'estatus'],
    'futuro_cont_cat_com' => ['id_contrato', 'id_mos_cat_com'],
    'futuro_promocion_reglas' => [
      'id_regla', 'id_mos_cat_com', 'pago_inicial', 'gasto_administrativo',
      'comision_vendedor_inicial', 'comision_vendedor_posterior',
      'comision_lider', 'comision_gerente', 'activo',
    ],
    'futuro_contrato_promocion_condiciones' => [
      'id_condicion', 'id_contrato', 'id_mos_cat_com', 'id_regla_origen',
      'pago_inicial', 'gasto_administrativo', 'comision_vendedor_inicial',
      'comision_vendedor_posterior', 'comision_lider', 'comision_gerente',
      'id_vendedor', 'id_lider', 'id_gerente', 'id_abono_corte', 'id_abono_inicial',
    ],
    'futuro_abono_distribucion' => [
      'id_distribucion', 'id_abono', 'id_contrato', 'tipo', 'etapa', 'id_personal', 'monto',
    ],
  ];

  $tableNames = array_keys($required);
  $placeholders = implode(',', array_fill(0, count($tableNames), '?'));
  $schemaRows = qall("
    SELECT TABLE_NAME,COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$placeholders})
  ", $tableNames);
  $available = [];
  foreach ($schemaRows as $row) {
    $available[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']] = true;
  }

  $missing = [];
  foreach ($required as $table => $columns) {
    if (empty($available[$table])) {
      $missing[] = $table;
      continue;
    }
    foreach ($columns as $column) {
      if (empty($available[$table][$column])) $missing[] = $table.'.'.$column;
    }
  }

  if ($missing) {
    throw new RuntimeException(
      'Falta ejecutar la migracion 2026_08_promociones_planes_pagos.sql. Elementos pendientes: '.implode(', ', $missing)
    );
  }
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
  $rows = qall("SELECT DISTINCT TRIM(tipo_contrato) AS tipo_contrato FROM futuro_contratos WHERE tipo_contrato IS NOT NULL AND TRIM(tipo_contrato) <> '' ORDER BY tipo_contrato");
  $paquetes = [];
  foreach ($rows as $row) {
    $tipo = trim((string)($row['tipo_contrato'] ?? ''));
    if ($tipo !== '') $paquetes[] = $tipo;
  }
  return $paquetes ?: ['SERVICIO COMPLETO', 'CREMACION DIRECTA', 'CREMACION'];
}

function futuros_promociones_catalogo(bool $soloActivas = false): array {
  futuros_promociones_ensure_schema();
  $where = $soloActivas ? 'WHERE p.estatus=1' : '';
  return qall("
    SELECT p.id_mos_cat_com, p.codigo, p.concepto, p.estatus, p.fecha_registro,
           r.id_regla, r.pago_inicial, r.gasto_administrativo,
           r.comision_vendedor_inicial, r.comision_vendedor_posterior,
           r.comision_lider, r.comision_gerente
    FROM futuro_mostrar_catalogo_promociones p
    LEFT JOIN futuro_promocion_reglas r ON r.id_regla=(
      SELECT r2.id_regla FROM futuro_promocion_reglas r2
      WHERE r2.id_mos_cat_com=p.id_mos_cat_com AND COALESCE(r2.activo,1)=1
      ORDER BY r2.id_regla DESC LIMIT 1
    )
    {$where}
    ORDER BY p.estatus DESC, p.codigo, p.concepto
  ");
}

function futuros_promociones_opciones(bool $soloActivas = true, bool $soloConfiguradas = false): array {
  $opts = [];
  foreach (futuros_promociones_catalogo($soloActivas) as $p) {
    if ($soloConfiguradas && !futuros_promociones_regla_configurada($p)) continue;
    $opts[(int)$p['id_mos_cat_com']] = futuros_promociones_label($p['codigo'] ?? '', $p['concepto'] ?? '');
  }
  return $opts;
}

function futuros_promociones_regla_por_promocion(int $idPromocion): ?array {
  futuros_promociones_ensure_schema();
  return qone("SELECT * FROM futuro_promocion_reglas WHERE id_mos_cat_com=? ORDER BY activo DESC, id_regla DESC LIMIT 1", [$idPromocion]);
}

function futuros_promociones_money_to_cents($value): ?int {
  if ($value === null) return null;
  $value = trim((string)$value);
  if ($value === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) return null;
  [$whole, $decimals] = array_pad(explode('.', $value, 2), 2, '');
  $whole = ltrim($whole, '0');
  if ($whole === '') $whole = '0';
  if (strlen($whole) > 10) return null;
  $cents = ((int)$whole * 100) + (int)str_pad($decimals, 2, '0');
  return $cents <= 999999999999 ? $cents : null;
}

function futuros_promociones_db_cents($value): int {
  return (int)round(((float)$value) * 100);
}

function futuros_promociones_cents_to_decimal(int $cents): string {
  return number_format($cents / 100, 2, '.', '');
}

function futuros_promociones_regla_campos(): array {
  return [
    'pago_inicial' => 'Pago inicial',
    'gasto_administrativo' => 'Gasto administrativo / papeleria',
    'comision_vendedor_inicial' => 'Comision inicial del vendedor',
    'comision_vendedor_posterior' => 'Comision posterior del vendedor',
    'comision_lider' => 'Comision del lider',
    'comision_gerente' => 'Comision del gerente',
  ];
}

function futuros_promociones_preparar_regla(array $regla): array {
  $datos = [];
  $centavos = [];
  $errores = [];
  foreach (futuros_promociones_regla_campos() as $campo => $etiqueta) {
    $valor = futuros_promociones_money_to_cents($regla[$campo] ?? null);
    if ($valor === null) {
      $errores[] = "{$etiqueta} debe ser un importe no negativo con maximo dos decimales.";
      continue;
    }
    $centavos[$campo] = $valor;
    $datos[$campo] = futuros_promociones_cents_to_decimal($valor);
  }
  if (!$errores) {
    if ($centavos['pago_inicial'] <= 0) $errores[] = 'Pago inicial debe ser mayor que cero.';
    $desglose = $centavos['gasto_administrativo'] + $centavos['comision_vendedor_inicial'];
    if ($centavos['pago_inicial'] !== $desglose) {
      $errores[] = 'Pago inicial debe ser igual al gasto administrativo mas la comision inicial del vendedor.';
    }
  }
  return ['datos'=>$datos, 'centavos'=>$centavos, 'errores'=>$errores];
}

function futuros_promociones_validar_regla(array $regla): array {
  return futuros_promociones_preparar_regla($regla)['errores'];
}

function futuros_promociones_regla_configurada(?array $regla): bool {
  return $regla && !futuros_promociones_validar_regla($regla);
}

function futuros_promociones_guardar_regla(int $idPromocion, array $regla): array {
  $prepared = futuros_promociones_preparar_regla($regla);
  if ($prepared['errores']) return $prepared['errores'];
  $d = $prepared['datos'];
  $current = qone("SELECT id_regla FROM futuro_promocion_reglas WHERE id_mos_cat_com=? ORDER BY activo DESC,id_regla DESC LIMIT 1 FOR UPDATE", [$idPromocion]);
  if ($current) {
    $id = (int)$current['id_regla'];
    q("UPDATE futuro_promocion_reglas SET activo=0 WHERE id_mos_cat_com=? AND id_regla<>?", [$idPromocion,$id]);
    q("UPDATE futuro_promocion_reglas SET pago_inicial=?,gasto_administrativo=?,comision_vendedor_inicial=?,comision_vendedor_posterior=?,comision_lider=?,comision_gerente=?,activo=1,updated_at=CURRENT_TIMESTAMP WHERE id_regla=?", [
      $d['pago_inicial'],$d['gasto_administrativo'],$d['comision_vendedor_inicial'],
      $d['comision_vendedor_posterior'],$d['comision_lider'],$d['comision_gerente'],$id,
    ]);
  } else {
    q("INSERT INTO futuro_promocion_reglas (id_mos_cat_com,pago_inicial,gasto_administrativo,comision_vendedor_inicial,comision_vendedor_posterior,comision_lider,comision_gerente,activo) VALUES (?,?,?,?,?,?,?,1)", [
      $idPromocion,$d['pago_inicial'],$d['gasto_administrativo'],$d['comision_vendedor_inicial'],
      $d['comision_vendedor_posterior'],$d['comision_lider'],$d['comision_gerente'],
    ]);
  }
  return [];
}

function futuros_promociones_jerarquia(int $idVendedor, array $regla): array {
  $errores = [];
  $vendedor = qone("SELECT id_personal,jefe_directo FROM futuro_personal WHERE id_personal=?", [$idVendedor]);
  if (!$vendedor) return ['id_vendedor'=>$idVendedor,'id_lider'=>null,'id_gerente'=>null,'errores'=>['El vendedor seleccionado no existe.']];

  $idLider = (int)($vendedor['jefe_directo'] ?? 0);
  $lider = $idLider > 0 ? qone("SELECT id_personal,jefe_directo FROM futuro_personal WHERE id_personal=?", [$idLider]) : null;
  $idGerente = (int)($lider['jefe_directo'] ?? 0);
  $gerente = $idGerente > 0 ? qone("SELECT id_personal FROM futuro_personal WHERE id_personal=?", [$idGerente]) : null;

  if (futuros_promociones_db_cents($regla['comision_lider'] ?? 0) > 0 && !$lider) {
    $errores[] = 'El vendedor no tiene lider configurado como jefe directo.';
  }
  if (futuros_promociones_db_cents($regla['comision_gerente'] ?? 0) > 0 && !$gerente) {
    $errores[] = 'El lider no tiene gerente configurado como jefe directo.';
  }
  if ($idVendedor === $idLider || $idVendedor === $idGerente || ($idLider > 0 && $idLider === $idGerente)) {
    $errores[] = 'La jerarquia del vendedor contiene una relacion circular.';
  }
  return [
    'id_vendedor'=>$idVendedor,
    'id_lider'=>$lider ? $idLider : null,
    'id_gerente'=>$gerente ? $idGerente : null,
    'errores'=>$errores,
  ];
}

function futuros_promociones_total_fijo_cents(array $regla): int {
  return futuros_promociones_db_cents($regla['pago_inicial'] ?? 0)
    + futuros_promociones_db_cents($regla['comision_vendedor_posterior'] ?? 0)
    + futuros_promociones_db_cents($regla['comision_lider'] ?? 0)
    + futuros_promociones_db_cents($regla['comision_gerente'] ?? 0);
}

function futuros_promociones_crear_condicion(
  int $idContrato,
  int $idPromocion,
  array $regla,
  array $jerarquia,
  int $idAbonoCorte = 0,
  bool $migrado = false
): int {
  q("INSERT INTO futuro_contrato_promocion_condiciones
    (id_contrato,id_mos_cat_com,id_regla_origen,pago_inicial,gasto_administrativo,
     comision_vendedor_inicial,comision_vendedor_posterior,comision_lider,comision_gerente,
     id_vendedor,id_lider,id_gerente,id_abono_corte,es_migrado,fecha_vigencia)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)", [
      $idContrato,$idPromocion,(int)$regla['id_regla'],$regla['pago_inicial'],$regla['gasto_administrativo'],
      $regla['comision_vendedor_inicial'],$regla['comision_vendedor_posterior'],$regla['comision_lider'],$regla['comision_gerente'],
      $jerarquia['id_vendedor'],$jerarquia['id_lider'],$jerarquia['id_gerente'],$idAbonoCorte,$migrado ? 1 : 0,
    ]);
  $idCondicion = (int)db()->lastInsertId();
  futuros_promociones_guardar_relacion_personal('futuro_contrato_lider', 'id_cont_lider', $idContrato, (int)($jerarquia['id_lider'] ?? 0));
  futuros_promociones_guardar_relacion_personal('futuro_contrato_jefecuad', 'id_cont_jefecuad', $idContrato, (int)($jerarquia['id_gerente'] ?? 0));
  return $idCondicion;
}

function futuros_promociones_guardar_relacion_personal(string $table, string $idColumn, int $idContrato, int $idPersonal): void {
  if ($idPersonal <= 0 || !preg_match('/^[a-z0-9_]+$/', $table.$idColumn)) return;
  $exists = qone("SELECT {$idColumn} AS id FROM {$table} WHERE id_contrato=? AND id_personal=? ORDER BY {$idColumn} DESC LIMIT 1", [$idContrato,$idPersonal]);
  if (!$exists) q("INSERT INTO {$table} (id_contrato,id_personal) VALUES (?,?)", [$idContrato,$idPersonal]);
}

function futuros_promociones_condicion_contrato(int $idContrato): ?array {
  return qone("SELECT * FROM futuro_contrato_promocion_condiciones WHERE id_contrato=? LIMIT 1", [$idContrato]);
}

function futuros_promociones_segmentos(array $condicion, int $totalAntes, int $monto): array {
  if ($monto <= 0) return [];
  $buckets = [
    ['tipo'=>'gasto_administrativo','etapa'=>'inicial','id_personal'=>null,'monto'=>futuros_promociones_db_cents($condicion['gasto_administrativo'] ?? 0)],
    ['tipo'=>'vendedor','etapa'=>'inicial','id_personal'=>(int)($condicion['id_vendedor'] ?? 0),'monto'=>futuros_promociones_db_cents($condicion['comision_vendedor_inicial'] ?? 0)],
    ['tipo'=>'vendedor','etapa'=>'posterior','id_personal'=>(int)($condicion['id_vendedor'] ?? 0),'monto'=>futuros_promociones_db_cents($condicion['comision_vendedor_posterior'] ?? 0)],
    ['tipo'=>'lider','etapa'=>'posterior','id_personal'=>(int)($condicion['id_lider'] ?? 0),'monto'=>futuros_promociones_db_cents($condicion['comision_lider'] ?? 0)],
    ['tipo'=>'gerente','etapa'=>'posterior','id_personal'=>(int)($condicion['id_gerente'] ?? 0),'monto'=>futuros_promociones_db_cents($condicion['comision_gerente'] ?? 0)],
  ];
  $inicioPago = $totalAntes;
  $finPago = $totalAntes + $monto;
  $umbral = 0;
  $segmentos = [];
  foreach ($buckets as $bucket) {
    $inicioBolsa = $umbral;
    $umbral += $bucket['monto'];
    $asignado = max(0, min($finPago, $umbral) - max($inicioPago, $inicioBolsa));
    if ($asignado > 0) {
      $bucket['monto'] = $asignado;
      $segmentos[] = $bucket;
    }
  }
  if ($finPago > $umbral) {
    $empresa = $finPago - max($inicioPago, $umbral);
    if ($empresa > 0) $segmentos[] = ['tipo'=>'empresa','etapa'=>'posterior','id_personal'=>null,'monto'=>$empresa];
  }
  return $segmentos;
}

function futuros_promociones_reconstruir_distribuciones(int $idContrato): void {
  $condicion = futuros_promociones_condicion_contrato($idContrato);
  if (!$condicion) throw new RuntimeException('El contrato no tiene condiciones de promocion congeladas.');
  q("DELETE FROM futuro_abono_distribucion WHERE id_contrato=?", [$idContrato]);
  $abonos = qall("SELECT id_abono,cant_abono,fecha_registro FROM futuro_abonos WHERE id_contrato=? ORDER BY fecha_registro,id_abono", [$idContrato]);
  $totalAntes = 0;
  $corte = (int)($condicion['id_abono_corte'] ?? 0);
  foreach ($abonos as $abono) {
    $monto = futuros_promociones_db_cents($abono['cant_abono'] ?? 0);
    if ((int)$abono['id_abono'] > $corte) {
      foreach (futuros_promociones_segmentos($condicion, $totalAntes, $monto) as $segmento) {
        q("INSERT INTO futuro_abono_distribucion (id_abono,id_contrato,tipo,etapa,id_personal,monto) VALUES (?,?,?,?,?,?)", [
          (int)$abono['id_abono'],$idContrato,$segmento['tipo'],$segmento['etapa'],
          $segmento['id_personal'] ?: null,futuros_promociones_cents_to_decimal($segmento['monto']),
        ]);
      }
    }
    $totalAntes += $monto;
  }
}

function futuros_promociones_normalizar(string $value): string {
  $value = trim(mb_strtolower($value, 'UTF-8'));
  $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
  if (is_string($converted)) $value = $converted;
  return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function futuros_promociones_codigo_canonico(string $codigo, string $concepto): ?string {
  $text = futuros_promociones_normalizar($codigo.' '.$concepto);
  if (str_contains($text, '1000')) return 'PROMO1000';
  if (str_contains($text, '500')) return 'PROMO500';
  if (str_contains($text, '300')) return 'PROMO300';
  if (str_contains($text, 'normal')) return 'NORMAL';
  return null;
}

function futuros_promociones_buscar_codigo(string $codigo): ?array {
  $needle = futuros_promociones_normalizar($codigo);
  foreach (futuros_promociones_catalogo(false) as $promotion) {
    if (futuros_promociones_normalizar((string)$promotion['codigo']) === $needle) return $promotion;
  }
  return null;
}

function futuros_promociones_evaluar_contrato_migracion(array $contract): array {
    $idContrato = (int)($contract['id_contrato'] ?? 0);
    if ($idContrato <= 0) return ['item'=>null, 'error'=>'Contrato invalido.'];
    $idPromocion = (int)($contract['id_promocion'] ?? 0);
    $promo = $idPromocion > 0 ? qone("SELECT * FROM futuro_mostrar_catalogo_promociones WHERE id_mos_cat_com=?", [$idPromocion]) : null;
    $rule = $promo ? futuros_promociones_regla_por_promocion($idPromocion) : null;
    if (!$promo || !futuros_promociones_regla_configurada($rule)) {
      $canonical = $promo ? futuros_promociones_codigo_canonico((string)$promo['codigo'], (string)$promo['concepto']) : null;
      $mapped = $canonical ? futuros_promociones_buscar_codigo($canonical) : null;
      if ($mapped) {
        $idPromocion = (int)$mapped['id_mos_cat_com'];
        $promo = $mapped;
        $rule = futuros_promociones_regla_por_promocion($idPromocion);
      }
    }
    if (!$promo || !futuros_promociones_regla_configurada($rule)) {
      return ['item'=>null, 'error'=>'promocion inexistente o sin configuracion valida.'];
    }
    $hierarchy = futuros_promociones_jerarquia((int)$contract['id_vendedor'], $rule);
    if ($hierarchy['errores']) {
      return ['item'=>null, 'error'=>implode(' ', $hierarchy['errores'])];
    }
    if (futuros_promociones_db_cents($contract['costo_final']) < futuros_promociones_total_fijo_cents($rule)) {
      return ['item'=>null, 'error'=>'el costo no cubre las bolsas de la promocion.'];
    }
    $cutoff = (int)(qone("SELECT COALESCE(MAX(id_abono),0) total FROM futuro_abonos WHERE id_contrato=?", [$idContrato])['total'] ?? 0);
    $paid = (float)(qone("SELECT COALESCE(SUM(cant_abono),0) total FROM futuro_abonos WHERE id_contrato=?", [$idContrato])['total'] ?? 0);
    return [
      'item'=>['contrato'=>$contract,'promocion'=>$promo,'regla'=>$rule,'jerarquia'=>$hierarchy,'id_abono_corte'=>$cutoff,'pagado'=>$paid],
      'error'=>null,
    ];
}

function futuros_promociones_migracion_estado(): array {
  futuros_promociones_ensure_schema();
  $contracts = qall("
    SELECT c.id_contrato,c.costo_final,
      (SELECT cv.id_personal FROM futuro_contrato_vendedor cv WHERE cv.id_contrato=c.id_contrato ORDER BY cv.id_cont_vend DESC LIMIT 1) id_vendedor,
      (SELECT cc.id_mos_cat_com FROM futuro_cont_cat_com cc WHERE cc.id_contrato=c.id_contrato ORDER BY cc.id_cont_cat_com DESC LIMIT 1) id_promocion
    FROM futuro_contratos c
    LEFT JOIN futuro_contrato_promocion_condiciones cp ON cp.id_contrato=c.id_contrato
    WHERE UPPER(c.estatus)='ACTIVO' AND cp.id_condicion IS NULL
    ORDER BY c.id_contrato
  ");
  $ready = [];
  $errors = [];
  foreach ($contracts as $contract) {
    $evaluacion = futuros_promociones_evaluar_contrato_migracion($contract);
    if ($evaluacion['item']) {
      $ready[] = $evaluacion['item'];
    } else {
      $errors[] = 'Contrato #'.(int)$contract['id_contrato'].': '.$evaluacion['error'];
    }
  }
  return ['listos'=>$ready,'errores'=>$errors,'total'=>count($contracts)];
}

function futuros_promociones_migrar_contratos_vigentes(): array {
  $state = futuros_promociones_migracion_estado();
  if ($state['errores']) throw new RuntimeException('Corrige los contratos pendientes antes de activar la migracion.');
  foreach ($state['listos'] as $item) {
    futuros_promociones_migrar_item($item);
  }
  return ['migrados'=>count($state['listos'])];
}

function futuros_promociones_migrar_item(array $item): int {
  $idContrato = (int)($item['contrato']['id_contrato'] ?? 0);
  $idPromocion = (int)($item['promocion']['id_mos_cat_com'] ?? 0);
  if ($idContrato <= 0 || $idPromocion <= 0 || empty($item['regla']) || empty($item['jerarquia'])) {
    throw new RuntimeException('El contrato no tiene una configuracion valida para prepararse.');
  }
  if (futuros_promociones_condicion_contrato($idContrato)) return $idContrato;
  if ((int)($item['contrato']['id_promocion'] ?? 0) !== $idPromocion) {
    q("INSERT INTO futuro_cont_cat_com (id_contrato,id_mos_cat_com) VALUES (?,?)", [$idContrato,$idPromocion]);
  }
  futuros_promociones_crear_condicion(
    $idContrato,
    $idPromocion,
    $item['regla'],
    $item['jerarquia'],
    (int)($item['id_abono_corte'] ?? 0),
    true
  );
  return $idContrato;
}

function futuros_promociones_migrar_contrato_vigente(int $idContrato): array {
  if ($idContrato <= 0) throw new RuntimeException('Contrato invalido.');
  if (futuros_promociones_condicion_contrato($idContrato)) {
    return ['id_contrato'=>$idContrato, 'preparado'=>false];
  }
  $contract = qone("
    SELECT c.id_contrato,c.costo_final,
      (SELECT cv.id_personal FROM futuro_contrato_vendedor cv WHERE cv.id_contrato=c.id_contrato ORDER BY cv.id_cont_vend DESC LIMIT 1) id_vendedor,
      (SELECT cc.id_mos_cat_com FROM futuro_cont_cat_com cc WHERE cc.id_contrato=c.id_contrato ORDER BY cc.id_cont_cat_com DESC LIMIT 1) id_promocion
    FROM futuro_contratos c
    WHERE c.id_contrato=? AND UPPER(c.estatus)='ACTIVO'
    LIMIT 1
  ", [$idContrato]);
  if (!$contract) throw new RuntimeException('El contrato no existe o no esta activo.');
  $evaluacion = futuros_promociones_evaluar_contrato_migracion($contract);
  if (!$evaluacion['item']) throw new RuntimeException((string)$evaluacion['error']);
  futuros_promociones_migrar_item($evaluacion['item']);
  return ['id_contrato'=>$idContrato, 'preparado'=>true];
}

function futuros_promociones_catalogo_para_reportes(): array {
  $porId = [];
  $porCodigoCanonico = [];
  foreach (futuros_promociones_catalogo(false) as $promocion) {
    $idPromocion = (int)$promocion['id_mos_cat_com'];
    $porId[$idPromocion] = $promocion;
    if (!futuros_promociones_regla_configurada($promocion)) continue;

    $codigoCanonico = futuros_promociones_codigo_canonico(
      (string)($promocion['codigo'] ?? ''),
      (string)($promocion['concepto'] ?? '')
    );
    if (!$codigoCanonico) continue;

    $actual = $porCodigoCanonico[$codigoCanonico] ?? null;
    if (!$actual || ((int)$promocion['estatus'] === 1 && (int)$actual['estatus'] !== 1)) {
      $porCodigoCanonico[$codigoCanonico] = $promocion;
    }
  }
  return ['por_id'=>$porId, 'por_codigo'=>$porCodigoCanonico];
}

function futuros_promociones_resolver_para_reporte(int $idPromocion, array $catalogo): ?array {
  $promocion = $catalogo['por_id'][$idPromocion] ?? null;
  if (!$promocion) return null;
  if (futuros_promociones_regla_configurada($promocion)) return $promocion;

  $codigoCanonico = futuros_promociones_codigo_canonico(
    (string)($promocion['codigo'] ?? ''),
    (string)($promocion['concepto'] ?? '')
  );
  return $codigoCanonico ? ($catalogo['por_codigo'][$codigoCanonico] ?? null) : null;
}

function futuros_promociones_personal_para_reportes(): array {
  $personal = [];
  foreach (qall("SELECT id_personal,jefe_directo,nombre,apellido_p,apellido_m FROM futuro_personal") as $row) {
    $idPersonal = (int)$row['id_personal'];
    $personal[$idPersonal] = [
      'id_personal'=>$idPersonal,
      'jefe_directo'=>(int)($row['jefe_directo'] ?? 0),
      'nombre'=>trim(implode(' ', array_filter([
        trim((string)($row['nombre'] ?? '')),
        trim((string)($row['apellido_p'] ?? '')),
        trim((string)($row['apellido_m'] ?? '')),
      ], fn($parte) => $parte !== ''))),
    ];
  }
  return $personal;
}

function futuros_promociones_condicion_virtual(array $row, array $catalogo, array $personal): ?array {
  if ((int)($row['id_condicion'] ?? 0) > 0) {
    $idPromocion = (int)$row['condicion_id_promocion'];
    return [
      'id_mos_cat_com'=>$idPromocion,
      'pago_inicial'=>$row['condicion_pago_inicial'],
      'gasto_administrativo'=>$row['condicion_gasto_administrativo'],
      'comision_vendedor_inicial'=>$row['condicion_vendedor_inicial'],
      'comision_vendedor_posterior'=>$row['condicion_vendedor_posterior'],
      'comision_lider'=>$row['condicion_lider'],
      'comision_gerente'=>$row['condicion_gerente'],
      'id_vendedor'=>(int)$row['condicion_id_vendedor'],
      'id_lider'=>(int)($row['condicion_id_lider'] ?? 0),
      'id_gerente'=>(int)($row['condicion_id_gerente'] ?? 0),
      'promocion'=>$catalogo['por_id'][$idPromocion] ?? null,
    ];
  }

  $promocion = futuros_promociones_resolver_para_reporte((int)($row['id_promocion_actual'] ?? 0), $catalogo);
  $idVendedor = (int)($row['id_vendedor_actual'] ?? 0);
  if (!$promocion || $idVendedor <= 0 || empty($personal[$idVendedor])) return null;

  $idLider = (int)($personal[$idVendedor]['jefe_directo'] ?? 0);
  $idGerente = (int)($personal[$idLider]['jefe_directo'] ?? 0);
  $requiereLider = futuros_promociones_db_cents($promocion['comision_lider'] ?? 0) > 0;
  $requiereGerente = futuros_promociones_db_cents($promocion['comision_gerente'] ?? 0) > 0;
  if ($requiereLider && empty($personal[$idLider])) return null;
  if ($requiereGerente && empty($personal[$idGerente])) return null;
  if ($idVendedor === $idLider || $idVendedor === $idGerente || ($idLider > 0 && $idLider === $idGerente)) return null;

  return array_merge($promocion, [
    'id_vendedor'=>$idVendedor,
    'id_lider'=>$idLider ?: null,
    'id_gerente'=>$idGerente ?: null,
    'promocion'=>$promocion,
  ]);
}

function futuros_promociones_reporte_diagnostico(?array $nuevo = null): array {
  static $diagnostico = [
    'abonos_evaluados'=>0,
    'contratos_calculados'=>0,
    'contratos_sin_configuracion'=>0,
    'abonos_sin_configuracion'=>0,
    'limite_alcanzado'=>false,
  ];
  if ($nuevo !== null) $diagnostico = array_merge($diagnostico, $nuevo);
  return $diagnostico;
}

function futuros_promociones_pagos_resueltos(?string $fechaInicio, ?string $fechaFin, int $limit = 5000): array {
  futuros_promociones_ensure_schema();
  $limit = max(1, min(20000, $limit));
  $fechaInicio = $fechaInicio && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)
    ? $fechaInicio
    : date('Y-m-d', strtotime('-30 days'));
  $fechaFin = $fechaFin && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)
    ? $fechaFin
    : date('Y-m-d');
  if ($fechaInicio > $fechaFin) [$fechaInicio,$fechaFin] = [$fechaFin,$fechaInicio];
  $inicioSql = $fechaInicio.' 00:00:00';
  $finSql = $fechaFin.' 23:59:59';

  $acumulados = [];
  foreach (qall("
    SELECT id_contrato,COUNT(*) numero_pagos,COALESCE(SUM(cant_abono),0) total_pagado
    FROM futuro_abonos
    WHERE fecha_registro<?
    GROUP BY id_contrato
  ", [$inicioSql]) as $row) {
    $acumulados[(int)$row['id_contrato']] = [
      'pagos'=>(int)$row['numero_pagos'],
      'monto'=>futuros_promociones_db_cents($row['total_pagado']),
    ];
  }

  $abonos = qall("
    SELECT a.id_abono,a.id_contrato,a.cant_abono,a.fecha_registro,c.tipo_contrato,
           cp.id_condicion,cp.id_mos_cat_com condicion_id_promocion,
           cp.pago_inicial condicion_pago_inicial,
           cp.gasto_administrativo condicion_gasto_administrativo,
           cp.comision_vendedor_inicial condicion_vendedor_inicial,
           cp.comision_vendedor_posterior condicion_vendedor_posterior,
           cp.comision_lider condicion_lider,cp.comision_gerente condicion_gerente,
           cp.id_vendedor condicion_id_vendedor,cp.id_lider condicion_id_lider,
           cp.id_gerente condicion_id_gerente,
           (SELECT cc.id_mos_cat_com FROM futuro_cont_cat_com cc WHERE cc.id_contrato=a.id_contrato ORDER BY cc.id_cont_cat_com DESC LIMIT 1) id_promocion_actual,
           (SELECT cv.id_personal FROM futuro_contrato_vendedor cv WHERE cv.id_contrato=a.id_contrato ORDER BY cv.id_cont_vend DESC LIMIT 1) id_vendedor_actual,
           (SELECT CONCAT(t.nombre,' ',t.apellido_p,' ',t.apellido_m) FROM titular_contrato tc JOIN titulares t ON t.id_titular=tc.id_titular WHERE tc.id_contrato=a.id_contrato ORDER BY tc.id_titular_contrato DESC LIMIT 1) titular,
           (SELECT ac.id_personal FROM futuro_abono_cobrador ac WHERE ac.id_abono=a.id_abono ORDER BY ac.id_abono_cobrador DESC LIMIT 1) id_cobrador
    FROM futuro_abonos a
    JOIN futuro_contratos c ON c.id_contrato=a.id_contrato
    LEFT JOIN futuro_contrato_promocion_condiciones cp ON cp.id_contrato=a.id_contrato
    WHERE a.fecha_registro>=? AND a.fecha_registro<=?
    ORDER BY a.fecha_registro,a.id_abono
    LIMIT {$limit}
  ", [$inicioSql,$finSql]);

  $catalogo = futuros_promociones_catalogo_para_reportes();
  $personal = futuros_promociones_personal_para_reportes();
  $condiciones = [];
  $contratosCalculados = [];
  $contratosSinConfiguracion = [];
  $abonosSinConfiguracion = 0;
  $rows = [];
  foreach ($abonos as $abono) {
    $idContrato = (int)$abono['id_contrato'];
    if (!array_key_exists($idContrato, $condiciones)) {
      $condiciones[$idContrato] = futuros_promociones_condicion_virtual($abono, $catalogo, $personal);
    }
    $condicion = $condiciones[$idContrato];
    $monto = futuros_promociones_db_cents($abono['cant_abono'] ?? 0);
    $totalAntes = (int)($acumulados[$idContrato]['monto'] ?? 0);
    $numeroPago = (int)($acumulados[$idContrato]['pagos'] ?? 0) + 1;
    $acumulados[$idContrato] = ['monto'=>$totalAntes + $monto, 'pagos'=>$numeroPago];
    if (!$condicion) {
      $contratosSinConfiguracion[$idContrato] = true;
      $abonosSinConfiguracion++;
      continue;
    }
    $contratosCalculados[$idContrato] = true;
    if ($monto <= 0) continue;

    $promocion = $condicion['promocion'] ?? null;
    $idCobrador = (int)($abono['id_cobrador'] ?? 0);
    foreach (futuros_promociones_segmentos($condicion, $totalAntes, $monto) as $orden => $segmento) {
      $tipo = (string)$segmento['tipo'];
      $idBeneficiario = (int)($segmento['id_personal'] ?? 0);
      $nombreBeneficiario = $idBeneficiario > 0 ? (string)($personal[$idBeneficiario]['nombre'] ?? '') : '';
      if ($tipo === 'empresa') $nombreBeneficiario = 'Empresa';
      if ($tipo === 'gasto_administrativo') $nombreBeneficiario = 'Administrativo / papeleria';
      $rows[] = [
        'id_distribucion'=>0,
        'id_abono'=>(int)$abono['id_abono'],
        'id_contrato'=>$idContrato,
        'beneficiario_tipo'=>$tipo,
        'etapa'=>(string)$segmento['etapa'],
        'beneficiario_id'=>$idBeneficiario ?: null,
        'monto_asignado'=>futuros_promociones_cents_to_decimal((int)$segmento['monto']),
        'cant_abono'=>$abono['cant_abono'],
        'fecha_registro'=>$abono['fecha_registro'],
        'tipo_contrato'=>$abono['tipo_contrato'],
        'promo_codigo'=>$promocion['codigo'] ?? '',
        'promo_concepto'=>$promocion['concepto'] ?? '',
        'promocion'=>futuros_promociones_label($promocion['codigo'] ?? '', $promocion['concepto'] ?? ''),
        'beneficiario_nombre'=>$nombreBeneficiario,
        'nombre_cobrador'=>$idCobrador > 0 ? (string)($personal[$idCobrador]['nombre'] ?? '') : '',
        'titular'=>$abono['titular'] ?? '',
        'numero_pago'=>$numeroPago,
        'observacion'=>'Calculado desde los abonos existentes.',
        'asignable_persona'=>$idBeneficiario > 0,
        '_orden_segmento'=>$orden,
      ];
    }
  }

  usort($rows, static function(array $a, array $b): int {
    return strcmp((string)$a['fecha_registro'], (string)$b['fecha_registro'])
      ?: ((int)$a['id_abono'] <=> (int)$b['id_abono'])
      ?: ((int)$a['_orden_segmento'] <=> (int)$b['_orden_segmento']);
  });
  $rows = array_slice($rows, 0, $limit);
  foreach ($rows as &$row) unset($row['_orden_segmento']);
  unset($row);
  futuros_promociones_reporte_diagnostico([
    'abonos_evaluados'=>count($abonos),
    'contratos_calculados'=>count($contratosCalculados),
    'contratos_sin_configuracion'=>count($contratosSinConfiguracion),
    'abonos_sin_configuracion'=>$abonosSinConfiguracion,
    'limite_alcanzado'=>count($abonos) >= $limit,
  ]);
  return $rows;
}
