<?php
// Versión de prueba — imprime comprobante con datos inventados
require '../fpdf/fpdf.php';

// Datos ficticios
$id_contrato = 'CON-2025-001';
$estatus = 'Activo';
$tipo_contrato = 'Plan Premium Familiar';
$costo_final = 18500.50;
$tipo_pago = 'Mensualidad';
$nom_comp_titu = 'Juan Pérez Ramírez';
$id_abono = 'ABN-00987';
$fecha_abono = '2025-10-24';
$cant_abono = 950.00;
$saldo = 17550.50;
$cobrador = 'María López';

// Función para formato de dinero
function asMoney($a){
    return '$'.number_format($a,2);
}

// Generar PDF
$pdf = new FPDF('P','mm');
$pdf->AddPage();

// Logo (si no existe la imagen, comenta esta línea)
if (file_exists('logo.jpg')) {
    $pdf->Image('logo.jpg',30,16,30,20,'jpg');
}

$link='https://asx.mx/';
$pdf->SetFont('Arial','',16);    
$textypos = 5;

// Datos de empresa
$pdf->Cell(50);
$pdf->setY(15);$pdf->setX(125);
$pdf->SetFont('Arial','',16);
$pdf->Cell(5,$textypos,utf8_decode("Grupo Ureña Funerarios"),0,0,'C',false,$link);
$pdf->SetFont('Arial','',10);    
$pdf->setY(21);$pdf->setX(110);
$pdf->Cell(5,$textypos,"Independencia No. 708");
$pdf->setY(25);$pdf->setX(115);
$pdf->Cell(5,$textypos,"Col. San Miguel");
$pdf->setY(29);$pdf->setX(117);
$pdf->Cell(5,$textypos,"Tel. 477-7122326");

// Título
$pdf->SetFont('Arial','B',14);    
$pdf->setY(45);$pdf->setX(70);
$pdf->Cell(5,$textypos,"Comprobante de Pago",0,0,'L');

// Datos del contrato
$pdf->SetFont('Arial','',12);
$y = 60;
$esp = 5;

$campos = [
    "Número de contrato:" => $id_contrato,
    "Estatus del contrato:" => $estatus,
    "Tipo de contrato:" => $tipo_contrato,
    "Costo final:" => asMoney($costo_final),
    "Tipo de pago:" => $tipo_pago,
    "Nombre del titular:" => $nom_comp_titu,
    "Comprobante número:" => $id_abono,
    "Pago realizado:" => $fecha_abono,
    "Su pago:" => asMoney($cant_abono),
    "Saldo actual:" => asMoney($saldo),
    "Pago recibido por:" => $cobrador
];

foreach($campos as $titulo => $valor){
    $pdf->setY($y);$pdf->setX(20);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(5,$textypos,utf8_decode($titulo));
    $pdf->setY($y);$pdf->setX(75);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(5,$textypos,utf8_decode($valor));
    $y += $esp;
}

// Mensaje final
$pdf->SetFont('Arial','I',12);    
$pdf->setY($y+10);$pdf->setX(60);
$pdf->Cell(5,$textypos,utf8_decode("Gracias por su confianza. Fue un placer atenderle."));

// Salida del PDF
$pdf->Output();
?>
