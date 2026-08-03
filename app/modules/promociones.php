<?php
// CRUD de promociones para reportes de futuros.
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/futuros_promociones.php';

require_login();
require_role(['administradora','futuroadmivo']);

$action = $action ?? 'listar';
futuros_promociones_ensure_schema();

function promociones_find(int $id): ?array {
  return qone("SELECT * FROM futuro_mostrar_catalogo_promociones WHERE id_mos_cat_com=?", [$id]);
}

function promociones_post_reglas(): array {
  $tipos = $_POST['tipo_contrato'] ?? [];
  if (!is_array($tipos)) return [];

  $out = [];
  foreach ($tipos as $idx => $tipo) {
    $tipo = trim((string)$tipo);
    if ($tipo === '') continue;
    $out[] = [
      'tipo_contrato' => $tipo,
      'vendedor_desde' => $_POST['vendedor_desde'][$idx] ?? null,
      'vendedor_hasta' => $_POST['vendedor_hasta'][$idx] ?? null,
      'supervisor_desde' => $_POST['supervisor_desde'][$idx] ?? null,
      'supervisor_hasta' => $_POST['supervisor_hasta'][$idx] ?? null,
      'empresa_desde' => $_POST['empresa_desde'][$idx] ?? null,
    ];
  }
  return $out;
}

