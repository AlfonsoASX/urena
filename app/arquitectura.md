# Arquitectura del proyecto

## Resumen ejecutivo

Este proyecto es un **monolito PHP clásico, renderizado del lado del servidor**, sin framework (no Laravel, Symfony, Slim, etc.), sin ORM y sin pipeline de frontend.

La aplicación se organiza alrededor de:

- Un **front controller** único en `index.php`.
- Un conjunto de utilidades y servicios compartidos en `core/`.
- Un conjunto de **módulos por archivo** en `modules/`, donde cada archivo mezcla:
  - control de flujo por `switch ($action)`,
  - consultas SQL directas,
  - validación,
  - y renderizado HTML de la vista.
- Layout base simple en `layout/`.
- Persistencia en **MySQL vía PDO**.
- Librerías de PDF embebidas localmente (`dompdf`, `fpdf`).
- Dependencias frontend cargadas mayormente por CDN.

En términos prácticos, la app tiene dos dominios principales viviendo en el mismo código:

1. **Servicios funerarios operativos**
   - `servicios`
   - `servicio_asignaciones`
   - `servicio_seguimiento`
   - `servicio_cierres`
   - `servicio_pagos`
   - `servicio_reportes`
   - `inventario_api`
   - `servicios_api`

2. **Futuros / previsión / cobranza**
   - `contratos`
   - `pagos`
   - `cobrador`
   - `rutas`
   - impresiones relacionadas

Además, existe un dominio transversal de **inventario**:

- `articulos`
- `cajas`
- `equipos`
- `compras`
- `vales`
- `proveedores`

## Estilo arquitectónico

### Patrón general

La arquitectura es una mezcla de:

- **Front Controller**: todas las requests entran por `index.php`.
- **Router por query string**: la ruta se define con `?r=modulo.accion`.
- **Page Controller por archivo**: cada archivo en `modules/` atiende acciones del mismo módulo.
- **Service/Helper layer ligera** en `core/`: encapsula DB, auth, schema bootstrap y reglas de negocio reutilizables.
- **Server-rendered HTML** con mejoras puntuales en JavaScript.

### Lo que no existe

No se observan:

- contenedor de dependencias,
- autoload propio del proyecto,
- namespaces del proyecto,
- separación estricta `controller/service/repository/view`,
- migraciones versionadas tipo `migrations/`,
- tests automatizados,
- build de assets,
- manejo de dependencias con Composer a nivel raíz,
- manejo de dependencias con npm/yarn/pnpm.

## Flujo principal de una request

### 1. Entrada

`index.php`:

- carga `core/bootstrap.php`,
- obtiene `$_GET['r']`,
- separa `modulo.accion`,
- valida el módulo/acción contra una whitelist,
- aplica una redirección especial para perfil `cobrador`,
- y finalmente hace `require` del archivo `modules/$module.php`.

### 2. Bootstrap global

`core/bootstrap.php` hace lo siguiente:

- inicia sesión con `session_start()`,
- carga la configuración desde `config/.env.php`,
- registra `cfg` en `$GLOBALS`,
- incluye:
  - `core/db.php`
  - `core/inventario_schema.php`
  - `core/performance_schema.php`
  - `core/helpers.php`
  - `core/ui.php`
  - `core/auth.php`
  - `core/api.php`
  - `core/inventario_v2.php`
  - `core/servicios_operativos.php`
- inicializa telemetría de rendimiento,
- asegura compatibilidad de esquema en runtime,
- registra reparaciones automáticas de esquema,
- instala un manejador global de excepciones,
- y registra logging de requests lentos al finalizar.

### 3. Resolución por módulo

Cada archivo en `modules/`:

- reimporta explícitamente dependencias de `core/` con `require_once`,
- llama normalmente a `require_login()` y/o `require_role()` / `require_permission()`,
- define helpers locales del módulo,
- y resuelve acciones con `switch ($action)`.

### 4. Renderizado

Las vistas HTML se construyen con `ob_start()` + `render($title, $html)`.

`render()` usa:

- `layout/base.php`
- `layout/partial_header.php`
- `layout/partial_nav.php`
- `layout/partial_footer.php`

Esto deja a la app con una plantilla base compartida y contenido por página inyectado como string HTML.

## Mapa de carpetas

