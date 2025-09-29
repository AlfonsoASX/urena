<?php
// app/modules/home.php
require_once __DIR__."/../core/db.php";
require_once __DIR__."/../core/helpers.php";
require_once __DIR__."/../core/ui.php";
require_once __DIR__."/../core/auth.php";

require_login();

// (Opcional) Puedes traer contadores rápidos si quieres
function kpi($sql, $params = []) {
  try { $r = qone($sql, $params); return (int)array_values($r)[0]; } catch(Exception $e){ return 0; }
}
$kpi_articulos = kpi("SELECT COUNT(*) FROM articulos WHERE COALESCE(eliminado,0)=0");
$kpi_cajas     = kpi("SELECT COUNT(*) FROM cajas WHERE COALESCE(eliminado,0)=0");
$kpi_serv_ab   = kpi("SELECT COUNT(*) FROM servicios s WHERE NOT EXISTS(SELECT 1 FROM futuro_contrato_estatus ce WHERE ce.id_contrato=s.id_servicio AND ce.estatus='cerrado')");
$kpi_contratos = kpi("SELECT COUNT(*) FROM futuro_contratos");

ob_start(); ?>

<div class="mb-3">
  <h1 class="h4 mb-1">Grupo Ureña Funerarias</h1>
  <div class="text-muted">Panel principal · Accesos rápidos</div>
</div>

<?php if (!empty($_SESSION['_alerts'])): 

  echo $_SESSION['_alerts']; 
  unset($_SESSION['_alerts']); 

endif; ?>

<div class="row g-3">

  <!-- Servicios -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Servicios</h5>
        <p class="card-text text-muted">
          Alta y gestión de servicios (cremación o inhumación), renta de ataúd, auxiliares y folios.
        </p>
        <div class="mt-auto d-grid gap-2">
          <a href="?r=servicios.listar" class="btn btn-primary">Abrir servicios</a>
          <div class="small text-muted">Abiertos aprox.: <?= number_format($kpi_serv_ab) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Fallecidos -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Fallecidos</h5>
        <p class="card-text text-muted">
          Registro de fallecidos y domicilios de velación. Vínculo con servicios activos.
        </p>
        <div class="mt-auto d-grid gap-2">
          <a href="?r=fallecidos.listar" class="btn btn-outline-primary">Gestionar fallecidos</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Inventario general -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Inventario general</h5>
        <p class="card-text text-muted">
          Artículos, compras (entradas) y vales (salidas). Controla existencias al día.
        </p>
        <div class="d-grid gap-2">
          <a href="?r=articulos.listar" class="btn btn-outline-primary">Artículos (<?= $kpi_articulos ?>)</a>
          <a href="?r=compras.nuevo" class="btn btn-outline-success">Nueva compra (entrada)</a>
          <a href="?r=vales.nuevo" class="btn btn-outline-secondary">Nuevo vale (salida)</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Ataúdes (cajas) -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Ataúdes (Cajas)</h5>
        <p class="card-text text-muted">
          Modelos, estados (nuevo, en uso, reciclado, fuera de uso), proveedor y costo.
        </p>
        <div class="mt-auto d-grid gap-2">
          <a href="?r=cajas.listar" class="btn btn-outline-primary">Gestionar ataúdes (<?= $kpi_cajas ?>)</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Equipos -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Equipos</h5>
        <p class="card-text text-muted">
          Control de equipos (carpas, sillas, audio, etc.). Entradas y asignación a servicios.
        </p>
        <div class="mt-auto d-grid gap-2">
          <a href="?r=equipos.listar" class="btn btn-outline-primary">Inventario de equipos</a>
          <a href="?r=vales.listar" class="btn btn-outline-secondary">Vales de salida</a>
        </div>
      </div>
    </div>
  </div>

  <!-- NUEVO: Pagos y comisiones -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-primary">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Pagos (Abonos) &amp; Comisiones</h5>
        <p class="card-text text-muted">
          Controla abonos de servicios pre-vendidos, corte por persona y captura de comisiones.
        </p>
        <div class="mt-auto d-grid gap-2">
          <a href="?r=pagos.contratos" class="btn btn-primary">Contratos y saldos</a>
          <a href="?r=pagos.corte" class="btn btn-outline-primary">Corte por persona</a>
          <a href="?r=pagos.comisiones" class="btn btn-outline-secondary">Capturar comisión</a>
          <div class="small text-muted">Contratos: <?= number_format($kpi_contratos) ?></div>
        </div>
      </div>
    </div>
  </div>


  <!-- Proveedores / Usuarios -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title">Administración</h5>
        <p class="card-text text-muted">
          Proveedores (altas, bajas, cambios) y usuarios por perfil (vendedores, auxiliares, administradoras).
        </p>
        <div class="mt-auto d-grid gap-2">
          <a href="?r=proveedores.listar" class="btn btn-outline-primary">Proveedores</a>
        </div>
      </div>
    </div>
  </div>

</div>

<?php
render('Inicio', ob_get_clean());
