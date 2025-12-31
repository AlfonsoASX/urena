<?php
// 1. INICIO DEL BUFFER (Captura cualquier salida accidental)
ob_start();

require_once __DIR__.'/../core/bootstrap.php';
require_once __DIR__.'/../core/db.php';


// Configuración de errores: Ocultarlos en pantalla para no romper el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers para evitar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

// --- Cargas de archivos ---
// Usamos rutas absolutas para evitar problemas



// --- Lógica ---

$id_abono = isset($_GET['id_abono']) ? (int)$_GET['id_abono'] : 0;
$response = [];

// Función para salir con error seguro
function sendError($msg) {
    // Limpiamos buffer antes de salir
    ob_clean(); 
    echo json_encode([[
        'type' => 0,
        'content' => $msg,
        'align' => 1,
        'bold' => 0,
        'format' => 0
    ]]);
    exit;
}

if ($id_abono <= 0) {
    sendError('Error: ID invalido');
}

$sql = "
    SELECT 
        a.id_abono, a.fecha_registro, a.cant_abono, a.saldo,
        c.id_contrato, c.estatus, c.tipo_contrato, 
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

// Asegúrate que tu función qone() existe y funciona. Si falla, el script muere.
// Si usas PDO directo, adáptalo. Asumo que qone() es de tu framework.
$datos = qone($sql, [$id_abono]);

if (!$datos) {
    sendError('Ticket no encontrado');
}

// --- Funciones Auxiliares ---

function asMoney($val) {
    return '$' . number_format((float)$val, 2);
}

function fila($izquierda, $derecha) {
    $ancho_papel = 32; 
    // Limpieza de caracteres extraños para el conteo
    $txt_izq = trim((string)$izquierda);
    $txt_der = trim((string)$derecha);
    
    // mb_strlen cuenta caracteres reales, no bytes
    $len_izq = mb_strlen($txt_izq, 'UTF-8');
    $len_der = mb_strlen($txt_der, 'UTF-8');
    
    $espacios = $ancho_papel - ($len_izq + $len_der);
    if ($espacios < 1) $espacios = 1;
    
    return $txt_izq . str_repeat(' ', $espacios) . $txt_der;
}

// --- Construcción del Array (Sin Clases para máxima compatibilidad) ---
// A veces json_encode con objetos privados da problemas, usaremos Arrays asociativos puros.

function addItem($content, $align=0, $bold=0, $format=0) {
    return [
        "type" => 0,              // INT puro
        "content" => (string)$content,
        "align" => (int)$align,   // INT puro
        "bold" => (int)$bold,     // INT puro
        "format" => (int)$format  // INT puro
    ];
}

$response[] = addItem("GRUPO URENA FUNERARIOS", 1, 1, 1); // Evitar Ñ en título por si acaso
$response[] = addItem("Independencia No. 708", 1);
$response[] = addItem("Col. San Miguel", 1);
$response[] = addItem("Tel. 477-7122326", 1);
$response[] = addItem("--------------------------------", 1, 1);

$response[] = addItem("RECIBO DE PAGO", 1, 1, 1);
$response[] = addItem(" "); 

$response[] = addItem(fila("Contrato:", $datos['id_contrato']));
$response[] = addItem("Titular:", 0, 1);
// Convertir a UTF8 explícito si tu BD no lo está
$titular = mb_convert_encoding($datos['titular'], 'UTF-8', 'auto');
$response[] = addItem($titular, 0, 0);

$response[] = addItem(fila("Estatus:", $datos['estatus']));
$response[] = addItem("--------------------------------", 1, 1);

$folio = str_pad($datos['id_abono'], 6, "0", STR_PAD_LEFT);
$response[] = addItem(fila("Folio:", $folio), 0, 1);
$response[] = addItem(fila("Fecha:", date('d/m/y H:i', strtotime($datos['fecha_registro']))));

$response[] = addItem(" ");
$response[] = addItem("IMPORTE PAGADO", 1, 1, 0);
$response[] = addItem(asMoney($datos['cant_abono']), 1, 1, 1);

$response[] = addItem(" ");
$response[] = addItem(fila("Saldo Restante:", asMoney($datos['saldo'])));
$response[] = addItem("Cobrador:", 0, 1);
$cobrador = $datos['nombre_cobrador'] ? $datos['nombre_cobrador'] : 'Oficina';
$response[] = addItem($cobrador, 0);

$response[] = addItem("--------------------------------", 1, 1);
$response[] = addItem("Gracias por su confianza.", 1);
$response[] = addItem("\n\n\n", 0); // Saltos finales obligatorios

// ---------------------------------------------------------
// 2. SALIDA LIMPIA (Aquí ocurre la magia)
// ---------------------------------------------------------

// Borramos TODO lo que se haya impreso antes (espacios en includes, warnings, etc)
ob_clean(); 

// Imprimimos SOLO el JSON
echo json_encode($response, JSON_FORCE_OBJECT);


// Finalizamos el script
exit;
?>