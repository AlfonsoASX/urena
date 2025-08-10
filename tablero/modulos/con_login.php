<?php 

if ($_POST){
	session_start();
	require '../conexion/conexion.php';
	
	$usuario = strtolower( $_POST['usuario']);
	$pass = $_POST['pass'];
	$pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$query = $pdo -> prepare("SELECT id, usuario, nombre, perfil FROM usuarios WHERE usuario = :usuario AND pass = :pass");
	$query -> bindParam(":usuario",$usuario);
	$query -> bindParam(":pass",$pass);
	$query -> execute();
	//$sesion = $query -> fetch(PDO:: FETCH_ASSOC);
	//if ($rowo = $queryo->fetch(PDO::FETCH_ASSOC))
	if ($sesion = $query -> fetch(PDO:: FETCH_ASSOC)) {
		$_SESSION['usuario'] = $sesion["usuario"];
		$_SESSION['nombre'] = $sesion['nombre'];
		$_SESSION['perfil'] = $sesion['perfil'];
		$_SESSION['id'] = $sesion['id'];
		$_SESSION['tiempo'] = time();
		
		header("location:../vistas/principal.php");
		
	} else {
		header("location:../vistas/index.php");
		
	}

}

?>