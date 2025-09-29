<?php 
if (!empty($_POST)){
	session_start();

	require 'conexion.php';
	
	$usuario = strtolower( $_POST['usuario']);
	$pass = $_POST['pass'];
	$pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$query = $pdo -> prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND pass = :pass");
	$query -> bindParam(":usuario",$usuario);
	$query -> bindParam(":pass",$pass);
	$query -> execute();
	$sesion = $query -> fetch(PDO:: FETCH_ASSOC);
	if ($sesion) {
		$_SESSION['usuario'] = $sesion["usuario"];
		$_SESSION['nombre'] = $sesion['nombre'];
		$_SESSION['perfil'] = $sesion['perfil'];
		$_SESSION['tiempo'] = time();
		
		header("location:principal.php");
		
	} else {
		echo "Credenciales incorrectas";
	}

}	

?><!DOCTYPE html>
<html >
  <head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>ALMACEN</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
	<link rel="stylesheet" href="style_login.css">
	<!-- <link rel="preconnect" href="https://fonts.gstatic.com"> -->
	<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet" type='text/css'>	

	
  </head>
<body>
	<!-- Page Header -->
	<style>
		body {
		background-image: url('/almacen/imagenes/print_pagina_03.png');
		background-repeat: no-repeat;
		background-attachment: fixed;
		background-size: cover;
		}
</style>

	<!-- Page Content -->

	<div class="container">
				
				<div class="row text-center login-page text-dark">
					<h1 class="manuscrita m-auto" style="font-size: 50px;">Grupo Ureña Funerarios</h1>
					<div class="col-md-12 login-form">

						<form action="index.php" method="POST" name="login"> 
							<div class="row">
								<div class="col-md-12 login-form-header text-success">
									<p class="login-form-font-header">Módulo de Almacén<p>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 login-from-row">
									<input name="usuario" type="text" placeholder="Usuario" required id="usuario"/>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 login-from-row">
									<input name="pass" type="password" placeholder="Contraseña" required id="pass"/>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 login-from-row">
									<input class="btn btn-info" type="submit" value="Iniciar Sesion" />
									
								</div>
							</div>
						</form>

					</div>
				</div>
			
	</div>
	
</body>



</html>

