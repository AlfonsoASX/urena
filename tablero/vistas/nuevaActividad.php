<?php
session_start();
if (isset($_SESSION["usuario"])) {
  //require '../conexion/cierre_automatico.php';
  require 'header.php';
  require 'menu.php';
?>

<div class="container" >

    <div class="row">
      <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center text-info mx-auto">
        <h2 class="mt-5 font-weight-bold">Nueva Actividad</h2>
      </div>
    </div>
    <div class="row">
        
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-7 text-center mx-auto">
            <form id="form1" action="../modulos/con_nuevaActividad.php" method="POST">
                <div class="form-inline pt-3 mx-auto float-left">
                    <label for="descripcion" style="width: 200px;">Descripción:</label>
                    <textarea class="form-control" rows="5" id="descripcion" name="descripcion" style="height: 150px; width: 300px; min-height: 50px; max-height: 200px;" pattern="[a-z0-9 \.\u00d1\u00f1]+"></textarea>
                </div>              
                <div class="form-inline pt-3 mx-auto float-left">
                    <input class="btn btn-info" type="submit" value="Guardar" style="width: 300px;" />
                    <!--<a href="principal.php" class="btn btn-info m-4" role="button" style="width: 130px;">Cerrar</a>-->
                </div>
            </form>
        </div>
    </div>
</div>
  
<?php
  require 'footer.php';

}else {
  header("location:index.php");
}
?>