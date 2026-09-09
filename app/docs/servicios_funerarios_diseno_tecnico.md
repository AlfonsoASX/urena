# Diseno Tecnico de Modulos de Servicios Funerarios

## Objetivo

Separar claramente el dominio de `Futuros / Prevision` del dominio de `Servicios Funerarios Operativos`.

En este repo:

- `contratos`, `pagos`, `cobrador` y `rutas` siguen perteneciendo a `futuros`.
- Los nuevos requerimientos deben implementarse como un bloque independiente para administrar servicios funerarios reales.
- La relacion con `futuros` debe ser opcional: un servicio puede ser directo o derivado de un contrato de previson.

## Diagnostico del repo actual

### Lo que ya existe

- [index.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/index.php) usa rutas `?r=modulo.accion`.
- [modules/servicios.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/modules/servicios.php) ya maneja:
  - alta simple de servicio
  - relacion con fallecido
  - asignacion de ataud
  - asignacion de equipo
  - cierre con devolucion automatica
- [modules/articulos.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/modules/articulos.php), [modules/cajas.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/modules/cajas.php) y [modules/equipos.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/modules/equipos.php) ya resuelven bien el inventario y kardex.

### Limitaciones actuales

- `servicios` solo distingue entre `abierto` y `cerrado`.
- No hay separacion por etapas operativas.
- No hay modulo de pagos del servicio directo.
- No hay captura formal de preparacion, seguimiento o cierre administrativo.
- No hay origen del servicio (`directo` vs `futuro`).
- El dashboard en [modules/home.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/modules/home.php) aun consulta `cerrado` en vez de `estatus`, asi que conviene normalizarlo cuando se trabaje esta parte.

## Decision de arquitectura

### Dominios

Se recomienda manejar dos dominios totalmente separados:

1. `Futuros / Prevision`
   - contratos a plazo
   - abonos
   - cobranza
   - comisiones

2. `Servicios Funerarios`
   - contratacion directa del servicio
   - vinculacion opcional con contrato de futuros
   - logistica
   - operacion
   - cierre
   - pagos operativos del servicio
   - reportes del servicio

### Regla clave

`Futuros` no debe convertirse en la fuente principal del flujo operativo.

La fuente principal debe ser `servicios`, porque el servicio real siempre existe aunque:

- sea contratado directamente, o
- provenga de un contrato de futuros.

## Modelo propuesto

### 1. Mantener `servicios` como expediente principal

En lugar de crear otra tabla principal, conviene evolucionar `servicios` para que sea el expediente maestro del servicio funerario.

Campos sugeridos para agregar a `servicios`:

- `origen_tipo` enum o varchar:
  - `directo`
  - `futuro`
- `id_contrato_futuro` nullable
- `estatus`:
  - `registrado`
  - `preparacion`
  - `en_proceso`
  - `finalizado`
  - `cancelado`
- `fecha_hora_programada`
- `lugar_tipo`:
  - `capilla`
  - `domicilio`
  - `iglesia`
  - `otro`
- `lugar_detalle`
- `id_vehiculo` nullable
- `id_sala` nullable
- `id_usuario_vendedor` nullable
- `id_usuario_auxiliar` nullable
- `monto_total`
- `anticipo`
- `saldo_pendiente`
- `forma_pago_inicial`
- `requiere_factura`
- `observaciones_comerciales`
- `updated_at`
- `closed_at` nullable

Campos actuales que siguen siendo utiles:

- `folio`
- `tipo_servicio`
- `tipo_disposicion`
- `responsable`
- `auxiliares`
- `contratante_nombre`
- `contratante_tel`
- `contratante_email`
- `notas`
- `created_at`

### 2. Crear tabla de asignacion operativa

Tabla sugerida: `servicio_asignacion`

Una fila por servicio para concentrar la preparacion/logistica actual.

Campos sugeridos:

- `id_asignacion`
- `id_servicio`
- `fecha_hora_inicio`
- `lugar_tipo`
- `lugar_detalle`
- `id_usuario_auxiliar`
- `codigo_caja` nullable
- `id_vehiculo` nullable
- `id_sala` nullable
- `instrucciones`
- `created_by`
- `created_at`
- `updated_at`

Motivo:

- hoy la asignacion esta dispersa entre `servicios`, `cajas` y `equipos`
- esta tabla permite tener una vista clara de preparacion
- no rompe el kardex existente porque el inventario sigue moviendose en sus tablas actuales

### 3. Crear tabla de seguimiento operativo

Tabla sugerida: `servicio_seguimiento`

Una fila por servicio, editable durante la ejecucion.

Campos:

- `id_seguimiento`
- `id_servicio`
- `hora_inicio_real`
- `hora_fin_real`
- `observaciones_iniciales`
- `observaciones_finales`
- `incidencias`
- `confirmado_por_auxiliar`
- `confirmado_por_usuario`
- `updated_at`

