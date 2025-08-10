<?php
session_start();
if (isset($_SESSION["usuario"])) {
  //require '../conexion/cierre_automatico.php'; 
  require 'header.php';
  require 'menu.php';
  if ($_GET) {
    $id_act = $_GET['id_act'];

  }
?>
<body class="">
<div class="container" >

    <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center text-info mx-auto">
        <h2 class="mt-5 font-weight-bold">Asignar Actividad</h2>
      </div>
    </div>
    <div class="row">
         
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center mx-auto">
            <form id="form1" action="../modulos/con_asignar.php" method="POST">
                <div class="form-inline pt-3 mx-auto float-left">
                    <label class="" for="id_act">Id Actividad:</label>
                    <input readonly type="text" value="<?php echo $id_act ?>" class="form-control" style="width: 300px;"  placeholder="" id="id_act" name="id_act">
                </div>
                <div class="form-inline pt-3 mx-auto float-left">
                    <label class="" for="asignar_a">Asignar a:</label>
                    <select class="form-control" style="width: 300px;"  id="asignar_a" name="asignar_a" required>
                        <?php
                        require '../conexion/conexion.php';
                            // CONSULTA PARA TRAER LOS PUESTOS PARA EL SELECT
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $querya = $pdo->prepare("SELECT id, nombre FROM usuarios");
                            $querya->execute();
                            while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
                                
                                echo '<option value="' . $rowa['id'] . '">' . $rowa['nombre'] . '</option>';
                            }
                            ?> 
                        </select>
                </div>             
                <div class="form-inline pt-3 mx-auto float-left">
                    <input class="btn btn-info" type="submit" value="Asignar" style="width: 300px;" />
                    <!--<a href="principal.php" class="btn btn-info m-4" role="button" style="width: 130px;">Cerrar</a>-->
                </div>
            </form>
        </div>
    </div>
</div>
</body>  
<?php
  require 'footer.php';

}else {
  header("location:index.php");
}
?>