```text
app/
├── index.php                      # Front controller y router principal
├── arquitectura.md                # Documento de arquitectura
├── config/
│   └── .env.php                   # Configuración runtime (app + DB + flags)
├── core/
│   ├── bootstrap.php              # Bootstrap global de sesión, core y manejo de errores
│   ├── db.php                     # Conexión PDO, helpers q/qall/qone, cache de esquema, auto-repair
│   ├── auth.php                   # Login, roles, permisos, require_role/permission
│   ├── helpers.php                # HTML escape, redirect, flash, CSRF, render, inputs
│   ├── ui.php                     # Helpers UI heredados/simples
│   ├── api.php                    # Helpers JSON/API
│   ├── inventario_schema.php      # Bootstrap de tablas base de inventario
│   ├── inventario_v2.php          # Reglas de inventario operativo y movimientos por servicio
│   ├── servicios_operativos.php   # Dominio de servicios funerarios
│   ├── home_dashboard.php         # KPIs y widgets del dashboard
│   └── performance_schema.php     # Bootstrap de logging de performance
├── layout/
│   ├── base.php                   # Layout base
│   ├── partial_header.php         # HTML head + Bootstrap CSS CDN
│   ├── partial_nav.php            # Navbar con visibilidad por rol/permisos
│   └── partial_footer.php         # Bootstrap JS bundle CDN
├── modules/
│   ├── auth.php                   # Login / logout
│   ├── home.php                   # Dashboard HTML principal
│   ├── home_api.php               # KPIs y widgets del dashboard en JSON
│   ├── servicios.php              # Expediente principal de servicio funerario
│   ├── servicio_asignaciones.php  # Logística y asignación de recursos
│   ├── servicio_seguimiento.php   # Seguimiento operativo en ejecución
│   ├── servicio_cierres.php       # Cierre estructurado de servicio
│   ├── servicio_pagos.php         # Pagos de servicios y recibos PDF
│   ├── servicio_reportes.php      # PDFs de servicio con dompdf
│   ├── servicios_api.php          # API JSON para asignaciones/consumos/devoluciones
│   ├── inventario_api.php         # API JSON de disponibilidad de inventario
│   ├── articulos.php              # Inventario de artículos y kardex
│   ├── cajas.php                  # Inventario de ataúdes / cajas
│   ├── equipos.php                # Inventario de equipos
│   ├── compras.php                # Entradas de artículos
│   ├── vales.php                  # Vales de salida
│   ├── proveedores.php            # Catálogo de proveedores
│   ├── contratos.php              # Contratos de futuros / previsión
│   ├── pagos.php                  # Abonos, cortes, comisiones, reportes de futuros
│   ├── cobrador.php               # Panel operativo del cobrador
│   ├── rutas.php                  # Asignación de cobradores a contratos
│   ├── usuarios.php               # Administración de usuarios, roles y permisos
│   ├── maintenance.php            # Reparación de esquema y reporte de performance
│   ├── fallecidos.php             # Redirección al flujo nuevo dentro de servicios
│   ├── imprimirL.php              # Impresión PDF heredada
│   └── imprimirM.php              # Endpoint/impresión auxiliar heredada
├── docs/
│   └── servicios_funerarios_diseno_tecnico.md  # Documento de evolución del dominio funerario
├── storage/
│   ├── cache/                     # Snapshot de esquema
│   └── logs/                      # perf.log y otros logs runtime
├── img/                           # Imágenes del sistema
├── dompdf/                        # Librería PDF embebida + vendor de Composer propio
├── fpdf/                          # Librería PDF embebida
├── efecto/                        # Demo visual independiente
└── .claude/                       # Configuración auxiliar local de tooling
```

## Capas y responsabilidades

### 1. Capa de entrada y routing

Responsable:

- `index.php`

Características:

- routing manual por whitelist,
- sin rutas declarativas externas,
- sin middlewares formales,
- sin dispatch dinámico más allá de `require "modules/$module.php"`.

Implicación:

- es simple de seguir,
- pero escala con fricción porque cada módulo debe mantenerse manualmente en la whitelist.

### 2. Capa core / infraestructura

Responsables principales:

- `core/db.php`
- `core/auth.php`
- `core/helpers.php`
- `core/api.php`
- `core/bootstrap.php`

Funciones clave:

