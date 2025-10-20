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



  <!-- Gráficas de recuperación -->
  <div class="row g-3 mb-4">
    <?php
    // ===== Últimos 30 días =====
    try {
      $abonos30 = qall("
        SELECT DATE(fecha_registro) AS fecha, 
               COALESCE(SUM(cant_abono),0) AS total
        FROM futuro_abonos
        WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(fecha_registro)
        ORDER BY fecha ASC
      ");
    } catch (Throwable $e) { $abonos30 = []; }

    // ===== Últimos 12 meses =====
    try {
      $abonos12 = qall("
        SELECT DATE_FORMAT(fecha_registro, '%Y-%m') AS mes, 
               COALESCE(SUM(cant_abono),0) AS total
        FROM futuro_abonos
        WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
        ORDER BY mes ASC
      ");
    } catch (Throwable $e) { $abonos12 = []; }

    // Convertir a arrays JS
    $dias_labels = array_map(fn($r)=>$r['fecha'], $abonos30);
    $dias_values = array_map(fn($r)=>(float)$r['total'], $abonos30);
    $mes_labels  = array_map(fn($r)=>$r['mes'], $abonos12);
    $mes_values  = array_map(fn($r)=>(float)$r['total'], $abonos12);
    ?>
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
  document.addEventListener('DOMContentLoaded', function(){
    const diasLabels = <?= json_encode($dias_labels) ?>;
    const diasData   = <?= json_encode($dias_values) ?>;
    const mesesLabels= <?= json_encode($mes_labels) ?>;
    const mesesData  = <?= json_encode($mes_values) ?>;

    // ====== Últimos 30 días ======
    new Chart(document.getElementById('chartDias'), {
      type: 'bar',
      data: {
        labels: diasLabels,
        datasets: [{
          label: 'Monto recuperado ($)',
          data: diasData,
          borderWidth: 1,
          backgroundColor: 'rgba(54, 162, 235, 0.5)',
          borderColor: 'rgba(54, 162, 235, 1)',
        }]
      },
      options: {
        scales: {
          y: { beginAtZero: true, ticks: { callback: val => '$' + val.toLocaleString() } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => '$' + ctx.parsed.y.toLocaleString() } }
        }
      }
    });

    // ====== Últimos 12 meses ======
    new Chart(document.getElementById('chartMeses'), {
      type: 'line',
      data: {
        labels: mesesLabels.map(m => m.replace('-', '/')),
        datasets: [{
          label: 'Monto recuperado ($)',
          data: mesesData,
          borderWidth: 2,
          fill: true,
          tension: 0.3,
          borderColor: 'rgba(75, 192, 192, 1)',
          backgroundColor: 'rgba(75, 192, 192, 0.2)'
        }]
      },
      options: {
        scales: {
          y: { beginAtZero: true, ticks: { callback: val => '$' + val.toLocaleString() } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => '$' + ctx.parsed.y.toLocaleString() } }
        }
      }
    });
  });
  </script>






  <!-- Acciones rápidas -->
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h5">Pagos</h2>
          <form class="row g-2 align-items-end mb-3" method="get">
            <input type="hidden" name="r" value="pagos.nuevo_abono">
            <div class="col-12 col-sm-6">
              <label class="form-label">ID de contrato</label>
              <input type="number" class="form-control" name="id_contrato" min="1" required>
            </div>
            <div class="col-12 col-sm-6 d-grid">
              <button class="btn btn-primary">Capturar abono</button>
            </div>
          </form>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="?r=pagos.contratos">Ver contratos</a>
            <a class="btn btn-outline-secondary btn-sm" href="?r=pagos.comisiones">Capturar comisión</a>
            <a class="btn btn-outline-dark btn-sm" href="?r=pagos.corte&tipo=cobrador">Corte por cobrador</a>
            <a class="btn btn-outline-dark btn-sm" href="?r=pagos.corte&tipo=vendedor">Corte por vendedor</a>
          </div>
        </div>
      </div>
    </div>


<div class="col-12 col-md-6 col-lg-3">
  <div class="card shadow-sm h-100">
    <div class="card-body d-grid">
      <h2 class="h6">Contratos</h2>
      <a class="btn btn-primary" href="?r=contratos.nuevo">Nuevo contrato</a>
      <a class="btn btn-outline-secondary mt-2" href="?r=pagos.contratos">Ver todos</a>
    </div>
  </div>
</div>



