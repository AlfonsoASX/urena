<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="?r=home.index">Ureña</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
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