- conexión PDO,
- queries con `q`, `qall`, `qone`,
- cache de esquema,
- snapshot de esquema en archivo,
- auto-reparación de columnas/tablas faltantes,
- logging de errores,
- logging de rendimiento,
- sesión y autenticación,
- autorización por roles y permisos,
- helpers de render y formularios,
- respuestas JSON estandarizadas.

### 3. Capa de dominio compartido

Responsables:

- `core/inventario_v2.php`
- `core/servicios_operativos.php`
- `core/home_dashboard.php`

Aquí vive la lógica de negocio reusable. Es la parte más cercana a una capa de servicios real.

Ejemplos:

- asignar / devolver cajas,
- asignar / liberar equipos,
- registrar consumos,
- recalcular saldo de servicios,
- guardar seguimiento,
- cerrar recursos de un servicio,
- construir KPIs para dashboard.

### 4. Capa de módulos / páginas

Responsables:

- `modules/*.php`

Patrón:

- reciben `$action`,
- resuelven permisos,
- hacen consultas,
- orquestan transacciones,
- renderizan HTML o responden JSON/PDF.

Es una capa híbrida:

- controlador,
- vista,
- y parte de acceso a datos,

todo dentro del mismo archivo.

### 5. Capa de presentación

Responsables:

- `layout/*`
- HTML generado con `ob_start()`
- scripts embebidos por página

La UI es principalmente:

- Bootstrap 5 para layout/base components,
- tablas HTML,
- formularios clásicos,
- mejoras JS específicas por módulo.

## Dominios funcionales

### A. Servicios funerarios

Es el dominio más estructurado del proyecto y el que muestra una evolución más reciente.

#### Expediente principal

`modules/servicios.php`

Responsabilidades:

- alta y edición de servicios,
- expediente principal del fallecido/contratante,
- programación del servicio,
- datos comerciales,
- relación opcional con contratos de futuros,
- visualización del expediente,
- cambio de estatus,
- baja lógica.

Soporte de negocio:

- `core/servicios_operativos.php`

Tablas principales involucradas:

- `servicios`
- `servicio_asignacion`
- `servicio_seguimiento`
- `servicio_cierre`
- `servicio_pagos`
- `servicio_bitacora`
- `servicio_fallecido`
- `fallecido`

#### Asignaciones operativas

`modules/servicio_asignaciones.php`

Responsabilidades:

- logística del servicio,
- asignación de ataúd,
- asignación de equipos,
- asignación de artículos,
- cancelación de recursos,
- integración con API JSON para UI más interactiva.

Depende de:

- `core/servicios_operativos.php`
- `core/inventario_v2.php`

#### Seguimiento operativo

`modules/servicio_seguimiento.php`

Responsabilidades:

- hora inicio/fin real,
- observaciones,
- incidencias,
- consumos adicionales,
- actualización de estatus operativo.

#### Cierre

`modules/servicio_cierres.php`

Responsabilidades:

- devolución de caja,
- liberación de equipos,
- devolución parcial de artículos,
- confirmación de pago,
- cierre administrativo/operativo.

#### Pagos de servicio

`modules/servicio_pagos.php`

Responsabilidades:

- captura de pagos de servicio,
- cálculo de saldo,
- emisión de recibos PDF con FPDF.

#### Reportes

`modules/servicio_reportes.php`

Responsabilidades:

- orden de preparación PDF,
- reporte de consumos,
- acta interna de cierre,
- reporte de cierre.

Dependencia externa:

- `dompdf` para PDFs HTML renderizados.

#### APIs del dominio

- `modules/servicios_api.php`
- `modules/inventario_api.php`
- `modules/home_api.php`

Estas APIs son ligeras y están pensadas como soporte al frontend server-rendered, no como API pública completa.

### B. Inventario

#### Inventario base

`modules/articulos.php`, `modules/cajas.php`, `modules/equipos.php`

Responsabilidades:

- catálogos,
- altas/ediciones/bajas lógicas,
- kardex/movimientos,
- ajuste de existencias,
- estado de recursos.

Tablas relevantes:

- `articulos`
- `articulos_movimientos`
- `articulos_traspasos`
- `articulos_traspaso_det`
- `articulos_salida_servicio`
- `cajas`
- `cajas_movimientos`
- `equipos`
- `equipos_movimientos`

#### Inventario operativo ligado a servicios

`core/inventario_v2.php`

