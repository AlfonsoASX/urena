<?php

$consultasHistoricas = [];

function qall($sql, $params = []): array {
  global $consultasHistoricas;
  $consultasHistoricas[] = $sql;

  if (str_contains($sql, 'information_schema.COLUMNS')) {
    $required = [
      'futuro_mostrar_catalogo_promociones'=>['id_mos_cat_com','codigo','concepto','estatus'],
      'futuro_cont_cat_com'=>['id_contrato','id_mos_cat_com'],
      'futuro_promocion_reglas'=>['id_regla','id_mos_cat_com','pago_inicial','gasto_administrativo','comision_vendedor_inicial','comision_vendedor_posterior','comision_lider','comision_gerente','activo'],
      'futuro_contrato_promocion_condiciones'=>['id_condicion','id_contrato','id_mos_cat_com','id_regla_origen','pago_inicial','gasto_administrativo','comision_vendedor_inicial','comision_vendedor_posterior','comision_lider','comision_gerente','id_usuario_vendedor','id_usuario_lider','id_usuario_gerente','id_abono_corte','id_abono_inicial'],
      'futuro_abono_distribucion'=>['id_distribucion','id_abono','id_contrato','tipo','etapa','id_usuario','monto'],
    ];
    $rows = [];
    foreach ($required as $table=>$columns) {
      foreach ($columns as $column) $rows[] = ['TABLE_NAME'=>$table,'COLUMN_NAME'=>$column];
    }
    return $rows;
  }

  if (str_contains($sql, 'FROM futuro_mostrar_catalogo_promociones p')) {
    return [[
      'id_mos_cat_com'=>1,'codigo'=>'PROMO300','concepto'=>'Promocion 300','estatus'=>1,
      'fecha_registro'=>'2026-01-01 00:00:00','id_regla'=>1,'pago_inicial'=>'300.00',
      'gasto_administrativo'=>'100.00','comision_vendedor_inicial'=>'200.00',
      'comision_vendedor_posterior'=>'1000.00','comision_lider'=>'100.00','comision_gerente'=>'200.00',
    ]];
  }

  if (str_contains($sql, 'COUNT(*) numero_pagos')) {
    return [['id_contrato'=>100,'numero_pagos'=>8,'total_pagado'=>'800.00']];
  }

  if (str_contains($sql, 'SELECT a.id_abono,a.id_contrato')) {
    return [[
      'id_abono'=>9,'id_contrato'=>100,'cant_abono'=>'700.00','fecha_registro'=>'2026-06-10 12:00:00',
      'tipo_contrato'=>'SERVICIO COMPLETO','id_condicion'=>null,'condicion_id_promocion'=>null,
      'condicion_pago_inicial'=>null,'condicion_gasto_administrativo'=>null,
      'condicion_vendedor_inicial'=>null,'condicion_vendedor_posterior'=>null,
      'condicion_lider'=>null,'condicion_gerente'=>null,'condicion_id_vendedor'=>null,
      'condicion_id_lider'=>null,'condicion_id_gerente'=>null,'id_promocion_actual'=>1,
      'id_vendedor_actual'=>10,'id_lider_actual'=>20,'id_gerente_actual'=>30,'titular'=>'Titular Prueba','id_cobrador'=>40,
    ]];
  }

  if (str_contains($sql, 'SELECT id,jefe_directo_id,nombre')) {
    return [
      ['id'=>10,'jefe_directo_id'=>20,'nombre'=>'Vendedor Prueba','puesto'=>'vendedor','estatus'=>'ACTIVO'],
      ['id'=>20,'jefe_directo_id'=>30,'nombre'=>'Lider Prueba','puesto'=>'lider','estatus'=>'ACTIVO'],
      ['id'=>30,'jefe_directo_id'=>0,'nombre'=>'Gerente Prueba','puesto'=>'gerente','estatus'=>'ACTIVO'],
      ['id'=>40,'jefe_directo_id'=>0,'nombre'=>'Cobrador Prueba','puesto'=>'cobrador','estatus'=>'ACTIVO'],
    ];
  }

  throw new RuntimeException('Consulta no contemplada por la prueba: '.$sql);
}

require_once __DIR__.'/../core/futuros_promociones.php';

function reporte_historico_assert(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$rows = futuros_promociones_pagos_resueltos('2026-06-01', '2026-06-30', 5000);
$totales = [];
foreach ($rows as $row) {
  $tipo = $row['beneficiario_tipo'];
  $totales[$tipo] = ($totales[$tipo] ?? 0) + futuros_promociones_db_cents($row['monto_asignado']);
}

reporte_historico_assert(($totales['vendedor'] ?? 0) === 50000, 'Debe calcular 500 historicos para vendedor.');
reporte_historico_assert(($totales['lider'] ?? 0) === 10000, 'Debe calcular 100 historicos para lider.');
reporte_historico_assert(($totales['gerente'] ?? 0) === 10000, 'Debe calcular 100 historicos para gerente.');
reporte_historico_assert(count(array_filter($consultasHistoricas, fn($sql)=>str_contains($sql, 'FROM futuro_abono_distribucion'))) === 0, 'El reporte no debe depender de distribuciones guardadas.');

$diagnostico = futuros_promociones_reporte_diagnostico();
reporte_historico_assert($diagnostico['abonos_evaluados'] === 1, 'Debe reportar el abono historico evaluado.');
reporte_historico_assert($diagnostico['contratos_sin_configuracion'] === 0, 'El contrato configurable no debe omitirse.');

echo "OK: reportes historicos calculados sin distribuciones guardadas.\n";
