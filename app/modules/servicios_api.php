<?php

require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/api.php';
require_once __DIR__.'/../core/inventario_v2.php';
require_once __DIR__.'/../core/servicios_operativos.php';

function servicios_api_status_from_exception(RuntimeException $e): int {
  $message = strtolower($e->getMessage());
  foreach (['no encontrado', 'no existe'] as $needle) {
    if (strpos($message, $needle) !== false) {
      return 404;
    }
  }
  return 409;
}

function servicios_api_servicio(int $idServicio): array {
  $svc = so_servicio_fetch($idServicio);
  if (!$svc) {
    api_error(404, 'Servicio no encontrado.');
  }
  return $svc;
}

function servicios_api_snapshot(int $idServicio): array {
  $svc = so_servicio_fetch($idServicio);
  return [
    'servicio' => [
      'id_servicio' => $idServicio,
      'folio' => $svc['folio'] ?? '',
      'fallecido' => $svc['fallecido'] ?? '',
      'estatus' => $svc['estatus_norm'] ?? ($svc['estatus'] ?? ''),
    ],
    'asignacion' => so_servicio_asignacion($idServicio) ?? [],
    'cajas_asignadas' => so_servicio_cajas_asignadas($idServicio),
    'equipos_asignados' => so_servicio_equipos_asignados($idServicio),
    'equipos_asignados_grupo' => so_servicio_equipos_asignados_grupo($idServicio),
    'articulos_asignados' => so_servicio_articulos_asignados($idServicio),
    'pendientes_cajas' => so_pendientes_devolucion_cajas($idServicio),
    'pendientes_equipos' => so_pendientes_devolucion_equipos($idServicio),
  ];
}

function servicios_api_string_list($value): array {
  if (!is_array($value)) return [];
  return array_values(array_unique(array_filter(array_map(static fn($item): string => trim((string)$item), $value))));
}

function servicios_api_error(Throwable $e, string $action, string $message): void {
  db_log_error($e, ['scope' => 'servicios_api', 'action' => $action]);
  api_error(500, $message);
}

api_require_login();
api_require_role(['administradora', 'auxiliar', 'coordinador', 'vendedor', 'caja']);

$action = $action ?? 'consumos';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$payload = api_payload();
api_verify_csrf_if_present();

if ($method !== 'POST') {
  api_error(405, 'Metodo no permitido.');
}

switch ($action) {
case 'guardar_asignacion':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  if ($idServicio <= 0) {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);
  try {
    $data = [
      'fecha_hora_inicio' => trim((string)($payload['fecha_hora_inicio'] ?? '')) ?: null,
      'lugar_tipo' => trim((string)($payload['lugar_tipo'] ?? '')) ?: null,
      'lugar_detalle' => trim((string)($payload['lugar_detalle'] ?? '')) ?: null,
      'id_usuario_auxiliar' => (int)($payload['id_usuario_auxiliar'] ?? 0) ?: null,
      'id_vehiculo' => trim((string)($payload['id_vehiculo'] ?? '')) ?: null,
      'id_sala' => trim((string)($payload['id_sala'] ?? '')) ?: null,
      'instrucciones' => trim((string)($payload['instrucciones'] ?? '')) ?: null,
    ];
    so_guardar_asignacion($idServicio, $data);
    so_log_bitacora($idServicio, 'api_asignacion', 'Asignacion actualizada por API', [
      'lugar_tipo' => $data['lugar_tipo'],
      'id_usuario_auxiliar' => $data['id_usuario_auxiliar'],
    ]);
    api_success(servicios_api_snapshot($idServicio), 'Asignacion actualizada.');
  } catch (RuntimeException $e) {
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    servicios_api_error($e, 'guardar_asignacion', 'No se pudo guardar la asignacion.');
  }
  break;

case 'asignar_caja':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $codigo = trim((string)($payload['codigo'] ?? $payload['codigo_caja'] ?? ''));
  $tipoAsignacion = trim((string)($payload['tipo_asignacion'] ?? '')) ?: null;
  $observaciones = trim((string)($payload['notas'] ?? $payload['observaciones'] ?? '')) ?: null;
  if ($idServicio <= 0 || $codigo === '') {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_asignar_caja($pdo, $idServicio, $codigo, $tipoAsignacion, so_current_user_id(), $observaciones);
    so_guardar_codigo_caja_asignacion($idServicio, $codigo);
    so_log_bitacora($idServicio, 'api_asignacion_caja', 'Ataud asignado por API', [
      'codigo' => $codigo,
      'tipo_asignacion' => $resultado['tipo_asignacion'] ?? $tipoAsignacion,
    ]);
    $pdo->commit();
    $snapshot = servicios_api_snapshot($idServicio);
    api_success(array_merge([
      'resultado' => $resultado,
    ], $snapshot), 'Ataud asignado correctamente.');
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, 'asignar_caja', 'No se pudo asignar el ataud.');
  }
  break;

