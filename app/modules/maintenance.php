<?php

require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/inventario_schema.php';
require_once __DIR__.'/../core/inventario_v2.php';
require_once __DIR__.'/../core/servicios_operativos.php';
require_once __DIR__.'/../core/performance_schema.php';

require_login();
require_role(['administradora']);

$action = $action ?? 'perf_report';

switch ($action) {
case 'schema_repair':
  if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
      csrf_verify();
    } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesion invalida.</div>");
      redirect('maintenance.schema_repair');
    }

    $started = microtime(true);
    try {
      inventario_schema_bootstrap(true);
      iv2_bootstrap(true);
      so_bootstrap(true);
      perf_schema_bootstrap(true);
      db_schema_snapshot_refresh();
      $elapsed = round((microtime(true) - $started) * 1000, 2);
      flash("<div class='alert alert-success'>Reparacion de esquema completada en {$elapsed} ms.</div>");
    } catch (Throwable $e) {
      flash("<div class='alert alert-danger'>No se pudo completar la reparacion: ".e($e->getMessage())."</div>");
    }
    redirect('maintenance.schema_repair');
  }

  $snapshotPath = db_schema_snapshot_path();
  $snapshotInfo = is_file($snapshotPath) ? @include $snapshotPath : null;
  $generatedAt = is_array($snapshotInfo) ? ($snapshotInfo['generated_at'] ?? null) : null;

  ob_start(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Mantenimiento de esquema</h1>
    <a class="btn btn-outline-secondary btn-sm" href="?r=maintenance.perf_report">Ver rendimiento</a>
  </div>
  <div class="card shadow-sm">
    <div class="card-body">
      <p class="text-muted">Esta accion corre las reparaciones no destructivas, actualiza indices y regenera el snapshot de esquema para acelerar requests normales.</p>
      <dl class="row">
        <dt class="col-sm-3">Modo actual</dt><dd class="col-sm-9"><?= e(db_schema_bootstrap_mode()) ?></dd>
        <dt class="col-sm-3">Snapshot</dt><dd class="col-sm-9"><?= e($generatedAt ?: 'No generado') ?></dd>
        <dt class="col-sm-3">Archivo</dt><dd class="col-sm-9"><code><?= e($snapshotPath) ?></code></dd>
      </dl>
      <form method="post" action="?r=maintenance.schema_repair">
        <?= csrf_field() ?>
        <button class="btn btn-primary">Ejecutar reparacion</button>
      </form>
    </div>
  </div>
  <?php
  render('Mantenimiento de esquema', ob_get_clean());
  break;

case 'perf_report':
default:
  $entries = db_perf_recent(150);
  $requests = array_values(array_filter($entries, fn($row) => ($row['type'] ?? '') === 'perf_request'));
  $queries = array_values(array_filter($entries, fn($row) => ($row['type'] ?? '') === 'perf_query'));
  $avgTotal = 0;
  if (!empty($requests)) {
    $avgTotal = array_sum(array_map(fn($row) => (float)($row['total_ms'] ?? 0), $requests)) / count($requests);
  }

  ob_start(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Resumen de rendimiento</h1>
    <a class="btn btn-outline-secondary btn-sm" href="?r=maintenance.schema_repair">Reparar esquema</a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Requests lentos recientes</div>
        <div class="fs-4 fw-semibold"><?= count($requests) ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Promedio request lento</div>
        <div class="fs-4 fw-semibold"><?= e(number_format($avgTotal, 2)) ?> ms</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Queries lentas recientes</div>
        <div class="fs-4 fw-semibold"><?= count($queries) ?></div>
      </div></div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h6">Requests lentos</h2>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead><tr><th>Fecha</th><th>Ruta</th><th>Total</th><th>Connect</th><th>Queries</th><th>SQL</th><th>Slowest</th></tr></thead>
          <tbody>
            <?php foreach ($requests as $row): ?>
            <tr>
              <td><?= e($row['ts'] ?? '') ?></td>
              <td><?= e($row['route'] ?? '') ?></td>
              <td><?= e(number_format((float)($row['total_ms'] ?? 0), 2)) ?> ms</td>
              <td><?= e(number_format((float)($row['connect_ms'] ?? 0), 2)) ?> ms</td>
              <td><?= (int)($row['query_count'] ?? 0) ?></td>
              <td><?= e(number_format((float)($row['sql_ms'] ?? 0), 2)) ?> ms</td>
              <td><?= e(number_format((float)($row['slowest_query_ms'] ?? 0), 2)) ?> ms</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?>
            <tr><td colspan="7" class="text-center text-muted">No hay registros recientes.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h6">Queries lentas</h2>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead><tr><th>Fecha</th><th>Ruta</th><th>Tiempo</th><th>SQL</th></tr></thead>
          <tbody>
            <?php foreach ($queries as $row): ?>
            <tr>
              <td><?= e($row['ts'] ?? '') ?></td>
              <td><?= e($row['route'] ?? '') ?></td>
              <td><?= e(number_format((float)($row['query_ms'] ?? 0), 2)) ?> ms</td>
              <td><code><?= e($row['sql'] ?? '') ?></code></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($queries)): ?>
            <tr><td colspan="4" class="text-center text-muted">No hay queries lentas recientes.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php
  render('Resumen de rendimiento', ob_get_clean());
}
