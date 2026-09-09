<?php
// app/modules/usuarios.php — Administración de usuarios, roles y permisos
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/usuarios_directorio.php';

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
  if ($rol_id <= 0 || !qone("SELECT id FROM roles WHERE id=?", [$rol_id])) {
    throw new RuntimeException('Rol no encontrado.');
  }

  $perm_ids = array_values(array_unique(array_filter(array_map('intval', $perm_ids), fn($id) => $id > 0)));
  $validos = [];
  if (!empty($perm_ids)) {
    $placeholders = implode(',', array_fill(0, count($perm_ids), '?'));
    foreach (qall("SELECT id FROM permisos WHERE id IN ({$placeholders})", $perm_ids) as $row) {
      $validos[] = (int)$row['id'];
    }
  }

  $pdo = db();
  try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM roles_permisos WHERE rol_id=?")->execute([$rol_id]);
    if (!empty($validos)) {
      $stmt = $pdo->prepare("INSERT IGNORE INTO roles_permisos (rol_id, permiso_id) VALUES (?, ?)");
      foreach ($validos as $perm_id) {
        $stmt->execute([$rol_id, $perm_id]);
      }
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
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

function usuarios_jefe_select(?int $selected = null, int $excludeId = 0): string {
  $html = "<div class='mb-3'><label class='form-label'>Jefe directo</label><select name='jefe_directo_id' class='form-select'>";
  $html .= "<option value=''>-- Sin jefe directo --</option>";
  foreach (usuarios_directorio_activos(['lider','gerente']) as $row) {
    if ((int)$row['id'] === $excludeId) continue;
    $sel = (int)$selected === (int)$row['id'] ? ' selected' : '';
    $puesto = auth_puestos()[$row['puesto']] ?? $row['puesto'];
    $html .= "<option value='".(int)$row['id']."'{$sel}>".e($row['nombre'].' - '.$puesto)."</option>";
  }
  return $html."</select><div class='form-text'>Vendedor requiere lider; lider requiere gerente.</div></div>";
}

function usuarios_acceso_control(bool $checked): string {
  return "<div class='form-check form-switch mb-3'><input class='form-check-input' type='checkbox' name='acceso_sistema' value='1' id='acceso_sistema'".($checked ? ' checked' : '')."><label class='form-check-label' for='acceso_sistema'>Puede iniciar sesion</label></div>";
}

function usuarios_validar_captura(int $id, string $nombre, string $puesto, ?int $jefeId, bool $acceso, string $usuario, string $pass, array $rolesIds, ?array $actual = null, bool $activo = true): array {
  $errores = [];
  if ($nombre === '') $errores[] = 'El nombre completo es obligatorio.';
  if (!isset(auth_puestos()[$puesto])) $errores[] = 'Selecciona un puesto valido.';
  if ($activo || $jefeId) {
    $errores = array_merge($errores, usuarios_directorio_validar_asignacion($id, $puesto, $jefeId, $id > 0));
  }
  if ($id > 0) $errores = array_merge($errores, usuarios_directorio_validar_dependientes($id,$puesto,$activo));
  if ($acceso && !$activo) $errores[] = 'Un usuario inactivo no puede tener acceso al sistema.';
  $hashDisponible = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : ($actual['pass'] ?? null);
  $errores = array_merge($errores, usuarios_directorio_validar_acceso($acceso, $usuario, $hashDisponible, $rolesIds));
  if ($usuario !== '') {
    $duplicado = qone("SELECT id FROM usuarios WHERE usuario=? AND id<>? LIMIT 1", [$usuario,$id]);
    if ($duplicado) $errores[] = 'Ese nombre de usuario ya esta registrado.';
  }
  return array_values(array_unique($errores));
}

// ── Switch principal ────────────────────────────────────────────────────────────

switch ($action) {

  // ── Listar usuarios ──────────────────────────────────────────────────────────
  case 'listar':
  default:
    if (auth_roles_enabled()) {
      $rows = qall("
        SELECT u.id, u.usuario, u.nombre, COALESCE(u.puesto,'') AS puesto,
               COALESCE(u.acceso_sistema,1) AS acceso_sistema,u.estatus,j.nombre AS jefe_nombre,
               GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ') AS roles
        FROM usuarios u
        LEFT JOIN usuarios j ON j.id=u.jefe_directo_id
        LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
        LEFT JOIN roles r ON r.id = ur.rol_id
        GROUP BY u.id, u.usuario, u.nombre, u.puesto,u.acceso_sistema,u.estatus,j.nombre
        ORDER BY (u.estatus='ACTIVO') DESC,u.nombre ASC
      ");
    } else {
      $rows = qall("
        SELECT u.id, u.usuario, u.nombre, COALESCE(u.puesto,'') AS puesto,
               COALESCE(u.acceso_sistema,1) AS acceso_sistema,u.estatus,j.nombre AS jefe_nombre,'' AS roles
        FROM usuarios u
        LEFT JOIN usuarios j ON j.id=u.jefe_directo_id
        ORDER BY (u.estatus='ACTIVO') DESC,u.nombre ASC
      ");
    }
    $puestoLabels = auth_puestos();

    ob_start(); ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <h1 class="h4 m-0">Usuarios</h1>
      <div class="d-flex gap-2">
        <a href="?r=usuarios.nuevo" class="btn btn-success btn-sm">Nuevo usuario</a>
        <a href="?r=usuarios.directorio" class="btn btn-outline-primary btn-sm">Conciliacion de personal</a>
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
            <th>Jefe directo</th>
            <th>Acceso</th>
            <th>Roles</th>
            <th style="width:260px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['usuario'] ?: 'Sin acceso') ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td>
              <?php if ($r['puesto'] !== ''): ?>
                <span class="badge text-bg-secondary"><?= e($puestoLabels[$r['puesto']] ?? $r['puesto']) ?></span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td><?= e($r['jefe_nombre'] ?: '--') ?></td>
            <td>
              <?php if (($r['estatus'] ?? '') !== 'ACTIVO'): ?>
                <span class="badge text-bg-secondary">Inactivo</span>
              <?php elseif (!empty($r['acceso_sistema'])): ?>
                <span class="badge text-bg-success">Habilitado</span>
              <?php else: ?>
                <span class="badge text-bg-light">Directorio</span>
              <?php endif; ?>
            </td>
            <td><?= e($r['roles'] ?? '—') ?></td>
            <td class="d-flex flex-wrap gap-1">
              <a class="btn btn-outline-primary btn-sm" href="?r=usuarios.editar&id=<?= (int)$r['id'] ?>">Editar</a>
              <?php if (!empty($r['acceso_sistema'])): ?><a class="btn btn-outline-warning btn-sm" href="?r=usuarios.cambiar_password&id=<?= (int)$r['id'] ?>">Contrasena</a><?php endif; ?>
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
          <tr><td colspan="8" class="text-center text-muted">Sin usuarios</td></tr>
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
          <?= form_input('nombre', 'Nombre completo', '', ['required'=>true]) ?>
          <?= usuarios_puesto_select() ?>
          <?= usuarios_jefe_select() ?>
          <div class="mb-3"><label class="form-label">Estatus laboral</label><select name="estatus" class="form-select"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo / historico</option></select></div>
          <?= usuarios_acceso_control(false) ?>
          <?= form_input('usuario', 'Usuario para iniciar sesion', '') ?>
          <?= form_input('pass', 'Contrasena inicial', '', ['type'=>'password']) ?>
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
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    $jefeId = (int)($_POST['jefe_directo_id'] ?? 0) ?: null;
    $acceso = !empty($_POST['acceso_sistema']);
    $estatus = strtoupper(trim((string)($_POST['estatus'] ?? 'ACTIVO'))) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
    $errores = usuarios_validar_captura(0,$nombre,$puesto,$jefeId,$acceso,$usuario,$pass,$roles_ids,null,$estatus==='ACTIVO');
    if ($errores) {
      flash("<div class='alert alert-warning'>".e(implode(' ', $errores))."</div>");
      redirect('usuarios.nuevo');
    }
    $pdo = db();
    try {
      $pdo->beginTransaction();
      q("INSERT INTO usuarios (usuario,nombre,pass,perfil,puesto,jefe_directo_id,acceso_sistema,estatus) VALUES (?,?,?,?,?,?,?,?)", [
        $acceso ? $usuario : null,$nombre,$acceso ? password_hash($pass,PASSWORD_DEFAULT) : null,
        $acceso ? 'usuario' : null,$puesto,$jefeId,$acceso ? 1 : 0,$estatus,
      ]);
      $id = (int)$pdo->lastInsertId();
      usuarios_asignar_roles($id, $acceso ? $roles_ids : []);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
    flash("<div class='alert alert-success'>Usuario creado.</div>");
    redirect('usuarios.listar');
    break;

  // ── Editar usuario ───────────────────────────────────────────────────────────
  case 'editar':
    $id = (int)($_GET['id'] ?? 0);
    $u  = qone("SELECT * FROM usuarios WHERE id=?", [$id]);
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
          <?= form_input('nombre', 'Nombre completo', $u['nombre'], ['required'=>true]) ?>
          <?= usuarios_puesto_select($u['puesto'] ?? '') ?>
          <?= usuarios_jefe_select((int)($u['jefe_directo_id'] ?? 0) ?: null,$id) ?>
          <div class="mb-3"><label class="form-label">Estatus laboral</label><select name="estatus" class="form-select"><option value="ACTIVO" <?= ($u['estatus'] ?? 'ACTIVO')==='ACTIVO'?'selected':'' ?>>Activo</option><option value="INACTIVO" <?= ($u['estatus'] ?? '')==='INACTIVO'?'selected':'' ?>>Inactivo / historico</option></select></div>
          <?= usuarios_acceso_control(!empty($u['acceso_sistema'])) ?>
          <?= form_input('usuario', 'Usuario para iniciar sesion', $u['usuario'] ?? '') ?>
          <?= form_input('pass', 'Nueva contrasena (solo para cambiarla)', '', ['type'=>'password']) ?>
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
    $pass = (string)($_POST['pass'] ?? '');
    $actual = qone("SELECT * FROM usuarios WHERE id=?", [$id]);
    if (!$actual) { flash("<div class='alert alert-warning'>Usuario no encontrado.</div>"); redirect('usuarios.listar'); }
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    $jefeId = (int)($_POST['jefe_directo_id'] ?? 0) ?: null;
    $acceso = !empty($_POST['acceso_sistema']);
    $estatus = strtoupper(trim((string)($_POST['estatus'] ?? 'ACTIVO'))) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
    $errores = usuarios_validar_captura($id,$nombre,$puesto,$jefeId,$acceso,$usuario,$pass,$roles_ids,$actual,$estatus==='ACTIVO');
    if ($errores) {
      flash("<div class='alert alert-warning'>".e(implode(' ', $errores))."</div>");
      redirect("usuarios.editar&id={$id}");
    }
    $pdo = db();
    try {
      $pdo->beginTransaction();
      $hash = $pass !== '' ? password_hash($pass,PASSWORD_DEFAULT) : ($actual['pass'] ?? null);
      q("UPDATE usuarios SET usuario=?,nombre=?,pass=?,puesto=?,jefe_directo_id=?,acceso_sistema=?,estatus=? WHERE id=?", [
        $acceso ? $usuario : null,$nombre,$hash,$puesto,$jefeId,$acceso ? 1 : 0,$estatus,$id,
      ]);
      usuarios_asignar_roles($id, $acceso ? $roles_ids : []);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
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
    $u = qone("SELECT id,usuario FROM usuarios WHERE id=? AND estatus='ACTIVO'", [$id]);
    if (!$u) { flash("<div class='alert alert-warning'>Usuario no encontrado.</div>"); redirect('usuarios.listar'); }
    $erroresDependientes = usuarios_directorio_validar_dependientes($id,(string)(qone("SELECT puesto FROM usuarios WHERE id=?",[$id])['puesto'] ?? ''),false);
    if ($erroresDependientes) {
      flash("<div class='alert alert-warning'>".e(implode(' ',$erroresDependientes))."</div>");
      redirect('usuarios.listar');
    }
    try {
      if (auth_roles_enabled()) q("DELETE FROM usuarios_roles WHERE usuario_id=?", [$id]);
      q("UPDATE usuarios SET estatus='INACTIVO',acceso_sistema=0 WHERE id=?", [$id]);
      flash("<div class='alert alert-success'>Usuario eliminado.</div>");
    } catch (Exception $e) {
      flash("<div class='alert alert-danger'>No se pudo eliminar el usuario.</div>");
    }
    redirect('usuarios.listar');
    break;

  // ── Cambiar contraseña ───────────────────────────────────────────────────────
  case 'cambiar_password':
    $id = (int)($_GET['id'] ?? 0);
    $u  = qone("SELECT id,usuario,nombre,acceso_sistema FROM usuarios WHERE id=? AND estatus='ACTIVO' AND acceso_sistema=1 AND usuario IS NOT NULL", [$id]);
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

    // Agrupar permisos de BD por módulo, enriquecidos con la descripción canónica.
    $permisosMap  = usuarios_permisos_map();   // clave → id
    $catalogoGrps = auth_permisos_por_modulo(); // grupo → [clave => desc]

    // Claves asignadas al rol seleccionado; son más estables que los IDs entre ambientes.
    $asignadosClaves = [];
    if ($rol_id > 0) {
      foreach (qall("
        SELECT p.clave
        FROM roles_permisos rp
        JOIN permisos p ON p.id = rp.permiso_id
        WHERE rp.rol_id = ?
      ", [$rol_id]) as $row) {
        $clave = (string)($row['clave'] ?? '');
        if ($clave !== '') $asignadosClaves[$clave] = true;
      }
    }

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
                             <?= !empty($asignadosClaves[$clave]) ? 'checked' : '' ?>>
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
    try {
      usuarios_asignar_permisos($rol_id, $perm_ids);
      flash("<div class='alert alert-success'>Permisos actualizados.</div>");
    } catch (Throwable $e) {
      flash("<div class='alert alert-danger'>No se pudieron actualizar los permisos: ".e($e->getMessage())."</div>");
    }
    redirect('usuarios.permisos&rol_id='.$rol_id);
    break;

  // ── Conciliacion del directorio historico ────────────────────────────────────
  case 'directorio':
    $migracionLista = db_table_exists('usuarios_personal_legacy');
    $directorioActivado = db_column_exists('futuro_contratos','id_usuario_vendedor');
    $personal = [];
    if ($migracionLista && db_table_exists('futuro_personal')) {
      $personal = qall("
        SELECT fp.id_personal,fp.id AS usuario_legacy_id,
               TRIM(CONCAT(COALESCE(fp.nombre,''),' ',COALESCE(fp.apellido_p,''),' ',COALESCE(fp.apellido_m,''))) AS nombre_personal,
               COALESCE(fp.estatus,'') AS estatus_personal,
               upl.id_usuario,u.nombre AS nombre_usuario,u.puesto,u.estatus AS estatus_usuario,
               upl.tipo_vinculo,upl.estado,upl.notas,
               (SELECT COUNT(*) FROM futuro_personal fpd WHERE fpd.id=fp.id AND fp.id IS NOT NULL) AS enlaces_legacy_repetidos,
               (SELECT COUNT(*) FROM usuarios ud
                WHERE ud.id<>u.id
                  AND UPPER(TRIM(CONVERT(ud.nombre USING utf8mb4))) COLLATE utf8mb4_general_ci
                    = UPPER(TRIM(CONVERT(u.nombre USING utf8mb4))) COLLATE utf8mb4_general_ci) AS posibles_duplicados,
               (SELECT COUNT(*) FROM futuro_contrato_vendedor x WHERE x.id_personal=fp.id_personal) AS contratos,
               (SELECT COUNT(*) FROM futuro_abono_cobrador x WHERE x.id_personal=fp.id_personal) AS abonos,
               (SELECT COUNT(*) FROM futuro_gestion x WHERE x.id_personal=fp.id_personal) AS gestiones
        FROM futuro_personal fp
        LEFT JOIN usuarios_personal_legacy upl ON upl.id_personal=fp.id_personal
        LEFT JOIN usuarios u ON u.id=upl.id_usuario
        ORDER BY (upl.id_usuario IS NULL) DESC,nombre_personal,fp.id_personal
      ");
    }
    $pendientesMapeo = count(array_filter($personal, fn($r) => empty($r['id_usuario'])));
    $posiblesDuplicados = count(array_filter($personal, fn($r) => (int)($r['posibles_duplicados'] ?? 0) > 0));
    $pendientesJerarquia = [];
    foreach (usuarios_directorio_activos(['vendedor']) as $vendedor) {
      $j = usuarios_directorio_jerarquia_comercial((int)$vendedor['id']);
      if ($j['errores']) $pendientesJerarquia[] = ['usuario'=>$vendedor,'errores'=>$j['errores']];
    }
    $usuariosOpts = usuarios_directorio_opciones();

    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div><h1 class="h4 mb-1">Conciliacion de personal</h1><div class="text-muted small">Esta pantalla es la unica que consulta el archivo historico de personal.</div></div>
      <a href="?r=usuarios.listar" class="btn btn-outline-secondary btn-sm">Volver a usuarios</a>
    </div>
    <?php if (!$migracionLista): ?>
      <div class="alert alert-warning">Ejecuta primero la migracion <strong>Usuarios - etapa 1</strong> desde el Centro de migraciones.</div>
    <?php else: ?>
      <div class="row g-2 mb-3">
        <div class="col-md-3"><div class="border p-3"><div class="small text-muted">Personal sin usuario</div><div class="h4 mb-0"><?= $pendientesMapeo ?></div></div></div>
        <div class="col-md-3"><div class="border p-3"><div class="small text-muted">Vendedores sin cadena valida</div><div class="h4 mb-0"><?= count($pendientesJerarquia) ?></div></div></div>
        <div class="col-md-3"><div class="border p-3"><div class="small text-muted">Posibles duplicados</div><div class="h4 mb-0"><?= $posiblesDuplicados ?></div></div></div>
        <div class="col-md-3"><div class="border p-3"><div class="small text-muted">Estado de activacion</div><div class="fw-semibold <?= ($pendientesMapeo || $pendientesJerarquia) ? 'text-danger' : 'text-success' ?>"><?= ($pendientesMapeo || $pendientesJerarquia) ? 'Bloqueada' : 'Lista' ?></div></div></div>
      </div>
      <?php if ($directorioActivado): ?><div class="alert alert-info py-2">La etapa 2 ya esta activa. Los enlaces historicos son de solo lectura; las correcciones se realizan sobre Usuarios.</div><?php endif; ?>

      <?php if ($pendientesJerarquia): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1">Jerarquias pendientes</div>
          <?php foreach ($pendientesJerarquia as $item): ?>
            <div><a href="?r=usuarios.editar&id=<?= (int)$item['usuario']['id'] ?>"><?= e($item['usuario']['nombre']) ?></a>: <?= e(implode(' ', $item['errores'])) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead><tr><th>Personal historico</th><th>Usuario enlazado</th><th>Referencias</th><th>Origen</th><th style="min-width:320px">Corregir enlace</th></tr></thead>
          <tbody>
          <?php foreach ($personal as $row): ?>
            <tr class="<?= empty($row['id_usuario']) ? 'table-warning' : '' ?>">
              <td><div><?= e($row['nombre_personal']) ?></div><div class="small text-muted">#<?= (int)$row['id_personal'] ?> · <?= e($row['estatus_personal']) ?></div></td>
              <td><?php if ($row['id_usuario']): ?><a href="?r=usuarios.editar&id=<?= (int)$row['id_usuario'] ?>"><?= e($row['nombre_usuario']) ?></a><div class="small text-muted"><?= e(auth_puestos()[$row['puesto']] ?? $row['puesto']) ?></div><?php if ((int)($row['posibles_duplicados'] ?? 0)>0): ?><span class="badge text-bg-warning">Posible nombre duplicado</span><?php endif; ?><?php else: ?><span class="text-danger">Pendiente</span><?php endif; ?></td>
              <td class="small">Contratos <?= (int)$row['contratos'] ?> · Abonos <?= (int)$row['abonos'] ?> · Gestiones <?= (int)$row['gestiones'] ?></td>
              <td><span class="badge text-bg-light"><?= e($row['tipo_vinculo'] ?: 'sin enlace') ?></span><?php if ((int)$row['enlaces_legacy_repetidos']>1): ?><span class="badge text-bg-warning ms-1">Enlace legado duplicado</span><?php endif; ?><?php if ($row['notas']): ?><div class="small text-muted"><?= e($row['notas']) ?></div><?php endif; ?></td>
              <td>
                <?php if (!$directorioActivado): ?><form method="post" action="?r=usuarios.directorio_guardar_mapeo" class="d-flex gap-1">
                  <?= csrf_field() ?><input type="hidden" name="id_personal" value="<?= (int)$row['id_personal'] ?>">
                  <select name="id_usuario" class="form-select form-select-sm" required><option value="">Selecciona usuario</option><?php foreach ($usuariosOpts as $uid=>$nombre): ?><option value="<?= (int)$uid ?>" <?= (int)$row['id_usuario']===(int)$uid?'selected':'' ?>><?= e($nombre) ?></option><?php endforeach; ?></select>
                  <button class="btn btn-primary btn-sm">Vincular</button>
                </form><?php else: ?><span class="text-muted small">Enlace congelado</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif;
    render('Conciliacion de personal', ob_get_clean());
    break;

  case 'directorio_guardar_mapeo':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.directorio');
    csrf_verify();
    $idPersonal = (int)($_POST['id_personal'] ?? 0);
    $idUsuario = (int)($_POST['id_usuario'] ?? 0);
    if (db_column_exists('futuro_contratos','id_usuario_vendedor')) {
      flash("<div class='alert alert-warning'>La conciliacion ya fue activada y los enlaces historicos estan congelados.</div>");
      redirect('usuarios.directorio');
    }
    if (!db_table_exists('usuarios_personal_legacy') || $idPersonal <= 0 || $idUsuario <= 0) {
      flash("<div class='alert alert-warning'>Selecciona un enlace valido.</div>");
      redirect('usuarios.directorio');
    }
    $ocupado = qone("SELECT id_personal FROM usuarios_personal_legacy WHERE id_usuario=? AND id_personal<>?", [$idUsuario,$idPersonal]);
    if ($ocupado) {
      flash("<div class='alert alert-danger'>Ese usuario ya esta enlazado con otro registro historico. Revisa el posible duplicado antes de continuar.</div>");
      redirect('usuarios.directorio');
    }
    $anterior = qone("SELECT id_usuario FROM usuarios_personal_legacy WHERE id_personal=?", [$idPersonal]);
    q("INSERT INTO usuarios_personal_legacy (id_personal,id_usuario,tipo_vinculo,estado,notas) VALUES (?,?,'manual','conciliado','Enlace confirmado desde Usuarios') ON DUPLICATE KEY UPDATE id_usuario=VALUES(id_usuario),tipo_vinculo='manual',estado='conciliado',notas=VALUES(notas),updated_at=CURRENT_TIMESTAMP", [$idPersonal,$idUsuario]);
    $idAnterior = (int)($anterior['id_usuario'] ?? 0);
    if ($idAnterior > 0 && $idAnterior !== $idUsuario) {
      q("UPDATE usuarios u SET u.estatus='INACTIVO',u.acceso_sistema=0 WHERE u.id=? AND u.acceso_sistema=0 AND NOT EXISTS (SELECT 1 FROM usuarios_personal_legacy x WHERE x.id_usuario=u.id)", [$idAnterior]);
    }
    flash("<div class='alert alert-success'>Enlace actualizado.</div>");
    redirect('usuarios.directorio');
    break;
}
