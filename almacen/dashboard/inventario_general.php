<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'cierre_automatico.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>Menu</title>

  <!-- Bootstrap core CSS -->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-success static-top">
    <div class="container">
    <img src="img/logo.png" class="rounded-circle">
      <a class="navbar-brand" href="#">Grupo Ureña Funerarios</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarResponsive">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
            <a class="nav-link" href="principal.php">Menú Principal</a>
          </li>
          <li class="nav-item ">
            <a class="nav-link" href="AltaProductos.php">Alta Productos</a>
          </li>
          <li class="nav-item active">
            <a class="nav-link" href="inventario_general.php">Inventario
            <span class="sr-only">(current)</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="Servicios.php">Servicios</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="ValeSalida.php">Vale Salida</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle mr-5" href="#" id="navbardrop" data-toggle="dropdown">
                Usuario
            </a>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="password.php">Contraseña</a>
                <a class="dropdown-item" href="logout.php">Cerrar Sesión</a>
            </div>
        </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Content -->
  <div class="container">
      <div class="row">
        <div class="col-lg-12 text-center">
          <h1 class="mt-5 font-weight-bold text-info">Inventario General</h1>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-12 text-center mt-5">
          <a href="inventario_cajas.php" class="btn btn-outline-info" role="button" style="width: 250px; height: 70px; font-size: 25px;">Inventario Ataudes</a>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-12 text-center mt-5">
          <a href="inventario_articulos.php" class="btn btn-outline-info" role="button" style="width: 250px; height: 70px; font-size: 25px;">Inventario Articulos</a>
        </div>
      </div>
      
  </div>

  <!-- Bootstrap core JavaScript -->
  <script src="vendor/jquery/jquery.slim.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>

<?php
}else {
  header("location:index.php");
}
?>