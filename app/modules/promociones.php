<?php
// CRUD de promociones y su configuracion economica.
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

function promociones_regla_vacia(): array {
  return array_fill_keys(array_keys(futuros_promociones_regla_campos()), '0.00');
}

function promociones_post_regla(): array {
  $regla = [];
  foreach (futuros_promociones_regla_campos() as $campo => $_) {
    $regla[$campo] = trim((string)($_POST[$campo] ?? ''));
  }
  return $regla;
}

function promociones_validar_catalogo(string $codigo, string $concepto, int $ignoreId = 0): array {
  $errores = [];
  if ($codigo === '') $errores[] = 'Codigo es obligatorio.';
  if ($concepto === '') $errores[] = 'Concepto es obligatorio.';
  if (strlen($codigo) > 20) $errores[] = 'Codigo no puede exceder 20 caracteres.';
  if (strlen($concepto) > 250) $errores[] = 'Concepto no puede exceder 250 caracteres.';
  if ($codigo !== '') {
    foreach (futuros_promociones_catalogo(false) as $row) {
      if ((int)$row['id_mos_cat_com'] === $ignoreId) continue;
      if (futuros_promociones_normalizar((string)$row['codigo']) === futuros_promociones_normalizar($codigo)) {
        $errores[] = 'Ya existe una promocion con ese codigo.';
        break;
      }
    }
  }
  return $errores;
}

function promociones_render_form(
  string $titulo,
  string $actionUrl,
  array $promo,
  array $regla,
  array $errores = []
): string {
  $regla = array_merge(promociones_regla_vacia(), $regla);
  $moneyAttrs = [
    'type' => 'number',
    'step' => '0.01',
    'min' => '0',
    'max' => '9999999999.99',
    'required' => true,
  ];

  ob_start(); ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3"><?= e($titulo) ?></h1>

      <?php if (!empty($errores)): ?>
        <div class="alert alert-danger" role="alert">
          <strong>No se pudo guardar la promocion.</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($errores as $error): ?>
              <li><?= e($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e($actionUrl) ?>" id="form-promocion">
        <?= csrf_field() ?>

        <div class="row g-3">
          <div class="col-md-3">
            <?= form_input('codigo', 'Codigo', $promo['codigo'] ?? '', ['required'=>true, 'maxlength'=>20]) ?>
          </div>
          <div class="col-md-6">
            <?= form_input('concepto', 'Concepto', $promo['concepto'] ?? '', ['required'=>true, 'maxlength'=>250]) ?>
          </div>
          <div class="col-md-3">
            <?= form_select('estatus', 'Estatus', ['1'=>'Activa', '0'=>'Inactiva'], (string)($promo['estatus'] ?? 1), ['class'=>'form-select']) ?>
          </div>
        </div>

        <h2 class="h6 mt-4 mb-2">Desglose del pago inicial</h2>
        <div class="row g-3 align-items-start">
          <div class="col-md-4">
            <?= form_input('pago_inicial', 'Pago inicial', $regla['pago_inicial'], $moneyAttrs + ['id'=>'pago_inicial']) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('gasto_administrativo', 'Administrativo / papeleria', $regla['gasto_administrativo'], $moneyAttrs + ['id'=>'gasto_administrativo']) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('comision_vendedor_inicial', 'Comision inicial del vendedor', $regla['comision_vendedor_inicial'], $moneyAttrs + ['id'=>'comision_vendedor_inicial']) ?>
          </div>
        </div>
        <div id="estado-desglose" class="alert py-2 mb-0" role="status" aria-live="polite"></div>

        <h2 class="h6 mt-4 mb-2">Comisiones posteriores</h2>
        <div class="row g-3">
          <div class="col-md-4">
            <?= form_input('comision_vendedor_posterior', 'Comision posterior del vendedor', $regla['comision_vendedor_posterior'], $moneyAttrs) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('comision_lider', 'Comision del lider', $regla['comision_lider'], $moneyAttrs) ?>
          </div>
          <div class="col-md-4">
            <?= form_input('comision_gerente', 'Comision del gerente', $regla['comision_gerente'], $moneyAttrs) ?>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-primary" type="submit">Guardar promocion</button>
          <a href="?r=promociones.listar" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const inicial = document.getElementById('pago_inicial');
    const comision = document.getElementById('comision_vendedor_inicial');
    const administrativo = document.getElementById('gasto_administrativo');
    const estado = document.getElementById('estado-desglose');

    function centavos(input) {
      const valor = Number.parseFloat(input?.value || '0');
      return Number.isFinite(valor) ? Math.round(valor * 100) : 0;
    }

    function moneda(valorCentavos) {
      return (Math.abs(valorCentavos) / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN'
      });
    }

    function actualizarDesglose() {
      const diferencia = centavos(inicial) - centavos(administrativo) - centavos(comision);
      estado.classList.remove('alert-success', 'alert-warning');
      if (diferencia === 0 && centavos(inicial) > 0) {
        estado.classList.add('alert-success');
        estado.textContent = 'Desglose completo: administrativo y vendedor cubren el pago inicial.';
      } else {
        estado.classList.add('alert-warning');
        estado.textContent = diferencia >= 0
          ? 'Faltan por distribuir ' + moneda(diferencia) + '.'
          : 'El desglose excede el pago inicial por ' + moneda(diferencia) + '.';
      }
    }

    [inicial, administrativo, comision].forEach(function (input) {
      input?.addEventListener('input', actualizarDesglose);
    });
    actualizarDesglose();
  });
  </script>
  <?php
  return ob_get_clean();
}

