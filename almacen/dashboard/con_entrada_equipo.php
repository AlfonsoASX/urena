<?php
session_start();
if (isset($_SESSION["usuario"])) {
require 'conexion.php';
date_default_timezone_set('America/Mexico_city');

$biombo = $_POST['biombo'];
$pedestal = $_POST['pedestal'];
$torcheros = $_POST['torcheros'];
$candeleros = $_POST['candeleros'];
$cristo_angel = $_POST['cristo_angel'];
$floreros = $_POST['floreros'];
$carpa = $_POST['carpa'];
$sillas = $_POST['sillas'];
$sillas_update = $_POST['sillas'];
$responsable = $_SESSION["nombre"];
$auxiliar = $_POST['auxiliar'];
$notas = $_POST['notas'];
$fecha= date("y-m-d");

//CONSULTA PARA GENERAR LA ENTRADA DE EQUIPOS
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$querya = $pdo->prepare("INSERT INTO entrada (responsable, auxiliar, notas) VALUES(:responsable, :auxiliar, :notas) ");
                                    $querya->bindParam(':responsable', $responsable);                                    
                                    $querya->bindParam(':auxiliar', $auxiliar);
                                    $querya->bindParam(':notas', $notas);
                                    $querya->execute();
                                    $id_entrada = $pdo->lastInsertId();

//CONSULTAS PARA ACTUALIZAR EL ESTATUS DE CADA EQUIPO INGRESADO AL SERVICIO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryd = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryd->bindParam(':id_entrada', $id_entrada);
                                    $queryd->bindParam(':id_equipo', $biombo);
                                    $queryd->bindParam(':fecha', $fecha);                                    
                                    $queryd->execute();
$querye = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $querye->bindParam(':id_entrada', $id_entrada);
                                    $querye->bindParam(':id_equipo', $pedestal);
                                    $querye->bindParam(':fecha', $fecha);
                                    $querye->execute();
$queryf = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryf->bindParam(':id_entrada', $id_entrada);
                                    $queryf->bindParam(':id_equipo', $torcheros);
                                    $queryf->bindParam(':fecha', $fecha);
                                    $queryf->execute();
$queryg = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryg->bindParam(':id_entrada', $id_entrada);
                                    $queryg->bindParam(':id_equipo', $candeleros);
                                    $queryg->bindParam(':fecha', $fecha);
                                    $queryg->execute();
$queryh = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryh->bindParam(':id_entrada', $id_entrada);
                                    $queryh->bindParam(':id_equipo', $cristo_angel);
                                    $queryh->bindParam(':fecha', $fecha);
                                    $queryh->execute();
$queryi = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryi->bindParam(':id_entrada', $id_entrada);
                                    $queryi->bindParam(':id_equipo', $floreros);
                                    $queryi->bindParam(':fecha', $fecha);
                                    $queryi->execute();
$queryj = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryj->bindParam(':id_entrada', $id_entrada);
                                    $queryj->bindParam(':id_equipo', $carpa);
                                    $queryj->bindParam(':fecha', $fecha);
                                    $queryj->execute();
while (true) {
  $silla = current($sillas);  
$queryk = $pdo->prepare("INSERT INTO equipo_entrada (id_equipo, id_entrada, fecha) VALUES(:id_equipo, :id_entrada, :fecha) ");
                                    $queryk->bindParam(':id_entrada', $id_entrada);
                                    $queryk->bindParam(':id_equipo', $silla);
                                    $queryk->bindParam(':fecha', $fecha);
                                    $queryk->execute();
  $silla = next($sillas);
  if($silla === false) break;
}
// CONSULTAS PARA ACTUALIZAR EL ESTADO DE LOS EQUIPOS
if ($biombo!="bb1sinbiombo"){
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryo = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :biombo");
                                    $queryo->bindParam(':biombo', $biombo);
                                    $queryo->bindParam(':fecha', $fecha);
                                    $queryo->execute();}
if ($carpa!="ca1sincarpa") {
$queryp = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :carpa");
                                    $queryp->bindParam(':carpa', $carpa);
                                    $queryp->bindParam(':fecha', $fecha);
                                    $queryp->execute();}
if($candeleros!="cd1sincandelero"){
$queryq = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :candeleros");
                                    $queryq->bindParam(':candeleros', $candeleros);
                                    $queryq->bindParam(':fecha', $fecha);
                                    $queryq->execute();}
if($cristo_angel!="cr1sincristo"){
$queryr = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :cristo_angel");
                                    $queryr->bindParam(':cristo_angel', $cristo_angel);
                                    $queryr->bindParam(':fecha', $fecha);
                                    $queryr->execute();}
if($floreros!="fl1sinflorero"){
$querys = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :floreros");
                                    $querys->bindParam(':floreros', $floreros);
                                    $querys->bindParam(':fecha', $fecha);
                                    $querys->execute();}
if($pedestal!="pd1sinpedestal"){
$queryt = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :pedestal");
                                    $queryt->bindParam(':pedestal', $pedestal);
                                    $queryt->bindParam(':fecha', $fecha);
                                    $queryt->execute();}

while (true) {
    $silla_update = current($sillas_update);
    if($silla_update!="si1sinsilla"){
$queryu = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :silla_update");
                                    $queryu->bindParam(':silla_update', $silla_update);
                                    $queryu->bindParam(':fecha', $fecha);
                                    $queryu->execute();}
$silla_update = next($sillas_update);
if ($silla_update === false) break;
}

if($torcheros!="tr1sintorchero"){
$queryv = $pdo->prepare("UPDATE equipos SET estatus = 'disponible', updated_at = :fecha WHERE id_equipo= :torcheros");
                                    $queryv->bindParam(':torcheros', $torcheros);
                                    $queryv->bindParam(':fecha', $fecha);
                                    $queryv->execute();}

//CODIGO PARA GENERAR LA FACTURA
include "fpdf/fpdf.php";

$pdf = new FPDF($orientation='P',$unit='mm');
$pdf->AddPage();
$link='http://192.168.1.91/almacen/dashboard/Servicios.php';
$pdf->SetFont('Arial','B',18);    
$textypos = 5;
$pdf->setY(12);
$pdf->setX(50);
// Agregamos los datos de la empresa
$pdf->Cell(50);
$pdf->Cell(5,$textypos,utf8_decode("GRUPO UREÑA FUNERARIOS"),0,0,'C',false,$link);
$pdf->setY(18);
$pdf->setX(75);
$pdf->SetFont('Arial','',16);
$pdf->Cell(5,$textypos,utf8_decode("Entrada de Equipo"));

$pdf->SetFont('Arial','',10);    
$pdf->setY(23);$pdf->setX(82);
$pdf->Cell(5,$textypos,"Independencia No. 708");
$pdf->setY(27);$pdf->setX(86);
$pdf->Cell(5,$textypos,"Col. San Miguel");
$pdf->setY(31);$pdf->setX(88);
$pdf->Cell(5,$textypos,"477-454-0117");

// Agregamos los datos del cliente
$pdf->SetFont('Arial','B',14);    
$pdf->setY(45);$pdf->setX(135);
$pdf->Cell(5,$textypos,"ENTRADA");
$pdf->setY(45);$pdf->setX(165);
$pdf->Cell(5,$textypos,$id_entrada);
$pdf->SetFont('Arial','',14);    
$pdf->setY(50);$pdf->setX(135);
$pdf->Cell(5,$textypos,"Fecha:");
$pdf->setY(50);$pdf->setX(165);
$pdf->Cell(5,$textypos,$fecha);

/// Apartir de aqui empezamos con la tabla de productos
    $pdf->Ln();
    $pdf->SetFont('Arial','',14);
    $pdf->setY(75);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Biombo:"));
    $pdf->setY(75);$pdf->setX(80);
    $biombo_tmp = substr($biombo,0,3);
    if ($biombo_tmp == "bb1"){
      $biombo="sin biombo";
    }
    $pdf->Cell(5,$textypos,$biombo);
    $pdf->setY(81);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Pedestal:"));
    $pdf->setY(81);$pdf->setX(80);
    $pedestal_tmp = substr($pedestal,0,3);
    if ($pedestal_tmp == "pd1"){
      $pedestal="sin pedestal";
    }
    $pdf->Cell(5,$textypos,$pedestal);
    $pdf->setY(87);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Torcheros:"));
    $pdf->setY(87);$pdf->setX(80);
    $torcheros_tmp = substr($torcheros,0,3);
    if ($torcheros_tmp == "tr1"){
      $torcheros="sin torcheros";
    }
    $pdf->Cell(5,$textypos,$torcheros);
    $pdf->setY(93);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Candeleros:"));
    $pdf->setY(93);$pdf->setX(80);
    $candeleros_tmp = substr($candeleros,0,3);
    if ($candeleros_tmp == "cd1"){
      $candeleros="sin candeleros";
    }
    $pdf->Cell(5,$textypos,$candeleros);
    $pdf->setY(99);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Cristo/Angel:"));
    $pdf->setY(99);$pdf->setX(80);
    $cristo_angel_tmp = substr($cristo_angel,0,3);
    if ($cristo_angel_tmp == "cr1"){
      $cristo_angel="sin cristo o angel";
    }
    $pdf->Cell(5,$textypos,$cristo_angel);
    $pdf->setY(105);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Floreros:"));
    $pdf->setY(105);$pdf->setX(80);
    $floreros_tmp = substr($floreros,0,3);
    if ($floreros_tmp == "fl1") {
      $floreros="sin floreros";
    }
    $pdf->Cell(5,$textypos,$floreros);
    $pdf->setY(111);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Carpa:"));
    $pdf->setY(111);$pdf->setX(80);
    $carpa_tmp = substr($carpa,0,3);
    if ($carpa_tmp == "ca1") {
      $carpa="sin carpa";
    }
    $pdf->Cell(5,$textypos,$carpa);
    $pdf->setY(117);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Sillas de Color:"));
    $pdf->SetFont('Arial','',10);
    $xposdyn=80;
    $i=0;
    $grupo_sillas = "";
    foreach($sillas as $dato){
      $silla_tmp=substr($dato,0,2); 
      $silla_tmp40=substr($dato,0,3);
      if ($silla_tmp=="si" && $silla_tmp40=="si1") {
        $grupo_sillas = "sin sillas";
      }elseif ($silla_tmp=="si") {
        $grupo_sillas = $grupo_sillas . "40 " . $dato. " ";
      }
    }
    $pdf->setY(159);$pdf->setX(80);
    $pdf->Cell(5,$textypos,$grupo_sillas);
    $pdf->SetFont('Arial','',14);
    $pdf->setY(123);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Responsable:"));
    $pdf->setY(123);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($responsable));
    $pdf->setY(129);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Auxiliar:"));
    $pdf->setY(129);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($auxiliar));
    $pdf->setY(135);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Notas:"));
    $pdf->setY(135);$pdf->setX(80);
    $pdf->Multicell(100,$textypos,utf8_decode($notas));
    $pdf->Line(70, 220, 140, 220);
    $pdf->setY(221);$pdf->setX(70);
    $pdf->Cell(5,$textypos,"nombre y firma de quien recibe");

$pdf->output();

}else {
    header("location:index.php");
  }

?>