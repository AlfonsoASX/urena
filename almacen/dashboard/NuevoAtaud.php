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
  <link href="vendor/bootstrap/css/clases.css" rel="stylesheet">

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
          <li class="nav-item active">
            <a class="nav-link" href="principal.php">Menú Principal</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="AltaProductos.php">Alta Productos
            <span class="sr-only">(current)</span></a>
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
          <h2 class="mt-5 font-weight-bold">Alta de Caja Nueva</h2>
        </div>
    </div>

    <div class="row ">
        <div class="col-lg-3 col-sm-1"></div>

        <div class="col-lg-6 col-sm-10 text-center ">

            <form class="" action="NuevoAtaud.php" method="POST" enctype="multipart/form-data">
              
                <div class="form-inline pt-3 mr-5 float-right ">
                    <label class="" for="codigo">Código:</label>
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="codigo" name="codigo" required pattern="[a-z0-9]+" title="Sólo letras minusculas y números">
                </div>
                <div class="form-inline pt-3 mr-5 float-right w-auto ">
                    <label for="tipo">Modelo:</label>
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="modelo" name="modelo" required pattern="[a-z0-9_\u00f1]+" title="Sólo letras minusculas, números y guión bajo">
                </div>
                <div class="form-inline pt-3 mr-5 float-right w-auto ">
                    <label for="tipo">Estado:</label>
                    <select class="form-control" style="width: 300px;"  id="estado" name="estado" required>
                      
                      <option>nuevo</option>
                      <option>reciclado</option>
                    </select>
                </div>
                <div class="form-inline pt-3 mr-5 float-right w-auto ">
                    <label for="tipo">Ubicación:</label>
                    <select class="form-control" style="width: 300px;"  id="ubicacion" name="ubicacion" required>
                      <option>economica</option>
                      <option>independencia</option>
                      <option>capillas</option>
                      <option>almacen</option>
                      <option>romita</option>
                      <option>cueramaro</option>
                    </select>
                </div>
                <div class="form-inline pt-3 mr-5 float-right w-auto ">
                    <label for="color">Color:</label>
                    <select class="form-control" style="width: 300px;"  id="color" name="color" required>
                      <option>arena</option>
                      <option>azulclaro</option>
                      <option>azuloscuro</option>
                      <option>beige</option>
                      <option>blanco</option>
                      <option>cafeclaro</option>
                      <option>cafeoscuro</option>
                      <option>caoba</option>
                      <option>champagne</option>
                      <option>dorado</option>
                      <option>grisclaro</option>
                      <option>grisoscuro</option>
                      <option>lila</option>
                      <option>madera</option>
                      <option>marmoleadabeige</option>
                      <option>marmoleadoarena</option>
                      <option>marmoleadoazul</option>
                      <option>marmoleadocafe</option>
                      <option>marmoleadogris</option>
                      <option>negra</option>
                      <option>rosa</option>
                      <option>verde</option>
                    </select>
                </div>
                <div class="form-inline pt-3 mr-5 float-right w-auto ">
                    <label for="proveedor">Proveedor:</label>
                    <select class="form-control" style="width: 300px;"  id="proveedor" name="proveedor" required>
                      <option value="ataudes_jr">ataudes_jr</option>
                      <option value="industrias_arga">industrias_arga</option>
                      <option value="ataudes_don_leo">ataudes_don_leo</option>
                      <option value="ataudes_aguilar">ataudes_aguilar</option>
                      <option value="ataudes_hidalgo">ataudes_hidalgo</option>
                      <option value="ataudes_chavez">ataudes_chavez</option>
                      <option value="ataudes_martin">ataudes_martin</option>
                      <option value="ataudes_toluca">ataudes_toluca</option>
                      <option value="ataudes_urnas_jose_luis">ataudes_urnas_jose_luis</option>
                      <option value="ataudes_victor">ataudes_victor</option>
                      <option value="ataudes_michoacan">ataudes_michoacan</option>
                      <option value="ataudes_sanher">ataudes_sanher</option>
                    </select>
                </div>
                <div class="form-inline pt-3 mr-5 float-right">
                    <label class="" for="costo">Costo:</label>
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="costo" name="costo" required pattern="[0-9]+" title="Sólo cantidad entera">
                </div>
                <div class="form-inline pt-3 mr-5 float-right">
                    <label>Foto:</label>
                    <input type="file" id="customFile" name="foto">
                    <!--<label class="custom-file-label text-left" for="customFile">Foto</label>-->
                </div>

                <div class="form-inline pt-3 mr-5 float-right">
                    <!--<button type="submit" class="btn btn-info m-4" style="width: 130px;">Guardar</button>-->
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

