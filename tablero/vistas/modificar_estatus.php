<?php
session_start();
if (isset($_SESSION["usuario"])) {
    //require '../conexion/cierre_automatico.php';
    require '../conexion/conexion.php';
    require 'header.php';
    require 'menu.php';
    if ($_GET) {
        $id_act = $_GET['id_act'];
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $querya = $pdo->prepare("SELECT ta.descripcion, ta.estatus, u.id, u.nombre 
        FROM tablero_actividades ta
        INNER JOIN tablero_asignaciones tas ON ta.id_actividad = tas.id_actividad
        INNER JOIN usuarios u ON tas.id = u.id
        WHERE ta.id_actividad=:id_actividad ORDER BY tas.created_at DESC
        ");
        $querya->bindParam(':id_actividad',$_GET['id_act']);
        $querya -> execute();
        $rowa = $querya -> fetch(PDO::FETCH_ASSOC);
        
    }
?>
<body class="">
<div class="container" >

    <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center text-info mx-auto">
        <h2 class="mt-5 font-weight-bold">Modificar Estatus</h2>
      </div>
    </div>
    <div class="row">
          
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center mx-auto">
            <form action="../modulos/con_modificar_estatus.php" method="POST">
                <div class="form-inline pt-3 mx-auto float-left">
                    <label class="" for="id_act">Id Actividad:</label>
                    <input readonly type="text" value="<?php echo $id_act ?>" class="form-control" style="width: 300px;"  placeholder="" id="id_act" name="id_act">
                </div>
                <div class="form-inline pt-3 mx-auto float-left">
                    <label class="" for="id_usr">Asignado a:</label>
                    <input readonly type="text" value="<?php echo $rowa['id'] ?>" class="form-control" style="width: 300px;"  placeholder="" id="id_usr" name="id_usr">
                    <input readonly type="text" value="<?php echo $rowa['nombre'] ?>" class="form-control" style="width: 300px;"  placeholder="" id="asignado" name="asignado">
                </div>
                <div class="form-inline pt-3 mx-auto float-left">
                    <label for="descripcion" >Descripción:</label>
                    <textarea readonly class="form-control" rows="5" id="descripcion" name="descripcion" style="height: 150px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.\u00d1\u00f1]+"><?php echo $rowa['descripcion'] ?></textarea>
                </div>
                             
                <div class="form-inline pt-3 mx-auto float-left">
                    <label class="" for="estatus_actual">Estatus actual:</label>
                    <input readonly type="text" value="<?php echo $rowa['estatus'] ?>" class="form-control" style="width: 300px;"  placeholder="" id="estatus_actual" name="estatus_actual">
                </div>
                <div class="form-inline pt-3 mx-auto float-left">
                    <label class="" for="estatus_nuevo">Nuevo estatus:</label>
                    <select class="form-control" style="width: 300px;"  id="estatus_nuevo" name="estatus_nuevo" required>
                        <option value="proceso">En Proceso</option>
                        <option value="planeada">Planeada</option>
                        <option value="finalizada">Finalizada</option>    
                    </select>
                </div>
                
                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center mx-auto">
                        <div class="form-inline pt-3 mx-auto float-left">
                            <input class="btn btn-success" type="submit" value="Guardar" style="width: 300px;" />
                        </div>
                    </div>
                
            </form>
            <div class="row">
                <br>
            </div>
        </div>
    </div>
<!-- Container </div> -->

  
<?php
$pdo = NULL;
$querya = NULL;

  require 'footer.php';

?>


</div>
</body>
<?php

}else {
  header("location:index.php");
}
?>
