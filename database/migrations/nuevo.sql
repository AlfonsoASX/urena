-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: ganas001.mysql.guardedhost.com
-- Tiempo de generación: 26-12-2025 a las 19:43:33
-- Versión del servidor: 11.4.7-MariaDB-deb12
-- Versión de PHP: 8.3.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de datos: `ganas001_unitec`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulos`
--

DROP TABLE IF EXISTS `articulos`;
CREATE TABLE `articulos` (
  `id` int(11) NOT NULL,
  `articulo` varchar(50) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `existencias` int(10) NOT NULL,
  `foto` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulos_vale_salida`
--

DROP TABLE IF EXISTS `articulos_vale_salida`;
CREATE TABLE `articulos_vale_salida` (
  `id_art_vale` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `cantidad` int(10) NOT NULL,
  `id_vale` int(11) NOT NULL,
  `fecha` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajas`
--

DROP TABLE IF EXISTS `cajas`;
CREATE TABLE `cajas` (
  `codigo` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `estado` varchar(50) NOT NULL,
  `ubicacion` varchar(50) NOT NULL,
  `color` varchar(50) NOT NULL,
  `proveedor` varchar(50) NOT NULL,
  `costo` double NOT NULL,
  `foto` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cambio_ataud`
--

DROP TABLE IF EXISTS `cambio_ataud`;
CREATE TABLE `cambio_ataud` (
  `id_cambio` int(10) NOT NULL,
  `id_servicio` int(10) NOT NULL,
  `ataud_anterior` varchar(50) NOT NULL,
  `ataud_nuevo` varchar(50) NOT NULL,
  `fecha` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compra_articulos`
--

DROP TABLE IF EXISTS `compra_articulos`;
CREATE TABLE `compra_articulos` (
  `id_compra` int(11) NOT NULL,
  `articulo` varchar(50) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `cantidad` int(5) NOT NULL,
  `costo` double NOT NULL,
  `created_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `domicilios`
--

DROP TABLE IF EXISTS `domicilios`;
CREATE TABLE `domicilios` (
  `id_domicilio` int(10) NOT NULL,
  `municipio` varchar(50) NOT NULL,
  `colonia` varchar(50) NOT NULL,
  `calle` varchar(50) NOT NULL,
  `num_ext` int(10) NOT NULL,
  `num_int` varchar(5) NOT NULL,
  `entre_calle1` varchar(50) NOT NULL,
  `entre_calle2` varchar(50) NOT NULL,
  `tipo_dom` varchar(15) NOT NULL,
  `notas` varchar(250) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrada`
--

DROP TABLE IF EXISTS `entrada`;
CREATE TABLE `entrada` (
  `id_entrada` int(11) NOT NULL,
  `responsable` varchar(50) NOT NULL,
  `auxiliar` varchar(50) NOT NULL,
  `notas` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

DROP TABLE IF EXISTS `equipos`;
CREATE TABLE `equipos` (
  `id_equipo` varchar(50) NOT NULL,
  `equipo` varchar(50) NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `foto` varchar(50) DEFAULT NULL,
  `updated_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipo_entrada`
--

DROP TABLE IF EXISTS `equipo_entrada`;
CREATE TABLE `equipo_entrada` (
  `id_equipo_entrada` int(11) NOT NULL,
  `id_equipo` varchar(50) NOT NULL,
  `id_entrada` int(11) NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fallecido`
--

DROP TABLE IF EXISTS `fallecido`;
CREATE TABLE `fallecido` (
  `id_fallecido` int(10) NOT NULL,
  `nom_fallecido` varchar(100) NOT NULL,
  `dom_velacion` varchar(100) NOT NULL,
  `hospital` varchar(50) DEFAULT NULL,
  `municipio` varchar(100) NOT NULL,
  `fecha` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_abonos`
--

DROP TABLE IF EXISTS `futuro_abonos`;
CREATE TABLE `futuro_abonos` (
  `id_abono` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `saldo` double NOT NULL,
  `cant_abono` double NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_abonos_eliminados`
--

DROP TABLE IF EXISTS `futuro_abonos_eliminados`;
CREATE TABLE `futuro_abonos_eliminados` (
  `id_abonos_eliminados` int(10) NOT NULL,
  `id_abono` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `cant_abono` double NOT NULL,
  `id_usuario` int(10) NOT NULL,
  `fecha_abono` datetime NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_abono_cobrador`
--

DROP TABLE IF EXISTS `futuro_abono_cobrador`;
CREATE TABLE `futuro_abono_cobrador` (
  `id_abono_cobrador` int(10) NOT NULL,
  `id_abono` int(10) NOT NULL,
  `id_personal` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_ab_ie`
--

DROP TABLE IF EXISTS `futuro_ab_ie`;
CREATE TABLE `futuro_ab_ie` (
  `id_ab_ie` int(10) NOT NULL,
  `id_ie` int(10) NOT NULL,
  `id_abono` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_bono_4ventas`
--

DROP TABLE IF EXISTS `futuro_bono_4ventas`;
CREATE TABLE `futuro_bono_4ventas` (
  `id_bono_4ventas` int(10) NOT NULL,
  `contrato1` int(10) DEFAULT NULL,
  `contrato2` int(10) DEFAULT NULL,
  `contrato3` int(10) DEFAULT NULL,
  `contrato4` int(10) DEFAULT NULL,
  `id_personal` int(10) NOT NULL,
  `integridad` varchar(10) NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_bono_reco`
--

DROP TABLE IF EXISTS `futuro_bono_reco`;
CREATE TABLE `futuro_bono_reco` (
  `id_bono_reco` int(10) NOT NULL,
  `id_personal` int(10) NOT NULL,
  `recomendado` int(10) NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_comcob`
--

DROP TABLE IF EXISTS `futuro_comcob`;
CREATE TABLE `futuro_comcob` (
  `id_comcob` int(10) NOT NULL,
  `id_abono` int(10) NOT NULL,
  `cant_comcob` float NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_comision_semanal`
--

DROP TABLE IF EXISTS `futuro_comision_semanal`;
CREATE TABLE `futuro_comision_semanal` (
  `id_bono_sem` int(10) NOT NULL,
  `id_abono` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `cant_comision` float NOT NULL,
  `saldo_comision` float NOT NULL,
  `descuento` float NOT NULL,
  `cant_com_final` float NOT NULL,
  `comisionista` varchar(20) NOT NULL,
  `id_comisionista` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_comp_bono4v`
--

DROP TABLE IF EXISTS `futuro_comp_bono4v`;
CREATE TABLE `futuro_comp_bono4v` (
  `id_comp_bono4v` int(10) NOT NULL,
  `id_pago_bonocom` int(10) NOT NULL,
  `id_bono_4ventas` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_comp_bonoreco`
--

DROP TABLE IF EXISTS `futuro_comp_bonoreco`;
CREATE TABLE `futuro_comp_bonoreco` (
  `id_comp_bonoreco` int(10) NOT NULL,
  `id_pago_bonocom` int(10) NOT NULL,
  `id_bono_reco` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_comp_comcob`
--

DROP TABLE IF EXISTS `futuro_comp_comcob`;
CREATE TABLE `futuro_comp_comcob` (
  `id_comp_comcob` int(10) NOT NULL,
  `id_comcob` int(10) NOT NULL,
  `id_pago_comcob` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_comp_comsem`
--

DROP TABLE IF EXISTS `futuro_comp_comsem`;
CREATE TABLE `futuro_comp_comsem` (
  `id_comp_comsem` int(10) NOT NULL,
  `id_pago_bonocom` int(10) NOT NULL,
  `id_bono_sem` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_contratos`
--

DROP TABLE IF EXISTS `futuro_contratos`;
CREATE TABLE `futuro_contratos` (
  `id_contrato` int(10) NOT NULL,
  `tipo_contrato` varchar(50) NOT NULL,
  `tipo_pago` varchar(20) NOT NULL,
  `costo_contrato` float NOT NULL,
  `descuento` float NOT NULL,
  `costo_final` float NOT NULL,
  `periodo_pago` varchar(10) DEFAULT NULL,
  `compromiso_pago` float DEFAULT NULL,
  `estatus` varchar(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `porc_promotor` float NOT NULL,
  `porc_jefe_cuad` float NOT NULL,
  `porc_lider` float NOT NULL,
  `porc_empresa` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_contrato_cobrador`
--

DROP TABLE IF EXISTS `futuro_contrato_cobrador`;
CREATE TABLE `futuro_contrato_cobrador` (
  `id_cont_cob` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `id_personal` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_contrato_estatus`
--

DROP TABLE IF EXISTS `futuro_contrato_estatus`;
CREATE TABLE `futuro_contrato_estatus` (
  `id_contrato_estatus` int(10) NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `causa` varchar(250) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_contrato_jefecuad`
--

DROP TABLE IF EXISTS `futuro_contrato_jefecuad`;
CREATE TABLE `futuro_contrato_jefecuad` (
  `id_cont_jefecuad` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `id_personal` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_contrato_lider`
--

DROP TABLE IF EXISTS `futuro_contrato_lider`;
CREATE TABLE `futuro_contrato_lider` (
  `id_cont_lider` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `id_personal` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_contrato_vendedor`
--

DROP TABLE IF EXISTS `futuro_contrato_vendedor`;
CREATE TABLE `futuro_contrato_vendedor` (
  `id_cont_vend` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `id_personal` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_cont_cat_com`
--

DROP TABLE IF EXISTS `futuro_cont_cat_com`;
CREATE TABLE `futuro_cont_cat_com` (
  `id_cont_cat_com` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `id_mos_cat_com` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_ingresos_egresos`
--

DROP TABLE IF EXISTS `futuro_ingresos_egresos`;
CREATE TABLE `futuro_ingresos_egresos` (
  `id_ie` int(10) NOT NULL,
  `ingresos` double NOT NULL,
  `egresos` double NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_logs`
--

DROP TABLE IF EXISTS `futuro_logs`;
CREATE TABLE `futuro_logs` (
  `id_log` int(10) NOT NULL,
  `nom_tabla` varchar(50) NOT NULL,
  `nom_campo` varchar(50) NOT NULL,
  `valor_anterior` varchar(100) NOT NULL,
  `valor_nuevo` varchar(100) NOT NULL,
  `id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_mostrar_catalogo_promociones`
--

DROP TABLE IF EXISTS `futuro_mostrar_catalogo_promociones`;
CREATE TABLE `futuro_mostrar_catalogo_promociones` (
  `id_mos_cat_com` int(10) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `concepto` varchar(250) NOT NULL,
  `estatus` int(1) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_nivel_ventas`
--

DROP TABLE IF EXISTS `futuro_nivel_ventas`;
CREATE TABLE `futuro_nivel_ventas` (
  `id_nivel_ventas` int(10) NOT NULL,
  `nom_nivel_ventas` varchar(20) NOT NULL,
  `descripcion` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_pago_bonocom`
--

DROP TABLE IF EXISTS `futuro_pago_bonocom`;
CREATE TABLE `futuro_pago_bonocom` (
  `id_pago_bonocom` int(10) NOT NULL,
  `total` float NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_pago_comcob`
--

DROP TABLE IF EXISTS `futuro_pago_comcob`;
CREATE TABLE `futuro_pago_comcob` (
  `id_pago_comcob` int(10) NOT NULL,
  `total` float NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_pbc_ie`
--

DROP TABLE IF EXISTS `futuro_pbc_ie`;
CREATE TABLE `futuro_pbc_ie` (
  `id_pbc_ie` int(10) NOT NULL,
  `id_ie` int(10) NOT NULL,
  `id_pago_bonocom` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_pcc_ie`
--

DROP TABLE IF EXISTS `futuro_pcc_ie`;
CREATE TABLE `futuro_pcc_ie` (
  `id_pcc_ie` int(10) NOT NULL,
  `id_ie` int(10) NOT NULL,
  `id_pago_comcob` int(10) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_personal`
--

DROP TABLE IF EXISTS `futuro_personal`;
CREATE TABLE `futuro_personal` (
  `id_personal` int(10) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido_p` varchar(50) NOT NULL,
  `apellido_m` varchar(50) NOT NULL,
  `tel_casa` bigint(10) NOT NULL,
  `tel_cel` bigint(10) NOT NULL,
  `fecha_ing` date NOT NULL,
  `id_puesto` int(10) NOT NULL,
  `id_nivel_ventas` int(10) NOT NULL,
  `jefe_directo` int(10) NOT NULL,
  `recomendante` int(10) DEFAULT NULL,
  `foto` varchar(50) DEFAULT NULL,
  `estatus` varchar(10) NOT NULL,
  `fecha_reg` timestamp NOT NULL DEFAULT current_timestamp(),
  `contratos` int(5) DEFAULT NULL,
  `notas` varchar(255) NOT NULL,
  `id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `futuro_puestos`
--

DROP TABLE IF EXISTS `futuro_puestos`;
CREATE TABLE `futuro_puestos` (
  `id_puesto` int(10) NOT NULL,
  `nom_puesto` varchar(20) NOT NULL,
  `des_puesto` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

DROP TABLE IF EXISTS `servicios`;
CREATE TABLE `servicios` (
  `id_servicio` int(10) NOT NULL,
  `id_evento` varchar(50) DEFAULT NULL,
  `tipo_servicio` varchar(50) NOT NULL,
  `tipo_venta` varchar(50) NOT NULL,
  `velas` int(2) NOT NULL,
  `despensa` int(2) NOT NULL,
  `notas` varchar(255) NOT NULL,
  `responsable` varchar(50) NOT NULL,
  `auxiliares` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_caja`
--

DROP TABLE IF EXISTS `servicio_caja`;
CREATE TABLE `servicio_caja` (
  `id_serv_cod` int(11) NOT NULL,
  `id_servicio` int(10) NOT NULL,
  `codigo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_equipo`
--

DROP TABLE IF EXISTS `servicio_equipo`;
CREATE TABLE `servicio_equipo` (
  `id_serv_eq` int(11) NOT NULL,
  `id_servicio` int(10) NOT NULL,
  `id_equipo` varchar(50) NOT NULL,
  `fecha` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_equipo_entrada`
--

DROP TABLE IF EXISTS `servicio_equipo_entrada`;
CREATE TABLE `servicio_equipo_entrada` (
  `id_serv_eq_ent` int(11) NOT NULL,
  `id_equipo` varchar(10) NOT NULL,
  `auxiliar` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_fallecido`
--

DROP TABLE IF EXISTS `servicio_fallecido`;
CREATE TABLE `servicio_fallecido` (
  `id_falle_serv` int(10) NOT NULL,
  `id_fallecido` int(10) NOT NULL,
  `id_servicio` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tablero_actividades`
--

DROP TABLE IF EXISTS `tablero_actividades`;
CREATE TABLE `tablero_actividades` (
  `id_actividad` int(11) NOT NULL,
  `descripcion` varchar(250) NOT NULL,
  `estatus` varchar(10) NOT NULL,
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tablero_asignaciones`
--

DROP TABLE IF EXISTS `tablero_asignaciones`;
CREATE TABLE `tablero_asignaciones` (
  `id_asignacion` int(11) NOT NULL,
  `asignado_por` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tablero_lecturas`
--

DROP TABLE IF EXISTS `tablero_lecturas`;
CREATE TABLE `tablero_lecturas` (
  `id_lectura` int(11) NOT NULL,
  `id_nota` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tablero_notas`
--

DROP TABLE IF EXISTS `tablero_notas`;
CREATE TABLE `tablero_notas` (
  `id_nota` int(11) NOT NULL,
  `descripcion` varchar(250) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telefonos`
--

DROP TABLE IF EXISTS `telefonos`;
CREATE TABLE `telefonos` (
  `id_tel` int(10) NOT NULL,
  `numero` varchar(10) NOT NULL,
  `tipo_tel` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `titulares`
--

DROP TABLE IF EXISTS `titulares`;
CREATE TABLE `titulares` (
  `id_titular` int(10) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido_p` varchar(50) NOT NULL,
  `apellido_m` varchar(50) NOT NULL,
  `foto` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `titular_contrato`
--

DROP TABLE IF EXISTS `titular_contrato`;
CREATE TABLE `titular_contrato` (
  `id_titular_contrato` int(10) NOT NULL,
  `id_titular` int(10) NOT NULL,
  `id_contrato` int(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `titular_dom`
--

DROP TABLE IF EXISTS `titular_dom`;
CREATE TABLE `titular_dom` (
  `id_titular_dom` int(10) NOT NULL,
  `id_titular` int(10) NOT NULL,
  `id_domicilio` int(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `titular_tels`
--

DROP TABLE IF EXISTS `titular_tels`;
CREATE TABLE `titular_tels` (
  `id_titular_tels` int(10) NOT NULL,
  `id_titular` int(10) NOT NULL,
  `id_tel` int(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `perfil` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `es_super` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_roles`
--

DROP TABLE IF EXISTS `usuarios_roles`;
CREATE TABLE `usuarios_roles` (
  `usuario_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles_permisos`
--

DROP TABLE IF EXISTS `roles_permisos`;
CREATE TABLE `roles_permisos` (
  `rol_id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vales_salida`
--

DROP TABLE IF EXISTS `vales_salida`;
CREATE TABLE `vales_salida` (
  `id_vale` int(11) NOT NULL,
  `responsable` varchar(100) NOT NULL,
  `solicitante` varchar(100) NOT NULL,
  `fecha` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `articulos`
--
ALTER TABLE `articulos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `articulos_vale_salida`
--
ALTER TABLE `articulos_vale_salida`
  ADD PRIMARY KEY (`id_art_vale`),
  ADD KEY `id` (`id`,`id_vale`),
  ADD KEY `id_vale` (`id_vale`);

--
-- Indices de la tabla `cajas`
--
ALTER TABLE `cajas`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `cambio_ataud`
--
ALTER TABLE `cambio_ataud`
  ADD PRIMARY KEY (`id_cambio`),
  ADD KEY `id_servicio` (`id_servicio`);

--
-- Indices de la tabla `compra_articulos`
--
ALTER TABLE `compra_articulos`
  ADD PRIMARY KEY (`id_compra`);

--
-- Indices de la tabla `domicilios`
--
ALTER TABLE `domicilios`
  ADD PRIMARY KEY (`id_domicilio`);

--
-- Indices de la tabla `entrada`
--
ALTER TABLE `entrada`
  ADD PRIMARY KEY (`id_entrada`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`);

--
-- Indices de la tabla `equipo_entrada`
--
ALTER TABLE `equipo_entrada`
  ADD PRIMARY KEY (`id_equipo_entrada`),
  ADD KEY `id_equipo` (`id_equipo`),
  ADD KEY `id_entrada` (`id_entrada`);

--
-- Indices de la tabla `fallecido`
--
ALTER TABLE `fallecido`
  ADD PRIMARY KEY (`id_fallecido`);

--
-- Indices de la tabla `futuro_abonos`
--
ALTER TABLE `futuro_abonos`
  ADD PRIMARY KEY (`id_abono`),
  ADD KEY `id_contrato` (`id_contrato`);

--
-- Indices de la tabla `futuro_abonos_eliminados`
--
ALTER TABLE `futuro_abonos_eliminados`
  ADD PRIMARY KEY (`id_abonos_eliminados`);

--
-- Indices de la tabla `futuro_abono_cobrador`
--
ALTER TABLE `futuro_abono_cobrador`
  ADD PRIMARY KEY (`id_abono_cobrador`),
  ADD KEY `id_abono` (`id_abono`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_ab_ie`
--
ALTER TABLE `futuro_ab_ie`
  ADD PRIMARY KEY (`id_ab_ie`),
  ADD KEY `id_ie` (`id_ie`),
  ADD KEY `id_abono` (`id_abono`);

--
-- Indices de la tabla `futuro_bono_4ventas`
--
ALTER TABLE `futuro_bono_4ventas`
  ADD PRIMARY KEY (`id_bono_4ventas`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_bono_reco`
--
ALTER TABLE `futuro_bono_reco`
  ADD PRIMARY KEY (`id_bono_reco`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_comcob`
--
ALTER TABLE `futuro_comcob`
  ADD PRIMARY KEY (`id_comcob`),
  ADD KEY `id_abono` (`id_abono`);

--
-- Indices de la tabla `futuro_comision_semanal`
--
ALTER TABLE `futuro_comision_semanal`
  ADD PRIMARY KEY (`id_bono_sem`),
  ADD KEY `id_abono` (`id_abono`),
  ADD KEY `id_contrato` (`id_contrato`);

--
-- Indices de la tabla `futuro_comp_bono4v`
--
ALTER TABLE `futuro_comp_bono4v`
  ADD PRIMARY KEY (`id_comp_bono4v`),
  ADD KEY `id_pago_bonocom` (`id_pago_bonocom`),
  ADD KEY `id_bono_4ventas` (`id_bono_4ventas`);

--
-- Indices de la tabla `futuro_comp_bonoreco`
--
ALTER TABLE `futuro_comp_bonoreco`
  ADD PRIMARY KEY (`id_comp_bonoreco`),
  ADD KEY `id_pago_bonocom` (`id_pago_bonocom`),
  ADD KEY `id_bono_reco` (`id_bono_reco`);

--
-- Indices de la tabla `futuro_comp_comcob`
--
ALTER TABLE `futuro_comp_comcob`
  ADD PRIMARY KEY (`id_comp_comcob`),
  ADD KEY `id_comcob` (`id_comcob`),
  ADD KEY `id_pago_comcob` (`id_pago_comcob`);

--
-- Indices de la tabla `futuro_comp_comsem`
--
ALTER TABLE `futuro_comp_comsem`
  ADD PRIMARY KEY (`id_comp_comsem`),
  ADD KEY `id_pago_bonocom` (`id_pago_bonocom`),
  ADD KEY `id_bono_sem` (`id_bono_sem`);

--
-- Indices de la tabla `futuro_contratos`
--
ALTER TABLE `futuro_contratos`
  ADD PRIMARY KEY (`id_contrato`);

--
-- Indices de la tabla `futuro_contrato_cobrador`
--
ALTER TABLE `futuro_contrato_cobrador`
  ADD PRIMARY KEY (`id_cont_cob`),
  ADD KEY `id_contrato` (`id_contrato`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_contrato_estatus`
--
ALTER TABLE `futuro_contrato_estatus`
  ADD PRIMARY KEY (`id_contrato_estatus`),
  ADD KEY `id_contrato` (`id_contrato`);

--
-- Indices de la tabla `futuro_contrato_jefecuad`
--
ALTER TABLE `futuro_contrato_jefecuad`
  ADD PRIMARY KEY (`id_cont_jefecuad`),
  ADD KEY `id_contrato` (`id_contrato`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_contrato_lider`
--
ALTER TABLE `futuro_contrato_lider`
  ADD PRIMARY KEY (`id_cont_lider`),
  ADD KEY `id_contrato` (`id_contrato`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_contrato_vendedor`
--
ALTER TABLE `futuro_contrato_vendedor`
  ADD PRIMARY KEY (`id_cont_vend`),
  ADD KEY `id_contrato` (`id_contrato`),
  ADD KEY `id_personal` (`id_personal`);

--
-- Indices de la tabla `futuro_cont_cat_com`
--
ALTER TABLE `futuro_cont_cat_com`
  ADD PRIMARY KEY (`id_cont_cat_com`),
  ADD KEY `id_contrato` (`id_contrato`),
  ADD KEY `id_com_cat_com` (`id_mos_cat_com`);

--
-- Indices de la tabla `futuro_ingresos_egresos`
--
ALTER TABLE `futuro_ingresos_egresos`
  ADD PRIMARY KEY (`id_ie`);

--
-- Indices de la tabla `futuro_logs`
--
ALTER TABLE `futuro_logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id` (`id`);

--
-- Indices de la tabla `futuro_mostrar_catalogo_promociones`
--
ALTER TABLE `futuro_mostrar_catalogo_promociones`
  ADD PRIMARY KEY (`id_mos_cat_com`);

--
-- Indices de la tabla `futuro_nivel_ventas`
--
ALTER TABLE `futuro_nivel_ventas`
  ADD PRIMARY KEY (`id_nivel_ventas`);

--
-- Indices de la tabla `futuro_pago_bonocom`
--
ALTER TABLE `futuro_pago_bonocom`
  ADD PRIMARY KEY (`id_pago_bonocom`);

--
-- Indices de la tabla `futuro_pago_comcob`
--
ALTER TABLE `futuro_pago_comcob`
  ADD PRIMARY KEY (`id_pago_comcob`);

--
-- Indices de la tabla `futuro_pbc_ie`
--
ALTER TABLE `futuro_pbc_ie`
  ADD PRIMARY KEY (`id_pbc_ie`),
  ADD KEY `id_ie` (`id_ie`),
  ADD KEY `id_pago_bonocom` (`id_pago_bonocom`);

--
-- Indices de la tabla `futuro_pcc_ie`
--
ALTER TABLE `futuro_pcc_ie`
  ADD PRIMARY KEY (`id_pcc_ie`),
  ADD KEY `id_ie` (`id_ie`),
  ADD KEY `id_pago_comcob` (`id_pago_comcob`);

--
-- Indices de la tabla `futuro_personal`
--
ALTER TABLE `futuro_personal`
  ADD PRIMARY KEY (`id_personal`),
  ADD KEY `id_puesto` (`id_puesto`),
  ADD KEY `id_nivel_ventas` (`id_nivel_ventas`),
  ADD KEY `id` (`id`);

--
-- Indices de la tabla `futuro_puestos`
--
ALTER TABLE `futuro_puestos`
  ADD PRIMARY KEY (`id_puesto`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`);

--
-- Indices de la tabla `servicio_caja`
--
ALTER TABLE `servicio_caja`
  ADD PRIMARY KEY (`id_serv_cod`),
  ADD KEY `id_servicio` (`id_servicio`,`codigo`),
  ADD KEY `codigo` (`codigo`);

--
-- Indices de la tabla `servicio_equipo`
--
ALTER TABLE `servicio_equipo`
  ADD PRIMARY KEY (`id_serv_eq`),
  ADD KEY `id_servicio` (`id_servicio`,`id_equipo`),
  ADD KEY `id_equipo` (`id_equipo`);

--
-- Indices de la tabla `servicio_equipo_entrada`
--
ALTER TABLE `servicio_equipo_entrada`
  ADD PRIMARY KEY (`id_serv_eq_ent`);

--
-- Indices de la tabla `servicio_fallecido`
--
ALTER TABLE `servicio_fallecido`
  ADD PRIMARY KEY (`id_falle_serv`),
  ADD KEY `id_fallecido` (`id_fallecido`,`id_servicio`),
  ADD KEY `id_servicio` (`id_servicio`);

--
-- Indices de la tabla `tablero_actividades`
--
ALTER TABLE `tablero_actividades`
  ADD PRIMARY KEY (`id_actividad`),
  ADD KEY `id_usuario` (`id`);

--
-- Indices de la tabla `tablero_asignaciones`
--
ALTER TABLE `tablero_asignaciones`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD KEY `id_usuario` (`id`,`id_actividad`),
  ADD KEY `id_actividad` (`id_actividad`);

--
-- Indices de la tabla `tablero_lecturas`
--
ALTER TABLE `tablero_lecturas`
  ADD PRIMARY KEY (`id_lectura`),
  ADD KEY `id_nota` (`id_nota`,`id`),
  ADD KEY `id_usuario` (`id`);

--
-- Indices de la tabla `tablero_notas`
--
ALTER TABLE `tablero_notas`
  ADD PRIMARY KEY (`id_nota`),
  ADD KEY `id_actividad` (`id_actividad`,`id`),
  ADD KEY `id_usuario` (`id`);

--
-- Indices de la tabla `telefonos`
--
ALTER TABLE `telefonos`
  ADD PRIMARY KEY (`id_tel`);

--
-- Indices de la tabla `titulares`
--
ALTER TABLE `titulares`
  ADD PRIMARY KEY (`id_titular`);

--
-- Indices de la tabla `titular_contrato`
--
ALTER TABLE `titular_contrato`
  ADD PRIMARY KEY (`id_titular_contrato`),
  ADD KEY `id_titular` (`id_titular`),
  ADD KEY `id_contrato` (`id_contrato`);

--
-- Indices de la tabla `titular_dom`
--
ALTER TABLE `titular_dom`
  ADD PRIMARY KEY (`id_titular_dom`),
  ADD KEY `id_titular` (`id_titular`),
  ADD KEY `id_domicilio` (`id_domicilio`);

--
-- Indices de la tabla `titular_tels`
--
ALTER TABLE `titular_tels`
  ADD PRIMARY KEY (`id_titular_tels`),
  ADD KEY `id_titular` (`id_titular`),
  ADD KEY `id_tel` (`id_tel`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_slug` (`slug`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_permisos_clave` (`clave`);

--
-- Indices de la tabla `usuarios_roles`
--
ALTER TABLE `usuarios_roles`
  ADD PRIMARY KEY (`usuario_id`,`rol_id`),
  ADD KEY `idx_ur_rol` (`rol_id`);

--
-- Indices de la tabla `roles_permisos`
--
ALTER TABLE `roles_permisos`
  ADD PRIMARY KEY (`rol_id`,`permiso_id`),
  ADD KEY `idx_rp_permiso` (`permiso_id`);

--
-- Indices de la tabla `vales_salida`
--
ALTER TABLE `vales_salida`
  ADD PRIMARY KEY (`id_vale`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `articulos`
--
ALTER TABLE `articulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `articulos_vale_salida`
--
ALTER TABLE `articulos_vale_salida`
  MODIFY `id_art_vale` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cambio_ataud`
--
ALTER TABLE `cambio_ataud`
  MODIFY `id_cambio` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compra_articulos`
--
ALTER TABLE `compra_articulos`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `domicilios`
--
ALTER TABLE `domicilios`
  MODIFY `id_domicilio` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `entrada`
--
ALTER TABLE `entrada`
  MODIFY `id_entrada` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `equipo_entrada`
--
ALTER TABLE `equipo_entrada`
  MODIFY `id_equipo_entrada` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fallecido`
--
ALTER TABLE `fallecido`
  MODIFY `id_fallecido` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_abonos`
--
ALTER TABLE `futuro_abonos`
  MODIFY `id_abono` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_abonos_eliminados`
--
ALTER TABLE `futuro_abonos_eliminados`
  MODIFY `id_abonos_eliminados` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_abono_cobrador`
--
ALTER TABLE `futuro_abono_cobrador`
  MODIFY `id_abono_cobrador` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_ab_ie`
--
ALTER TABLE `futuro_ab_ie`
  MODIFY `id_ab_ie` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_bono_4ventas`
--
ALTER TABLE `futuro_bono_4ventas`
  MODIFY `id_bono_4ventas` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_bono_reco`
--
ALTER TABLE `futuro_bono_reco`
  MODIFY `id_bono_reco` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_comcob`
--
ALTER TABLE `futuro_comcob`
  MODIFY `id_comcob` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_comision_semanal`
--
ALTER TABLE `futuro_comision_semanal`
  MODIFY `id_bono_sem` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_comp_bono4v`
--
ALTER TABLE `futuro_comp_bono4v`
  MODIFY `id_comp_bono4v` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_comp_bonoreco`
--
ALTER TABLE `futuro_comp_bonoreco`
  MODIFY `id_comp_bonoreco` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_comp_comcob`
--
ALTER TABLE `futuro_comp_comcob`
  MODIFY `id_comp_comcob` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_comp_comsem`
--
ALTER TABLE `futuro_comp_comsem`
  MODIFY `id_comp_comsem` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_contratos`
--
ALTER TABLE `futuro_contratos`
  MODIFY `id_contrato` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_contrato_cobrador`
--
ALTER TABLE `futuro_contrato_cobrador`
  MODIFY `id_cont_cob` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_contrato_estatus`
--
ALTER TABLE `futuro_contrato_estatus`
  MODIFY `id_contrato_estatus` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_contrato_jefecuad`
--
ALTER TABLE `futuro_contrato_jefecuad`
  MODIFY `id_cont_jefecuad` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_contrato_lider`
--
ALTER TABLE `futuro_contrato_lider`
  MODIFY `id_cont_lider` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_contrato_vendedor`
--
ALTER TABLE `futuro_contrato_vendedor`
  MODIFY `id_cont_vend` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_cont_cat_com`
--
ALTER TABLE `futuro_cont_cat_com`
  MODIFY `id_cont_cat_com` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_ingresos_egresos`
--
ALTER TABLE `futuro_ingresos_egresos`
  MODIFY `id_ie` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_logs`
--
ALTER TABLE `futuro_logs`
  MODIFY `id_log` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_mostrar_catalogo_promociones`
--
ALTER TABLE `futuro_mostrar_catalogo_promociones`
  MODIFY `id_mos_cat_com` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_nivel_ventas`
--
ALTER TABLE `futuro_nivel_ventas`
  MODIFY `id_nivel_ventas` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_pago_bonocom`
--
ALTER TABLE `futuro_pago_bonocom`
  MODIFY `id_pago_bonocom` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_pago_comcob`
--
ALTER TABLE `futuro_pago_comcob`
  MODIFY `id_pago_comcob` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_pbc_ie`
--
ALTER TABLE `futuro_pbc_ie`
  MODIFY `id_pbc_ie` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_pcc_ie`
--
ALTER TABLE `futuro_pcc_ie`
  MODIFY `id_pcc_ie` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_personal`
--
ALTER TABLE `futuro_personal`
  MODIFY `id_personal` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `futuro_puestos`
--
ALTER TABLE `futuro_puestos`
  MODIFY `id_puesto` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicio_caja`
--
ALTER TABLE `servicio_caja`
  MODIFY `id_serv_cod` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicio_equipo`
--
ALTER TABLE `servicio_equipo`
  MODIFY `id_serv_eq` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicio_equipo_entrada`
--
ALTER TABLE `servicio_equipo_entrada`
  MODIFY `id_serv_eq_ent` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicio_fallecido`
--
ALTER TABLE `servicio_fallecido`
  MODIFY `id_falle_serv` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tablero_actividades`
--
ALTER TABLE `tablero_actividades`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tablero_asignaciones`
--
ALTER TABLE `tablero_asignaciones`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tablero_lecturas`
--
ALTER TABLE `tablero_lecturas`
  MODIFY `id_lectura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tablero_notas`
--
ALTER TABLE `tablero_notas`
  MODIFY `id_nota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telefonos`
--
ALTER TABLE `telefonos`
  MODIFY `id_tel` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `titulares`
--
ALTER TABLE `titulares`
  MODIFY `id_titular` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `titular_contrato`
--
ALTER TABLE `titular_contrato`
  MODIFY `id_titular_contrato` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `titular_dom`
--
ALTER TABLE `titular_dom`
  MODIFY `id_titular_dom` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `titular_tels`
--
ALTER TABLE `titular_tels`
  MODIFY `id_titular_tels` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vales_salida`
--
ALTER TABLE `vales_salida`
  MODIFY `id_vale` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `articulos_vale_salida`
--
ALTER TABLE `articulos_vale_salida`
  ADD CONSTRAINT `articulos_vale_salida_ibfk_1` FOREIGN KEY (`id_vale`) REFERENCES `vales_salida` (`id_vale`) ON UPDATE CASCADE,
  ADD CONSTRAINT `articulos_vale_salida_ibfk_2` FOREIGN KEY (`id`) REFERENCES `articulos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cambio_ataud`
--
ALTER TABLE `cambio_ataud`
  ADD CONSTRAINT `cambio_ataud_ibfk_1` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios_roles`
--
ALTER TABLE `usuarios_roles`
  ADD CONSTRAINT `fk_ur_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;

--
-- Filtros para la tabla `roles_permisos`
--
ALTER TABLE `roles_permisos`
  ADD CONSTRAINT `fk_rp_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;

--
-- Filtros para la tabla `equipo_entrada`
--
ALTER TABLE `equipo_entrada`
  ADD CONSTRAINT `equipo_entrada_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON UPDATE CASCADE,
  ADD CONSTRAINT `equipo_entrada_ibfk_2` FOREIGN KEY (`id_entrada`) REFERENCES `entrada` (`id_entrada`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_abonos`
--
ALTER TABLE `futuro_abonos`
  ADD CONSTRAINT `futuro_abonos_ibfk_1` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_abono_cobrador`
--
ALTER TABLE `futuro_abono_cobrador`
  ADD CONSTRAINT `futuro_abono_cobrador_ibfk_1` FOREIGN KEY (`id_abono`) REFERENCES `futuro_abonos` (`id_abono`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_abono_cobrador_ibfk_2` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_ab_ie`
--
ALTER TABLE `futuro_ab_ie`
  ADD CONSTRAINT `futuro_ab_ie_ibfk_1` FOREIGN KEY (`id_ie`) REFERENCES `futuro_ingresos_egresos` (`id_ie`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_ab_ie_ibfk_2` FOREIGN KEY (`id_abono`) REFERENCES `futuro_abonos` (`id_abono`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_bono_4ventas`
--
ALTER TABLE `futuro_bono_4ventas`
  ADD CONSTRAINT `futuro_bono_4ventas_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_bono_reco`
--
ALTER TABLE `futuro_bono_reco`
  ADD CONSTRAINT `futuro_bono_reco_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_comcob`
--
ALTER TABLE `futuro_comcob`
  ADD CONSTRAINT `futuro_comcob_ibfk_1` FOREIGN KEY (`id_abono`) REFERENCES `futuro_abonos` (`id_abono`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_comision_semanal`
--
ALTER TABLE `futuro_comision_semanal`
  ADD CONSTRAINT `futuro_comision_semanal_ibfk_1` FOREIGN KEY (`id_abono`) REFERENCES `futuro_abonos` (`id_abono`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_comision_semanal_ibfk_2` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_comp_bono4v`
--
ALTER TABLE `futuro_comp_bono4v`
  ADD CONSTRAINT `futuro_comp_bono4v_ibfk_1` FOREIGN KEY (`id_pago_bonocom`) REFERENCES `futuro_pago_bonocom` (`id_pago_bonocom`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_comp_bono4v_ibfk_2` FOREIGN KEY (`id_bono_4ventas`) REFERENCES `futuro_bono_4ventas` (`id_bono_4ventas`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_comp_bonoreco`
--
ALTER TABLE `futuro_comp_bonoreco`
  ADD CONSTRAINT `futuro_comp_bonoreco_ibfk_1` FOREIGN KEY (`id_pago_bonocom`) REFERENCES `futuro_pago_bonocom` (`id_pago_bonocom`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_comp_bonoreco_ibfk_2` FOREIGN KEY (`id_bono_reco`) REFERENCES `futuro_bono_reco` (`id_bono_reco`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_comp_comcob`
--
ALTER TABLE `futuro_comp_comcob`
  ADD CONSTRAINT `futuro_comp_comcob_ibfk_1` FOREIGN KEY (`id_comcob`) REFERENCES `futuro_comcob` (`id_comcob`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_comp_comcob_ibfk_2` FOREIGN KEY (`id_pago_comcob`) REFERENCES `futuro_pago_comcob` (`id_pago_comcob`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_comp_comsem`
--
ALTER TABLE `futuro_comp_comsem`
  ADD CONSTRAINT `futuro_comp_comsem_ibfk_1` FOREIGN KEY (`id_pago_bonocom`) REFERENCES `futuro_pago_bonocom` (`id_pago_bonocom`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_comp_comsem_ibfk_2` FOREIGN KEY (`id_bono_sem`) REFERENCES `futuro_comision_semanal` (`id_bono_sem`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_contrato_cobrador`
--
ALTER TABLE `futuro_contrato_cobrador`
  ADD CONSTRAINT `futuro_contrato_cobrador_ibfk_1` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_contrato_cobrador_ibfk_2` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_contrato_estatus`
--
ALTER TABLE `futuro_contrato_estatus`
  ADD CONSTRAINT `futuro_contrato_estatus_ibfk_1` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_contrato_jefecuad`
--
ALTER TABLE `futuro_contrato_jefecuad`
  ADD CONSTRAINT `futuro_contrato_jefecuad_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_contrato_jefecuad_ibfk_2` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_contrato_lider`
--
ALTER TABLE `futuro_contrato_lider`
  ADD CONSTRAINT `futuro_contrato_lider_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_contrato_lider_ibfk_2` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_contrato_vendedor`
--
ALTER TABLE `futuro_contrato_vendedor`
  ADD CONSTRAINT `futuro_contrato_vendedor_ibfk_1` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_contrato_vendedor_ibfk_2` FOREIGN KEY (`id_personal`) REFERENCES `futuro_personal` (`id_personal`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_cont_cat_com`
--
ALTER TABLE `futuro_cont_cat_com`
  ADD CONSTRAINT `futuro_cont_cat_com_ibfk_1` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_cont_cat_com_ibfk_2` FOREIGN KEY (`id_mos_cat_com`) REFERENCES `futuro_mostrar_catalogo_promociones` (`id_mos_cat_com`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_logs`
--
ALTER TABLE `futuro_logs`
  ADD CONSTRAINT `futuro_logs_ibfk_1` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_pbc_ie`
--
ALTER TABLE `futuro_pbc_ie`
  ADD CONSTRAINT `futuro_pbc_ie_ibfk_1` FOREIGN KEY (`id_ie`) REFERENCES `futuro_ingresos_egresos` (`id_ie`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_pbc_ie_ibfk_2` FOREIGN KEY (`id_pago_bonocom`) REFERENCES `futuro_pago_bonocom` (`id_pago_bonocom`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_pcc_ie`
--
ALTER TABLE `futuro_pcc_ie`
  ADD CONSTRAINT `futuro_pcc_ie_ibfk_1` FOREIGN KEY (`id_ie`) REFERENCES `futuro_ingresos_egresos` (`id_ie`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_pcc_ie_ibfk_2` FOREIGN KEY (`id_pago_comcob`) REFERENCES `futuro_pago_comcob` (`id_pago_comcob`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `futuro_personal`
--
ALTER TABLE `futuro_personal`
  ADD CONSTRAINT `futuro_personal_ibfk_1` FOREIGN KEY (`id_puesto`) REFERENCES `futuro_puestos` (`id_puesto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_personal_ibfk_2` FOREIGN KEY (`id_nivel_ventas`) REFERENCES `futuro_nivel_ventas` (`id_nivel_ventas`) ON UPDATE CASCADE,
  ADD CONSTRAINT `futuro_personal_ibfk_3` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicio_caja`
--
ALTER TABLE `servicio_caja`
  ADD CONSTRAINT `servicio_caja_ibfk_1` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `servicio_caja_ibfk_2` FOREIGN KEY (`codigo`) REFERENCES `cajas` (`codigo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicio_equipo`
--
ALTER TABLE `servicio_equipo`
  ADD CONSTRAINT `servicio_equipo_ibfk_1` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `servicio_equipo_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicio_fallecido`
--
ALTER TABLE `servicio_fallecido`
  ADD CONSTRAINT `servicio_fallecido_ibfk_1` FOREIGN KEY (`id_fallecido`) REFERENCES `fallecido` (`id_fallecido`) ON UPDATE CASCADE,
  ADD CONSTRAINT `servicio_fallecido_ibfk_2` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tablero_actividades`
--
ALTER TABLE `tablero_actividades`
  ADD CONSTRAINT `tablero_actividades_ibfk_1` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tablero_asignaciones`
--
ALTER TABLE `tablero_asignaciones`
  ADD CONSTRAINT `tablero_asignaciones_ibfk_1` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tablero_asignaciones_ibfk_2` FOREIGN KEY (`id_actividad`) REFERENCES `tablero_actividades` (`id_actividad`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tablero_lecturas`
--
ALTER TABLE `tablero_lecturas`
  ADD CONSTRAINT `tablero_lecturas_ibfk_1` FOREIGN KEY (`id_nota`) REFERENCES `tablero_notas` (`id_nota`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tablero_lecturas_ibfk_2` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tablero_notas`
--
ALTER TABLE `tablero_notas`
  ADD CONSTRAINT `tablero_notas_ibfk_1` FOREIGN KEY (`id_actividad`) REFERENCES `tablero_actividades` (`id_actividad`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tablero_notas_ibfk_2` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `titular_contrato`
--
ALTER TABLE `titular_contrato`
  ADD CONSTRAINT `titular_contrato_ibfk_1` FOREIGN KEY (`id_contrato`) REFERENCES `futuro_contratos` (`id_contrato`) ON UPDATE CASCADE,
  ADD CONSTRAINT `titular_contrato_ibfk_2` FOREIGN KEY (`id_titular`) REFERENCES `titulares` (`id_titular`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `titular_dom`
--
ALTER TABLE `titular_dom`
  ADD CONSTRAINT `titular_dom_ibfk_1` FOREIGN KEY (`id_domicilio`) REFERENCES `domicilios` (`id_domicilio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `titular_dom_ibfk_2` FOREIGN KEY (`id_titular`) REFERENCES `titulares` (`id_titular`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `titular_tels`
--
ALTER TABLE `titular_tels`
  ADD CONSTRAINT `titular_tels_ibfk_1` FOREIGN KEY (`id_tel`) REFERENCES `telefonos` (`id_tel`) ON UPDATE CASCADE,
  ADD CONSTRAINT `titular_tels_ibfk_2` FOREIGN KEY (`id_titular`) REFERENCES `titulares` (`id_titular`) ON UPDATE CASCADE;
COMMIT;
