<?php 
session_start();
if (isset($_SESSION["usuario"])) {
  //require '../conexion/cierre_automatico.php';
  require 'header.php';
  require 'menu.php';
?>
<script>
    function validacion(){
        var pw_nw = document.getElementById('pass_new').value;
        var pw_cf = document.getElementById('conf_pass').value;
        if (pw_nw != pw_cf) {
            alert('[ERROR] La nueva contraseña y la confirmación deben coincidir');
            return false;
        }else{
            return true;
        }        
    }
 
    function showPassAnt() {
      var passAnt = document.getElementById("pass_ant");
      if (passAnt.type === "password") {
        passAnt.type = "text";
      } else {
        passAnt.type = "password";
      }
    }

    function showPassNew() {
      var passNew = document.getElementById("pass_new");
      if (passNew.type === "password") {
        passNew.type = "text";
      } else {
        passNew.type = "password";
      }
    }

    function showConfPass() {
      var confPass = document.getElementById("conf_pass");
      if (confPass.type === "password") {
        confPass.type = "text";
      } else {
        confPass.type = "password";
      }
    }

  </script>
<!DOCTYPE html>
<html lang="en">
  
  <!-- Page Content -->
  <div class="container">
  <?php require '../modulos/con_password.php'; ?>
    <div class="row">
        <div class="col-xl-7 col-sm-8 text-center text-info mx-auto">
          <h2 class="mt-3 font-weight-bold">Cambio de Contraseña</h2>
        </div>
    </div>
 
    <div class="row ">
        <!--<div class="col-lg-3 col-sm-1"></div>-->

        <div class="col-xl-7 col-sm-8 text-center mx-auto">

            <form class="" action="password.php" method="POST" onsubmit="return validacion()">
               
                <div class="form-inline pt-3 mr-4 float-right ">
                    <label class="" for="pass_ant">Contraseña Actual:</label>
                    <input type="password" class="form-control" style="width: 300px;"  placeholder="" id="pass_ant" name="pass_ant" required pattern="[a-zA-Z0-9]+" title="Sólo letras y números">
                    <span class="input-group-btn">
                      <button class="btn btn-default reveal" type="button" onclick="showPassAnt()"><i class="fa fa-eye"></i></button>
                    </span> 
                </div>
                <div class="form-inline pt-3 mr-4 float-right ">
                    <label class="" for="pass_new">Constraseña Nueva:</label>
                    <input type="password" minlength="6" class="form-control" style="width: 300px;"  placeholder="" id="pass_new" name="pass_new" required pattern="[a-zA-Z0-9]+" title="Sólo letras y números">
                    <span class="input-group-btn">
                      <button class="btn btn-default reveal" type="button" onclick="showPassNew()"><i class="fa fa-eye"></i></button>
                    </span> 
                </div>
                <div class="form-inline pt-3 mr-4 float-right ">
                    <label class="" for="conf_pass">Confirmar Contraseña:</label>
                    <input type="password" minlength="6" class="form-control" style="width: 300px;"  placeholder="" id="conf_pass" name="conf_pass" required pattern="[a-zA-Z0-9]+" title="Sólo letras y números">
                    <span class="input-group-btn">
                      <button class="btn btn-default reveal" type="button" onclick="showConfPass()"><i class="fa fa-eye"></i></button>
                    </span> 
                </div>
                <div class="form-inline pt-3 mx-auto float-left">
                    <input class="btn btn-info" type="submit" value="Guardar" style="width: 300px;" />
                    <!--<a href="principal.php" class="btn btn-info mx-auto" role="button" style="width: 130px;">Cerrar</a>-->
                </div>                
            </form>
            
        </div>
        <div class=""></div>
    </div> 

  </div>               

</body>
<?php
require 'footer.php';
?>
</html>
<?php
}else {
  header("location:index.php");
}
?>