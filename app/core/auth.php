<?php

// ── Bootstrap de autenticación ────────────────────────────────────────────────

/**
 * Puestos de trabajo disponibles en el sistema.
 */
function auth_puestos(): array {
  return [
    'administrador' => 'Administrador',
    'coordinador'   => 'Coordinador',
    'vendedor'      => 'Vendedor',
    'cobrador'      => 'Cobrador',
    'auxiliar'      => 'Auxiliar',
    'operario'      => 'Operario',
    'recepcionista' => 'Recepcionista',
    'otro'          => 'Otro',
  ];
}

/**
 * Catálogo canónico de permisos agrupados por módulo.
 * clave => descripción
 */
function auth_permisos_catalogo(): array {
  return [
    // ── Panel ────────────────────────────────────────────────────────────────
    'home.ver'                    => 'Panel principal — ver',

    // ── Servicios funerarios ─────────────────────────────────────────────────
    'servicios.ver'               => 'Servicios — ver lista',
    'servicios.crear'             => 'Servicios — crear nuevo',
    'servicios.editar'            => 'Servicios — editar datos',
    'servicios.cerrar'            => 'Servicios — cerrar/finalizar',
    'servicios.borrar'            => 'Servicios — dar de baja',

    // ── Sub-módulos de servicio ───────────────────────────────────────────────
    'servicio_asignaciones.ver'   => 'Asignaciones de servicio — ver/editar',
    'servicio_seguimiento.ver'    => 'Seguimiento de servicio — ver/editar',
    'servicio_cierres.ver'        => 'Cierres de servicio — ver/editar',
    'servicio_pagos.ver'          => 'Pagos de servicio — ver/registrar',
    'servicio_reportes.ver'       => 'Reportes de servicio — imprimir',

    // ── Fallecidos ────────────────────────────────────────────────────────────
    'fallecidos.ver'              => 'Fallecidos — ver lista',
    'fallecidos.admin'            => 'Fallecidos — crear/editar/baja',

    // ── Inventario ────────────────────────────────────────────────────────────
    'almacenes.ver'              => 'Almacenes — ver catalogo',
    'almacenes.admin'            => 'Almacenes — crear/editar/inactivar',
    'articulos.ver'               => 'Artículos — ver inventario',
    'articulos.admin'             => 'Artículos — crear/editar/ajustar',
    'cajas.ver'                   => 'Ataúdes — ver inventario',
    'cajas.admin'                 => 'Ataúdes — crear/editar/reciclar',
    'equipos.ver'                 => 'Equipos — ver inventario',
    'equipos.admin'               => 'Equipos — crear/editar/asignar',
    'vales.ver'                   => 'Vales de salida — ver',
    'vales.admin'                 => 'Vales de salida — crear/cancelar',
    'compras.ver'                 => 'Compras — ver',
    'compras.admin'               => 'Compras — crear',
    'proveedores.ver'             => 'Proveedores — ver',
    'proveedores.admin'           => 'Proveedores — crear/editar/baja',

    // ── Previsión / Futuros ───────────────────────────────────────────────────
    'contratos.ver'               => 'Contratos — ver',
    'contratos.admin'             => 'Contratos — crear/editar/eliminar',
    'promociones.ver'             => 'Promociones — ver',
    'promociones.admin'           => 'Promociones — crear/editar/inactivar',
    'pagos.ver'                   => 'Pagos y comisiones — ver',
    'pagos.admin'                 => 'Pagos y comisiones — gestionar',
    'rutas.ver'                   => 'Rutas de cobro — ver',
    'rutas.admin'                 => 'Rutas de cobro — asignar',
    'cobrador.panel'              => 'Panel cobrador — acceso',

    // ── Administración ────────────────────────────────────────────────────────
    'usuarios.admin'              => 'Usuarios — gestión completa',
    'maintenance.admin'           => 'Mantenimiento — reparación de esquema',
  ];
}

/**
 * Agrupa el catálogo de permisos por módulo (prefijo antes del punto).
 * Retorna ['modulo' => ['clave' => 'descripcion', ...], ...]
 */
function auth_permisos_por_modulo(): array {
  $etiquetas = [
    'home'                 => 'Panel',
    'servicios'            => 'Servicios funerarios',
    'servicio_asignaciones'=> 'Asignaciones de servicio',
    'servicio_seguimiento' => 'Seguimiento de servicio',
    'servicio_cierres'     => 'Cierres de servicio',
    'servicio_pagos'       => 'Pagos de servicio',
    'servicio_reportes'    => 'Reportes de servicio',
    'fallecidos'           => 'Fallecidos',
    'almacenes'            => 'Almacenes',
    'articulos'            => 'Artículos',
    'cajas'                => 'Ataúdes / Cajas',
    'equipos'              => 'Equipos',
    'vales'                => 'Vales de salida',
    'compras'              => 'Compras',
    'proveedores'          => 'Proveedores',
    'contratos'            => 'Contratos',
    'promociones'          => 'Promociones',
    'pagos'                => 'Pagos y comisiones',
    'rutas'                => 'Rutas de cobro',
    'cobrador'             => 'Panel cobrador',
    'usuarios'             => 'Administración de usuarios',
    'maintenance'          => 'Mantenimiento',
  ];
  $grupos = [];
  foreach (auth_permisos_catalogo() as $clave => $desc) {
    $prefix = explode('.', $clave)[0];
    $label  = $etiquetas[$prefix] ?? $prefix;
    $grupos[$label][$clave] = $desc;
  }
  return $grupos;
}

