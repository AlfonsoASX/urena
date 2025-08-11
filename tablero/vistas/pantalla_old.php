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
    
<?php 
  require '../modulos/con_verActividades.php';
?>

<script>
  let counter = 1;
setInterval(() => {
	counter++;
	if(counter > 5) location.reload();
}, 60000);
</script>
</body>  
<?php
  require 'footer.php';


?>