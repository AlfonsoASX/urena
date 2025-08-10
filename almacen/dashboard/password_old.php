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

  <title>Password</title>

  <!-- Bootstrap core CSS -->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

  <script>
    function validacion(){
        var pw_nw = document.getElementById('pass_new').value;
        var pw_cf = document.getElementById('conf_pass').value;
        if (pw_nw != pw_cf) {
            alert('[ERROR] La nueva contraseña y la confirmación deben coincidir');
            return false;
        }else{
            return true;
        }        
    }

  </script>

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
                <a class="dropdown-item" href="#">Contraseña</a>
                <a class="dropdown-item" href="logout.php">Cerrar Sesión</a>
            </div>
        </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Content -->
  <div class="container">
  <?php require 'con_password.php'; ?>
    <div class="row">
        <div class="col-lg-12 text-center text-info ">
          <h2 class="mt-3 font-weight-bold">Cambio de Contraseña</h2>
        </div>
    </div>

    <div class="row ">
        <div class="col-lg-3 col-sm-1"></div>

        <div class="col-lg-6 col-sm-10 text-center ">

            <form class="" action="password.php" method="POST" onsubmit="return validacion()">
               
                <div class="form-inline pt-3 mr-4 float-right ">
                    <label class="" for="pass_ant">Contraseña Actual:</label>
                    <input type="password" class="form-control" style="width: 300px;"  placeholder="" id="pass_ant" name="pass_ant" required pattern="[a-zA-Z0-9]+" title="Sólo letras minusculas y números">
                </div>
                <div class="form-inline pt-3 mr-4 float-right ">
                    <label class="" for="pass_new">Constraseña Nueva:</label>
                    <input type="password" class="form-control" style="width: 300px;"  placeholder="" id="pass_new" name="pass_new" required pattern="[a-zA-Z0-9]+" title="Sólo letras minusculas y números">
                </div>
                <div class="form-inline pt-3 mr-4 float-right ">
                    <label class="" for="conf_pass">Confirmar Contraseña Nueva:</label>
                    <input type="password" class="form-control" style="width: 300px;"  placeholder="" id="conf_pass" name="conf_pass" required pattern="[a-zA-Z0-9]+" title="Sólo letras minusculas y números">
                </div>
                <div class="form-inline pt-3 mr-4 float-right">
                    <input class="btn btn-info m-4" type="submit" value="Guardar" style="width: 130px;" />
                    <a href="principal.php" class="btn btn-info m-4" role="button" style="width: 130px;">Cerrar</a>
                </div>                
            </form>
            
        </div>
        <div class=""></div>
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