-- Migraciones inventario y control de servicios
-- Ejecutar en orden; todas son idempotentes (IF NOT EXISTS) para evitar errores.

ALTER TABLE articulos 
  ADD COLUMN IF NOT EXISTS categoria VARCHAR(30) NOT NULL DEFAULT 'general' AFTER marca;

ALTER TABLE articulos
  ADD COLUMN IF NOT EXISTS id_proveedor INT NULL AFTER categoria;

ALTER TABLE articulos
  ADD CONSTRAINT fk_articulos_proveedores
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor) ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS articulos_movimientos (
  id_mov INT AUTO_INCREMENT PRIMARY KEY,
  id_articulo INT NOT NULL,
  tipo ENUM('entrada','salida','traspaso_out','traspaso_in') NOT NULL,
  cantidad INT NOT NULL,
  referencia VARCHAR(50) NULL,
  origen VARCHAR(50) NULL,
  destino VARCHAR(50) NULL,
  notas VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_articulo) REFERENCES articulos(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS articulos_traspasos (
  id_traspaso INT AUTO_INCREMENT PRIMARY KEY,
  origen VARCHAR(50) NOT NULL,
  destino VARCHAR(50) NOT NULL,
  responsable VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS articulos_traspaso_det (
  id_traspaso_det INT AUTO_INCREMENT PRIMARY KEY,
  id_traspaso INT NOT NULL,
  id_articulo INT NOT NULL,
  cantidad INT NOT NULL,
  FOREIGN KEY (id_traspaso) REFERENCES articulos_traspasos(id_traspaso) ON UPDATE CASCADE,
  FOREIGN KEY (id_articulo) REFERENCES articulos(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS articulos_salida_servicio (
  id_salida INT AUTO_INCREMENT PRIMARY KEY,
  id_servicio INT NOT NULL,
  id_articulo INT NOT NULL,
  cantidad INT NOT NULL,
  responsable VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON UPDATE CASCADE,
  FOREIGN KEY (id_articulo) REFERENCES articulos(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE cajas
  ADD COLUMN IF NOT EXISTS es_rentado TINYINT(1) NOT NULL DEFAULT 0 AFTER modelo,
  ADD COLUMN IF NOT EXISTS reciclado TINYINT(1) NOT NULL DEFAULT 0 AFTER es_rentado,
  ADD COLUMN IF NOT EXISTS ciclos_uso INT NOT NULL DEFAULT 0 AFTER reciclado,
  ADD COLUMN IF NOT EXISTS id_proveedor INT NULL AFTER proveedor;

ALTER TABLE cajas
  ADD CONSTRAINT fk_cajas_proveedores
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor) ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS cajas_movimientos (
  id_mov INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) NOT NULL,
  tipo ENUM('alta','asignacion','devolucion','baja') NOT NULL,
  id_servicio INT NULL,
  notas VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (codigo) REFERENCES cajas(codigo) ON UPDATE CASCADE,
  FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipos_movimientos (
  id_mov INT AUTO_INCREMENT PRIMARY KEY,
  id_equipo VARCHAR(50) NOT NULL,
  tipo ENUM('alta','asignacion','devolucion','baja','traspaso_out','traspaso_in') NOT NULL,
  id_servicio INT NULL,
  origen VARCHAR(50) NULL,
  destino VARCHAR(50) NULL,
  notas VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_equipo) REFERENCES equipos(id_equipo) ON UPDATE CASCADE,
  FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE servicios
  ADD COLUMN IF NOT EXISTS folio VARCHAR(30) NULL UNIQUE AFTER id_evento,
  ADD COLUMN IF NOT EXISTS tipo_disposicion ENUM('cremacion','inhumacion') NOT NULL DEFAULT 'inhumacion' AFTER tipo_servicio,
  ADD COLUMN IF NOT EXISTS contratante_nombre VARCHAR(100) NOT NULL DEFAULT '' AFTER auxiliares,
  ADD COLUMN IF NOT EXISTS contratante_tel VARCHAR(20) NULL AFTER contratante_nombre,
  ADD COLUMN IF NOT EXISTS contratante_email VARCHAR(100) NULL AFTER contratante_tel;

ALTER TABLE usuarios 
  MODIFY COLUMN perfil ENUM('admin','administradora','vendedor','auxiliar','cobrador') NOT NULL;

CREATE TABLE IF NOT EXISTS vendedor_pagos (
  id_pago INT AUTO_INCREMENT PRIMARY KEY,
  id_personal INT NOT NULL,
  id_contrato INT NOT NULL,
  monto FLOAT NOT NULL,
  concepto VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_personal) REFERENCES futuro_personal(id_personal) ON UPDATE CASCADE,
  FOREIGN KEY (id_contrato) REFERENCES futuro_contratos(id_contrato) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cortes_pago (
  id_corte INT AUTO_INCREMENT PRIMARY KEY,
  id_personal INT NOT NULL,
  periodo_inicio DATE NOT NULL,
  periodo_fin DATE NOT NULL,
  total FLOAT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_personal) REFERENCES futuro_personal(id_personal) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contrato_cambios_paquete (
  id_cambio INT AUTO_INCREMENT PRIMARY KEY,
  id_contrato INT NOT NULL,
  paquete_anterior VARCHAR(50) NOT NULL,
  paquete_nuevo VARCHAR(50) NOT NULL,
  id_personal INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_contrato) REFERENCES futuro_contratos(id_contrato) ON UPDATE CASCADE,
  FOREIGN KEY (id_personal) REFERENCES futuro_personal(id_personal) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comisiones_ajustes (
  id_ajuste INT AUTO_INCREMENT PRIMARY KEY,
  id_contrato INT NOT NULL,
  id_personal INT NOT NULL,
  monto FLOAT NOT NULL,
  motivo VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_contrato) REFERENCES futuro_contratos(id_contrato) ON UPDATE CASCADE,
  FOREIGN KEY (id_personal) REFERENCES futuro_personal(id_personal) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW vw_activos AS
SELECT 'articulo' AS tipo, a.id AS clave, a.articulo AS nombre, a.marca, a.existencias AS stock, NULL AS estado, NULL AS ubicacion
FROM articulos a
UNION ALL
SELECT 'caja', NULL, c.codigo, c.modelo, NULL, c.estado, c.ubicacion
FROM cajas c
UNION ALL
SELECT 'equipo', NULL, e.id_equipo, e.equipo, NULL, e.estatus, e.ubicacion
FROM equipos e;
