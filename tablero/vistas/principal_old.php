<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require '../conexion/cierre_automatico.php';
  require '../conexion/conexion.php';
  require 'header.php';
  require 'menu.php';
?>
<style>
    .encabezado {
	background-color: #1E1E1E;
}
.fondo {
	background-color: #252526;
}
.tabla {
	background-color: #333333;
}
.otro {
	background-color: #3C3C3C;
}
</style>
<body class="">
 <!-- Page Content -->
 <div class="container">

<div class="row">
    <div class="col-sm-6 text-center text-info ">
      <h2 class="mt-3 font-weight-bold">Lista de Actividades</h2>
    </div>
    <div class="col-sm-1 text-center text-danger">
      <h6 class="mt-3 font-weight-bold">0</h6>
      <p>Nuevas</p>
    </div>
    <div class="col-sm-1 text-center text-warning">
      <h6 class="mt-3 font-weight-bold">0</h6>
      <p>Pendientes</p>
    </div>
    <div class="col-sm-1 text-center text-success">
      <h6 class="mt-3 font-weight-bold">0</h6>
      <p>En Proceso</p>
    </div>
    <div class="col-sm-1 text-center">
      <h6 style="color: #E244C4;" class="mt-3 font-weight-bold">0</h6>
      <p style="color: #E244C4;">Programados</p>
    </div>
    <div class="col-lg-1"></div>
</div>
    
<div class="row">
    <!--<div class="col-xl- col-lg-1 col-md-1 col-sm-1 mt-3"></div>-->
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mt-3 container-fluid text-center">
        <input type="text" style="width: 200px;" class="form-control" placeholder="Buscar" id="busqueda" name="busqueda">    
        <table class="table table-responsive table-striped">
                <thead>
                    <tr>
                      <th>Id_Act</th>
                      <th>Descripción</th>
                      <th>Registrado_Por</th>
                      <th>Fecha_Hora</th>
                      <th>Asignado_a</th>
                      <th>Reasignar</th>
                      <th>Notas</th>
                      <th>Agregar_nota</th>
                      <th>Estatus</th>
                    </tr>
                </thead>
                <?php
                    $pdo2 = $pdo;
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $querya = $pdo->prepare("SELECT * FROM tablero_actividades ORDER BY estatus");
                    $querya -> execute();
                    
                ?>
                <tbody id="tblista">
                <?php    
                    while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {        
                ?>
                     <tr>
                        <td><?php echo $rowa['id_actividad'] ?></td>
                        <td><?php echo $rowa['descripcion'] ?></td>
                        <?php 
                          $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $queryb = $pdo2->prepare("SELECT nombre FROM usuarios WHERE id = :id");
                          $queryb->bindParam(':id',$rowa['id']);
                          $queryb -> execute();
                          $rowb = $queryb->fetch(PDO::FETCH_ASSOC)
                        ?>
                        <td><?php echo $rowb['nombre'] ?></td>
                        <td><?php echo $nueva_fecha = date("d-m-Y H:i:s", strtotime($rowa['created_at'])); ?></td>
                        <td><a href="#" target="_blank"></a></td>
                        <td><a href="asignar.php?id_act=<?php echo $rowa['id_actividad'] ?>">Asignar_a_mi</a></td>
                        <td><a href="verNotas.php?id_act=<?php echo $rowa['id_actividad'] ?>">Ver_Notas</a></td>
                        <td><a href="nuevaNota.php?id_act=<?php echo $rowa['id_actividad'] ?>">Agregar_Nota</a></td>
                        <?php 
                          if ($rowa['estatus']=="nueva") {
                            echo '<td class="text-danger">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="proceso") {
                            echo '<td class="text-success">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="pendiente") {
                            echo '<td class="text-warning">' . $rowa['estatus']  . '</td>';
                          }
                        ?>
                     </tr>
                     <?php 
                        }
                        $pdo = null;
                        $querya = null;
                      ?>
                </tbody>
            </table>
        </div>    
    </div>
</div>
<script src="js/reload.js"></script>
</body>  
<?php
  require 'footer.php';

}else {
  header("location:index.php");
}
?>