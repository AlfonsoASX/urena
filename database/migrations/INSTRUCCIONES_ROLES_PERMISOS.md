# Instrucciones para habilitar roles y permisos

## 1. Crear las tablas y datos base

Ejecuta la migración incluida en `database/migrations/2025_01_roles_permisos.sql` sobre la base de datos activa:

```sql
SOURCE database/migrations/2025_01_roles_permisos.sql;
```

> La migración crea las tablas `roles`, `permisos`, `usuarios_roles` y `roles_permisos`, además de sembrar los roles actuales (`administradora`, `auxiliar`, `vendedor`, `cobrador`) y el permiso `usuarios.admin`.

## 2. Verificar usuarios existentes

La migración enlaza los usuarios existentes usando el campo `usuarios.perfil`.

Si necesitas validar el resultado:

```sql
SELECT u.usuario, u.perfil, GROUP_CONCAT(r.slug) AS roles
FROM usuarios u
LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
LEFT JOIN roles r ON r.id = ur.rol_id
GROUP BY u.id;
```

## 3. Asignar permisos adicionales

Puedes crear permisos adicionales desde la interfaz de **Usuarios > Permisos** o de forma manual:

```sql
INSERT INTO permisos (clave, descripcion)
VALUES ('inventario.admin', 'Administrar inventario');

INSERT INTO roles_permisos (rol_id, permiso_id)
SELECT r.id, p.id
FROM roles r
JOIN permisos p ON p.clave = 'inventario.admin'
WHERE r.slug = 'administradora';
```

## 4. (Opcional) Habilitar seguridad total

En `app/config/.env.php` asegúrate de tener:

```php
'modo_libre' => false,
```

Esto activa la validación de roles y permisos.
