<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/servicios_operativos.php';

require_login();
require_role(['administradora','auxiliar','vendedor','coordinador','caja']);

$action = $action ?? 'orden_preparacion_pdf';
$id = (int)($_GET['id'] ?? 0);
$svc = so_servicio_fetch($id);
if (!$svc) {
  die('Servicio no encontrado.');
}

require_once __DIR__.'/../dompdf/autoload.inc.php';

function servicio_reportes_output(string $title, string $bodyHtml, string $filename): void {
  $html = '<html><head><meta charset="utf-8"><style>
    body{font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#222}
    h1{font-size:20px;margin:0 0 8px}
    h2{font-size:15px;margin:18px 0 8px}
    .muted{color:#666}
    .grid{width:100%;border-collapse:collapse;margin-top:8px}
    .grid th,.grid td{border:1px solid #ccc;padding:6px 8px;text-align:left}
  </style></head><body>'.$bodyHtml.'</body></html>';

  $previousDisplayErrors = ini_get('display_errors');
  $previousReporting = error_reporting();
  ini_set('display_errors', '0');
  error_reporting($previousReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_WARNING);
  $previousHandler = set_error_handler(static function ($severity, $message, $file) {
    if (($severity & (E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_WARNING)) && strpos((string)$file, '/dompdf/') !== false) {
      return true;
    }
    return false;
  });

  try {
    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isFontSubsettingEnabled', false);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfOutput = $dompdf->output();
  } finally {
    if ($previousHandler !== null) {
      restore_error_handler();
    } else {
      restore_error_handler();
    }
    error_reporting($previousReporting);
    ini_set('display_errors', (string)$previousDisplayErrors);
  }

  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  if (!headers_sent()) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.$filename.'"');
    header('Cache-Control: private, max-age=0, must-revalidate');
  }
  echo $pdfOutput;
  exit;
}

$asignacion = so_servicio_asignacion($id);
$seguimiento = so_servicio_seguimiento($id);
$cierre = so_servicio_cierre($id);
$totales = so_servicio_totales($id);
$articulos = so_servicio_articulos_asignados($id);
$cajas = so_servicio_cajas_asignadas($id);
$equipos = so_servicio_equipos_asignados($id);
$fechaProgramada = $asignacion['fecha_hora_inicio'] ?? ($svc['fecha_hora_programada'] ?? '');
$auxiliarNombre = $asignacion['auxiliar_nombre'] ?? ($svc['auxiliar_nombre'] ?? '');
$lugarTipo = $asignacion['lugar_tipo'] ?? ($svc['lugar_tipo'] ?? '');
$lugarDetalle = $asignacion['lugar_detalle'] ?? ($svc['lugar_detalle'] ?? '');
$vehiculo = $asignacion['id_vehiculo'] ?? ($svc['id_vehiculo'] ?? '');
$sala = $asignacion['id_sala'] ?? ($svc['id_sala'] ?? '');
$instrucciones = $asignacion['instrucciones'] ?? ($svc['notas'] ?? '');
$fechaCierre = $cierre['fecha_cierre'] ?? ($svc['closed_at'] ?? '');
$auxiliarCierre = $cierre['auxiliar_nombre'] ?? ($svc['auxiliar_nombre'] ?? '');

switch ($action) {
case 'orden_preparacion_pdf':
  ob_start(); ?>
  <h1>Orden interna de preparacion</h1>
  <p class="muted">Servicio <?= e($svc['folio']) ?> · Fallecido <?= e($svc['fallecido'] ?: 'No capturado') ?></p>
  <table class="grid">
    <tr><th>Contratante</th><td><?= e($svc['contratante_nombre']) ?></td><th>Telefono</th><td><?= e($svc['contratante_tel'] ?: '—') ?></td></tr>
    <tr><th>Fecha programada</th><td><?= e($fechaProgramada ?: '—') ?></td><th>Auxiliar</th><td><?= e($auxiliarNombre ?: '—') ?></td></tr>
    <tr><th>Lugar</th><td colspan="3"><?= e(($lugarTipo ?: '—').' '.($lugarDetalle ?: '')) ?></td></tr>
    <tr><th>Vehiculo</th><td><?= e($vehiculo ?: '—') ?></td><th>Sala</th><td><?= e($sala ?: '—') ?></td></tr>
    <tr><th>Instrucciones</th><td colspan="3"><?= e($instrucciones ?: '—') ?></td></tr>
  </table>
  <h2>Recursos preparados</h2>
  <table class="grid">
    <tr><th>Ataudes</th><td><?= e(implode(', ', array_map(fn($r) => $r['codigo'].' '.$r['modelo'], $cajas)) ?: '—') ?></td></tr>
    <tr><th>Equipos</th><td><?= e(implode(', ', array_map(fn($r) => $r['equipo'].' '.$r['id_equipo'], $equipos)) ?: '—') ?></td></tr>
    <tr><th>Articulos</th><td><?= e(implode(', ', array_map(fn($r) => $r['articulo'].' x'.$r['cantidad'], $articulos)) ?: '—') ?></td></tr>
  </table>
  <?php
  servicio_reportes_output('Orden de preparacion', ob_get_clean(), 'orden_preparacion_'.$id.'.pdf');
  break;

case 'consumo_pdf':
  ob_start(); ?>
  <h1>Reporte de consumo del servicio</h1>
  <p class="muted">Servicio <?= e($svc['folio']) ?> · Fallecido <?= e($svc['fallecido'] ?: 'No capturado') ?></p>
  <table class="grid">
    <tr><th>Inicio real</th><td><?= e($seguimiento['hora_inicio_real'] ?? '—') ?></td><th>Fin real</th><td><?= e($seguimiento['hora_fin_real'] ?? '—') ?></td></tr>
    <tr><th>Incidencias</th><td colspan="3"><?= e($seguimiento['incidencias'] ?? '—') ?></td></tr>
  </table>
  <h2>Consumos</h2>
  <table class="grid">
    <thead><tr><th>Articulo</th><th>Cantidad</th><th>Responsable</th><th>Fecha</th></tr></thead>
    <tbody>
      <?php foreach ($articulos as $articulo): ?>
      <tr>
        <td><?= e($articulo['articulo']) ?></td>
        <td><?= (int)$articulo['cantidad'] ?></td>
        <td><?= e($articulo['responsable'] ?: '—') ?></td>
        <td><?= e($articulo['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($articulos)): ?>
      <tr><td colspan="4">Sin consumos registrados.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php
  servicio_reportes_output('Reporte de consumo', ob_get_clean(), 'reporte_consumo_'.$id.'.pdf');
  break;

case 'acta_cierre_pdf':
  ob_start(); ?>
  <h1>Acta interna de cierre</h1>
  <p class="muted">Servicio <?= e($svc['folio']) ?> · Fallecido <?= e($svc['fallecido'] ?: 'No capturado') ?></p>
  <table class="grid">
    <tr><th>Fecha cierre</th><td><?= e($fechaCierre ?: '—') ?></td><th>Auxiliar</th><td><?= e($auxiliarCierre ?: '—') ?></td></tr>
    <tr><th>Pago confirmado</th><td><?= !empty($cierre['pago_confirmado']) ? 'Si' : 'No' ?></td><th>Auxiliar confirma</th><td><?= !empty($cierre['auxiliar_confirma']) ? 'Si' : 'No' ?></td></tr>
    <tr><th>Observaciones</th><td colspan="3"><?= e($cierre['observaciones_cierre'] ?? '—') ?></td></tr>
  </table>
  <?php
  servicio_reportes_output('Acta interna de cierre', ob_get_clean(), 'acta_cierre_'.$id.'.pdf');
  break;

case 'reporte_cierre_pdf':
default:
  ob_start(); ?>
  <h1>Reporte de cierre de servicio</h1>
  <p class="muted">Servicio <?= e($svc['folio']) ?> · Fallecido <?= e($svc['fallecido'] ?: 'No capturado') ?></p>
  <?= so_servicio_resumen_html($id) ?>
  <?php
  servicio_reportes_output('Reporte de cierre', ob_get_clean(), 'reporte_cierre_'.$id.'.pdf');
}
