<?php

$loginUsers = [
  'activo'=>['id'=>1,'usuario'=>'activo','pass'=>password_hash('ok',PASSWORD_DEFAULT),'estatus'=>'ACTIVO','acceso_sistema'=>1,'perfil'=>'usuario'],
  'sin_acceso'=>['id'=>2,'usuario'=>'sin_acceso','pass'=>password_hash('ok',PASSWORD_DEFAULT),'estatus'=>'ACTIVO','acceso_sistema'=>0,'perfil'=>'usuario'],
  'inactivo'=>['id'=>3,'usuario'=>'inactivo','pass'=>password_hash('ok',PASSWORD_DEFAULT),'estatus'=>'INACTIVO','acceso_sistema'=>1,'perfil'=>'usuario'],
  'directorio'=>['id'=>4,'usuario'=>'directorio','pass'=>null,'estatus'=>'ACTIVO','acceso_sistema'=>0,'perfil'=>null],
];

function db_column_exists(string $table,string $column): bool { return $table==='usuarios' && $column==='acceso_sistema'; }
function qone(string $sql,array $params=[]): ?array {
  global $loginUsers;
  $row=$loginUsers[(string)($params[0] ?? '')] ?? null;
  if (!$row) return null;
  if (str_contains($sql,"UPPER(COALESCE(estatus,'ACTIVO'))='ACTIVO'") && $row['estatus']!=='ACTIVO') return null;
  if (str_contains($sql,'acceso_sistema=1') && !$row['acceso_sistema']) return null;
  return $row;
}

$GLOBALS['cfg']=['modo_libre'=>false];
$_SESSION=[];
require_once __DIR__.'/../core/auth.php';

function login_assert(bool $condition,string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

login_assert(login('activo','ok'),'El usuario activo con acceso no pudo iniciar sesion.');
logout();
login_assert(!login('sin_acceso','ok'),'Se permitio iniciar sesion sin acceso_sistema.');
login_assert(!login('inactivo','ok'),'Se permitio iniciar sesion a un usuario inactivo.');
login_assert(!login('directorio','ok'),'Se permitio iniciar sesion a un usuario de directorio.');

echo "OK: login exige usuario activo, acceso y contrasena.\n";
