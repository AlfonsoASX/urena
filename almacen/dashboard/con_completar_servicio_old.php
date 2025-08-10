<?php
session_start();
if (isset($_SESSION["usuario"])) {
  date_default_timezone_set('America/Mexico_city');
require 'conexion.php';
$id_fallecido = $_POST['id_fallecido'];
$id_servicio = $_POST['id_servicio'];
$nom_fallecido = $_POST['nom_fallecido'];
$dom_velacion = $_POST['dom_velacion'];
$municipio = $_POST['municipio'];
$tipo_servicio = $_POST['tipo_servicio'];
$tipo_venta = "venta";
$caja = $_POST['caja'];
$modelo_ataud = $_POST['modelo_ataud'];
$proveedor_ataud = $_POST['proveedor_ataud'];
$biombo = $_POST['biombo'];
$pedestal = $_POST['pedestal'];
$torcheros = $_POST['torcheros'];
$candeleros = $_POST['candeleros'];
$cristo_angel = $_POST['cristo_angel'];
$floreros = $_POST['floreros'];
$carpa = $_POST['carpa'];
$velas = $_POST['velas'];
$despensa = $_POST['despensa'];
$sillas = $_POST['sillas'];
$sillas_update = $_POST['sillas'];
$auxiliares = $_POST['auxiliares'];
$notas = $_POST['notas'];
$responsable = $_SESSION['nombre'];

//CONSULTA PARA ACTUALIZAR LOS DATOS DEL FALLECIDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$querya = $pdo->prepare("UPDATE fallecido SET dom_velacion=:dom_velacion WHERE id_fallecido=:id_fallecido");
                                  $querya->bindParam(':id_fallecido', $id_fallecido);
                                  $querya->bindParam(':dom_velacion', $dom_velacion);
                                  $querya->execute();
                                  


//CONSULTA PARA ACTUALIZAR EL SERVICIO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryb = $pdo->prepare("UPDATE servicios SET tipo_venta=:tipo_venta, velas=:velas, despensa=:despensa, 
                        notas=:notas, responsable=:responsable, auxiliares=:auxiliares 
                        WHERE id_servicio=:id_servicio");
                                    $queryb->bindParam(':tipo_venta', $tipo_venta);
                                    $queryb->bindParam(':velas', $velas);
                                    $queryb->bindParam(':despensa', $despensa);
                                    $queryb->bindParam(':notas', $notas);
                                    $queryb->bindParam(':responsable', $responsable);
                                    $queryb->bindParam(':auxiliares', $auxiliares);
                                    $queryb->bindParam(':id_servicio', $id_servicio);
                                    $queryb->execute();
 
//MODULO PARA CONSULTAR LA EXISTENCIA ACTUAL
if ($torcheros!="tr1sintorcheros") {
  $velasid=67;
}else {
  $velasid=82;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$query_despensa = $pdo->prepare("SELECT existencias, id FROM articulos WHERE id in (46, 52, 54, :velasid, 83, 84, 85)");
$query_despensa->bindParam(':velasid',$velasid);
$query_despensa->execute();


//MODULO PARA ACTUALIZAR LAS EXISTENCIAS AL MOMENTO DE REALIZAR UN SERVICIO
    while ($row_desp_exist_act = $query_despensa->fetch(PDO::FETCH_ASSOC)){
      if($row_desp_exist_act['id'] ==84){
              $exist_ant = $row_desp_exist_act['existencias'];
              $exist_act = $exist_ant - (4*$despensa); 
      }elseif($row_desp_exist_act['id']==82){
        $exist_ant = $row_desp_exist_act['existencias'];
        $exist_act = $exist_ant - $velas;
      }else{
      $exist_ant = $row_desp_exist_act['existencias'];
      $exist_act = $exist_ant - (1*$despensa);
      }
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $query_despensa_update = $pdo->prepare("UPDATE articulos SET existencias = :exist_act WHERE id= :id");
      $query_despensa_update->bindParam(':exist_act', $exist_act);
      $query_despensa_update->bindParam(':id', $row_desp_exist_act['id']);
      $query_despensa_update->execute();
    }
//CONSULTA PARA GUARDAR REGISTROS DE SERVICIO_EQUIPO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$queryd = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :biombo) ");
                                    $queryd->bindParam(':id_servicio', $id_servicio);                                    
                                    $queryd->bindParam(':biombo', $biombo);
                                    $queryd->execute();
$querye = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :pedestal) ");
                                    $querye->bindParam(':id_servicio', $id_servicio);
                                    $querye->bindParam(':pedestal', $pedestal);
                                    $querye->execute();
$queryf = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :torcheros) ");
                                    $queryf->bindParam(':id_servicio', $id_servicio);
                                    $queryf->bindParam(':torcheros', $torcheros);
                                    $queryf->execute();
$queryg = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :candeleros) ");
                                    $queryg->bindParam(':id_servicio', $id_servicio);
                                    $queryg->bindParam(':candeleros', $candeleros);
                                    $queryg->execute();
$queryh = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :cristo_angel) ");
                                    $queryh->bindParam(':id_servicio', $id_servicio);
                                    $queryh->bindParam(':cristo_angel', $cristo_angel);
                                    $queryh->execute();
$queryi = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :floreros) ");
                                    $queryi->bindParam(':id_servicio', $id_servicio);
                                    $queryi->bindParam(':floreros', $floreros);
                                    $queryi->execute();
$queryj = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :carpa) ");
                                    $queryj->bindParam(':id_servicio', $id_servicio);
                                    $queryj->bindParam(':carpa', $carpa);
                                    $queryj->execute();
