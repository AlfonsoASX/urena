<?php
require_once __DIR__."/../core/db.php";
require_once __DIR__."/../core/helpers.php";
require_once __DIR__."/../core/auth.php";
require_once __DIR__."/../core/servicios_operativos.php";
require_once __DIR__."/../core/inventario_v2.php";

require_login();

$isAdmin    = user_has_role(['administradora', 'admin']);
$isAuxiliar = !$isAdmin && user_has_role(['auxiliar']) && !user_has_role(['administradora','coordinador','vendedor']);

// ── Vista Auxiliar ────────────────────────────────────────────────────────────
if ($isAuxiliar) {
  so_bootstrap();
  iv2_bootstrap();

  // Servicios activos
  $activosSql = iv2_servicio_activo_sql('s');
  $serviciosActivos = [];
  try {
    $serviciosActivos = qall("
      SELECT s.id_servicio, s.folio,
             COALESCE(NULLIF(TRIM(COALESCE(s.fallecido_nombre,'')),''), 'Sin fallecido') AS fallecido,
             COALESCE(s.estatus,'registrado') AS estatus,
             s.fecha_hora_programada,
             s.tipo_servicio,
             COALESCE(ua.nombre, ua.usuario, '') AS auxiliar_nombre,
             COALESCE(s.lugar_tipo,'') AS lugar_tipo
      FROM servicios s
      LEFT JOIN usuarios ua ON ua.id = s.id_usuario_auxiliar
      WHERE {$activosSql}
      ORDER BY s.fecha_hora_programada ASC, s.id_servicio DESC
    ");
  } catch (Throwable $e) {}

  foreach ($serviciosActivos as &$row) {
    $row['estatus_norm']  = so_servicio_estatus_norm($row['estatus']);
    $row['estatus_badge'] = so_servicio_badge($row['estatus']);
  }
  unset($row);

  // Conteo por estatus
  $cntRegistrado  = count(array_filter($serviciosActivos, fn($r) => $r['estatus_norm'] === 'registrado'));
  $cntPreparacion = count(array_filter($serviciosActivos, fn($r) => $r['estatus_norm'] === 'preparacion'));
  $cntEnProceso   = count(array_filter($serviciosActivos, fn($r) => $r['estatus_norm'] === 'en_proceso'));

  // Inventario – cajas disponibles
  $cajasState    = so_inventory_section_state('cajas',    '', 100);
  $cajasDisp     = count($cajasState['items']);

  // Inventario – equipos por grupo
  $equiposGrupos = [];
  try { $equiposGrupos = iv2_disponibilidad_equipos('', 80, true); } catch (Throwable $e) {}

  // Inventario – artículos con stock bajo (≤ 5)
  $articulosBajos = [];
  try {
    $articulosBajos = qall("
      SELECT articulo, COALESCE(existencias,0) AS existencias
      FROM articulos
      WHERE COALESCE(eliminado,0)=0 AND COALESCE(existencias,0) <= 5
      ORDER BY existencias ASC, articulo ASC
      LIMIT 20
    ");
  } catch (Throwable $e) {}

  // Artículos total con stock
  $articulosDisp = 0;
  try {
    $row = qone("SELECT COUNT(*) AS cnt FROM articulos WHERE COALESCE(eliminado,0)=0 AND COALESCE(existencias,0) > 0");
    $articulosDisp = (int)($row['cnt'] ?? 0);
  } catch (Throwable $e) {}

  ob_start(); ?>
  <div class="py-3 py-md-4">
    <div class="mb-4">
      <h1 class="h4 m-0">Panel auxiliar</h1>
      <div class="text-muted small">Vista operativa — servicios activos e inventario</div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-secondary h-100">
          <div class="card-body py-3">
            <div class="text-muted small">Registrados</div>
            <div class="fs-2 fw-bold text-secondary"><?= $cntRegistrado ?></div>
            <div class="small text-muted">Sin preparar</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-info h-100">
          <div class="card-body py-3">
            <div class="text-muted small">En preparación</div>
            <div class="fs-2 fw-bold text-info"><?= $cntPreparacion ?></div>
            <div class="small text-muted">Asignando recursos</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-warning h-100">
          <div class="card-body py-3">
            <div class="text-muted small">En proceso</div>
            <div class="fs-2 fw-bold text-warning"><?= $cntEnProceso ?></div>
            <div class="small text-muted">Servicio activo</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-<?= count($articulosBajos) ? 'danger' : 'success' ?> h-100">
          <div class="card-body py-3">
            <div class="text-muted small">Artículos stock bajo</div>
            <div class="fs-2 fw-bold text-<?= count($articulosBajos) ? 'danger' : 'success' ?>"><?= count($articulosBajos) ?></div>
            <div class="small text-muted"><?= $articulosDisp ?> con existencias</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">

      <!-- Servicios activos -->
      <div class="col-12 col-xl-8">
        <div class="card shadow-sm h-100">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold">Servicios activos</span>
            <a href="?r=servicios.listar" class="btn btn-sm btn-outline-secondary">Ver todos</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($serviciosActivos)): ?>
              <div class="text-center text-muted py-5">Sin servicios activos actualmente.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Folio</th>
                      <th>Fallecido</th>
                      <th>Estatus</th>
                      <th>Programado</th>
                      <th>Auxiliar</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($serviciosActivos as $svc): ?>
                      <tr>
                        <td class="ps-3 fw-semibold text-nowrap"><?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></td>
                        <td><?= e($svc['fallecido']) ?></td>
                        <td>
                          <span class="badge text-bg-<?= e($svc['estatus_badge']) ?>">
                            <?= e(ucwords(str_replace('_',' ',$svc['estatus_norm']))) ?>
                          </span>
                        </td>
                        <td class="text-muted small text-nowrap">
                          <?php if ($svc['fecha_hora_programada'] && $svc['fecha_hora_programada'] !== '0000-00-00 00:00:00'): ?>
                            <?= e(date('d/m/Y H:i', strtotime($svc['fecha_hora_programada']))) ?>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($svc['auxiliar_nombre'] ?: '—') ?></td>
                        <td class="pe-3">
                          <a href="?r=servicio_asignaciones.ver&id=<?= (int)$svc['id_servicio'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">Asignación</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Inventario lateral -->
      <div class="col-12 col-xl-4">
        <div class="vstack gap-3">

          <!-- Cajas / Ataúdes -->
          <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
              <span class="fw-semibold">Ataúdes disponibles</span>
              <span class="badge text-bg-<?= $cajasDisp > 0 ? 'success' : 'danger' ?>"><?= $cajasDisp ?></span>
            </div>
            <?php if (!empty($cajasState['items'])): ?>
              <div class="list-group list-group-flush" style="max-height:180px;overflow-y:auto">
                <?php foreach (array_slice($cajasState['items'], 0, 8) as $caja): ?>
                  <div class="list-group-item list-group-item-action py-2 d-flex justify-content-between align-items-center">
                    <div>
                      <span class="fw-semibold small"><?= e($caja['codigo']) ?></span>
                      <?php if ($caja['modelo'] ?? ''): ?>
                        <span class="text-muted small ms-1"><?= e($caja['modelo']) ?></span>
                      <?php endif; ?>
                    </div>
                    <span class="badge text-bg-<?= ($caja['reciclado'] ?? 0) || ($caja['veces_usado'] ?? 0) > 0 ? 'warning text-dark' : 'success' ?> ms-2">
                      <?= ($caja['reciclado'] ?? 0) || ($caja['veces_usado'] ?? 0) > 0 ? 'Usado' : 'Nuevo' ?>
                    </span>
                  </div>
                <?php endforeach; ?>
                <?php if (count($cajasState['items']) > 8): ?>
                  <div class="list-group-item text-muted small text-center py-1">+<?= count($cajasState['items']) - 8 ?> más</div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="card-body py-3 text-center text-muted small">Sin ataúdes disponibles.</div>
            <?php endif; ?>
          </div>

          <!-- Equipos por grupo -->
          <div class="card shadow-sm">
            <div class="card-header py-2">
              <span class="fw-semibold">Equipos disponibles</span>
            </div>
            <?php if (!empty($equiposGrupos)): ?>
              <div class="list-group list-group-flush" style="max-height:200px;overflow-y:auto">
                <?php foreach ($equiposGrupos as $grp): ?>
                  <div class="list-group-item py-2 d-flex justify-content-between align-items-center">
                    <span class="small"><?= e($grp['equipo']) ?></span>
                    <span class="badge text-bg-<?= (int)($grp['disponibles'] ?? 0) > 0 ? 'primary' : 'secondary' ?>">
                      <?= (int)($grp['disponibles'] ?? 0) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="card-body py-3 text-center text-muted small">Sin equipos registrados.</div>
            <?php endif; ?>
          </div>

          <!-- Artículos con stock bajo -->
          <?php if (!empty($articulosBajos)): ?>
            <div class="card shadow-sm border-danger">
              <div class="card-header py-2 bg-danger bg-opacity-10">
                <span class="fw-semibold text-danger">Stock bajo (≤ 5 piezas)</span>
              </div>
              <div class="list-group list-group-flush" style="max-height:160px;overflow-y:auto">
                <?php foreach ($articulosBajos as $art): ?>
                  <div class="list-group-item py-2 d-flex justify-content-between align-items-center">
                    <span class="small"><?= e($art['articulo']) ?></span>
                    <span class="badge text-bg-<?= (int)$art['existencias'] === 0 ? 'danger' : 'warning text-dark' ?>">
                      <?= (int)$art['existencias'] ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
  <?php
  render('Panel auxiliar', ob_get_clean());
  return;
}

ob_start(); ?>
<style>
  .kpi-card{border-radius:1rem}
  .kpi-value{font-size:1.6rem;font-weight:700}
  .kpi-sub{font-size:.85rem;color:#6c757d}
  .placeholder-box{min-height:220px;background:linear-gradient(135deg,#f8f9fa,#eef2f6);border:1px dashed #d0d7de;border-radius:1rem}
  .loader-line{height:12px;border-radius:999px;background:linear-gradient(90deg,#eceff3,#f8f9fa,#eceff3);background-size:200% 100%;animation: shimmer 1.4s linear infinite}
  .loader-line + .loader-line{margin-top:.75rem}
  @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>

<div class="py-3 py-md-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 m-0">Panel principal</h1>
      <div class="text-muted">Carga diferida de indicadores y widgets para reducir el tiempo de respuesta inicial.</div>
    </div>
    <?php if ($isAdmin): ?>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="?r=maintenance.perf_report">Rendimiento</a>
        <a class="btn btn-outline-dark btn-sm" href="?r=maintenance.schema_repair">Esquema</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="row g-3 mb-4" id="kpiGrid">
    <?php for ($i = 0; $i < 4; $i++): ?>
      <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm">
          <div class="card-body">
            <div class="loader-line w-75"></div>
            <div class="loader-line w-50 mt-3"></div>
          </div>
        </div>
      </div>
    <?php endfor; ?>
  </div>

  <div id="widgetsArea">
    <div class="placeholder-box d-flex align-items-center justify-content-center">
      <div class="text-center text-muted">
        <div class="loader-line" style="width:220px"></div>
        <div class="loader-line" style="width:320px;margin-top:16px"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script>
async function fetchJson(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  const data = await res.json();
  if (!data.ok) throw new Error(data.message || 'Error');
  return data.data;
}

function currency(value) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0));
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[char]);
}

function renderKpis(data) {
  const items = [
    { title: 'Servicios abiertos', value: Number(data.servicios_abiertos || 0).toLocaleString('es-MX'), sub: `Cerrados: ${Number(data.servicios_cerrados || 0).toLocaleString('es-MX')}` },
    { title: 'Pagos servicio del día', value: currency(data.pagos_servicio_hoy || 0), sub: `Abonos futuros: ${currency(data.abonos_hoy || 0)}` },
    { title: 'Comisiones pendientes', value: Number(data.comisiones_pendientes || 0).toLocaleString('es-MX'), sub: 'Pendientes por revisar' },
    { title: 'Inventario', value: `${Number(data.articulos_total || 0).toLocaleString('es-MX')} artículos`, sub: `Bajo stock: ${Number(data.articulos_bajos || 0).toLocaleString('es-MX')} · Cajas: ${Number(data.cajas_total || 0).toLocaleString('es-MX')}` },
  ];

  document.getElementById('kpiGrid').innerHTML = items.map(item => `
    <div class="col-6 col-md-3">
      <div class="card kpi-card shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">${item.title}</div>
          <div class="kpi-value">${item.value}</div>
          <div class="kpi-sub">${item.sub}</div>
        </div>
      </div>
    </div>
  `).join('');
}

function renderWidgets(data) {
  const widgetsArea = document.getElementById('widgetsArea');
  widgetsArea.innerHTML = `
    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
          <h2 class="h6 mb-3">Recuperado últimos 30 días</h2>
          <canvas id="chartDias" height="180"></canvas>
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
          <h2 class="h6 mb-3">Recuperado últimos 12 meses</h2>
          <canvas id="chartMeses" height="180"></canvas>
        </div></div>
      </div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
          <h2 class="h6 mb-3">Citas del día</h2>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#Contrato</th><th>Titular</th><th>Colonia</th><th>Cobrador</th><th>Estado</th></tr></thead>
              <tbody id="tbodyCitas"></tbody>
            </table>
          </div>
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
          <h2 class="h6 mb-3">Mapa de gestiones atendidas (hoy)</h2>
          <div id="mapGestiones" style="height:300px;border-radius:10px;"></div>
          <p id="mapEmpty" class="text-muted small mt-2 mb-0 text-center d-none">No hay gestiones con ubicación registrada hoy.</p>
        </div></div>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100"><div class="card-body">
          <h2 class="h6 mb-3">Últimos abonos</h2>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#</th><th>Contrato</th><th>Titular</th><th class="text-end">Monto</th><th>Fecha</th></tr></thead>
              <tbody id="tbodyAbonos"></tbody>
            </table>
          </div>
        </div></div>
      </div>
      <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100"><div class="card-body">
          <h2 class="h6 mb-3">Últimas comisiones</h2>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>#</th><th>Contrato</th><th class="text-end">Monto</th><th>Estatus</th><th>Fecha</th></tr></thead>
              <tbody id="tbodyComisiones"></tbody>
            </table>
          </div>
        </div></div>
      </div>
    </div>
  `;

  new Chart(document.getElementById('chartDias'), {
    type: 'bar',
    data: {
      labels: (data.abonos30 || []).map(row => row.fecha),
      datasets: [{ data: (data.abonos30 || []).map(row => Number(row.total || 0)), backgroundColor: 'rgba(54,162,235,0.5)', borderColor: 'rgba(54,162,235,1)', borderWidth: 1 }]
    },
    options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
  });

  new Chart(document.getElementById('chartMeses'), {
    type: 'line',
    data: {
      labels: (data.abonos12 || []).map(row => row.mes),
      datasets: [{ data: (data.abonos12 || []).map(row => Number(row.total || 0)), borderColor: 'rgba(75,192,192,1)', backgroundColor: 'rgba(75,192,192,0.2)', tension: .3, fill: true }]
    },
    options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
  });

  const tbodyCitas = document.getElementById('tbodyCitas');
  const citas = data.citas_hoy || [];
  tbodyCitas.innerHTML = citas.length ? citas.map(row => `
    <tr>
      <td>#${Number(row.id_contrato || 0)}</td>
      <td>${escapeHtml(row.titular || '-')}</td>
      <td>${escapeHtml(row.colonia || '-')}</td>
      <td>${escapeHtml(row.cobrador || '-')}</td>
      <td><span class="badge bg-${row.estado === 'atendida' ? 'success' : (row.estado === 'pendiente' ? 'warning text-dark' : (row.estado === 'vencida' ? 'danger' : 'secondary'))}">${escapeHtml(row.estado || 'sin_programar')}</span></td>
    </tr>
  `).join('') : '<tr><td colspan="5" class="text-muted">No hay citas programadas para hoy.</td></tr>';

  const tbodyAbonos = document.getElementById('tbodyAbonos');
  const abonos = data.ultimos_abonos || [];
  tbodyAbonos.innerHTML = abonos.length ? abonos.map(row => `
    <tr>
      <td>#${Number(row.id_abono || 0)}</td>
      <td>#${Number(row.id_contrato || 0)}</td>
      <td>${escapeHtml(row.titular || '-')}</td>
      <td class="text-end">${currency(row.cant_abono || 0)}</td>
      <td>${escapeHtml(row.fecha || '')}</td>
    </tr>
  `).join('') : '<tr><td colspan="5" class="text-muted">Sin abonos recientes.</td></tr>';

  const tbodyComisiones = document.getElementById('tbodyComisiones');
  const comisiones = data.ultimas_comisiones || [];
  tbodyComisiones.innerHTML = comisiones.length ? comisiones.map(row => `
    <tr>
      <td>#${Number(row.id_bono_sem || 0)}</td>
      <td>#${Number(row.id_contrato || 0)}</td>
      <td class="text-end">${currency(row.monto || 0)}</td>
      <td>${escapeHtml(row.estatus || '-')}</td>
      <td>${escapeHtml(row.fecha || '')}</td>
    </tr>
  `).join('') : '<tr><td colspan="5" class="text-muted">Sin comisiones recientes.</td></tr>';

  const puntos = data.gestiones_hoy || [];
  const map = L.map('mapGestiones').setView([21.122, -101.68], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
  const bounds = [];
  puntos.forEach(p => {
    const lat = parseFloat(p.latitud);
    const lng = parseFloat(p.longitud);
    if (!lat || !lng) return;
    const marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup(`<strong>${escapeHtml(p.titular || 'Sin titular')}</strong><br>${escapeHtml(p.cobrador || 'Sin cobrador')}<br><a href="https://maps.google.com/?q=${lat},${lng}" target="_blank">Abrir en Google Maps</a>`);
    bounds.push([lat, lng]);
  });
  if (bounds.length) {
    map.fitBounds(bounds, { padding: [30, 30] });
  } else {
    document.getElementById('mapEmpty').classList.remove('d-none');
  }
}

async function loadDashboard() {
  try {
    const kpis = await fetchJson('?r=home_api.kpis');
    renderKpis(kpis);
  } catch (error) {
    document.getElementById('kpiGrid').innerHTML = '<div class="col-12"><div class="alert alert-warning">No se pudieron cargar los KPIs.</div></div>';
  }

  try {
    const widgets = await fetchJson('?r=home_api.widgets');
    renderWidgets(widgets);
  } catch (error) {
    document.getElementById('widgetsArea').innerHTML = '<div class="alert alert-warning">No se pudieron cargar los widgets secundarios.</div>';
  }
}

document.addEventListener('DOMContentLoaded', loadDashboard);
</script>
<?php
render('Panel principal', ob_get_clean());
