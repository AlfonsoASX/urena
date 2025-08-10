<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'cierre_automatico.php';
  if ($_SESSION['perfil'] == 'administrativo') {
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
          <li class="nav-item">
            <a class="nav-link" href="AltaProductos.php">Alta Productos
            <span class="sr-only">(current)</a>
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

  <!-- Page Content -->
  <div class="container">
    <div class="row">
        <div class="col-lg-12 text-center text-info ">
          <h2 class="mt-5 font-weight-bold">Alta de Equipo Nuevo</h2>
        </div>
    </div>

      <div class="row ">
            <div class="col-lg-3 col-sm-1"></div>

            <div class="col-lg-6 col-sm-10 text-center ">

                <form class="" action="con_nuevo_equipo.php" method="POST" enctype="multipart/form-data">
                  
                    <div class="form-inline pt-3 mr-5 float-right w-auto ">
                        <label for="">Equipo:</label>
                        <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="equipo" name="equipo" required pattern="[a-z]+">
                    </div>
                    <div class="form-inline pt-3 mr-5 float-right ">
                        <label class="" for="">Código:</label>
                        <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="id_equipo" name="id_equipo" required pattern="[a-z0-9]+">
                    </div>
                    <div class="form-inline pt-3 mr-5 float-right">
                    <label>Foto:</label>
                    <input type="file" id="customFile" name="foto">
                    </div>
                    

                    <div class="form-inline pt-3 mr-5 float-right">
                        <!--<button type="submit" class="btn btn-info m-4" style="width: 130px;">Guardar</button>-->
                        <input class="btn btn-info m-4" type="submit" value="Guardar" style="width: 130px;" />
                        <a href="principal.php" class="btn btn-info m-4" role="button" style="width: 130px;">Cerrar</a>
                    </div>
                    
                    
                </form>

            </div>
            <div ></div>
      </div>  
  
  </div>  
                


  <!-- Bootstrap core JavaScript -->
  <script src="vendor/jquery/jquery.slim.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

<?php 



?>

</html>

<?php
  }else {
    header("location:principal.php");
  }
}else {
  header("location:index.php");
}
?>