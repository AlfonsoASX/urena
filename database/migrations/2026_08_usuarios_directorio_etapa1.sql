-- @name: Usuarios como directorio unico - etapa 1
-- @description: Amplia usuarios, concilia futuro_personal sin coincidencias aproximadas y crea usuarios de directorio sin acceso. No cambia referencias operativas.
-- @backup: usuarios,usuarios_roles,futuro_personal,usuarios_personal_legacy

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_preparar_esquema$$
CREATE PROCEDURE usuarios_directorio_preparar_esquema()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='estatus') THEN
    ALTER TABLE usuarios ADD COLUMN estatus VARCHAR(10) NOT NULL DEFAULT 'ACTIVO';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='puesto') THEN
    ALTER TABLE usuarios ADD COLUMN puesto VARCHAR(60) NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='jefe_directo_id') THEN
    ALTER TABLE usuarios ADD COLUMN jefe_directo_id INT(11) NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='acceso_sistema') THEN
    ALTER TABLE usuarios ADD COLUMN acceso_sistema TINYINT(1) NOT NULL DEFAULT 1;
  END IF;

  ALTER TABLE usuarios MODIFY usuario VARCHAR(50) NULL;
  ALTER TABLE usuarios MODIFY pass VARCHAR(255) NULL;
  ALTER TABLE usuarios MODIFY perfil VARCHAR(50) NULL;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='usuario' AND NON_UNIQUE=0) THEN
    ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_usuario (usuario);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND INDEX_NAME='idx_usuarios_puesto_estatus') THEN
    ALTER TABLE usuarios ADD KEY idx_usuarios_puesto_estatus (puesto,estatus);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND INDEX_NAME='idx_usuarios_jefe') THEN
    ALTER TABLE usuarios ADD KEY idx_usuarios_jefe (jefe_directo_id);
  END IF;
END$$
CALL usuarios_directorio_preparar_esquema()$$
DROP PROCEDURE usuarios_directorio_preparar_esquema$$
DELIMITER ;

CREATE TABLE IF NOT EXISTS usuarios_personal_legacy (
  id_personal INT(10) NOT NULL,
  id_usuario INT(11) NOT NULL,
  tipo_vinculo VARCHAR(30) NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  notas VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_personal),
  UNIQUE KEY uq_upl_usuario (id_usuario),
  KEY idx_upl_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enlaces explicitos solamente cuando son uno a uno. Esto excluye, entre otros,
-- los dos cobradores que estaban ligados al mismo usuario pantalla.
INSERT IGNORE INTO usuarios_personal_legacy (id_personal,id_usuario,tipo_vinculo,estado,notas)
SELECT fp.id_personal,fp.id,'explicito','conciliado','Enlace uno a uno conservado desde futuro_personal.id'
FROM futuro_personal fp
JOIN usuarios u ON u.id=fp.id
JOIN (
  SELECT id FROM futuro_personal WHERE id IS NOT NULL GROUP BY id HAVING COUNT(*)=1
) unicos ON unicos.id=fp.id;

-- Coincidencia por nombre completo exacto y unico en ambos catalogos. Se fuerza
-- una misma collation para evitar errores entre servidores MariaDB/MySQL.
INSERT IGNORE INTO usuarios_personal_legacy (id_personal,id_usuario,tipo_vinculo,estado,notas)
SELECT fp.id_personal,usuarios_unicos.id_usuario,'nombre_exacto','conciliado','Coincidencia exacta y unica por nombre completo'
FROM futuro_personal fp
JOIN (
  SELECT clave_nombre,MIN(id) AS id_usuario
  FROM (
    SELECT id,UPPER(TRIM(CONVERT(nombre USING utf8mb4))) COLLATE utf8mb4_general_ci AS clave_nombre
    FROM usuarios
  ) usuarios_nombres
  GROUP BY clave_nombre
  HAVING COUNT(*)=1
) usuarios_unicos
  ON usuarios_unicos.clave_nombre=UPPER(TRIM(CONVERT(CONCAT_WS(' ',fp.nombre,fp.apellido_p,fp.apellido_m) USING utf8mb4))) COLLATE utf8mb4_general_ci
