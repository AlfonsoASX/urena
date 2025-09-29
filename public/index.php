<?php

require __DIR__ . '/../app/core/bootstrap.php';

$route = $_GET['r'] ?? 'home.index';
list($module,$action) = explode('.', $route) + [null,null];

$allowed = [
  'home'       => ['index'],
  'auth'       => ['login','logout'],
  'articulos'  => ['listar','nuevo','guardar','editar','baja'],
  'compras'    => ['listar','nuevo','guardar'],
  'vales'      => ['listar','nuevo','guardar'],
  'cajas'      => ['listar','editar','nuevo','guardar','estado'],
  'fallecidos' => ['listar','nuevo','guardar'],
  'servicios'  => ['listar','nuevo','guardar','ver','asignar_caja','cerrar','borrar'],
  'equipos'    => ['listar','nuevo','guardar','editar','actualizar','asignar','guardar_asignacion','retornar','guardar_retorno','baja'],
  'proveedores'=> ['listar','nuevo','guardar','editar','actualizar','baja','activar','inactivar'],
  'pagos'      => ['contratos','nuevo_abono','guardar_abono','corte','comisiones','guardar_comision']

];

if (!isset($allowed[$module]) || !in_array($action, $allowed[$module])) {
  $module='home'; $action='index';
}

require __DIR__ . "/../app/modules/$module.php";
