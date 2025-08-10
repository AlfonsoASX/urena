<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_GET) {

    require '../conexion/conexion.php';
    $id_act = $_GET['id_act'];
            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("UPDATE tablero_actividades SET estatus = 'finalizada' 
            WHERE id_actividad = :id_act");
            $query->bindParam(':id_act',$id_act);
            if ($query -> execute()) {
                ?>  
                    <script type="text/javascript">
                        alert("LA ACTIVIDAD FUE MODIFICADA CORRECTAMENTE");
                        window.location.href = "../vistas/principal.php";                
                    </script>
                    <?php
            }else {
                ?>  
                    <script type="text/javascript">
                        alert("EL ESTATUS NO PUDO SER MODIFICADO, FAVOR DE COMUNICARSE CON EL ADMINISTRADOR DEL SISTEMA");
                        window.location.href = "../vistas/actividad.php?id_act=<?php echo $id_act?>";                
                    </script>
                    <?php
            }
        }
    

     


}else {
    header("location:index.php");
  }
