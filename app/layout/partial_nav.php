<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <img src="img/logo.png">
    <a class="navbar-brand" href="?r=home.index">Inicio</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
  

<!--        

      <ul class="navbar-nav me-auto">
        <?php if (user_has_role(['administradora','auxiliar','vendedor','cobrador'])): ?>
          <li class="nav-item"><a class="nav-link" href="?r=servicios.listar">Servicios</a></li>
        <?php endif; ?>
        <?php if (user_has_role(['administradora','auxiliar'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="invDrop" role="button" data-bs-toggle="dropdown">Inventario</a>
            <ul class="dropdown-menu" aria-labelledby="invDrop">
              <li><a class="dropdown-item" href="?r=articulos.listar">Artículos</a></li>
              <li><a class="dropdown-item" href="?r=cajas.listar">Ataúdes</a></li>
              <li><a class="dropdown-item" href="?r=equipos.listar">Equipos</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <?php if (user_has_role(['administradora','cobrador','vendedor'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="pagosDrop" role="button" data-bs-toggle="dropdown">Pagos</a>
            <ul class="dropdown-menu" aria-labelledby="pagosDrop">
              <li><a class="dropdown-item" href="?r=pagos.contratos">Contratos</a></li>
              <li><a class="dropdown-item" href="?r=pagos.corte">Corte por persona</a></li>
              <li><a class="dropdown-item" href="?r=pagos.comisiones">Comisiones</a></li>
            </ul>
          </li>
        <?php endif; ?>
        <?php if (user_has_role(['administradora'])): ?>
          <li class="nav-item"><a class="nav-link" href="?r=proveedores.listar">Proveedores</a></li>
        <?php endif; ?>
      </ul>
        -->



<ul class="navbar-nav me-auto">
  <?php
    $navServiciosRoles = ['administradora','auxiliar','vendedor','coordinador','caja'];
    $navServicioPagosRoles = ['administradora','vendedor','coordinador','caja'];
    $navInventarioRoles = ['administradora','auxiliar'];
    $navCanServicios = user_has_role($navServiciosRoles)
      || user_has_permission(['servicios.ver','servicios.crear','servicios.editar','servicios.cerrar','servicios.borrar']);
    $navCanServicioPagos = user_has_role($navServicioPagosRoles)
      || user_has_permission('servicio_pagos.ver');
    $navCanArticulos = user_has_permission_or_legacy_role(['articulos.ver','articulos.admin'], $navInventarioRoles);
    $navCanCajas = user_has_permission_or_legacy_role(['cajas.ver','cajas.admin'], $navInventarioRoles);
    $navCanEquipos = user_has_permission_or_legacy_role(['equipos.ver','equipos.admin'], $navInventarioRoles);
    $navCanAlmacenes = user_has_permission_or_legacy_role(['almacenes.ver','almacenes.admin'], $navInventarioRoles);
    $navHasServicios = $navCanServicios || $navCanServicioPagos;
    $navHasInventario = $navCanArticulos || $navCanCajas || $navCanEquipos || $navCanAlmacenes;
  ?>
  <?php if ($navHasServicios || $navHasInventario): ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="serviciosDrop" role="button" data-bs-toggle="dropdown">
        Servicios Funerarios
      </a>
      <ul class="dropdown-menu" aria-labelledby="serviciosDrop">
        <?php if ($navCanServicios): ?>
          <li><a class="dropdown-item" href="?r=servicios.listar">Servicios</a></li>
        <?php endif; ?>
        <?php if ($navCanServicioPagos): ?>
          <li><a class="dropdown-item" href="?r=servicio_pagos.listar">Pagos de servicios</a></li>
        <?php endif; ?>
        <?php if ($navHasServicios && $navHasInventario): ?>
          <li><hr class="dropdown-divider"></li>
        <?php endif; ?>
        <?php if ($navCanArticulos): ?>
          <li><a class="dropdown-item" href="?r=articulos.listar">Articulos</a></li>
        <?php endif; ?>
        <?php if ($navCanCajas): ?>
          <li><a class="dropdown-item" href="?r=cajas.listar">Ataudes</a></li>
        <?php endif; ?>
        <?php if ($navCanEquipos): ?>
          <li><a class="dropdown-item" href="?r=equipos.listar">Equipos</a></li>
        <?php endif; ?>
        <?php if ($navCanAlmacenes): ?>
          <li><a class="dropdown-item" href="?r=almacenes.listar">Almacenes</a></li>
        <?php endif; ?>
      </ul>
    </li>
  <?php endif; ?>
<!--
  <?php if (user_has_role(['administradora','auxiliar','vendedor','cobrador'])): ?>
    <li class="nav-item">
      <a class="nav-link" href="?r=servicios.listar">Servicios</a>
    </li>
  <?php endif; ?>
  <?php if (user_has_role(['administradora','auxiliar'])): ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="invDrop" role="button" data-bs-toggle="dropdown">
        Inventario
      </a>
      <ul class="dropdown-menu" aria-labelledby="invDrop">
        <li><a class="dropdown-item" href="?r=articulos.listar">Artículos</a></li>
        <li><a class="dropdown-item" href="?r=compras.listar">Compras</a></li>
        <li><a class="dropdown-item" href="?r=vales.listar">Vales de salida</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="?r=cajas.listar">Ataúdes</a></li>
        <li><a class="dropdown-item" href="?r=equipos.listar">Equipos</a></li>
      </ul>
    </li>
  <?php endif; ?>

  <?php if (user_has_role(['administradora','auxiliar','vendedor'])): ?>
    <li class="nav-item">
      <a class="nav-link" href="?r=fallecidos.listar">Fallecidos</a>
    </li>
  <?php endif; ?>
-->

  <?php if (user_has_role(['administradora','vendedor','futuroadmivo'])): ?>

    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="pagosDrop" role="button" data-bs-toggle="dropdown">
        Futuros
      </a>
      <ul class="dropdown-menu" aria-labelledby="pagosDrop">
        <li><a class="dropdown-item" href="?r=contratos.listar">Lista</a></li>
        <li><a class="dropdown-item" href="?r=promociones.listar">Promociones</a></li>
        <li><a class="dropdown-item" href="?r=pagos.contratos">Pagos</a></li>
        <li><a class="dropdown-item" href="?r=rutas.contratos">Armado de rutas</a></li>
        <li><a class="dropdown-item" href="?r=cobrador.contratos">Vista cobrador</a></li>
      </ul>
    </li>

    <li class="nav-item">
    </li>
  <?php endif; ?>

  <?php if (user_has_role(['administradora','vendedor','futuroadmivo'])): ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="pagosDrop" role="button" data-bs-toggle="dropdown">
        Pagos
      </a>
      <ul class="dropdown-menu" aria-labelledby="pagosDrop">
        <li><a class="dropdown-item" href="?r=pagos.contratos">Contratos</a></li>
        <li><a class="dropdown-item" href="?r=pagos.reporte_comisiones">Comisiones vendedores</a></li>
        <li><a class="dropdown-item" href="?r=pagos.reporte_lideres">Comisiones lideres</a></li>
        <li><a class="dropdown-item" href="?r=pagos.reporte_gerentes">Comisiones gerentes</a></li>
        <li><a class="dropdown-item" href="?r=pagos.reporte_empresa">Distribucion empresa</a></li>
        <li><a class="dropdown-item" href="?r=pagos.cortes_cobradores">Cortes de cobradores</a></li>
        <li><a class="dropdown-item" href="?r=pagos.reporte_cobradores">Cobros por rango de fechas</a></li>
        <?php if (user_has_permission_or_legacy_role('pagos.admin', ['administradora','futuroadmivo'])): ?>
          <li><a class="dropdown-item" href="?r=pagos.configurar_cortes">Configurar corte semanal</a></li>
        <?php endif; ?>
<!--        <li><a class="dropdown-item" href="?r=pagos.comisiones">Comisiones manuales</a></li> -->
        <li><a class="dropdown-item" href="?r=pagos.reporte_base">Reporte de pagos/promocion</a></li>

        
      </ul>
    </li>
  <?php endif; ?>

  <?php if (user_has_role(['administradora','cobrador','futuroadmivo'])): ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="cobDrop" role="button" data-bs-toggle="dropdown">
        Cobrador
      </a>
      <ul class="dropdown-menu" aria-labelledby="cobDrop">
        <li><a class="dropdown-item" href="?r=cobrador.panel">Panel</a></li>
        <li><a class="dropdown-item" href="?r=cobrador.contratos">Contratos</a></li>

      </ul>
    </li>
  <?php endif; ?>
  <?php
    $navCanUsuarios = user_has_permission('usuarios.admin') || user_has_role(['administradora','admin','futuroadmivo']);
    $navCanMigraciones = user_is_super();
  ?>
  <?php if ($navCanUsuarios || $navCanMigraciones): ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="configDrop" role="button" data-bs-toggle="dropdown">Configuracion</a>
      <ul class="dropdown-menu" aria-labelledby="configDrop">
        <?php if ($navCanUsuarios): ?><li><a class="dropdown-item" href="?r=usuarios.listar">Usuarios y permisos</a></li><?php endif; ?>
        <?php if ($navCanMigraciones): ?>
          <li><a class="dropdown-item" href="?r=migraciones.index">Centro de migraciones</a></li>
          <li><a class="dropdown-item" href="?r=maintenance.schema_repair">Mantenimiento de esquema</a></li>
        <?php endif; ?>
      </ul>
    </li>
  <?php endif; ?>
<!--
  <?php if (user_has_role(['administradora'])): ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="catDrop" role="button" data-bs-toggle="dropdown">
        Catálogos
      </a>
      <ul class="dropdown-menu" aria-labelledby="catDrop">
        <li><a class="dropdown-item" href="?r=proveedores.listar">Proveedores</a></li>

        <li><a class="dropdown-item" href="?r=rutas.contratos">Rutas</a></li>
        <li><a class="dropdown-item" href="?r=rutas.cobradores">Cobradores</a></li>
      </ul>
    </li>
  <?php endif; ?>
-->
</ul>


      <ul class="navbar-nav ms-auto">
        <?php if (logged_in()): ?>
          <li class="nav-item"><span class="navbar-text text-white me-3"><?= e(current_user()['nombre'] ?? current_user()['usuario'] ?? '') ?> (<?= e(current_user()['perfil'] ?? '-') ?>)</span></li>
          <li class="nav-item"><a class="nav-link" href="?r=auth.logout">Salir</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="?r=auth.login">Entrar</a></li>
        <?php endif; ?>
      </ul>
    </div>

  </div>
</nav>