<?php 

if ($_POST){

      //echo "se mando correctamente el formulario";
      require 'conexion.php';
      $codigo = $_POST['codigo'];
      $modelo = $_POST['modelo'];
      $estado = $_POST['estado'];
      $ubicacion = $_POST['ubicacion'];
      $color = $_POST['color'];
      $proveedor = $_POST['proveedor'];
      $costo = $_POST['costo'];
      $nombre_imagen = $_FILES['foto']['name'];
      $ruta_tmp_img = $_FILES['foto']['tmp_name'];
      $tipo_imagen = $_FILES['foto']['type'];
      $size_imagen = $_FILES['foto']['size'];
      $carpeta_destino = $_SERVER['DOCUMENT_ROOT'] . '/almacen/dashboard/fotos/';
      
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $querya = $pdo->prepare("SELECT codigo FROM cajas");
      $querya->execute();
      $codigos=0;
      while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
          if ($codigo==$rowa['codigo']) {
            $codigos=1;
          }
      }
      if ($codigos==0) {
        if ($nombre_imagen!=NULL) {
        if ($size_imagen<=1000000){
          if($tipo_imagen == "image/jpg" OR $tipo_imagen == "image/jpeg" OR $tipo_imagen == "image/gif" OR $tipo_imagen == "image/png"){
              move_uploaded_file($ruta_tmp_img,$carpeta_destino.$nombre_imagen);
              $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
              $query = $pdo -> prepare("INSERT INTO cajas (codigo, modelo, estado, ubicacion, color, proveedor, costo, foto) 
                                      VALUES(:codigo, :modelo, :estado, :ubicacion, :color, :proveedor, :costo, :foto) ");
              $query->bindParam(':codigo',$codigo);
              $query->bindParam(':modelo',$modelo);
              $query->bindParam(':estado',$estado);
              $query->bindParam(':ubicacion',$ubicacion);
              $query->bindParam(':color',$color);
              $query->bindParam(':proveedor',$proveedor);
              $query->bindParam(':costo',$costo);
              $query->bindParam(':foto',$nombre_imagen);
              $query -> execute();
            }else{
              ?>
                <script>alert("ERROR!!! EL TIPO DE ARCHIVO ES INCORRECTO");</script>
                <?php
                }
                
                }else{
                    ?>
                <script>alert("ERROR!!! EL TAMAÑO DE LA FOTO DEBE SER MENOR A 1 MB");</script>
                <?php
                }    
              }else{
                $nombre_imagen="sinfoto.PNG";
                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
              $query = $pdo -> prepare("INSERT INTO cajas (codigo, modelo, estado, ubicacion, color, proveedor, costo, foto) 
                                      VALUES(:codigo, :modelo, :estado, :ubicacion, :color, :proveedor, :costo, :foto) ");
              $query->bindParam(':codigo',$codigo);
              $query->bindParam(':modelo',$modelo);
              $query->bindParam(':estado',$estado);
              $query->bindParam(':ubicacion',$ubicacion);
              $query->bindParam(':color',$color);
              $query->bindParam(':proveedor',$proveedor);
              $query->bindParam(':costo',$costo);
              $query->bindParam(':foto',$nombre_imagen);
              $query -> execute();
              }
              }else {
                ?>
                <script>alert("ERROR!!! EL CÓDIGO DE ATAUD YA EXISTE");</script>
                <?php
                
              }
}	

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

