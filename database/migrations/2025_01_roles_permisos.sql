-- Migración: roles y permisos

START TRANSACTION;

CREATE TABLE IF NOT EXISTS roles (
  id INT(11) NOT NULL AUTO_INCREMENT,
  slug VARCHAR(50) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  es_super TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE IF NOT EXISTS permisos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  clave VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_permisos_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE IF NOT EXISTS usuarios_roles (
  usuario_id INT(11) NOT NULL,
  rol_id INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, rol_id),
  KEY idx_ur_rol (rol_id),
  CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ur_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE IF NOT EXISTS roles_permisos (
  rol_id INT(11) NOT NULL,
  permiso_id INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (rol_id, permiso_id),
  KEY idx_rp_permiso (permiso_id),
  CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Roles base
INSERT IGNORE INTO roles (slug, nombre, es_super) VALUES
  ('administradora', 'Administradora', 1),
  ('auxiliar', 'Auxiliar', 0),
  ('vendedor', 'Vendedor', 0),
  ('cobrador', 'Cobrador', 0);

-- Permisos base
INSERT IGNORE INTO permisos (clave, descripcion) VALUES
  ('usuarios.admin', 'Administración de usuarios, roles y permisos');

-- Asigna permiso base a rol administradora
INSERT IGNORE INTO roles_permisos (rol_id, permiso_id)
SELECT r.id, p.id
FROM roles r
JOIN permisos p ON p.clave = 'usuarios.admin'
WHERE r.slug = 'administradora';

-- Enlaza usuarios existentes usando el campo perfil
INSERT IGNORE INTO usuarios_roles (usuario_id, rol_id)
SELECT u.id, r.id
FROM usuarios u
JOIN roles r ON r.slug = u.perfil
WHERE u.perfil IS NOT NULL AND u.perfil <> '';

COMMIT;
