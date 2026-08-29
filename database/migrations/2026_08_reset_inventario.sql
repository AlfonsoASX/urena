-- @name: Reinicio completo de inventario
-- @description: Elimina almacenes, articulos, equipos y ataudes, junto con sus asignaciones y movimientos, para recapturarlos desde cero. Conserva servicios, proveedores, compras, contratos y pagos.
-- @backup: almacenes,articulos,equipos,cajas,servicio_asignacion,servicio_articulo_evento,servicio_equipo_evento,servicio_caja_evento,servicio_articulos,kardex_articulos,articulos_movimientos,articulos_salida_servicio,articulos_traspaso_det,articulos_traspasos,articulos_vale_salida,servicio_equipo,equipos_movimientos,servicio_equipo_entrada,equipo_entrada,servicio_caja,renta_cajas,cajas_movimientos

DELIMITER $$
DROP PROCEDURE IF EXISTS reset_inventario_delete_table$$
CREATE PROCEDURE reset_inventario_delete_table(IN p_table VARCHAR(64))
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND TABLE_TYPE='BASE TABLE'
  ) THEN
    SET @reset_delete_sql=CONCAT('DELETE FROM `',REPLACE(p_table,'`','``'),'`');
    PREPARE reset_delete_stmt FROM @reset_delete_sql;
    EXECUTE reset_delete_stmt;
    DEALLOCATE PREPARE reset_delete_stmt;
  END IF;
END$$

DROP PROCEDURE IF EXISTS reset_inventario_clear_service_box$$
CREATE PROCEDURE reset_inventario_clear_service_box()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='servicio_asignacion' AND COLUMN_NAME='codigo_caja'
  ) THEN
    UPDATE servicio_asignacion SET codigo_caja=NULL WHERE codigo_caja IS NOT NULL;
  END IF;
END$$
DELIMITER ;

START TRANSACTION;

CALL reset_inventario_clear_service_box();

CALL reset_inventario_delete_table('servicio_articulo_evento');
CALL reset_inventario_delete_table('servicio_equipo_evento');
CALL reset_inventario_delete_table('servicio_caja_evento');

CALL reset_inventario_delete_table('articulos_traspaso_det');
CALL reset_inventario_delete_table('articulos_traspasos');
CALL reset_inventario_delete_table('articulos_vale_salida');
CALL reset_inventario_delete_table('articulos_salida_servicio');
CALL reset_inventario_delete_table('kardex_articulos');
CALL reset_inventario_delete_table('servicio_articulos');
CALL reset_inventario_delete_table('articulos_movimientos');

CALL reset_inventario_delete_table('servicio_equipo_entrada');
CALL reset_inventario_delete_table('equipo_entrada');
CALL reset_inventario_delete_table('servicio_equipo');
CALL reset_inventario_delete_table('equipos_movimientos');

CALL reset_inventario_delete_table('servicio_caja');
CALL reset_inventario_delete_table('renta_cajas');
CALL reset_inventario_delete_table('cajas_movimientos');

CALL reset_inventario_delete_table('articulos');
CALL reset_inventario_delete_table('equipos');
CALL reset_inventario_delete_table('cajas');
CALL reset_inventario_delete_table('almacenes');

COMMIT;

DELIMITER $$
DROP PROCEDURE IF EXISTS reset_inventario_clear_service_box$$
DROP PROCEDURE IF EXISTS reset_inventario_delete_table$$
DELIMITER ;

SELECT
  (SELECT COUNT(*) FROM almacenes) AS almacenes,
  (SELECT COUNT(*) FROM articulos) AS articulos,
  (SELECT COUNT(*) FROM equipos) AS equipos,
  (SELECT COUNT(*) FROM cajas) AS ataudes;