while (true) {
$silla = current($sillas);
$queryk = $pdo->prepare("INSERT INTO servicio_equipo (id_servicio, id_equipo) VALUES(:id_servicio, :silla) ");
                                    $queryk->bindParam(':id_servicio', $id_servicio);
                                    $queryk->bindParam(':silla', $silla);
                                    $queryk->execute();
$silla = next($sillas);
if ($silla === false) break;
}

//CONSULTAS PARA ACTUALIZAR EL ESTATUS DE CADA EQUIPO INGRESADO AL SERVICIO
$fecha= date("y-m-d");
if($biombo!="bb1sinbiombos"){
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $queryo = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :biombo");
                                      $queryo->bindParam(':biombo', $biombo);
                                      $queryo->bindParam(':fecha', $fecha);
                                      $queryo->execute();}
  if ($carpa!="ca1sincarpas") {                                    
  $queryp = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :carpa");
                                      $queryp->bindParam(':carpa', $carpa);
                                      $queryp->bindParam(':fecha', $fecha);
                                      $queryp->execute();}
  if($candeleros!="cd1sincandeleros"){
  $queryq = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :candeleros");
                                      $queryq->bindParam(':candeleros', $candeleros);
                                      $queryq->bindParam(':fecha', $fecha);
                                      $queryq->execute();}
  if($cristo_angel!="cr1sincristos"){
  $queryr = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :cristo_angel");
                                      $queryr->bindParam(':cristo_angel', $cristo_angel);
                                      $queryr->bindParam(':fecha', $fecha);
                                      $queryr->execute();}
  if($floreros!="fl1sinfloreros"){
  $querys = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :floreros");
                                      $querys->bindParam(':floreros', $floreros);
                                      $querys->bindParam(':fecha', $fecha);
                                      $querys->execute();}
  if($pedestal!="pd1sinpedestales"){
  $queryt = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :pedestal");
                                      $queryt->bindParam(':pedestal', $pedestal);
                                      $queryt->bindParam(':fecha', $fecha);
                                      $queryt->execute();}
  while (true) {
  $silla_update = current($sillas_update);
  if($silla_update!="si1sinsillas"){                                       
  $queryu = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :silla_update");
                                      $queryu->bindParam(':silla_update', $silla_update);
                                      $queryu->bindParam(':fecha', $fecha);
                                      $queryu->execute();}
  $silla_update = next($sillas_update);
  if($silla_update === false) break;
  }
  if($torcheros!="tr1sintorcheros"){
  $queryv = $pdo->prepare("UPDATE equipos SET estatus = 'ocupado', updated_at = :fecha WHERE id_equipo= :torcheros");
                                      $queryv->bindParam(':torcheros', $torcheros);
                                      $queryv->bindParam(':fecha', $fecha);
                                      $queryv->execute();}
$queryw = $pdo->prepare("UPDATE cajas SET estado = 'vendido', updated_at = :fecha WHERE codigo= :caja");
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
                                        $pdf->Cell(5,$textypos,utf8_decode("Domicilio de Velación:"));
                                        $pdf->setY(81);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,utf8_decode($dom_velacion));
                                        $pdf->setY(87);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Municipio:"));
                                        $pdf->setY(87);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$municipio);
                                        $pdf->setY(93);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Tipo de Servicio:"));
                                        $pdf->setY(93);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$tipo_servicio);
                                        
                                        $pdf->setY(99);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Ataud:"));
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
                                        $pdf->Cell(5,$textypos,utf8_decode("Biombo:"));
                                        $pdf->setY(117);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$biombo);
                                        $pdf->setY(123);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Pedestal:"));
                                        $pdf->setY(123);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$pedestal);
                                        $pdf->setY(129);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Torcheros:"));
                                        $pdf->setY(129);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$torcheros);
                                        $pdf->setY(135);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Candeleros:"));
                                        $pdf->setY(135);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$candeleros);
                                        $pdf->setY(141);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Cristo/Angel:"));
                                        $pdf->setY(141);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$cristo_angel);
                                        $pdf->setY(147);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Floreros:"));
                                        $pdf->setY(147);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$floreros);
                                        $pdf->setY(153);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Carpa:"));
                                        $pdf->setY(153);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$carpa);
                                        $pdf->setY(159);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Sillas de Color:"));
                                        $pdf->SetFont('Arial','',10);
                                        $xposdyn=80;
                                        $i=0;
                                        foreach($sillas as $dato){
                                          $pdf->setY(159);$pdf->setX($xposdyn);
										                      $sillacant="40 ".$dato;
                                          $pdf->Cell(5,$textypos,$sillacant);
                                          $xposdyn = $xposdyn +20;
                                          $i++;
                                        }
                                        $pdf->SetFont('Arial','',14);
                                        $pdf->setY(165);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Cantidad de Velas:"));
                                        $pdf->setY(165);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$velas);
                                        $pdf->setY(171);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Cantidad de Despensas:"));
                                        $pdf->setY(171);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,$despensa);
                                        $pdf->setY(177);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Responsable:"));
                                        $pdf->setY(177);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,utf8_decode($responsable));
                                        $pdf->setY(183);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Auxiliar:"));
                                        $pdf->setY(183);$pdf->setX(80);
                                        $pdf->Cell(5,$textypos,utf8_decode($auxiliares));
                                        $pdf->setY(189);$pdf->setX(20);
                                        $pdf->Cell(5,$textypos,utf8_decode("Notas:"));
                                        $pdf->setY(189);$pdf->setX(80);
                                        $pdf->Multicell(100,$textypos,utf8_decode($notas));
                                        $pdf->Line(65, 250, 138, 250);
                                        $pdf->setY(251);$pdf->setX(70);
                                        $pdf->Cell(5,$textypos,"Nombre y Firma del Familiar");
                                    
                                    $pdf->output();

//header("location:NuevoServicio.php");

}else {
    header("location:index.php");
  }

?>