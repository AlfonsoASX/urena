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
    <?php
      if ($_SESSION['perfil'] == 'administrativo') {
    ?>
    <div class="row">
      <div class="col-6 text-info form-inline mx-auto rounded border">
        <h3 class="font-weight-bold">Editar Evento</h3>
        <form id="form3" action="con_edit_event.php" method="POST">
            <input type="text" style="width: 200px;" class="form-control" placeholder="Id del Servicio" id="ing_id_serv" name="ing_id_serv" required pattern="[0-9]+" title="Solo números enteros">  
            <input type="text" style="width: 200px;" class="form-control" placeholder="Evento" id="id_evento" name="id_evento" required pattern="[0-9]+" title="Solo números enteros">
            <button name="form3" type="submit" class="btn btn-info" style="width: 100px;">Modificar</button>
        </form>
      </div>
      <div class="col-6 text-info form-inline mx-auto rounded border">
        <h3 class="font-weight-bold">Hacer Cambio de Ataud</h3>
        <form id="form1" action="con_cambio_ataud.php" method="POST">
            <input type="text" style="width: 130px;" class="form-control" placeholder="Id del Servicio" id="id_serv_xch" name="id_serv_xch" required pattern="[0-9]+" title="Solo números enteros">  
            <input type="text" style="width: 130px;" class="form-control" placeholder="Ataud Anterior" id="codigo_ant" name="codigo_ant" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
            <input type="text" style="width: 130px;" class="form-control" placeholder="Ataud Nuevo" id="codigo_nvo" name="codigo_nvo" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
            <button name="form1" type="submit" class="btn btn-info" style="width: 100px;">Cambio</button>
        </form>
      </div>
    </div>
    <?php
    }
    ?>
    <div class="row">
      <div class="col-2 "></div>
      <div class="col-8 text-center text-info ">
        <h3 class="mt-3 font-weight-bold">Servicios de Velación</h3>
      </div>
    </div>
    <div class="row ">
      <!--<div class="text-center col-xl-1 col-lg-1 col-md-1 col-sm-1"></div>-->
      <div class="text-center col-xl-12 col-lg-12 col-md-12 col-sm-1">
          <div class="row">
            <div class="col-6 form-inline mx-auto pt-1">
              <input type="text" style="width: 200px;" class="form-control " placeholder="Busqueda" id="busqueda" name="busqueda" required pattern="[a-z0-9 \-]+" title="Solo letras, numeros y formato de fecha año-mes-día">
            </div>
            <div class="col-6 form-inline mx-auto pt-1">
              <form id="form2" action="con_consul_serv.php" method="POST">
                <input type="text" style="width: 200px;" class="form-control " placeholder="Ingresar Id de Servicio" id="ing_serv" name="ing_serv" required pattern="[0-9]+" title="Solo números enteros">
                <button name="form2" type="submit" class="btn btn-info " style="width: 100px;">Reimprimir</button>
              </form>
            </div>
          </div>
        <div class="row">
