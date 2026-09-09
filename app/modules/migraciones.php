<?php

require_once __DIR__.'/../core/migrations.php';

require_login();
if (!user_is_super()) {
  flash("<div class='alert alert-danger'>Solo un superadministrador puede acceder al Centro de migraciones.</div>");
  redirect('home.index');
}

function migraciones_csrf_or_redirect(string $route = 'migraciones.index'): void {
  try {
    csrf_verify();
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-danger'>La sesion de seguridad vencio. Intenta nuevamente.</div>");
    redirect($route);
  }
}

function migraciones_format_bytes(int $bytes): string {
  if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
  if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
  if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
  return $bytes.' B';
}

function migraciones_status(array $migration, ?array $history): array {
  if (!$history) return ['Pendiente', 'warning'];
  if (($history['estado'] ?? '') === 'completada') {
    if (hash_equals((string)$history['checksum'], (string)$migration['checksum'])) return ['Aplicada', 'success'];
    return ['Bloqueada por integridad', 'danger'];
  }
  if (($history['estado'] ?? '') === 'fallida') return ['Fallida', 'danger'];
  return ['En ejecucion', 'info'];
}

function migraciones_render_index(?array $queryResult = null, string $querySql = ''): void {
  $ready = migrations_tables_ready();
  $dbInfo = qone('SELECT DATABASE() base,VERSION() version,NOW() fecha_servidor');
  if (!$ready) {
    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h4 mb-1">Centro de migraciones</h1>
        <div class="text-muted small">Base <?= e($dbInfo['base'] ?? '-') ?> · <?= e($dbInfo['version'] ?? '-') ?></div>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="?r=maintenance.schema_repair">Mantenimiento</a>
    </div>
    <div class="alert alert-warning">El centro aun no tiene sus tablas de control. Inicializarlo no altera las tablas operativas del sistema.</div>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6">Inicializar control y auditoria</h2>
        <form method="post" action="?r=migraciones.inicializar" class="row g-3" autocomplete="off">
          <?= csrf_field() ?>
          <div class="col-md-5">
            <label class="form-label" for="credencial_actual">Confirma tu contrasena</label>
            <input class="form-control" type="password" name="credencial_actual" id="credencial_actual" required autocomplete="current-password">
          </div>
          <div class="col-md-5">
            <label class="form-label" for="confirmacion">Escribe INICIALIZAR</label>
            <input class="form-control" name="confirmacion" id="confirmacion" required autocomplete="off">
          </div>
          <div class="col-12"><button class="btn btn-primary">Inicializar centro</button></div>
        </form>
      </div>
    </div>
    <?php
    render('Centro de migraciones', ob_get_clean());
  }

  migrations_ensure_private_dirs();
  $migrations = migrations_list();
  $historyByFile = migrations_history_by_file();
  $history = migrations_recent_history(30);
  $audit = migrations_recent_audit(30);

  ob_start(); ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Centro de migraciones</h1>
      <div class="text-muted small">Base <?= e($dbInfo['base'] ?? '-') ?> · <?= e($dbInfo['version'] ?? '-') ?> · <?= e($dbInfo['fecha_servidor'] ?? '-') ?></div>
    </div>
    <div class="btn-group btn-group-sm" role="group">
      <a class="btn btn-outline-secondary" href="?r=maintenance.schema_repair">Esquema</a>
      <a class="btn btn-outline-secondary" href="?r=maintenance.perf_report">Rendimiento</a>
    </div>
  </div>

  <div class="alert alert-danger py-2"><strong>Produccion:</strong> una migracion puede confirmar cambios estructurales aunque una sentencia posterior falle. El respaldo previo es obligatorio.</div>

  <section class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 m-0">Migraciones disponibles</h2>
      <span class="badge text-bg-secondary"><?= count($migrations) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead><tr><th>Archivo</th><th>Estado</th><th>Sentencias</th><th>Tablas</th><th>Riesgo</th><th class="text-end">Accion</th></tr></thead>
        <tbody>
        <?php foreach ($migrations as $migration):
          $latest = $historyByFile[$migration['filename']] ?? null;
          [$status, $statusClass] = migraciones_status($migration, $latest);
        ?>
          <tr>
            <td><div class="fw-semibold"><?= e($migration['name']) ?></div><code class="small"><?= e($migration['filename']) ?></code></td>
            <td><span class="badge text-bg-<?= e($statusClass) ?>"><?= e($status) ?></span></td>
            <td><?= count($migration['statements']) ?><div class="text-muted small"><?= e(migraciones_format_bytes((int)$migration['size'])) ?></div></td>
            <td class="small"><?= e(implode(', ', array_slice($migration['tables'], 0, 5)) ?: 'Sin tablas existentes') ?><?= count($migration['tables']) > 5 ? '…' : '' ?></td>
            <td class="small"><?= e(implode(', ', $migration['risks']) ?: 'Bajo') ?></td>
            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="?r=migraciones.validar&amp;archivo=<?= rawurlencode($migration['filename']) ?>">Revisar</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($migrations)): ?><tr><td colspan="6" class="text-center text-muted py-3">No hay migraciones preparadas.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6">Preparar migracion SQL</h2>
          <form method="post" action="?r=migraciones.guardar">
            <?= csrf_field() ?>
            <div class="mb-2">
              <label class="form-label" for="nombre">Nombre</label>
              <input class="form-control" name="nombre" id="nombre" required maxlength="120">
            </div>
            <div class="mb-3">
              <label class="form-label" for="sql_migracion">SQL versionado</label>
              <textarea class="form-control font-monospace" name="sql" id="sql_migracion" rows="9" required spellcheck="false"></textarea>
            </div>
            <button class="btn btn-primary">Guardar para revision</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6">Consulta de diagnostico</h2>
          <form method="post" action="?r=migraciones.consulta">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="sql_consulta">SELECT, SHOW, DESCRIBE o EXPLAIN</label>
              <textarea class="form-control font-monospace" name="sql" id="sql_consulta" rows="9" required spellcheck="false"><?= e($querySql) ?></textarea>
            </div>
            <button class="btn btn-outline-primary">Ejecutar consulta</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <?php if ($queryResult !== null): ?>
    <section class="mb-4">
      <h2 class="h6">Resultado de consulta</h2>
      <div class="table-responsive border">
        <table class="table table-sm table-striped mb-0">
          <thead><tr><?php foreach ($queryResult['columns'] as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($queryResult['rows'] as $row): ?><tr><?php foreach ($queryResult['columns'] as $column): ?><td><?= e($row[$column] ?? null) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          <?php if (empty($queryResult['rows'])): ?><tr><td colspan="<?= max(1, count($queryResult['columns'])) ?>" class="text-center text-muted">Sin resultados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($queryResult['truncated'])): ?><div class="text-muted small mt-1">Resultado limitado a 200 filas.</div><?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="mb-4">
    <h2 class="h6">Ejecuciones recientes</h2>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead><tr><th>Fecha</th><th>Archivo</th><th>Estado</th><th>Usuario</th><th>Progreso</th><th>Respaldo</th></tr></thead>
        <tbody>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= e($row['fecha_inicio']) ?></td><td><code><?= e($row['archivo']) ?></code></td>
            <td><?= e($row['estado']) ?><?php if (!empty($row['error'])): ?><div class="text-danger small"><?= e($row['error']) ?></div><?php endif; ?></td>
            <td><?= e($row['usuario']) ?></td><td><?= (int)$row['sentencias_ejecutadas'] ?>/<?= (int)$row['sentencias_total'] ?></td>
            <td><?php if (!empty($row['respaldo'])): ?><a href="?r=migraciones.descargar_respaldo&amp;archivo=<?= rawurlencode($row['respaldo']) ?>&amp;_token=<?= rawurlencode(csrf_token()) ?>">Descargar</a><?php else: ?>-<?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($history)): ?><tr><td colspan="6" class="text-center text-muted">Sin ejecuciones.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <details>
    <summary class="h6">Auditoria reciente</summary>
    <div class="table-responsive mt-2">
      <table class="table table-sm table-striped"><thead><tr><th>Fecha</th><th>Accion</th><th>Usuario</th><th>Resultado</th><th>Detalle</th></tr></thead><tbody>
      <?php foreach ($audit as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['accion']) ?></td><td><?= e($row['usuario']) ?></td><td><?= $row['exito'] ? 'Correcto' : 'Error' ?></td><td><?= e($row['detalle']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </div>
  </details>
  <?php
  render('Centro de migraciones', ob_get_clean());
}

