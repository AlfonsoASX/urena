<html>
<!-- Navigation -->
<nav class="navbar navbar-expand-sm navbar-dark bg-success static-top">
    <div class="container-fluid">
    <img src="img/logo.png" class="rounded-circle">
      <h1 class="navbar-brand" ><a style="color: #ffffff" href="principal.php">Grupo Ureña Funerarios</a></h1>
      
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarResponsive">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="principal.php">Principal</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="nuevaActividad.php">Nueva Actividad</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle mr-5" href="#" id="navbardrop" data-toggle="dropdown">
                    Usuario
                </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="password.php">Contraseña</a>
                    <a class="dropdown-item" href="index.php">Cerrar Sesión</a>
                </div>
            </li>
        </ul>
      </div>
    </div>
  </nav>
  <label class=""><?php echo $_SESSION['nombre'];?></label>
  </html>