<?php
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $queryc = $pdo->prepare("SELECT * FROM servicios");
      $queryc -> execute();

      ?>
    
            <table class="table table-responsive table-sm">
              <thead class="thead-light">
                <tr>
                  <th>Id Servicio</th>
                  <th>Evento</th>
                  <th>Fallecido</th>
                  <th>Hospital</th>
                  <th>Domicilio Velación</th>
                  <th>Municipio</th>
                  <th>Tipo Servicio</th>
                  <th>Tipo Venta</th>
                  <th>Ataud</th>
                  <th>Modelo Ataud</th>
                  <th>Proveedor Ataud</th>
                  <th>Biombo</th>
                  <th>Carpa</th>
                  <th>Candeleros</th>
                  <th>CristoAngel</th>
                  <th>Floreros</th>
                  <th>Pedestal</th>
                  <th>Sillas</th>
                  <th>Torcheros</th>
                  <th>Velas</th>
                  <th>Despensa</th>
                  <th>Notas</th>
                  <th>Responsable</th>
                  <th>Auxiliar</th>
                  <th>Fecha Hora</th>
                </tr>
              </thead>
              
              <tbody id="tbserv">
                <tr>
      
      <?PHP

  while ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
    
      $ing_serv = $rowc['id_servicio'];

      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $querya = $pdo->prepare("SELECT codigo FROM servicio_caja 
                WHERE id_servicio =:ing_serv");
      $querya->bindParam(':ing_serv', $ing_serv);
      $querya->execute();
      $rowa = $querya->fetch(PDO::FETCH_ASSOC);
      $caja=$rowa['codigo'];

      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $queryb = $pdo->prepare("SELECT modelo, estado, proveedor FROM cajas 
                WHERE codigo =:caja");
      $queryb->bindParam(':caja', $caja);
      $queryb->execute();
      $rowb = $queryb->fetch(PDO::FETCH_ASSOC);

        //CONSULTA PARA OBTENER EL ID DEL FALLECIDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryd = $pdo->prepare("SELECT id_fallecido FROM servicio_fallecido 
                  WHERE id_servicio =:ing_serv");
        $queryd->bindParam(':ing_serv', $ing_serv);
        $queryd->execute();
        $rowd = $queryd->fetch(PDO::FETCH_ASSOC);
        $id_fallecido = $rowd['id_fallecido'];

        //CONSULTA PARA OBTENER DATOS DE LA TABLA FALLECIDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $querye = $pdo->prepare("SELECT * FROM fallecido 
                  WHERE id_fallecido =:id_fallecido");
        $querye->bindParam(':id_fallecido', $id_fallecido);
        $querye->execute();
        $rowe = $querye->fetch(PDO::FETCH_ASSOC);

        //CONSULTA PARA OBTENER EL CODIGO DE LA CAJA
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryf = $pdo->prepare("SELECT codigo FROM servicio_caja 
                  WHERE id_servicio =:ing_serv");
        $queryf->bindParam(':ing_serv', $ing_serv);
        $queryf->execute();
        $rowf = $queryf->fetch(PDO::FETCH_ASSOC);
            ?>
              <td><?php echo $rowc['id_servicio'] ?> </td>
              <td><?php echo $rowc['id_evento'] ?></td>
              <td><?php echo $rowe['nom_fallecido'] ?></td>
              <td><?php echo $rowe['hospital'] ?></td>
              <td><?php echo $rowe['dom_velacion'] ?></td>
              <td><?php echo $rowe['municipio'] ?></td>
              <td><?php echo $rowc['tipo_servicio'] ?></td>
              <td><?php echo $rowc['tipo_venta'] ?></td>
              <td><?php echo $caja ?></td>
              <td><?php echo $rowb['modelo'] ?></td>
              <td><?php echo $rowb['proveedor'] ?></td>
            <?php
                  $grupo_sillas = NULL;
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryg = $pdo->prepare("SELECT id_equipo FROM servicio_equipo 
                                  WHERE id_servicio =:ing_serv");
                        $queryg->bindParam(':ing_serv', $ing_serv);
                        $queryg->execute();
                        while ($rowg = $queryg->fetch(PDO::FETCH_ASSOC)) {
                          $silla_tmp=substr($rowg['id_equipo'],0,2);
                          if ($silla_tmp=="si") {
                            $grupo_sillas = $grupo_sillas . $rowg['id_equipo']. " ";
                            
                          }
                        }
                      
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryh = $pdo->prepare("SELECT id_equipo FROM servicio_equipo 
                                  WHERE id_servicio =:ing_serv");
                        $queryh->bindParam(':ing_serv', $ing_serv);
                        $queryh->execute();
                        $rowh = $queryh->fetchAll(PDO::FETCH_ASSOC);
                        $size=sizeof($rowh);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryi = $pdo->prepare("SELECT id_equipo FROM servicio_equipo 
                                  WHERE id_servicio =:ing_serv");
                        $queryi->bindParam(':ing_serv', $ing_serv);
                        $queryi->execute();
                        if ($size==0) {
                          for ($i=0; $i < 8; $i++) { 
                            ?><td><?php echo "N/A"; ?></td><?php
                          }
                        }else{
                            for ($i=0; $i < $size; $i++) { 
                              $rowi = $queryi->fetch(PDO::FETCH_ASSOC);
                              if ($i<6 OR $i==($size-1)) {
                                ?><td><?php echo $rowi['id_equipo'] ?></td><?php
                              }elseif ($i==6) {
                                ?><td><?php echo $grupo_sillas ?></td><?php
                              }
                            }
                          }
              ?>
              <td><?php echo $rowc['velas'] ?></td>
              <td><?php echo $rowc['despensa'] ?></td>
              <td><?php echo $rowc['notas'] ?></td>
              <td><?php echo $rowc['responsable'] ?></td>
              <td><?php echo $rowc['auxiliares'] ?></td>
              <td><?php echo $rowc['created_at'] ?></td>
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