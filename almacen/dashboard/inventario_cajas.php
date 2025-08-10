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


<div class="row">
        <div class="col-sm-2 col-lg-12 text-center text-info">
          <h2 class="mt-2 font-weight-bold">Inventario de Cajas</h2>
        </div>
</div>
<div class="row ">
  <div class="text-center col-sm- col-md-1"></div>
  <div class="text-center col-sm-2 col-md-10 col-lg-12">
        
        <div class="row">
          <?php
          if ($_SESSION['usuario'] == 'ma.ureña'){?>  
            <div class="col-lg-"></div>
                <div class="col-lg-12 col-md-2 col-sm-2 float-right">
                    <h6 class="mt-2">Cambiar el Costo del Ataud</h6>
                </div>
          <?php }elseif ($_SESSION['perfil'] == 'administrativo') {?>  
            <div class="col-lg-"></div>
                <div class="col-lg-12 col-md-2 col-sm-2 float-right">
                    <h6 class="mt-2">Cambiar la Ubicación del Ataud</h6>
                </div>
          <?php }?> 
        </div>
        
            
        <div class="row">
            <div class="text-center mx-auto col-xl-1 col-lg-1 col-md-1 col-sm-1"></div>
            <div class="text-center mx-auto col-xl-8 col-lg-10 col-md-8 col-sm-2 form-inline fluid-container">
              <input type="text" style="width: 200px;" class="form-control" placeholder="Buscar Ataud" id="ing_cod" name="ing_cod">
                  <?php   
                  if ($_SESSION['usuario'] == 'ma.ureña'){?>
                    <form id="form1" action="con_costo_ataud.php" method="POST">
                        <input type="text" style="width: 200px;" class="form-control" placeholder="Ingresar Código" id="codigo" name="codigo" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
                        <input type="text" style="width: 200px;" class="form-control" placeholder="Ingresar Costo" id="costo" name="costo" required pattern="[0-9]+" title="Solo números enteros">
                        <button name="form1" type="submit" class="btn btn-info" style="width: 100px;">Modificar</button>
                    </form>
                <?php 
                  }elseif ($_SESSION['perfil'] == 'administrativo') {?>
                    <form id="form1" action="con_ubicacion_ataud.php" method="POST">
                        <input type="text" style="width: 200px;" class="form-control" placeholder="Ingresar Código" id="codigo" name="codigo" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
                        <!--<label class="bg-danger" style="width: 100px; display:inline-block">Nueva Ubicación:</label>-->
                        <select class="form-control" style="width: 200px;"  id="ubi_nueva" name="ubi_nueva" required>
                          <option>economica</option>
                          <option>independencia</option>
                          <option>capillas</option>
                          <option>almacen</option>
                          <option>romita</option>
                          <option>cueramaro</option>
                        </select>
                        <button name="form1" type="submit" class="btn btn-info" style="width: 100px;">Reubicar</button>
                    </form>
                <?php }?>
            </div>    
        </div>
        
        <div class="row">
        <div class="col-xl-1 col-lg-1 col-md-1 col-sm-1 mt-3"></div>
            <div class="col-xl-8 col-lg-10 col-md-12 col-sm-2 mt-3 container-fluid">
                <table class="table table-responsive">
                    <thead>
                        <tr>
                          <th>Codigo</th>
                          <th>Modelo</th>
                          <th>Proveedor</th>
                          <th>Estado</th>
                          <th>Ubicación</th>
                          <th>Color</th>
                          <th>Costo</th>
                          <th>Foto</th>
                        </tr>
                    </thead>
                    <tbody id="tbcajas">
                        <?php    
                            // <!-- MODULO PARA VER INVENTARIO DE CAJAS SIN BUSQUEDA-->
                            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $query = $pdo -> prepare("SELECT codigo, modelo, estado, ubicacion, color, proveedor, foto FROM cajas"); 
                            $query -> execute();
                            
                                while ($row = $query -> fetch(PDO:: FETCH_ASSOC)){
                                  if ($_SESSION['usuario'] != 'ma.ureña') {
                                    $costo=0;
                                  }else{
                                  $costo=$row['costo'];
                                  }
                        ?>
                            
                                  <tr>
                                    <td><?php echo $row['codigo']?></td>
                                    <td><?php echo $row['modelo']?></td>
                                    <td><?php echo $row['proveedor']?></td>
                                    <td><?php echo $row['estado']?></td>
                                    <td><?php echo $row['ubicacion']?></td>
                                    <td><?php echo $row['color']?></td>
                                    <td><?php echo $costo?></td>
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
            <div class="container-fluid float-left mt-3" >
                <!--<a href="inventario_cajas.php" class="btn btn-success mr-5 ml-5" role="button" style="width: 100px;">Limpiar</a>-->
                <a href="principal.php" class="btn btn-info mr-5 ml-5" role="button" style="width: 100px;">Cerrar</a>
            </div>
        </div>

    

</div>

</div>

  <!-- Bootstrap core JavaScript -->
  <script src="vendor/jquery/jquery.slim.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
$(document).ready(function(){
  $("#ing_cod").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#tbcajas tr").filter(function() {
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

</body>

</html>

<?php
}else {
  header("location:index.php");
}
?>