<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_POST['id_usr']==$_SESSION['id']) {

    require '../conexion/conexion.php';
    $id_act = $_POST['id_act'];
    $estatus_nuevo = $_POST['estatus_nuevo'];
            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("UPDATE tablero_actividades SET estatus = :estatus_nuevo 
            WHERE id_actividad = :id_act");
            $query->bindParam(':id_act',$id_act);
            $query->bindParam(':estatus_nuevo',$estatus_nuevo);
            if ($query -> execute()) {
                ?>  
                    <script type="text/javascript">
                        alert("EL ESTATUS FUE MODIFICADO CORRECTAMENTE");
                        window.location.href = "../vistas/principal.php";                
                    </script>
                    <?php
            }else {
                ?>  
                    <script type="text/javascript">
                        alert("EL ESTATUS NO PUDO SER MODIFICADO, FAVOR DE COMUNICARSE CON EL ADMINISTRADOR DEL SISTEMA");
                        window.location.href = "../vistas/principal.php";                
                    </script>
                    <?php
            }
        }else {
            ?>  
                <script type="text/javascript">
                    alert("EL ESTATUS SOLAMENTE PUEDE SER MODIFICADO POR EL RESPONSABLE DE LA ACTIVIDAD, POR LO QUE DEDERÁ REASIGNAR LA ACTIVIDAD PRIMERO PARA PODER MOFICIAR EL ESTATUS");
                    window.location.href = "../vistas/principal.php";                
                </script>
                <?php
        }
    

     


}else {
    header("location:index.php");
  }
