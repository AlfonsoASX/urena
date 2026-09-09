<?php

require_once __DIR__.'/db.php';

function perf_schema_bootstrap(bool $force = false): void {
  static $ready = false;
  static $suppressed = false;
  if ($ready) return;
  if ($suppressed && !$force) return;
  if (!$force && !db_schema_bootstrap_on_request()) {
    $suppressed = true;
    return;
  }

  db_add_index_if_missing('servicios', 'idx_servicios_estado_fecha', 'INDEX idx_servicios_estado_fecha (eliminado, estatus, fecha_hora_programada, created_at)');
  if (db_column_exists('servicios', 'cerrado')) {
    db_add_index_if_missing('servicios', 'idx_servicios_cerrado_fecha', 'INDEX idx_servicios_cerrado_fecha (eliminado, cerrado, fecha_hora_programada, created_at)');
  }
  db_add_index_if_missing('servicio_fallecido', 'idx_servicio_fallecido_servicio_fallecido', 'INDEX idx_servicio_fallecido_servicio_fallecido (id_servicio, id_fallecido)');
  db_add_index_if_missing('servicio_pagos', 'idx_servicio_pagos_servicio_fecha', 'INDEX idx_servicio_pagos_servicio_fecha (id_servicio, fecha_pago)');
  db_add_index_if_missing('servicio_bitacora', 'idx_servicio_bitacora_servicio_fecha', 'INDEX idx_servicio_bitacora_servicio_fecha (id_servicio, created_at)');
  db_add_index_if_missing('articulos', 'idx_articulos_existencias', 'INDEX idx_articulos_existencias (eliminado, existencias)');
  db_add_index_if_missing('equipos', 'idx_equipos_estado', 'INDEX idx_equipos_estado (eliminado, estatus)');
  db_add_index_if_missing('futuro_abonos', 'idx_futuro_abonos_fecha_registro', 'INDEX idx_futuro_abonos_fecha_registro (fecha_registro)');
  db_add_index_if_missing('futuro_comision_semanal', 'idx_futuro_comision_semanal_estatus', 'INDEX idx_futuro_comision_semanal_estatus (estatus)');
  db_add_index_if_missing('futuro_gestion', 'idx_futuro_gestion_fecha_visita', 'INDEX idx_futuro_gestion_fecha_visita (fecha_proxima_visita)');

  if (db_column_exists('cajas', 'estatus_logico') && db_column_exists('cajas', 'disponible')) {
    db_add_index_if_missing('cajas', 'idx_cajas_estatus_disponible', 'INDEX idx_cajas_estatus_disponible (estatus_logico, disponible)');
  } elseif (db_column_exists('cajas', 'estado')) {
    db_add_index_if_missing('cajas', 'idx_cajas_estado', 'INDEX idx_cajas_estado (eliminado, estado)');
  }

  $ready = true;
  $suppressed = false;
}

function perf_schema_repair_now(): void {
  perf_schema_bootstrap(true);
}

db_register_schema_repair('performance_schema', 'perf_schema_repair_now');
