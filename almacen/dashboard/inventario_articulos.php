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

    <title>InvArt</title>
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
        <div class="col-lg-12 text-center text-info">
          <h2 class="mt-2 font-weight-bold">Ingresar Articulos a Almacén</h2>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="text-center col-2"></div>
      <div class="col-8 form-inline pt-3 float-left">
        <form id="form1" class="" action="inventario_articulos.php" method="POST" enctype="multipart/form-data">
          <?php

          ?>
          <!--<label class="form-control bg-secondary text-white" for="">Articulo:</label>-->
          <select class="form-control ml-3" style="width: 200px;" id="articulo" name="articulo">
            <!--<option></option> -->
            <!-- MODULO PARA LLENAR LA SELECIÓN DE ARTICULOS QUE EXISTEN EN LA BASE DE DATOS -->
            <?php
            require 'conexion.php';
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo->prepare("SELECT id, articulo FROM articulos");
            $query->execute();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
              //echo "<option>" . $row['articulo'] . "</option>";
              echo '<option value=' . $row['articulo'] . '>' . $row['articulo'] . '</option>';
            }
            ?>
            <select name="" id="">
            <div id="select2lista" class="d-inline"> </div>
            </select>
            
            <input type="text" class="form-control ml-3" placeholder="Cantidad" id="cantidad" name="cantidad" style="width: 100px;" required pattern="[0-9]+" title="La cantidad debe ser número entero">
            <input type="text" class="form-control ml-3" placeholder="Costo" id="costo" name="costo" style="width: 100px;" required pattern="\d{1,3}(,\d{3})*(\.\d{2})?" title="El costo debe contener 2 decimales">
            <input name="form1" class="btn btn-info ml-3" type="submit" value="Ingresar" style="width: 100px;" />
            <!--<button type="submit" class="btn btn-outline-success mr-1 ml-2" style="width: 100px;">Ingresar</button>                  -->
        </form>
      </div>
    </div>

    <?php
    if (isset($_POST["form1"])) {

      //echo "se mando correctamente el formulario";
      require 'conexion.php';
      $articulo = $_POST['articulo'];
      $marca = $_POST['marca'];
      $cantidad = $_POST['cantidad'];
      $costo = $_POST['costo'];

      //CONSULTA PARA INGRESAR ARTICULOS AL ALMACEN
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $querya = $pdo->prepare("INSERT INTO compra_articulos (articulo, marca, cantidad, costo) 
                                VALUES(:articulo, :marca, :cantidad, :costo) ");
      $querya->bindParam(':articulo', $articulo);
      $querya->bindParam(':marca', $marca);
      $querya->bindParam(':cantidad', $cantidad);
      $querya->bindParam(':costo', $costo);
      $querya->execute();

      //consulta para obtener la existencia actual
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $queryb = $pdo->prepare("SELECT id, articulo, marca, existencias FROM articulos");
      $queryb->execute();
      while ($row = $queryb->fetch(PDO::FETCH_ASSOC)) {
        //MODULO PARA ACTUALIZAR LAS EXISTENCIAS AL MOMENTO DE INGRESAR PRODUCTOS AL ALMACEN
        if ($row['articulo'] == $articulo and $row['marca'] == $marca) {
          $id_art = $row['id'];
          $exist_ant = $row['existencias'];
          $exist_act = $exist_ant + $cantidad;
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          $queryc = $pdo->prepare("UPDATE articulos SET existencias = :exist_act WHERE id= :id_art");
          $queryc->bindParam(':exist_act', $exist_act);
          $queryc->bindParam(':id_art', $id_art);
          $queryc->execute();
        }
      }
    }
    ?>

    <div class="row">
      <div class="col-2 "></div>
      <div class="col-8 text-center text-info">
        <h2 class="mt-5 font-weight-bold">Buscar Articulos</h2>
      </div>
    </div>
    <div class="row ">
      <div class="text-center col-2"></div>
      <div class="text-center col-8">
        
          <div class="row">
            <div class="form-inline mx-auto pt-3">
              <form id="form2" action="#" method="POST">
                <input type="text" style="width: 300px;" class="form-control mr-1 ml-2" placeholder="Filtrar Articulos" id="ing_art" name="ing_art">
                <!--<button name="form2" type="submit" class="btn btn-info mr-5 ml-1" style="width: 100px;">Buscar</button>-->
              </form>
              <form id="form4" action="ArtDetail.php" method="POST">
                <input type="text" style="width: 200px;" class="form-control mr-1 ml-5" placeholder="Id para ver historico" id="ing_id_art" name="ing_id_art" pattern="[0-9]+" title="El Id debe ser número entero">
                <button name="form4" type="submit" class="btn btn-info mr-2 ml-1" style="width: 100px;">Historico</button>
              </form>
            </div>
          </div>
        
        <div class="row">
        <div class="col-xl-2 col-lg-1 col-md-1 col-sm-1 mt-3"></div>
          <div class="col-xl-8 col-lg-10 col-md-12 col-sm-2 mt-3 fluid-container text-center">
            <table class="table table-responsive">
              <thead>
                <tr>
                  <th>Id</th>
                  <th>Articulo</th>
                  <th>Marca</th>
                  <th>Existencias</th>
                  <th>Ver Foto</th>
                </tr>
              </thead>
              <tbody id="tbart">
                <!-- MODULO PARA BUSCAR ARTICULOS POR EL NOMBRE O MARCA DEL ARTICULO -->
                <?php
                  // <!-- TABLA PARA MOSTRAR TOTAL DE ARTICULOS -->
                  $query = $pdo->prepare("SELECT id, articulo, marca, existencias, foto FROM articulos");
                  $query->execute();
                  while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    //}
                  ?>
                    <tr>
                      <td><?php echo $row['id'] ?></td>
                      <td><?php echo $row['articulo'] ?></td>
                      <td><?php echo $row['marca'] ?></td>
                      <td><?php echo $row['existencias'] ?></td>
                      <td><a href="fotos/<?php echo $row['foto'] ?>" target="_blank">ver foto</a></td>
                    </tr>
                <?php
                  }
                
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="row">
          <div class="container-fluid float-left mt-3">
            <!--<a href="inventario_articulos.php" class="btn btn-info mr-5 ml-5" role="button" style="width: 100px;">Limpiar</a>-->
            <a href="principal.php" class="btn btn-info mr-5 ml-5" role="button" style="width: 100px;">Cerrar</a>
          </div>
        </div>



      </div>

    </div>

    <!-- Bootstrap core JavaScript -->


  </body>

  </html>

 

  <script type="text/javascript">
    $(document).ready(function() {
      $('#articulo').val();
      recargarLista();

      $('#articulo').change(function() {
        recargarLista();
      });
    })
  </script>
  <script type="text/javascript">
    function recargarLista() {
      $.ajax({
        type: "POST",
        url: "inv_articulos_select2.php",
        data: "articulo=" + $('#articulo').val(),
        success: function(r) {
          $('#select2lista').html(r);
        }
      });
    }

  $(document).ready(function(){
  $("#ing_art").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#tbart tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});

$('th').click(function() {
    var table = $(this).parents('table').eq(0)
    var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()))
    this.asc = !this.asc
    if (!this.asc) {
      rows = rows.reverse()
    }
    for (var i = 0; i < rows.length; i++) {
      table.append(rows[i])
    }
    setIcon($(this), this.asc);
  })

  function comparer(index) {
    return function(a, b) {
      var valA = getCellValue(a, index),
        valB = getCellValue(b, index)
      return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB)
    }
  }

  function getCellValue(row, index) {
    return $(row).children('td').eq(index).html()
  }

  function setIcon(element, asc) {
    $("th").each(function(index) {
      $(this).removeClass("sorting");
      $(this).removeClass("asc");
      $(this).removeClass("desc");
    });
    element.addClass("sorting");
    if (asc) element.addClass("asc");
    else element.addClass("desc");
  }
  </script>


<?php
  }else {
    header("location:inventario_general.php");
  }
} else {
  header("location:index.php");
}
?>