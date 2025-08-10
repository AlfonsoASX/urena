<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'cierre_automatico.php';
  require 'conexion.php';
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

  <script>
      function validacion(){
        var bb = document.getElementById('biombo').value;
        var cd = document.getElementById('candeleros').value
        var ca = document.getElementById('carpa').value;
        var cr = document.getElementById('cristo_angel').value;
        var pd = document.getElementById('pedestal').value;
        var tr = document.getElementById('torcheros').value;
        var fl = document.getElementById('floreros').value;
        var sill = document.getElementById('sillas');
        var bandera=0;
        for (let i = 0; i < sill.options.length; i++) {
              if (sill.options[i].selected === true) {
                bandera = bandera + 1;
                }
            }
        if (sill.options[0].selected === true && bandera > 1) {
                alert('[ERROR] La seleccion de sillas es erronea');
                return false;
        }else if (bb === 'bb1sinbiombo' && cd === 'cd1sincandelero' && ca === 'ca1sincarpa' && cr === 'cr1sincristo'
                  && pd === 'pd1sinpedestal' && tr === 'tr1sintorchero' 
                  && fl === 'fl1sinflorero' && sill.options[0].selected === true){          
                  alert('[ERROR] Se debe seleccionar al menos un equipo valido');
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
            <a class="nav-link" href="AltaProductos.php">Alta Productos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="inventario_general.php">Inventario</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="Servicios.php">Servicios
            <span class="sr-only">(current)</span>
            </a>
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
    <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2"></div>
        <div class="col-xl-6 col-lg-7 col-md-2 col-sm-2 text-center text-info">
          <h2 class="mt-5 font-weight-bold">Entrada de Equipo de Velación</h2>
        </div>
    </div>

    <div class="row ">
    <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2"></div>
        <div class="col-xl-6 col-lg-7 col-md-2 col-sm-2 text-center">

            <form class="" action="con_entrada_equipo.php" method="POST" onsubmit="return validacion()">
              
            <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="material">Seleccionar Biombo:</label>
                    <select class="form-control" style="width: 300px;"  id="biombo" name="biombo">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $querya = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'biombo' AND estatus = 'ocupado'");
                      $querya->execute();
                      while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowa['id_equipo'] . '>' . $rowa['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="medida">Seleccionar Pedestal:</label>
                    <select class="form-control" style="width: 300px;"  id="pedestal" name="pedestal">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryb = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'pedestal' AND estatus = 'ocupado'");
                      $queryb->execute();
                      while ($rowb = $queryb->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowb['id_equipo'] . '>' . $rowb['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="proveedor">Seleccionar torcheros:</label>
                    <select class="form-control" style="width: 300px;"  id="torcheros" name="torcheros">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryc = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'torcheros' AND estatus = 'ocupado'");
                      $queryc->execute();
                      while ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowc['id_equipo'] . '>' . $rowc['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Seleccionar candeleros:</label>
                    <select class="form-control" style="width: 300px;"  id="candeleros" name="candeleros">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryd = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'candeleros' AND estatus = 'ocupado'");
                      $queryd->execute();
                      while ($rowd = $queryd->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowd['id_equipo'] . '>' . $rowd['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Seleccionar Cristo/Angel:</label>
                    <select class="form-control" style="width: 300px;"  id="cristo_angel" name="cristo_angel">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $querye = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'cristo_angel' AND estatus = 'ocupado'");
                      $querye->execute();
                      while ($rowe = $querye->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowe['id_equipo'] . '>' . $rowe['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Seleccionar floreros:</label>
                    <select class="form-control" style="width: 300px;"  id="floreros" name="floreros">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryf = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'floreros' AND estatus = 'ocupado'");
                      $queryf->execute();
                      while ($rowf = $queryf->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowf['id_equipo'] . '>' . $rowf['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Seleccionar Carpa:</label>
                    <select class="form-control" style="width: 300px;"  id="carpa" name="carpa">
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryg = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'carpa' AND (estatus = 'ocupado' OR id_equipo = 'casincarpa')");
                      $queryg->execute();
                      while ($rowg = $queryg->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowg['id_equipo'] . '>' . $rowg['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Seleccionar sillas de color:</label>
                    <select multiple class="form-control" style="width: 300px; height: 100px;"  id="sillas" name="sillas[]" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryh = $pdo->prepare("SELECT id_equipo FROM equipos WHERE equipo = 'sillas' AND estatus = 'ocupado'");
                      $queryh->execute();
                      while ($rowh = $queryh->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowh['id_equipo'] . '>' . $rowh['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="auxiliar">Auxiliar del Servicio:</label>
                    <select class="form-control" style="width: 300px;"  id="auxiliar" name="auxiliar" required>
                    <?php
                            //
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $queryi = $pdo->prepare("SELECT nombre FROM usuarios WHERE perfil = 'servicio'");
                            $queryi->execute();
                            while ($rowi = $queryi->fetch(PDO::FETCH_ASSOC)) {
                              echo '<option value="' . $rowi['nombre'] . '">' . $rowi['nombre'] . '</option>';
                            }
                          ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label for="notas" style="width: 200px;">Notas:</label>
                    <textarea class="form-control" rows="5" id="notas" name="notas" style="height: 50px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.\u00d1\u00f1]+"></textarea>
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

</html>

<?php
}else {
  header("location:index.php");
}
?>
