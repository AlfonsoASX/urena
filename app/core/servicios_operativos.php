<?php

require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/auth.php';

function so_table_exists(string $table): bool {
  return db_table_exists($table);
}

function so_column_exists(string $table, string $column): bool {
  return db_column_exists($table, $column);
}

function so_add_column_if_missing(string $table, string $column, string $ddl): void {
  db_add_column_if_missing($table, $column, $ddl);
}

function so_create_tables(): void {
  static $ready = false;
  if ($ready) return;

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicios (
        id_servicio INT AUTO_INCREMENT PRIMARY KEY,
        folio VARCHAR(40) DEFAULT NULL,
        tipo_servicio VARCHAR(40) DEFAULT NULL,
        tipo_disposicion VARCHAR(40) DEFAULT NULL,
        responsable VARCHAR(120) DEFAULT NULL,
        auxiliares VARCHAR(255) DEFAULT NULL,
        contratante_nombre VARCHAR(150) DEFAULT NULL,
        contratante_tel VARCHAR(40) DEFAULT NULL,
        contratante_email VARCHAR(120) DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        estatus VARCHAR(30) NOT NULL DEFAULT 'registrado',
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS fallecido (
        id_fallecido INT AUTO_INCREMENT PRIMARY KEY,
        nom_fallecido VARCHAR(150) NOT NULL,
        fecha DATE DEFAULT NULL,
        eliminado TINYINT(1) NOT NULL DEFAULT 0
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_fallecido (
        id_rel INT AUTO_INCREMENT PRIMARY KEY,
        id_fallecido INT NOT NULL,
        id_servicio INT NOT NULL
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_asignacion (
        id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        fecha_hora_inicio DATETIME NULL,
        lugar_tipo VARCHAR(30) DEFAULT NULL,
        lugar_detalle VARCHAR(255) DEFAULT NULL,
        id_usuario_auxiliar INT DEFAULT NULL,
        codigo_caja VARCHAR(60) DEFAULT NULL,
        id_vehiculo VARCHAR(80) DEFAULT NULL,
        id_sala VARCHAR(80) DEFAULT NULL,
        instrucciones TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_seguimiento (
        id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        hora_inicio_real DATETIME DEFAULT NULL,
        hora_fin_real DATETIME DEFAULT NULL,
        observaciones_iniciales TEXT DEFAULT NULL,
        observaciones_finales TEXT DEFAULT NULL,
        incidencias TEXT DEFAULT NULL,
        confirmado_por_auxiliar TINYINT(1) NOT NULL DEFAULT 0,
        confirmado_por_usuario INT DEFAULT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_cierre (
        id_cierre INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        observaciones_cierre TEXT DEFAULT NULL,
        articulos_devueltos_json LONGTEXT DEFAULT NULL,
        caja_reciclada TINYINT(1) NOT NULL DEFAULT 0,
        pago_confirmado TINYINT(1) NOT NULL DEFAULT 0,
        auxiliar_confirma TINYINT(1) NOT NULL DEFAULT 0,
        id_usuario_auxiliar INT DEFAULT NULL,
        id_usuario_administrador INT DEFAULT NULL,
        fecha_cierre DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_pagos (
        id_pago INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        fecha_pago DATETIME NOT NULL,
        monto DECIMAL(12,2) NOT NULL DEFAULT 0,
        forma_pago VARCHAR(30) DEFAULT NULL,
        referencia VARCHAR(120) DEFAULT NULL,
        tipo_pago VARCHAR(20) NOT NULL DEFAULT 'abono',
        observaciones TEXT DEFAULT NULL,
        id_usuario INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS servicio_bitacora (
        id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        tipo_evento VARCHAR(40) NOT NULL,
        descripcion VARCHAR(255) DEFAULT NULL,
        id_usuario INT DEFAULT NULL,
        metadata_json LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos_movimientos (
        id_mov INT AUTO_INCREMENT PRIMARY KEY,
        id_articulo INT NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        referencia VARCHAR(120) DEFAULT NULL,
        origen VARCHAR(100) DEFAULT NULL,
        destino VARCHAR(100) DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS articulos_salida_servicio (
        id_salida INT AUTO_INCREMENT PRIMARY KEY,
        id_servicio INT NOT NULL,
        id_articulo INT NOT NULL,
        cantidad INT NOT NULL DEFAULT 0,
        responsable VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS cajas_movimientos (
        id_mov INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(60) NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        id_servicio INT DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS equipos_movimientos (
        id_mov INT AUTO_INCREMENT PRIMARY KEY,
        id_equipo VARCHAR(60) NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        id_servicio INT DEFAULT NULL,
        origen VARCHAR(100) DEFAULT NULL,
        destino VARCHAR(100) DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS vehiculos (
        id_vehiculo VARCHAR(80) PRIMARY KEY,
        descripcion VARCHAR(120) NOT NULL,
        estatus VARCHAR(20) NOT NULL DEFAULT 'disponible',
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  try {
    q("
      CREATE TABLE IF NOT EXISTS salas (
        id_sala VARCHAR(80) PRIMARY KEY,
        descripcion VARCHAR(120) NOT NULL,
        estatus VARCHAR(20) NOT NULL DEFAULT 'disponible',
        eliminado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");
  } catch (Throwable $e) {
  }

  $columns = [
    'folio' => "VARCHAR(40) DEFAULT NULL",
    'tipo_servicio' => "VARCHAR(40) DEFAULT NULL",
    'tipo_disposicion' => "VARCHAR(40) DEFAULT NULL",
    'responsable' => "VARCHAR(120) DEFAULT NULL",
    'auxiliares' => "VARCHAR(255) DEFAULT NULL",
    'contratante_nombre' => "VARCHAR(150) DEFAULT NULL",
    'contratante_tel' => "VARCHAR(40) DEFAULT NULL",
    'contratante_email' => "VARCHAR(120) DEFAULT NULL",
    'notas' => "TEXT DEFAULT NULL",
    'estatus' => "VARCHAR(30) NOT NULL DEFAULT 'registrado'",
    'eliminado' => "TINYINT(1) NOT NULL DEFAULT 0",
    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'origen_tipo' => "VARCHAR(20) NOT NULL DEFAULT 'directo'",
    'id_contrato_futuro' => "INT DEFAULT NULL",
    'fecha_hora_programada' => "DATETIME DEFAULT NULL",
    'lugar_tipo' => "VARCHAR(30) DEFAULT NULL",
    'lugar_detalle' => "VARCHAR(255) DEFAULT NULL",
    'id_vehiculo' => "VARCHAR(80) DEFAULT NULL",
    'id_sala' => "VARCHAR(80) DEFAULT NULL",
    'id_usuario_vendedor' => "INT DEFAULT NULL",
    'id_usuario_auxiliar' => "INT DEFAULT NULL",
    'monto_total' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'anticipo' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'saldo_pendiente' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    'forma_pago_inicial' => "VARCHAR(30) DEFAULT NULL",
    'requiere_factura' => "TINYINT(1) NOT NULL DEFAULT 0",
    'observaciones_comerciales' => "TEXT DEFAULT NULL",
    'contratante_direccion' => "VARCHAR(255) DEFAULT NULL",
    'updated_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'closed_at' => "DATETIME DEFAULT NULL",
  ];
  foreach ($columns as $column => $ddl) {
    so_add_column_if_missing('servicios', $column, $ddl);
  }

  $ready = true;
}

function so_bootstrap(): void {
  so_create_tables();
}

db_register_schema_repair('servicios_operativos', 'so_bootstrap');

function so_role_filter_sql(array $roles): array {
  $roles = array_values(array_filter(array_map('trim', $roles)));
  if (empty($roles)) return ['', []];
  $placeholders = implode(',', array_fill(0, count($roles), '?'));

  if (auth_roles_enabled()) {
    return [
      " AND EXISTS (
        SELECT 1
        FROM usuarios_roles ur
        JOIN roles r ON r.id = ur.rol_id
        WHERE ur.usuario_id = u.id AND r.slug IN ({$placeholders})
      )",
      $roles
    ];
  }

  return [" AND COALESCE(u.perfil,'') IN ({$placeholders})", $roles];
}

function so_user_options(array $roles = []): array {
  if (!so_table_exists('usuarios')) return [];
  [$extraSql, $params] = so_role_filter_sql($roles);
  try {
    $rows = qall("
      SELECT u.id, u.nombre, u.usuario, COALESCE(u.perfil,'') AS perfil
      FROM usuarios u
      WHERE COALESCE(u.estatus,'ACTIVO') = 'ACTIVO' {$extraSql}
      ORDER BY u.nombre ASC, u.usuario ASC
    ", $params);
  } catch (Throwable $e) {
    $rows = [];
  }

  $opts = ['' => 'Sin asignar'];
  foreach ($rows as $row) {
    $label = trim((string)($row['nombre'] ?? ''));
    if ($label === '') $label = (string)($row['usuario'] ?? ('Usuario '.$row['id']));
    $opts[(string)$row['id']] = $label;
  }
  return $opts;
}

function so_fallecidos_options(): array {
  $opts = ['' => 'Crear nuevo fallecido'];
  try {
    $rows = qall("SELECT id_fallecido, nom_fallecido FROM fallecido WHERE COALESCE(eliminado,0)=0 ORDER BY nom_fallecido ASC");
  } catch (Throwable $e) {
    $rows = [];
  }
  foreach ($rows as $row) {
    $opts[(string)$row['id_fallecido']] = $row['nom_fallecido'];
  }
  return $opts;
}

function so_contratos_futuros_options(): array {
  if (!so_table_exists('futuro_contratos')) return ['' => 'No aplica'];
  $opts = ['' => 'No aplica'];
  try {
    $rows = qall("SELECT id_contrato FROM futuro_contratos ORDER BY id_contrato DESC LIMIT 300");
  } catch (Throwable $e) {
    $rows = [];
  }
  foreach ($rows as $row) {
    $opts[(string)$row['id_contrato']] = 'Contrato #'.$row['id_contrato'];
  }
  return $opts;
}

function so_servicio_estatus_options(): array {
  return [
    'registrado' => 'Registrado',
    'preparacion' => 'Preparacion',
    'en_proceso' => 'En proceso',
    'finalizado' => 'Finalizado',
    'cancelado' => 'Cancelado',
  ];
}

function so_servicio_activos_sql(): string {
  return "COALESCE(s.estatus,'registrado') NOT IN ('finalizado','cancelado','cerrado')";
}

function so_servicio_cerrados_sql(): string {
  return "COALESCE(s.estatus,'registrado') IN ('finalizado','cancelado','cerrado')";
}

function so_servicio_estatus_norm(?string $estatus): string {
  $estatus = trim((string)$estatus);
  if ($estatus === '') return 'registrado';
  if ($estatus === 'abierto') return 'registrado';
  if ($estatus === 'cerrado') return 'finalizado';
  return $estatus;
}

function so_servicio_badge(string $estatus): string {
  return match (so_servicio_estatus_norm($estatus)) {
    'registrado' => 'secondary',
    'preparacion' => 'info text-dark',
    'en_proceso' => 'warning text-dark',
    'finalizado' => 'success',
    'cancelado' => 'danger',
    default => 'secondary',
  };
}

function so_servicio_tipo_pago_options(): array {
  return [
    'efectivo' => 'Efectivo',
    'transferencia' => 'Transferencia',
    'tarjeta' => 'Tarjeta',
    'cheque' => 'Cheque',
    'otro' => 'Otro',
  ];
}

function so_servicio_pago_tipo_options(): array {
  return [
    'anticipo' => 'Anticipo',
    'abono' => 'Abono',
    'liquidacion' => 'Liquidacion',
    'devolucion' => 'Devolucion',
  ];
}

function so_lugar_tipo_options(): array {
  return [
    'capilla' => 'Capilla',
    'domicilio' => 'Domicilio',
    'iglesia' => 'Iglesia',
    'otro' => 'Otro',
  ];
}

function so_now_local(): string {
  return date('Y-m-d H:i:s');
}

function so_now_input(): string {
  return date('Y-m-d\TH:i');
}

function so_datetime_to_input(?string $value): string {
  if (!$value) return '';
  $ts = strtotime($value);
  if (!$ts) return '';
  return date('Y-m-d\TH:i', $ts);
}

function so_parse_checkbox($value): int {
  return $value ? 1 : 0;
}

function so_parse_money($value): float {
  return round((float)str_replace(',', '', (string)$value), 2);
}

function so_json(array $data): string {
  return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function so_current_user_id(): ?int {
  $user = current_user();
  if (!$user) return null;
  return isset($user['id']) ? (int)$user['id'] : null;
}

function so_log_bitacora(int $id_servicio, string $tipo, string $descripcion, array $meta = []): void {
  so_bootstrap();
  try {
    q(
      "INSERT INTO servicio_bitacora (id_servicio, tipo_evento, descripcion, id_usuario, metadata_json, created_at)
       VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP())",
      [$id_servicio, $tipo, $descripcion, so_current_user_id(), so_json($meta)]
    );
  } catch (Throwable $e) {
  }
  try {
    $user = current_user();
    q(
      "INSERT INTO futuro_logs (tabla, accion, usuario, detalle, created_at)
       VALUES ('servicios_operativos', ?, ?, ?, CURRENT_TIMESTAMP())",
      [$tipo, $user['usuario'] ?? 'sistema', so_json(['id_servicio' => $id_servicio, 'descripcion' => $descripcion] + $meta)]
    );
  } catch (Throwable $e) {
  }
}

function so_generar_folio(PDO $pdo): string {
  $prefix = 'SERV-'.date('Ym').'-';
  $stmt = $pdo->prepare("SELECT folio FROM servicios WHERE folio LIKE ? ORDER BY folio DESC LIMIT 1");
  $stmt->execute([$prefix.'%']);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && preg_match('/(\d{4})$/', $row['folio'], $match)) {
    $seq = (int)$match[1] + 1;
  } else {
    $seq = 1;
  }
  return $prefix.str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function so_recalcular_saldo(int $id_servicio): void {
  $servicio = qone("SELECT monto_total FROM servicios WHERE id_servicio=?", [$id_servicio]);
  if (!$servicio) return;
  $pagado = qone("SELECT COALESCE(SUM(monto),0) AS total FROM servicio_pagos WHERE id_servicio=?", [$id_servicio]);
  $total = (float)($servicio['monto_total'] ?? 0);
  $abonado = (float)($pagado['total'] ?? 0);
  $saldo = max(0, round($total - $abonado, 2));
  q("UPDATE servicios SET anticipo=?, saldo_pendiente=?, updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?",
    [$abonado, $saldo, $id_servicio]);
}

function so_servicio_fetch(int $id_servicio): ?array {
  so_bootstrap();
  $sql = "
    SELECT s.*, sf.id_fallecido,
           COALESCE(f.nom_fallecido,'') AS fallecido,
           COALESCE(uv.nombre, uv.usuario, '') AS vendedor_nombre,
           COALESCE(ua.nombre, ua.usuario, '') AS auxiliar_nombre
    FROM servicios s
    LEFT JOIN servicio_fallecido sf ON sf.id_servicio=s.id_servicio
    LEFT JOIN fallecido f ON f.id_fallecido=sf.id_fallecido
    LEFT JOIN usuarios uv ON uv.id = s.id_usuario_vendedor
    LEFT JOIN usuarios ua ON ua.id = s.id_usuario_auxiliar
    WHERE s.id_servicio=? AND COALESCE(s.eliminado,0)=0
    LIMIT 1
  ";
  $row = qone($sql, [$id_servicio]);
  if (!$row) return null;
  $row['estatus_norm'] = so_servicio_estatus_norm($row['estatus'] ?? '');
  return $row;
}

function so_servicio_pagos(int $id_servicio): array {
  so_bootstrap();
  try {
    return qall("
      SELECT sp.*,
             COALESCE(u.nombre, u.usuario, 'Sistema') AS usuario_nombre
      FROM servicio_pagos sp
      LEFT JOIN usuarios u ON u.id = sp.id_usuario
      WHERE sp.id_servicio=?
      ORDER BY sp.fecha_pago DESC, sp.id_pago DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_asignacion(int $id_servicio): ?array {
  so_bootstrap();
  return qone("
    SELECT sa.*, COALESCE(u.nombre, u.usuario, '') AS auxiliar_nombre
    FROM servicio_asignacion sa
    LEFT JOIN usuarios u ON u.id = sa.id_usuario_auxiliar
    WHERE sa.id_servicio=?
    ORDER BY sa.id_asignacion DESC
    LIMIT 1
  ", [$id_servicio]);
}

function so_servicio_seguimiento(int $id_servicio): ?array {
  so_bootstrap();
  return qone("
    SELECT ss.*, COALESCE(u.nombre, u.usuario, '') AS confirma_nombre
    FROM servicio_seguimiento ss
    LEFT JOIN usuarios u ON u.id = ss.confirmado_por_usuario
    WHERE ss.id_servicio=?
    ORDER BY ss.id_seguimiento DESC
    LIMIT 1
  ", [$id_servicio]);
}

function so_servicio_cierre(int $id_servicio): ?array {
  so_bootstrap();
  return qone("
    SELECT sc.*,
           COALESCE(ua.nombre, ua.usuario, '') AS auxiliar_nombre,
           COALESCE(um.nombre, um.usuario, '') AS administrador_nombre
    FROM servicio_cierre sc
    LEFT JOIN usuarios ua ON ua.id = sc.id_usuario_auxiliar
    LEFT JOIN usuarios um ON um.id = sc.id_usuario_administrador
    WHERE sc.id_servicio=?
    ORDER BY sc.id_cierre DESC
    LIMIT 1
  ", [$id_servicio]);
}

function so_servicio_bitacora(int $id_servicio): array {
  so_bootstrap();
  try {
    return qall("
      SELECT sb.*,
             COALESCE(u.nombre, u.usuario, 'Sistema') AS usuario_nombre
      FROM servicio_bitacora sb
      LEFT JOIN usuarios u ON u.id = sb.id_usuario
      WHERE sb.id_servicio=?
      ORDER BY sb.id_bitacora DESC
      LIMIT 50
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_totales(int $id_servicio): array {
  $svc = so_servicio_fetch($id_servicio);
  $pagado = qone("SELECT COALESCE(SUM(monto),0) AS total FROM servicio_pagos WHERE id_servicio=?", [$id_servicio]);
  $total = (float)($svc['monto_total'] ?? 0);
  $abonado = (float)($pagado['total'] ?? 0);
  return [
    'total' => $total,
    'pagado' => $abonado,
    'saldo' => max(0, round($total - $abonado, 2)),
  ];
}

function so_fallecido_resolver(PDO $pdo, int $id_fallecido, string $nom_fallecido): int {
  if ($id_fallecido > 0) return $id_fallecido;
  $nom_fallecido = trim($nom_fallecido);
  if ($nom_fallecido === '') {
    throw new RuntimeException('Debes seleccionar o registrar al fallecido.');
  }
  q("INSERT INTO fallecido (nom_fallecido, fecha) VALUES (?, CURRENT_DATE())", [$nom_fallecido]);
  return (int)$pdo->lastInsertId();
}

function so_guardar_pago(PDO $pdo, int $id_servicio, float $monto, string $forma_pago, string $tipo_pago, ?string $referencia, ?string $observaciones, ?string $fecha_pago = null): int {
  $fecha_pago = $fecha_pago ?: so_now_local();
  q(
    "INSERT INTO servicio_pagos (id_servicio, fecha_pago, monto, forma_pago, referencia, tipo_pago, observaciones, id_usuario, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())",
    [$id_servicio, $fecha_pago, $monto, $forma_pago ?: null, $referencia ?: null, $tipo_pago, $observaciones ?: null, so_current_user_id()]
  );
  $idPago = (int)$pdo->lastInsertId();
  so_recalcular_saldo($id_servicio);
  so_log_bitacora($id_servicio, 'pago', 'Pago registrado', ['id_pago' => $idPago, 'monto' => $monto, 'tipo_pago' => $tipo_pago]);
  return $idPago;
}

function so_servicios_cajas_disponibles(): array {
  try {
    return qall("SELECT codigo, modelo, estado FROM cajas WHERE COALESCE(eliminado,0)=0 AND estado IN ('nuevo','reciclado') ORDER BY modelo ASC");
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicios_equipo_disponible(): array {
  try {
    return qall("SELECT id_equipo, equipo FROM equipos WHERE COALESCE(eliminado,0)=0 AND estatus='disponible' ORDER BY equipo ASC");
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicios_articulos_disponibles(): array {
  try {
    return qall("SELECT id, articulo, existencias FROM articulos WHERE COALESCE(eliminado,0)=0 AND COALESCE(existencias,0) > 0 ORDER BY articulo ASC");
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_cajas_asignadas(int $id_servicio): array {
  try {
    return qall("
      SELECT m.codigo, c.modelo, m.created_at, c.es_rentado, c.reciclado
      FROM cajas_movimientos m
      JOIN cajas c ON c.codigo = m.codigo
      WHERE m.id_servicio=? AND m.tipo='asignacion'
      ORDER BY m.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_equipos_asignados(int $id_servicio): array {
  try {
    return qall("
      SELECT em.id_equipo, e.equipo, em.created_at
      FROM equipos_movimientos em
      JOIN equipos e ON e.id_equipo = em.id_equipo
      WHERE em.id_servicio=? AND em.tipo='asignacion'
      ORDER BY em.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_servicio_articulos_asignados(int $id_servicio): array {
  try {
    return qall("
      SELECT s.id_articulo, a.articulo, s.cantidad, s.responsable, s.created_at
      FROM articulos_salida_servicio s
      JOIN articulos a ON a.id=s.id_articulo
      WHERE s.id_servicio=?
      ORDER BY s.id_salida DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_caja_activa(string $codigo): ?array {
  return qone("SELECT codigo, modelo, estado, reciclado, es_rentado FROM cajas WHERE codigo=? AND COALESCE(eliminado,0)=0", [$codigo]);
}

function so_caja_en_uso(string $codigo): bool {
  $row = qone("SELECT tipo FROM cajas_movimientos WHERE codigo=? ORDER BY id_mov DESC LIMIT 1", [$codigo]);
  return $row && $row['tipo'] === 'asignacion';
}

function so_registrar_mov_caja(PDO $pdo, string $codigo, string $tipo, ?int $id_servicio = null, ?string $notas = null): void {
  $stmt = $pdo->prepare("INSERT INTO cajas_movimientos (codigo,tipo,id_servicio,notas) VALUES (?,?,?,?)");
  $stmt->execute([$codigo, $tipo, $id_servicio, $notas]);
}

function so_registrar_mov_equipo(PDO $pdo, string $id_equipo, string $tipo, ?int $id_servicio = null, ?string $origen = null, ?string $destino = null, ?string $notas = null): void {
  $stmt = $pdo->prepare("INSERT INTO equipos_movimientos (id_equipo,tipo,id_servicio,origen,destino,notas) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$id_equipo, $tipo, $id_servicio, $origen, $destino, $notas]);
}

function so_registrar_articulo_servicio(PDO $pdo, int $id_servicio, int $id_articulo, int $cantidad, string $responsable, ?string $notas = null): void {
  $articulo = qone("SELECT id, articulo, existencias FROM articulos WHERE id=? AND COALESCE(eliminado,0)=0 FOR UPDATE", [$id_articulo]);
  if (!$articulo) throw new RuntimeException('Articulo no encontrado.');
  if ($cantidad <= 0) throw new RuntimeException('La cantidad debe ser mayor a cero.');
  if ((int)$articulo['existencias'] < $cantidad) throw new RuntimeException('No hay existencias suficientes.');

  q(
    "INSERT INTO articulos_salida_servicio (id_servicio,id_articulo,cantidad,responsable) VALUES (?,?,?,?)",
    [$id_servicio, $id_articulo, $cantidad, $responsable]
  );
  q("UPDATE articulos SET existencias=existencias-?, updated_at=CURRENT_TIMESTAMP() WHERE id=?", [$cantidad, $id_articulo]);
  q(
    "INSERT INTO articulos_movimientos (id_articulo,tipo,cantidad,referencia,origen,destino,notas)
     VALUES (?, 'salida', ?, ?, NULL, NULL, ?)",
    [$id_articulo, $cantidad, 'servicio:'.$id_servicio, $notas ?: null]
  );
  so_log_bitacora($id_servicio, 'salida_articulo', 'Salida de articulo registrada', [
    'id_articulo' => $id_articulo,
    'cantidad' => $cantidad,
    'responsable' => $responsable,
  ]);
}

function so_guardar_asignacion(int $id_servicio, array $data): void {
  so_bootstrap();
  $existente = so_servicio_asignacion($id_servicio);
  if ($existente) {
    q(
      "UPDATE servicio_asignacion
       SET fecha_hora_inicio=?, lugar_tipo=?, lugar_detalle=?, id_usuario_auxiliar=?, id_vehiculo=?, id_sala=?, instrucciones=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_servicio=?",
      [
        $data['fecha_hora_inicio'] ?: null,
        $data['lugar_tipo'] ?: null,
        $data['lugar_detalle'] ?: null,
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_vehiculo'] ?: null,
        $data['id_sala'] ?: null,
        $data['instrucciones'] ?: null,
        $id_servicio,
      ]
    );
  } else {
    q(
      "INSERT INTO servicio_asignacion (id_servicio, fecha_hora_inicio, lugar_tipo, lugar_detalle, id_usuario_auxiliar, id_vehiculo, id_sala, instrucciones, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [
        $id_servicio,
        $data['fecha_hora_inicio'] ?: null,
        $data['lugar_tipo'] ?: null,
        $data['lugar_detalle'] ?: null,
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_vehiculo'] ?: null,
        $data['id_sala'] ?: null,
        $data['instrucciones'] ?: null,
        so_current_user_id(),
      ]
    );
  }

  q(
    "UPDATE servicios
     SET fecha_hora_programada=?, lugar_tipo=?, lugar_detalle=?, id_vehiculo=?, id_sala=?, id_usuario_auxiliar=?, estatus='preparacion', updated_at=CURRENT_TIMESTAMP()
     WHERE id_servicio=?",
    [
      $data['fecha_hora_inicio'] ?: null,
      $data['lugar_tipo'] ?: null,
      $data['lugar_detalle'] ?: null,
      $data['id_vehiculo'] ?: null,
      $data['id_sala'] ?: null,
      $data['id_usuario_auxiliar'] ?: null,
      $id_servicio,
    ]
  );

  so_log_bitacora($id_servicio, 'asignacion', 'Asignacion operativa actualizada', [
    'fecha_hora_inicio' => $data['fecha_hora_inicio'] ?: null,
    'lugar_tipo' => $data['lugar_tipo'] ?: null,
    'id_usuario_auxiliar' => $data['id_usuario_auxiliar'] ?: null,
  ]);
}

function so_guardar_seguimiento(int $id_servicio, array $data): string {
  so_bootstrap();
  $existente = so_servicio_seguimiento($id_servicio);
  if ($existente) {
    q(
      "UPDATE servicio_seguimiento
       SET hora_inicio_real=?, hora_fin_real=?, observaciones_iniciales=?, observaciones_finales=?, incidencias=?, confirmado_por_auxiliar=?, confirmado_por_usuario=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_servicio=?",
      [
        $data['hora_inicio_real'] ?: null,
        $data['hora_fin_real'] ?: null,
        $data['observaciones_iniciales'] ?: null,
        $data['observaciones_finales'] ?: null,
        $data['incidencias'] ?: null,
        $data['confirmado_por_auxiliar'],
        $data['confirmado_por_usuario'] ?: null,
        $id_servicio,
      ]
    );
  } else {
    q(
      "INSERT INTO servicio_seguimiento (id_servicio, hora_inicio_real, hora_fin_real, observaciones_iniciales, observaciones_finales, incidencias, confirmado_por_auxiliar, confirmado_por_usuario)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
      [
        $id_servicio,
        $data['hora_inicio_real'] ?: null,
        $data['hora_fin_real'] ?: null,
        $data['observaciones_iniciales'] ?: null,
        $data['observaciones_finales'] ?: null,
        $data['incidencias'] ?: null,
        $data['confirmado_por_auxiliar'],
        $data['confirmado_por_usuario'] ?: null,
      ]
    );
  }

  $estatus = 'preparacion';
  if (!empty($data['hora_fin_real'])) {
    $estatus = 'finalizado';
  } elseif (!empty($data['hora_inicio_real'])) {
    $estatus = 'en_proceso';
  }
  q("UPDATE servicios SET estatus=?, updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?", [$estatus, $id_servicio]);
  so_log_bitacora($id_servicio, 'seguimiento', 'Seguimiento operativo actualizado', ['estatus' => $estatus]);
  return $estatus;
}

function so_pendientes_devolucion_cajas(int $id_servicio): array {
  try {
    return qall("
      SELECT m.codigo, c.modelo, c.es_rentado, c.reciclado
      FROM cajas_movimientos m
      JOIN cajas c ON c.codigo=m.codigo
      LEFT JOIN cajas_movimientos d
        ON d.codigo=m.codigo AND d.id_servicio=m.id_servicio AND d.tipo='devolucion'
      WHERE m.id_servicio=? AND m.tipo='asignacion' AND d.id_mov IS NULL
      ORDER BY m.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_pendientes_devolucion_equipos(int $id_servicio): array {
  try {
    return qall("
      SELECT em.id_equipo, e.equipo
      FROM equipos_movimientos em
      JOIN equipos e ON e.id_equipo=em.id_equipo
      LEFT JOIN equipos_movimientos d
        ON d.id_equipo=em.id_equipo AND d.id_servicio=em.id_servicio AND d.tipo='devolucion'
      WHERE em.id_servicio=? AND em.tipo='asignacion' AND d.id_mov IS NULL
      ORDER BY em.id_mov DESC
    ", [$id_servicio]);
  } catch (Throwable $e) {
    return [];
  }
}

function so_cerrar_servicio_recursos(PDO $pdo, int $id_servicio): void {
  $cajas = so_pendientes_devolucion_cajas($id_servicio);
  foreach ($cajas as $caja) {
    so_registrar_mov_caja($pdo, $caja['codigo'], 'devolucion', $id_servicio, 'Cierre de servicio');
    if (!empty($caja['es_rentado'])) {
      q("UPDATE cajas SET reciclado=1, ciclos_uso=ciclos_uso+1, estado='reciclado' WHERE codigo=?", [$caja['codigo']]);
    } else {
      q("UPDATE cajas SET estado='fuera_uso' WHERE codigo=?", [$caja['codigo']]);
    }
  }

  $equipos = so_pendientes_devolucion_equipos($id_servicio);
  foreach ($equipos as $equipo) {
    so_registrar_mov_equipo($pdo, $equipo['id_equipo'], 'devolucion', $id_servicio, null, 'almacen', 'Cierre de servicio');
    q("UPDATE equipos SET estatus='disponible', updated_at=CURRENT_TIMESTAMP() WHERE id_equipo=?", [$equipo['id_equipo']]);
  }
}

function so_guardar_cierre(int $id_servicio, array $data): void {
  so_bootstrap();
  $existente = so_servicio_cierre($id_servicio);
  if ($existente) {
    q(
      "UPDATE servicio_cierre
       SET observaciones_cierre=?, articulos_devueltos_json=?, caja_reciclada=?, pago_confirmado=?, auxiliar_confirma=?, id_usuario_auxiliar=?, id_usuario_administrador=?, fecha_cierre=?, updated_at=CURRENT_TIMESTAMP()
       WHERE id_servicio=?",
      [
        $data['observaciones_cierre'] ?: null,
        $data['articulos_devueltos_json'] ?: null,
        $data['caja_reciclada'],
        $data['pago_confirmado'],
        $data['auxiliar_confirma'],
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_usuario_administrador'] ?: null,
        $data['fecha_cierre'] ?: null,
        $id_servicio,
      ]
    );
  } else {
    q(
      "INSERT INTO servicio_cierre (id_servicio, observaciones_cierre, articulos_devueltos_json, caja_reciclada, pago_confirmado, auxiliar_confirma, id_usuario_auxiliar, id_usuario_administrador, fecha_cierre)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [
        $id_servicio,
        $data['observaciones_cierre'] ?: null,
        $data['articulos_devueltos_json'] ?: null,
        $data['caja_reciclada'],
        $data['pago_confirmado'],
        $data['auxiliar_confirma'],
        $data['id_usuario_auxiliar'] ?: null,
        $data['id_usuario_administrador'] ?: null,
        $data['fecha_cierre'] ?: null,
      ]
    );
  }
  q("UPDATE servicios SET estatus='finalizado', closed_at=?, updated_at=CURRENT_TIMESTAMP() WHERE id_servicio=?",
    [$data['fecha_cierre'] ?: so_now_local(), $id_servicio]);
  so_log_bitacora($id_servicio, 'cierre', 'Cierre administrativo registrado', [
    'pago_confirmado' => $data['pago_confirmado'],
    'auxiliar_confirma' => $data['auxiliar_confirma'],
  ]);
}

function so_moneda($value): string {
  return '$'.number_format((float)$value, 2);
}

function so_texto_pdf(string $str): string {
  if (function_exists('mb_convert_encoding')) {
    return mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
  }
  return $str;
}

function so_servicio_resumen_html(int $id_servicio): string {
  $svc = so_servicio_fetch($id_servicio);
  if (!$svc) return '<p>Servicio no encontrado.</p>';

  $asignacion = so_servicio_asignacion($id_servicio);
  $seguimiento = so_servicio_seguimiento($id_servicio);
  $cierre = so_servicio_cierre($id_servicio);
  $totales = so_servicio_totales($id_servicio);
  $articulos = so_servicio_articulos_asignados($id_servicio);
  $cajas = so_servicio_cajas_asignadas($id_servicio);
  $equipos = so_servicio_equipos_asignados($id_servicio);
  $fechaProgramada = $asignacion['fecha_hora_inicio'] ?? ($svc['fecha_hora_programada'] ?? '');
  $lugarTipo = $asignacion['lugar_tipo'] ?? ($svc['lugar_tipo'] ?? '');
  $lugarDetalle = $asignacion['lugar_detalle'] ?? ($svc['lugar_detalle'] ?? '');
  $vehiculo = $asignacion['id_vehiculo'] ?? ($svc['id_vehiculo'] ?? '');
  $sala = $asignacion['id_sala'] ?? ($svc['id_sala'] ?? '');
  $fechaCierre = $cierre['fecha_cierre'] ?? ($svc['closed_at'] ?? '');

  ob_start(); ?>
  <style>
    body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222}
    h1,h2{margin:0 0 10px}
    .muted{color:#666}
    .grid{width:100%;border-collapse:collapse;margin-bottom:14px}
    .grid th,.grid td{border:1px solid #ccc;padding:6px 8px;text-align:left}
    .card{margin-bottom:14px}
  </style>
  <h1>Servicio <?= e($svc['folio'] ?: '#'.$svc['id_servicio']) ?></h1>
  <p class="muted">Fallecido: <?= e($svc['fallecido'] ?: 'No capturado') ?> | Estatus: <?= e(ucwords(str_replace('_', ' ', so_servicio_estatus_norm($svc['estatus'] ?? '')))) ?></p>
  <table class="grid">
    <tr><th>Contratante</th><td><?= e($svc['contratante_nombre']) ?></td><th>Telefono</th><td><?= e($svc['contratante_tel'] ?: '—') ?></td></tr>
    <tr><th>Correo</th><td><?= e($svc['contratante_email'] ?: '—') ?></td><th>Direccion</th><td><?= e($svc['contratante_direccion'] ?: '—') ?></td></tr>
    <tr><th>Tipo</th><td><?= e($svc['tipo_servicio']) ?></td><th>Disposicion</th><td><?= e($svc['tipo_disposicion']) ?></td></tr>
    <tr><th>Origen</th><td><?= e($svc['origen_tipo']) ?></td><th>Contrato futuro</th><td><?= e($svc['id_contrato_futuro'] ?: '—') ?></td></tr>
    <tr><th>Vendedor</th><td><?= e($svc['vendedor_nombre'] ?: '—') ?></td><th>Auxiliar</th><td><?= e($svc['auxiliar_nombre'] ?: '—') ?></td></tr>
    <tr><th>Total</th><td><?= e(so_moneda($totales['total'])) ?></td><th>Pagado / Saldo</th><td><?= e(so_moneda($totales['pagado']).' / '.so_moneda($totales['saldo'])) ?></td></tr>
  </table>
  <div class="card">
    <h2>Preparacion</h2>
    <table class="grid">
      <tr><th>Fecha programada</th><td><?= e($fechaProgramada ?: '—') ?></td><th>Lugar</th><td><?= e(($lugarTipo ?: '—').' '.($lugarDetalle ?: '')) ?></td></tr>
      <tr><th>Vehiculo</th><td><?= e($vehiculo ?: '—') ?></td><th>Sala</th><td><?= e($sala ?: '—') ?></td></tr>
      <tr><th>Instrucciones</th><td colspan="3"><?= e($asignacion['instrucciones'] ?? '—') ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Seguimiento</h2>
    <table class="grid">
      <tr><th>Inicio real</th><td><?= e($seguimiento['hora_inicio_real'] ?? '—') ?></td><th>Fin real</th><td><?= e($seguimiento['hora_fin_real'] ?? '—') ?></td></tr>
      <tr><th>Observaciones iniciales</th><td colspan="3"><?= e($seguimiento['observaciones_iniciales'] ?? '—') ?></td></tr>
      <tr><th>Incidencias</th><td colspan="3"><?= e($seguimiento['incidencias'] ?? '—') ?></td></tr>
      <tr><th>Observaciones finales</th><td colspan="3"><?= e($seguimiento['observaciones_finales'] ?? '—') ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Recursos asignados</h2>
    <table class="grid">
      <tr><th>Ataudes</th><td><?= e(implode(', ', array_map(fn($r) => $r['codigo'].' '.$r['modelo'], $cajas)) ?: '—') ?></td></tr>
      <tr><th>Equipos</th><td><?= e(implode(', ', array_map(fn($r) => $r['equipo'].' '.$r['id_equipo'], $equipos)) ?: '—') ?></td></tr>
      <tr><th>Articulos</th><td><?= e(implode(', ', array_map(fn($r) => $r['articulo'].' x'.$r['cantidad'], $articulos)) ?: '—') ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Cierre</h2>
    <table class="grid">
      <tr><th>Fecha cierre</th><td><?= e($fechaCierre ?: '—') ?></td><th>Pago confirmado</th><td><?= !empty($cierre['pago_confirmado']) ? 'Si' : 'No' ?></td></tr>
      <tr><th>Observaciones</th><td colspan="3"><?= e($cierre['observaciones_cierre'] ?? '—') ?></td></tr>
    </table>
  </div>
  <?php
  return ob_get_clean();
}