JOIN (
  SELECT clave_nombre
  FROM (
    SELECT UPPER(TRIM(CONVERT(CONCAT_WS(' ',nombre,apellido_p,apellido_m) USING utf8mb4))) COLLATE utf8mb4_general_ci AS clave_nombre
    FROM futuro_personal
  ) personal_nombres
  GROUP BY clave_nombre
  HAVING COUNT(*)=1
) personal_unico ON personal_unico.clave_nombre=usuarios_unicos.clave_nombre
LEFT JOIN usuarios_personal_legacy existente ON existente.id_personal=fp.id_personal
WHERE existente.id_personal IS NULL;

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_crear_faltantes$$
CREATE PROCEDURE usuarios_directorio_crear_faltantes()
BEGIN
  DECLARE terminado INT DEFAULT 0;
  DECLARE v_id_personal INT;
  DECLARE v_nombre VARCHAR(255);
  DECLARE v_estatus VARCHAR(10);
  DECLARE v_puesto VARCHAR(60);
  DECLARE v_usuario INT;
  DECLARE cur CURSOR FOR
    SELECT fp.id_personal,
           TRIM(CONCAT_WS(' ',fp.nombre,fp.apellido_p,fp.apellido_m)),
           CASE WHEN UPPER(COALESCE(fp.estatus,'')) IN ('ACTIVO','ACTIVE') THEN 'ACTIVO' ELSE 'INACTIVO' END,
           CASE
             WHEN UPPER(COALESCE(p.nom_puesto,'')) IN ('PROMOTOR','VENDEDOR') THEN 'vendedor'
             WHEN UPPER(COALESCE(p.nom_puesto,''))='COBRADOR' THEN 'cobrador'
             WHEN UPPER(COALESCE(p.nom_puesto,'')) LIKE '%AUXILIAR%' THEN 'auxiliar'
             WHEN UPPER(COALESCE(p.nom_puesto,'')) LIKE '%OPER%' THEN 'operario'
             WHEN UPPER(COALESCE(p.nom_puesto,'')) LIKE '%RECEP%' THEN 'recepcionista'
             ELSE 'otro'
           END
    FROM futuro_personal fp
    LEFT JOIN futuro_puestos p ON p.id_puesto=fp.id_puesto
    LEFT JOIN usuarios_personal_legacy upl ON upl.id_personal=fp.id_personal
    WHERE upl.id_personal IS NULL
    ORDER BY fp.id_personal;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET terminado=1;

  OPEN cur;
  bucle: LOOP
    FETCH cur INTO v_id_personal,v_nombre,v_estatus,v_puesto;
    IF terminado=1 THEN LEAVE bucle; END IF;
    INSERT INTO usuarios (usuario,nombre,pass,perfil,estatus,puesto,jefe_directo_id,acceso_sistema)
    VALUES (NULL,v_nombre,NULL,NULL,v_estatus,v_puesto,NULL,0);
    SET v_usuario=LAST_INSERT_ID();
    INSERT INTO usuarios_personal_legacy (id_personal,id_usuario,tipo_vinculo,estado,notas)
    VALUES (v_id_personal,v_usuario,'creado','conciliado','Usuario de directorio creado sin acceso');
  END LOOP;
  CLOSE cur;
END$$
CALL usuarios_directorio_crear_faltantes()$$
DROP PROCEDURE usuarios_directorio_crear_faltantes$$
DELIMITER ;

