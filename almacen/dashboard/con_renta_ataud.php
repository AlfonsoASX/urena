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
$modelo_ataud = $_POST['modelo_ataud'];
$proveedor_ataud = $_POST['proveedor_ataud'];
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
                                    $queryb->bindParam(':velas', $velas);
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
$pdf->Cell(5,$textypos,utf8_decode("Servicio de Velación"));

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
$pdf->Cell(5,$textypos,"SERVICIO");
$pdf->setY(45);$pdf->setX(165);
$pdf->Cell(5,$textypos,$id_servicio);
$pdf->SetFont('Arial','',14);    
$pdf->setY(50);$pdf->setX(135);
$pdf->Cell(5,$textypos,"Fecha:");
$pdf->setY(50);$pdf->setX(165);
$pdf->Cell(5,$textypos,$fecha);

/// Apartir de aqui empezamos con la tabla de productos
    $pdf->Ln();
    $pdf->SetFont('Arial','',14);
    $pdf->setY(75);$pdf->setX(20);
    $pdf->Cell(5,$textypos,"Nombre del Fallecido:");
    $pdf->setY(75);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($nom_fallecido));
    $pdf->setY(81);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Hospital:"));
    $pdf->setY(81);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($hospital));
    $pdf->setY(87);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Municipio:"));
    $pdf->setY(87);$pdf->setX(80);
    $pdf->Cell(5,$textypos,$municipio);
    $pdf->setY(93);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Tipo de Servicio:"));
    $pdf->setY(93);$pdf->setX(80);
    $pdf->Cell(5,$textypos,$tipo_servicio);
    
    $pdf->setY(99);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Ataud en Renta:"));
    $pdf->setY(99);$pdf->setX(80);
    $pdf->Cell(5,$textypos,$caja);
    
    $pdf->setY(105);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Modelo Ataud:"));
    $pdf->setY(105);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($modelo_ataud));
    $pdf->setY(111);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Proveedor Ataud:"));
    $pdf->setY(111);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($proveedor_ataud));

    $pdf->setY(117);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Responsable:"));
    $pdf->setY(117);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($responsable));
    $pdf->setY(123);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Auxiliar:"));
    $pdf->setY(123);$pdf->setX(80);
    $pdf->Cell(5,$textypos,utf8_decode($auxiliares));
    $pdf->setY(129);$pdf->setX(20);
    $pdf->Cell(5,$textypos,utf8_decode("Notas:"));
    $pdf->setY(129);$pdf->setX(80);
    $pdf->Multicell(100,$textypos,utf8_decode($notas));
    $pdf->Line(65, 190, 138, 190);
    $pdf->setY(191);$pdf->setX(70);
    $pdf->Cell(5,$textypos,"Nombre y Firma del Familiar");

$pdf->output();

}else {
    header("location:index.php");
  }

?>