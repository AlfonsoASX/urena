<html>
<?php
require 'header.php';
?>

<body>
	<!-- Page Header -->
	<style>
		body {
		background-image: url('img/fondo.png');
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

						<form action="../modulos/con_login.php" method="POST" name="login"> 
							<div class="row">
								<div class="col-md-12 login-form-header">
									<p style="color: #006600" class="login-form-font-header">Tablero de Actividades<p>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 login-from-row">
									<input name="usuario" type="text" placeholder="Usuario" autofocus required id="usuario"/>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 login-from-row">
									<input name="pass" type="password" placeholder="Contraseña" required id="pass"/>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12 login-from-row">
									<!--<input class="btn btn-info" type="submit" value="Iniciar Sesion" />-->
									<input value="Iniciar Sesión" type="submit" class="btn btn-info" style="width: 180px; height: 40px; font-size: 20px;">
								</div>
							</div>
						</form>

					</div>
				</div>
			
	</div>
	
</body>



</html>