-- Promociones por importes, condiciones congeladas y distribucion por abono.
-- Ejecutar una sola vez antes de publicar el codigo. Es idempotente.
-- @name: Reestructura de promociones, contratos y pagos
-- @description: Crea los planes economicos, congela condiciones por contrato y registra la distribucion de cada abono.
-- @backup: futuro_mostrar_catalogo_promociones,futuro_cont_cat_com,futuro_promocion_reglas

CREATE TABLE IF NOT EXISTS futuro_mostrar_catalogo_promociones (
  id_mos_cat_com INT(10) NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  concepto VARCHAR(250) NOT NULL,
  estatus INT(1) NOT NULL DEFAULT 1,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mos_cat_com),
  KEY idx_promociones_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS futuro_cont_cat_com (
  id_cont_cat_com INT(10) NOT NULL AUTO_INCREMENT,
  id_contrato INT(10) NOT NULL,
  id_mos_cat_com INT(10) NOT NULL,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cont_cat_com),
  KEY idx_futuro_cont_cat_com_contrato (id_contrato),
  KEY idx_futuro_cont_cat_com_promocion (id_mos_cat_com)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS futuro_promocion_reglas (
  id_regla INT(11) NOT NULL AUTO_INCREMENT,
  id_mos_cat_com INT(10) NOT NULL,
  pago_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
  gasto_administrativo DECIMAL(12,2) NOT NULL DEFAULT 0,
  comision_vendedor_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
  comision_vendedor_posterior DECIMAL(12,2) NOT NULL DEFAULT 0,
  comision_lider DECIMAL(12,2) NOT NULL DEFAULT 0,
  comision_gerente DECIMAL(12,2) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_regla),
  KEY idx_promocion_reglas_promocion (id_mos_cat_com)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
DROP PROCEDURE IF EXISTS migrar_promociones_planes_pagos$$
CREATE PROCEDURE migrar_promociones_planes_pagos()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='pago_inicial') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN pago_inicial DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER id_mos_cat_com;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='gasto_administrativo') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN gasto_administrativo DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER pago_inicial;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='gasto_papeleria') THEN
    UPDATE futuro_promocion_reglas SET gasto_administrativo=gasto_papeleria WHERE gasto_administrativo=0;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='comision_vendedor_inicial') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN comision_vendedor_inicial DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gasto_administrativo;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='comision_vendedor_posterior') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN comision_vendedor_posterior DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER comision_vendedor_inicial;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='comision_lider') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN comision_lider DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER comision_vendedor_posterior;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='comision_gerente') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN comision_gerente DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER comision_lider;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='activo') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='created_at') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='updated_at') THEN
    ALTER TABLE futuro_promocion_reglas ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
  END IF;

  UPDATE futuro_promocion_reglas r
  JOIN (SELECT id_mos_cat_com,MAX(id_regla) id_regla FROM futuro_promocion_reglas GROUP BY id_mos_cat_com) x
    ON x.id_mos_cat_com=r.id_mos_cat_com
  SET r.activo=IF(r.id_regla=x.id_regla,1,0);

  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND INDEX_NAME='uq_promocion_paquete') THEN
    ALTER TABLE futuro_promocion_reglas DROP INDEX uq_promocion_paquete;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND INDEX_NAME='idx_promocion_reglas_paquete') THEN
    ALTER TABLE futuro_promocion_reglas DROP INDEX idx_promocion_reglas_paquete;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND INDEX_NAME='idx_promocion_reglas_promocion') THEN
    ALTER TABLE futuro_promocion_reglas ADD INDEX idx_promocion_reglas_promocion (id_mos_cat_com);
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='tipo_contrato') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN tipo_contrato; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='vendedor_desde') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN vendedor_desde; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='vendedor_hasta') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN vendedor_hasta; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='supervisor_desde') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN supervisor_desde; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='supervisor_hasta') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN supervisor_hasta; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='empresa_desde') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN empresa_desde; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='gasto_papeleria') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN gasto_papeleria; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='futuro_promocion_reglas' AND COLUMN_NAME='descuento') THEN ALTER TABLE futuro_promocion_reglas DROP COLUMN descuento; END IF;
END$$
CALL migrar_promociones_planes_pagos()$$
DROP PROCEDURE migrar_promociones_planes_pagos$$
DELIMITER ;