case 'asignar_equipo':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $idEquipo = trim((string)($payload['id_equipo'] ?? ''));
  $idsEquipo = servicios_api_string_list($payload['ids_equipo'] ?? []);
  $grupo = trim((string)($payload['equipo_grupo'] ?? ''));
  $cantidad = (int)($payload['cantidad'] ?? 0);
  $observaciones = trim((string)($payload['notas'] ?? $payload['observaciones'] ?? '')) ?: null;
  if ($idServicio <= 0 || ($idEquipo === '' && empty($idsEquipo) && ($grupo === '' || $cantidad <= 0))) {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);
  $pdo = db();
  try {
    $pdo->beginTransaction();
    if (!empty($idsEquipo)) {
      $resultado = iv2_asignar_equipos($pdo, $idServicio, $idsEquipo, so_current_user_id(), $observaciones);
    } elseif ($grupo !== '' && $cantidad > 0) {
      $resultado = iv2_asignar_equipos_por_grupo($pdo, $idServicio, $grupo, $cantidad, so_current_user_id(), $observaciones);
    } else {
      $resultado = [iv2_asignar_equipo($pdo, $idServicio, $idEquipo, so_current_user_id(), $observaciones)];
    }
    so_log_bitacora($idServicio, 'api_asignacion_equipo', 'Equipo asignado por API', ['equipos' => $resultado]);
    $pdo->commit();
    $snapshot = servicios_api_snapshot($idServicio);
    api_success(array_merge([
      'resultado' => $resultado,
    ], $snapshot), 'Equipo asignado correctamente.');
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, 'asignar_equipo', 'No se pudo asignar el equipo.');
  }
  break;

case 'cancelar_caja':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $codigo = trim((string)($payload['codigo_caja'] ?? $payload['codigo'] ?? '')) ?: null;
  if ($idServicio <= 0) {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_cancelar_caja($pdo, $idServicio, $codigo, so_current_user_id(), trim((string)($payload['observaciones'] ?? '')) ?: 'Cancelacion de asignacion');
    so_log_bitacora($idServicio, 'api_cancelacion_caja', 'Ataud retirado del servicio', $resultado);
    $pdo->commit();
    api_success(array_merge(['resultado' => $resultado], servicios_api_snapshot($idServicio)), 'Ataud retirado del servicio.');
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, 'cancelar_caja', 'No se pudo retirar el ataud del servicio.');
  }
  break;

case 'cancelar_equipos':
case 'liberar_equipos':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $idsEquipo = servicios_api_string_list($payload['ids_equipo'] ?? []);
  if ($idServicio <= 0 || empty($idsEquipo)) {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);
  $tipoEvento = $action === 'liberar_equipos' ? 'devolucion' : 'cancelacion';
  $mensaje = $action === 'liberar_equipos' ? 'Equipos liberados correctamente.' : 'Equipos retirados del servicio.';
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_cancelar_equipos($pdo, $idServicio, $idsEquipo, so_current_user_id(), trim((string)($payload['observaciones'] ?? '')) ?: null, $tipoEvento);
    so_log_bitacora($idServicio, 'api_'.$action, $mensaje, ['equipos' => $resultado]);
    $pdo->commit();
    api_success(array_merge(['resultado' => $resultado], servicios_api_snapshot($idServicio)), $mensaje);
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, $action, 'No se pudieron actualizar los equipos del servicio.');
  }
  break;