$action = $action ?? 'index';

switch ($action) {
case 'inicializar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('migraciones.index');
  migraciones_csrf_or_redirect();
  if (trim((string)($_POST['confirmacion'] ?? '')) !== 'INICIALIZAR') {
    flash("<div class='alert alert-warning'>La frase de confirmacion no coincide.</div>");
    redirect('migraciones.index');
  }
  if (!migrations_verify_password((string)($_POST['credencial_actual'] ?? ''))) {
    flash("<div class='alert alert-danger'>La contrasena no es correcta.</div>");
    redirect('migraciones.index');
  }
  migrations_initialize();
  flash("<div class='alert alert-success'>Centro de migraciones inicializado.</div>");
  redirect('migraciones.index');

case 'guardar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('migraciones.index');
  migraciones_csrf_or_redirect();
  try {
    $migration = migrations_save_pending((string)($_POST['nombre'] ?? ''), (string)($_POST['sql'] ?? ''));
    flash("<div class='alert alert-success'>Migracion guardada para revision. Aun no se ejecuto SQL.</div>");
    redirect('migraciones.validar&archivo='.rawurlencode($migration['filename']));
  } catch (Throwable $e) {
    migrations_audit('preparar', null, null, false, $e->getMessage());
    flash("<div class='alert alert-danger'>No se pudo preparar la migracion: ".e($e->getMessage())."</div>");
    redirect('migraciones.index');
  }

case 'validar':
  if (!migrations_tables_ready()) redirect('migraciones.index');
  try {
    $migration = migrations_get((string)($_GET['archivo'] ?? ''));
    $estimates = migrations_table_estimates($migration['tables']);
    $history = migrations_history_by_file()[$migration['filename']] ?? null;
    [$status, $statusClass] = migraciones_status($migration, $history);
  } catch (Throwable $e) {
    flash("<div class='alert alert-danger'>No fue posible validar: ".e($e->getMessage())."</div>");
    redirect('migraciones.index');
  }
  ob_start(); ?>
  <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
    <div><h1 class="h4 mb-1"><?= e($migration['name']) ?></h1><code><?= e($migration['filename']) ?></code></div>
    <a class="btn btn-outline-secondary btn-sm" href="?r=migraciones.index">Volver</a>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="border p-3 h-100"><div class="text-muted small">Estado</div><span class="badge text-bg-<?= e($statusClass) ?>"><?= e($status) ?></span></div></div>
    <div class="col-md-3"><div class="border p-3 h-100"><div class="text-muted small">Sentencias</div><strong><?= count($migration['statements']) ?></strong></div></div>
    <div class="col-md-3"><div class="border p-3 h-100"><div class="text-muted small">Checksum</div><code><?= e(substr($migration['checksum'], 0, 16)) ?></code></div></div>
    <div class="col-md-3"><div class="border p-3 h-100"><div class="text-muted small">Riesgos</div><span><?= e(implode(', ', $migration['risks']) ?: 'Bajo') ?></span></div></div>
  </div>
  <?php if ($migration['description']): ?><p><?= e($migration['description']) ?></p><?php endif; ?>
  <h2 class="h6">Tablas incluidas en el respaldo</h2>
  <div class="table-responsive mb-3"><table class="table table-sm table-striped"><thead><tr><th>Tabla</th><th>Existe</th><th>Filas estimadas</th><th>Tamano estimado</th></tr></thead><tbody>
  <?php foreach ($migration['tables'] as $table): $info = $estimates[$table] ?? null; ?>
    <tr><td><code><?= e($table) ?></code></td><td><?= $info ? 'Si' : 'Nueva' ?></td><td><?= $info ? number_format((int)$info['TABLE_ROWS']) : '-' ?></td><td><?= $info ? e(migraciones_format_bytes((int)$info['DATA_LENGTH'] + (int)$info['INDEX_LENGTH'])) : '-' ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($migration['tables'])): ?><tr><td colspan="4" class="text-center text-muted">No se detectaron tablas.</td></tr><?php endif; ?>
  </tbody></table></div>
  <details class="mb-3"><summary class="h6">Revisar SQL completo</summary><pre class="border bg-light p-3 mt-2 overflow-auto" style="max-height:420px"><code><?= e($migration['sql']) ?></code></pre></details>

  <?php $canExecute = $status === 'Pendiente' || $status === 'Fallida'; ?>
  <?php if ($canExecute): ?>
    <div class="border border-danger p-3">
      <h2 class="h6 text-danger">Confirmar ejecucion</h2>
      <form method="post" action="?r=migraciones.ejecutar" class="row g-3" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="archivo" value="<?= e($migration['filename']) ?>">
        <div class="col-md-5"><label class="form-label" for="credencial_actual">Contrasena</label><input class="form-control" type="password" name="credencial_actual" id="credencial_actual" required autocomplete="current-password"></div>
        <div class="col-md-7"><label class="form-label" for="confirmacion">Escribe EJECUTAR <?= e($migration['filename']) ?></label><input class="form-control" name="confirmacion" id="confirmacion" required autocomplete="off"></div>
        <div class="col-12"><button class="btn btn-danger">Generar respaldo y ejecutar</button></div>
      </form>
    </div>
  <?php else: ?>
    <div class="alert alert-<?= e($statusClass) ?>">Esta migracion no puede ejecutarse en su estado actual.</div>
  <?php endif; ?>
  <?php
  render('Validar migracion', ob_get_clean());

