<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_POST) {

    require 'conexion.php';
    $id_equipo = $_POST['id_equipo'];
    $equipo = $_POST['equipo'];
    $estatus= "disponible";
    $nombre_imagen = $_FILES['foto']['name'];
    $ruta_tmp_img = $_FILES['foto']['tmp_name'];
    $tipo_imagen = $_FILES['foto']['type'];
    $size_imagen = $_FILES['foto']['size'];
    $carpeta_destino = $_SERVER['DOCUMENT_ROOT'] . '/almacen/dashboard/fotos/';
    
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $querya = $pdo->prepare("SELECT id_equipo FROM equipos");
    $querya->execute();
    $codigos=0;
    while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
        if ($id_equipo==$rowa['id_equipo']) {
          $codigos=1;
        }
    } 
    if ($codigos==0) {
        if ($nombre_imagen!=NULL) {

    if ($size_imagen<=1000000){
        if($tipo_imagen == "image/jpg" OR $tipo_imagen == "image/jpeg" OR $tipo_imagen == "image/gif" OR $tipo_imagen == "image/png"){
            move_uploaded_file($ruta_tmp_img,$carpeta_destino.$nombre_imagen);
            
            $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("INSERT INTO equipos (id_equipo, equipo, estatus, foto) 
                                    VALUES(:id_equipo, :equipo, :estatus, :foto) ");
            $query->bindParam(':id_equipo',$id_equipo);
            $query->bindParam(':equipo',$equipo);
            $query->bindParam(':estatus',$estatus);
            $query->bindParam(':foto',$nombre_imagen);
            $query -> execute();

            header("location:NuevoEquipo.php");
        }else{
            ?>
        <script>alert("ERROR!!! EL TIPO DE ARCHIVO ES INCORRECTO");</script>
        <?php
        
        }
        
        }else{
            ?>
        <script>alert("ERROR!!! EL TAMAÑO DE LA FOTO DEBE SER MENOR A 1 MB");</script>
        <?php
        
        }    
    } else{
        $nombre_imagen="sinfoto.PNG";
        $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $query = $pdo -> prepare("INSERT INTO equipos (id_equipo, equipo, estatus, foto) 
                                    VALUES(:id_equipo, :equipo, :estatus, :foto) ");
            $query->bindParam(':id_equipo',$id_equipo);
            $query->bindParam(':equipo',$equipo);
            $query->bindParam(':estatus',$estatus);
            $query->bindParam(':foto',$nombre_imagen);
            $query -> execute();
            header("location:NuevoEquipo.php");
    } 
    }else {
        ?>
        <script>alert("ERROR!!! EL EQUIPO YA EXISTE");</script>
        <?php
        
      }
}
    


}else {
    header("location:index.php");
  }
