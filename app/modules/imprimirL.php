<?php
// app/modules/imprimirL.php
require_once __DIR__.'/../core/bootstrap.php';
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../fpdf/fpdf.php';

$id_abono = (int)($_GET['id_abono'] ?? 0);

if ($id_abono <= 0) {
    die("Error: No se especificó un ID de abono válido.");
}

$sql = "
    SELECT 
        a.id_abono,
        a.fecha_registro,
        a.cant_abono,
        c.id_contrato,
        c.estatus,
        c.tipo_contrato,
        c.costo_final,
        c.tipo_pago,
        t.titular,
        COALESCE(CONCAT(p.nombre, ' ', p.apellido_p), 'Oficina') AS nombre_cobrador,
        GREATEST(
          c.costo_final - COALESCE((
            SELECT SUM(a2.cant_abono)
            FROM futuro_abonos a2
            WHERE a2.id_contrato = c.id_contrato
              AND a2.id_abono <= a.id_abono
          ),0),
          0
        ) AS saldo_calc
    FROM futuro_abonos a
    INNER JOIN futuro_contratos c ON c.id_contrato = a.id_contrato
    LEFT JOIN vw_titular_contrato t ON t.id_contrato = c.id_contrato
    LEFT JOIN futuro_abono_cobrador fac ON fac.id_abono = a.id_abono
    LEFT JOIN futuro_personal p ON p.id_personal = fac.id_personal
    WHERE a.id_abono = ?
    LIMIT 1
";

$datos = qone($sql, [$id_abono]);

if (!$datos) {
    die("Error: No se encontró el abono solicitado.");
}

// ---------------------------------------------------------
// FUNCIONES AUXILIARES
// ---------------------------------------------------------

function texto($str) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string)($str ?? ''), 'ISO-8859-1', 'UTF-8');
    }
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)($str ?? ''));
}

function asMoney($a) {
    return '$' . number_format((float)$a, 2);
}

// ---------------------------------------------------------
// SALDO CALCULADO DESPUÉS DE ESTE ABONO
// ---------------------------------------------------------

$saldo_calc = (float)($datos['saldo_calc'] ?? 0);

// ---------------------------------------------------------
// GENERACIÓN DEL PDF
// ---------------------------------------------------------

$pdf = new FPDF('P','mm');
$pdf->AddPage();

if (file_exists('logo.jpg')) {
    $pdf->Image('logo.jpg', 30, 16, 30, 20, 'jpg');
}

$link = 'https://www.grupourenafunerarios.com.mx/';
$textypos = 5;

$pdf->Cell(50);
$pdf->setY(15); $pdf->setX(125);
$pdf->SetFont('Arial', '', 16);
$pdf->Cell(5, $textypos, texto("Grupo Ureña Funerarios"), 0, 0, 'C', false, $link);

$pdf->SetFont('Arial', '', 10);
$pdf->setY(21); $pdf->setX(110);
$pdf->Cell(5, $textypos, "Independencia No. 708");
$pdf->setY(25); $pdf->setX(115);
$pdf->Cell(5, $textypos, "Col. San Miguel");
$pdf->setY(29); $pdf->setX(117);
$pdf->Cell(5, $textypos, "Tel. 477-7122326");

$pdf->SetFont('Arial', 'B', 14);
$pdf->setY(45); $pdf->setX(70);
$pdf->Cell(5, $textypos, texto("Comprobante de Pago"), 0, 0, 'L');

$pdf->SetFont('Arial', '', 12);
$y = 60;
$esp = 6;

$campos = [
    "Número de contrato:"   => $datos['id_contrato'],
    "Estatus del contrato:" => ucfirst((string)($datos['estatus'] ?? '')),
    "Tipo de contrato:"     => $datos['tipo_contrato'],
    "Costo final:"          => asMoney($datos['costo_final']),
    "Tipo de pago:"         => $datos['tipo_pago'],
    "Nombre del titular:"   => $datos['titular'],
    "Comprobante número:"   => str_pad((string)$datos['id_abono'], 6, "0", STR_PAD_LEFT),
    "Fecha de pago:"        => $datos['fecha_registro'] ? date('d/m/Y H:i', strtotime($datos['fecha_registro'])) : '',
    "Su pago:"              => asMoney($datos['cant_abono']),
    "Saldo pendiente:"      => asMoney($saldo_calc),
    "Cobrador:"             => $datos['nombre_cobrador'] ?? 'Oficina'
];

foreach ($campos as $titulo => $valor) {
    $pdf->setY($y); $pdf->setX(20);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(60, $textypos, texto($titulo), 0, 0, 'L');

    $pdf->setX(80);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(100, $textypos, texto($valor), 0, 0, 'L');

    $y += $esp;
}

$pdf->SetFont('Arial', 'I', 10);
$pdf->setY($y + 15); $pdf->setX(0);
$pdf->Cell(210, $textypos, texto("Gracias por su confianza. Fue un placer atenderle."), 0, 0, 'C');

$pdf->Output('I', 'Recibo_' . (int)$datos['id_abono'] . '.pdf');
?>
