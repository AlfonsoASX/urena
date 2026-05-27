<?php
// app/modules/usuarios.php — Administración de usuarios, roles y permisos
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();
require_permission('usuarios.admin');

$action = $action ?? 'listar';

// ── Helpers ────────────────────────────────────────────────────────────────────

function usuarios_roles_disponibles(): array {
  if (!auth_roles_enabled()) return [];
  return qall("SELECT id, slug, nombre, es_super FROM roles ORDER BY nombre ASC");
}

function usuarios_permisos_disponibles(): array {
  if (!auth_permissions_enabled()) return [];
  return qall("SELECT id, clave, descripcion FROM permisos ORDER BY clave ASC");
}

function usuarios_asignar_roles(int $usuario_id, array $roles_ids): void {
  if (!auth_roles_enabled()) return;
  q("DELETE FROM usuarios_roles WHERE usuario_id=?", [$usuario_id]);
  foreach ($roles_ids as $rol_id) {
    if (!$rol_id) continue;
    q("INSERT INTO usuarios_roles (usuario_id, rol_id) VALUES (?, ?)", [$usuario_id, $rol_id]);
  }
}

function usuarios_asignar_permisos(int $rol_id, array $perm_ids): void {
  if (!auth_permissions_enabled()) return;
  q("DELETE FROM roles_permisos WHERE rol_id=?", [$rol_id]);
  foreach ($perm_ids as $perm_id) {
    if (!$perm_id) continue;
    q("INSERT INTO roles_permisos (rol_id, permiso_id) VALUES (?, ?)", [$rol_id, $perm_id]);
  }
}

/** Retorna el mapa clave→id de permisos existentes en la BD */
function usuarios_permisos_map(): array {
  static $cache = null;
  if ($cache !== null) return $cache;
  if (!auth_permissions_enabled()) { $cache = []; return $cache; }
  foreach (auth_permisos_catalogo() as $clave => $desc) {
    try {
      q("INSERT INTO permisos (clave, descripcion) VALUES (?, ?) ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion)", [$clave, $desc]);
    } catch (Throwable $e) {
    }
  }
  $rows  = qall("SELECT id, clave FROM permisos ORDER BY clave ASC");
  $cache = [];
  foreach ($rows as $r) $cache[$r['clave']] = (int)$r['id'];
  return $cache;
}

/** HTML de un selector de puesto */
function usuarios_puesto_select(string $selected = '', string $name = 'puesto'): string {
  $html = "<div class='mb-3'><label class='form-label'>Puesto de trabajo</label>"
        . "<select name='".e($name)."' class='form-select'>"
        . "<option value=''>— Sin especificar —</option>";
  foreach (auth_puestos() as $val => $label) {
    $sel  = $selected === $val ? ' selected' : '';
    $html .= "<option value='".e($val)."'{$sel}>".e($label)."</option>";
  }
  $html .= "</select></div>";
  return $html;
}

// ── Switch principal ────────────────────────────────────────────────────────────

