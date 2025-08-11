<?php
session_start();
if (isset($_SESSION["usuario"])) {
require 'conexion.php';
date_default_timezone_set('America/Mexico_city');
if ($_POST) {

$codigo = $_POST['codigo'];
$costo = $_POST['costo'];
$fecha= date("y-m-d");

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$querya = $pdo->prepare("UPDATE cajas SET costo = :costo, updated_at = :fecha WHERE codigo= :codigo");
                                    $querya->bindParam(':codigo', $codigo);
                                    $querya->bindParam(':costo', $costo);
                                    $querya->bindParam(':fecha', $fecha);
                                    $querya->execute();
    header("location:inventario_cajas.php");
}
}else {
    header("location:index.php");
  }

?>