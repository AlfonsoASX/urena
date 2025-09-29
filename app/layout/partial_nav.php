<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="?r=home.index">Ureña</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="?r=articulos.listar">Artículos</a></li>
        <li class="nav-item"><a class="nav-link" href="?r=cajas.listar">Cajas</a></li>
        <li class="nav-item"><a class="nav-link" href="?r=servicios.listar">Servicios</a></li>
      </ul>
    </div>
    <span class="navbar-text text-white">
      <?= logged_in() ? e($_SESSION['user']['nombre']) : '' ?>
    </span>
  </div>
</nav>
