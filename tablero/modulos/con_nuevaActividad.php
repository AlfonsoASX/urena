<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_POST) {

    require '../conexion/conexion.php';
    $descripcion = $_POST['descripcion'];
    $estatus = 'nueva';
    $id_usuario = $_SESSION['id'];
    
    
    
            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("INSERT INTO tablero_actividades (descripcion, estatus, id) 
                                    VALUES(:descripcion, :estatus, :id) ");
            $query->bindParam(':descripcion',$descripcion);
            $query->bindParam(':estatus',$estatus);
            $query->bindParam(':id',$id_usuario);
            if ($query -> execute()) {
                ?>  
                    <script type="text/javascript">
                        alert("LA ACTIVIDAD SE REGISTRÓ CORRECTAMENTE");
                        window.location.href = "../vistas/principal.php";                
                    </script>
                    <?php
            }else {
                ?>  
                    <script type="text/javascript">
                        alert("LA ACTIVIDAD NO SE PUDO REGISTRAR, FAVOR DE COMUNICARSE CON EL ADMINISTRADOR DEL SISTEMA");
                        window.location.href = "../vistas/principal.php";                
                    </script>
                    <?php
            }
        }
    

    


}else {
    header("location:index.php");
  }