Este archivo es crítico porque conecta inventario con servicios funerarios.

Añade tablas y reglas para:

- `servicio_articulos`
- `kardex_articulos`
- `servicio_caja`
- `renta_cajas`
- `servicio_equipo`
- tablas de eventos por artículo/equipo/caja

También encapsula operaciones transaccionales como:

- asignar caja,
- devolver caja,
- asignar equipo,
- cancelar equipos,
- registrar consumos,
- cerrar recursos.

#### Compras y vales

- `modules/compras.php`
- `modules/vales.php`

Responsabilidades:

- entradas de inventario por compra,
- salidas por vale,
- afectación de stock.

Estas piezas parecen más antiguas y menos cohesionadas con `inventario_v2`, pero siguen siendo parte del flujo de inventario.

#### Proveedores

`modules/proveedores.php`

Particularidad:

- implementa su propia auto-migración suave del esquema del catálogo de proveedores.

### C. Futuros / previsión / cobranza

#### Contratos

`modules/contratos.php`

Responsabilidades:

- alta y edición de contratos de previsión,
- consulta de titular/domicilio,
- PDFs y título,
- relación con vendedores/promociones.

#### Pagos y comisiones

`modules/pagos.php`

Responsabilidades:

- listar contratos con saldo,
- registrar abonos,
- reportes,
- corte por persona,
- comisiones,
- edición financiera asociada al dominio de futuros.

#### Cobrador

`modules/cobrador.php`

Responsabilidades:

- panel del cobrador,
- contratos asignados,
- gestiones,
- pagos,
- estado de cuenta,
- tickets.

#### Rutas

`modules/rutas.php`

Responsabilidades:

- asignación de cobradores a contratos,
- armado de rutas,
- vistas por contrato y por cobrador.

### D. Administración

#### Usuarios, roles y permisos

`modules/usuarios.php` + `core/auth.php`

Capacidades:

- CRUD de usuarios,
- asignación de roles,
- asignación de permisos,
- cambio de contraseña,
- catálogo canónico de permisos por módulo.

Tablas relevantes:

- `usuarios`
- `roles`
- `usuarios_roles`
- `permisos`
- `roles_permisos`

#### Mantenimiento

`modules/maintenance.php`

Capacidades:

- ejecutar repair explícito de esquema,
- ver requests lentos,
- ver queries lentas.

## Dependencias internas

### Dependencia de arranque

```text
index.php
  -> core/bootstrap.php
      -> core/db.php
      -> core/inventario_schema.php
      -> core/performance_schema.php
      -> core/helpers.php
      -> core/ui.php
      -> core/auth.php
      -> core/api.php
      -> core/inventario_v2.php
      -> core/servicios_operativos.php
  -> modules/<modulo>.php
```

### Dependencias clave por módulo

#### Módulos base

- `modules/auth.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/ui.php`
  - `core/auth.php`

- `modules/home.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`
  - `core/servicios_operativos.php`
  - `core/inventario_v2.php`

- `modules/home_api.php`
  - `core/api.php`
  - `core/home_dashboard.php`

#### Servicios funerarios

- `modules/servicios.php`
  - `core/servicios_operativos.php`

- `modules/servicio_asignaciones.php`
  - `core/servicios_operativos.php`
  - `core/inventario_v2.php` de forma indirecta vía funciones llamadas

- `modules/servicio_seguimiento.php`
  - `core/servicios_operativos.php`
  - `core/inventario_v2.php` de forma indirecta

- `modules/servicio_cierres.php`
  - `core/servicios_operativos.php`
  - `core/inventario_v2.php`

- `modules/servicio_pagos.php`
  - `core/servicios_operativos.php`
  - `fpdf/fpdf.php`

- `modules/servicio_reportes.php`
  - `core/servicios_operativos.php`
  - `dompdf/autoload.inc.php`

- `modules/servicios_api.php`
  - `core/api.php`
  - `core/inventario_v2.php`
  - `core/servicios_operativos.php`

- `modules/inventario_api.php`
  - `core/api.php`
  - `core/inventario_v2.php`
  - `core/servicios_operativos.php`

#### Inventario

- `modules/articulos.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/cajas.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/equipos.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/compras.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/vales.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/proveedores.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

#### Futuros

- `modules/contratos.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/pagos.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/cobrador.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/rutas.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