switch ($action) {
case 'listar':
  $rows = futuros_promociones_catalogo(false);

  ob_start(); ?>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 m-0">Promociones</h1>
      <div class="text-muted small">Configuracion economica de promociones.</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary btn-sm" href="?r=promociones.migracion">Contratos vigentes</a>
      <a class="btn btn-success btn-sm" href="?r=promociones.nuevo">Nueva promocion</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead>
        <tr>
          <th>Promocion</th>
          <th>Concepto</th>
          <th class="text-end">Pago inicial</th>
          <th class="text-end">Vendedor posterior</th>
          <th class="text-end">Lider</th>
          <th class="text-end">Gerente</th>
          <th>Configuracion</th>
          <th>Estatus</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
          $configurada = futuros_promociones_regla_configurada(!empty($row['id_regla']) ? $row : null);
        ?>
        <tr>
          <td>
            <code><?= e($row['codigo']) ?></code>
            <div class="small text-muted">#<?= (int)$row['id_mos_cat_com'] ?></div>
          </td>
          <td><?= e($row['concepto']) ?></td>
          <td class="text-end">
            <strong>$<?= number_format((float)($row['pago_inicial'] ?? 0), 2) ?></strong>
            <div class="small text-muted">
              Vendedor $<?= number_format((float)($row['comision_vendedor_inicial'] ?? 0), 2) ?> ·
              Administrativo $<?= number_format((float)($row['gasto_administrativo'] ?? 0), 2) ?>
            </div>
          </td>
          <td class="text-end">$<?= number_format((float)($row['comision_vendedor_posterior'] ?? 0), 2) ?></td>
          <td class="text-end">$<?= number_format((float)($row['comision_lider'] ?? 0), 2) ?></td>
          <td class="text-end">$<?= number_format((float)($row['comision_gerente'] ?? 0), 2) ?></td>
          <td>
            <span class="badge text-bg-<?= $configurada ? 'success' : 'warning' ?>">
              <?= $configurada ? 'Completa' : 'Pendiente' ?>
            </span>
          </td>
          <td>
            <span class="badge text-bg-<?= ((int)$row['estatus'] === 1) ? 'success' : 'secondary' ?>">
              <?= ((int)$row['estatus'] === 1) ? 'Activa' : 'Inactiva' ?>
            </span>
          </td>
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
          <tr><td colspan="9" class="text-center text-muted">Sin promociones registradas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  render('Promociones', ob_get_clean());
  break;

case 'nuevo':
  render(
    'Nueva promocion',
    promociones_render_form('Nueva promocion', '?r=promociones.guardar', ['estatus'=>1], promociones_regla_vacia())
  );
  break;

case 'guardar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('promociones.listar');
  try {
    csrf_verify();
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-danger'>Sesion invalida.</div>");
    redirect('promociones.nuevo');
  }

  $codigo = trim((string)($_POST['codigo'] ?? ''));
  $concepto = trim((string)($_POST['concepto'] ?? ''));
  $estatus = (int)($_POST['estatus'] ?? 1) ? 1 : 0;
  $regla = promociones_post_regla();
  $errores = array_merge(
    promociones_validar_catalogo($codigo, $concepto),
    futuros_promociones_validar_regla($regla)
  );

  if (!empty($errores)) {
    render('Nueva promocion', promociones_render_form(
      'Nueva promocion',
      '?r=promociones.guardar',
      ['codigo'=>$codigo, 'concepto'=>$concepto, 'estatus'=>$estatus],
      $regla,
      $errores
    ));
  }

  try {
    db()->beginTransaction();
    q("INSERT INTO futuro_mostrar_catalogo_promociones (codigo, concepto, estatus) VALUES (?,?,?)", [$codigo, $concepto, $estatus]);
    $id = (int)db()->lastInsertId();
    $errores = futuros_promociones_guardar_regla($id, $regla);
    if (!empty($errores)) throw new RuntimeException(implode(' ', $errores));
    db()->commit();
    flash("<div class='alert alert-success'>Promocion registrada.</div>");
    redirect('promociones.listar');
  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    db_log_error($e, ['route'=>'promociones.guardar']);
    render('Nueva promocion', promociones_render_form(
      'Nueva promocion',
      '?r=promociones.guardar',
      ['codigo'=>$codigo, 'concepto'=>$concepto, 'estatus'=>$estatus],
      $regla,
      ['No se pudo guardar la promocion.']
    ));
  }
  break;

case 'editar':
  $id = (int)($_GET['id'] ?? 0);
  $promo = promociones_find($id);
  if (!$promo) {
    flash("<div class='alert alert-warning'>Promocion no encontrada.</div>");
    redirect('promociones.listar');
  }
  $regla = futuros_promociones_regla_por_promocion($id) ?? promociones_regla_vacia();
  render('Editar promocion', promociones_render_form(
    'Editar promocion #'.$id,
    '?r=promociones.actualizar&id='.$id,
    $promo,
    $regla
  ));
  break;

case 'actualizar':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('promociones.listar');
  try {
    csrf_verify();
  } catch (RuntimeException $e) {
    flash("<div class='alert alert-danger'>Sesion invalida.</div>");
    redirect('promociones.listar');
  }

  $id = (int)($_GET['id'] ?? 0);
  $promoActual = promociones_find($id);
  if (!$promoActual) {
    flash("<div class='alert alert-warning'>Promocion no encontrada.</div>");
    redirect('promociones.listar');
  }

  $codigo = trim((string)($_POST['codigo'] ?? ''));
  $concepto = trim((string)($_POST['concepto'] ?? ''));
  $estatus = (int)($_POST['estatus'] ?? 1) ? 1 : 0;
  $regla = promociones_post_regla();
  $errores = array_merge(
    promociones_validar_catalogo($codigo, $concepto, $id),
    futuros_promociones_validar_regla($regla)
  );
  $promoFormulario = array_merge($promoActual, [
    'codigo'=>$codigo,
    'concepto'=>$concepto,
    'estatus'=>$estatus,
  ]);

  if (!empty($errores)) {
    render('Editar promocion', promociones_render_form(
      'Editar promocion #'.$id,
      '?r=promociones.actualizar&id='.$id,
      $promoFormulario,
      $regla,
      $errores
    ));
  }

  try {
    db()->beginTransaction();
    q("UPDATE futuro_mostrar_catalogo_promociones SET codigo=?, concepto=?, estatus=? WHERE id_mos_cat_com=?", [$codigo, $concepto, $estatus, $id]);
    $errores = futuros_promociones_guardar_regla($id, $regla);
    if (!empty($errores)) throw new RuntimeException(implode(' ', $errores));
    db()->commit();
    flash("<div class='alert alert-success'>Promocion actualizada.</div>");
    redirect('promociones.listar');
  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    db_log_error($e, ['route'=>'promociones.actualizar', 'id_promocion'=>$id]);
    render('Editar promocion', promociones_render_form(
      'Editar promocion #'.$id,
      '?r=promociones.actualizar&id='.$id,
      $promoFormulario,
      $regla,
      ['No se pudo actualizar la promocion.']
    ));
  }
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
    if (!futuros_promociones_regla_configurada(futuros_promociones_regla_por_promocion($id))) {
      flash("<div class='alert alert-warning'>Completa la configuracion economica antes de activar la promocion.</div>");
    } else {
      q("UPDATE futuro_mostrar_catalogo_promociones SET estatus=1 WHERE id_mos_cat_com=?", [$id]);
      flash("<div class='alert alert-success'>Promocion activada.</div>");
    }
  }
  redirect('promociones.listar');
  break;

