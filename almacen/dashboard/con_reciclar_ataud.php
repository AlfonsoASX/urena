<?php
session_start();
if (isset($_SESSION["usuario"])) {
require 'conexion.php';
date_default_timezone_set('America/Mexico_city');

if ($_POST) {
    

$codigo = $_POST["codigo"];
$fecha= date("y-m-d");

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryo = $pdo->prepare("UPDATE cajas SET estado = 'reciclado', updated_at = :fecha WHERE codigo= :codigo");
                                    $queryo->bindParam(':codigo', $codigo);
                                    $queryo->bindParam(':fecha', $fecha);
                                    $queryo->execute();

header("location:RentaAtaud.php");
}


}else {
    header("location:index.php");
  }

?>