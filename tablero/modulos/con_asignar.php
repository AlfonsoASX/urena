<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_POST) {

    require '../conexion/conexion.php';
    $id_usuario = $_SESSION['id'];
    $asignar_a = $_POST['asignar_a'];
    $id_act = $_POST['id_act'];
    
    
    
            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("INSERT INTO tablero_asignaciones (asignado_por, id, id_actividad) 
                                    VALUES(:asignado_por, :id, :id_actividad) ");
            $query->bindParam(':asignado_por',$id_usuario);
            $query->bindParam(':id',$asignar_a);
            $query->bindParam(':id_actividad',$id_act);
            if ($query -> execute()) {
                ?>  
                    <script type="text/javascript">
                        alert("LA ACTIVIDAD FUE ASIGNADA CORRECTAMENTE");
                        window.location.href = "../vistas/actividad.php?id_act=<?php echo $id_act?>";                
                    </script>
                    <?php
            }else {
                ?>  
                    <script type="text/javascript">
                        alert("LA ACTIVIDAD NO SE PUDO ASIGNAR, FAVOR DE COMUNICARSE CON EL ADMINISTRADOR DEL SISTEMA");
                        window.location.href = "../vistas/actividad.php?id_act=<?php echo $id_act?>";                
                    </script>
                    <?php
            }
        }
    

    


}else {
    header("location:index.php");
  }