-- Completa puestos inequívocos sin usar roles como puesto organizacional.
UPDATE usuarios u
JOIN usuarios_personal_legacy upl ON upl.id_usuario=u.id
JOIN futuro_personal fp ON fp.id_personal=upl.id_personal
LEFT JOIN futuro_puestos p ON p.id_puesto=fp.id_puesto
SET u.puesto=CASE
  WHEN UPPER(COALESCE(p.nom_puesto,'')) IN ('PROMOTOR','VENDEDOR') THEN 'vendedor'
  WHEN UPPER(COALESCE(p.nom_puesto,''))='COBRADOR' THEN 'cobrador'
  WHEN u.puesto IS NULL OR u.puesto='' THEN 'otro'
  ELSE u.puesto END;

-- Semilla organizacional acordada: Jessica gerente y Mario lider.
UPDATE usuarios SET puesto='gerente',jefe_directo_id=NULL,estatus='ACTIVO'
WHERE UPPER(TRIM(CONVERT(usuario USING utf8mb4))) COLLATE utf8mb4_general_ci = UPPER('je.ureña') COLLATE utf8mb4_general_ci;

SET @usuario_jessica=(SELECT MIN(id) FROM usuarios WHERE UPPER(TRIM(CONVERT(usuario USING utf8mb4))) COLLATE utf8mb4_general_ci = UPPER('je.ureña') COLLATE utf8mb4_general_ci);
UPDATE usuarios SET puesto='lider',jefe_directo_id=@usuario_jessica,estatus='ACTIVO'
WHERE UPPER(TRIM(CONVERT(usuario USING utf8mb4))) COLLATE utf8mb4_general_ci = UPPER('ma.ureña') COLLATE utf8mb4_general_ci;

SET @usuario_mario=(SELECT MIN(id) FROM usuarios WHERE UPPER(TRIM(CONVERT(usuario USING utf8mb4))) COLLATE utf8mb4_general_ci = UPPER('ma.ureña') COLLATE utf8mb4_general_ci);
SET @personal_mario=(SELECT MIN(id_personal) FROM usuarios_personal_legacy WHERE id_usuario=@usuario_mario);
SET @personal_jessica=(SELECT MIN(id_personal) FROM usuarios_personal_legacy WHERE id_usuario=@usuario_jessica);

-- Solo vendedores: los subordinados antiguos de Mario quedan con Mario/Jessica.
UPDATE usuarios u
JOIN usuarios_personal_legacy upl ON upl.id_usuario=u.id
JOIN futuro_personal fp ON fp.id_personal=upl.id_personal
LEFT JOIN futuro_puestos p ON p.id_puesto=fp.id_puesto
SET u.puesto='vendedor',u.jefe_directo_id=@usuario_mario
WHERE fp.jefe_directo=@personal_mario
  AND UPPER(COALESCE(p.nom_puesto,'')) IN ('PROMOTOR','VENDEDOR');

-- Los vendedores que reportaban directamente a Jessica requieren elegir lider.
UPDATE usuarios u
JOIN usuarios_personal_legacy upl ON upl.id_usuario=u.id
JOIN futuro_personal fp ON fp.id_personal=upl.id_personal
LEFT JOIN futuro_puestos p ON p.id_puesto=fp.id_puesto
SET u.puesto='vendedor',u.jefe_directo_id=NULL
WHERE fp.jefe_directo=@personal_jessica
  AND UPPER(COALESCE(p.nom_puesto,'')) IN ('PROMOTOR','VENDEDOR')
  AND (u.jefe_directo_id IS NULL OR u.jefe_directo_id=@usuario_jessica);

SELECT
  (SELECT COUNT(*) FROM futuro_personal) AS personal_historico,
  (SELECT COUNT(*) FROM usuarios_personal_legacy) AS personal_con_usuario,
  (SELECT COUNT(*) FROM usuarios WHERE acceso_sistema=0) AS usuarios_sin_acceso,
  (SELECT COUNT(*) FROM usuarios WHERE puesto='vendedor' AND jefe_directo_id IS NULL) AS vendedores_pendientes_lider;
