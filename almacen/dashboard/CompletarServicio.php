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
      if (tr != 'tr1sintorcheros' && cd != 'cd1sincandeleros') {
        alert('[ERROR] No puedes seleccionar torcheros y candeleros al mismo tiempo');
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

            </div>


            <div class="row ">
                <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2"></div>
                <div class="col-xl-6 col-lg-7 col-md-2 col-sm-2 text-center">
                    <div id="imp1">
                        <div class="col-lg-12 text-center text-info">
                            <h2 class="mt-5 font-weight-bold">Completar Servicio </h2>
                            <label for=""><?php echo "Fecha de servicio:" . date('d/m/y'); ?></label>
                        </div>
                        <div class="form-inline pt-3 mr- float-right">
                            <form id="form1" action="CompletarServicio.php" method="POST" >
                                <input type="text" style="width: 200px;" class="form-control mr-" placeholder="Ingresar Id de Servicio" id="serv_comp" name="serv_comp" required pattern="[0-9]+" title="Solo números enteros">
                                <button name="form1" type="submit" class="btn btn-info mr- ml-" style="width: 100px;">Buscar</button>
                            </form>
                        </div>
                        <?php
                        if ($_POST) {
                            
                        
                            $serv_comp = $_POST['serv_comp'];
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $queryk = $pdo->prepare("SELECT * FROM servicios WHERE id_servicio = :serv_comp");
                            $queryk->bindParam(':serv_comp', $serv_comp);
                            $queryk->execute();
                            $rowk=$queryk->fetch(PDO::FETCH_ASSOC);
                            if ($rowk['tipo_venta']=="renta") {
                                
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $queryl = $pdo->prepare("SELECT id_fallecido FROM servicio_fallecido WHERE id_servicio = :serv_comp");
                                $queryl->bindParam(':serv_comp', $serv_comp);
                                $queryl->execute();
                                $rowl=$queryl->fetch(PDO::FETCH_ASSOC);
                                $fallecido_comp = $rowl['id_fallecido'];

                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $querym = $pdo->prepare("SELECT nom_fallecido, municipio FROM fallecido WHERE id_fallecido = :fallecido_comp");
                                $querym->bindParam(':fallecido_comp', $fallecido_comp);
                                $querym->execute();
                                $rowm=$querym->fetch(PDO::FETCH_ASSOC);
                                //$nom_fallecido_comp = $rowm['nom_fallecido'];
                                //$municipio_comp = $rowm['municipio'];
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $queryn = $pdo->prepare("SELECT codigo FROM servicio_caja WHERE id_servicio = :serv_comp");
                                $queryn->bindParam(':serv_comp', $serv_comp);
                                $queryn->execute();
                                $rown=$queryn->fetch(PDO::FETCH_ASSOC);

                                //consulta para mostrar detalles del ataud
                                $codigo_ataud_rentado = $rown['codigo'];
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $queryo = $pdo->prepare("SELECT modelo, proveedor FROM cajas WHERE codigo = :codigo_ataud_rentado");
                                $queryo->bindParam(':codigo_ataud_rentado', $codigo_ataud_rentado);
                                $queryo->execute();
                                $rowo=$queryo->fetch(PDO::FETCH_ASSOC);
                            
                        ?>
                        <form id="form2" class="" action="con_completar_servicio.php" method="POST" onsubmit="return validacion()">
                            <div class="form-inline pt-3 mr- float-right">
                                <label class="" for="id_fallecido">Id Fallecido:</label>
                                <input readonly value="<?php echo $fallecido_comp; ?>" type="text" class="form-control" style="width: 300px;" placeholder="" id="id_fallecido" name="id_fallecido" required pattern="[a-z \u00d1]+" title="El nombre sólo puede contener letras minusculas">
                            </div>
                            <div class="form-inline pt-3 mr- float-right">
                                <label class="" for="id_servicio">Id Servicio:</label>
                                <input readonly value="<?php echo $serv_comp; ?>" type="text" class="form-control" style="width: 300px;" placeholder="" id="id_servicio" name="id_servicio" required pattern="[a-z \u00d1]+" title="El nombre sólo puede contener letras minusculas">
                            </div>
                            <div class="form-inline pt-3 mr- float-right">
                                <label class="" for="codigo">Nombre del Fallecido:</label>
                                <input readonly value="<?php echo $rowm['nom_fallecido']; ?>" type="text" class="form-control" style="width: 300px;" placeholder="" id="nom_fallecido" name="nom_fallecido" required pattern="[a-z \u00d1]+" title="El nombre sólo puede contener letras minusculas">
                            </div>
                            <div class="form-inline pt-3 mr- float-right">
                                <label for="codigo">Domicilio de Velación:</label>
                                <input type="text" class="form-control" style="width: 300px;" placeholder=" Ej. Colonia Calle número" id="dom_velacion" name="dom_velacion" required pattern="[a-z0-9 \u00f1]+" title="El domicilio sólo puede contener letras minusculas y numeros">
                            </div>
                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="municipio">Municipio:</label>
                                <input readonly value="<?php echo $rowm['municipio']; ?>" type="text" class="form-control" style="width: 300px;" id="municipio" name="municipio">
                            </div>
                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="tipo">Tipo de Servicio:</label>
                                <input readonly value="<?php echo $rowk['tipo_servicio'];?>" type="text" class="form-control" style="width: 300px;" id="tipo_servicio" name="tipo_servicio">
                            </div>
                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="codigo">Ataud:</label>
                                <input readonly value="<?php echo $rown['codigo'];?>" type="text" class="form-control" style="width: 300px;" id="caja" name="caja">
                            </div>
                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="modelo_ataud">Modelo Ataud:</label>
                                <input readonly value="<?php echo $rowo['modelo'];?>" type="text" class="form-control" style="width: 300px;" id="modelo_ataud" name="modelo_ataud">
                            </div>
                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="proveedor_ataud">Proveedor Ataud:</label>
                                <input readonly value="<?php echo $rowo['proveedor'];?>" type="text" class="form-control" style="width: 300px;" id="proveedor_ataud" name="proveedor_ataud">
                            </div>
                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="material">Biombo:</label>
                                <select class="form-control" style="width: 300px;" id="biombo" name="biombo" required>
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
                                <select class="form-control" style="width: 300px;" id="pedestal" name="pedestal" required>
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
                                <select class="form-control" style="width: 300px;" id="torcheros" name="torcheros" required>
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
                                <select class="form-control" style="width: 300px;" id="candeleros" name="candeleros" required>
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
                                <select class="form-control" style="width: 300px;" id="cristo_angel" name="cristo_angel" required>
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
                                <select class="form-control" style="width: 300px;" id="floreros" name="floreros" required>
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
                                <select class="form-control" style="width: 300px;" id="carpa" name="carpa">
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
                                <select multiple class="form-control" style="width: 300px; height: 100px;" id="sillas" name="sillas[]" required>
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
                                <input type="text" class="form-control" style="width: 300px;" placeholder="" id="velas" name="velas" required pattern="[0-9]+" title="Solo números enteros">
                            </div>
                            <div class="form-inline pt-3 mr- float-right">
                                <label for="codigo">Cantidad de Despensas:</label>
                                <input type="text" class="form-control" style="width: 300px;" placeholder="" id="despensa" name="despensa" required pattern="[0-9]+" title="Solo números enteros">
                            </div>

                            <div class="form-inline pt-3 mr- float-right w-auto ">
                                <label for="tipo">Auxiliar del Servicio:</label>
                                <!--<input type="text" class="form-control" style="width: 300px;" id="auxiliares" name="auxiliares" required pattern="[a-z ]+" title="Solo se admiten letras minusculas">-->
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
                                <textarea class="form-control float-left" rows="5" id="notas" name="notas" style="height: 50px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.\u00d1\u00f1]+"><?php
                                    $res_ant=$rowk['responsable'];
                                    $aux_ant=$rowk['auxiliares'];
                                    $notas_ant=$rowk['notas'];
                                    $personal="responsable de la renta: ".$res_ant.
                                    ", auxiliar de la renta: ".$aux_ant . ", notas de la renta: " . 
                                    $notas_ant. ", nueva nota: "; 
                                    echo $personal;
                                ?></textarea>
                            </div>
                    </div>
                    <div id="botones" class="form-inline pt-3 mr- float-right">
                        <button type="submit" class="btn btn-info m-4" style="width: 130px; ">Guardar</button>
                        <a href="principal.php" class="btn btn-info m-4" role="button" style="width: 130px;">Cerrar</a>
                    </div>
                    </form>
                    <?php 
                    } else {
                        ?>
                        <script>alert('El servicio no fue encontrado');</script>
                        <?php
                    }
                }
                    ?>
                    
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