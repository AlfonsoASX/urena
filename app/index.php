<?php
require __DIR__ . '/core/bootstrap.php';

if(!empty($_SESSION['user']['perfil']))
{
  $perfil = $_SESSION['user']['perfil'];
}
else
{
  $perfil='';

}
$route = $_GET['r'] ?? 'home.index';
list($module,$action) = explode('.', $route) + [null,null];

$allowed = [
  'home'        => ['index'],
  'auth'        => ['login','logout','do_login'],
  'articulos'   => ['listar','nuevo','guardar','editar','actualizar','baja','ajustar','historial'],
  'compras'     => ['listar','nuevo','guardar','ver'],
  'vales'       => ['listar','nuevo','guardar','ver','cancelar','agregar_articulo','quitar_articulo'],
  'cajas'       => ['listar','editar','nuevo','guardar','actualizar','baja','estado','reciclar','asignar'],
  'fallecidos'  => ['listar','nuevo','guardar','editar','actualizar','baja'],
  'servicios'   => ['listar','nuevo','guardar','ver','asignar_caja','asignar_equipo','cerrar','borrar'],
  'equipos'     => ['listar','nuevo','guardar','editar','actualizar','asignar','guardar_asignacion','retornar','guardar_retorno','baja'],
  'proveedores' => ['listar','nuevo','guardar','editar','actualizar','baja','activar','inactivar'],
  'pagos'       => ['contratos','nuevo_abono','guardar_abono','corte','comisiones','guardar_comision', 'editar_contrato', 'borrar_abono'],
  'cobrador'    => ['panel','contratos','pago','pagos','estado','ticket', 'gestion'],
  'contratos'   => ['pdf','listar','nuevo','guardar','editar','actualizar','ver', 'eliminar'],
  'rutas'   => ['contratos', 'cobradores', 'asignar', 'asignar_masivo', ''],
  'usuarios'   => ['listar','nuevo','guardar','editar','actualizar','roles','guardar_rol','permisos','guardar_permiso','asignar_roles','asignar_permisos','cambiar_password', 'eliminar', 'editar_rol', 'actualizar_rol'],


  // aquí puedes agregar después más módulos como usuarios, rentas, etc.
];


if (!isset($allowed[$module]) || !in_array($action, $allowed[$module])) {
  $module='home'; $action='index';
}

//echo $perfil;
if ($perfil === 'cobrador' && $module !== 'cobrador' && $module !== 'auth') {
  header('Location: index.php?r=cobrador.panel');
  exit;
}

require __DIR__ . "/modules/$module.php";
