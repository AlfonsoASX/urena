-- @name: Zona horaria y cortes de cobradores
-- @description: Configura el soporte para un corte semanal recurrente y conserva la fecha local de abonos historicos capturados sin hora. La aplicacion establece UTC-06:00 en cada conexion.
-- @backup: futuro_abonos,futuro_corte_configuracion

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS futuro_corte_configuracion (
  id_configuracion TINYINT UNSIGNED NOT NULL,
  dia_semana TINYINT UNSIGNED NOT NULL,
  hora_corte TIME NOT NULL,
  actualizado_por INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_configuracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estos registros antiguos representan una fecha sin hora. Se desplazan en UTC
-- para que, al leerlos en UTC-06:00, conserven el mismo dia a las 00:00.
UPDATE futuro_abonos
SET fecha_registro = DATE_ADD(fecha_registro, INTERVAL 6 HOUR)
WHERE TIME(fecha_registro) = '00:00:00';

SELECT ROW_COUNT() AS abonos_de_fecha_corregidos;
