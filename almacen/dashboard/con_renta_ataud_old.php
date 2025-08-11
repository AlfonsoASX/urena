<?php
session_start();
if (isset($_SESSION["usuario"])) {
  date_default_timezone_set('America/Mexico_city');
require 'conexion.php';
$nom_fallecido = $_POST['nom_fallecido'];
$hospital = $_POST['hospital'];
$dom_velacion = "pendiente";
$municipio = $_POST['municipio'];
$tipo_servicio = $_POST['tipo_servicio'];
$tipo_venta="renta";
$caja = $_POST['caja'];
$velas = "pendiente";
$despensa = "pendiente";
$auxiliares = $_POST['auxiliares'];
$notas = $_POST['notas'];
$responsable = $_SESSION['nombre'];

//CONSULTA PARA INSERTAR UN NUEVO FALLECIDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$querya = $pdo->prepare("INSERT INTO fallecido (
                                  nom_fallecido, 
                                  dom_velacion,
                                  hospital, 
                                  municipio) VALUES (
                                  :nom_fallecido, 
                                  :dom_velacion,
                                  :hospital,
                                  :municipio)");
                                  $querya->bindParam(':nom_fallecido', $nom_fallecido);
                                  $querya->bindParam(':dom_velacion', $dom_velacion);
                                  $querya->bindParam(':hospital', $hospital);
                                  $querya->bindParam(':municipio', $municipio);
                                  $querya->execute();
                                  $id_fallecido = $pdo->lastInsertId();


//CONSULTA PARA INSERTAR UN NUEVO SERVICIO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryb = $pdo->prepare("INSERT INTO servicios (
                                    tipo_servicio,
                                    tipo_venta,
                                    velas,
                                    despensa,
                                    auxiliares,
                                    notas,
                                    responsable) VALUES (
                                    :tipo_servicio,
                                    :tipo_venta,
                                    :velas,
                                    :despensa,
                                    :auxiliares,
                                    :notas,
                                    :responsable)");
                                    $queryb->bindParam(':tipo_servicio', $tipo_servicio);
                                    $queryb->bindParam(':tipo_venta', $tipo_venta);
                                    $queryb->bindParam(':velas',$velas);
                                    $queryb->bindParam(':despensa', $despensa);
                                    $queryb->bindParam(':auxiliares', $auxiliares);
                                    $queryb->bindParam(':notas', $notas);
                                    $queryb->bindParam(':responsable', $responsable);
                                    $queryb->execute();
                                    $id_servicio = $pdo->lastInsertId();



//CONSULTA PARA GUARDAR REGISTRO SERVICIO_FALLECIDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryn = $pdo->prepare("INSERT INTO servicio_fallecido (
                                    id_fallecido, id_servicio)
                                    VALUES (
                                    :id_fallecido, :id_servicio
                                    )");
                                    $queryn->bindParam(':id_fallecido', $id_fallecido);
                                    $queryn->bindParam(':id_servicio', $id_servicio);
                                    $queryn->execute();


//CONSULTA PARA GUARDAR REGISTRO SERVICIO_CAJA
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryc = $pdo->prepare("INSERT INTO servicio_caja (
                                    id_servicio, codigo)
                                    VALUES (
                                    :id_servicio, :caja
                                    )");
                                    $queryc->bindParam(':caja', $caja);
                                    $queryc->bindParam(':id_servicio', $id_servicio);
                                    $queryc->execute();


//CONSULTAS PARA ACTUALIZAR EL ESTATUS DE CADA EQUIPO INGRESADO AL SERVICIO
$fecha= date("y-m-d");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryw = $pdo->prepare("UPDATE cajas SET estado = 'rentado', updated_at = :fecha WHERE codigo= :caja");
                                    $queryw->bindParam(':caja', $caja);
                                    $queryw->bindParam(':fecha', $fecha);
                                    $queryw->execute();

?><script>alert("El número de servicio generado es: <?php echo $id_servicio;?>");</script><?php
header("location:RentaAtaud.php");

}else {
    header("location:index.php");
  }

?>