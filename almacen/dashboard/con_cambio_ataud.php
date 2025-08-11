<?php
session_start();
if (isset($_SESSION["usuario"])) {
  require 'conexion.php';
  date_default_timezone_set('America/Mexico_city');
  $fecha= date("y-m-d");
  $responsable = $_SESSION['nombre'];

  if ($_POST) {
    $id_serv_xch = $_POST['id_serv_xch'];
    $codigo_anterior = $_POST['codigo_ant'];
    $codigo_nuevo = $_POST['codigo_nvo'];

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $querya = $pdo->prepare("SELECT id_serv_cod FROM servicio_caja WHERE id_servicio=:id_serv_xch AND codigo=:codigo_anterior");
    $querya->bindParam(':id_serv_xch', $id_serv_xch);
    $querya->bindParam(':codigo_anterior', $codigo_anterior);
    $querya->execute();
    
    if ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
            //CONSULTA PARA RECUPERAR EL ESTADO DEL ATAUD NUEVO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryb = $pdo->prepare("SELECT estado FROM cajas WHERE codigo = :codigo_nuevo");
            $queryb->bindParam(':codigo_nuevo', $codigo_nuevo);
            $queryb->execute();
            $rowb = $queryb->fetch(PDO::FETCH_ASSOC);

            if ($rowb['estado']=="nuevo" OR $rowb['estado']=="reciclado") {
                //INSERTAR REGISTRO EN TABLA CAMBIO_CAJA
                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $queryc = $pdo -> prepare("INSERT INTO cambio_ataud (id_servicio, ataud_anterior, ataud_nuevo) 
                                        VALUES(:id_serv_xch, :codigo_anterior, :codigo_nuevo) ");
                $queryc->bindParam(':id_serv_xch',$id_serv_xch);
                $queryc->bindParam(':codigo_anterior',$codigo_anterior);
                $queryc->bindParam(':codigo_nuevo',$codigo_nuevo);
                $queryc -> execute();

                //UPDATE DE ESTADO DEL ATAUD NUEVO
                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $queryd = $pdo->prepare("UPDATE cajas SET estado = 'vendido' WHERE codigo= :codigo_nuevo");
                $queryd->bindParam(':codigo_nuevo', $codigo_nuevo);
                $queryd->execute();

                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $querye = $pdo->prepare("UPDATE cajas SET estado = 'nuevo' WHERE codigo= :codigo_anterior");
                $querye->bindParam(':codigo_anterior', $codigo_anterior);
                $querye->execute();

                //UPDATE EN TABLA SERVICIO_CAJA
                $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $queryg = $pdo->prepare("UPDATE servicio_caja SET codigo = :codigo_nuevo WHERE id_servicio=:id_serv_xch");
                $queryg->bindParam(':codigo_nuevo', $codigo_nuevo);
                $queryg->bindParam(':id_serv_xch', $id_serv_xch);
                $queryg->execute();

                header("location:ConsultaServicios.php");

            }else {
                ?> <script>alert('El nuevo Ataud no está disponible');</script> <?php
            }

            //header("location:ConsultaServicios.php");
    }else {
        ?> <script>alert('El servicio o el Ataud son incorrectos');</script> <?php
    }
  } 

}else {
    header("location:index.php");
  }

?>