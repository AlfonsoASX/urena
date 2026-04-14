<?php
session_start();
$cfg = require __DIR__.'/../config/.env.php';
$GLOBALS['cfg'] = $cfg;

require __DIR__.'/db.php';
require __DIR__.'/inventario_schema.php';
require __DIR__.'/helpers.php';
require __DIR__.'/ui.php';
require __DIR__.'/auth.php';

inventario_schema_bootstrap();

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
