<?php

require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/api.php';
require_once __DIR__.'/../core/inventario_v2.php';
require_once __DIR__.'/../core/servicios_operativos.php';

api_require_login();
api_require_role(['administradora', 'auxiliar', 'coordinador', 'vendedor', 'caja']);

$action = $action ?? 'disponibilidad';

if ($action !== 'disponibilidad') {
  api_error(404, 'Endpoint no encontrado.');
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
  api_error(405, 'Metodo no permitido.');
}

$tipo = trim((string)($_GET['tipo'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 0);
$limit = $limit > 0 ? $limit : null;
$agrupado = !empty($_GET['agrupado']) || trim((string)($_GET['modo'] ?? '')) === 'grupos';
$grupo = trim((string)($_GET['grupo'] ?? ''));

if ($tipo !== '') {
  if (!in_array($tipo, ['articulos', 'cajas', 'equipos'], true)) {
    api_error(400, 'Tipo de inventario invalido.');
  }
  if ($tipo === 'equipos') {
    try {
      $items = iv2_disponibilidad_equipos($query, $limit, $agrupado, $grupo ?: null);
      $state = ['items' => $items, 'error' => null];
    } catch (Throwable $e) {
      db_log_error($e, ['scope' => 'inventario_api', 'tipo' => 'equipos']);
      $state = ['items' => [], 'error' => 'No se pudieron cargar los equipos disponibles.'];
    }
  } else {
    $state = so_inventory_section_state($tipo, $query, $limit);
  }
  api_success([
    'tipo' => $tipo,
    'query' => $query,
    'agrupado' => $agrupado,
    'grupo' => $grupo,
    'items' => $state['items'],
    'error' => $state['error'],
  ], $state['error'] ? 'Disponibilidad parcial obtenida.' : 'Disponibilidad obtenida correctamente.');
}

$articulos = so_inventory_section_state('articulos');
$cajas = so_inventory_section_state('cajas');
$equipos = so_inventory_section_state('equipos');

api_success([
  'articulos' => $articulos['items'],
  'cajas' => $cajas['items'],
  'equipos' => $equipos['items'],
  'errors' => array_filter([
    'articulos' => $articulos['error'],
    'cajas' => $cajas['error'],
    'equipos' => $equipos['error'],
  ]),
], 'Disponibilidad obtenida correctamente.');