### 4. Crear bitacora estructurada del servicio

Tabla sugerida: `servicio_bitacora`

Campos:

- `id_bitacora`
- `id_servicio`
- `tipo_evento`
- `descripcion`
- `id_usuario`
- `metadata_json`
- `created_at`

Eventos sugeridos:

- `creacion`
- `asignacion_auxiliar`
- `asignacion_caja`
- `asignacion_equipo`
- `salida_articulo`
- `cambio_estatus`
- `incidencia`
- `cierre`
- `pago`
- `devolucion`

Esta tabla complementa `futuro_logs`, pero enfocada al expediente operativo.

### 5. Crear cierre administrativo del servicio

Tabla sugerida: `servicio_cierre`

Campos:

- `id_cierre`
- `id_servicio`
- `observaciones_cierre`
- `articulos_devueltos_json`
- `caja_reciclada`
- `pago_confirmado`
- `auxiliar_confirma`
- `id_usuario_auxiliar`
- `id_usuario_administrador`
- `fecha_cierre`

Motivo:

- hoy `servicios.cerrar` hace todo como accion unica
- el requerimiento pide formulario de cierre y evidencia administrativa

### 6. Crear pagos de servicios directos

Tabla sugerida: `servicio_pagos`

Este punto es importante para no reutilizar `pagos.php`, que hoy pertenece a `futuros`.

Campos:

- `id_pago`
- `id_servicio`
- `fecha_pago`
- `monto`
- `forma_pago`
- `referencia`
- `tipo_pago`
  - `anticipo`
  - `abono`
  - `liquidacion`
  - `devolucion`
- `observaciones`
- `id_usuario`
- `created_at`

Con esta tabla se puede:

- imprimir recibos del servicio directo
- calcular saldo pendiente
- cerrar administrativamente sin depender de futuros

### 7. Catalogos opcionales

Si se desea administrar disponibilidad real, conviene agregar:

- `vehiculos`
- `salas`
- `turnos_auxiliares` o `disponibilidad_auxiliar`

Si se quiere avanzar mas rapido, estos catalogos pueden iniciar simples y crecer despues.

## Modulos PHP propuestos

Siguiendo el patron actual de `index.php`, se recomienda agregar o ampliar estos modulos:

### 1. `servicios`

Debe seguir siendo el expediente principal.

Nuevas acciones sugeridas:

- `listar`
- `nuevo`
- `guardar`
- `ver`
- `editar`
- `actualizar`
- `cambiar_estatus`
- `vincular_futuro`

Responsabilidad:

- alta del servicio
- datos generales
- origen del servicio
- contratante
- fallecido
- resumen economico

### 2. `servicio_asignaciones`

Acciones sugeridas:

- `ver`
- `guardar`
- `actualizar`
- `orden_pdf`

Responsabilidad:

- preparacion
- auxiliar
- lugar
- ataud
- articulos a preparar
- vehiculo
- orden interna

### 3. `servicio_seguimiento`

Acciones sugeridas:

- `ver`
- `guardar`
- `agregar_incidencia`
- `agregar_consumo`

Responsabilidad:

- inicio real
- incidencias
- observaciones
- salidas adicionales de articulos
- cierre operativo preliminar

### 4. `servicio_cierres`

Acciones sugeridas:

- `ver`
- `guardar`
- `imprimir`

Responsabilidad:

- devoluciones
- reciclado
- confirmaciones
- pago total
- cierre administrativo

### 5. `servicio_pagos`

Acciones sugeridas:

- `listar`
- `nuevo`
- `guardar`
- `recibo`

Responsabilidad:

- anticipo
- abonos
- liquidacion
- saldo pendiente

### 6. `servicio_reportes`

Acciones sugeridas:

- `orden_preparacion_pdf`
- `consumo_pdf`
- `acta_cierre_pdf`
- `reporte_cierre_pdf`

Responsabilidad:

- salidas documentales operativas

## Flujo funcional propuesto

### Etapa 1. Captacion / contratacion

Pantalla principal:

- `servicios.nuevo`

Debe capturar:

- fallecido
- contratante
- telefono, correo, direccion
- tipo de servicio
- tipo de disposicion
- vendedor
- monto total
- anticipo
- forma de pago
- origen del servicio
- contrato futuro relacionado, si aplica

Al guardar:

- genera folio
- deja `estatus = registrado`
- crea bitacora
- opcionalmente genera primer pago si hubo anticipo

### Etapa 2. Preparacion / asignacion

Pantalla principal:

- `servicio_asignaciones.ver&id=...`

Debe capturar:

- fecha y hora programada
- lugar
- auxiliar
- ataud
- articulos a preparar
- vehiculo