case 'ejecutar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('migraciones.index');
  migraciones_csrf_or_redirect();
  $filename = (string)($_POST['archivo'] ?? '');
  $expected = 'EJECUTAR '.$filename;
  if (!hash_equals($expected, trim((string)($_POST['confirmacion'] ?? '')))) {
    flash("<div class='alert alert-warning'>La frase de confirmacion no coincide.</div>");
    redirect('migraciones.validar&archivo='.rawurlencode($filename));
  }
  if (!migrations_verify_password((string)($_POST['credencial_actual'] ?? ''))) {
    migrations_audit('autorizacion', ['filename' => $filename, 'checksum' => null], null, false, 'Contrasena incorrecta.');
    flash("<div class='alert alert-danger'>La contrasena no es correcta.</div>");
    redirect('migraciones.validar&archivo='.rawurlencode($filename));
  }
  try {
    $migration = migrations_get($filename);
    $result = migrations_execute($migration);
    $download = '?r=migraciones.descargar_respaldo&amp;archivo='.rawurlencode($result['backup']).'&amp;_token='.rawurlencode(csrf_token());
    flash("<div class='alert alert-success'>Migracion completada. Se ejecutaron ".(int)$result['executed']." sentencias. <a href='".$download."'>Descargar respaldo previo</a>.</div>");
  } catch (Throwable $e) {
    flash("<div class='alert alert-danger'>La migracion no termino correctamente: ".e($e->getMessage())." Revisa la auditoria y el respaldo antes de reintentar.</div>");
  }
  redirect('migraciones.index');

