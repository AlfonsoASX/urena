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
  'home_api'    => ['kpis','widgets'],
  'auth'        => ['login','logout','do_login'],
  'almacenes'   => ['listar','nuevo','guardar','editar','actualizar','baja','activar'],
  'articulos'   => ['listar','nuevo','guardar','editar','actualizar','baja','ajustar','historial'],
  'compras'     => ['listar','nuevo','guardar','ver'],
  'vales'       => ['listar','nuevo','guardar','ver','cancelar','agregar_articulo','quitar_articulo'],
  'cajas'       => ['listar','editar','nuevo','guardar','actualizar','baja','estado','reciclar','asignar','historial'],
  'fallecidos'  => ['listar','nuevo','guardar','editar','actualizar','baja'],
  'servicios'   => ['listar','nuevo','guardar','ver','editar','actualizar','asignar_caja','asignar_equipo','cambiar_estatus','cerrar','borrar'],
  'servicio_asignaciones' => ['ver','guardar','actualizar','agregar_articulo','cancelar_articulo','cancelar_caja','cancelar_equipos','orden_pdf'],
  'servicio_seguimiento'  => ['ver','guardar','agregar_consumo','agregar_incidencia'],
  'servicio_cierres'      => ['ver','guardar','retornar_parcial','imprimir','devolver_caja'],
  'servicio_pagos'        => ['listar','nuevo','guardar','recibo'],
  'servicio_reportes'     => ['orden_preparacion_pdf','consumo_pdf','acta_cierre_pdf','reporte_cierre_pdf'],
  'inventario_api'        => ['disponibilidad'],
  'servicios_api'         => ['consumos','devolucion_caja','asignar_caja','asignar_equipo','guardar_asignacion','cancelar_caja','cancelar_equipos','cancelar_consumo','devolucion_articulos','liberar_equipos'],
  'equipos'     => ['listar','nuevo','guardar','editar','actualizar','asignar','guardar_asignacion','retornar','guardar_retorno','baja','historial'],
  'proveedores' => ['listar','nuevo','guardar','editar','actualizar','baja','activar','inactivar'],
  'promociones' => ['listar','nuevo','guardar','editar','actualizar','baja','activar','migracion','migrar_contratos'],
  'pagos'       => ['reporte_base','reporte_comisiones','reporte_lideres','reporte_gerentes','reporte_empresa','reporte_cobradores','cortes_cobradores','configurar_cortes','guardar_configuracion_cortes','contratos','nuevo_abono','guardar_abono','corte','comisiones','guardar_comision','editar_contrato','guardar_contrato','borrar_abono'],
  'cobrador'    => ['panel','contratos','pago','pagos','estado','ticket', 'gestion'],
  'contratos'   => ['titulo_pdf','pdf','listar','nuevo','guardar','editar','actualizar','ver', 'eliminar'],
  'rutas'       => ['contratos', 'cobradores', 'asignar', 'asignar_masivo', ''],
  'usuarios'    => ['listar','nuevo','guardar','editar','actualizar','roles','guardar_rol','permisos','guardar_permiso','asignar_roles','asignar_permisos','cambiar_password','eliminar','editar_rol','actualizar_rol','directorio','directorio_guardar_mapeo'],
  'maintenance' => ['schema_repair','perf_report'],
  'migraciones' => ['index','inicializar','guardar','validar','ejecutar','consulta','descargar_respaldo'],


  // aquí puedes agregar después más módulos como usuarios, rentas, etc.
];


if (!isset($allowed[$module]) || !in_array($action, $allowed[$module])) {
  $module='home'; $action='index';
}

//echo $perfil;
if (($_SESSION['user']['puesto'] ?? '') === 'cobrador' && $module !== 'cobrador' && $module !== 'auth') {
  header('Location: index.php?r=cobrador.panel');
  exit;
}

require __DIR__ . "/modules/$module.php";
