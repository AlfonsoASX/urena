<?php
session_start();
if (isset($_SESSION["usuario"])) {
    require 'conexion.php';
    date_default_timezone_set('America/Mexico_city');
    
    if ($_POST) {
        $items1 = ($_POST['id_articulo']);
        $items2 = ($_POST['cantidad']);
        $identificadores = ($_POST['id_articulo']);
        $cantidades = ($_POST['cantidad']);
        $responsable = $_POST['responsable'];
        $solicitante = $_POST['solicitante'];
        $fecha= date("y-m-d");

        //CONSULTA PARA REALIZAR LA INSERCIÓN DE UN NUEVO VALE
        $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $querya = $pdo -> prepare("INSERT INTO vales_salida (responsable, solicitante ) 
                                  VALUES (:responsable, :solicitante) ");            
        $querya->bindParam(':responsable',$responsable);
        $querya->bindParam(':solicitante',$solicitante);
        $querya -> execute();
        $id_vale = $pdo->lastInsertId();

//CODIGO PARA IMPRIMIR EL VALE => INICIA CON $PDF
include "fpdf/fpdf.php";

$pdf = new FPDF('P','mm','letter', true);
$pdf->AddPage('portrait','letter');
$link='http://192.168.1.91/almacen/dashboard/ValeSalida.php';
$pdf->SetFont('Arial','B',18);    
$textypos = 5;
// Agregamos los datos de la empresa
$pdf->setY(28);$pdf->setX(65);
$pdf->Cell(65);
$pdf->Cell(5,$textypos,utf8_decode("GRUPO UREÑA FUNERARIOS"),0,0,'C',false,$link);
$pdf->SetFont('Arial','',10);    
$pdf->setY(33);$pdf->setX(92);
$pdf->Cell(5,$textypos,"Independencia No. 708");
$pdf->setY(37);$pdf->setX(96);
$pdf->Cell(5,$textypos,"Col. San Miguel");
$pdf->setY(41);$pdf->setX(98);
$pdf->Cell(5,$textypos,"477-454-0117");
$pdf->SetFont('Arial','B',12);    
$pdf->setY(55);$pdf->setX(150);
$pdf->Cell(5,$textypos,"VALE");
$pdf->SetFont('Arial','',12);
$pdf->setY(55);$pdf->setX(165);
$pdf->Cell(5,$textypos,$id_vale);    
$pdf->setY(60);$pdf->setX(150);
$pdf->Cell(5,$textypos,"Fecha");
$pdf->setY(60);$pdf->setX(165);
$pdf->Cell(5,$textypos,$fecha);
$pdf->setY(75);$pdf->setX(75);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(5,$textypos,utf8_decode("Vale de Salida de Articulos"));
$pdf->SetFont('Arial','B',14);
$pdf->setY(85);$pdf->setX(50);
$pdf->Cell(5,$textypos,"Id Art.");
$pdf->setY(85);$pdf->setX(70);
$pdf->Cell(5,$textypos,"Articulo");
$pdf->setY(85);$pdf->setX(110);
$pdf->Cell(5,$textypos,"Marca");
$pdf->setY(85);$pdf->setX(150);
$pdf->Cell(5,$textypos,"Cant.");
$pdf->Line(50, 90, 165, 90);

        ///////////// SEPARAR VALORES DE ARRAYS, EN ESTE CASO SON 4 ARRAYS UNO POR CADA INPUT (ID, NOMBRE, CARRERA Y GRUPO////////////////////)
                $artydynpos=85;
                while(true) {

				    //// RECUPERAR LOS VALORES DE LOS ARREGLOS ////////
                    $artydynpos=$artydynpos+6;
                    $item1 = current($items1);
                    $item2 = current($items2);
                    
                    $pdf->SetFont('Arial','I',8);
                    $pdf->setY($artydynpos);$pdf->setX(52);
                    $pdf->Cell(5,$textypos,utf8_decode($item1));
                    $pdf->setY($artydynpos);$pdf->setX(152);
                    $pdf->Cell(5,$textypos,utf8_decode($item2));
				    
				    ///////// QUERY DE INSERCIÓN ////////////////////////////
                    $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryb = $pdo -> prepare("INSERT INTO articulos_vale_salida (id, cantidad, id_vale) 
                                            VALUES (:id, :cantidad, :id_vale) ");
                    $queryb->bindParam(':id',$item1);
                    $queryb->bindParam(':cantidad',$item2);
                    $queryb->bindParam(':id_vale', $id_vale);
                    $queryb -> execute();

                    //consulta para obtener la existencia actual
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $queryc = $pdo->prepare("SELECT existencias, articulo, marca FROM articulos WHERE id=:id");
                    $queryc->bindParam(':id',$item1);
                    $queryc->execute();
                    $rowc = $queryc->fetch(PDO::FETCH_ASSOC);

                    $pdf->setY($artydynpos);$pdf->setX(70);
                    $pdf->Cell(5,$textypos,utf8_decode($rowc['articulo']));
                    $pdf->setY($artydynpos);$pdf->setX(110);
                    $pdf->Cell(5,$textypos,utf8_decode($rowc['marca']));

                        //MODULO PARA ACTUALIZAR LAS EXISTENCIAS AL MOMENTO DE INGRESAR PRODUCTOS AL ALMACEN
                        
                        $exist_ant = $rowc['existencias'];
                        $exist_act = $exist_ant - $item2;
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $queryd = $pdo->prepare("UPDATE articulos SET existencias = :exist_act WHERE id= :id");
                        $queryd->bindParam(':exist_act', $exist_act);
                        $queryd->bindParam(':id', $item1);
                        $queryd->execute();
                        
                    
				    
				    // Up! Next Value
				    $item1 = next( $items1 );
				    $item2 = next( $items2 );
				    
				    
				    // Check terminator
				    if($item1 === false && $item2 === false) break;
    
                }
                
$pdf->SetFont('Arial','',8);
$pdf->setY($artydynpos+34);$pdf->setX(50);
$pdf->Cell(5,$textypos,utf8_decode($responsable));
$pdf->Line(50, $artydynpos+38, 100, $artydynpos+38);
$pdf->setY($artydynpos+39);$pdf->setX(60);
$pdf->Cell(5,$textypos,"Entregado Por");
$pdf->setY($artydynpos+34);$pdf->setX(115);
$pdf->Cell(5,$textypos,utf8_decode($solicitante));
$pdf->Line(115, $artydynpos+38, 185, $artydynpos+38);
$pdf->setY($artydynpos+39);$pdf->setX(140);
$pdf->Cell(5,$textypos,"Solicitado Por");
$pdf->output();

    }
    //header("location:ValeSalida.php");
} else {
    header("location:index.php");
}
