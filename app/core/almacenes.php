<?php

require_once __DIR__.'/db.php';
require_once __DIR__.'/inventario_schema.php';
require_once __DIR__.'/helpers.php';

function almacenes_ensure_schema(): void {
  inventario_schema_bootstrap(true);
}

function almacenes_activos(?int $includeId = null): array {
  almacenes_ensure_schema();
  $params = [];
  $where = ["COALESCE(eliminado,0)=0"];
  if ($includeId !== null && $includeId > 0) {
    $where[] = "(COALESCE(activo,1)=1 OR id_almacen=?)";
    $params[] = $includeId;
  } else {
    $where[] = "COALESCE(activo,1)=1";
  }
  try {
    return qall(
      "SELECT id_almacen, codigo, nombre, descripcion, activo
       FROM almacenes
       WHERE ".implode(' AND ', $where)."
       ORDER BY nombre ASC",
      $params
    );
  } catch (Throwable $e) {
    return [];
  }
}

function almacenes_options(?int $includeId = null, string $placeholder = 'Sin almacen'): array {
  $options = ['' => $placeholder];
  foreach (almacenes_activos($includeId) as $row) {
    $label = trim((string)($row['nombre'] ?? ''));
    $codigo = trim((string)($row['codigo'] ?? ''));
    if ($codigo !== '') {
      $label .= ' ('.$codigo.')';
    }
    if (empty($row['activo'])) {
      $label .= ' - inactivo';
    }
    $options[(string)$row['id_almacen']] = $label;
  }
  return $options;
}

function almacen_find(?int $id, bool $soloActivo = false): ?array {
  almacenes_ensure_schema();
  $id = (int)$id;
  if ($id <= 0) return null;
  $where = "id_almacen=? AND COALESCE(eliminado,0)=0";
  if ($soloActivo) {
    $where .= " AND COALESCE(activo,1)=1";
  }
  try {
    return qone("SELECT * FROM almacenes WHERE {$where} LIMIT 1", [$id]);
  } catch (Throwable $e) {
    return null;
  }
}

function almacen_nombre(?int $id): ?string {
  static $cache = [];
  $id = (int)$id;
  if ($id <= 0) return null;
  if (array_key_exists($id, $cache)) return $cache[$id];
  $row = almacen_find($id, false);
  $cache[$id] = $row ? trim((string)$row['nombre']) : null;
  return $cache[$id];
}

function almacen_resolver_post(string $field = 'id_almacen', bool $required = false): array {
  $id = (int)($_POST[$field] ?? 0);
  if ($id <= 0) {
    if ($required) {
      throw new RuntimeException('Selecciona un almacen valido.');
    }
    return [null, null];
  }
  $row = almacen_find($id, true);
  if (!$row) {
    throw new RuntimeException('Selecciona un almacen activo.');
  }
  return [(int)$row['id_almacen'], trim((string)$row['nombre'])];
}

function almacen_update_item_location(string $tabla, string $pkColumn, $pkValue, ?int $idAlmacen, ?string $nombreAlmacen = null, ?string $legacyColumn = null): void {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla) || !preg_match('/^[a-zA-Z0-9_]+$/', $pkColumn)) {
    return;
  }
  if (!db_table_exists($tabla)) return;

  $set = [];
  $params = [];
  if (db_column_exists($tabla, 'id_almacen')) {
    $set[] = 'id_almacen=?';
    $params[] = $idAlmacen;
  }
  if ($legacyColumn && preg_match('/^[a-zA-Z0-9_]+$/', $legacyColumn) && db_column_exists($tabla, $legacyColumn)) {
    $set[] = "{$legacyColumn}=?";
    $params[] = $nombreAlmacen ?: null;
  }
  if (db_column_exists($tabla, 'updated_at')) {
    $set[] = 'updated_at=CURRENT_TIMESTAMP()';
  }
  if (empty($set)) return;
  $params[] = $pkValue;
  q("UPDATE {$tabla} SET ".implode(', ', $set)." WHERE {$pkColumn}=?", $params);
}
