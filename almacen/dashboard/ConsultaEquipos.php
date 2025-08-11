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
        <div class="col-lg-12 text-center text-info">
          <h2 class="mt-5 font-weight-bold">Equipo de Velación</h2>
        </div>
      </div>

      <form class="" action="#">

        <div class="row">
          <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2"></div>
          <div class="col-xl-8 col-lg-8 col-md-2 col-sm-2 text-center">
            <table class="table table-sm table-responsive-md">
              <thead class="thead-light">
                <tr>
                  <th>Equipo</th>
                  <th>Total</th>
                  <th>Disponibles</th>
                </tr>
              </thead>
              <tbody>
                <?php require 'con_consul_equipo_res.php'; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="row">
        <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2"></div>
          <div class="col-xl-8 col-lg-8 col-md-2 col-sm-2 text-center form-inline ">
            <label class="font-weight-bold" for="filtro">Filtro:</label>
            <input type="text" class="form-control" style="width: 200px;" placeholder="" id="filtro" name="filtro">
          </div>
        </div>

        <div class="row">
          <div class="col-xl-2 col-lg-2 col-md-2 col-sm-2"></div>
          <div class="col-xl-8 col-lg-8 col-md-2 col-sm-2 text-center">
            <table class="table table-sm table-responsive-md">
              <thead class="thead-light">
                <tr>
                  <th>Equipo</th>
                  <th>Código</th>
                  <th>Estatus</th>
                  <th>Id Servicio</th>
                  <th>Fallecido</th>
                  <th>Fecha de Salida</th>
                </tr>
              </thead>
              <tbody id="ocupado">
                <?php require 'con_consul_equipo_ocupado.php'; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="row">
          <div class="col-xl-8 col-lg-8 col-md-2 col-sm-2 text-center container-fluid float-left mt-3">
            <!--<a href="#" class="btn btn-success mr-5 ml-5" role="button" style="width: 100px;">Imprimir</a>-->
            <a href="principal.php" class="btn btn-info mr-5 ml-5" role="button" style="width: 100px;">Cerrar</a>
          </div>
        </div>

      </form>





      <!-- Bootstrap core JavaScript -->
      <script src="vendor/jquery/jquery.slim.min.js"></script>
      <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

      <script>
        $(document).ready(function() {
          $("#filtro").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#ocupado tr").filter(function() {
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
} else {
  header("location:index.php");
}
?>