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
        $querya = $pdo->prepare("SELECT ta.descripcion, ta.id, u.usuario FROM tablero_actividades ta
        INNER JOIN usuarios u ON ta.id = u.id
        WHERE id_actividad=:id_actividad");
        $querya->bindParam(':id_actividad',$_GET['id_act']);
        $querya -> execute();
        $rowa = $querya -> fetch(PDO::FETCH_ASSOC);
        
    }
?>
<body class="">
<div class="container" >

    <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-6 col-sm-1 text-center text-info mx-auto">
        <h2 class="mt-5 font-weight-bold">Detalles de la Actividad</h2>
      </div>
    </div>
    <div class="row"> 
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-1 text-center mx-auto">
            <div class="form-inline pt-3 mx-auto float-left">
                <label class="" for="id_act">Id Actividad:</label>
                <input readonly type="text" value="<?php echo $id_act ?>" class="form-control" style="width: 300px;"  placeholder="" id="id_act" name="id_act">
            </div>
            <div class="form-inline pt-3 mx-auto float-left">
                <label for="descripcion" >Descripción:</label>
                <textarea readonly class="form-control" rows="5" id="descripcion" name="descripcion" style="height: 150px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.\u00d1\u00f1]+"><?php echo $rowa['descripcion'] ?></textarea>
            </div>
            <div class="form-inline pt-3 mx-auto float-left">
                <label class="" for="reg_by">Registrado por:</label>
                <input readonly type="text" value="<?php echo $rowa['usuario'] ?>" class="form-control" style="width: 300px;"  placeholder="" id="reg_by" name="reg_by">
            </div>
            <div class="form-inline pt-3 mx-auto float-left">
                <?php require 'verAsignaciones.php'; ?>
                <a href="asignar.php?id_act=<?php echo $id_act ?>" class="btn btn-lg btn-info mt-3 mb-3" role="button" style="width: 300px;">Asignar Actividad</a>
            </div>
        </div>
    </div>
    <?php require 'verNotas.php' ?>
    <div class="row">
        <div class="form-inline col-xl-6 col-lg-6 col-md-6 col-sm-1 pt-3 mx-auto float-left">
            <a href="nuevaNota.php?id_act=<?php echo $id_act ?>" class="btn btn-lg btn-secondary mt-3" role="button" style="width: 300px;">Agregar Nota</a>
            <a href="modificar_estatus.php?id_act=<?php echo $id_act ?>" class="btn btn-lg btn-success mt-3 mb-3" role="button" style="width: 300px;">Cambiar Estatus</a>            
        </div>
    </div>
    
</div>
</body>
<?php
$pdo = NULL;
$querya = NULL;
require 'footer.php';
}else {
  header("location:index.php");
}
?>
