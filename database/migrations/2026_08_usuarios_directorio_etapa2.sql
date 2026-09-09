-- @name: Usuarios como directorio unico - etapa 2
-- @description: Activa usuarios como unica referencia operativa. je.ureña cubre como lider y gerente los casos sin jerarquia configurada.
-- @backup: usuarios,usuarios_personal_legacy,futuro_contratos,futuro_contrato_vendedor,futuro_contrato_lider,futuro_contrato_jefecuad,futuro_contrato_cobrador,futuro_abono_cobrador,futuro_gestion,futuro_comision_semanal,futuro_bono_4ventas,futuro_bono_reco,futuro_contrato_promocion_condiciones,futuro_abono_distribucion,cortes_pagos

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_asignar_respaldo$$
CREATE PROCEDURE usuarios_directorio_asignar_respaldo()
BEGIN
  DECLARE v_respaldo INT DEFAULT 0;
  SELECT COALESCE(MAX(id),0) INTO v_respaldo
  FROM usuarios
  WHERE usuario='je.ureña'
    AND UPPER(COALESCE(estatus,'ACTIVO'))='ACTIVO';

  IF v_respaldo=0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: no existe el usuario activo je.ureña.';
  END IF;

  UPDATE usuarios SET puesto='gerente',jefe_directo_id=NULL WHERE id=v_respaldo;

  UPDATE usuarios vendedor
  LEFT JOIN usuarios lider ON lider.id=vendedor.jefe_directo_id
  SET vendedor.jefe_directo_id=v_respaldo
  WHERE vendedor.puesto='vendedor'
    AND UPPER(COALESCE(vendedor.estatus,'ACTIVO'))='ACTIVO'
    AND (lider.id IS NULL
         OR UPPER(COALESCE(lider.estatus,'ACTIVO'))<>'ACTIVO'
         OR (lider.puesto<>'lider' AND lider.id<>v_respaldo));

  UPDATE usuarios lider
  LEFT JOIN usuarios gerente ON gerente.id=lider.jefe_directo_id
  SET lider.jefe_directo_id=v_respaldo
  WHERE lider.puesto='lider'
    AND UPPER(COALESCE(lider.estatus,'ACTIVO'))='ACTIVO'
    AND lider.id<>v_respaldo
    AND (gerente.id IS NULL
         OR gerente.puesto<>'gerente'
         OR UPPER(COALESCE(gerente.estatus,'ACTIVO'))<>'ACTIVO');
