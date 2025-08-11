<?php 
    require '../conexion/conexion.php';
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
 
    
<div class="row border">
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mt-3 container-fluid text-center">
        <!--<input type="text" style="width: 200px" class="form-control" placeholder="Buscar" id="busqueda" name="busqueda">-->
        <h6>Lista de Asignaciones</h6>
        <table class="table table-responsive table-striped">
                <thead>
                    <tr>
                      <th>Asignado Por</th>
                      <th>Asignado A</th>
                      <th>Fecha Hora</th>
                    </tr>
                </thead>
                <?php
                   
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $querya = $pdo->prepare("SELECT tas.asignado_por, tas.id, tas.created_at, u.nombre 
                    FROM tablero_asignaciones tas
                    INNER JOIN usuarios u ON tas.id = u.id
                    WHERE tas.id_actividad=:id_actividad
                    ");
                    $querya->bindParam(':id_actividad',$_GET['id_act']);
                    $querya -> execute();
                    
                ?>
                <tbody id="tblista">
                <?php    
                    while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {        
                ?>
                     <tr>
                        <td><?php echo $rowa['asignado_por'] ?></td>
                        <td><?php echo $rowa['nombre'] ?></td>
                        <td><?php echo $rowa['created_at'] ?></td>
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

</body>  
<?php
  

