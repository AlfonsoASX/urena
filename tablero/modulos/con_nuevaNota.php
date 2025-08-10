<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_POST) {

    require '../conexion/conexion.php';
    $descripcion = $_POST['descripcion'];
    $id_actividad = $_POST['id_act'];
    $id_usuario = $_SESSION['id'];
    
    
    
            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("INSERT INTO tablero_notas (descripcion, id_actividad, id) 
                                    VALUES(:descripcion, :id_actividad, :id) ");
            $query->bindParam(':descripcion',$descripcion);
            $query->bindParam(':id_actividad',$id_actividad);
            $query->bindParam(':id',$id_usuario);
            if ($query -> execute()) {
                ?>  
                    <script type="text/javascript">
                        alert("LA NOTA SE REGISTRÓ CORRECTAMENTE");
                        window.location.href = "../vistas/actividad.php?id_act=<?php echo $id_actividad?>";                
                    </script>
                    <?php
            }else {
                ?>  
                    <script type="text/javascript">
                        alert("LA NOTA NO SE PUDO REGISTRAR, FAVOR DE COMUNICARSE CON EL ADMINISTRADOR DEL SISTEMA");
                        window.location.href = "../vistas/actividad.php?id_act=<?php echo $id_actividad?>";                
                    </script>
                    <?php
            }
        }
    

     


}else {
    header("location:index.php");
  }
