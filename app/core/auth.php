<?php

function logged_in(){return !empty($_SESSION['user']);}

function current_user(){return $_SESSION['user'] ?? null;}

function require_login(){if(!logged_in())redirect('auth.login');}

function login($usuario,$pass){
  $u=qone("SELECT * FROM usuarios WHERE usuario=?",[$usuario]);
  if($u && password_verify($pass,$u['pass'])){
    $_SESSION['user']=$u;
    return true;
  }
  return false;
}

function logout(){unset($_SESSION['user']);}

function auth_table_exists(string $table): bool {
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    $cache[$table] = false;
    return false;
  }
  try {
    qone("SELECT 1 FROM {$table} LIMIT 1");
    $cache[$table] = true;
  } catch (Throwable $e) {
    $cache[$table] = false;
  }
  return $cache[$table];
}

function auth_roles_enabled(): bool {
  return auth_table_exists('roles') && auth_table_exists('usuarios_roles');
}

function auth_permissions_enabled(): bool {
  return auth_table_exists('permisos') && auth_table_exists('roles_permisos');
}

function user_role_rows(): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $user = current_user();
  if (!$user) return [];

  $rows = [];
  if (auth_roles_enabled()) {
    try {
      $rows = qall(
        "SELECT r.id, r.slug, r.nombre, r.es_super
         FROM roles r
         JOIN usuarios_roles ur ON ur.rol_id = r.id
         WHERE ur.usuario_id = ?
         ORDER BY r.nombre",
        [$user['id']]
      );
    } catch (Throwable $e) {
      $rows = [];
    }
  }

  if (empty($rows) && !empty($user['perfil'])) {
    $rows[] = [
      'id' => null,
      'slug' => $user['perfil'],
      'nombre' => $user['perfil'],
      'es_super' => in_array($user['perfil'], ['admin', 'administradora'], true) ? 1 : 0,
    ];
  }

  $cache = $rows;
  return $rows;
}

function user_roles(): array {
  $roles = [];
  foreach (user_role_rows() as $row) {
    $roles[] = $row['slug'];
  }
  $roles = array_filter(array_unique($roles));
  return array_values($roles);
}

function user_is_super(): bool {
  foreach (user_role_rows() as $row) {
    if (!empty($row['es_super'])) return true;
    if (in_array($row['slug'], ['admin', 'administradora'], true)) return true;
  }
  return false;
}

function user_permissions(): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $user = current_user();
  if (!$user || !auth_roles_enabled() || !auth_permissions_enabled()) {
    $cache = [];
    return $cache;
  }

  try {
    $rows = qall(
      "SELECT DISTINCT p.clave
       FROM permisos p
       JOIN roles_permisos rp ON rp.permiso_id = p.id
       JOIN usuarios_roles ur ON ur.rol_id = rp.rol_id
       WHERE ur.usuario_id = ?
       ORDER BY p.clave",
      [$user['id']]
    );
    $perms = array_map(fn($row) => $row['clave'], $rows);
  } catch (Throwable $e) {
    $perms = [];
  }

  $cache = $perms;
  return $perms;
}

function user_has_role($roles){
  $modo_libre = $GLOBALS['cfg']['modo_libre'] ?? false;

  if($modo_libre) return true;

  $user = current_user();
  if(!$user) return false;
  if (user_is_super()) return true;
  if(is_string($roles)) $roles = [$roles];
  return (bool) array_intersect($roles, user_roles());
}

function user_has_permission($permisos){
  $modo_libre = $GLOBALS['cfg']['modo_libre'] ?? false;

  if($modo_libre) return true;

  $user = current_user();
  if(!$user) return false;
  if (user_is_super()) return true;
  if (!auth_permissions_enabled()) return user_has_role(['administradora', 'admin']);
  if(is_string($permisos)) $permisos = [$permisos];
  return (bool) array_intersect($permisos, user_permissions());
}

function require_role($roles){
  if(user_has_role($roles)) return;
  flash("<div class='alert alert-danger'>No tienes permisos para esta acción.</div>");
  redirect('home.index');
}

function require_permission($permisos){
  if(user_has_permission($permisos)) return;
  flash("<div class='alert alert-danger'>No tienes permisos para esta acción.</div>");
  redirect('home.index');
}
