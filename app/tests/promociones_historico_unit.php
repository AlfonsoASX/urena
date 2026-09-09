<?php

require_once __DIR__.'/../core/futuros_promociones.php';

function historico_assert(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

function historico_total_tipo(array $segmentos, string $tipo): int {
  $total = 0;
  foreach ($segmentos as $segmento) {
    if ($segmento['tipo'] === $tipo) $total += (int)$segmento['monto'];
  }
  return $total;
}

$condicion = [
  'pago_inicial'=>'300.00',
  'gasto_administrativo'=>'100.00',
  'comision_vendedor_inicial'=>'200.00',
  'comision_vendedor_posterior'=>'1000.00',
  'comision_lider'=>'100.00',
  'comision_gerente'=>'200.00',
  'id_usuario_vendedor'=>10,
  'id_usuario_lider'=>20,
  'id_usuario_gerente'=>30,
];

$inicial = futuros_promociones_segmentos($condicion, 0, 30000);
historico_assert(historico_total_tipo($inicial, 'gasto_administrativo') === 10000, 'El pago inicial debe asignar 100 a administrativo.');
historico_assert(historico_total_tipo($inicial, 'vendedor') === 20000, 'El pago inicial debe asignar 200 al vendedor.');

$posterior = futuros_promociones_segmentos($condicion, 30000, 100000);
historico_assert(historico_total_tipo($posterior, 'vendedor') === 100000, 'La bolsa posterior debe asignar 1000 al vendedor.');

$lider = futuros_promociones_segmentos($condicion, 130000, 10000);
historico_assert(historico_total_tipo($lider, 'lider') === 10000, 'La bolsa de lider debe asignar 100.');

$gerente = futuros_promociones_segmentos($condicion, 140000, 20000);
historico_assert(historico_total_tipo($gerente, 'gerente') === 20000, 'La bolsa de gerente debe asignar 200.');

$empresa = futuros_promociones_segmentos($condicion, 160000, 5000);
historico_assert(historico_total_tipo($empresa, 'empresa') === 5000, 'El excedente debe pertenecer a empresa.');

// Un reporte que inicia con 800 ya pagados debe continuar desde ese acumulado.
$rangoHistorico = futuros_promociones_segmentos($condicion, 80000, 70000);
historico_assert(historico_total_tipo($rangoHistorico, 'vendedor') === 50000, 'El rango debe respetar la parte de vendedor consumida antes del periodo.');
historico_assert(historico_total_tipo($rangoHistorico, 'lider') === 10000, 'El abono que cruza bolsas debe incluir lider.');
historico_assert(historico_total_tipo($rangoHistorico, 'gerente') === 10000, 'El abono que cruza bolsas debe incluir gerente.');

echo "OK: distribucion historica calculada desde abonos acumulados.\n";