case 'consulta':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('migraciones.index');
  migraciones_csrf_or_redirect();
  $sql = trim((string)($_POST['sql'] ?? ''));
  try {
    $result = migrations_run_read_only($sql, 200);
    migraciones_render_index($result, $sql);
  } catch (Throwable $e) {
    migrations_audit('consulta', null, null, false, $e->getMessage());
    flash("<div class='alert alert-danger'>Consulta rechazada: ".e($e->getMessage())."</div>");
    redirect('migraciones.index');
  }

case 'descargar_respaldo':
  try {
    csrf_verify();
  } catch (RuntimeException $e) {
    http_response_code(403);
    exit('Acceso denegado.');
  }
  $filename = basename((string)($_GET['archivo'] ?? ''));
  $registered = qone('SELECT id_migracion FROM sistema_migraciones WHERE respaldo=? LIMIT 1', [$filename]);
  $path = $registered ? migrations_backup_path($filename) : null;
  if ($path === null) {
    http_response_code(404);
    exit('Respaldo no encontrado.');
  }
  migrations_audit('descargar_respaldo', ['filename' => $filename, 'checksum' => null], (int)$registered['id_migracion'], true, 'Respaldo descargado.');
  header('Content-Type: application/sql; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.str_replace('"', '', $filename).'"');
  header('Content-Length: '.filesize($path));
  header('X-Content-Type-Options: nosniff');
  readfile($path);
  exit;

case 'index':
default:
  migraciones_render_index();
}
