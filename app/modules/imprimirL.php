<?php
// app/modules/imprimirL.php
// Requiere acceso a la base de datos y librería FPDF
require_once __DIR__.'/../core/bootstrap.php';
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../fpdf/fpdf.php';

// 1. Obtener ID del abono desde la URL
$id_abono = (int)($_GET['id_abono'] ?? 0);

if ($id_abono <= 0) {
    die("Error: No se especificó un ID de abono válido.");
}

// 2. Consultar datos reales de la BD
// Hacemos JOIN con contratos, titulares y personal para tener todo el recibo completo
$sql = "
    SELECT 
        a.id_abono,
        a.fecha_registro,
        a.cant_abono,
        a.saldo,
        c.id_contrato,
        c.estatus,
        c.tipo_contrato,
        c.costo_final,
        c.tipo_pago,
        t.titular,
        CONCAT(p.nombre, ' ', p.apellido_p) AS nombre_cobrador
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

// Helper para compatibilidad con PHP 8.2+ (Reemplazo de utf8_decode)
function texto($str) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str ?? '', 'ISO-8859-1', 'UTF-8');
    }
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str ?? '');
}

function asMoney($a) {
    return '$' . number_format((float)$a, 2);
}

// ---------------------------------------------------------
// GENERACIÓN DEL PDF
// ---------------------------------------------------------

$pdf = new FPDF('P','mm');
$pdf->AddPage();

// Logo
if (file_exists('logo.jpg')) {
    $pdf->Image('logo.jpg', 30, 16, 30, 20, 'jpg');
}

$link = 'https://asx.mx/';
$textypos = 5;

// --- Encabezado de Empresa ---
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

// --- Título del Documento ---
$pdf->SetFont('Arial', 'B', 14);    
$pdf->setY(45); $pdf->setX(70);
$pdf->Cell(5, $textypos, texto("Comprobante de Pago"), 0, 0, 'L');

// --- Mapeo de Datos de la BD al PDF ---
$pdf->SetFont('Arial', '', 12);
$y = 60;
$esp = 6; // Espaciado vertical un poco más amplio

$campos = [
    "Número de contrato:"   => $datos['id_contrato'],
    "Estatus del contrato:" => ucfirst($datos['estatus']),
    "Tipo de contrato:"     => $datos['tipo_contrato'],
    "Costo final:"          => asMoney($datos['costo_final']),
    "Tipo de pago:"         => $datos['tipo_pago'],
    "Nombre del titular:"   => $datos['titular'],
    "Comprobante número:"   => str_pad($datos['id_abono'], 6, "0", STR_PAD_LEFT), // Formato 000123
    "Fecha de pago:"        => date('d/m/Y H:i', strtotime($datos['fecha_registro'])),
    "Su pago:"              => asMoney($datos['cant_abono']),
    "Saldo pendiente:"      => asMoney($datos['saldo']), // Nota: 'saldo' en futuro_abonos suele ser el saldo restante
    "Cobrador:"             => $datos['nombre_cobrador'] ?? 'Oficina'
];

foreach ($campos as $titulo => $valor) {
    // Etiqueta
    $pdf->setY($y); $pdf->setX(20);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(60, $textypos, texto($titulo), 0, 0, 'L');
    
    // Valor
    $pdf->setX(80); // Ajuste de posición X para alinear valores
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(100, $textypos, texto($valor), 0, 0, 'L');
    
    $y += $esp;
}

// --- Pie de página ---
$pdf->SetFont('Arial', 'I', 10);    
$pdf->setY($y + 15); $pdf->setX(0);
$pdf->Cell(210, $textypos, texto("Gracias por su confianza. Fue un placer atenderle."), 0, 0, 'C');

// --- Salida ---
// 'I' envía el archivo al navegador para previsualizar, con nombre sugerido
$pdf->Output('I', 'Recibo_' . $datos['id_abono'] . '.pdf');
?>