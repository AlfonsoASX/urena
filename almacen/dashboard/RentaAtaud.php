<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'cierre_automatico.php';
  date_default_timezone_set('America/Mexico_city');
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
        var caja = document.getElementById('caja').value.length;
        var modelo_ataud = document.getElementById('modelo_ataud').value.length;
        var proveedor_ataud = document.getElementById('proveedor_ataud').value.length;
        
        if (caja === 0 || modelo_ataud === 0 || proveedor_ataud === 0) {
          alert('[ERROR] La seleccion de ataud es erronea');
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
        <div class="col-lg-5 text-center text-info">
          <h2 class="mt-1 font-weight-bold">Reciclar Ataud </h2>
        </div>
        <div class="col-1"></div>
        <div class="col-lg-6 text-center text-info">
          <h2 class="mt-1 font-weight-bold">Renta de Ataud </h2>
          <label for=""><?php echo "Fecha de servicio: " . date('d/m/y'); ?></label>
        </div>
      </div>

      <div class="row ">
        <div class="col-xl-5 col-lg-5 col-md-2 col-sm-2 pt-1 border rounded">
          <?php
          if ($_SESSION['perfil'] == 'administrativo') {
          ?>
            <div class="row">
              <div class="container-fluid form-inline pt-3 mr- ">
                <form id="form1" action="con_reciclar_ataud.php" method="POST">
                  <input type="text" style="width: 200px;" class="form-control mr-" placeholder="Ingresar Código" id="codigo" name="codigo" required pattern="[a-z0-9]+" title="Solo números enteros y letras minusculas">
                  <button name="form1" type="submit" class="btn btn-info mr- ml-" style="width: 100px;">Reciclar</button>
                </form>
              </div>
            </div>
          <?php } ?>
          <div class="row">
            <div class="container-fluid pt-3 mr-">
              <h5 class="mt-1 font-weight-bold ">Ataudes Rentados</h5>
            </div>
          </div>
          <div class="row">
            <div class="container-fluid pt-3 table-sm">
              <table class="table table-responsive">
                <thead>
                  <tr>
                    <th>Servicio</th>
                    <th>Ataud</th>
                    <th>Modelo Ataud</th>
                    <th>Proveedor Ataud</th>
                    <th>Fallecido</th>
                  </tr>
                </thead>
                <tbody id="tbcajas">
                  <?php
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                  $querya = $pdo->prepare("SELECT codigo, modelo, proveedor, estado FROM cajas WHERE estado='rentado'");
                  $querya->execute();
                  while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {

                    echo '<tr>';
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryb = $pdo->prepare("SELECT id_servicio FROM servicio_caja WHERE codigo=:codigo");
                    $queryb->bindParam(':codigo', $rowa['codigo']);
                    $queryb->execute();
                    $rowb = $queryb->fetch(PDO::FETCH_ASSOC);

                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryc = $pdo->prepare("SELECT id_fallecido FROM servicio_fallecido WHERE id_servicio=:id_servicio");
                    $queryc->bindParam(':id_servicio', $rowb['id_servicio']);
                    $queryc->execute();
                    $rowc = $queryc->fetch(PDO::FETCH_ASSOC);

                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryd = $pdo->prepare("SELECT nom_fallecido FROM fallecido WHERE id_fallecido=:id_fallecido");
                    $queryd->bindParam(':id_fallecido', $rowc['id_fallecido']);
                    $queryd->execute();
                    $rowd = $queryd->fetch(PDO::FETCH_ASSOC);

                    echo '<td>' . $rowb['id_servicio'] . '</td>';
                    echo '<td>' . $rowa['codigo'] . '</td>';
                    echo '<td>' . $rowa['modelo'] . '</td>';
                    echo '<td>' . $rowa['proveedor'] . '</td>';
                    echo '<td>' . $rowd['nom_fallecido'] . '</td>';
                    echo '</tr>';
                  }

                  ?>

                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class=""></div>
 
        <div class="col-xl-7 col-lg-7 col-md-2 col-sm-2 text-center border rounded">
          <!--<form class="" action="con_renta_ataud.php" method="POST">-->
          <form id="form1" action="RentaAtaud.php" method="POST">
                  <div class="form-inline pt-3 mr- float-right w-auto ">
                      
                      <label for="seleccionar_ataud">Seleccionar Ataud:</label>
                      <select class="form-control" style="width: 150px;"  id="seleccionar_ataud" name="seleccionar_ataud" required>
                        <?php
                          //
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $query = $pdo->prepare("SELECT codigo FROM cajas WHERE estado = 'nuevo' OR estado = 'reciclado'");
                          $query->execute();
                          while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value=' . $row['codigo'] . '>' . $row['codigo'] . '</option>';
                          }
                        ?> 
                      </select>
                      <button name="form1" type="submit" class="btn btn-info" style="width: 150px;">Seleccionar</button>
                  </div>
                </form>
        
            <?php
              if ($_POST) {
                $ataud=$_POST['seleccionar_ataud'];
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $queryj = $pdo->prepare("SELECT modelo, proveedor FROM cajas WHERE codigo=:ataud");
                $queryj->bindParam(':ataud', $ataud);
                $queryj->execute();
                $rowj = $queryj->fetch(PDO::FETCH_ASSOC);
                $ataud_modelo=$rowj['modelo'];
                $ataud_proveedor=$rowj['proveedor'];
              } 
            ?>
 
            <form class="" action="con_renta_ataud.php" method="POST" onsubmit="return validacion()">
                <div class="form-inline pt-3 mr- float-right">
                    <label class="" for="caja">Código Ataud:</label>
                    <input readonly value="<?php echo $ataud; ?>" type="text" class="form-control" style="width: 300px;"  placeholder="" id="caja" name="caja" required pattern="[A-Z \u00d1]+" title="El nombre sólo puede contener letras mayusculas">
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label class="" for="modelo_ataud">Modelo Ataud:</label>
                    <input readonly value="<?php echo $ataud_modelo; ?>" type="text" class="form-control" style="width: 300px;"  placeholder="" id="modelo_ataud" name="modelo_ataud" required pattern="[A-Z \u00d1]+" title="El nombre sólo puede contener letras mayusculas">
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label class="" for="proveedor_ataud">Proveedor Ataud:</label>
                    <input readonly value="<?php echo $ataud_proveedor; ?>" type="text" class="form-control" style="width: 300px;"  placeholder="" id="proveedor_ataud" name="proveedor_ataud" required pattern="[A-Z \u00d1]+" title="El nombre sólo puede contener letras mayusculas">
                </div>
            <div class="form-inline pt-3 mr- float-right">
              <label class="" for="codigo">Nombre del Fallecido:</label>
              <input type="text" class="form-control" style="width: 300px;" placeholder="" id="nom_fallecido" name="nom_fallecido" required pattern="[A-Z ]+" title="El nombre solo puede contener letras mayusculas">
            </div>
            <div class="form-inline pt-3 mr- float-right">
              <label for="codigo">Hospital:</label>
              <input type="text" class="form-control" style="width: 300px;" placeholder="" id="hospital" name="hospital" required pattern="[a-z0-9 ]+" title="El hospital sólo puede contener letras minusculas y numeros">
            </div>
            <div class="form-inline pt-3 mr- float-right w-auto ">
              <label for="tipo">Municipio:</label>
              <select class="form-control" style="width: 300px;" id="municipio" name="municipio" required>
                <option value="leon">leon</option>
                <option value="san francisco del rincon">san francisco del rincon</option>
                <option value="romita">romita</option>
                <option value="cueramaro">cueramaro</option>
                <option value="abasolo">abasolo</option>
                <option value="acambaro">acambaro</option>
                <option value="san miguel de allende">san miguel de allende</option>
                <option value="apaseo el alto">apaseo el alto</option>
                <option value="apaseo el grande">apaseo el grande</option>
                <option value="atarjea">atarjea</option>
                <option value="celaya">celaya</option>
                <option value="manuel doblado">manuel doblado</option>
                <option value="comonfort">comonfort</option>
                <option value="coroneo">coroneo</option>
                <option value="cortazar">cortazar</option>
                <option value="doctor mora">doctor mora</option>
                <option value="dolores hidalgo">dolores hidalgo</option>
                <option value="guanajuato">guanajuato</option>
                <option value="huanimaro">huanimaro</option>
                <option value="irapuato">irapuato</option>
                <option value="jaral del progreso">jaral del progreso</option>
                <option value="jerecuaro">jerecuaro</option>
                <option value="moroleon">moroleon</option>
                <option value="ocampo">ocampo</option>
                <option value="penjamo">penjamo</option>
                <option value="pueblo nuevo">pueblo nuevo</option>
                <option value="purisima del rincon">purisima del rincon</option>
                <option value="salamanca">salamanca</option>
                <option value="salvatierra">salvatierra</option>
                <option value="san diego de la union">san diego de la union</option>
                <option value="san felipe">san felipe</option>
                <option value="san jose iturbide">san jose iturbide</option>
                <option value="san luis de la paz">san luis de la paz</option>
                <option value="santa catarina ">santa catarina </option>
                <option value="juventino rosas">juventino rosas</option>
                <option value="santiago maravatio">santiago maravatio</option>
                <option value="silao de la victoria">silao de la victoria</option>
                <option value="tarandacuao">tarandacuao</option>
                <option value="tarimoro">tarimoro</option>
                <option value="tierra blanca">tierra blanca</option>
                <option value="uriangato">uriangato</option>
                <option value="valle de santiago">valle de santiago</option>
                <option value="victoria">victoria</option>
                <option value="villagran">villagran</option>
                <option value="xichu">xichu</option>
                <option value="yuriria">yuriria</option>
              </select>
            </div>
            <div class="form-inline pt-3 mr- float-right w-auto ">
              <label for="tipo">Tipo de Servicio:</label>
              <select class="form-control" style="width: 300px;" id="tipo_servicio" name="tipo_servicio" required>
                <option></option>
                <option>Cooperativa</option>
                <option>Vendido</option>
                <option>Atención a Víctimas</option>
              </select>
            </div>
            <div class="form-inline pt-3 mr- float-right w-auto ">
              <label for="tipo">Auxiliar del Servicio:</label>
              <select class="form-control" style="width: 300px;" id="auxiliares" name="auxiliares" required>
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
              <label for="codigo" style="width: 200px;">Notas:</label>
              <textarea class="form-control" rows="5" id="notas" name="notas" style="height: 50px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.]+"></textarea>
            </div>

            <div id="botones" class="form-inline pt-3 mr- float-right">
              <button type="submit" class="btn btn-info m-4" style="width: 130px; ">Guardar</button>
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
} else {
  header("location:index.php");
}
?>