case 'consumos':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $items = $payload['items'] ?? [];
  if ($idServicio <= 0 || !is_array($items)) {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $responsable = trim((string)($payload['responsable'] ?? (current_user()['usuario'] ?? 'api'))) ?: null;
    $observaciones = trim((string)($payload['observaciones'] ?? '')) ?: null;
    $resultado = iv2_registrar_consumos($pdo, $idServicio, $items, so_current_user_id(), $responsable, $observaciones);
    $svc = so_servicio_fetch($idServicio);
    $nuevoEstatus = ($svc && ($svc['estatus_norm'] ?? 'registrado') === 'en_proceso') ? 'en_proceso' : 'preparacion';
    iv2_update_servicio_estatus($idServicio, $nuevoEstatus, null, null);
    so_log_bitacora($idServicio, 'api_consumos', 'Consumos registrados por API', ['items' => $resultado]);
    $pdo->commit();
    $snapshot = servicios_api_snapshot($idServicio);
    api_success(array_merge([
      'id_servicio' => $idServicio,
      'items' => $resultado,
      'stock_actualizado' => $resultado,
    ], $snapshot), 'Consumos registrados correctamente.');
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, 'consumos', 'No se pudieron registrar los consumos.');
  }
  break;

case 'cancelar_consumo':
case 'devolucion_articulos':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $idServicioArticulo = (int)($payload['id_servicio_articulo'] ?? 0);
  $cantidad = (int)($payload['cantidad'] ?? 0);
  if ($idServicio <= 0 || $idServicioArticulo <= 0 || $cantidad <= 0) {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);
  $tipoEvento = $action === 'devolucion_articulos' ? 'devolucion' : 'cancelacion';
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_cancelar_consumo_articulo(
      $pdo,
      $idServicio,
      $idServicioArticulo,
      $cantidad,
      so_current_user_id(),
      trim((string)($payload['observaciones'] ?? '')) ?: null,
      $tipoEvento
    );
    so_log_bitacora($idServicio, 'api_'.$action, 'Ajuste de articulos registrado', $resultado);
    $pdo->commit();
    api_success(array_merge(['resultado' => $resultado], servicios_api_snapshot($idServicio)), $tipoEvento === 'devolucion' ? 'Devolucion de articulos registrada.' : 'Consumo cancelado correctamente.');
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, $action, 'No se pudo actualizar el consumo de articulos.');
  }
  break;

case 'devolucion_caja':
  $idServicio = (int)($_GET['id_servicio'] ?? $payload['id_servicio'] ?? 0);
  $codigo = trim((string)($payload['codigo_caja'] ?? ''));
  $estadoDevolucion = trim((string)($payload['estado_devolucion'] ?? 'usable')) ?: 'usable';
  $reciclado = array_key_exists('reciclado', $payload) ? ((int)!empty($payload['reciclado'])) : null;
  $observaciones = trim((string)($payload['observaciones'] ?? '')) ?: null;
  if ($idServicio <= 0 || $codigo === '') {
    api_error(400, 'Payload invalido.');
  }
  servicios_api_servicio($idServicio);

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $resultado = iv2_devolver_caja($pdo, $idServicio, $codigo, $estadoDevolucion, $reciclado, $observaciones, so_current_user_id());
    so_log_bitacora($idServicio, 'api_devolucion_caja', 'Devolucion de caja registrada por API', $resultado);
    $pdo->commit();
    $snapshot = servicios_api_snapshot($idServicio);
    api_success(array_merge([
      'resultado' => $resultado,
    ], $snapshot), 'Devolucion de caja registrada.');
  } catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error(servicios_api_status_from_exception($e), $e->getMessage());
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    servicios_api_error($e, 'devolucion_caja', 'No se pudo registrar la devolucion de la caja.');
  }
  break;

default:
  api_error(404, 'Endpoint no encontrado.');
}