CREATE TABLE IF NOT EXISTS futuro_contrato_promocion_condiciones (
  id_condicion INT(11) NOT NULL AUTO_INCREMENT,
  id_contrato INT(10) NOT NULL,
  id_mos_cat_com INT(10) NOT NULL,
  id_regla_origen INT(11) NOT NULL,
  pago_inicial DECIMAL(12,2) NOT NULL,
  gasto_administrativo DECIMAL(12,2) NOT NULL,
  comision_vendedor_inicial DECIMAL(12,2) NOT NULL,
  comision_vendedor_posterior DECIMAL(12,2) NOT NULL,
  comision_lider DECIMAL(12,2) NOT NULL,
  comision_gerente DECIMAL(12,2) NOT NULL,
  id_vendedor INT(10) NOT NULL,
  id_lider INT(10) NULL,
  id_gerente INT(10) NULL,
  id_abono_corte INT(10) NOT NULL DEFAULT 0,
  id_abono_inicial INT(10) NULL,
  es_migrado TINYINT(1) NOT NULL DEFAULT 0,
  fecha_vigencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_condicion),
  UNIQUE KEY uq_condicion_contrato (id_contrato),
  KEY idx_condicion_promocion (id_mos_cat_com),
  KEY idx_condicion_vendedor (id_vendedor),
  KEY idx_condicion_lider (id_lider),
  KEY idx_condicion_gerente (id_gerente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS futuro_abono_distribucion (
  id_distribucion BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_abono INT(10) NOT NULL,
  id_contrato INT(10) NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  etapa VARCHAR(20) NOT NULL,
  id_personal INT(10) NULL,
  monto DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_distribucion),
  KEY idx_distribucion_abono (id_abono),
  KEY idx_distribucion_contrato (id_contrato),
  KEY idx_distribucion_persona_fecha (id_personal,created_at),
  KEY idx_distribucion_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
DROP PROCEDURE IF EXISTS asegurar_plan_promocion$$
CREATE PROCEDURE asegurar_plan_promocion(
  IN p_codigo VARCHAR(20), IN p_concepto VARCHAR(250),
  IN p_inicial DECIMAL(12,2), IN p_admin DECIMAL(12,2),
  IN p_vendedor_inicial DECIMAL(12,2), IN p_vendedor_posterior DECIMAL(12,2),
  IN p_lider DECIMAL(12,2), IN p_gerente DECIMAL(12,2)
)
BEGIN
  DECLARE v_promocion INT DEFAULT NULL;
  DECLARE v_regla INT DEFAULT NULL;
  SELECT MIN(id_mos_cat_com) INTO v_promocion
  FROM futuro_mostrar_catalogo_promociones
  WHERE UPPER(TRIM(CONVERT(codigo USING utf8mb4))) COLLATE utf8mb4_general_ci
        = UPPER(TRIM(CONVERT(p_codigo USING utf8mb4))) COLLATE utf8mb4_general_ci;
  IF v_promocion IS NULL THEN
    INSERT INTO futuro_mostrar_catalogo_promociones (codigo,concepto,estatus) VALUES (p_codigo,p_concepto,1);
    SET v_promocion=LAST_INSERT_ID();
  END IF;
  SELECT MAX(id_regla) INTO v_regla FROM futuro_promocion_reglas WHERE id_mos_cat_com=v_promocion;
  IF v_regla IS NULL THEN
    INSERT INTO futuro_promocion_reglas (id_mos_cat_com,pago_inicial,gasto_administrativo,comision_vendedor_inicial,comision_vendedor_posterior,comision_lider,comision_gerente,activo)
    VALUES (v_promocion,p_inicial,p_admin,p_vendedor_inicial,p_vendedor_posterior,p_lider,p_gerente,1);
    SET v_regla=LAST_INSERT_ID();
  ELSEIF NOT EXISTS (
    SELECT 1 FROM futuro_promocion_reglas
    WHERE id_regla=v_regla AND pago_inicial>0
      AND pago_inicial=gasto_administrativo+comision_vendedor_inicial
  ) THEN
    UPDATE futuro_promocion_reglas SET pago_inicial=p_inicial,gasto_administrativo=p_admin,
      comision_vendedor_inicial=p_vendedor_inicial,comision_vendedor_posterior=p_vendedor_posterior,
      comision_lider=p_lider,comision_gerente=p_gerente,activo=1 WHERE id_regla=v_regla;
  END IF;
  UPDATE futuro_promocion_reglas SET activo=0 WHERE id_mos_cat_com=v_promocion AND id_regla<>v_regla;
END$$
CALL asegurar_plan_promocion('NORMAL','Normal',100,100,0,1200,100,300)$$
CALL asegurar_plan_promocion('PROMO300','Promocion 300',300,100,200,1000,100,200)$$
CALL asegurar_plan_promocion('PROMO500','Promocion 500',500,100,400,800,100,0)$$
CALL asegurar_plan_promocion('PROMO1000','Promocion 1000',1000,200,800,400,100,0)$$
DROP PROCEDURE asegurar_plan_promocion$$
DELIMITER ;

SELECT COUNT(*) promociones, (SELECT COUNT(*) FROM futuro_promocion_reglas) reglas,
       (SELECT COUNT(*) FROM futuro_contrato_promocion_condiciones) condiciones,
       (SELECT COUNT(*) FROM futuro_abono_distribucion) distribuciones
FROM futuro_mostrar_catalogo_promociones;
