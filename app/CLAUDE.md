# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

**Grupo Ureña Funerarias** — a funeral services ERP with two domains:
- **Servicios operativos**: active funeral cases (deceased intake, logistics, follow-up, closure, payments, PDF reports)
- **Previsión / futuros**: prepaid burial contracts, installment collection, collector routes, commissions

## Running locally

```bash
php -S localhost:8000   # from app/
```

Database is hosted remotely. No build step, no Composer autoload — all `require` calls are manual.

## Request flow

```
index.php  →  core/bootstrap.php  →  modules/{module}.php
```

`index.php` reads `?r=module.action`, validates it against a hard-coded allowlist, then `require`s `modules/{module}.php`. No framework, no ORM — pure procedural PHP.

## Module pattern

Every `modules/*.php` file follows:

```php
require_login();
require_role(['administradora', 'auxiliar', ...]);
$action = $action ?? 'listar';

switch ($action) {
  case 'listar': ...
  case 'nuevo':  ...          // show empty form
  case 'guardar': ...         // POST → insert → redirect
  case 'editar': ...          // show populated form
  case 'actualizar': ...      // POST → update → redirect
  case 'baja': ...            // soft delete (eliminado = 1)
}
```

Modules ending in `_api` (e.g. `servicios_api`, `inventario_api`, `home_api`) return JSON via `api_success()` / `api_error()` instead of calling `render()`.

## Core files

| File | Purpose |
|---|---|
| `core/bootstrap.php` | Session, config, require all core files, schema bootstrap, exception handler, shutdown perf logger |
| `core/db.php` | PDO singleton `db()`, query helpers `q`/`qall`/`qone`, schema introspection & self-healing, perf logging |
| `core/auth.php` | `require_login()`, `require_role()`, `require_permission()`, role/permission lookup with fallback to legacy `perfil` column |
| `core/helpers.php` | `e()` HTML escape, `redirect()`, `flash()`/`consume_flash()`, `csrf_field()`/`csrf_verify()`, `render()`, `form_input()`/`form_select()` |
| `core/api.php` | `api_send()`, `api_success()`, `api_error()`, `api_require_login()`, `api_require_role()`, `api_payload()` |
| `core/inventario_schema.php` | DDL bootstrap for inventory tables |
| `core/servicios_operativos.php` | DDL bootstrap + business logic for services domain |
| `core/performance_schema.php` | DDL bootstrap for perf log table |

## Schema bootstrap

On every request, `bootstrap.php` may call each `*_bootstrap(true)` function (controlled by `schema_bootstrap_mode` in config: `always` or `ondemand`). These functions issue `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ADD COLUMN` as needed — schema evolves automatically as code changes.

If a query throws a schema error (unknown column, missing table), `db_prepare_execute()` auto-triggers `db_run_registered_repairs()` and retries once.

To force a full schema rebuild: `?r=maintenance.schema_repair` (admin only).

## Authentication & authorization

- Session-based. `$_SESSION['user']` holds the user row after login.
- **Role-based** (current): `roles` + `usuarios_roles` tables. `user_has_role()` falls back to the legacy `perfil` string column if tables don't exist.
- **Permission-based**: `permisos` + `roles_permisos` tables. `user_has_permission()` falls back to role check if tables don't exist.
- `user_is_super()` returns `true` for roles with `es_super=1` or slug `admin`/`administradora` — bypasses all checks.
- `modo_libre = true` in config bypasses all auth (dev convenience).

## Config (`config/.env.php`)

Returns a plain PHP array. Key options:

```php
'dsn'                   => 'mysql:host=...;dbname=...;charset=utf8mb4'
'user' / 'pass'         => credentials
'debug'                 => true          // show raw error messages
'modo_libre'            => false         // bypass all auth checks
'schema_bootstrap_mode' => 'ondemand'   // 'always' runs DDL every request
'perf_log_enabled'      => true
'perf_log_threshold_ms' => 250
```

## Database conventions

- Spanish names for business-domain columns; snake_case throughout.
- Soft deletes: `eliminado` tinyint column; always filter with `WHERE COALESCE(eliminado,0)=0`.
- Query helpers: `q($sql, $params)` execute, `qall(...)` fetch all, `qone(...)` fetch one row or null.
- Always use parameterized queries — never interpolate user input into SQL.
- Transactions: `db()->beginTransaction()` / `commit()` / `rollBack()`; the global exception handler calls `db_rollback_open_transaction()` on any uncaught error.

## Layout

`render($title, $html)` sets globals and includes `layout/base.php`, which chains `partial_header.php` → `partial_nav.php` → content → `partial_footer.php`. Bootstrap 5.3.3 via CDN. Chart.js for dashboard, dompdf/fpdf for PDF reports.

## Adding a module

1. Add it to the `$allowed` whitelist in `index.php`.
2. Create `modules/{module}.php` with the standard pattern above.
3. For JSON APIs, name it `{name}_api` — `api_is_request()` auto-detects the `_api` suffix.
