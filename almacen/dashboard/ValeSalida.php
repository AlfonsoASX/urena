<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'cierre_automatico.php';
  if ($_SESSION['perfil'] == 'administrativo') {
    $encargado = $_SESSION['nombre'];
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <meta name="description" content="">
      <meta name="author" content="">

      <title>Vale</title>
      <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>

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
              <li class="nav-item">
                <a class="nav-link" href="Servicios.php">Servicios</a>
              </li>
              <li class="nav-item">
                <a class="nav-link active" href="ValeSalida.php">Vale Salida<span class="sr-only">(current)</span></a>
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
            <h2 class="mt-5 font-weight-bold">Vale de Salida de Articulos</h2>
          </div>
        </div>

        <!--DIV ROW GENERAL CONTENIDO CENTRAL-->
        <div class="row">

          <!--DIV CONTENIDO IZQUIERDA-->
          <div class="col-xl-4 col-lg-4 col-md-2 col-sm-2 pt-3 float-left align-items-start border rounded">
            <!-- DIV PARA LOS FORMULARIOS -->

            <input type="text" style="width: 200px;" class="form-control mr-1 ml-2 mb-3" placeholder="Busqueda" id="ing_art" name="ing_art">


            <table id="tb_art" class="table table-sm table-responsive">
              <thead>
                <tr>
                  <th style="text-align:center">Id</th>
                  <th style="text-align:center">Articulo</th>
                  <th style="text-align:center">Marca</th>
                  <th style="text-align:center">Existencias</th>

                </tr>
              </thead>
              <tbody>
                <!-- MODULO PARA BUSCAR ARTICULOS POR EL NOMBRE O MARCA DEL ARTICULO -->
                <?php
                // <!-- TABLA PARA MOSTRAR TOTAL DE ARTICULOS -->
                require 'conexion.php';
                $query = $pdo->prepare("SELECT id, articulo, marca, existencias, foto FROM articulos");
                $query->execute();
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                  //}
                ?>
                  <tr>
                    <td><?php echo $row['id'] ?></td>
                    <td><?php echo $row['articulo'] ?></td>
                    <td><?php echo $row['marca'] ?></td>
                    <td style="text-align:center"><?php echo $row['existencias'] ?></td>

                  </tr>
                <?php
                }

                ?>
              </tbody>
            </table>

          </div>
          <!-- DIV PARA GENERAR UN ESPACIO ENTRE CADA SECCIÓN -->
          <div class="col-1"></div>
          <!-- DIV LADO DERECHO, PARA PONER EL FOMULARIO DE SALIDA DE ARTICULOS -->
          <div class="col-xl-7 col-lg-7 col-md-7 col-sm-7 border rounded">

            <div class="row">
              <div class="mt-3 ml-4">
                <button id="adicional" name="adicional" type="button" class="btn btn-info mb-3" style="width: 200px;"> Agregar Articulos </button>
              </div>
            </div>
            <div class="row">
              <div class="col-xl-10 col-lg-7 col-md-7 col-sm-2 mt-3 w-auto container-fluid">
                <form id="guardar" action="con_vale_salida.php" method="POST">
                  <table class="table table-sm table-responsive" id="tabla">
                    <thead>
                      <tr>
                        <th >Id del Articulo</th>
                        <th >Cantidad</th>
                        <th >Eliminar Articulo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr class="fila-fija">
                        <td><input class="form-control form-control-sm" type="text"  placeholder="" id="id_articulo" name="id_articulo[]"  required pattern="[0-9]+" title="Solo números enteros"></td>
                        <td><input class="form-control form-control-sm" type="text"  placeholder="" id="cantidad" name="cantidad[]" required pattern="[0-9]+" title="Solo números enteros"></td>
                        <td class="eliminar"><input type="button" value="Eliminar"></td>
                      </tr>
                    </tbody>
                  </table>
              </div>
            </div>
            <div class="row">
              <div class="form-inline mt-3 mr- float-right w-auto container-fluid">
                <div class="form-inline mr- float-right">
                  <label class="" style="width: 300px;">Persona que entrega el material:</label>
                </div>
                <div class="form-inline mr- float-right">
                  <input type="text" class="form-control" style="width: 300px;" value="<?php echo $_SESSION['nombre']; ?>" id="responsable" name="responsable" readonly>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="form-inline mt-3 mr- float-right w-auto container-fluid">
                <div class="form-inline float-right">
                  <label class="" style="width: 300px;">Persona que solicita el material:</label>
                </div>
                <select class="form-control" style="width: 300px;" id="solicitante" name="solicitante">
                  <?php
                  //
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                  $queryb = $pdo->prepare("SELECT nombre FROM usuarios WHERE perfil = 'servicio'");
                  $queryb->execute();
                  while ($rowb = $queryb->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . $rowb['nombre'] . '">' . $rowb['nombre'] . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="form-inline mx-auto pt-5 w-auto">
                <button type="submit" name="guardar" class="btn btn-info mb-3 mr-4" style="width: 130px;">Guardar</button>
                <a href="principal.php" class="btn btn-info mb-3 " role="button" style="width: 130px;">Cerrar</a>
              </div>
            </div>

            </form>






          </div>

        </div>
      </div>
      <!-- Bootstrap core JavaScript -->
      <script>
        $(document).ready(function() {
          $("#ing_art").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#tb_art tr").filter(function() {
              $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
          });
        });

        $(function() {
          // Clona la fila oculta que tiene los campos base, y la agrega al final de la tabla
          $("#adicional").on('click', function() {
            $("#tabla tbody tr:eq(0)").clone().removeClass('fila-fija').appendTo("#tabla");
          });

          // Evento que selecciona la fila y la elimina 
          $(document).on("click", ".eliminar", function() {
            var parent = $(this).parents().get(0);
            $(parent).remove();
          });
        });
      </script>

    </body>

    </html>






<?php
  } else {
    header("location:principal.php");
  }
} else {
  header("location:index.php");
}
?>