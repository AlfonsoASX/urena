<?php

require_once __DIR__.'/db.php';

function inventario_caja_materiales(): array {
  return [
    'metalico' => 'Metálico',
    'madera' => 'Madera',
    'tapizado' => 'Tapizado',
  ];
}

function inventario_caja_material_normalizar(?string $material): ?string {
  $material = strtr(trim((string)$material), [
    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
    'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
  ]);
  $material = strtolower($material);
  return array_key_exists($material, inventario_caja_materiales()) ? $material : null;
}

function inventario_caja_material_label(?string $material): string {
  $material = inventario_caja_material_normalizar($material);
  return $material !== null ? inventario_caja_materiales()[$material] : 'Sin especificar';
}

function inventario_schema_bootstrap(bool $force = false): void {
  static $ready = false;
  static $suppressed = false;
  if ($ready) return;
  if ($suppressed && !$force) return;
  if (!$force && !db_schema_bootstrap_on_request()) {
    $suppressed = true;
    return;
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS almacenes (
        id_almacen INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) DEFAULT NULL,
        nombre VARCHAR(120) NOT NULL,
        descripcion TEXT DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_almacenes_codigo (codigo)
      )
    ");
    db_invalidate_schema_cache('almacenes');
    db_schema_snapshot_clear_file();
  } catch (Throwable $e) {
  }
  foreach ([
    'codigo' => "VARCHAR(40) DEFAULT NULL",
    'nombre' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'descripcion' => "TEXT DEFAULT NULL",
    'activo' => "TINYINT(1) NOT NULL DEFAULT 1",
    'eliminado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('almacenes', $column, $ddl);
  }
  db_add_index_if_missing('almacenes', 'idx_almacenes_activo', 'INDEX idx_almacenes_activo (eliminado, activo, nombre)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        articulo VARCHAR(120) NOT NULL,
        marca VARCHAR(120) DEFAULT NULL,
        categoria VARCHAR(40) DEFAULT NULL,
        existencias INT NOT NULL DEFAULT 0,
        foto VARCHAR(255) DEFAULT NULL,
        id_proveedor INT DEFAULT NULL,
        id_almacen INT DEFAULT NULL,
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'articulo' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'marca' => "VARCHAR(120) DEFAULT NULL",
    'categoria' => "VARCHAR(40) DEFAULT NULL",
    'existencias' => "INT NOT NULL DEFAULT 0",
    'foto' => "VARCHAR(255) DEFAULT NULL",
    'id_proveedor' => "INT DEFAULT NULL",
    'id_almacen' => "INT DEFAULT NULL",
    'eliminado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('articulos', $column, $ddl);
  }
  db_add_index_if_missing('articulos', 'idx_articulos_almacen', 'INDEX idx_articulos_almacen (id_almacen)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS cajas (
        codigo VARCHAR(60) PRIMARY KEY,
        modelo VARCHAR(120) NOT NULL,
        material VARCHAR(20) DEFAULT NULL,
        estado VARCHAR(30) NOT NULL DEFAULT 'nuevo',
        ubicacion VARCHAR(120) DEFAULT NULL,
        color VARCHAR(60) DEFAULT NULL,
        id_proveedor INT DEFAULT NULL,
        id_almacen INT DEFAULT NULL,
        proveedor VARCHAR(120) DEFAULT NULL,
        costo DECIMAL(12,2) NOT NULL DEFAULT 0,
        es_rentado TINYINT(1) NOT NULL DEFAULT 0,
        reciclado TINYINT(1) NOT NULL DEFAULT 0,
        ciclos_uso INT NOT NULL DEFAULT 0,
        notas TEXT DEFAULT NULL,
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'modelo' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'material' => "VARCHAR(20) DEFAULT NULL",
    'estado' => "VARCHAR(30) NOT NULL DEFAULT 'nuevo'",
    'ubicacion' => "VARCHAR(120) DEFAULT NULL",
    'color' => "VARCHAR(60) DEFAULT NULL",
    'id_proveedor' => "INT DEFAULT NULL",
    'id_almacen' => "INT DEFAULT NULL",
    'proveedor' => "VARCHAR(120) DEFAULT NULL",
    'costo' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'es_rentado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'reciclado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'ciclos_uso' => "INT NOT NULL DEFAULT 0",
    'notas' => "TEXT DEFAULT NULL",
    'eliminado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('cajas', $column, $ddl);
  }
  db_add_index_if_missing('cajas', 'idx_cajas_almacen', 'INDEX idx_cajas_almacen (id_almacen)');
  db_add_index_if_missing('cajas', 'idx_cajas_material', 'INDEX idx_cajas_material (material)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS equipos (
        id_equipo VARCHAR(60) PRIMARY KEY,
        equipo VARCHAR(120) NOT NULL,
        estatus VARCHAR(30) NOT NULL DEFAULT 'disponible',
        ubicacion VARCHAR(120) DEFAULT NULL,
        id_almacen INT DEFAULT NULL,
        foto VARCHAR(255) DEFAULT NULL,
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'equipo' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'estatus' => "VARCHAR(30) NOT NULL DEFAULT 'disponible'",
    'ubicacion' => "VARCHAR(120) DEFAULT NULL",
    'id_almacen' => "INT DEFAULT NULL",
    'foto' => "VARCHAR(255) DEFAULT NULL",
    'eliminado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('equipos', $column, $ddl);
  }
  db_add_index_if_missing('equipos', 'idx_equipos_almacen', 'INDEX idx_equipos_almacen (id_almacen)');

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos_traspasos (
        id_traspaso INT AUTO_INCREMENT PRIMARY KEY,
        origen VARCHAR(120) NOT NULL,
        destino VARCHAR(120) NOT NULL,
        id_almacen_origen INT DEFAULT NULL,
        id_almacen_destino INT DEFAULT NULL,
        responsable VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }
  foreach ([
    'origen' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'destino' => "VARCHAR(120) NOT NULL DEFAULT ''",
    'id_almacen_origen' => "INT DEFAULT NULL",
    'id_almacen_destino' => "INT DEFAULT NULL",
    'responsable' => "VARCHAR(120) DEFAULT NULL",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
  ] as $column => $ddl) {
    db_add_column_if_missing('articulos_traspasos', $column, $ddl);
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos_traspaso_det (
        id_det INT AUTO_INCREMENT PRIMARY KEY,
        id_traspaso INT NOT NULL,
        id_articulo INT NOT NULL,
        cantidad INT NOT NULL DEFAULT 0
      )
    ");
  } catch (Throwable $e) {
  }

  $ready = true;
  $suppressed = false;
}

function inventario_schema_repair_now(): void {
  inventario_schema_bootstrap(true);
}

db_register_schema_repair('inventario', 'inventario_schema_repair_now');