#### Administración

- `modules/usuarios.php`
  - `core/db.php`
  - `core/helpers.php`
  - `core/auth.php`

- `modules/maintenance.php`
  - `core/inventario_schema.php`
  - `core/inventario_v2.php`
  - `core/servicios_operativos.php`
  - `core/performance_schema.php`
  - `core/auth.php`

## Dependencias externas

## Backend / runtime

- **PHP**
  - uso intensivo de PDO, sesiones, JSON, output buffering, manejo de errores.
- **MySQL / MariaDB**
  - acceso directo por PDO.
- **PDO MySQL**
  - conexión principal de persistencia.

## Librerías PHP embebidas localmente

- **FPDF**
  - ubicada en `fpdf/`
  - usada para recibos y algunas impresiones heredadas.

- **dompdf**
  - ubicada en `dompdf/`
  - usada para reportes PDF basados en HTML.

Dependencias transitivas incluidas dentro de `dompdf/vendor/`:

- `dompdf/dompdf`
- `dompdf/php-font-lib`
- `dompdf/php-svg-lib`
- `masterminds/html5`
- `sabberworm/php-css-parser`
- `composer/*` del vendor embebido

## Frontend por CDN

- **Bootstrap 5.3.3**
  - CSS y JS bundle cargados en layout global.

- **Chart.js**
  - usado en `modules/home.php` para gráficas del dashboard.

- **Leaflet 1.9.4**
  - usado en `modules/home.php` para mapa de gestiones.

- **OpenStreetMap tiles**
  - usados por Leaflet como capa base.

- **jQuery**
  - usado en algunas pantallas heredadas, especialmente `contratos.php` y `pagos.php`.

- **DataTables**
  - usado en vistas heredadas del dominio de futuros.

- **three.js**
  - aparece en `efecto/index.html`, que parece ser una demo aislada y no una dependencia core de la app principal.

## Integraciones indirectas

- enlaces a **Google Maps** desde el dashboard/mapa,
- uso de **CDNs públicos** para librerías JS/CSS.

## Gestión de datos

### Acceso a datos

No existe repository layer formal.

El patrón real es:

- SQL embebido en módulos y funciones de `core`,
- wrappers mínimos:
  - `q()`
  - `qall()`
  - `qone()`

Ventajas:

- acceso directo y fácil de rastrear.

Costos:

- alto acoplamiento entre UI y SQL,
- lógica repetida,
- difícil reutilización fina,
- testing complejo.

### Auto-bootstrap y auto-repair de esquema

Uno de los rasgos más distintivos del proyecto es que el esquema de DB se repara en runtime.

Componentes involucrados:

- `core/inventario_schema.php`
- `core/inventario_v2.php`
- `core/servicios_operativos.php`
- `core/auth.php`
- `core/performance_schema.php`
- `core/db.php`

Mecánica:

1. Se registran callbacks de repair con `db_register_schema_repair(...)`.
2. Si una query falla por tabla/columna ausente y no hay transacción activa:
   - `db_prepare_execute()` detecta el error de esquema,
   - corre `db_run_registered_repairs()`,
   - refresca snapshot/cache,
   - reintenta la query.

Esto reduce fricción operativa, pero también significa que:

- el esquema no está estrictamente versionado,
- la base de datos puede evolucionar por side effects del runtime,
- y parte de la arquitectura depende del estado de producción.

### Snapshot y cache de esquema

`core/db.php` guarda un snapshot en:

- `storage/cache/schema_snapshot.php`

Esto acelera verificaciones de:

- tablas existentes,
- columnas existentes,
- índices existentes.

### Logging de performance

`core/db.php` y `modules/maintenance.php` trabajan juntos para registrar:

- conexiones lentas,
- queries lentas,
- requests lentos.

Archivo típico:

- `storage/logs/perf.log`

## Seguridad y control de acceso

### Autenticación

Responsable:

- `core/auth.php`

Mecanismo:

- sesión PHP,
- login por usuario/contraseña,
- `password_verify()` sobre `usuarios.pass`.

### Autorización

Se usan dos niveles:

1. **Roles**
   - `require_role()`
   - `user_has_role()`

2. **Permisos**
   - `require_permission()`
   - `user_has_permission()`

Particularidad:

- el proyecto soporta modo legado por `perfil` en `usuarios`,
- y modo más formal con tablas `roles`, `permisos`, `usuarios_roles`, `roles_permisos`.

### Protección CSRF

`core/helpers.php` implementa:

- `csrf_token()`
- `csrf_field()`
- `csrf_verify()`

Y `core/api.php` agrega:

- `api_verify_csrf_if_present()`

## Presentación y frontend

### Enfoque

La UI es mayormente:

- HTML generado en PHP,
- Bootstrap para estilos,
- formularios clásicos con POST/redirect,
- y JS embebido solo donde agrega valor.

### Grado de interactividad

Hay tres niveles:

1. **Pantallas totalmente server-rendered**
   - usuarios
   - proveedores
   - inventario base
   - pagos

2. **Pantallas server-rendered con JS pequeño embebido**
   - compras
   - vales
   - rutas
   - proveedor live search

3. **Pantallas híbridas con APIs JSON**
   - dashboard (`home.php` + `home_api.php`)
   - asignaciones (`servicio_asignaciones.php` + `servicios_api.php`)
   - disponibilidad (`inventario_api.php`)

## Observaciones de diseño

### Fortalezas

- Arquitectura simple de entender para quien conoce PHP clásico.
- Código muy directo: la ruta al dato suele ser corta.
- Ya existe una semicapas de negocio reutilizable en `core/servicios_operativos.php` e `inventario_v2.php`.
- El dominio de servicios funerarios está mejor encapsulado que el resto.
- La auto-reparación de esquema hace el sistema tolerante a diferencias entre ambientes.

### Debilidades / deuda técnica

- Alto acoplamiento entre SQL, reglas de negocio y HTML.
- Sin separación clara entre controller/service/repository/view.
- Sin pruebas automáticas visibles.
- Sin migraciones formales.
- Dependencias frontend cargadas por CDN y dispersas por módulo.
- Mezcla de módulos nuevos más estructurados con módulos heredados más procedurales.
- El proyecto guarda configuración sensible en `config/.env.php`, lo que conviene tratar como riesgo operacional.

### Módulos heredados o transicionales

- `fallecidos.php` ya no tiene flujo propio y redirige a `servicios`.
- `imprimirL.php` e `imprimirM.php` parecen utilitarios heredados.
- `efecto/` parece una demo separada, no parte del runtime principal.

## Dependencias por dominio de datos

### Servicios funerarios

Tablas más relevantes:

- `servicios`
- `servicio_asignacion`
- `servicio_seguimiento`
- `servicio_cierre`
- `servicio_pagos`
- `servicio_bitacora`
- `servicio_articulos`
- `servicio_caja`
- `servicio_equipo`
- `renta_cajas`
- `kardex_articulos`

### Inventario

Tablas más relevantes:

- `articulos`
- `articulos_movimientos`
- `articulos_traspasos`
- `articulos_traspaso_det`
- `articulos_salida_servicio`
- `cajas`
- `cajas_movimientos`
- `equipos`
- `equipos_movimientos`
- `proveedores`

### Futuros / cobranza

Tablas más relevantes:

- `futuro_contratos`
- `futuro_abonos`
- `futuro_comision_semanal`
- `futuro_gestion`
- `futuro_personal`
- `futuro_contrato_cobrador`
- `futuro_contrato_vendedor`
- `titulares`
- `titular_contrato`
- `titular_dom`
- `domicilios`
- vistas como `vw_titular_contrato`

### Administración

Tablas más relevantes:

- `usuarios`
- `roles`
- `usuarios_roles`
- `permisos`
- `roles_permisos`

## Conclusión

La arquitectura actual puede resumirse así:

- **Monolito PHP server-rendered**
- **routing manual por query string**
- **módulos por archivo con controlador + vista + SQL**
- **capa core reusable para DB, auth, inventario y servicios**
- **MySQL con auto-bootstrap/auto-repair de esquema**
- **frontend clásico con Bootstrap y mejoras JS puntuales**

La parte más sólida y moderna del diseño está en la separación parcial del dominio de **servicios funerarios** dentro de `core/servicios_operativos.php` e `inventario_v2.php`.

Si el sistema sigue creciendo, el siguiente paso natural sería profundizar esa dirección:

- consolidar más lógica de negocio en `core/`,
- adelgazar `modules/`,
- y formalizar migraciones y dependencias.