switch ($action) {

  // ── Listar usuarios ──────────────────────────────────────────────────────────
  case 'listar':
  default:
    if (auth_roles_enabled()) {
      $rows = qall("
        SELECT u.id, u.usuario, u.nombre, COALESCE(u.puesto,'') AS puesto,
               GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ') AS roles
        FROM usuarios u
        LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
        LEFT JOIN roles r ON r.id = ur.rol_id
        WHERE u.estatus='ACTIVO'
        GROUP BY u.id, u.usuario, u.nombre, u.puesto
        ORDER BY u.nombre ASC
      ");
    } else {
      $rows = qall("
        SELECT u.id, u.usuario, u.nombre, COALESCE(u.puesto,'') AS puesto, '' AS roles
        FROM usuarios u
        WHERE u.estatus='ACTIVO'
        ORDER BY u.nombre ASC
      ");
    }
    $puestoLabels = auth_puestos();

    ob_start(); ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <h1 class="h4 m-0">Usuarios</h1>
      <div class="d-flex gap-2">
        <a href="?r=usuarios.nuevo" class="btn btn-success btn-sm">Nuevo usuario</a>
        <a href="?r=usuarios.roles" class="btn btn-outline-secondary btn-sm">Roles</a>
        <a href="?r=usuarios.permisos" class="btn btn-outline-secondary btn-sm">Permisos</a>
      </div>
    </div>

    <?php if (!auth_roles_enabled()): ?>
      <div class="alert alert-warning">
        Las tablas de roles no están disponibles. Ve a
        <a href="?r=maintenance.schema_repair">Mantenimiento → Reparar esquema</a> para activarlas.
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Nombre</th>
            <th>Puesto</th>
            <th>Roles</th>
            <th style="width:260px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['usuario']) ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td>
              <?php if ($r['puesto'] !== ''): ?>
                <span class="badge text-bg-secondary"><?= e($puestoLabels[$r['puesto']] ?? $r['puesto']) ?></span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td><?= e($r['roles'] ?? '—') ?></td>
            <td class="d-flex flex-wrap gap-1">
              <a class="btn btn-outline-primary btn-sm" href="?r=usuarios.editar&id=<?= (int)$r['id'] ?>">Editar</a>
              <a class="btn btn-outline-warning btn-sm" href="?r=usuarios.cambiar_password&id=<?= (int)$r['id'] ?>">Contraseña</a>
              <form method="post" action="?r=usuarios.eliminar&id=<?= (int)$r['id'] ?>" class="m-0 p-0 d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('¿Eliminar usuario #<?= (int)$r['id'] ?> (<?= e($r['usuario']) ?>)?');">
                  Eliminar
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted">Sin usuarios</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    render('Usuarios', ob_get_clean());
    break;

  // ── Nuevo usuario ────────────────────────────────────────────────────────────
  case 'nuevo':
    $roles = usuarios_roles_disponibles();
    ob_start(); ?>
    <div class="card shadow-sm" style="max-width:640px">
      <div class="card-body">
        <h1 class="h5 mb-3">Nuevo usuario</h1>
        <form method="post" action="?r=usuarios.guardar">
          <?= csrf_field() ?>
          <?= form_input('usuario', 'Usuario', '', ['required'=>true]) ?>
          <?= form_input('nombre', 'Nombre completo', '', ['required'=>true]) ?>
          <?= form_input('pass', 'Contraseña', '', ['type'=>'password','required'=>true]) ?>
          <?= usuarios_puesto_select() ?>
          <?php if (!empty($roles)): ?>
            <div class="mb-3">
              <label class="form-label">Roles</label>
              <div class="row row-cols-2 g-1">
                <?php foreach ($roles as $rol): ?>
                  <div class="col">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int)$rol['id'] ?>" id="nrol<?= (int)$rol['id'] ?>">
                      <label class="form-check-label" for="nrol<?= (int)$rol['id'] ?>"><?= e($rol['nombre']) ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Guardar</button>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">Volver</button>
          </div>
        </form>
      </div>
    </div>
    <?php
    render('Nuevo usuario', ob_get_clean());
    break;

  // ── Guardar nuevo usuario ────────────────────────────────────────────────────
  case 'guardar':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar');
    }
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre  = trim($_POST['nombre'] ?? '');
    $pass    = (string)($_POST['pass'] ?? '');
    $puesto  = trim($_POST['puesto'] ?? '');
    if ($usuario === '' || $nombre === '' || $pass === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.nuevo');
    }
    $puestosValidos = array_keys(auth_puestos());
    if ($puesto !== '' && !in_array($puesto, $puestosValidos, true)) $puesto = '';
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    q("INSERT INTO usuarios (usuario, nombre, pass, puesto) VALUES (?,?,?,?)",
      [$usuario, $nombre, $hash, $puesto ?: null]);
    $id = (int)db()->lastInsertId();
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    usuarios_asignar_roles($id, $roles_ids);
    flash("<div class='alert alert-success'>Usuario creado.</div>");
    redirect('usuarios.listar');
    break;

  // ── Editar usuario ───────────────────────────────────────────────────────────
  case 'editar':
    $id = (int)($_GET['id'] ?? 0);
    $u  = qone("SELECT * FROM usuarios WHERE id=? AND estatus='ACTIVO'", [$id]);
    if (!$u) { flash("<div class='alert alert-warning'>Usuario no encontrado.</div>"); redirect('usuarios.listar'); }
    $roles = usuarios_roles_disponibles();
    $roles_asignados = [];
    if (auth_roles_enabled()) {
      $roles_asignados = array_map(
        fn($r) => (int)$r['rol_id'],
        qall("SELECT rol_id FROM usuarios_roles WHERE usuario_id=?", [$id])
      );
    }
    ob_start(); ?>
    <div class="card shadow-sm" style="max-width:640px">
      <div class="card-body">
        <h1 class="h5 mb-3">Editar usuario</h1>
        <form method="post" action="?r=usuarios.actualizar&id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <?= form_input('usuario', 'Usuario', $u['usuario'], ['required'=>true]) ?>
          <?= form_input('nombre', 'Nombre completo', $u['nombre'], ['required'=>true]) ?>
          <?= usuarios_puesto_select($u['puesto'] ?? '') ?>
          <?php if (!empty($roles)): ?>
            <div class="mb-3">
              <label class="form-label">Roles</label>
              <div class="row row-cols-2 g-1">
                <?php foreach ($roles as $rol): ?>
                  <div class="col">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="roles[]"
                             value="<?= (int)$rol['id'] ?>" id="erol<?= (int)$rol['id'] ?>"
                             <?= in_array((int)$rol['id'], $roles_asignados, true) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="erol<?= (int)$rol['id'] ?>"><?= e($rol['nombre']) ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Actualizar</button>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">Volver</button>
          </div>
        </form>
      </div>
    </div>
    <?php
    render('Editar usuario', ob_get_clean());
    break;

  // ── Actualizar usuario ───────────────────────────────────────────────────────
  case 'actualizar':
    $id = (int)($_GET['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar');
    }
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre  = trim($_POST['nombre'] ?? '');
    $puesto  = trim($_POST['puesto'] ?? '');
    if ($usuario === '' || $nombre === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect("usuarios.editar&id={$id}");
    }
    $puestosValidos = array_keys(auth_puestos());
    if ($puesto !== '' && !in_array($puesto, $puestosValidos, true)) $puesto = '';
    q("UPDATE usuarios SET usuario=?, nombre=?, puesto=? WHERE id=?",
      [$usuario, $nombre, $puesto ?: null, $id]);
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    usuarios_asignar_roles($id, $roles_ids);
    flash("<div class='alert alert-success'>Usuario actualizado.</div>");
    redirect('usuarios.listar');
    break;

  // ── Eliminar usuario ─────────────────────────────────────────────────────────
  case 'eliminar':
    $id = (int)($_GET['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar');
    }
    if ($id <= 0) redirect('usuarios.listar');
    $me = current_user();
    if ((int)($me['id'] ?? 0) === $id) {
      flash("<div class='alert alert-warning'>No puedes eliminar tu propio usuario.</div>");
      redirect('usuarios.listar');
    }
    $u = qone("SELECT id, usuario FROM usuarios WHERE id=? AND estatus='ACTIVO'", [$id]);
    if (!$u) { flash("<div class='alert alert-warning'>Usuario no encontrado.</div>"); redirect('usuarios.listar'); }
    try {
      if (auth_roles_enabled()) q("DELETE FROM usuarios_roles WHERE usuario_id=?", [$id]);
      q("UPDATE usuarios SET estatus='INACTIVO' WHERE id=?", [$id]);
      flash("<div class='alert alert-success'>Usuario eliminado.</div>");
    } catch (Exception $e) {
      flash("<div class='alert alert-danger'>No se pudo eliminar el usuario.</div>");
    }
    redirect('usuarios.listar');
    break;

  // ── Cambiar contraseña ───────────────────────────────────────────────────────
  case 'cambiar_password':
    $id = (int)($_GET['id'] ?? 0);
    $u  = qone("SELECT id, usuario, nombre FROM usuarios WHERE id=? AND estatus='ACTIVO'", [$id]);
    if (!$u) { flash("<div class='alert alert-warning'>Usuario no encontrado.</div>"); redirect('usuarios.listar'); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      try { csrf_verify(); } catch (RuntimeException $e) {
        flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar');
      }
      $pass = (string)($_POST['pass'] ?? '');
      if ($pass === '') {
        flash("<div class='alert alert-warning'>Ingresa una contraseña.</div>");
        redirect("usuarios.cambiar_password&id={$id}");
      }
      q("UPDATE usuarios SET pass=? WHERE id=?", [password_hash($pass, PASSWORD_DEFAULT), $id]);
      flash("<div class='alert alert-success'>Contraseña actualizada.</div>");
      redirect('usuarios.listar');
    }
    ob_start(); ?>
    <div class="card shadow-sm" style="max-width:480px">
      <div class="card-body">
        <h1 class="h5 mb-3">Cambiar contraseña</h1>
        <p class="text-muted">Usuario: <?= e($u['usuario']) ?> — <?= e($u['nombre']) ?></p>
        <form method="post" action="?r=usuarios.cambiar_password&id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <?= form_input('pass', 'Nueva contraseña', '', ['type'=>'password','required'=>true]) ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Actualizar</button>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">Volver</button>
          </div>
        </form>
      </div>
    </div>
    <?php
    render('Cambiar contraseña', ob_get_clean());
    break;

  // ── Roles ────────────────────────────────────────────────────────────────────
  case 'roles':
    if (!auth_roles_enabled()) {
      flash("<div class='alert alert-warning'>No existen las tablas de roles. Ejecuta la migración correspondiente.</div>");
      redirect('usuarios.listar');
    }
    $roles = usuarios_roles_disponibles();
    ob_start(); ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <h1 class="h4 m-0">Roles</h1>
      <a class="btn btn-outline-secondary btn-sm" href="?r=usuarios.listar">← Usuarios</a>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header py-2"><span class="fw-semibold">Nuevo rol</span></div>
      <div class="card-body">
        <form method="post" action="?r=usuarios.guardar_rol" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <div class="col-md-3"><?= form_input('slug', 'Slug', '', ['required'=>true, 'help'=>'Ej. auxiliar']) ?></div>
          <div class="col-md-4"><?= form_input('nombre', 'Nombre visible', '', ['required'=>true]) ?></div>
          <div class="col-md-3">
            <label class="form-label">Super rol (acceso total)</label>
            <select name="es_super" class="form-select">
              <option value="0">No</option>
              <option value="1">Sí</option>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-primary w-100">Crear</button></div>
        </form>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Nombre</th>
            <th>Super</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $rol): ?>
          <tr>
            <td><?= (int)$rol['id'] ?></td>
            <td><code><?= e($rol['slug']) ?></code></td>
            <td><?= e($rol['nombre']) ?></td>
            <td><?= !empty($rol['es_super']) ? '<span class="badge text-bg-warning">Sí</span>' : '<span class="text-muted">No</span>' ?></td>
            <td class="d-flex gap-1">
              <a class="btn btn-outline-primary btn-sm" href="?r=usuarios.editar_rol&id=<?= (int)$rol['id'] ?>">Editar</a>
              <a class="btn btn-outline-secondary btn-sm" href="?r=usuarios.permisos&rol_id=<?= (int)$rol['id'] ?>">Permisos</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($roles)): ?>
          <tr><td colspan="5" class="text-center text-muted">Sin roles</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    render('Roles', ob_get_clean());
    break;

  // ── Editar rol ───────────────────────────────────────────────────────────────
  case 'editar_rol':
    if (!auth_roles_enabled()) {
      flash("<div class='alert alert-warning'>No existen las tablas de roles.</div>");
      redirect('usuarios.listar');
    }
    $id  = (int)($_GET['id'] ?? 0);
    $rol = qone("SELECT * FROM roles WHERE id=?", [$id]);
    if (!$rol) { flash("<div class='alert alert-warning'>Rol no encontrado.</div>"); redirect('usuarios.roles'); }
    ob_start(); ?>
    <div class="card shadow-sm" style="max-width:640px">
      <div class="card-body">
        <h1 class="h5 mb-3">Editar rol</h1>
        <form method="post" action="?r=usuarios.actualizar_rol&id=<?= (int)$id ?>" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <div class="col-md-4"><?= form_input('slug', 'Slug', $rol['slug'], ['required'=>true]) ?></div>
          <div class="col-md-5"><?= form_input('nombre', 'Nombre visible', $rol['nombre'], ['required'=>true]) ?></div>
          <div class="col-md-3">
            <label class="form-label">Super rol</label>
            <select name="es_super" class="form-select">
              <option value="0" <?= empty($rol['es_super']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($rol['es_super']) ? 'selected' : '' ?>>Sí</option>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Guardar cambios</button>
            <a class="btn btn-outline-secondary" href="?r=usuarios.roles">Volver</a>
            <a class="btn btn-outline-secondary" href="?r=usuarios.permisos&rol_id=<?= (int)$id ?>">Editar permisos</a>
          </div>
        </form>
      </div>
    </div>
    <?php
    render('Editar rol', ob_get_clean());
    break;

  // ── Guardar / Actualizar rol ─────────────────────────────────────────────────
  case 'guardar_rol':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.roles');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.roles');
    }
    $slug     = trim($_POST['slug'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $es_super = (int)($_POST['es_super'] ?? 0);
    if ($slug === '' || $nombre === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.roles');
    }
    q("INSERT INTO roles (slug, nombre, es_super) VALUES (?,?,?)", [$slug, $nombre, $es_super]);
    flash("<div class='alert alert-success'>Rol creado.</div>");
    redirect('usuarios.roles');
    break;

  case 'actualizar_rol':
    if (!auth_roles_enabled()) { redirect('usuarios.listar'); }
    $id = (int)($_GET['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.roles');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.roles');
    }
    $slug     = trim($_POST['slug'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $es_super = (int)($_POST['es_super'] ?? 0);
    if ($slug === '' || $nombre === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.editar_rol&id='.$id);
    }
    q("UPDATE roles SET slug=?, nombre=?, es_super=? WHERE id=?", [$slug, $nombre, $es_super, $id]);
    flash("<div class='alert alert-success'>Rol actualizado.</div>");
    redirect('usuarios.roles');
    break;

  // ── Permisos ─────────────────────────────────────────────────────────────────
  case 'permisos':
    if (!auth_permissions_enabled()) {
      flash("<div class='alert alert-warning'>No existen las tablas de permisos. Ejecuta la migración.</div>");
      redirect('usuarios.listar');
    }
    $rol_id  = (int)($_GET['rol_id'] ?? 0);
    $roles   = usuarios_roles_disponibles();
    $rolActual = $rol_id > 0 ? qone("SELECT * FROM roles WHERE id=?", [$rol_id]) : null;

    // IDs asignados al rol seleccionado
    $asignados = [];
    if ($rol_id > 0) {
      $asignados = array_map(
        fn($r) => (int)$r['permiso_id'],
        qall("SELECT permiso_id FROM roles_permisos WHERE rol_id=?", [$rol_id])
      );
    }

    // Agrupar permisos de BD por módulo, enriquecidos con la descripción canónica
    $permisosMap  = usuarios_permisos_map();   // clave → id
    $catalogoGrps = auth_permisos_por_modulo(); // grupo → [clave => desc]

    ob_start(); ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <h1 class="h4 m-0">Permisos</h1>
      <a class="btn btn-outline-secondary btn-sm" href="?r=usuarios.roles">← Roles</a>
    </div>

    <!-- Selector de rol -->
    <?php if (!empty($roles)): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body py-3">
        <form method="get" action="" class="d-flex gap-2 align-items-end flex-wrap">
          <input type="hidden" name="r" value="usuarios.permisos">
          <div style="min-width:220px">
            <label class="form-label mb-1 small">Editar permisos del rol</label>
            <select name="rol_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="0">— Selecciona un rol —</option>
              <?php foreach ($roles as $rol): ?>
                <option value="<?= (int)$rol['id'] ?>" <?= $rol_id === (int)$rol['id'] ? 'selected' : '' ?>>
                  <?= e($rol['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <noscript><button class="btn btn-sm btn-outline-primary">Ver</button></noscript>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($rol_id > 0 && $rolActual): ?>
    <!-- Asignación de permisos al rol seleccionado -->
    <div class="card shadow-sm mb-4">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Permisos — <?= e($rolActual['nombre']) ?></span>
        <?php if (!empty($rolActual['es_super'])): ?>
          <span class="badge text-bg-warning">Super rol — acceso total sin necesidad de permisos</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="post" action="?r=usuarios.asignar_permisos">
          <?= csrf_field() ?>
          <input type="hidden" name="rol_id" value="<?= (int)$rol_id ?>">

          <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="document.querySelectorAll('.perm-chk').forEach(c=>c.checked=true)">
              Marcar todos
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="document.querySelectorAll('.perm-chk').forEach(c=>c.checked=false)">
              Desmarcar todos
            </button>
          </div>

          <div class="row g-3">
            <?php foreach ($catalogoGrps as $grupoLabel => $claves): ?>
            <div class="col-md-6">
              <div class="card border h-100">
                <div class="card-header py-2 small fw-semibold bg-light"><?= e($grupoLabel) ?></div>
                <div class="card-body py-2">
                  <?php foreach ($claves as $clave => $desc):
                    $pid = $permisosMap[$clave] ?? null;
                    if ($pid === null) continue; // no sembrado aún ?>
                    <div class="form-check">
                      <input class="form-check-input perm-chk" type="checkbox"
                             name="permisos[]" value="<?= (int)$pid ?>"
                             id="p<?= (int)$pid ?>"
                             <?= in_array((int)$pid, $asignados, true) ? 'checked' : '' ?>>
                      <label class="form-check-label small" for="p<?= (int)$pid ?>">
                        <?= e($desc) ?>
                        <code class="text-muted ms-1" style="font-size:.7rem"><?= e($clave) ?></code>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-3">
            <button class="btn btn-primary">Guardar permisos</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabla de todos los permisos registrados en BD -->
    <div class="card shadow-sm">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Catálogo de permisos</span>
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-bs-toggle="collapse" data-bs-target="#catalogoCollapse">
          Mostrar / ocultar
        </button>
      </div>
      <div class="collapse" id="catalogoCollapse">
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Clave</th><th>Descripción</th></tr>
            </thead>
            <tbody>
              <?php foreach ($catalogoGrps as $grupoLabel => $claves): ?>
                <tr class="table-secondary"><td colspan="2" class="fw-semibold small"><?= e($grupoLabel) ?></td></tr>
                <?php foreach ($claves as $clave => $desc):
                  $pid = $permisosMap[$clave] ?? null; ?>
                  <tr>
                    <td><code><?= e($clave) ?></code></td>
                    <td><?= e($desc) ?><?= $pid === null ? ' <span class="badge text-bg-danger ms-1">no sembrado</span>' : '' ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
    render('Permisos', ob_get_clean());
    break;

  // ── Guardar permiso manual ────────────────────────────────────────────────────
  case 'guardar_permiso':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.permisos');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.permisos');
    }
    $clave       = trim($_POST['clave'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($clave === '' || $descripcion === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.permisos');
    }
    q("INSERT INTO permisos (clave, descripcion) VALUES (?,?) ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion)",
      [$clave, $descripcion]);
    flash("<div class='alert alert-success'>Permiso registrado.</div>");
    redirect('usuarios.permisos');
    break;

  // ── Asignar roles al usuario ──────────────────────────────────────────────────
  case 'asignar_roles':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar');
    }
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    $roles_ids  = array_map('intval', $_POST['roles'] ?? []);
    usuarios_asignar_roles($usuario_id, $roles_ids);
    flash("<div class='alert alert-success'>Roles actualizados.</div>");
    redirect('usuarios.editar&id='.$usuario_id);
    break;

  // ── Asignar permisos al rol ────────────────────────────────────────────────────
  case 'asignar_permisos':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.permisos');
    try { csrf_verify(); } catch (RuntimeException $e) {
      flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.permisos');
    }
    $rol_id   = (int)($_POST['rol_id'] ?? 0);
    $perm_ids = array_map('intval', $_POST['permisos'] ?? []);
    usuarios_asignar_permisos($rol_id, $perm_ids);
    flash("<div class='alert alert-success'>Permisos actualizados.</div>");
    redirect('usuarios.permisos&rol_id='.$rol_id);
    break;
}
