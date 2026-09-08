<?php
date_default_timezone_set('America/Mexico_City');

session_start();
$cfg = require __DIR__.'/../config/.env.php';
$GLOBALS['cfg'] = $cfg;

require __DIR__.'/db.php';
require __DIR__.'/inventario_schema.php';
require __DIR__.'/performance_schema.php';
require __DIR__.'/helpers.php';
require __DIR__.'/ui.php';
require __DIR__.'/auth.php';
require __DIR__.'/usuarios_directorio.php';
require __DIR__.'/futuros_promociones.php';
require __DIR__.'/cortes_cobradores.php';
require __DIR__.'/api.php';
require __DIR__.'/inventario_v2.php';
require __DIR__.'/servicios_operativos.php';

db_perf_boot();
so_ensure_runtime_schema_compat();

// Siempre registrar el repair de auth para que db_prepare_execute pueda
// auto-corregir columnas faltantes (ej. puesto) aunque el modo sea ondemand.
db_register_schema_repair('auth', fn() => auth_bootstrap(true));
db_register_schema_repair('futuros_promociones', fn() => futuros_promociones_ensure_schema(true));

if (db_schema_bootstrap_on_request()) {
  auth_bootstrap(true);
  futuros_promociones_ensure_schema(true);
  inventario_schema_bootstrap(true);
  iv2_bootstrap(true);
  so_bootstrap(true);
  perf_schema_bootstrap(true);
  try {
    db_schema_snapshot_refresh();
  } catch (Throwable $e) {
    db_log_error($e, ['scope' => 'schema_snapshot_refresh_boot']);
  }
}

register_shutdown_function(function () {
  db_perf_log_request();
});

set_exception_handler(function (Throwable $e) {
  db_rollback_open_transaction();
  db_log_error($e, [
    'route' => $_GET['r'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
  ]);

  $message = db_user_error_message($e);
  $route = $_GET['r'] ?? 'home.index';
  $target = $route === 'home.index' ? null : 'home.index';

  if (PHP_SAPI === 'cli') {
    fwrite(STDERR, $message.PHP_EOL);
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    return;
  }

  if (function_exists('api_is_request') && api_is_request()) {
    $payload = [
      'ok' => false,
      'message' => $message,
    ];
    if (!empty($GLOBALS['cfg']['debug'])) {
      $payload['debug'] = ['error' => $e->getMessage()];
    }
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if (!headers_sent() && function_exists('flash')) {
    flash("<div class='alert alert-danger'>".e($message)."</div>");
    if ($target) {
      header("Location: ?r={$target}");
      exit;
    }
  }

  http_response_code(500);
  echo "<div style='font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:16px;border:1px solid #f1b7b7;background:#fff5f5;color:#7a1111;border-radius:8px'>";
  echo "<h1 style='margin-top:0;font-size:20px'>Error controlado</h1>";
  echo "<p>".e($message)."</p>";
  if (!empty($GLOBALS['cfg']['debug'])) {
    echo "<pre style='white-space:pre-wrap;background:#fff;border:1px solid #e5c1c1;padding:12px;border-radius:6px'>".e($e->getMessage())."</pre>";
  }
  echo "</div>";
});
