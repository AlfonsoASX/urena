<?php
// Versión hardcodeada para pruebas — sin dependencias ni variables externas

// Definimos clase objeto (simula la del sistema original)
class objeto {
    public $type;
    public $path;
    public $align;
    public $content;
    public $bold;
    public $format;
}

// Datos ficticios
$id_contrato   = 'CON-2025-001';
$estatus       = 'Activo';
$tipo_contrato = 'Plan Premium Familiar';
$costo_final   = 18500.50;
$tipo_pago     = 'Mensualidad';
$nom_comp_titu = 'Juan Pérez Ramírez';
$id_abono      = 'ABN-00987';
$fecha_abono   = '2025-10-24';
$cant_abono    = 950.00;
$saldo         = 17550.50;
$cobrador      = 'María López';

// Arreglo de salida
$a = array();

// Logo
/*
$obj2 = new objeto();       
$obj2->type = 1;
$obj2->path = 'http://urena.control.mx/img/logo.png';
$obj2->align = 1;
$a[] = $obj2;
*/
// Encabezado
$obj1 = new objeto();
$obj1->type = 0;
$obj1->content = 'Grupo Ureña Funerarios';
$obj1->bold = 1;
$obj1->align =1;
$obj1->format = 4;
$a[] = $obj1;
/*
$obj1 = new objeto();
$obj1->type = 0;
$obj1->content = '477-7122326';
$obj1->bold = 0;
$obj1->align =1;
$obj1->format = 4;
$a[] = $obj1;

$obj1 = new objeto();
$obj1->type = 0;
$obj1->content = ' ';
$a[] = $obj1;

// Datos del contrato
$obj1 = new objeto();
$obj1->type = 0;
$obj1->content = '----------Datos del Contrato----------';
$obj1->align =1;
$obj1->format = 4;
$a[] = $obj1;

function asMoney($m){ return '$'.number_format($m,2); }

$datosContrato = [
    'Numero de contrato:'   => $id_contrato,
    'Estatus del contrato:' => $estatus,
    'Tipo de contrato:'     => $tipo_contrato,
    'Costo final:'          => asMoney($costo_final),
    'Tipo de pago:'         => $tipo_pago
];

foreach ($datosContrato as $k => $v) {
    $obj = new objeto();
    $obj->type = 0;
    $obj->content = $k . ' ' . $v;
    $obj->bold = 0;
    $obj->align = 0;
    $obj->format = 4;
    $a[] = $obj;
}
// Titular
$a[] = (object)['type'=>0,'content'=>' ','align'=>0];
$a[] = (object)['type'=>0,'content'=>'----------Nombre del Titular----------','align'=>1,'format'=>4];
$a[] = (object)['type'=>0,'content'=>$nom_comp_titu,'align'=>0,'format'=>4];

// Datos del pago
$a[] = (object)['type'=>0,'content'=>' ','align'=>0];
$a[] = (object)['type'=>0,'content'=>'----------Datos del Pago----------','align'=>1,'format'=>4];


$datosPago = [
    'Comprobante No.:' => $id_abono,
    'Pago realizado:'  => $fecha_abono,
    'Su pago:'         => asMoney($cant_abono),
    'Saldo actual:'    => asMoney($saldo)
];

foreach ($datosPago as $k => $v) {
    $obj = new objeto();
    $obj->type = 0;
    $obj->content = $k . ' ' . $v;
    $obj->align = 0;
    $obj->format = 4;
    $a[] = $obj;
}

// Cobrador
$a[] = (object)['type'=>0,'content'=>' ','align'=>0];
$a[] = (object)['type'=>0,'content'=>'----------Pago recibido por----------','align'=>1,'format'=>4];
$a[] = (object)['type'=>0,'content'=>$cobrador,'align'=>0,'format'=>4];

// Mensaje final
$a[] = (object)['type'=>0,'content'=>' ','align'=>0];
$a[] = (object)['type'=>0,'content'=>'Fue un placer atenderle','align'=>1,'format'=>4];
$a[] = (object)['type'=>0,'content'=>' ','align'=>0];
*/
// Salida en formato JSON
echo json_encode($a, JSON_FORCE_OBJECT);
?>
