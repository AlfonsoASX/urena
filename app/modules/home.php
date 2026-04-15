<?php
require_once __DIR__."/../core/db.php";
require_once __DIR__."/../core/helpers.php";
require_once __DIR__."/../core/auth.php";

require_login();

$isAdmin = user_has_role(['administradora', 'admin']);

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
