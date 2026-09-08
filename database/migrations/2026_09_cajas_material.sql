-- @name: Material de ataudes
-- @description: Agrega a los ataudes el material metalico, madera o tapizado sin clasificar automaticamente registros existentes.
-- @backup: cajas

DELIMITER $$
DROP PROCEDURE IF EXISTS cajas_agregar_material$$
CREATE PROCEDURE cajas_agregar_material()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cajas' AND COLUMN_NAME='material'
  ) THEN
    ALTER TABLE cajas ADD COLUMN material VARCHAR(20) NULL DEFAULT NULL AFTER modelo;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cajas' AND INDEX_NAME='idx_cajas_material'
  ) THEN
    ALTER TABLE cajas ADD INDEX idx_cajas_material (material);
  END IF;
END$$
CALL cajas_agregar_material()$$
DROP PROCEDURE cajas_agregar_material$$
DELIMITER ;

SELECT material,COUNT(*) AS ataudes
FROM cajas
GROUP BY material
ORDER BY material;
