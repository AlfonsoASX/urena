-- @name: Asignar gerente a lideres sin jerarquia
-- @description: Asigna a je.ureña como gerente de los lideres de contratos activos que no tienen un jefe directo valido. Evita convertir al gerente en su propio jefe.
-- @backup: futuro_personal

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_gerente_jerarquia (
  id_personal INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_gerente_jerarquia (id_personal)
VALUES ((
  SELECT fp.id_personal
  FROM usuarios u
  JOIN futuro_personal fp ON fp.id=u.id
  WHERE u.usuario='je.ureña'
  LIMIT 1
));

UPDATE futuro_personal lider
JOIN (
  SELECT DISTINCT vendedor.jefe_directo AS id_lider
  FROM futuro_contratos contrato
  JOIN futuro_contrato_vendedor cv
    ON cv.id_cont_vend=(
      SELECT cv2.id_cont_vend
      FROM futuro_contrato_vendedor cv2
      WHERE cv2.id_contrato=contrato.id_contrato
      ORDER BY cv2.id_cont_vend DESC
      LIMIT 1
    )
  JOIN futuro_personal vendedor ON vendedor.id_personal=cv.id_personal
  LEFT JOIN futuro_contrato_promocion_condiciones condicion
    ON condicion.id_contrato=contrato.id_contrato
  WHERE UPPER(contrato.estatus)='ACTIVO'
    AND condicion.id_condicion IS NULL
    AND COALESCE(vendedor.jefe_directo,0)>0
) pendientes ON pendientes.id_lider=lider.id_personal
JOIN tmp_gerente_jerarquia gerente ON gerente.id_personal<>lider.id_personal
LEFT JOIN futuro_personal gerente_actual ON gerente_actual.id_personal=lider.jefe_directo
SET lider.jefe_directo=gerente.id_personal
WHERE gerente_actual.id_personal IS NULL;

SELECT lider.id_personal AS id_lider,
       TRIM(CONCAT(COALESCE(lider.nombre,''),' ',COALESCE(lider.apellido_p,''),' ',COALESCE(lider.apellido_m,''))) AS lider,
       gerente.id_personal AS id_gerente,
       TRIM(CONCAT(COALESCE(gerente.nombre,''),' ',COALESCE(gerente.apellido_p,''),' ',COALESCE(gerente.apellido_m,''))) AS gerente
FROM futuro_personal lider
JOIN futuro_personal gerente ON gerente.id_personal=lider.jefe_directo
JOIN tmp_gerente_jerarquia objetivo ON objetivo.id_personal=gerente.id_personal
ORDER BY lider.id_personal;

DROP TEMPORARY TABLE tmp_gerente_jerarquia;

COMMIT;