function promociones_render_form(string $titulo, string $actionUrl, array $promo, array $reglas): string {
  $paquetes = futuros_promociones_paquetes();
  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3"><?= e($titulo) ?></h1>
      <form method="post" action="<?= e($actionUrl) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-3">
            <?= form_input('codigo','Codigo',$promo['codigo'] ?? '', ['required'=>true,'maxlength'=>20]) ?>
          </div>
          <div class="col-md-6">
            <?= form_input('concepto','Concepto',$promo['concepto'] ?? '', ['required'=>true,'maxlength'=>250]) ?>
          </div>
          <div class="col-md-3">
            <?= form_select('estatus','Estatus',['1'=>'Activa','0'=>'Inactiva'],(string)($promo['estatus'] ?? 1),['class'=>'form-select']) ?>
          </div>
        </div>

        <div class="alert alert-info small mt-3 mb-3">
          Define rangos consecutivos por numero de pago. Ejemplo: vendedor 1-2, supervisor 3-4, empresa desde 5.
          Si no aplica vendedor o supervisor, deja ambos campos vacios y ajusta el inicio de empresa.
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Paquete</th>
                <th colspan="2" class="text-center">Vendedor</th>
                <th colspan="2" class="text-center">Supervisor</th>
                <th>Empresa desde</th>
              </tr>
              <tr>
                <th></th>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Pago</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($paquetes as $i => $tipo):
                $r = $reglas[$tipo] ?? [];
              ?>
              <tr>
                <td>
                  <strong><?= e($tipo) ?></strong>
                  <input type="hidden" name="tipo_contrato[]" value="<?= e($tipo) ?>">
                </td>
                <td><input class="form-control form-control-sm" type="number" min="1" name="vendedor_desde[]" value="<?= e($r['vendedor_desde'] ?? '') ?>"></td>
                <td><input class="form-control form-control-sm" type="number" min="1" name="vendedor_hasta[]" value="<?= e($r['vendedor_hasta'] ?? '') ?>"></td>
                <td><input class="form-control form-control-sm" type="number" min="1" name="supervisor_desde[]" value="<?= e($r['supervisor_desde'] ?? '') ?>"></td>
                <td><input class="form-control form-control-sm" type="number" min="1" name="supervisor_hasta[]" value="<?= e($r['supervisor_hasta'] ?? '') ?>"></td>
                <td><input class="form-control form-control-sm" type="number" min="1" name="empresa_desde[]" value="<?= e($r['empresa_desde'] ?? '') ?>" required></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-primary">Guardar promocion</button>
          <a href="?r=promociones.listar" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

switch ($action) {
case 'listar':
  $rows = qall("
    SELECT p.id_mos_cat_com, p.codigo, p.concepto, p.estatus, p.fecha_registro,
           COUNT(r.id_regla) AS reglas
    FROM futuro_mostrar_catalogo_promociones p
    LEFT JOIN futuro_promocion_reglas r ON r.id_mos_cat_com = p.id_mos_cat_com AND COALESCE(r.activo,1)=1
    GROUP BY p.id_mos_cat_com, p.codigo, p.concepto, p.estatus, p.fecha_registro
    ORDER BY p.estatus DESC, p.codigo ASC
  ");

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Promociones</h1>
      <div class="text-muted small">Reglas por paquete para repartir pagos entre vendedor, supervisor y empresa.</div>
    </div>
    <a class="btn btn-success btn-sm" href="?r=promociones.nuevo">Nueva promocion</a>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Codigo</th>
          <th>Concepto</th>
          <th>Reglas</th>
          <th>Estatus</th>
          <th>Fecha</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>#<?= (int)$row['id_mos_cat_com'] ?></td>
          <td><code><?= e($row['codigo']) ?></code></td>
          <td><?= e($row['concepto']) ?></td>
          <td><span class="badge text-bg-light"><?= (int)$row['reglas'] ?> paquete(s)</span></td>
          <td><span class="badge text-bg-<?= ((int)$row['estatus'] === 1) ? 'success' : 'secondary' ?>"><?= ((int)$row['estatus'] === 1) ? 'Activa' : 'Inactiva' ?></span></td>
          <td><?= e(date('Y-m-d', strtotime($row['fecha_registro']))) ?></td>
          <td class="text-end">
            <div class="d-inline-flex flex-wrap gap-1">
              <a class="btn btn-outline-primary btn-sm" href="?r=promociones.editar&id=<?= (int)$row['id_mos_cat_com'] ?>">Editar</a>
              <?php if ((int)$row['estatus'] === 1): ?>
                <a class="btn btn-outline-warning btn-sm" href="?r=promociones.baja&id=<?= (int)$row['id_mos_cat_com'] ?>" onclick="return confirm('Inactivar esta promocion?');">Inactivar</a>
              <?php else: ?>
                <a class="btn btn-outline-success btn-sm" href="?r=promociones.activar&id=<?= (int)$row['id_mos_cat_com'] ?>">Activar</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted">Sin promociones registradas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Promociones', ob_get_clean());
  break;

case 'nuevo':
  render('Nueva promocion', promociones_render_form('Nueva promocion', '?r=promociones.guardar', ['estatus' => 1], []));
  break;

case 'guardar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('promociones.listar');
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('promociones.nuevo'); }

  $codigo = trim($_POST['codigo'] ?? '');
  $concepto = trim($_POST['concepto'] ?? '');
  $estatus = (int)($_POST['estatus'] ?? 1) ? 1 : 0;
  $reglas = promociones_post_reglas();

  if ($codigo === '' || $concepto === '') {
    flash("<div class='alert alert-warning'>Codigo y concepto son obligatorios.</div>");
    redirect('promociones.nuevo');
  }

  try {
    db()->beginTransaction();
    q("INSERT INTO futuro_mostrar_catalogo_promociones (codigo, concepto, estatus) VALUES (?,?,?)", [$codigo, $concepto, $estatus]);
    $id = (int)db()->lastInsertId();
    $errores = futuros_promociones_guardar_reglas($id, $reglas);
    if (!empty($errores)) {
      db()->rollBack();
      flash("<div class='alert alert-danger'>".e(implode(' ', $errores))."</div>");
      redirect('promociones.nuevo');
    }
    db()->commit();
    flash("<div class='alert alert-success'>Promocion registrada.</div>");
    redirect('promociones.listar');
  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    flash("<div class='alert alert-danger'>No se pudo guardar la promocion.</div>");
    redirect('promociones.nuevo');
  }
  break;

case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $promo = promociones_find($id);
  if (!$promo) {
    flash("<div class='alert alert-warning'>Promocion no encontrada.</div>");
    redirect('promociones.listar');
  }
  render('Editar promocion', promociones_render_form('Editar promocion #'.$id, '?r=promociones.actualizar&id='.$id, $promo, futuros_promociones_reglas_por_promocion($id)));
  break;

case 'actualizar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('promociones.listar');
  try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesion invalida.</div>"); redirect('promociones.listar'); }

  $id = (int)($_GET['id'] ?? 0);
  $promo = promociones_find($id);
  if (!$promo) {
    flash("<div class='alert alert-warning'>Promocion no encontrada.</div>");
    redirect('promociones.listar');
  }

  $codigo = trim($_POST['codigo'] ?? '');
  $concepto = trim($_POST['concepto'] ?? '');
  $estatus = (int)($_POST['estatus'] ?? 1) ? 1 : 0;
  $reglas = promociones_post_reglas();
  if ($codigo === '' || $concepto === '') {
    flash("<div class='alert alert-warning'>Codigo y concepto son obligatorios.</div>");
    redirect('promociones.editar&id='.$id);
  }

  $errores = futuros_promociones_guardar_reglas($id, $reglas);
  if (!empty($errores)) {
    flash("<div class='alert alert-danger'>".e(implode(' ', $errores))."</div>");
    redirect('promociones.editar&id='.$id);
  }
  q("UPDATE futuro_mostrar_catalogo_promociones SET codigo=?, concepto=?, estatus=? WHERE id_mos_cat_com=?", [$codigo, $concepto, $estatus, $id]);
  flash("<div class='alert alert-success'>Promocion actualizada.</div>");
  redirect('promociones.listar');
  break;

case 'baja':
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    q("UPDATE futuro_mostrar_catalogo_promociones SET estatus=0 WHERE id_mos_cat_com=?", [$id]);
    flash("<div class='alert alert-success'>Promocion inactivada.</div>");
  }
  redirect('promociones.listar');
  break;

case 'activar':
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0) {
    q("UPDATE futuro_mostrar_catalogo_promociones SET estatus=1 WHERE id_mos_cat_com=?", [$id]);
    flash("<div class='alert alert-success'>Promocion activada.</div>");
  }
  redirect('promociones.listar');
  break;

default:
  redirect('promociones.listar');
}
