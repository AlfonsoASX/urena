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
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet" type='text/css'>

  
</head>

<body>

  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-success  static-top">
    <div class="container">
    <img src="img/logo.png" class="rounded-circle">
      <h1 class="navbar-brand" href="#">Grupo Ureña Funerarios</h1>
      
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarResponsive">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item active">
            <a class="nav-link" href="#">Menú Principal
              <span class="sr-only">(current)</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="AltaProductos.php">Alta Productos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="inventario_general.php">Inventario</a>
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
  <label class=""><?php echo "Bienvenido: " .$_SESSION['nombre'];?></label>
  <!-- Page Content -->
  <div class="container">
  <div class="row">
        <div class="col-lg-12 text-center text-info">
          <h1 class="mt-3 font-weight-bold manuscrita">Control de Almacén</h1>
        </div>
    </div>
    <div class="row">
      <div class="col-lg-12 text-center mt-2">
        <a href="AltaProductos.php" class="btn btn-outline-info mt-2" role="button" style="width: 250px; height: 70px; font-size: 25px;">Alta de Productos</a>
        
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12 text-center mt-2">
        
        <a href="inventario_general.php" class="btn btn-outline-info mt-2" role="button" style="width: 250px; height: 70px; font-size: 25px;">Inventario General</a>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12 text-center mt-2">
        <a href="Servicios.php" class="btn btn-outline-info mt-2" role="button" style="width: 250px; height: 70px; font-size: 25px;">Servicio de Velación</a>
        
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12 text-center mt-2">
        
        <a href="ValeSalida.php" class="btn btn-outline-info mt-2" role="button" style="width: 250px; height: 70px; font-size: 25px;">Salida de Articulos</a>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-12 text-center mt-5">
        
      </div>
    </div>
    
    <div class="row">
      <div class="col-lg-12 text-center mt-5">
        
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