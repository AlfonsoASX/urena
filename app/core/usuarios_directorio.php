<?php

/**
 * Directorio organizacional. Identidad, puesto y jerarquia viven en usuarios.
 * Los roles y permisos solamente controlan acceso al sistema.
 */

function usuarios_directorio_puestos(): array {
  return [
    'administrador' => 'Administrador',
    'coordinador' => 'Coordinador',
    'gerente' => 'Gerente',
    'lider' => 'Lider',
    'vendedor' => 'Vendedor',
    'cobrador' => 'Cobrador',
    'auxiliar' => 'Auxiliar',
    'operario' => 'Operario',
    'recepcionista' => 'Recepcionista',
    'otro' => 'Otro',
  ];
}

function usuarios_directorio_schema_listo(): bool {
  return db_column_exists('usuarios', 'puesto')
    && db_column_exists('usuarios', 'jefe_directo_id')
    && db_column_exists('usuarios', 'acceso_sistema');
}

function usuarios_directorio_usuario(int $idUsuario, bool $soloActivo = false): ?array {
  if ($idUsuario <= 0 || !usuarios_directorio_schema_listo()) return null;
  $activo = $soloActivo ? " AND UPPER(COALESCE(estatus,'ACTIVO'))='ACTIVO'" : '';
  return qone(
    "SELECT id,usuario,nombre,puesto,jefe_directo_id,acceso_sistema,estatus
     FROM usuarios WHERE id=?{$activo}",
    [$idUsuario]
  );
}

function usuarios_directorio_nombre(?int $idUsuario, string $default = 'Sin asignar'): string {
  if (!$idUsuario) return $default;
  $row = usuarios_directorio_usuario($idUsuario);
  $nombre = trim((string)($row['nombre'] ?? ''));
  return $nombre !== '' ? $nombre : $default;
}

function usuarios_directorio_activos(array $puestos = []): array {
  if (!usuarios_directorio_schema_listo()) return [];
  $puestos = array_values(array_intersect(array_keys(usuarios_directorio_puestos()), array_unique($puestos)));
  $params = [];
  $where = "UPPER(COALESCE(u.estatus,'ACTIVO'))='ACTIVO'";
  if ($puestos) {
    $where .= ' AND u.puesto IN ('.implode(',', array_fill(0, count($puestos), '?')).')';
    $params = $puestos;
  }
  return qall(
    "SELECT u.id,u.usuario,u.nombre,u.puesto,u.jefe_directo_id,u.acceso_sistema,u.estatus,
            j.nombre AS jefe_nombre,j.puesto AS jefe_puesto
     FROM usuarios u
     LEFT JOIN usuarios j ON j.id=u.jefe_directo_id
     WHERE {$where}
     ORDER BY u.nombre,u.id",
    $params
  );
}

function usuarios_directorio_opciones(array $puestos = []): array {
  $out = [];
  foreach (usuarios_directorio_activos($puestos) as $row) {
    $out[(int)$row['id']] = trim((string)$row['nombre']);
  }
  return $out;
}

function usuarios_directorio_puesto_jefe_esperado(string $puesto): ?string {
  return match ($puesto) {
    'vendedor' => 'lider',
    'lider' => 'gerente',
    default => null,
  };
}

function usuarios_directorio_validar_asignacion(
  int $idUsuario,
  string $puesto,
  ?int $idJefe,
  bool $usuarioDebeExistir = true
): array {
  $errores = [];
  if (!isset(usuarios_directorio_puestos()[$puesto])) {
    return ['Selecciona un puesto valido.'];
  }
  if ($usuarioDebeExistir && !usuarios_directorio_usuario($idUsuario)) {
    $errores[] = 'El usuario no existe.';
  }
  if ($idJefe !== null && $idJefe > 0 && $idJefe === $idUsuario) {
    $errores[] = 'Una persona no puede ser su propio jefe.';
  }

  $esperado = usuarios_directorio_puesto_jefe_esperado($puesto);
  if ($esperado !== null && (!$idJefe || $idJefe <= 0)) {
    $errores[] = $puesto === 'vendedor'
      ? 'El vendedor debe tener un lider como jefe directo.'
      : 'El lider debe tener un gerente como jefe directo.';
  }
  if ($idJefe && $idJefe > 0) {
    $jefe = usuarios_directorio_usuario($idJefe, true);
    if (!$jefe) {
      $errores[] = 'El jefe directo debe ser un usuario activo.';
    } elseif ($esperado !== null && ($jefe['puesto'] ?? '') !== $esperado) {
      $errores[] = 'El jefe directo de '.($puesto === 'vendedor' ? 'un vendedor' : 'un lider').' debe tener puesto de '.($esperado === 'lider' ? 'lider' : 'gerente').'.';
    }

    $visitados = [$idUsuario => true];
    $cursor = $idJefe;
    for ($nivel = 0; $nivel < 100 && $cursor > 0; $nivel++) {
      if (isset($visitados[$cursor])) {
        $errores[] = 'La jerarquia seleccionada forma un ciclo.';
        break;
      }
      $visitados[$cursor] = true;
      $fila = usuarios_directorio_usuario($cursor);
      $cursor = (int)($fila['jefe_directo_id'] ?? 0);
    }
  }
  return array_values(array_unique($errores));
}

