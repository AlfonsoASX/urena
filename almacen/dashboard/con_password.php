<?php
session_start();
if (isset($_SESSION["usuario"])) {


if ($_POST) {

    require 'conexion.php';
    $pass_ant = $_POST['pass_ant'];
    $pass_new = $_POST['pass_new'];
    $conf_pass = $_POST['conf_pass'];
    $usuario = $_SESSION['usuario'];

    
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $querya = $pdo->prepare("SELECT pass FROM usuarios WHERE usuario = :usuario");
    $querya->bindParam(':usuario',$usuario);
    $querya->execute();
    $rowa = $querya->fetch(PDO::FETCH_ASSOC);

    if ($pass_ant == $rowa['pass']) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryb = $pdo->prepare("UPDATE usuarios SET pass = :pass_new WHERE usuario= :usuario");
        $queryb->bindParam(':usuario',$usuario);
        $queryb->bindParam(':pass_new', $pass_new);
        $queryb->execute();
        echo "LA CONTRASEÑA SE CAMBIO CORRECTAMENTE";
    }else{
        echo "ERROR!!! LA CONTRASEÑA ANTERIOR ES INCORRECTA";
        
    }

    
// FIN DE POST    
}
//FIN DE SESION    
}else {
    header("location:index.php");
  }
?>