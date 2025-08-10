<?php
session_start();
if (isset($_SESSION["usuario"])) {

  require 'conexion.php';
  if (isset($_POST["form3"])) {
    $ing_id_serv = $_POST['ing_id_serv'];
    $id_event = $_POST['id_evento'];
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $queryd = $pdo->prepare("UPDATE servicios SET id_evento = :id_event WHERE id_servicio= :ing_id_serv");
    $queryd->bindParam(':ing_id_serv', $ing_id_serv);
    $queryd->bindParam(':id_event', $id_event);
    $queryd->execute();

    header("location:ConsultaServicios.php");

  } 

}else {
    header("location:index.php");
  }

?>