function usuarios_directorio_jerarquia_comercial(int $idVendedor): array {
  $result = [
    'id_vendedor' => $idVendedor,
    'id_lider' => null,
    'id_gerente' => null,
    'vendedor' => null,
    'lider' => null,
    'gerente' => null,
    'errores' => [],
  ];
  $vendedor = usuarios_directorio_usuario($idVendedor, true);
  $result['vendedor'] = $vendedor;
  if (!$vendedor || ($vendedor['puesto'] ?? '') !== 'vendedor') {
    $result['errores'][] = 'Selecciona un vendedor activo del directorio de usuarios.';
    return $result;
  }

  $idLider = (int)($vendedor['jefe_directo_id'] ?? 0);
  $lider = usuarios_directorio_usuario($idLider, true);
  $result['id_lider'] = $idLider ?: null;
  $result['lider'] = $lider;
  if (!$lider || ($lider['puesto'] ?? '') !== 'lider') {
    $result['errores'][] = 'El vendedor no tiene un lider activo configurado como jefe directo.';
    return $result;
  }

  $idGerente = (int)($lider['jefe_directo_id'] ?? 0);
  $gerente = usuarios_directorio_usuario($idGerente, true);
  $result['id_gerente'] = $idGerente ?: null;
  $result['gerente'] = $gerente;
  if (!$gerente || ($gerente['puesto'] ?? '') !== 'gerente') {
    $result['errores'][] = 'El lider no tiene un gerente activo configurado como jefe directo.';
  }
  if ($idVendedor === $idLider || $idVendedor === $idGerente || $idLider === $idGerente) {
    $result['errores'][] = 'La jerarquia comercial contiene una asignacion circular.';
  }
  $result['errores'] = array_values(array_unique($result['errores']));
  return $result;
}

function usuarios_directorio_validar_dependientes(int $idUsuario, string $puesto, bool $activo): array {
  if ($idUsuario <= 0 || !usuarios_directorio_schema_listo()) return [];
  $dependientes = qall(
    "SELECT id,nombre,puesto FROM usuarios
     WHERE jefe_directo_id=? AND UPPER(COALESCE(estatus,'ACTIVO'))='ACTIVO'",
    [$idUsuario]
  );
  $errores = [];
  foreach ($dependientes as $dependiente) {
    $puestoDependiente = (string)($dependiente['puesto'] ?? '');
    $puestoRequerido = usuarios_directorio_puesto_jefe_esperado($puestoDependiente);
    if (!$activo || ($puestoRequerido !== null && $puesto !== $puestoRequerido)) {
      $errores[] = 'No puedes inactivar o cambiar el puesto mientras '.trim((string)$dependiente['nombre']).' siga asignado como subordinado activo.';
    }
  }
  return array_values(array_unique($errores));
}

function usuarios_directorio_validar_acceso(
  bool $acceso,
  string $usuario,
  ?string $passwordHash,
  array $roles
): array {
  if (!$acceso) return [];
  $errores = [];
  if (trim($usuario) === '') $errores[] = 'El usuario es obligatorio cuando se habilita el acceso.';
  if (!$passwordHash) $errores[] = 'La contrasena es obligatoria cuando se habilita el acceso.';
  if (!$roles) $errores[] = 'Asigna por lo menos un rol cuando se habilita el acceso.';
  return $errores;
}
