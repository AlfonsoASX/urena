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
          <li class="nav-item">
            <a class="nav-link" href="AltaProductos.php">Alta Productos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="inventario_general.php">Inventario</a>
          </li>
          <li class="nav-item active">
            <a class="nav-link" href="Servicios.php">Servicios
            <span class="sr-only">(current)</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="ValeSalida.php">Vale Salida</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="logout.php">Cerrar</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Content -->
  <div class="row">
      <div class="col-2 "></div>
      <div class="col-8 text-center text-info ">
        <h3 class="mt-5 font-weight-bold">Buscar Servicio de Velación</h3>
      </div>
    </div>
    <div class="row ">
      <div class="text-center col-2 col-md-1"></div>
      <div class="text-center col-8 col-md-10">
          <div class="row">
            <div class="form-inline mx-auto pt-3">
              <form id="form4" action="ConsultaServicios.php" method="POST">
                <input type="text" style="width: 200px;" class="form-control " placeholder="Nombre|Domicilio|Fecha" id="busqueda" name="busqueda" required pattern="[a-z0-9 \-]+" title="Solo letras, numeros y formato de fecha año-mes-día">
                <button name="form4" type="submit" class="btn btn-info " style="width: 100px;">Buscar</button>
              </form>
              <form id="form2" action="ConsultaServicios.php" method="POST">
                <input type="text" style="width: 200px;" class="form-control " placeholder="Ingresar Id de Servicio" id="ing_serv" name="ing_serv" required pattern="[0-9]+" title="Solo números enteros">
                <button name="form2" type="submit" class="btn btn-info " style="width: 100px;">Buscar</button>
                <a href="ConsultaServicios.php" class="btn btn-info " role="button" style="width: 100px;">Limpiar</a>
              </form>
            </div>
          </div>
        <div class="row">
        <div class="text-center col-1"></div>
          <div class="col-10 mt-3 table-responsive">
             
                <!-- MODULO PARA BUSCAR ARTICULOS POR EL NOMBRE O MARCA DEL ARTICULO -->
                <?php require 'con_consul_serv.php'; ?>
              
          </div>
        </div>

        <div class="row">
          <div class="container-fluid float-left mt-3">
            <!--<a href="ConsultaServicios.php" class="btn btn-info mr-5 ml-5" role="button" style="width: 100px;">Limpiar</a>-->
            <!--<a href="principal.php" class="btn btn-info mr-5 ml-5" role="button" style="width: 100px;">Cerrar</a>-->
          </div>
        </div>
      </div>
    </div>
<?php
 if ($_SESSION['perfil'] == 'administrativo') {
?>
    <div class="row">
      <div class="col-2 "></div>
      <div class="col-8 text-center text-info">
        <h3 class="mt-3 font-weight-bold">Editar Evento</h3>
      </div>
    </div>
    <div class="row ">
      <div class="text-center col-2"></div>
      <div class="text-center col-8">
        <form id="form3" action="con_edit_event.php" method="POST">
          <div class="row">
            <div class="form-inline mx-auto pt-3">
            <input type="text" style="width: 200px;" class="form-control mr-4" placeholder="Ingresar Id del Servicio" id="ing_id_serv" name="ing_id_serv" required pattern="[0-9]+" title="Solo números enteros">  
            <input type="text" style="width: 200px;" class="form-control mr-4" placeholder="Ingresar Evento" id="id_evento" name="id_evento" required pattern="[0-9]+" title="Solo números enteros">
            <button name="form3" type="submit" class="btn btn-info mr-1 ml-" style="width: 100px;">Modificar</button>
            <a href="principal.php" class="btn btn-info" role="button" style="width: 100px;">Cerrar</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="row">
      <div class="col-2 "></div>
      <div class="col-8 text-center text-info">
        <h3 class="mt-3 font-weight-bold">Hacer Cambio de Ataud</h3>
      </div>
    </div>
    <div class="row ">
      <div class="text-center col-2"></div>
      <div class="text-center col-8">
        <form id="form1" action="con_cambio_ataud.php" method="POST">
          <div class="row">
            <div class="form-inline mx-auto pt-3">
            <input type="text" style="width: 200px;" class="form-control mr-1" placeholder="Ingresar Id del Servicio" id="id_serv_xch" name="id_serv_xch" required pattern="[0-9]+" title="Solo números enteros">  
            <input type="text" style="width: 200px;" class="form-control mr-1" placeholder="Ataud Anterior" id="codigo_ant" name="codigo_ant" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
            <input type="text" style="width: 200px;" class="form-control mr-1" placeholder="Ataud Nuevo" id="codigo_nvo" name="codigo_nvo" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
            <button name="form1" type="submit" class="btn btn-info mr-1 ml-" style="width: 100px;">Cambio</button>
            <a href="principal.php" class="btn btn-info mr-1 ml-" role="button" style="width: 100px;">Cerrar</a>
            </div>
          </div>
        </form>
      </div>
    </div>
<?php
 }
?>
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