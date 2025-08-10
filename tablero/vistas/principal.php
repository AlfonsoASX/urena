<?php
session_start();
if (isset($_SESSION["usuario"])) {
  //require '../conexion/cierre_automatico.php';
  require '../conexion/conexion.php';
  require 'header.php';
  if ($_SESSION['usuario']!="pantalla") {
    require 'menu.php';
   
  }
  require '../modulos/con_contador.php';
  $page = $_SERVER['PHP_SELF'];
  $sec = "60";
  
?>

<body class="fondo">
<meta http-equiv="refresh" content="<?php echo $sec?>;URL='<?php echo $page?>'">  
 <!-- Page Content -->
 <div class="container-fluid">

<div class="row">
    
    <div class="col-sm-1 col-lg-4 text-center text-info">
      <h2 class="mt-5 font-weight-bold">Lista de Actividades</h2>
    </div>
    <div class="col-sm-4 col-lg-4">
    </div>
    <div class="col-sm-1 col-lg-1 text-center pl-1 pr-1">
      <h4 style="color: red" class="mt-5 max-width 50% font-weight-bold"><?php echo $nueva?></h6>
      <p style="color: red" class="">Nuevas</p>
    </div>
    <div class="col-sm-1 col-lg-1 text-center text-warning">
      <h4 class="mt-5 font-weight-bold"><?php echo $planeada?></h6>
      <p class="" >Planeadas</p>
    </div>
    <div class="col-sm-1 col-lg-1 text-center text-primary">
      <h4 class="mt-5 font-weight-bold"><?php echo $proceso?></h6>
      <p class="">Proceso</p>
    </div>
    <div class="col-sm-1 col-lg-1 text-center text-success">
      <h4 class="mt-5 font-weight-bold"><?php echo $finalizada?></h6>
      <p class="">Finalizadas</p>
    </div>
</div>

<?php 
  require '../modulos/con_verActividades.php';
?>
 
</div>
<script src="js/reload.js"></script>
</body>  
<?php
  require 'footer.php';

}else {
  header("location:index.php");
}
?>