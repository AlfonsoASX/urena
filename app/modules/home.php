<?php
// app/modules/home.php — Dashboard mejorado (sin triggers; todo vía PHP)
require_once __DIR__."/../core/db.php";
require_once __DIR__."/../core/helpers.php";
require_once __DIR__."/../core/auth.php";

require_login();

/** KPIs rápidos (defensivos ante datos faltantes) */
function kpi_int($sql,$params=[]){
  try{ $r=qone($sql,$params); if(!$r) return 0; return (int)array_values($r)[0]; }catch(Throwable $e){ return 0; }
}
function kpi_float($sql,$params=[]){
  try{ $r=qone($sql,$params); if(!$r) return 0.0; return (float)array_values($r)[0]; }catch(Throwable $e){ return 0.0; }
}

// Servicios
$kpi_serv_abiertos = kpi_int("SELECT COUNT(*) FROM servicios WHERE COALESCE(eliminado,0)=0 AND COALESCE(cerrado,0)=0");
$kpi_serv_cerrados = kpi_int("SELECT COUNT(*) FROM servicios WHERE COALESCE(eliminado,0)=0 AND COALESCE(cerrado,0)=1");

// Inventario
$kpi_articulos_total = kpi_int("SELECT COUNT(*) FROM articulos WHERE COALESCE(eliminado,0)=0");
$kpi_articulos_bajos = kpi_int("SELECT COUNT(*) FROM articulos WHERE COALESCE(eliminado,0)=0 AND COALESCE(existencias,0) <= 5");
$kpi_cajas_total     = kpi_int("SELECT COUNT(*) FROM cajas WHERE COALESCE(eliminado,0)=0");

// Pagos / contratos
$kpi_contratos       = kpi_int("SELECT COUNT(*) FROM futuro_contratos");
$kpi_abonos_hoy      = kpi_float("SELECT COALESCE(SUM(cant_abono),0) FROM futuro_abonos WHERE DATE(fecha_registro)=CURDATE()");

// Comisiones (si existe tabla)
$kpi_comisiones_pend = kpi_int("SELECT COUNT(*) FROM futuro_comision_semanal WHERE LOWER(COALESCE(estatus,'')) IN ('pendiente','por_pagar','por pagar')");

