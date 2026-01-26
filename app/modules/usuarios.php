<?php
// app/modules/usuarios.php — Administración de usuarios, roles y permisos
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();
require_permission('usuarios.admin');

$action = $action ?? 'listar';

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

switch ($action) {
  case 'listar':
  default:
    if (auth_roles_enabled()) {
      $rows = qall("
        SELECT u.id, u.usuario, u.nombre, u.perfil,
               GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ') AS roles
        FROM usuarios u
        LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
        LEFT JOIN roles r ON r.id = ur.rol_id
        GROUP BY u.id, u.usuario, u.nombre, u.perfil
        ORDER BY u.nombre ASC
      ");
    } else {
      $rows = qall("
        SELECT u.id, u.usuario, u.nombre, u.perfil, '' AS roles
        FROM usuarios u
        ORDER BY u.nombre ASC
      ");
    }

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
        Las tablas de roles no están disponibles. Aplica la migración para activar roles y permisos.
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Nombre</th>
            <th>Perfil</th>
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
            <td><?= e($r['perfil']) ?></td>
            <td><?= e($r['roles'] ?? '—') ?></td>
            <td class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-primary btn-sm" href="?r=usuarios.editar&id=<?= (int)$r['id'] ?>">Editar</a>
              <a class="btn btn-outline-warning btn-sm" href="?r=usuarios.cambiar_password&id=<?= (int)$r['id'] ?>">Contraseña</a>
              <form method="post" action="?r=usuarios.eliminar&id=<?= (int)$r['id'] ?>" class="m-0 p-0 d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('¿Eliminar usuario #<?= (int)$r['id'] ?> (<?= e($r['usuario']) ?>)? Esta acción no se puede deshacer.');">
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

  case 'nuevo':
    $roles = usuarios_roles_disponibles();
    ob_start(); ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h5 mb-3">Nuevo usuario</h1>
        <form method="post" action="?r=usuarios.guardar">
          <?= csrf_field() ?>
          <?= form_input('usuario', 'Usuario', '', ['required'=>true]) ?>
          <?= form_input('nombre', 'Nombre', '', ['required'=>true]) ?>
          <?= form_input('pass', 'Contraseña', '', ['type'=>'password','required'=>true]) ?>
          <?= form_input('perfil', 'Perfil (legacy)', '', ['help'=>'Se mantiene por compatibilidad con instalaciones anteriores.']) ?>
          <?php if (!empty($roles)): ?>
            <div class="mb-2">
              <label class="form-label">Roles</label>
              <?php foreach ($roles as $rol): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int)$rol['id'] ?>">
                  <label class="form-check-label"><?= e($rol['nombre']) ?> (<?= e($rol['slug']) ?>)</label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Guardar</button>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
              Volver
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php
    render('Nuevo usuario', ob_get_clean());
    break;

  case 'guardar':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar'); }
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');
    $perfil = trim($_POST['perfil'] ?? '');
    if ($usuario === '' || $nombre === '' || $pass === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.nuevo');
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    q("INSERT INTO usuarios (usuario, nombre, pass, perfil) VALUES (?,?,?,?)", [$usuario, $nombre, $hash, $perfil]);
    $id = (int)db()->lastInsertId();
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    usuarios_asignar_roles($id, $roles_ids);
    flash("<div class='alert alert-success'>Usuario creado.</div>");
    redirect('usuarios.listar');
    break;

  case 'editar':
    $id = (int)($_GET['id'] ?? 0);
    $u = qone("SELECT * FROM usuarios WHERE id=?", [$id]);
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
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h5 mb-3">Editar usuario</h1>
        <form method="post" action="?r=usuarios.actualizar&id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <?= form_input('usuario', 'Usuario', $u['usuario'], ['required'=>true]) ?>
          <?= form_input('nombre', 'Nombre', $u['nombre'], ['required'=>true]) ?>
          <?= form_input('perfil', 'Perfil (legacy)', $u['perfil'], ['help'=>'Se mantiene por compatibilidad con instalaciones anteriores.']) ?>
          <?php if (!empty($roles)): ?>
            <div class="mb-2">
              <label class="form-label">Roles</label>
              <?php foreach ($roles as $rol): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int)$rol['id'] ?>"
                    <?= in_array((int)$rol['id'], $roles_asignados, true) ? 'checked' : '' ?>>
                  <label class="form-check-label"><?= e($rol['nombre']) ?> (<?= e($rol['slug']) ?>)</label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Actualizar</button>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
              Volver
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php
    render('Editar usuario', ob_get_clean());
    break;

  case 'actualizar':
    $id = (int)($_GET['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar'); }
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $perfil = trim($_POST['perfil'] ?? '');
    if ($usuario === '' || $nombre === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect("usuarios.editar&id={$id}");
    }
    q("UPDATE usuarios SET usuario=?, nombre=?, perfil=? WHERE id=?", [$usuario, $nombre, $perfil, $id]);
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    usuarios_asignar_roles($id, $roles_ids);
    flash("<div class='alert alert-success'>Usuario actualizado.</div>");
    redirect('usuarios.listar');
    break;

  case 'eliminar':
    $id = (int)($_GET['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar'); }
    if ($id <= 0) redirect('usuarios.listar');

    $me = current_user();
    if ((int)($me['id'] ?? 0) === $id) {
      flash("<div class='alert alert-warning'>No puedes eliminar tu propio usuario.</div>");
      redirect('usuarios.listar');
    }

    $u = qone("SELECT id, usuario, nombre FROM usuarios WHERE id=?", [$id]);
    if (!$u) {
      flash("<div class='alert alert-warning'>Usuario no encontrado.</div>");
      redirect('usuarios.listar');
    }

    try {
      if (auth_roles_enabled()) {
        q("DELETE FROM usuarios_roles WHERE usuario_id=?", [$id]);
      }
      q("DELETE FROM usuarios WHERE id=?", [$id]);
      flash("<div class='alert alert-success'>Usuario eliminado.</div>");
    } catch (Exception $e) {
      flash("<div class='alert alert-danger'>No se pudo eliminar el usuario.</div>");
    }
    redirect('usuarios.listar');
    break;

  case 'cambiar_password':
    $id = (int)($_GET['id'] ?? 0);
    $u = qone("SELECT id, usuario, nombre FROM usuarios WHERE id=?", [$id]);
    if (!$u) { flash("<div class='alert alert-warning'>Usuario no encontrado.</div>"); redirect('usuarios.listar'); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar'); }
      $pass = (string)($_POST['pass'] ?? '');
      if ($pass === '') {
        flash("<div class='alert alert-warning'>Ingresa una contraseña.</div>");
        redirect("usuarios.cambiar_password&id={$id}");
      }
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      q("UPDATE usuarios SET pass=? WHERE id=?", [$hash, $id]);
      flash("<div class='alert alert-success'>Contraseña actualizada.</div>");
      redirect('usuarios.listar');
    }
    ob_start(); ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h5 mb-3">Cambiar contraseña</h1>
        <p class="text-muted">Usuario: <?= e($u['usuario']) ?> — <?= e($u['nombre']) ?></p>
        <form method="post" action="?r=usuarios.cambiar_password&id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <?= form_input('pass', 'Nueva contraseña', '', ['type'=>'password','required'=>true]) ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Actualizar</button>
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
              Volver
            </button>

          </div>
        </form>
      </div>
    </div>
    <?php
    render('Cambiar contraseña', ob_get_clean());
    break;

  case 'roles':
    if (!auth_roles_enabled()) {
      flash("<div class='alert alert-warning'>No existen las tablas de roles. Ejecuta la migración correspondiente.</div>");
      redirect('usuarios.listar');
    }
    $roles = usuarios_roles_disponibles();
    ob_start(); ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <h1 class="h4 m-0">Roles</h1>
      <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
        Volver
      </button>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6">Nuevo rol</h2>
        <form method="post" action="?r=usuarios.guardar_rol" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-md-3"><?= form_input('slug', 'Slug', '', ['required'=>true, 'help'=>'Ej. administradora']) ?></div>
          <div class="col-md-4"><?= form_input('nombre', 'Nombre', '', ['required'=>true]) ?></div>
          <div class="col-md-3">
            <label class="form-label">Super rol</label>
            <select name="es_super" class="form-select">
              <option value="0">No</option>
              <option value="1">Sí</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Guardar</button>
          </div>
        </form>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm">
        <thead>
          <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Nombre</th>
            <th>Super</th>
            <th>Permisos</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $rol): ?>
          <tr>
            <td><?= (int)$rol['id'] ?></td>
            <td><?= e($rol['slug']) ?></td>
            <td><?= e($rol['nombre']) ?></td>
            <td><?= !empty($rol['es_super']) ? 'Sí' : 'No' ?></td>
            <td>
              <a class="btn btn-outline-secondary btn-sm" href="?r=usuarios.permisos&rol_id=<?= (int)$rol['id'] ?>">Asignar permisos</a>
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

  case 'guardar_rol':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.roles');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.roles'); }
    $slug = trim($_POST['slug'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $es_super = (int)($_POST['es_super'] ?? 0);
    if ($slug === '' || $nombre === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.roles');
    }
    q("INSERT INTO roles (slug, nombre, es_super) VALUES (?,?,?)", [$slug, $nombre, $es_super]);
    flash("<div class='alert alert-success'>Rol creado.</div>");
    redirect('usuarios.roles');
    break;

  case 'permisos':
    if (!auth_permissions_enabled()) {
      flash("<div class='alert alert-warning'>No existen las tablas de permisos. Ejecuta la migración correspondiente.</div>");
      redirect('usuarios.listar');
    }
    $rol_id = (int)($_GET['rol_id'] ?? 0);
    $roles = usuarios_roles_disponibles();
    $permisos = usuarios_permisos_disponibles();
    $asignados = [];
    if ($rol_id > 0 && auth_permissions_enabled()) {
      $asignados = array_map(
        fn($r) => (int)$r['permiso_id'],
        qall("SELECT permiso_id FROM roles_permisos WHERE rol_id=?", [$rol_id])
      );
    }
    ob_start(); ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
      <h1 class="h4 m-0">Permisos</h1>
      <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
        Volver
      </button>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6">Nuevo permiso</h2>
        <form method="post" action="?r=usuarios.guardar_permiso" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-md-4"><?= form_input('clave', 'Clave', '', ['required'=>true, 'help'=>'Ej. usuarios.admin']) ?></div>
          <div class="col-md-6"><?= form_input('descripcion', 'Descripción', '', ['required'=>true]) ?></div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Guardar</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($roles)): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6">Asignar permisos a rol</h2>
        <form method="post" action="?r=usuarios.asignar_permisos" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-md-4">
            <label class="form-label">Rol</label>
            <select name="rol_id" class="form-select">
              <?php foreach ($roles as $rol): ?>
                <option value="<?= (int)$rol['id'] ?>" <?= $rol_id === (int)$rol['id'] ? 'selected' : '' ?>>
                  <?= e($rol['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Permisos</label>
            <div class="row">
              <?php foreach ($permisos as $perm): ?>
                <div class="col-md-6">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permisos[]" value="<?= (int)$perm['id'] ?>"
                      <?= in_array((int)$perm['id'], $asignados, true) ? 'checked' : '' ?>>
                    <label class="form-check-label"><?= e($perm['clave']) ?> — <?= e($perm['descripcion']) ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm">
        <thead>
          <tr>
            <th>ID</th>
            <th>Clave</th>
            <th>Descripción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permisos as $perm): ?>
          <tr>
            <td><?= (int)$perm['id'] ?></td>
            <td><?= e($perm['clave']) ?></td>
            <td><?= e($perm['descripcion']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($permisos)): ?>
          <tr><td colspan="3" class="text-center text-muted">Sin permisos</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    render('Permisos', ob_get_clean());
    break;

  case 'guardar_permiso':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.permisos');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.permisos'); }
    $clave = trim($_POST['clave'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($clave === '' || $descripcion === '') {
      flash("<div class='alert alert-warning'>Completa los datos obligatorios.</div>");
      redirect('usuarios.permisos');
    }
    q("INSERT INTO permisos (clave, descripcion) VALUES (?,?)", [$clave, $descripcion]);
    flash("<div class='alert alert-success'>Permiso creado.</div>");
    redirect('usuarios.permisos');
    break;

  case 'asignar_roles':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.listar'); }
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    $roles_ids = array_map('intval', $_POST['roles'] ?? []);
    usuarios_asignar_roles($usuario_id, $roles_ids);
    flash("<div class='alert alert-success'>Roles actualizados.</div>");
    redirect('usuarios.editar&id='.$usuario_id);
    break;

  case 'asignar_permisos':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('usuarios.permisos');
    try { csrf_verify(); } catch (RuntimeException $e) { flash("<div class='alert alert-danger'>Sesión inválida.</div>"); redirect('usuarios.permisos'); }
    $rol_id = (int)($_POST['rol_id'] ?? 0);
    $perm_ids = array_map('intval', $_POST['permisos'] ?? []);
    usuarios_asignar_permisos($rol_id, $perm_ids);
    flash("<div class='alert alert-success'>Permisos actualizados.</div>");
    redirect('usuarios.permisos&rol_id='.$rol_id);
    break;
}
