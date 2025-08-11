<?php
session_start();
if (isset($_SESSION["usuario"])) {

    
    require 'conexion.php';
    if (isset($_POST["form2"])) {
      $ing_serv = $_POST['ing_serv'];

      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $querya = $pdo->prepare("SELECT codigo FROM servicio_caja 
                WHERE id_servicio =:ing_serv");
      $querya->bindParam(':ing_serv', $ing_serv);
      $querya->execute();
      $rowa = $querya->fetch(PDO::FETCH_ASSOC);
      $caja=$rowa['codigo'];

      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $queryb = $pdo->prepare("SELECT modelo, estado, proveedor FROM cajas 
                WHERE codigo =:caja");
      $queryb->bindParam(':caja', $caja);
      $queryb->execute();
      $rowb = $queryb->fetch(PDO::FETCH_ASSOC);

      if ($rowb['estado']!= "rentado") {
        
      
          //CONSULTA PARA TRAER TODOS LOS CAMPOS DE LA TABLA SERVICIO
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          $queryc = $pdo->prepare("SELECT * FROM servicios 
                    WHERE id_servicio =:ing_serv");
          $queryc->bindParam(':ing_serv', $ing_serv);
          $queryc->execute();
          $rowc = $queryc->fetch(PDO::FETCH_ASSOC);
          
            //CONSULTA PARA OBTENER EL ID DEL FALLECIDO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryd = $pdo->prepare("SELECT id_fallecido FROM servicio_fallecido 
                      WHERE id_servicio =:ing_serv");
            $queryd->bindParam(':ing_serv', $ing_serv);
            $queryd->execute();
            $rowd = $queryd->fetch(PDO::FETCH_ASSOC);
            $id_fallecido = $rowd['id_fallecido'];

            //CONSULTA PARA OBTENER DATOS DE LA TABLA FALLECIDO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $querye = $pdo->prepare("SELECT * FROM fallecido 
                      WHERE id_fallecido =:id_fallecido");
            $querye->bindParam(':id_fallecido', $id_fallecido);
            $querye->execute();
            $rowe = $querye->fetch(PDO::FETCH_ASSOC);

            //CONSULTA PARA OBTENER EL CODIGO DE LA CAJA
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryf = $pdo->prepare("SELECT codigo FROM servicio_caja 
                      WHERE id_servicio =:ing_serv");
            $queryf->bindParam(':ing_serv', $ing_serv);
            $queryf->execute();
            $rowf = $queryf->fetch(PDO::FETCH_ASSOC);

            //CONSULTA PARA EXTRAER LAS SILLAS
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryg = $pdo->prepare("SELECT id_equipo FROM servicio_equipo 
                      WHERE id_servicio =:ing_serv");
            $queryg->bindParam(':ing_serv', $ing_serv);
            $queryg->execute();
            $grupo_sillas="";
            while ($rowg = $queryg->fetch(PDO::FETCH_ASSOC)) {
              $silla_tmp=substr($rowg['id_equipo'],0,2);
              $silla_tmp40=substr($rowg['id_equipo'],0,3);
              if ($silla_tmp=="si" && $silla_tmp40=="si1") {
                //$grupo_sillas = $grupo_sillas . $rowg['id_equipo']. " ";
                $grupo_sillas = "sin sillas";
              }elseif ($silla_tmp=="si") {
                $grupo_sillas = $grupo_sillas . "40 " . $rowg['id_equipo']. " ";
              }
            }

            //CONSULTA PARA EXTRAER LOS EQUIPOS
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryh = $pdo->prepare("SELECT id_equipo FROM servicio_equipo 
                      WHERE id_servicio =:ing_serv");
            $queryh->bindParam(':ing_serv', $ing_serv);
            $queryh->execute();
            $rowh = $queryh->fetchAll(PDO::FETCH_ASSOC);
            $size=sizeof($rowh);
            $a=0;
            foreach ($rowh as $roh) {
              $equipos[$a] = $roh['id_equipo'];
              $a++;
            }
           
            

            //CODIGO PARA REIMPRIMIR SERVICIO
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
            $pdf->Cell(5,$textypos,$rowc['id_servicio']);
            $pdf->SetFont('Arial','',14);    
            $pdf->setY(50);$pdf->setX(135);
            $pdf->Cell(5,$textypos,"Fecha:");
            $pdf->setY(50);$pdf->setX(165);
            $pdf->Cell(5,$textypos,substr($rowc['created_at'],0,10));

        /// Apartir de aqui empezamos con la tabla de productos
            $pdf->Ln();
            $pdf->SetFont('Arial','',14);
            $pdf->setY(75);$pdf->setX(20);
            $pdf->Cell(5,$textypos,"Nombre del Fallecido:");
            $pdf->setY(75);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowe['nom_fallecido']));

            $pdf->setY(81);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Hospital:"));
            $pdf->setY(81);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowe['hospital']));

            $pdf->setY(87);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Domicilio de Velación:"));
            $pdf->setY(87);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowe['dom_velacion']));
            $pdf->setY(93);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Municipio:"));
            $pdf->setY(93);$pdf->setX(80);
            $pdf->Cell(5,$textypos,$rowe['municipio']);
            $pdf->setY(99);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Tipo de Servicio:"));
            $pdf->setY(99);$pdf->setX(80);
            $pdf->Cell(5,$textypos,$rowc['tipo_servicio']);
            $pdf->setY(105);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Ataud:"));
            $pdf->setY(105);$pdf->setX(80);
            $pdf->Cell(5,$textypos,$caja);
            $pdf->setY(111);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Modelo Ataud:"));
            $pdf->setY(111);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowb['modelo']));
            $pdf->setY(117);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Proveedor Ataud:"));
            $pdf->setY(117);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowb['proveedor']));
            $pdf->setY(123);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Biombo:"));
            $pdf->setY(123);$pdf->setX(80);
            $biombo = $equipos[0];
            $biombo_tmp = substr($biombo,0,3);
            if ($biombo_tmp == "bb1"){
              $biombo="sin biombo";
            }
            $pdf->Cell(5,$textypos,$biombo);
            $pdf->setY(129);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Pedestal:"));
            $pdf->setY(129);$pdf->setX(80);
            $pedestal=$equipos[5];
            $pedestal_tmp = substr($pedestal,0,3);
            if ($pedestal_tmp == "pd1"){
              $pedestal="sin pedestal";
            }
            $pdf->Cell(5,$textypos,$pedestal);
            $pdf->setY(135);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Torcheros:"));
            $pdf->setY(135);$pdf->setX(80);
            $torcheros=$equipos[$size-1];
            $torcheros_tmp = substr($torcheros,0,3);
            if ($torcheros_tmp == "tr1"){
              $torcheros="sin torcheros";
            }
            $pdf->Cell(5,$textypos,$torcheros);
            $pdf->setY(141);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Candeleros:"));
            $pdf->setY(141);$pdf->setX(80);
            $candeleros=$equipos[2];
            $candeleros_tmp = substr($candeleros,0,3);
            if ($candeleros_tmp == "cd1"){
              $candeleros="sin candeleros";
            }
            $pdf->Cell(5,$textypos,$candeleros);
            $pdf->setY(147);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Cristo/Angel:"));
            $pdf->setY(147);$pdf->setX(80);
            $cristo_angel=$equipos[3];
            $cristo_angel_tmp = substr($cristo_angel,0,3);
            if ($cristo_angel_tmp == "cr1"){
              $cristo_angel="sin cristo o angel";
            }
            $pdf->Cell(5,$textypos,$cristo_angel);
            $pdf->setY(153);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Floreros:"));
            $pdf->setY(153);$pdf->setX(80);
            $floreros=$equipos[4];
            $floreros_tmp = substr($floreros,0,3);
            if ($floreros_tmp == "fl1") {
              $floreros="sin floreros";
            }
            $pdf->Cell(5,$textypos,$floreros);
            $pdf->setY(159);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Carpa:"));
            $pdf->setY(159);$pdf->setX(80);
            $carpa=$equipos[1];
            $carpa_tmp = substr($carpa,0,3);
            if ($carpa_tmp == "ca1") {
              $carpa="sin carpa";
            }
            $pdf->Cell(5,$textypos,$carpa);
            $pdf->setY(165);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Sillas de Color:"));
            $pdf->SetFont('Arial','',10);
            $pdf->setY(165);$pdf->setX(80);
            $pdf->Cell(5,$textypos,$grupo_sillas);
            $pdf->SetFont('Arial','',14);
            $pdf->setY(171);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Cantidad de Velas:"));
            $pdf->setY(171);$pdf->setX(80);
            $pdf->Cell(5,$textypos,$rowc['velas']);
            $pdf->setY(177);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Cantidad de Despensas:"));
            $pdf->setY(177);$pdf->setX(80);
            $pdf->Cell(5,$textypos,$rowc['despensa']);
            $pdf->setY(183);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Responsable:"));
            $pdf->setY(183);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowc['responsable']));
            $pdf->setY(189);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Auxiliar:"));
            $pdf->setY(189);$pdf->setX(80);
            $pdf->Cell(5,$textypos,utf8_decode($rowc['auxiliares']));
            $pdf->setY(195);$pdf->setX(20);
            $pdf->Cell(5,$textypos,utf8_decode("Notas:"));
            $pdf->setY(195);$pdf->setX(80);
            $pdf->Multicell(100,$textypos,utf8_decode($rowc['notas']));
            $pdf->Line(65, 250, 138, 250);
            $pdf->setY(251);$pdf->setX(70);
            $pdf->Cell(5,$textypos,"Nombre y Firma del Familiar");
            $pdf->output();

      }else {
          //CONSULTA PARA TRAER TODOS LOS CAMPOS DE LA TABLA SERVICIO
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          $queryj = $pdo->prepare("SELECT * FROM servicios 
                    WHERE id_servicio =:ing_serv");
          $queryj->bindParam(':ing_serv', $ing_serv);
          $queryj->execute();

            //CONSULTA PARA OBTENER EL ID DEL FALLECIDO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryk = $pdo->prepare("SELECT id_fallecido FROM servicio_fallecido 
                      WHERE id_servicio =:ing_serv");
            $queryk->bindParam(':ing_serv', $ing_serv);
            $queryk->execute();
            $rowk = $queryk->fetch(PDO::FETCH_ASSOC);
            $id_fallecido = $rowk['id_fallecido'];

            //CONSULTA PARA OBTENER DATOS DE LA TABLA FALLECIDO
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $queryl = $pdo->prepare("SELECT * FROM fallecido 
                      WHERE id_fallecido =:id_fallecido");
            $queryl->bindParam(':id_fallecido', $id_fallecido);
            $queryl->execute();
            $rowl = $queryl->fetch(PDO::FETCH_ASSOC);

        //cierre IF ataud en renta o venta
        }

  //cierre de formulario form2  
  } 
   
//cierre sesión activa o inactiva
}else {
  header("location:index.php");
}
?>