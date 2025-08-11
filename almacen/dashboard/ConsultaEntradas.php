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

  <title>Consulta Entradas</title>

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
      <div class="col-2 "></div>
      <div class="col-8 text-center text-info ">
        <h3 class="mt-3 font-weight-bold">Entradas de Equipos</h3>
      </div>
    </div>
    <div class="row ">
      <!--<div class="text-center col-xl-1 col-lg-1 col-md-1 col-sm-1"></div>-->
      <div class="text-center col-xl-12 col-lg-12 col-md-12 col-sm-1">
          <div class="row">
            <div class="form-inline mt-2 mb-2">
              <input type="text" style="width: 200px;" class="form-control " autofocus placeholder="Busqueda" id="busqueda" name="busqueda" required pattern="[a-z0-9 \-]+" title="Solo letras, numeros y formato de fecha año-mes-día">
            </div>
          </div>
        <div class="row">
<?php
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryc = $pdo->prepare("SELECT * FROM entrada");
        $queryc -> execute();
      ?>
            <table class="table table-responsive table-sm">
              <thead class="thead-light">
                <tr>
                  <th>Id Entrada</th>
                  <th>Biombo</th>
                  <th>Pedestal</th>
                  <th>Torcheros</th>
                  <th>Candeleros</th>
                  <th>CristoAngel</th>
                  <th>Floreros</th>
                  <th>Carpa</th>
                  <th>Sillas</th>
                  <th>Notas</th>
                  <th>Responsable</th>
                  <th>Auxiliar</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody id="tbserv">
                <tr>
      
        <?PHP
        while ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
            $id_entrada = $rowc['id_entrada'];

        ?>
            <td><?php echo $id_entrada ?> </td>
            <?php
                  $grupo_sillas = NULL;
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryg = $pdo->prepare("SELECT id_equipo FROM equipo_entrada 
                                  WHERE id_entrada =:id_entrada");
                        $queryg->bindParam(':id_entrada', $id_entrada);
                        $queryg->execute();
                        while ($rowg = $queryg->fetch(PDO::FETCH_ASSOC)) {
                          $silla_tmp=substr($rowg['id_equipo'],0,2);
                          if ($silla_tmp=="si") {
                            $grupo_sillas = $grupo_sillas . $rowg['id_equipo']. " ";
                            
                          }
                        }
                      
                        //QUERY PARA TOMAR EL TAMAÑO DEL ARREGLO
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryh = $pdo->prepare("SELECT id_equipo FROM equipo_entrada 
                                  WHERE id_entrada =:id_entrada");
                        $queryh->bindParam(':id_entrada', $id_entrada);
                        $queryh->execute();
                        $rowh = $queryh->fetchAll(PDO::FETCH_ASSOC);
                        $size=sizeof($rowh);
                        //var_dump($rowh);

                        //  QUERY PARA EXTRAER LOS EQUIPOS
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryi = $pdo->prepare("SELECT id_equipo FROM equipo_entrada 
                                  WHERE id_entrada =:id_entrada");
                        $queryi->bindParam(':id_entrada', $id_entrada);
                        $queryi->execute();
                        if ($size==0) {
                          for ($i=0; $i < 8; $i++) { 
                            ?><td><?php echo "N/A"; ?></td><?php
                          }
                        }else{
                            for ($i=0; $i < $size; $i++) { 
                              $rowi = $queryi->fetch(PDO::FETCH_ASSOC);
                              if ($i<7) {
                                ?><td><?php echo $rowi['id_equipo'] ?></td><?php
                              }elseif ($i==7) {
                                ?><td><?php echo $grupo_sillas ?></td><?php
                              }
                            }
                          }
              ?>
              
              <td><?php echo $rowc['notas'] ?></td>
              <td><?php echo $rowc['responsable'] ?></td>
              <td><?php echo $rowc['auxiliar'] ?></td>
              <td><?php echo substr($rowc['created_at'],0,10) ?></td>
            </tr>
            <?php
      }
      ?>
          </tbody>
        </table>
      
      
        </div>
      </div>
    </div>
  </div>
  <!-- Bootstrap core JavaScript -->
  <script src="vendor/jquery/jquery.slim.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
$(document).ready(function(){
  $("#busqueda").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#tbserv tr").filter(function() {
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