END$$
CALL usuarios_directorio_asignar_respaldo()$$
DROP PROCEDURE usuarios_directorio_asignar_respaldo$$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_validar_activacion$$
CREATE PROCEDURE usuarios_directorio_validar_activacion()
BEGIN
  DECLARE v_pendientes INT DEFAULT 0;
  DECLARE v_usuario INT DEFAULT 0;
  DECLARE v_actual INT DEFAULT 0;
  DECLARE v_nivel INT DEFAULT 0;
  DECLARE v_fin INT DEFAULT 0;
  DECLARE v_respaldo INT DEFAULT 0;
  DECLARE cur_usuarios CURSOR FOR SELECT id FROM usuarios WHERE jefe_directo_id IS NOT NULL ORDER BY id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin=1;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios_personal_legacy') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ejecuta primero Usuarios como directorio unico - etapa 1.';
  END IF;

  SELECT COALESCE(MAX(id),0) INTO v_respaldo
  FROM usuarios
  WHERE usuario='je.ureña' AND puesto='gerente'
    AND UPPER(COALESCE(estatus,'ACTIVO'))='ACTIVO';
  IF v_respaldo=0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: je.ureña debe ser un gerente activo.';
  END IF;

  SELECT COUNT(*) INTO v_pendientes
  FROM futuro_personal fp
  LEFT JOIN usuarios_personal_legacy upl ON upl.id_personal=fp.id_personal
  LEFT JOIN usuarios u ON u.id=upl.id_usuario
  WHERE u.id IS NULL;
  IF v_pendientes>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: existe personal historico sin usuario conciliado.';
  END IF;

  SELECT COALESCE(SUM(x.total),0) INTO v_pendientes FROM (
    SELECT COUNT(*) total FROM futuro_contrato_vendedor r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_contrato_lider r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_contrato_jefecuad r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_contrato_cobrador r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_abono_cobrador r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_gestion r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_comision_semanal r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_comisionista WHERE r.id_comisionista IS NOT NULL AND NOT (r.id_comisionista=0 AND UPPER(COALESCE(r.comisionista,''))='LIDER') AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_bono_4ventas r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
    UNION ALL SELECT COUNT(*) FROM futuro_bono_reco r LEFT JOIN usuarios_personal_legacy m ON m.id_personal=r.id_personal WHERE r.id_personal IS NOT NULL AND m.id_usuario IS NULL
  ) x;
  IF v_pendientes>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: existen referencias historicas sin usuario conciliado.';
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones') THEN
    SELECT COUNT(*) INTO v_pendientes FROM futuro_contrato_promocion_condiciones c
    LEFT JOIN usuarios_personal_legacy mv ON mv.id_personal=c.id_vendedor
    LEFT JOIN usuarios_personal_legacy ml ON ml.id_personal=c.id_lider
    LEFT JOIN usuarios_personal_legacy mg ON mg.id_personal=c.id_gerente
    WHERE (c.id_vendedor IS NOT NULL AND mv.id_usuario IS NULL)
       OR (c.id_lider IS NOT NULL AND ml.id_usuario IS NULL)
       OR (c.id_gerente IS NOT NULL AND mg.id_usuario IS NULL);
    IF v_pendientes>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: hay condiciones de promocion sin usuario conciliado.'; END IF;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_abono_distribucion') THEN
    SELECT COUNT(*) INTO v_pendientes FROM futuro_abono_distribucion d LEFT JOIN usuarios_personal_legacy m ON m.id_personal=d.id_personal WHERE d.id_personal IS NOT NULL AND m.id_usuario IS NULL;
    IF v_pendientes>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: hay distribuciones sin usuario conciliado.'; END IF;
  END IF;

  SELECT COUNT(*) INTO v_pendientes
  FROM usuarios u
  WHERE u.jefe_directo_id=u.id;
  IF v_pendientes>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: existe una autoasignacion en la jerarquia de usuarios.';
  END IF;

  SET v_fin=0;
  OPEN cur_usuarios;
  validar_ciclos: LOOP
    FETCH cur_usuarios INTO v_usuario;
    IF v_fin=1 THEN LEAVE validar_ciclos; END IF;
    SET v_actual=v_usuario;
    SET v_nivel=0;
    WHILE v_actual>0 AND v_nivel<100 DO
      SET v_actual=COALESCE((SELECT MAX(jefe_directo_id) FROM usuarios WHERE id=v_actual),0);
      SET v_nivel=v_nivel+1;
      IF v_actual=v_usuario THEN
        CLOSE cur_usuarios;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: existe un ciclo en la jerarquia de usuarios.';
      END IF;
    END WHILE;
    IF v_actual>0 THEN
      CLOSE cur_usuarios;
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: la jerarquia excede 100 niveles o contiene un ciclo.';
    END IF;
  END LOOP;
  CLOSE cur_usuarios;

  SELECT COUNT(*) INTO v_pendientes
  FROM usuarios lider
  LEFT JOIN usuarios gerente ON gerente.id=lider.jefe_directo_id
  WHERE lider.puesto='lider' AND UPPER(COALESCE(lider.estatus,'ACTIVO'))='ACTIVO'
    AND (gerente.id IS NULL OR gerente.puesto<>'gerente' OR UPPER(COALESCE(gerente.estatus,'ACTIVO'))<>'ACTIVO' OR gerente.id=lider.id);
  IF v_pendientes>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: hay lideres activos sin gerente valido.';
  END IF;

  SELECT COUNT(*) INTO v_pendientes
  FROM usuarios vendedor
  LEFT JOIN usuarios lider ON lider.id=vendedor.jefe_directo_id
  LEFT JOIN usuarios gerente ON gerente.id=IF(lider.id=v_respaldo,v_respaldo,lider.jefe_directo_id)
  WHERE vendedor.puesto='vendedor'
    AND UPPER(COALESCE(vendedor.estatus,'ACTIVO'))='ACTIVO'
    AND (lider.id IS NULL OR (lider.puesto<>'lider' AND lider.id<>v_respaldo) OR UPPER(COALESCE(lider.estatus,'ACTIVO'))<>'ACTIVO'
         OR gerente.id IS NULL OR gerente.puesto<>'gerente' OR UPPER(COALESCE(gerente.estatus,'ACTIVO'))<>'ACTIVO'
         OR vendedor.id=lider.id OR vendedor.id=gerente.id OR (lider.id=gerente.id AND lider.id<>v_respaldo));
  IF v_pendientes>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activacion bloqueada: hay vendedores activos sin cadena vendedor-lider-gerente valida.';
  END IF;

  -- Las relaciones historicas conservan a quien realizo la venta aunque hoy tenga
  -- otro puesto. Los contratos sin vendedor usan al usuario de respaldo al congelarse.
