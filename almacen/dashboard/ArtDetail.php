<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'cierre_automatico.php';
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
              <a class="nav-link" href="#">Menú Principal
                <span class="sr-only">(current)</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#">Alta Productos</a>
            </li>
            <li class="nav-item active">
              <a class="nav-link" href="#">Inventario
                <span class="sr-only">(current)</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#">Servicios</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#">Vale Salida</a>
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


      <div class="row ">
        <div class="col-xl-4 col-lg-3 col-md-3 col-sm-1 mt-3"></div>

        <div class="col-xl-5 col-lg-6 col-md-6 col-sm-2 mt-3">
          <div class="row">
            <div class="container-fluid mx-auto text-center text-dark">
              <h4 class="mt-5 font-weight-bold">Entradas del Articulo</h4>
            </div>
          </div>

          <div class="row">
            <div class="container-fluid mx-auto text-center">
              <table class="table table-responsive">
                <thead>
                  <tr>
                    <th>Articulo</th>
                    <th>Marca</th>
                    <th>Cantidad</th>
                    <th>C.U.</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  require 'conexion.php';
                  if (isset($_POST["form4"])) {
                    $ing_id_art = $_POST['ing_id_art'];
                    //QUERY PARA PASAR A LA SEGUNDA TABLA
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $querya = $pdo->prepare("SELECT id, articulo, marca FROM articulos 
                                            WHERE id= :ing_id_art ");
                    $querya->bindParam(':ing_id_art', $ing_id_art);
                    $querya->execute();
                    $rowa = $querya->fetch(PDO::FETCH_ASSOC);
                    $var_art = $rowa['articulo'];
                    $var_marc = $rowa['marca'];

                    //QUERY PARA EXTRAER LAS COMPRAS DE LA SEGUNDA TABLA
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryb = $pdo->prepare("SELECT * FROM compra_articulos 
                                            WHERE articulo=:var_art AND marca=:var_marc ");
                    $queryb->bindParam(':var_art', $var_art);
                    $queryb->bindParam(':var_marc', $var_marc);
                    $queryb->execute();
                    while ($rowb = $queryb->fetch(PDO::FETCH_ASSOC)) {
 
                  ?>
                      <tr>
                        <td><?php echo $rowb['articulo']; ?></td>
                        <td><?php echo $rowb['marca']; ?></td>
                        <td><?php echo $rowb['cantidad']; ?></td>
                        <td><?php echo $rowb['costo']; ?></td>
                        <td><?php echo $rowb['created_at']; ?></td>
                      </tr>
                  <?php
                    }
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="row">
            <div class="container-fluid mx-auto text-center text-dark">
              <h4 class="mt-1 font-weight-bold">Salidas del Articulo</h4>
            </div>
          </div>

          <div class="row">
            <div class="container-fluid mx-auto text-center">
              <table class="table table-responsive">
                <thead>
                  <tr>
                    <th>Articulo</th>
                    <th>Marca</th>
                    <th>Cantidad</th>
                    <th>Entregado a</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (isset($_POST["form4"])) {
                  ?>
                  <?php 
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $queryc = $pdo->prepare("SELECT fecha, cantidad, id_vale FROM articulos_vale_salida 
                                        WHERE id=:ing_id_art");
                $queryc->bindParam(':ing_id_art', $ing_id_art);
                $queryc->execute();
                while ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
                ?>
                  <tr>
                    <td><?php echo $var_art; ?></td>
                    <td><?php echo $var_marc; ?></td>
                    <td><?php echo $rowc['cantidad']; ?></td>
                    <td><?php echo $rowc['fecha'];?></td>
                    <?php 
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryd = $pdo->prepare("SELECT solicitante FROM vales_salida
                                            WHERE id_vale=:id_vale");
                    $queryd->bindParam(':id_vale', $rowc['id_vale']);
                    $queryd->execute();
                    $rowd = $queryd->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <td><?php echo $rowd['solicitante']; ?></td>
                  </tr>
                  <?php }}?>
                </tbody>
              </table>
            </div>
          </div>


          <div class="row">
            <div class="container-fluid text-center mx-auto">

              <a href="inventario_articulos.php" class="btn btn-success mr-5 ml-5" role="button" style="width: 100px;">Cerrar</a>
            </div>
          </div>




        </div>

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