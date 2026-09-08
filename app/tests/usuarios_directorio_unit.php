<?php

$directorioUsuarios = [
  1 => ['id'=>1,'usuario'=>'venta','nombre'=>'Venta Uno','puesto'=>'vendedor','jefe_directo_id'=>2,'acceso_sistema'=>1,'estatus'=>'ACTIVO'],
  2 => ['id'=>2,'usuario'=>null,'nombre'=>'Lider Uno','puesto'=>'lider','jefe_directo_id'=>3,'acceso_sistema'=>0,'estatus'=>'ACTIVO'],
  3 => ['id'=>3,'usuario'=>null,'nombre'=>'Gerente Uno','puesto'=>'gerente','jefe_directo_id'=>null,'acceso_sistema'=>0,'estatus'=>'ACTIVO'],
  4 => ['id'=>4,'usuario'=>null,'nombre'=>'Lider Inactivo','puesto'=>'lider','jefe_directo_id'=>3,'acceso_sistema'=>0,'estatus'=>'INACTIVO'],
];

function db_column_exists(string $table, string $column): bool { return $table==='usuarios'; }
function qone(string $sql, array $params=[]): ?array {
  global $directorioUsuarios;
  $row = $directorioUsuarios[(int)($params[0] ?? 0)] ?? null;
  if ($row && str_contains($sql, "UPPER(COALESCE(estatus,'ACTIVO'))='ACTIVO'") && $row['estatus']!=='ACTIVO') return null;
  return $row;
}
function qall(string $sql, array $params=[]): array {
  global $directorioUsuarios;
  if (str_contains($sql,'WHERE jefe_directo_id=?')) {
    return array_values(array_filter($directorioUsuarios,fn($u)=>(int)($u['jefe_directo_id'] ?? 0)===(int)$params[0] && $u['estatus']==='ACTIVO'));
  }
  return [];
}

require_once __DIR__.'/../core/usuarios_directorio.php';

function directorio_assert(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$valida = usuarios_directorio_jerarquia_comercial(1);
directorio_assert(!$valida['errores'], 'La cadena vendedor-lider-gerente valida fue rechazada.');
directorio_assert($valida['id_lider']===2 && $valida['id_gerente']===3, 'La cadena no resolvio los usuarios correctos.');

$directorioUsuarios[1]['jefe_directo_id']=4;
$inactiva = usuarios_directorio_jerarquia_comercial(1);
directorio_assert((bool)$inactiva['errores'], 'Se acepto un lider inactivo.');

$directorioUsuarios[1]['jefe_directo_id']=2;
$directorioUsuarios[2]['jefe_directo_id']=1;
$ciclo = usuarios_directorio_validar_asignacion(1,'vendedor',2);
directorio_assert((bool)array_filter($ciclo, fn($e)=>str_contains($e,'ciclo')), 'No se detecto la jerarquia circular.');
$directorioUsuarios[2]['jefe_directo_id']=3;
$dependientes = usuarios_directorio_validar_dependientes(2,'otro',true);
directorio_assert((bool)$dependientes,'Se permitio cambiar el puesto de un lider con vendedores activos.');

$sinAcceso = usuarios_directorio_validar_acceso(false,'',null,[]);
directorio_assert($sinAcceso===[], 'Un usuario de directorio no debe requerir credenciales.');
$conAcceso = usuarios_directorio_validar_acceso(true,'',null,[]);
directorio_assert(count($conAcceso)===3, 'El acceso debe exigir usuario, contrasena y rol.');

echo "OK: jerarquia, ciclos, inactivos y acceso de directorio.\n";