END$$
CALL usuarios_directorio_validar_activacion()$$
DROP PROCEDURE usuarios_directorio_validar_activacion$$
DELIMITER ;

-- Las siguientes alteraciones solo se alcanzan cuando toda la validacion termina.
DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_agregar_referencias$$
CREATE PROCEDURE usuarios_directorio_agregar_referencias()
BEGIN
  -- Las columnas antiguas permanecen como archivo, pero dejan de ser obligatorias
  -- para que toda escritura nueva utilice exclusivamente id_usuario.
  ALTER TABLE futuro_contrato_vendedor MODIFY id_personal INT(10) NULL;
  ALTER TABLE futuro_contrato_lider MODIFY id_personal INT(10) NULL;
  ALTER TABLE futuro_contrato_jefecuad MODIFY id_personal INT(10) NULL;
  ALTER TABLE futuro_contrato_cobrador MODIFY id_personal INT(10) NULL;
  ALTER TABLE futuro_abono_cobrador MODIFY id_personal INT(10) NULL;
  ALTER TABLE futuro_gestion MODIFY id_personal INT(10) NULL;
  ALTER TABLE futuro_comision_semanal MODIFY id_comisionista INT(10) NULL;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contratos' AND COLUMN_NAME='id_usuario_vendedor') THEN ALTER TABLE futuro_contratos ADD COLUMN id_usuario_vendedor INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contratos' AND COLUMN_NAME='id_usuario_lider') THEN ALTER TABLE futuro_contratos ADD COLUMN id_usuario_lider INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contratos' AND COLUMN_NAME='id_usuario_gerente') THEN ALTER TABLE futuro_contratos ADD COLUMN id_usuario_gerente INT(11) NULL; END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_vendedor' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_contrato_vendedor ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_lider' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_contrato_lider ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_jefecuad' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_contrato_jefecuad ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_cobrador' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_contrato_cobrador ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_abono_cobrador' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_abono_cobrador ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_gestion' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_gestion ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_comision_semanal' AND COLUMN_NAME='id_usuario_comisionista') THEN ALTER TABLE futuro_comision_semanal ADD COLUMN id_usuario_comisionista INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_bono_4ventas' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_bono_4ventas ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_bono_reco' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_bono_reco ADD COLUMN id_usuario INT(11) NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_bono_reco' AND COLUMN_NAME='id_usuario_recomendado') THEN ALTER TABLE futuro_bono_reco ADD COLUMN id_usuario_recomendado INT(11) NULL; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cortes_pagos') AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cortes_pagos' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE cortes_pagos ADD COLUMN id_usuario INT(11) NULL; END IF;

  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones') THEN
    ALTER TABLE futuro_contrato_promocion_condiciones MODIFY id_vendedor INT(10) NULL;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones' AND COLUMN_NAME='id_usuario_vendedor') THEN ALTER TABLE futuro_contrato_promocion_condiciones ADD COLUMN id_usuario_vendedor INT(11) NULL; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones' AND COLUMN_NAME='id_usuario_lider') THEN ALTER TABLE futuro_contrato_promocion_condiciones ADD COLUMN id_usuario_lider INT(11) NULL; END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones' AND COLUMN_NAME='id_usuario_gerente') THEN ALTER TABLE futuro_contrato_promocion_condiciones ADD COLUMN id_usuario_gerente INT(11) NULL; END IF;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_abono_distribucion') AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_abono_distribucion' AND COLUMN_NAME='id_usuario') THEN ALTER TABLE futuro_abono_distribucion ADD COLUMN id_usuario INT(11) NULL; END IF;
END$$
CALL usuarios_directorio_agregar_referencias()$$
DROP PROCEDURE usuarios_directorio_agregar_referencias$$
DELIMITER ;

-- Backfill uno a uno; no se recalculan importes ni se crean movimientos.
UPDATE futuro_contrato_vendedor x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_contrato_lider x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_contrato_jefecuad x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_contrato_cobrador x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_abono_cobrador x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_gestion x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_comision_semanal x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_comisionista SET x.id_usuario_comisionista=m.id_usuario WHERE x.id_usuario_comisionista IS NULL;
UPDATE futuro_comision_semanal x
JOIN usuarios respaldo ON respaldo.usuario='je.ureña'
SET x.id_usuario_comisionista=respaldo.id
WHERE x.id_usuario_comisionista IS NULL
  AND x.id_comisionista=0
  AND UPPER(COALESCE(x.comisionista,''))='LIDER';
