<?php
// app/modules/imprimirM.php

// 1. Cargar configuración y BD (Igual que en imprimirL.php)
if (file_exists(__DIR__.'/../config.php')) {
    require_once __DIR__.'/../config.php';
} elseif (file_exists(__DIR__.'/../core/config.php')) {
    require_once __DIR__.'/../core/config.php';
} else {
    // Fallback silencioso o error JSON
    die(json_encode([['type'=>0, 'content'=>'Error: Config no encontrada', 'align'=>1]]));
}

require_once __DIR__.'/../core/db.php';

// 2. Obtener ID y validar
$id_abono = (int)($_GET['id_abono'] ?? 0);
$response = [];

if ($id_abono <= 0) {
    echo json_encode([['type'=>0, 'content'=>'Error: ID inválido', 'align'=>1]]);
    exit;
}

// 3. Consultar datos (Misma consulta que el PDF)
$sql = "
    SELECT 
        a.id_abono, a.fecha_registro, a.cant_abono, a.saldo,
        c.id_contrato, c.estatus, c.tipo_contrato, c.costo_final, c.tipo_pago,
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
    echo json_encode([['type'=>0, 'content'=>'Error: Ticket no encontrado', 'align'=>1]]);
    exit;
}

// 4. Funciones auxiliares
function asMoney($val) {
    return '$' . number_format((float)$val, 2);
}

// Clase simple para estructurar los datos según pide la app
class Item {
    public $type = 0;      // 0: Texto, 1: Imagen
    public $content = '';  // Texto a imprimir
    public $align = 0;     // 0: Izq, 1: Centro, 2: Der
    public $bold = 0;      // 0: Normal, 1: Negrita
    public $format = 0;    // Tamaño: 0 normal, 1 doble altura, etc.
    
    // Constructor rápido
    public function __construct($content, $align=0, $bold=0, $format=0) {
        $this->content = (string)$content;
        $this->align = $align;
        $this->bold = $bold;
        $this->format = $format;
    }
}

// ---------------------------------------------------------
// CONSTRUCCIÓN DEL TICKET (JSON)
// ---------------------------------------------------------

// --- Logo (Opcional, si la app soporta imagen por URL) ---
// $img = new Item('', 1);
// $img->type = 1;
// $img->path = 'http://tudominio.com/logo_bwy.png'; // Debe ser blanco y negro pequeño
// $response[] = $img;

// --- Encabezado ---
$response[] = new Item("GRUPO UREÑA FUNERARIOS", 1, 1, 1); // Centrado, Negrita, Grande
$response[] = new Item("Independencia No. 708", 1);
$response[] = new Item("Col. San Miguel", 1);
$response[] = new Item("Tel. 477-7122326", 1);
$response[] = new Item("--------------------------------", 1);

// --- Datos Contrato ---
$response[] = new Item("RECIBO DE PAGO", 1, 1, 1);
$response[] = new Item(" ", 0); // Espacio vacío

$response[] = new Item("Contrato: " . $datos['id_contrato'], 0, 1);
$response[] = new Item("Titular:  " . $datos['titular'], 0, 0);
$response[] = new Item("Estatus:  " . $datos['estatus'], 0);
$response[] = new Item("Tipo:     " . $datos['tipo_contrato'], 0);
$response[] = new Item("--------------------------------", 1);

// --- Datos Financieros ---
// Alinear números a la derecha suele verse mejor, pero requiere calcular espacios.
// Aquí lo haremos simple línea por línea.

$response[] = new Item("Folio Abono: " . str_pad($datos['id_abono'], 6, "0", STR_PAD_LEFT), 0, 1);
$response[] = new Item("Fecha: " . date('d/m/Y H:i', strtotime($datos['fecha_registro'])), 0);

$response[] = new Item(" ", 0);
$response[] = new Item("IMPORTE PAGADO:", 1, 0);
$response[] = new Item(asMoney($datos['cant_abono']), 1, 1, 1); // Centrado, Grande

$response[] = new Item(" ", 0);
$response[] = new Item("Saldo Restante: " . asMoney($datos['saldo']), 0);
$response[] = new Item("Cobrador: " . ($datos['nombre_cobrador'] ?? 'Oficina'), 0);

// --- Pie de página ---
$response[] = new Item("--------------------------------", 1);
$response[] = new Item("Gracias por su confianza.", 1);
$response[] = new Item("Fue un placer atenderle.", 1);
$response[] = new Item(" ", 0);
$response[] = new Item(" ", 0); // Espacio extra para cortar papel

// 5. Salida JSON
// Importante: Cabecera JSON y NO usar JSON_FORCE_OBJECT
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>