Al guardar:

- cambia `estatus = preparacion`
- registra bitacora
- ejecuta movimientos de inventario ya existentes

### Etapa 3. Ejecucion

Pantalla principal:

- `servicio_seguimiento.ver&id=...`

Debe capturar:

- hora real de inicio
- observaciones iniciales
- incidencias
- salidas adicionales de articulos
- hora real de finalizacion

Al guardar:

- cambia `estatus = en_proceso` al iniciar
- cambia `estatus = finalizado` al completar operacion
- registra bitacora

### Etapa 4. Cierre operativo y administrativo

Pantalla principal:

- `servicio_cierres.ver&id=...`

Debe capturar:

- devoluciones
- reciclado de ataud, si aplica
- confirmacion del auxiliar
- observaciones de cierre
- confirmacion de pago total

Al guardar:

- devuelve recursos
- actualiza inventario
- marca cierre
- genera reporte de cierre

## Integracion con inventario existente

La recomendacion es no reescribir inventario.

Se debe reutilizar:

- `cajas_movimientos`
- `equipos_movimientos`
- `articulos_movimientos`
- `articulos_salida_servicio`

Lo nuevo debe vivir alrededor del expediente del servicio, no duplicando kardex.

## Integracion opcional con futuros

Relacion sugerida:

- `servicios.id_contrato_futuro -> futuro_contratos.id_contrato`

Reglas:

- si `origen_tipo = directo`, `id_contrato_futuro` es `NULL`
- si `origen_tipo = futuro`, se guarda la referencia al contrato
- los pagos del servicio directo no se registran en `futuro_abonos`
- los pagos de futuros no sustituyen el cierre del servicio operativo

Esto permite rastrear el origen sin mezclar modulos.

## Cambios minimos en rutas

En [index.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/index.php) se propone ampliar:

- `servicios`
  - `editar`
  - `actualizar`
  - `cambiar_estatus`
  - `vincular_futuro`
- `servicio_asignaciones`
  - `ver`
  - `guardar`
  - `actualizar`
  - `orden_pdf`
- `servicio_seguimiento`
  - `ver`
  - `guardar`
  - `agregar_incidencia`
  - `agregar_consumo`
- `servicio_cierres`
  - `ver`
  - `guardar`
  - `imprimir`
- `servicio_pagos`
  - `listar`
  - `nuevo`
  - `guardar`
  - `recibo`
- `servicio_reportes`
  - `orden_preparacion_pdf`
  - `consumo_pdf`
  - `acta_cierre_pdf`
  - `reporte_cierre_pdf`

## Cambios en navegacion

En [layout/partial_nav.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/layout/partial_nav.php) se recomienda crear un bloque distinto de `Servicios Funerarios`, separado de `Contratos` y `Pagos`.

Ejemplo de menu:

- Servicios
- Asignaciones
- Seguimiento
- Cierres
- Pagos servicio
- Inventario

## Roles recomendados

Roles operativos sugeridos para este dominio:

- `administradora`
- `coordinador`
- `auxiliar`
- `vendedor`
- `caja`

Si no se quiere crear roles nuevos de inmediato, se puede arrancar con:

- `administradora`
- `auxiliar`
- `vendedor`

Y despues refinar permisos.

## Estrategia de implementacion por fases

### Fase 1. Base funcional minima

- ampliar tabla `servicios`
- actualizar [modules/servicios.php](/Users/alfonsoasx/Documents/GitHub/Urena/app/modules/servicios.php)
- capturar origen, monto, anticipo, estatus y fecha programada
- separar estados operativos

### Fase 2. Preparacion y seguimiento

- crear `servicio_asignacion`
- crear `servicio_seguimiento`
- agregar pantallas dedicadas
- mantener movimientos de inventario actuales

### Fase 3. Cierre y pagos

- crear `servicio_cierre`
- crear `servicio_pagos`
- generar recibo y cierre administrativo

### Fase 4. Reportes y catalogos

- PDFs operativos
- vehiculos
- salas
- disponibilidad de auxiliares

## Recomendacion de implementacion

La ruta mas segura para este repo es:

1. no tocar el dominio de `futuros` salvo para agregar relacion opcional
2. evolucionar `servicios` como expediente principal
3. crear modulos nuevos por etapa en vez de seguir cargando todo dentro de un solo archivo
4. reutilizar el kardex de inventario ya existente

## Siguiente paso recomendado

Con este diseno, el siguiente entregable tecnico deberia ser:

- lista exacta de cambios SQL
- actualizacion de rutas en `index.php`
- primer modulo a construir: `servicios` ampliado
- despues `servicio_asignaciones`

Ese orden permite empezar a capturar servicios directos desde ya, sin esperar a tener todos los reportes terminados.
