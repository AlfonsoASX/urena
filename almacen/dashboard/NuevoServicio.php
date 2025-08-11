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
        var tr = document.getElementById('torcheros').value;
        var cd = document.getElementById('candeleros').value;
        var sill = document.getElementById('sillas');
        var caja = document.getElementById('caja').value.length;
        var modelo_ataud = document.getElementById('modelo_ataud').value.length;
        var proveedor_ataud = document.getElementById('proveedor_ataud').value.length;
        var bandera=0;
        if (caja === 0 || modelo_ataud === 0 || proveedor_ataud === 0) {
          alert('[ERROR] La seleccion de ataud es erronea');
          return false;
        }else{
          if (tr != 'tr1sintorcheros' && cd != 'cd1sincandeleros') {
            alert('[ERROR] No puedes seleccionar torcheros y candeleros al mismo tiempo');
            return false;
          }else{
            for (let i = 0; i < sill.options.length; i++) {
              if (sill.options[i].selected === true) {
                bandera = bandera + 1;
              }
            }
          if (sill.options[0].selected === true && bandera > 1) {
              alert('[ERROR] La seleccion de sillas es erronea');
              return false;
            }else{          
              return true;
            }
          } 
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
  <div class="container" >

    <div class="row">
      <div class="col-lg-12 text-center text-info ">
        <h2 class="mt-5 font-weight-bold">Servicio de Velación</h2>
        <label for=""><?php echo "Fecha de servicio:" .date('d/m/y');?></label>
      </div>
    </div>

    <div class="row " >
    <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2 pt-1"></div>
        
        
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-2 text-center">

                <form id="form1" action="NuevoServicio.php" method="POST">
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
 
            <form class="" action="con_nuevo_servicio.php" method="POST" onsubmit="return validacion()">
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
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="nom_fallecido" name="nom_fallecido" required pattern="[A-Z \u00d1]+" title="El nombre sólo puede contener letras mayusculas">
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label for="codigo">Domicilio de Velación:</label>
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="colonia calle numero" id="dom_velacion" name="dom_velacion" required pattern="[a-z0-9 \u00f1]+" title="El domicilio sólo puede contener letras minusculas y numeros">
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Municipio:</label>
                    <select class="form-control" style="width: 300px;"  id="municipio" name="municipio" required >
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
                    <select class="form-control" style="width: 300px;"  id="tipo_servicio" name="tipo_servicio" required>
                      <option></option>
                      <option>cooperativa</option>
                      <option>vendido</option>
                      <option>atencion victimas</option>
                    </select>
                </div>
                
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="material">Biombo:</label>
                    <select class="form-control" style="width: 300px;"  id="biombo" name="biombo" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $querya = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'biombo' AND estatus = 'disponible')");
                      $querya->execute();
                      while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowa['id_equipo'] . '>' . $rowa['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="medida">Pedestal:</label>
                    <select class="form-control" style="width: 300px;"  id="pedestal" name="pedestal" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryb = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'pedestal' AND estatus = 'disponible')");
                      $queryb->execute();
                      while ($rowb = $queryb->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowb['id_equipo'] . '>' . $rowb['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="proveedor">Torcheros:</label>
                    <select class="form-control" style="width: 300px;"  id="torcheros" name="torcheros" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryc = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'torcheros' AND estatus = 'disponible')");
                      $queryc->execute();
                      while ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowc['id_equipo'] . '>' . $rowc['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Candeleros:</label>
                    <select class="form-control" style="width: 300px;"  id="candeleros" name="candeleros" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryd = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'candeleros' AND estatus = 'disponible')");
                      $queryd->execute();
                      while ($rowd = $queryd->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowd['id_equipo'] . '>' . $rowd['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Cristo/Angel:</label>
                    <select class="form-control" style="width: 300px;"  id="cristo_angel" name="cristo_angel" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $querye = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'cristo_angel' AND estatus = 'disponible')");
                      $querye->execute();
                      while ($rowe = $querye->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowe['id_equipo'] . '>' . $rowe['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Floreros:</label>
                    <select class="form-control" style="width: 300px;"  id="floreros" name="floreros" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryf = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'floreros' AND estatus = 'disponible')");
                      $queryf->execute();
                      while ($rowf = $queryf->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowf['id_equipo'] . '>' . $rowf['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Carpa:</label>
                    <select class="form-control" style="width: 300px;"  id="carpa" name="carpa" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryg = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'carpa' AND estatus = 'disponible')");
                      $queryg->execute();
                      while ($rowg = $queryg->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowg['id_equipo'] . '>' . $rowg['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Sillas de Color:</label>
                    <select multiple class="form-control" style="width: 300px; height: 100px;"  id="sillas" name="sillas[]" required>
                    <?php
                      //
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      $queryh = $pdo->prepare("SELECT id_equipo FROM equipos WHERE (equipo = 'sillas' AND estatus = 'disponible')");
                      $queryh->execute();
                      while ($rowh = $queryh->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value=' . $rowh['id_equipo'] . '>' . $rowh['id_equipo'] . '</option>';
                      }
                    ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label for="codigo">Cantidad de Velas:</label>
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="velas" name="velas" required pattern="[0-9]+" title="Solo números enteros">
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label for="codigo">Cantidad de Despensas:</label>
                    <input type="text" class="form-control" style="width: 300px;"  placeholder="" id="despensa" name="despensa" required pattern="[A-Za-z0-9]+" title="Solo números enteros">
                </div>              
                
                <div class="form-inline pt-3 mr- float-right w-auto ">
                    <label for="tipo">Auxiliar del Servicio:</label>
                    <select class="form-control" style="width: 300px;"  id="auxiliares" name="auxiliares" required>
                    <?php
                            //
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $queryi = $pdo->prepare("SELECT id,nombre FROM usuarios WHERE perfil = 'servicio'");
                            $queryi->execute();
                            while ($rowi = $queryi->fetch(PDO::FETCH_ASSOC)) {
                              echo '<option value="' . $rowi['nombre'] . '">' . $rowi['nombre'] . '</option>';
                            }
                          ?>
                    </select>
                </div>
                <div class="form-inline pt-3 mr- float-right">
                    <label for="codigo" style="width: 200px;">Notas:</label>
                    <textarea class="form-control" rows="5" id="notas" name="notas" style="height: 50px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.\u00d1\u00f1]+"></textarea>
                </div>              
                <div id="botones" class="form-inline pt-3 mr- float-right">
                    <button type="submit" class="btn btn-info m-4" style="width: 130px;">Guardar</button>
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