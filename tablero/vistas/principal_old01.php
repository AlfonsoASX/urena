<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require '../conexion/cierre_automatico.php';
  require '../conexion/conexion.php';
  require 'header.php';
  require 'menu.php';
?>

 <!-- Page Content -->
 <div class="container">

<div class="row">
    <div class="col-lg-12 text-center text-info ">
      <h2 class="mt-3 font-weight-bold">Lista de Actividades</h2>
    </div>
</div>
    
<div class="row">
    <!--<div class="col-xl- col-lg-1 col-md-1 col-sm-1 mt-3"></div>-->
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mt-3 container-fluid text-center">
        <input type="text" style="width: 200px;" class="form-control" placeholder="Buscar" id="busqueda" name="busqueda">    
        <table class="table table-responsive">
                <thead>
                    <tr>
                      <th>Id_Act</th>
                      <th>Descripción</th>
                      <th>Registrado_Por</th>
                      <th>Fecha_Hora</th>
                      <th>Asignado_a</th>
                      <th>Notas</th>
                      <th>Agregar_nota</th>
                      <th>Estatus</th>
                    </tr>
                </thead>
                <?php
                    $pdo2 = $pdo;
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $querya = $pdo->prepare("SELECT * FROM tablero_actividades");
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
                        <td><a href="#" target="_blank"></a>Pendiente</td>
                        <!--<td><a href="baja_personal.php?id_personal=<?php //echo $rowa['id_personal']?>" target="_blank">ver_foto</a></td>-->
                        <td><a href="#" target="_blank">ver_notas</a></td>
                        <td><a href="#" target="_blank">Agregar</a></td>
                        <td><?php echo $rowa['estatus'] ?></td>
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
  
<?php
  require 'footer.php';

}else {
  header("location:index.php");
}
?>