// Personal (vendedores/cobradores)
$personal = []; 
try {
  $personal = qall("SELECT id_personal, CONCAT(nombre,' ',apellido_p,' ',apellido_m) AS nombre, LOWER(COALESCE(estatus,'')) AS estatus
                    FROM futuro_personal WHERE COALESCE(estatus,'') <> 'inactivo' ORDER BY nombre ASC");
} catch(Throwable $e){ $personal=[]; }

// ÚLTIMOS ABONOS
$abonos = [];
try {
  $abonos = qall("
      SELECT a.id_abono, a.id_contrato, a.cant_abono, DATE_FORMAT(a.fecha_registro,'%Y-%m-%d') AS fecha,
             (SELECT CONCAT(t.nombre,' ',t.apellido_p) FROM titulares t 
              JOIN titular_contrato tc ON tc.id_titular=t.id_titular 
              WHERE tc.id_contrato=a.id_contrato LIMIT 1) AS titular
      FROM futuro_abonos a
      ORDER BY a.id_abono DESC
      LIMIT 10");
} catch(Throwable $e){ $abonos=[]; }

// COMISIONES RECIENTES (pendientes)
$comisiones = [];
try {
  $comisiones = qall("
      SELECT id_bono_sem, id_contrato, COALESCE(cant_comision,0) AS monto,
             LOWER(COALESCE(estatus,'')) AS estatus,
             DATE_FORMAT(fecha_registro,'%Y-%m-%d') AS fecha
      FROM futuro_comision_semanal
      ORDER BY id_bono_sem DESC
      LIMIT 10");
} catch(Throwable $e){ $comisiones=[]; }

ob_start(); ?>
<style>
  .kpi-card{border-radius:1rem}
  .kpi-value{font-size:1.6rem;font-weight:700}
  .kpi-sub{font-size:.85rem;color:#6c757d}
  .quick-link{display:flex;gap:.5rem;align-items:center;text-decoration:none}
  .quick-link:hover{opacity:.85}
</style>

<div class="py-3 py-md-4">
  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card kpi-card shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">Servicios abiertos</div>
          <div class="kpi-value"><?= number_format($kpi_serv_abiertos) ?></div>
          <div class="kpi-sub">Cerrados: <?= number_format($kpi_serv_cerrados) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">Abonos del día</div>
          <div class="kpi-value">$<?= number_format($kpi_abonos_hoy,2) ?></div>
          <div class="kpi-sub">Contratos: <?= number_format($kpi_contratos) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">Comisiones pendientes</div>
          <div class="kpi-value"><?= number_format($kpi_comisiones_pend) ?></div>
          <div class="kpi-sub">Revísalas y págales</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">Inventario</div>
          <div class="kpi-value"><?= number_format($kpi_articulos_total) ?> artículos</div>
          <div class="kpi-sub">Bajo stock: <?= number_format($kpi_articulos_bajos) ?> · Cajas: <?= number_format($kpi_cajas_total) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráficas -->
  <?php
  try {
    $abonos30 = qall("
      SELECT DATE(fecha_registro) AS fecha, COALESCE(SUM(cant_abono),0) AS total
      FROM futuro_abonos
      WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      GROUP BY DATE(fecha_registro)
      ORDER BY fecha ASC
    ");
    $abonos12 = qall("
      SELECT DATE_FORMAT(fecha_registro, '%Y-%m') AS mes, COALESCE(SUM(cant_abono),0) AS total
      FROM futuro_abonos
      WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
      ORDER BY mes ASC
    ");
  } catch (Throwable $e) { $abonos30 = $abonos12 = []; }
  $dias_labels = array_map(fn($r)=>$r['fecha'],$abonos30);
  $dias_values = array_map(fn($r)=>(float)$r['total'],$abonos30);
  $mes_labels  = array_map(fn($r)=>$r['mes'],$abonos12);
  $mes_values  = array_map(fn($r)=>(float)$r['total'],$abonos12);
  ?>
  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Recuperado últimos 30 días</h2>
          <canvas id="chartDias" height="180"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Recuperado últimos 12 meses</h2>
          <canvas id="chartMeses" height="180"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded',function(){
    new Chart(document.getElementById('chartDias'),{
      type:'bar',
      data:{labels:<?=json_encode($dias_labels)?>,datasets:[{data:<?=json_encode($dias_values)?>,backgroundColor:'rgba(54,162,235,0.5)',borderColor:'rgba(54,162,235,1)',borderWidth:1}]},
      options:{scales:{y:{beginAtZero:true}},plugins:{legend:{display:false}}}
    });
    new Chart(document.getElementById('chartMeses'),{
      type:'line',
      data:{labels:<?=json_encode($mes_labels)?>,datasets:[{data:<?=json_encode($mes_values)?>,borderColor:'rgba(75,192,192,1)',backgroundColor:'rgba(75,192,192,0.2)',tension:.3,fill:true}]},
      options:{scales:{y:{beginAtZero:true}},plugins:{legend:{display:false}}}
    });
  });
  </script>

  <!-- === GESTIONES & CITAS DEL DÍA === -->
  <div class="row g-3 mt-4">
    <!-- Widget: Citas del Día -->
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">🗓️ Citas del día (para cobrar)</h2>
          <?php
          try {
            $citas = qall("
              SELECT g.id_contrato, g.fecha_proxima_visita,
                     c.id_contrato, t.titular, d.colonia,
                     CONCAT(p.nombre,' ',p.apellido_p) AS cobrador,
                     CASE 
                       WHEN g.fecha_proxima_visita IS NULL THEN 'sin_programar'
                       WHEN g.fecha_proxima_visita < CURDATE() THEN 'vencida'
                       WHEN DATE(g.fecha_registro)=CURDATE() THEN 'atendida'
                       ELSE 'pendiente'
                     END AS estado
              FROM futuro_gestion g
              JOIN futuro_contratos c ON c.id_contrato=g.id_contrato
              LEFT JOIN vw_titular_contrato t ON t.id_contrato=g.id_contrato
              LEFT JOIN futuro_contrato_cobrador fc ON fc.id_contrato=g.id_contrato
              LEFT JOIN futuro_personal p ON p.id_personal=fc.id_personal
              LEFT JOIN titular_contrato tc ON tc.id_contrato=c.id_contrato
              LEFT JOIN titular_dom td ON td.id_titular=tc.id_titular
              LEFT JOIN domicilios d ON d.id_domicilio=td.id_domicilio
              WHERE DATE(g.fecha_proxima_visita)=CURDATE()
              ORDER BY g.fecha_proxima_visita ASC
            ");
          } catch(Throwable $e){ $citas=[]; }
          ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#Contrato</th><th>Titular</th><th>Colonia</th><th>Cobrador</th><th>Estado</th></tr></thead>
              <tbody>
                <?php if(!$citas): ?>
                  <tr><td colspan="5" class="text-muted">No hay citas programadas para hoy.</td></tr>
                <?php else: foreach($citas as $c): 
                  $badge = match($c['estado']){
                    'atendida'=>'success','pendiente'=>'warning','vencida'=>'danger',default=>'secondary'
                  };
                ?>
                  <tr>
                    <td>#<?= (int)$c['id_contrato'] ?></td>
                    <td><?= e($c['titular'] ?? '-') ?></td>
                    <td><?= e($c['colonia'] ?? '-') ?></td>
                    <td><?= e($c['cobrador'] ?? '-') ?></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($c['estado']) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

<?php
// Obtener gestiones atendidas hoy directamente desde PHP
try {
  $gestiones_hoy = qall("
    SELECT g.latitud, g.longitud,
           COALESCE(t.titular,'') AS titular,
           CONCAT(p.nombre,' ',p.apellido_p) AS cobrador
    FROM futuro_gestion g
    LEFT JOIN vw_titular_contrato t ON t.id_contrato=g.id_contrato
    LEFT JOIN futuro_contrato_cobrador fc ON fc.id_contrato=g.id_contrato
    LEFT JOIN futuro_personal p ON p.id_personal=fc.id_personal
    WHERE DATE(g.fecha_registro)=CURDATE()
      AND g.latitud IS NOT NULL AND g.longitud IS NOT NULL
      AND g.latitud!=0 AND g.longitud!=0
  ");
} catch(Throwable $e) { $gestiones_hoy = []; }
?>

<!-- Widget: Mapa de gestiones atendidas -->
<div class="col-12 col-lg-6">
  <div class="card shadow-sm h-100">
    <div class="card-body">
      <h2 class="h6 mb-3">🗺️ Mapa de gestiones atendidas (hoy)</h2>
      <div id="mapGestiones" style="height:300px;border-radius:10px;"></div>
      <?php if (empty($gestiones_hoy)): ?>
        <p class="text-muted small mt-2 mb-0 text-center">No hay gestiones con ubicación registrada hoy.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
document.addEventListener("DOMContentLoaded", () => {
  const map = L.map("mapGestiones").setView([21.122, -101.68], 12);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "© OpenStreetMap"
  }).addTo(map);

  const puntos = <?= json_encode($gestiones_hoy, JSON_UNESCAPED_UNICODE) ?>;
  if (!puntos.length) return;

  const bounds = [];
  puntos.forEach(p => {
    const lat = parseFloat(p.latitud);
    const lng = parseFloat(p.longitud);
    if (!lat || !lng) return;

    const marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup(
      `<strong>${p.titular || 'Sin titular'}</strong><br>` +
      `${p.cobrador || 'Sin cobrador'}<br>` +
      `<a href="https://maps.google.com/?q=${lat},${lng}" target="_blank">Abrir en Google Maps</a>`
    );
    bounds.push([lat, lng]);
  });

  if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
});
</script>





    
  </div>





  <!-- Últimos abonos y comisiones -->
  <div class="row g-3 mt-4">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Últimos abonos</h2>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#</th><th>Contrato</th><th>Titular</th><th class="text-end">Monto</th><th>Fecha</th><th></th></tr></thead>
              <tbody>
              <?php if(!$abonos): ?>
                <tr><td colspan="6" class="text-muted">Sin abonos recientes.</td></tr>
              <?php else: foreach($abonos as $a):
                $geo=qone("SELECT latitud,longitud FROM futuro_gestion WHERE id_contrato=? ORDER BY id_gestion DESC LIMIT 1",[$a['id_contrato']]);
                $linkMap=($geo&&$geo['latitud'])?"https://maps.google.com/?q={$geo['latitud']},{$geo['longitud']}":null;
              ?>
                <tr>
                  <td><?= (int)$a['id_abono'] ?></td>
                  <td>#<?= (int)$a['id_contrato'] ?></td>
                  <td><?= e($a['titular'] ?? '') ?></td>
                  <td class="text-end">$<?= number_format((float)$a['cant_abono'],2) ?></td>
                  <td><?= e($a['fecha']) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="?r=pagos.nuevo_abono&id_contrato=<?= (int)$a['id_contrato'] ?>">Ver</a>
                    <?php if($linkMap): ?><a href="<?= $linkMap ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Ver ubicación">📍</a><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <a class="quick-link" href="?r=pagos.contratos">➡ Ver todos los contratos</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Comisiones recientes</h2>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#</th><th>Contrato</th><th class="text-end">Monto</th><th>Estatus</th><th>Fecha</th><th></th></tr></thead>
              <tbody>
                <?php if(!$comisiones): ?>
                  <tr><td colspan="6" class="text-muted">Sin registros de comisión.</td></tr>
                <?php else: foreach($comisiones as $c): 
                  $st=strtolower($c['estatus']??'');
                  $badge=($st==='pagada')?'success':(($st==='pendiente'||$st==='por pagar'||$st==='por_pagar')?'warning':'secondary'); ?>
                  <tr>
                    <td><?= (int)$c['id_bono_sem'] ?></td>
                    <td>#<?= (int)$c['id_contrato'] ?></td>
                    <td class="text-end">$<?= number_format((float)$c['monto'],2) ?></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= e($c['estatus'] ?? '-') ?></span></td>
                    <td><?= e($c['fecha']) ?></td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="?r=pagos.comisiones&id_contrato=<?= (int)$c['id_contrato'] ?>">Capturar/Ver</a></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <a class="quick-link" href="?r=pagos.comisiones">➡ Ir a comisiones</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Render con layout base
render('Inicio', ob_get_clean());
