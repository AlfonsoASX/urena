<?php
  require '../conexion/conexion.php';
  require '../modulos/con_contador.php';
  require 'header.php';
  
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

 <!-- Page Content -->

 <!--<div class="container">-->
<body class="fondo">
    

<div class="row">
    <div class="col-lg-7 text-center text-info encabezado">
      <h4 class="mt-3 font-weight-bold">Tablero de Actividades Ureña</h4>
    </div>
    <div class="col-sm-1 text-center text-danger encabezado">
      <h6 class="mt-3 font-weight-bold"><?php echo $nueva?></h6>
      <p>Nuevas</p>
    </div>
    <div class="col-sm-1 text-center text-warning encabezado">
      <h6 class="mt-3 font-weight-bold"><?php echo $pendiente?></h6>
      <p>Pendientes</p>
    </div>
    <div class="col-sm-1 text-center text-success encabezado">
      <h6 class="mt-3 font-weight-bold"><?php echo $proceso?></h6>
      <p>En Proceso</p>
    </div>
    <div class="col-sm-1 text-center encabezado">
      <h6 style="color: pink;" class="mt-3 font-weight-bold"><?php echo $programada?></h6>
      <p style="color: pink;">Programadas</p>
    </div>
    <div class="col-lg-1 encabezado"></div>
</div>
    
<div class="row fondo">
    <!--<div class="col-xl- col-lg-1 col-md-1 col-sm-1 mt-3"></div>-->
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mt-3 container-fluid text-center">
        <!--<input type="text" style="width: 200px;" class="form-control" placeholder="Buscar" id="busqueda" name="busqueda">-->    
        <table class="table table-dark table-striped">
                <thead>
                    <tr>
                      <th>Id_Act</th>
                      <th>Descripción</th>
                      <th>Registrado_Por</th>
                      <th>Asignado_a</th>
                      <th>Fecha_Hora</th>
                      <th>Detalles</th>
                      <th>Estatus</th>
                    </tr>
                </thead>
                <?php
                    $pdob = $pdo;
                    $pdoc = $pdo;
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $querya = $pdo->prepare("SELECT * FROM tablero_actividades WHERE estatus!='finalizada' ORDER BY estatus");
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
                          $pdob->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $queryb = $pdob->prepare("SELECT nombre FROM usuarios WHERE id = :id");
                          $queryb->bindParam(':id',$rowa['id']);
                          $queryb -> execute();
                          $rowb = $queryb->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <td><?php echo $rowb['nombre'] ?></td>
                          <?php
                          $pdoc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $queryc = $pdoc->prepare("SELECT id FROM tablero_asignaciones WHERE id_actividad = :id_actividad ORDER BY id_asignacion DESC LIMIT 1");
                          $queryc->bindParam(':id_actividad',$rowa['id_actividad']);
                          $queryc -> execute();
                          if ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
                            ?> 
                            <td><?php echo $rowc['id'] ?></td>
                            <?php
                          }else {
                            ?> 
                            <td>sin asignar</td>
                            <?php
                          }
                          
                          ?>
                        
                        <td><?php echo $nueva_fecha = date("d-m-Y H:i:s", strtotime($rowa['created_at'])); ?></td>
                        <td><a href="actividad.php?id_act=<?php echo $rowa['id_actividad'] ?>">ver detalles</a></td>
                        <?php 
                          if ($rowa['estatus']=="nueva") {
                            echo '<td class="text-danger">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="proceso") {
                            echo '<td class="text-success">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="pendiente") {
                            echo '<td class="text-warning">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="programada") {
                            echo '<td style="color: pink">' . $rowa['estatus']  . '</td>';
                          }
                        ?>
                     </tr>
                     <?php 
                        }
                        $pdo = null;
                        $pdob = null;
                        $pdoc = null;
                        $querya = null;
                      ?>
                </tbody>
            </table>
        </div>    
    </div>
<!--</div>-->

<!--<div class="row">
    <div class="container otro ">
        <p class="mt-5">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
    </div>

</div>-->
<script>
  let counter = 1;
setInterval(() => {
	counter++;
	if(counter > 5) location.reload();
}, 1000);
</script>
</body>  
<?php
  require 'footer.php';


?>