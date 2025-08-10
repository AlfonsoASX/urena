<?php
session_start();
if (isset($_SESSION["usuario"])) {
require 'conexion.php';
date_default_timezone_set('America/Mexico_city');
if ($_POST) {

$codigo = $_POST['codigo'];
$ubi_nueva = $_POST['ubi_nueva'];
$fecha= date("y-m-d");

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$querya = $pdo->prepare("UPDATE cajas SET ubicacion = :ubi_nueva, updated_at = :fecha WHERE codigo= :codigo");
                                    $querya->bindParam(':codigo', $codigo);
                                    $querya->bindParam(':ubi_nueva', $ubi_nueva);
                                    $querya->bindParam(':fecha', $fecha);
                                    $querya->execute();
    header("location:inventario_cajas.php");
}
}else {
    header("location:index.php");
  }

?>