<!-- ===== RUTAS: Accesos rápidos ===== -->
<section class="mb-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
    <h2 class="h5 m-0">Rutas · Contratos & Cobradores</h2>
    <div class="d-flex gap-2">
      <a href="?r=rutas.contratos" class="btn btn-outline-primary btn-sm">
        Ver contratos & asignar cobrador
      </a>
      <a href="?r=rutas.cobradores" class="btn btn-outline-secondary btn-sm">
        Ver lista de cobradores
      </a>
    </div>
  </div>

  <!-- Tarjetas de acceso -->
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center mb-2">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
              <span class="text-primary fw-bold">RC</span>
            </div>
            <h3 class="h6 m-0">Contratos → Asignación de cobrador</h3>
          </div>
          <p class="text-muted small mb-3">
            Consulta todos los contratos y asigna o reasigna cobrador por fila. 
            Soporta asignación masiva con selección múltiple.
          </p>
          <div class="mt-auto d-flex justify-content-between align-items-center">
            <a href="?r=rutas.contratos" class="btn btn-sm btn-primary">Ir a contratos</a>
          </div>
          
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center mb-2">
            <div class="rounded-circle bg-secondary bg-opacity-10 p-2 me-2">
              <span class="text-secondary fw-bold">CB</span>
            </div>
            <h3 class="h6 m-0">Cobradores → Rutas</h3>
          </div>
          <p class="text-muted small mb-3">
            Ve el listado de cobradores y, al dar clic, consulta todos los contratos 
            actualmente asignados a cada uno.
          </p>
          <div class="mt-auto d-flex justify-content-between align-items-center">
            <a href="?r=rutas.cobradores" class="btn btn-sm btn-secondary">Ir a cobradores</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Buscador rápido hacia rutas.contratos (opcional) -->
  <form class="row g-2 mt-3" method="get" action="">
    <input type="hidden" name="r" value="rutas.contratos">
    <div class="col-md-9">
      <input type="search" name="q" class="form-control" placeholder="Buscar contrato por folio, titular o vendedor…">
    </div>
    <div class="col-md-3 d-grid">
      <button class="btn btn-outline-primary">Buscar en contratos</button>
    </div>
  </form>
</section>



  </div>

  <!-- Listas rápidas -->
  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h6 mb-3">Últimos abonos</h2>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr>
                <th>#</th><th>Contrato</th><th>Titular</th><th class="text-end">Monto</th><th>Fecha</th><th></th>
              </tr></thead>
              <tbody>
                <?php if(!$abonos): ?>
                  <tr><td colspan="6" class="text-muted">Sin abonos recientes.</td></tr>
                <?php else: foreach($abonos as $a): ?>
                  <tr>
                    <td><?= (int)$a['id_abono'] ?></td>
                    <td>#<?= (int)$a['id_contrato'] ?></td>
                    <td><?= e($a['titular'] ?? '') ?></td>
                    <td class="text-end">$<?= number_format((float)$a['cant_abono'],2) ?></td>
                    <td><?= e($a['fecha']) ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="?r=pagos.nuevo_abono&id_contrato=<?= (int)$a['id_contrato'] ?>">Ver</a></td>
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
              <thead><tr>
                <th>#</th><th>Contrato</th><th class="text-end">Monto</th><th>Estatus</th><th>Fecha</th><th></th>
              </tr></thead>
              <tbody>
                <?php if(!$comisiones): ?>
                  <tr><td colspan="6" class="text-muted">Sin registros de comisión.</td></tr>
                <?php else: foreach($comisiones as $c): ?>
                  <tr>
                    <td><?= (int)$c['id_bono_sem'] ?></td>
                    <td>#<?= (int)$c['id_contrato'] ?></td>
                    <td class="text-end">$<?= number_format((float)$c['monto'],2) ?></td>
                    <td>
                      <?php $st = strtolower($c['estatus'] ?? ''); 
                            $badge = ($st==='pagada') ? 'success' : (($st==='pendiente'||$st==='por pagar'||$st==='por_pagar') ? 'warning' : 'secondary'); ?>
                      <span class="badge bg-<?= $badge ?>"><?= e($c['estatus'] ?? '-') ?></span>
                    </td>
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

  <!-- Atajos de operación -->
  <div class="row g-3 mt-1">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-grid">
          <h2 class="h6">Servicios</h2>
          <a class="btn btn-primary" href="?r=servicios.listar">Abrir servicios</a>
          <a class="btn btn-outline-secondary mt-2" href="?r=fallecidos.listar">Fallecidos</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-grid">
          <h2 class="h6">Inventario</h2>
          <a class="btn btn-outline-primary" href="?r=articulos.listar">Artículos</a>
          <a class="btn btn-outline-secondary mt-2" href="?r=cajas.listar">Cajas/Ataúdes</a>
          <a class="btn btn-outline-secondary mt-2" href="?r=equipos.listar">Equipos</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-grid">
          <h2 class="h6">Pagos</h2>
          <a class="btn btn-outline-primary" href="?r=pagos.contratos">Contratos</a>
          <a class="btn btn-outline-secondary mt-2" href="?r=pagos.corte&tipo=cobrador">Corte cobrador</a>
          <a class="btn btn-outline-secondary mt-2" href="?r=pagos.corte&tipo=vendedor">Corte vendedor</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-grid">
          <h2 class="h6">Proveedores</h2>
          <a class="btn btn-outline-primary" href="?r=proveedores.listar">Gestionar proveedores</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const sel = document.getElementById('personaSelect');
    const btnCob = document.getElementById('btnCorteCobrador');
    const btnVen = document.getElementById('btnCorteVendedor');
    function updateLinks(){
      const v = sel ? sel.value : '';
      const dis = !v;
      [btnCob,btnVen].forEach(b=>{
        if(!b) return;
        b.classList.toggle('disabled', dis);
        b.setAttribute('aria-disabled', dis?'true':'false');
        b.href = dis ? '#' : (b.id==='btnCorteCobrador' ? ('?r=pagos.corte&tipo=cobrador&id_personal='+v) : ('?r=pagos.corte&tipo=vendedor&id_personal='+v));
      });
    }
    if(sel){ sel.addEventListener('change', updateLinks); updateLinks(); }
  })();
</script>

<?php
// Render con layout base
render('Inicio', ob_get_clean());