UPDATE futuro_bono_4ventas x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_bono_reco x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
UPDATE futuro_bono_reco x JOIN usuarios_personal_legacy m ON m.id_personal=x.recomendado SET x.id_usuario_recomendado=m.id_usuario WHERE x.id_usuario_recomendado IS NULL;

UPDATE futuro_contratos c
JOIN futuro_contrato_vendedor fcv ON fcv.id_cont_vend=(SELECT MAX(x.id_cont_vend) FROM futuro_contrato_vendedor x WHERE x.id_contrato=c.id_contrato)
JOIN usuarios_personal_legacy m ON m.id_personal=fcv.id_personal
SET c.id_usuario_vendedor=m.id_usuario
WHERE c.id_usuario_vendedor IS NULL;

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_congelar_contratos$$
CREATE PROCEDURE usuarios_directorio_congelar_contratos()
BEGIN
  DECLARE v_respaldo INT DEFAULT 0;
  SELECT COALESCE(MAX(id),0) INTO v_respaldo FROM usuarios WHERE usuario='je.ureña';

  UPDATE futuro_contratos c
  LEFT JOIN usuarios vendedor ON vendedor.id=c.id_usuario_vendedor
  LEFT JOIN usuarios lider
    ON lider.id=vendedor.jefe_directo_id
   AND (lider.puesto='lider' OR lider.id=v_respaldo)
   AND UPPER(COALESCE(lider.estatus,'ACTIVO'))='ACTIVO'
  LEFT JOIN usuarios gerente
    ON gerente.id=IF(lider.id=v_respaldo,v_respaldo,lider.jefe_directo_id)
   AND gerente.puesto='gerente'
   AND UPPER(COALESCE(gerente.estatus,'ACTIVO'))='ACTIVO'
  SET c.id_usuario_vendedor=COALESCE(vendedor.id,v_respaldo),
      c.id_usuario_lider=COALESCE(lider.id,v_respaldo),
      c.id_usuario_gerente=COALESCE(gerente.id,v_respaldo)
  WHERE c.id_usuario_vendedor IS NULL
     OR c.id_usuario_lider IS NULL
     OR c.id_usuario_gerente IS NULL;
END$$
CALL usuarios_directorio_congelar_contratos()$$
DROP PROCEDURE usuarios_directorio_congelar_contratos$$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_backfill_opcional$$
CREATE PROCEDURE usuarios_directorio_backfill_opcional()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones') THEN
    UPDATE futuro_contrato_promocion_condiciones x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_vendedor SET x.id_usuario_vendedor=m.id_usuario WHERE x.id_usuario_vendedor IS NULL;
    UPDATE futuro_contrato_promocion_condiciones x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_lider SET x.id_usuario_lider=m.id_usuario WHERE x.id_usuario_lider IS NULL;
    UPDATE futuro_contrato_promocion_condiciones x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_gerente SET x.id_usuario_gerente=m.id_usuario WHERE x.id_usuario_gerente IS NULL;
    UPDATE futuro_contrato_promocion_condiciones x JOIN futuro_contratos c ON c.id_contrato=x.id_contrato SET x.id_usuario_vendedor=COALESCE(x.id_usuario_vendedor,c.id_usuario_vendedor),x.id_usuario_lider=COALESCE(x.id_usuario_lider,c.id_usuario_lider),x.id_usuario_gerente=COALESCE(x.id_usuario_gerente,c.id_usuario_gerente);
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_abono_distribucion') THEN
    UPDATE futuro_abono_distribucion x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL AND x.id_personal IS NOT NULL;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cortes_pagos') THEN
    UPDATE cortes_pagos x JOIN usuarios_personal_legacy m ON m.id_personal=x.id_personal SET x.id_usuario=m.id_usuario WHERE x.id_usuario IS NULL;
  END IF;
END$$
CALL usuarios_directorio_backfill_opcional()$$
DROP PROCEDURE usuarios_directorio_backfill_opcional$$
DELIMITER ;

CREATE OR REPLACE VIEW vw_corte_cobrador AS
SELECT a.id_abono,a.id_contrato,CAST(a.fecha_registro AS DATE) AS fecha,a.cant_abono,
       ac.id_usuario AS id_cobrador,u.nombre AS cobrador,vwt.titular
FROM futuro_abonos a
JOIN futuro_abono_cobrador ac ON ac.id_abono_cobrador=(SELECT MAX(x.id_abono_cobrador) FROM futuro_abono_cobrador x WHERE x.id_abono=a.id_abono)
JOIN usuarios u ON u.id=ac.id_usuario
LEFT JOIN vw_titular_contrato vwt ON vwt.id_contrato=a.id_contrato;