/**
 * DDL y siembra inicial para el sistema de roles/permisos.
 * Crea tablas si no existen, agrega columnas faltantes y siembra
 * roles + permisos canónicos.
 */
function auth_bootstrap(bool $force = false): void {
  static $ready = false;
  static $suppressed = false;
  if ($ready && !$force) return;
  if ($suppressed && !$force) return;
  if (!$force && !db_schema_bootstrap_on_request()) {
    $suppressed = true;
    return;
  }

  // ── Tabla roles ────────────────────────────────────────────────────────────
  try {
    q("CREATE TABLE IF NOT EXISTS roles (
      id        INT AUTO_INCREMENT PRIMARY KEY,
      slug      VARCHAR(60) NOT NULL UNIQUE,
      nombre    VARCHAR(120) NOT NULL,
      es_super  TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) {}
  db_add_column_if_missing('roles', 'slug',      "VARCHAR(60)  NOT NULL DEFAULT ''");
  db_add_column_if_missing('roles', 'nombre',    "VARCHAR(120) NOT NULL DEFAULT ''");
  db_add_column_if_missing('roles', 'es_super',  "TINYINT(1) NOT NULL DEFAULT 0");
  db_add_column_if_missing('roles', 'created_at',"TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

  // ── Tabla usuarios_roles ───────────────────────────────────────────────────
  try {
    q("CREATE TABLE IF NOT EXISTS usuarios_roles (
      id          INT AUTO_INCREMENT PRIMARY KEY,
      usuario_id  INT NOT NULL,
      rol_id      INT NOT NULL,
      UNIQUE KEY uq_usuario_rol (usuario_id, rol_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) {}

  // ── Tabla permisos ─────────────────────────────────────────────────────────
  try {
    q("CREATE TABLE IF NOT EXISTS permisos (
      id          INT AUTO_INCREMENT PRIMARY KEY,
      clave       VARCHAR(120) NOT NULL UNIQUE,
      descripcion VARCHAR(255) NOT NULL DEFAULT '',
      created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) {}
  db_add_column_if_missing('permisos', 'clave',      "VARCHAR(120) NOT NULL DEFAULT ''");
  db_add_column_if_missing('permisos', 'descripcion',"VARCHAR(255) NOT NULL DEFAULT ''");
  db_add_column_if_missing('permisos', 'created_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

  // ── Tabla roles_permisos ───────────────────────────────────────────────────
  try {
    q("CREATE TABLE IF NOT EXISTS roles_permisos (
      id         INT AUTO_INCREMENT PRIMARY KEY,
      rol_id     INT NOT NULL,
      permiso_id INT NOT NULL,
      UNIQUE KEY uq_rol_permiso (rol_id, permiso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) {}

  // ── Columna puesto en usuarios ─────────────────────────────────────────────
  db_add_column_if_missing('usuarios', 'puesto', "VARCHAR(60) DEFAULT NULL");

  // ── Sembrar roles predeterminados ─────────────────────────────────────────
  $rolesDefault = [
    ['administradora', 'Administradora', 1],
    ['coordinador',    'Coordinador',    0],
    ['vendedor',       'Vendedor',       0],
    ['cobrador',       'Cobrador',       0],
    ['auxiliar',       'Auxiliar',       0],
    ['operario',       'Operario',       0],
    ['recepcionista',  'Recepcionista',  0],
  ];
  foreach ($rolesDefault as [$slug, $nombre, $esSuper]) {
    try {
      q("INSERT IGNORE INTO roles (slug, nombre, es_super) VALUES (?,?,?)",
        [$slug, $nombre, $esSuper]);
    } catch (Throwable $e) {}
  }

  // ── Sembrar permisos canónicos ────────────────────────────────────────────
  foreach (auth_permisos_catalogo() as $clave => $desc) {
    try {
      q("INSERT IGNORE INTO permisos (clave, descripcion) VALUES (?,?)", [$clave, $desc]);
    } catch (Throwable $e) {}
  }

  $ready = true;
  $suppressed = false;
  auth_table_cache_clear();
}

// ── Funciones de autenticación ────────────────────────────────────────────────

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

function auth_table_cache_clear(): void {
  auth_table_exists('', true);
}

function auth_table_exists(string $table, bool $clearCache = false): bool {
  static $cache = [];
  if ($clearCache) {
    $cache = [];
    return false;
  }
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

function user_has_permission_or_legacy_role($permisos, $rolesLegacy){
  $modo_libre = $GLOBALS['cfg']['modo_libre'] ?? false;

  if($modo_libre) return true;

  $user = current_user();
  if(!$user) return false;
  if (user_is_super()) return true;
  if (auth_permissions_enabled()) return user_has_permission($permisos);
  return user_has_role($rolesLegacy);
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

function require_permission_or_legacy_role($permisos, $rolesLegacy){
  if(user_has_permission_or_legacy_role($permisos, $rolesLegacy)) return;
  flash("<div class='alert alert-danger'>No tienes permisos para esta acción.</div>");
  redirect('home.index');
}
