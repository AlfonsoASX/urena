<?php session_start();

if (isset($SESSION['usuario'])){
    header('Location: /almacen/dashboard/index.php');
}
require 'conexion.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = strtolower( $_POST['usuario']);
    $pass = $_POST['pass'];
}

$conexion -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$query = $conexion->prepare("SELECT * FROM usuarios WHERE nombre = :usuario AND pass = :pass");
$query -> bindParam(":usuario",$usuario);
$query -> bindParam(":pass",$pass);
$query -> execute();
$sesion = $query -> fetch(PDO:: FETCH_ASSOC);
if ($sesion) {
    $_SESSION['sesion'] = $session["usuario"];
    header("location:/almacen/login/index.php");
} else {
    echo "Credenciales incorrectas";
}

require 'index.php';

?>