CREATE OR REPLACE VIEW vw_corte_vendedor AS
SELECT a.id_abono,a.id_contrato,CAST(a.fecha_registro AS DATE) AS fecha,a.cant_abono,
       c.id_usuario_vendedor AS id_vendedor,u.nombre AS vendedor,vwt.titular
FROM futuro_abonos a
JOIN futuro_contratos c ON c.id_contrato=a.id_contrato
JOIN usuarios u ON u.id=c.id_usuario_vendedor
LEFT JOIN vw_titular_contrato vwt ON vwt.id_contrato=a.id_contrato;

DELIMITER $$
DROP PROCEDURE IF EXISTS usuarios_directorio_agregar_fks$$
CREATE PROCEDURE usuarios_directorio_agregar_fks()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_usuarios_jefe') THEN ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_jefe FOREIGN KEY (jefe_directo_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_upl_usuario') THEN ALTER TABLE usuarios_personal_legacy ADD CONSTRAINT fk_upl_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fc_usuario_vendedor') THEN ALTER TABLE futuro_contratos ADD CONSTRAINT fk_fc_usuario_vendedor FOREIGN KEY (id_usuario_vendedor) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fc_usuario_lider') THEN ALTER TABLE futuro_contratos ADD CONSTRAINT fk_fc_usuario_lider FOREIGN KEY (id_usuario_lider) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fc_usuario_gerente') THEN ALTER TABLE futuro_contratos ADD CONSTRAINT fk_fc_usuario_gerente FOREIGN KEY (id_usuario_gerente) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcv_usuario') THEN ALTER TABLE futuro_contrato_vendedor ADD CONSTRAINT fk_fcv_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcl_usuario') THEN ALTER TABLE futuro_contrato_lider ADD CONSTRAINT fk_fcl_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcj_usuario') THEN ALTER TABLE futuro_contrato_jefecuad ADD CONSTRAINT fk_fcj_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcc_usuario') THEN ALTER TABLE futuro_contrato_cobrador ADD CONSTRAINT fk_fcc_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fac_usuario') THEN ALTER TABLE futuro_abono_cobrador ADD CONSTRAINT fk_fac_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fg_usuario') THEN ALTER TABLE futuro_gestion ADD CONSTRAINT fk_fg_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcs_usuario') THEN ALTER TABLE futuro_comision_semanal ADD CONSTRAINT fk_fcs_usuario FOREIGN KEY (id_usuario_comisionista) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fb4_usuario') THEN ALTER TABLE futuro_bono_4ventas ADD CONSTRAINT fk_fb4_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fbr_usuario') THEN ALTER TABLE futuro_bono_reco ADD CONSTRAINT fk_fbr_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fbr_recomendado') THEN ALTER TABLE futuro_bono_reco ADD CONSTRAINT fk_fbr_recomendado FOREIGN KEY (id_usuario_recomendado) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones') AND NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcpc_vendedor_usuario') THEN ALTER TABLE futuro_contrato_promocion_condiciones ADD CONSTRAINT fk_fcpc_vendedor_usuario FOREIGN KEY (id_usuario_vendedor) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones') AND NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcpc_lider_usuario') THEN ALTER TABLE futuro_contrato_promocion_condiciones ADD CONSTRAINT fk_fcpc_lider_usuario FOREIGN KEY (id_usuario_lider) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_contrato_promocion_condiciones') AND NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fcpc_gerente_usuario') THEN ALTER TABLE futuro_contrato_promocion_condiciones ADD CONSTRAINT fk_fcpc_gerente_usuario FOREIGN KEY (id_usuario_gerente) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_abono_distribucion') AND NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_fad_usuario') THEN ALTER TABLE futuro_abono_distribucion ADD CONSTRAINT fk_fad_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cortes_pagos') AND NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_cortes_usuario') THEN ALTER TABLE cortes_pagos ADD CONSTRAINT fk_cortes_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT; END IF;
END$$
CALL usuarios_directorio_agregar_fks()$$
DROP PROCEDURE usuarios_directorio_agregar_fks$$
DELIMITER ;

SELECT
  (SELECT COUNT(*) FROM futuro_contratos) AS contratos,
  (SELECT COUNT(*) FROM futuro_abonos) AS abonos,
  (SELECT COALESCE(SUM(cant_abono),0) FROM futuro_abonos) AS total_abonos,
  (SELECT COUNT(*) FROM futuro_gestion) AS gestiones,
  (SELECT COUNT(*) FROM futuro_comision_semanal) AS comisiones_historicas,
  (SELECT COALESCE(SUM(cant_com_final),0) FROM futuro_comision_semanal) AS total_comisiones;