case 'migracion':
  $estado = futuros_promociones_migracion_estado();
  ob_start(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 m-0">Contratos vigentes</h1>
      <div class="text-muted small">Preparacion para distribuir solamente los abonos futuros.</div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="?r=promociones.listar">Volver</a>
  </div>

  <?php if ($estado['errores']): ?>
    <div class="alert alert-danger">
      <strong>No se puede activar la migracion.</strong>
      <ul class="mb-0 mt-2 list-unstyled">
        <?php foreach ($estado['errores'] as $error):
          $idContratoError = 0;
          if (preg_match('/^Contrato #(\d+):/', (string)$error, $coincidencia)) {
            $idContratoError = (int)$coincidencia[1];
          }
        ?>
          <li class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span><?= e($error) ?></span>
            <?php if ($idContratoError > 0): ?>
              <a
                class="btn btn-sm btn-outline-danger"
                href="?r=contratos.editar&amp;id_contrato=<?= $idContratoError ?>"
              >Editar contrato</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      <?= count($estado['listos']) ?> contrato(s) estan listos. Los abonos anteriores solo marcaran el avance acumulado y no generaran comisiones retroactivas.
    </div>
  <?php endif; ?>

  <div class="table-responsive mb-3">
    <table class="table table-sm table-striped align-middle">
      <thead><tr><th>Contrato</th><th>Promocion reconocida</th><th class="text-end">Pagado antes</th><th class="text-end">Ultimo abono</th></tr></thead>
      <tbody>
        <?php foreach ($estado['listos'] as $item): ?>
          <tr>
            <td>#<?= (int)$item['contrato']['id_contrato'] ?></td>
            <td><?= e(futuros_promociones_label($item['promocion']['codigo'] ?? '', $item['promocion']['concepto'] ?? '')) ?></td>
            <td class="text-end">$<?= number_format((float)$item['pagado'], 2) ?></td>
            <td class="text-end">#<?= (int)$item['id_abono_corte'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$estado['listos']): ?><tr><td colspan="4" class="text-center text-muted">No hay contratos pendientes de migrar.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$estado['errores'] && $estado['listos']): ?>
    <form method="post" action="?r=promociones.migrar_contratos" onsubmit="return confirm('Activar las nuevas condiciones para estos contratos?');">
      <?= csrf_field() ?>
      <button class="btn btn-primary">Activar pagos futuros</button>
    </form>
  <?php endif; ?>
  <?php
  render('Migracion de contratos vigentes', ob_get_clean());
  break;

case 'migrar_contratos':
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('promociones.migracion');
  try {
    csrf_verify();
    db()->beginTransaction();
    $resultado = futuros_promociones_migrar_contratos_vigentes();
    db()->commit();
    flash("<div class='alert alert-success'>Se activaron {$resultado['migrados']} contrato(s) para pagos futuros.</div>");
  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    db_log_error($e, ['route'=>'promociones.migrar_contratos']);
    flash("<div class='alert alert-danger'>No se activo la migracion. Revisa los contratos pendientes.</div>");
  }
  redirect('promociones.migracion');
  break;

default:
  redirect('promociones.listar');
}
