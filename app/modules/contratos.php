<?php
require_once __DIR__.'/../core/futuros_promociones.php';

if (!user_has_role(['admin', 'vendedor', 'supervisor'])) redirect('auth.login');

    function __nombre_mes(int $mes): string {
  $meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
  ];
  return $meses[(int)$mes] ?? '';
}

function __personal_activo() {
  return qall("SELECT id_personal, CONCAT(nombre,' ',apellido_p,' ',apellido_m) AS nombre
               FROM futuro_personal
               WHERE (estatus IS NULL OR estatus NOT IN ('baja','inactivo'))
               ORDER BY nombre ASC");
}

function __promociones_activas() {
  $rows = futuros_promociones_catalogo(true);
  foreach ($rows as &$row) {
    $row['concepto'] = futuros_promociones_label($row['codigo'] ?? '', $row['concepto'] ?? '');
  }
  unset($row);
  return $rows;
}

function __vendedor_actual_id(int $id_contrato): int {
  $r = qone("SELECT id_personal FROM futuro_contrato_vendedor WHERE id_contrato=? ORDER BY id_cont_vend DESC LIMIT 1", [$id_contrato]);
  if (!$r) {
    $r = qone("SELECT id_personal FROM futuro_contrato_vendedor WHERE id_contrato=? ORDER BY fecha_registro DESC LIMIT 1", [$id_contrato]);
  }
  return (int)($r['id_personal'] ?? 0);
}

function __fecha_es($fecha): string {
  if (!$fecha) return '';
  try {
    $dt = ($fecha instanceof DateTime) ? $fecha : new DateTime(is_string($fecha) ? $fecha : (string)$fecha);
  } catch (Exception $e) {
    return '';
  }

  if (class_exists('IntlDateFormatter')) {
    $fmt = new IntlDateFormatter('es_MX', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'America/Mexico_City', IntlDateFormatter::GREGORIAN, "d 'de' MMMM 'de' y");
    $out = $fmt->format($dt);
    if ($out !== false) return $out;
  }

  $meses = [
    1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
  ];
  $d = (int)$dt->format('d');
  $m = (int)$dt->format('n');
  $y = (int)$dt->format('Y');
  return $d.' de '.($meses[$m] ?? $dt->format('F')).' de '.$y;
}

function __contrato_estatus_normalizado($v): string {
  $s = strtoupper(trim((string)$v));
  if (!in_array($s, ['ACTIVO','INACTIVO','USADO','PAGADO'], true)) return 'ACTIVO';
  return $s;
}

function __post_str($k, $def=''): string {
  $v = $_POST[$k] ?? $def;
  if (is_array($v)) return $def;
  return trim((string)$v);
}

function __post_float($k, $def=0.0): float {
  $v = $_POST[$k] ?? $def;
  if ($v === '' || $v === null) return (float)$def;
  return (float)$v;
}

function __post_int($k, $def=0): int {
  $v = $_POST[$k] ?? $def;
  if ($v === '' || $v === null) return (int)$def;
  return (int)$v;
}

switch ($action) {

  case 'listar2':
    $filtro = $_GET['filtro'] ?? 'activos';
    
    $mapa_estatus = [
        'activos' => 'ACTIVO',
        'usados'  => 'USADO',
        'pagados' => 'PAGADO',
        'bajas'   => 'INACTIVO'
    ];
    
    $estatus_db = $mapa_estatus[$filtro] ?? 'ACTIVO';

    $rows = qall("
      SELECT c.id_contrato,
             t.nombre, t.apellido_p, t.apellido_m,
             d.calle, d.num_ext, d.num_int, d.colonia, d.municipio,
             c.costo_final, c.estatus,
             (
               SELECT CASE
                        WHEN p.id_mos_cat_com IS NULL THEN NULL
                        WHEN COALESCE(p.codigo,'') <> '' AND COALESCE(p.concepto,'') <> '' AND p.codigo <> p.concepto
                          THEN CONCAT(p.codigo, ' - ', p.concepto)
                        ELSE COALESCE(NULLIF(p.codigo,''), p.concepto)
                      END
               FROM futuro_cont_cat_com fcc
               JOIN futuro_mostrar_catalogo_promociones p ON p.id_mos_cat_com = fcc.id_mos_cat_com
               WHERE fcc.id_contrato = c.id_contrato
               ORDER BY fcc.id_cont_cat_com DESC
               LIMIT 1
             ) AS promocion,
             (
               SELECT COUNT(*)
               FROM futuro_abonos a
               WHERE a.id_contrato = c.id_contrato
             ) AS pagos_hechos
      FROM futuro_contratos c
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titulares t ON t.id_titular = tc.id_titular
      LEFT JOIN titular_dom td ON td.id_titular = t.id_titular
      LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
      WHERE c.estatus = ?
      ORDER BY c.id_contrato DESC
    ", [$estatus_db]);

    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 m-0">Contratos</h1>
      <a href="?r=contratos.nuevo" class="btn btn-primary btn-sm">➕ Nuevo contrato</a>
    </div>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'activos') ? 'active' : '' ?>" href="?r=contratos.listar&filtro=activos">
            TODOS
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'usados') ? 'active' : '' ?>" href="?r=contratos.listar&filtro=usados">
            YA SE USARON
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'pagados') ? 'active' : '' ?>" href="?r=contratos.listar&filtro=pagados">
            PAGADOS
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'bajas') ? 'active link-secondary' : 'link-secondary' ?>" href="?r=contratos.listar&filtro=bajas">
            BAJAS
        </a>
      </li>
    </ul>

    <div class="table-responsive">
      <table class="table table-striped table-hover table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Titular</th>
            <th>Dirección</th>
            <th>Costo final</th>
            <th>Estatus</th>
            <th style="width:340px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><code><?= e($r['id_contrato']) ?></code></td>
            <td><?= e(trim(($r['nombre'] ?? '').' '.($r['apellido_p'] ?? '').' '.($r['apellido_m'] ?? ''))) ?></td>
            <td><?= e(trim(($r['calle'] ?? '')." #".($r['num_ext'] ?? '').(($r['num_int'] ?? '') ? " Int. ".$r['num_int'] : "").", ".($r['colonia'] ?? '').", ".($r['municipio'] ?? ''))) ?></td>
            <td>$<?= number_format((float)($r['costo_final'] ?? 0), 2) ?></td>
            <td>
                <?php
                $bg = match($r['estatus'] ?? '') {
                    'ACTIVO' => 'success',
                    'PAGADO' => 'info',
                    'USADO'  => 'primary',
                    'INACTIVO' => 'secondary',
                    default => 'secondary'
                };
                ?>
                <span class="badge bg-<?= $bg ?>"><?= e($r['estatus'] ?? 'ACTIVO') ?></span>
            </td>
            <td class="d-flex flex-wrap gap-2">
              <a href="?r=contratos.editar&id_contrato=<?= (int)$r['id_contrato'] ?>" class="btn btn-sm btn-outline-primary">✏️ Editar</a>
              <a href="?r=contratos.ver&id_contrato=<?= (int)$r['id_contrato'] ?>" class="btn btn-sm btn-outline-secondary">👁️ Ver</a>
              <a href="?r=contratos.pdf&id_contrato=<?= (int)$r['id_contrato'] ?>" class="btn btn-sm btn-outline-danger">📄 PDF</a>
              
              <?php if (($r['estatus'] ?? '') === 'PAGADO'): ?>
                <a href="?r=contratos.titulo_pdf&id_contrato=<?= (int)$r['id_contrato'] ?>" target="_blank" class="btn btn-sm btn-warning" title="Imprimir Título de Propiedad">📜 Título</a>
              <?php endif; ?>

              <?php if (($r['estatus'] ?? '') !== 'INACTIVO'): ?>
              <form method="post" action="?r=contratos.eliminar&id_contrato=<?= (int)$r['id_contrato'] ?>" class="m-0 p-0 d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-dark"
                        onclick="return confirm('¿Desactivar contrato #<?= (int)$r['id_contrato'] ?>?');">
                  🗑️ Eliminar
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted p-4">No hay contratos en esta sección</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    render('Contratos', ob_get_clean());
    break;
case 'listar':
    $filtro = $_GET['filtro'] ?? 'activos';
    
    $mapa_estatus = [
        'activos' => 'ACTIVO',
        'usados'  => 'USADO',
        'pagados' => 'PAGADO',
        'bajas'   => 'INACTIVO'
    ];
    
    $estatus_db = $mapa_estatus[$filtro] ?? 'ACTIVO';

    // Consulta de datos
    $rows = qall("
      SELECT c.id_contrato,
             t.nombre, t.apellido_p, t.apellido_m,
             d.calle, d.num_ext, d.num_int, d.colonia, d.municipio,
             c.costo_final, c.estatus,
             (
               SELECT CASE
                        WHEN p.id_mos_cat_com IS NULL THEN NULL
                        WHEN COALESCE(p.codigo,'') <> '' AND COALESCE(p.concepto,'') <> '' AND p.codigo <> p.concepto
                          THEN CONCAT(p.codigo, ' - ', p.concepto)
                        ELSE COALESCE(NULLIF(p.codigo,''), p.concepto)
                      END
               FROM futuro_cont_cat_com fcc
               JOIN futuro_mostrar_catalogo_promociones p ON p.id_mos_cat_com = fcc.id_mos_cat_com
               WHERE fcc.id_contrato = c.id_contrato
               ORDER BY fcc.id_cont_cat_com DESC
               LIMIT 1
             ) AS promocion,
             (
               SELECT COUNT(*)
               FROM futuro_abonos a
               WHERE a.id_contrato = c.id_contrato
             ) AS pagos_hechos
      FROM futuro_contratos c
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titulares t ON t.id_titular = tc.id_titular
      LEFT JOIN titular_dom td ON td.id_titular = t.id_titular
      LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
      WHERE c.estatus = ?
      ORDER BY c.id_contrato DESC
    ", [$estatus_db]);

    ob_start(); ?>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 m-0">Contratos</h1>
      <a href="?r=contratos.nuevo" class="btn btn-primary btn-sm">➕ Nuevo contrato</a>
    </div>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'activos') ? 'active' : '' ?>" href="?r=contratos.listar&filtro=activos">TODOS</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'usados') ? 'active' : '' ?>" href="?r=contratos.listar&filtro=usados">YA SE USARON</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'pagados') ? 'active' : '' ?>" href="?r=contratos.listar&filtro=pagados">PAGADOS</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($filtro === 'bajas') ? 'active link-secondary' : 'link-secondary' ?>" href="?r=contratos.listar&filtro=bajas">BAJAS</a>
      </li>
    </ul>

    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="table-responsive">
          <table id="tablaContratos" class="table table-striped table-hover table-sm align-middle" style="width:100%">
            <thead>
              <tr>
                <th>ID</th>
                <th>Titular</th>
                <th>Dirección</th>
                <th>Costo final</th>
                <th>Promoción</th>
                <th>Pagos hechos</th>
                <th>Estatus</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><code><?= e($r['id_contrato']) ?></code></td>
                <td><?= e(trim(($r['nombre'] ?? '').' '.($r['apellido_p'] ?? '').' '.($r['apellido_m'] ?? ''))) ?></td>
                <td><?= e(trim(($r['calle'] ?? '')." #".($r['num_ext'] ?? '').(($r['num_int'] ?? '') ? " Int. ".$r['num_int'] : "").", ".($r['colonia'] ?? '').", ".($r['municipio'] ?? ''))) ?></td>
                <td>$<?= number_format((float)($r['costo_final'] ?? 0), 2) ?></td>
                <td><?= e($r['promocion'] ?: '—') ?></td>
                <td><span class="badge text-bg-light"><?= (int)($r['pagos_hechos'] ?? 0) ?></span></td>
                <td>
                    <?php
                    $bg = match($r['estatus'] ?? '') {
                        'ACTIVO' => 'success',
                        'PAGADO' => 'info',
                        'USADO'  => 'primary',
                        'INACTIVO' => 'secondary',
                        default => 'secondary'
                    };
                    ?>
                    <span class="badge bg-<?= $bg ?>"><?= e($r['estatus'] ?? 'ACTIVO') ?></span>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                      <a href="?r=contratos.editar&id_contrato=<?= (int)$r['id_contrato'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">✏️</a>
                      <a href="?r=contratos.ver&id_contrato=<?= (int)$r['id_contrato'] ?>" class="btn btn-sm btn-outline-secondary" title="Ver">👁️</a>
                      <a href="?r=contratos.pdf&id_contrato=<?= (int)$r['id_contrato'] ?>" class="btn btn-sm btn-outline-danger" title="PDF">📄</a>
                      
                      <?php if (($r['estatus'] ?? '') === 'PAGADO'): ?>
                        <a href="?r=contratos.titulo_pdf&id_contrato=<?= (int)$r['id_contrato'] ?>" target="_blank" class="btn btn-sm btn-warning" title="Imprimir Título">📜</a>
                      <?php endif; ?>

                      <?php if (($r['estatus'] ?? '') !== 'INACTIVO'): ?>
                      <form method="post" action="?r=contratos.eliminar&id_contrato=<?= (int)$r['id_contrato'] ?>" class="m-0 p-0 d-inline" onsubmit="return confirm('¿Desactivar contrato #<?= (int)$r['id_contrato'] ?>?');">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-dark" title="Eliminar/Baja">🗑️</button>
                      </form>
                      <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#tablaContratos').DataTable({
            "language": {
                "sProcessing":     "Procesando...",
                "sLengthMenu":     "Mostrar _MENU_ registros",
                "sZeroRecords":    "No se encontraron resultados",
                "sEmptyTable":     "Ningún dato disponible en esta tabla",
                "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                "sSearch":         "Buscar:",
                "sInfoThousands":  ",",
                "sLoadingRecords": "Cargando...",
                "oPaginate": {
                    "sFirst":    "Primero",
                    "sLast":     "Último",
                    "sNext":     "Siguiente",
                    "sPrevious": "Anterior"
                },
                "oAria": {
                    "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                    "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                }
            },
            "order": [[ 0, "desc" ]], // Ordenar por ID (columna 0) descendente
            "pageLength": 25,         // Mostrar 25 registros por defecto
            "columnDefs": [
                { "orderable": false, "targets": 7 } // No permitir ordenar por la columna de acciones
            ]
        });
    });
    </script>

    <?php
    render('Contratos', ob_get_clean());
    break;
  case 'nuevo':
    $estatus_opts = [
        'ACTIVO'   => 'ACTIVO',
        'PAGADO'   => 'PAGADO',
        'USADO'    => 'YA SE USÓ',
        'INACTIVO' => 'BAJA / INACTIVO'
    ];
    
    $paquetes = futuros_promociones_paquetes();
    $tipo_contrato_opts = array_combine($paquetes, $paquetes) ?: [];
    
    $tipo_pago_opts = [
        'PARCIALIDADES' => 'PARCIALIDADES',
        'CONTADO' => 'CONTADO'
    ];
    $periodo_pago_opts = [
        'SEMANA' => 'SEMANA',
        'QUINCENA' => 'QUINCENA',
        'MES' => 'MES',
        'N/A' => 'N/A'
    ];

    $promociones = __promociones_activas();
    $promociones_opts = [];
    foreach($promociones as $p) {
        $promociones_opts[$p['id_mos_cat_com']] = $p['concepto'];
    }

    $promotores = __personal_activo();

    ob_start(); ?>
    <h1 class="h4 mb-3">Nuevo contrato</h1>

    <form method="post" action="?r=contratos.guardar">
      <?= csrf_field() ?>
      <input type="hidden" name="municipio" value="León">
      <div class="row g-3">

        <h5 class="mt-2">Datos del Titular</h5>
        <div class="col-md-6"><?= form_input('nombre','Nombre','', ['required'=>true,'maxlength'=>50]) ?></div>
        <div class="col-md-3"><?= form_input('apellido_p','Apellido paterno','', ['maxlength'=>50]) ?></div>
        <div class="col-md-3"><?= form_input('apellido_m','Apellido materno','', ['maxlength'=>50]) ?></div>

        <h5 class="mt-4">Domicilio</h5>
        <div class="col-md-6"><?= form_input('calle','Calle','', ['maxlength'=>50]) ?></div>
        <div class="col-md-2"><?= form_input('num_ext','Núm. exterior','', ['type'=>'number','min'=>'0']) ?></div>
        <div class="col-md-2"><?= form_input('num_int','Núm. interior','', ['maxlength'=>5]) ?></div>
        <div class="col-md-4"><?= form_input('colonia','Colonia','', ['maxlength'=>50]) ?></div>
        <div class="col-md-6"><?= form_input('entre_calle1','Entre calle 1','', ['maxlength'=>50]) ?></div>
        <div class="col-md-6"><?= form_input('entre_calle2','Entre calle 2','', ['maxlength'=>50]) ?></div>
        <div class="col-12"><?= form_input('notas','Notas del domicilio','', ['maxlength'=>250]) ?></div>

        <h5 class="mt-4">Datos del Contrato</h5>
        
        <div class="col-md-4">
            <?= form_select('tipo_contrato','Tipo de contrato / Paquete', $tipo_contrato_opts, '', ['class'=>'form-select']) ?>
        </div>
        
        <div class="col-md-4">
            <?= form_select('id_promocion', 'Promoción', [0 => '-- Sin promoción --'] + $promociones_opts, 0, ['class'=>'form-select']) ?>
        </div>

        <div class="col-md-4">
            <?= form_select('tipo_pago','Tipo de pago', $tipo_pago_opts, 'PARCIALIDADES', ['class'=>'form-select']) ?>
        </div>
        
        <div class="col-md-4"><?= form_input('costo_contrato','Costo base', '28900', ['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
        <div class="col-md-4"><?= form_input('descuento','Descuento', '0', ['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
        <div class="col-md-4"><?= form_input('costo_final','Costo final', '0', ['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
        
        <div class="col-md-4">
            <?= form_input('compromiso_pago','Pago periódico','100',['type'=>'number','step'=>'0.01','min'=>'0']) ?>
        </div>
        
        <div class="col-md-4">
            <?= form_select('periodo_pago','Periodo', $periodo_pago_opts, 'SEMANA', ['class'=>'form-select']) ?>
        </div>
        
        <div class="col-md-4"><?= form_select('estatus','Estatus',$estatus_opts,'ACTIVO',['class'=>'form-select']) ?></div>

        <h5 class="mt-4">Promotor</h5>
        <div class="col-md-6">
          <label class="form-label">Promotor asignado</label>
          <select name="id_promotor" class="form-select" required>
            <option value="">-- Selecciona promotor --</option>
            <?php foreach ($promotores as $p): ?>
              <option value="<?= (int)$p['id_personal'] ?>">
                <?= e($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 d-grid mt-3">
          <button class="btn btn-primary">Guardar contrato</button>
        </div>
      </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded',()=>{
      const base=document.querySelector('[name="costo_contrato"]');
      const desc=document.querySelector('[name="descuento"]');
      const fin=document.querySelector('[name="costo_final"]');
      function recalc(){
        const b=parseFloat(base?.value)||0, d=parseFloat(desc?.value)||0;
        if(fin) fin.value=(b-d).toFixed(2);
      }
      if (base && desc) {
        base.addEventListener('input',recalc);
        desc.addEventListener('input',recalc);
      }
    });
    </script>
    <?php
    render('Nuevo contrato', ob_get_clean());
    break;

  case 'guardar':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('contratos.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { $_SESSION['_alerts'] = "<div class='alert alert-danger'>Sesión inválida.</div>"; redirect('contratos.listar'); }

    $nombre = __post_str('nombre','');
    if ($nombre === '') {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>El nombre del titular es obligatorio.</div>";
      redirect('contratos.nuevo');
    }

    $id_promotor = (int)($_POST['id_promotor'] ?? 0);
    if ($id_promotor <= 0) {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Debes seleccionar un promotor válido.</div>";
      redirect('contratos.nuevo');
    }

    try {
      db()->beginTransaction();

      $apellido_p = __post_str('apellido_p','');
      $apellido_m = __post_str('apellido_m','');

      q("INSERT INTO titulares (nombre, apellido_p, apellido_m) VALUES (?,?,?)", [
        $nombre, $apellido_p, $apellido_m
      ]);
      $id_titular = (int)db()->lastInsertId();

      $municipio = 'León';
      $colonia = __post_str('colonia','');
      $calle = __post_str('calle','');
      $num_ext = __post_int('num_ext',0);
      $num_int = __post_str('num_int','');
      $entre_calle1 = __post_str('entre_calle1','');
      $entre_calle2 = __post_str('entre_calle2','');
      $notas = __post_str('notas','');

      q("INSERT INTO domicilios (municipio,colonia,calle,num_ext,num_int,entre_calle1,entre_calle2,tipo_dom,notas)
          VALUES (?,?,?,?,?,?,?,?,?)", [
        $municipio, $colonia, $calle, $num_ext, $num_int,
        $entre_calle1, $entre_calle2, 'particular', $notas
      ]);
      $id_domicilio = (int)db()->lastInsertId();

      q("INSERT INTO titular_dom (id_titular, id_domicilio) VALUES (?,?)", [$id_titular, $id_domicilio]);

      $tipo_contrato = __post_str('tipo_contrato','');
      $tipo_pago = __post_str('tipo_pago','');
      $costo_contrato = __post_float('costo_contrato', 0);
      $descuento = __post_float('descuento', 0);
      $costo_final = __post_float('costo_final', 0);
      $periodo_pago = __post_str('periodo_pago','');
      $compromiso_pago = __post_float('compromiso_pago', 0);
      $estatus = __contrato_estatus_normalizado($_POST['estatus'] ?? 'ACTIVO');

      q("INSERT INTO futuro_contratos (tipo_contrato,tipo_pago,costo_contrato,descuento,costo_final,periodo_pago,compromiso_pago,estatus,porc_promotor,porc_jefe_cuad,porc_lider,porc_empresa)
          VALUES (?,?,?,?,?,?,?, ?,25,15,10,50)", [
        $tipo_contrato, $tipo_pago, $costo_contrato, $descuento, $costo_final, $periodo_pago, $compromiso_pago, $estatus
      ]);
      $id_contrato = (int)db()->lastInsertId();

      q("INSERT INTO titular_contrato (id_titular,id_contrato) VALUES (?,?)", [$id_titular,$id_contrato]);

      $id_promocion = __post_int('id_promocion', 0);
      if ($id_promocion > 0) {
          q("INSERT INTO futuro_cont_cat_com (id_contrato, id_mos_cat_com) VALUES (?,?)", [$id_contrato, $id_promocion]);
      }

      q("INSERT INTO futuro_contrato_vendedor (id_contrato,id_personal) VALUES (?,?)", [$id_contrato, $id_promotor]);

      db()->commit();

      $_SESSION['_alerts'] = "<div class='alert alert-success'>Contrato #{$id_contrato} guardado correctamente.</div>";
      redirect("contratos.ver&id_contrato={$id_contrato}");
    } catch (Exception $e) {
      if (db()->inTransaction()) db()->rollBack();
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
      redirect('contratos.nuevo');
    }
    break;

  case 'editar':
    $idc = (int)($_GET['id_contrato'] ?? 0);
    $c = qone("
      SELECT c.*, t.*, d.*
      FROM futuro_contratos c
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titulares t ON t.id_titular = tc.id_titular
      LEFT JOIN titular_dom td ON td.id_titular = t.id_titular
      LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
      WHERE c.id_contrato = ?
    ", [$idc]);
    if (!$c) { $_SESSION['_alerts']="<div class='alert alert-warning'>Contrato no encontrado.</div>"; redirect('contratos.listar'); }

    $promotores = __personal_activo();
    $id_promotor_actual = __vendedor_actual_id($idc);
    
    $promociones = __promociones_activas();
    $promociones_opts = [];
    foreach($promociones as $p) {
        $promociones_opts[$p['id_mos_cat_com']] = $p['concepto'];
    }
    
    $promo_actual = qone("SELECT id_mos_cat_com FROM futuro_cont_cat_com WHERE id_contrato = ? ORDER BY id_cont_cat_com DESC LIMIT 1", [$idc]);
    $id_promocion_sel = (int)($promo_actual['id_mos_cat_com'] ?? 0);

    $estatus_opts = [
        'ACTIVO'   => 'ACTIVO',
        'PAGADO'   => 'PAGADO',
        'USADO'    => 'YA SE USÓ',
        'INACTIVO' => 'BAJA / INACTIVO'
    ];
    
    $paquetes = futuros_promociones_paquetes();
    $tipo_contrato_opts = array_combine($paquetes, $paquetes) ?: [];
    
    $tipo_pago_opts = [
        'PARCIALIDADES' => 'PARCIALIDADES',
        'CONTADO' => 'CONTADO'
    ];
    $periodo_pago_opts = [
        'SEMANA' => 'SEMANA',
        'QUINCENA' => 'QUINCENA',
        'MES' => 'MES',
        'N/A' => 'N/A'
    ];

    ob_start(); ?>
    <h1 class="h4 mb-3">Editar contrato #<?= $idc ?></h1>
    <form method="post" action="?r=contratos.actualizar&id_contrato=<?= $idc ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="municipio" value="León">
      <div class="row g-3">

        <h5>Datos del Titular</h5>
        <div class="col-md-6"><?= form_input('nombre','Nombre',$c['nombre'] ?? '', ['required'=>true,'maxlength'=>50]) ?></div>
        <div class="col-md-3"><?= form_input('apellido_p','Apellido paterno',$c['apellido_p'] ?? '', ['maxlength'=>50]) ?></div>
        <div class="col-md-3"><?= form_input('apellido_m','Apellido materno',$c['apellido_m'] ?? '', ['maxlength'=>50]) ?></div>

        <h5 class="mt-4">Domicilio</h5>
        <div class="col-md-6"><?= form_input('calle','Calle',$c['calle'] ?? '', ['maxlength'=>50]) ?></div>
        <div class="col-md-2"><?= form_input('num_ext','Núm. exterior',$c['num_ext'] ?? 0, ['type'=>'number','min'=>'0']) ?></div>
        <div class="col-md-2"><?= form_input('num_int','Núm. interior',$c['num_int'] ?? '', ['maxlength'=>5]) ?></div>
        <div class="col-md-4"><?= form_input('colonia','Colonia',$c['colonia'] ?? '', ['maxlength'=>50]) ?></div>
        <div class="col-md-6"><?= form_input('entre_calle1','Entre calle 1',$c['entre_calle1'] ?? '', ['maxlength'=>50]) ?></div>
        <div class="col-md-6"><?= form_input('entre_calle2','Entre calle 2',$c['entre_calle2'] ?? '', ['maxlength'=>50]) ?></div>
        <div class="col-12"><?= form_input('notas','Notas del domicilio',$c['notas'] ?? '', ['maxlength'=>250]) ?></div>

        <h5 class="mt-4">Datos del Contrato</h5>
        
        <div class="col-md-4">
            <?= form_select('tipo_contrato','Tipo de contrato', $tipo_contrato_opts, $c['tipo_contrato'] ?? '', ['class'=>'form-select']) ?>
        </div>
        
        <div class="col-md-4">
            <?= form_select('id_promocion', 'Promoción', [0 => '-- Sin promoción --'] + $promociones_opts, $id_promocion_sel, ['class'=>'form-select']) ?>
        </div>

        <div class="col-md-4">
            <?= form_select('tipo_pago','Tipo de pago', $tipo_pago_opts, $c['tipo_pago'] ?? '', ['class'=>'form-select']) ?>
        </div>
        
        <div class="col-md-4"><?= form_input('costo_contrato','Costo base',$c['costo_contrato'] ?? 0,['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
        <div class="col-md-4"><?= form_input('descuento','Descuento',$c['descuento'] ?? 0,['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
        <div class="col-md-4"><?= form_input('costo_final','Costo final',$c['costo_final'] ?? 0,['type'=>'number','step'=>'0.01','min'=>'0']) ?></div>
        
        <div class="col-md-4">
            <?= form_input('compromiso_pago','Pago periódico',$c['compromiso_pago'] ?? 0,['type'=>'number','step'=>'0.01','min'=>'0']) ?>
        </div>
        
        <div class="col-md-4">
            <?= form_select('periodo_pago','Periodo', $periodo_pago_opts, $c['periodo_pago'] ?? 'SEMANA', ['class'=>'form-select']) ?>
        </div>
        
        <div class="col-md-4"><?= form_select('estatus','Estatus',$estatus_opts, __contrato_estatus_normalizado($c['estatus'] ?? 'ACTIVO'), ['class'=>'form-select']) ?></div>

        <h5 class="mt-4">Promotor</h5>
        <div class="col-md-6">
          <label class="form-label">Promotor asignado</label>
          <select name="id_promotor" class="form-select" required>
            <option value="">-- Selecciona promotor --</option>
            <?php foreach ($promotores as $p): ?>
              <option value="<?= (int)$p['id_personal'] ?>" <?= ((int)$p['id_personal']===(int)$id_promotor_actual) ? 'selected' : '' ?>>
                <?= e($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 d-grid mt-3">
          <button class="btn btn-primary">Actualizar contrato</button>
        </div>
      </div>
    </form>
    <?php
    render('Editar contrato', ob_get_clean());
    break;

  case 'actualizar':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('contratos.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { $_SESSION['_alerts'] = "<div class='alert alert-danger'>Sesión inválida.</div>"; redirect('contratos.listar'); }

    $idc = (int)($_GET['id_contrato'] ?? 0);
    $id_promotor = (int)($_POST['id_promotor'] ?? 0);
    if ($id_promotor <= 0) {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Selecciona un promotor válido.</div>";
      redirect("contratos.editar&id_contrato={$idc}");
    }

    $nombre = __post_str('nombre','');
    if ($nombre === '') {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>El nombre del titular es obligatorio.</div>";
      redirect("contratos.editar&id_contrato={$idc}");
    }

    $estatus = __contrato_estatus_normalizado($_POST['estatus'] ?? 'ACTIVO');

    try {
      db()->beginTransaction();

      $tc = qone("SELECT id_titular FROM titular_contrato WHERE id_contrato=?", [$idc]);
      if ($tc) {
        q("UPDATE titulares SET nombre=?, apellido_p=?, apellido_m=? WHERE id_titular=?", [
          $nombre, __post_str('apellido_p',''), __post_str('apellido_m',''), $tc['id_titular']
        ]);

        $municipio = 'León';
        q("UPDATE domicilios d
           JOIN titular_dom td ON td.id_domicilio = d.id_domicilio
           SET d.municipio=?, d.colonia=?, d.calle=?, d.num_ext=?, d.num_int=?,
               d.entre_calle1=?, d.entre_calle2=?, d.notas=?
           WHERE td.id_titular=?", [
          $municipio,
          __post_str('colonia',''),
          __post_str('calle',''),
          __post_int('num_ext',0),
          __post_str('num_int',''),
          __post_str('entre_calle1',''),
          __post_str('entre_calle2',''),
          __post_str('notas',''),
          $tc['id_titular']
        ]);
      }

      q("UPDATE futuro_contratos
         SET tipo_contrato=?, tipo_pago=?, costo_contrato=?, descuento=?, costo_final=?, periodo_pago=?, compromiso_pago=?, estatus=?
         WHERE id_contrato=?", [
        __post_str('tipo_contrato',''),
        __post_str('tipo_pago',''),
        __post_float('costo_contrato',0),
        __post_float('descuento',0),
        __post_float('costo_final',0),
        __post_str('periodo_pago',''),
        __post_float('compromiso_pago',0),
        $estatus,
        $idc
      ]);

      $id_promocion = __post_int('id_promocion', 0);
      q("DELETE FROM futuro_cont_cat_com WHERE id_contrato = ?", [$idc]);
      if ($id_promocion > 0) {
          q("INSERT INTO futuro_cont_cat_com (id_contrato, id_mos_cat_com) VALUES (?,?)", [$idc, $id_promocion]);
      }

      q("INSERT INTO futuro_contrato_vendedor (id_contrato,id_personal) VALUES (?,?)", [$idc, $id_promotor]);

      db()->commit();

      $_SESSION['_alerts'] = "<div class='alert alert-success'>Contrato actualizado correctamente.</div>";
      redirect("contratos.listar&id_contrato={$idc}");
    } catch (Exception $e) {
      if (db()->inTransaction()) db()->rollBack();
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
      redirect("contratos.editar&id_contrato={$idc}");
    }
    break;

  case 'eliminar':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('contratos.listar');
    try { csrf_verify(); } catch (RuntimeException $e) { $_SESSION['_alerts'] = "<div class='alert alert-danger'>Sesión inválida.</div>"; redirect('contratos.listar'); }

    $idc = (int)($_GET['id_contrato'] ?? 0);
    if ($idc <= 0) redirect('contratos.listar');

    $c = qone("SELECT id_contrato, estatus FROM futuro_contratos WHERE id_contrato=?", [$idc]);
    if (!$c) { $_SESSION['_alerts']="<div class='alert alert-warning'>Contrato no encontrado.</div>"; redirect('contratos.listar'); }

    try {
      q("UPDATE futuro_contratos SET estatus='INACTIVO' WHERE id_contrato=?", [$idc]);
      $_SESSION['_alerts'] = "<div class='alert alert-success'>Contrato #{$idc} desactivado.</div>";
    } catch (Exception $e) {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>No se pudo desactivar el contrato.</div>";
    }
    redirect('contratos.listar');
    break;

  case 'ver':
    $idc = (int)($_GET['id_contrato'] ?? 0);
    $c = qone("
      SELECT c.*, t.*, d.*
      FROM futuro_contratos c
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titulares t ON t.id_titular = tc.id_titular
      LEFT JOIN titular_dom td ON td.id_titular = t.id_titular
      LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
      WHERE c.id_contrato = ?
    ", [$idc]);
    if (!$c) redirect('contratos.listar');

    $fecha_contrato = __fecha_es($c['fecha_registro'] ?? null);

    ob_start(); ?>
    <div class="">
      <h2 class="text-center mb-3">
        Contrato de Servicio<br>
        <small class="text-muted fs-6"><?= e($c['tipo_contrato'] ?? '') ?></small>
      </h2>
      <p class="text-end">Folio: <?= $idc ?>/<?= date('Y', strtotime($c['fecha_registro'] ?? 'now')) ?></p>
      <p>
        EL C. <strong><?= e(trim("{$c['nombre']} {$c['apellido_p']} {$c['apellido_m']}")) ?></strong>
        con domicilio en <strong><?= e(trim("{$c['calle']} #{$c['num_ext']}".($c['num_int'] ? " Int. {$c['num_int']}" : "")." {$c['colonia']} {$c['municipio']}")) ?></strong>
        el siguiente paquete para cuando lo solicite, teniendo derecho a lo estipulado en las cláusulas siguientes que incluyen:
      </p>

      <ul>
        <li>Recolección de cadáver dentro de la zona urbana de León, Guanajuato.</li>
        <li>Incineración de un cadáver en fecha de requerimiento de tal servicio.</li>
        <li>Tramitación ante las autoridades correspondientes para llevar a cabo la incineración.</li>
        <li>Gestoría del acta de defunción.</li>
        <li>Personal de la funeraria para la realización de dicho servicio.</li>
        <li>Urna para cenizas modelo Grecia.</li>
        <li>El contrato podrá ser transferible para cualquier persona.</li>
      </ul>

      <p>
        El paquete anterior será proporcionado en la cd. de León, Gto. en nuestras instalaciones, además tendrá un costo de
        $<?= number_format((float)$c['costo_final'],2) ?> pesos con un pago inicial de $100.00 pesos y con pagos semanales de $100.00 pesos.
        Si el cliente desea aportar 2 mensualidades consecutivas podrá hacerlo para terminar de pagar más pronto dicho convenio.
        Si el cliente deja de aportar 2 mensualidades consecutivas, será motivo para rescindir el presente contrato y perderá en su caso las mensualidades aportadas.
      </p>
      <p>
        Si el cliente llega a necesitar del servicio de la incineración antes de haber cubierto el costo del mismo, deberá cubrir las mensualidades restantes en el momento de solicitar el servicio para poder hacer efectivo el servicio.
      </p>
      <p>
        En caso de fallecimiento accidental instantáneo del titular únicamente, quedará automáticamente liquidado el saldo, siempre y cuando vaya al corriente de sus pagos.
      </p>
      <p>
        Nota: Si el cliente requiere un servicio extra que no esté estipulado en las cláusulas anteriores, los gastos correrán por cuenta del mismo.
      </p>
      <p>
        El presente contrato podrá ser rescindido por la empresa en momento y forma, si llegase a existir alguna anomalía que afectase a cualquiera de los participantes.
      </p>
      <p>
        Los participantes de dicho contrato firman al pie de la hoja el haber acordado, entendido y dan por aceptado dicho contrato.
      </p>

      <div class="mt-5 text-center">
        <p>_________________________<br><?= e(trim("{$c['nombre']} {$c['apellido_p']} {$c['apellido_m']}")) ?><br><small>Contratante</small></p>
        <p>_________________________<br>Lic. Christian Ureña<br><small>Grupo Ureña Funerarios</small></p>
        <p class="mt-3 text-muted">León, Gto. A <?= e($fecha_contrato) ?></p>
      </div>

      <div class="text-center mt-4 no-print">
        <a class="btn btn-danger" href="?r=contratos.pdf&id_contrato=<?= $idc ?>">📄 Descargar PDF</a>
        <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir</button>
        <button type="button" class="btn btn-secondary" onclick="history.back()">Volver</button>
      </div>
    </div>
    <style>@media print{.no-print{display:none;}}</style>
    <?php
    render("Contrato #$idc", ob_get_clean());
    break;

  case 'pdf':
    $idc = (int)($_GET['id_contrato'] ?? 0);
    $c = qone("
      SELECT c.*, t.*, d.*
      FROM futuro_contratos c
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titulares t ON t.id_titular = tc.id_titular
      LEFT JOIN titular_dom td ON td.id_titular = t.id_titular
      LEFT JOIN domicilios d ON d.id_domicilio = td.id_domicilio
      WHERE c.id_contrato = ?
    ", [$idc]);
    if (!$c) { $_SESSION['_alerts']="<div class='alert alert-warning'>Contrato no encontrado.</div>"; redirect('contratos.listar'); }

    try {

      require_once 'dompdf/autoload.inc.php';


      $titular = trim("{$c['nombre']} {$c['apellido_p']} {$c['apellido_m']}");
      $dom = trim("{$c['calle']} #{$c['num_ext']}".($c['num_int'] ? " Int. {$c['num_int']}" : "")." {$c['colonia']} {$c['municipio']}");
      $costo = number_format((float)$c['costo_final'], 2);
      $anio_folio = date('Y', strtotime($c['fecha_registro'] ?? 'now'));
      $folio = $idc.'/'.$anio_folio;
      $fecha_contrato = __fecha_es($c['fecha_registro'] ?? null);
      $tipo_contrato = htmlspecialchars($c['tipo_contrato'] ?? '', ENT_QUOTES, 'UTF-8');


      $html = '<html><head><meta charset="utf-8">
      <style>
        body{font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.35;}
        h2{margin:0 0 4px 0; text-align:center;}
        h3{margin:0 0 8px 0; text-align:center; font-weight:normal; color:#555; font-size:14px;}
        .folio{margin:0 0 10px 0; text-align:right;}
        ul{margin:8px 0 10px 18px;}
        .firmas{margin-top:40px; text-align:center;}
        .firma{margin-top:18px;}
        .muted{color:#666;}
      </style></head><body>';

switch($tipo_contrato)
{
  case 'SERVICIO COMPLETO':
  $html = '<html><head><meta charset="utf-8">
      <style>
        body{font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.2;} /* Reducido de 12px a 11px */
        h2{margin:0 0 4px 0; text-align:center; font-size: 16px;}
        h3{margin:0 0 8px 0; text-align:center; font-weight:normal; color:#555; font-size:13px;}
        .folio{margin:0 0 5px 0; text-align:right; font-weight: bold;}
        ul{margin:5px 0 5px 18px;}
        li{margin-bottom: 2px;}
        .firmas{margin-top:20px; text-align:center;}
        .muted{color:#666;}
        
        /* Clase para forzar el salto de hoja */
        .saltopagina {
            page-break-before: always;
        }
      </style></head><body>';
$html .= '<h2>CONTRATO DE SERVICIOS A PREVISIÓN</h2>';
      $html .= '<p class="folio">No. '.htmlspecialchars($folio, ENT_QUOTES, 'UTF-8').'</p>';

      $html .= '<p>En la ciudad de León, Guanajuato, a <strong>'.htmlspecialchars($fecha_contrato, ENT_QUOTES, 'UTF-8').'</strong> los que suscriben este documento comparecen, por una parte, <strong>GRUPO UREÑA FUNERARIOS</strong> y por la otra el C. <strong>'.htmlspecialchars($titular, ENT_QUOTES, 'UTF-8').'</strong> con domicilio en <strong>'.htmlspecialchars($dom, ENT_QUOTES, 'UTF-8').'</strong> en su calidad de titular; manifestando ambas partes, su conformidad para celebrar un contrato de prestación de servicios funerarios a futuro bajo las siguientes condiciones.</p>';

      $html .= '<p><strong>PRIMERA.</strong> El titular contrata un servicio de un <strong>'.($tipo_contrato ?: 'PLAN PREVISOR').'</strong> y expresa su entera conformidad de cubrir la cantidad de <strong>$'.htmlspecialchars($costo, ENT_QUOTES, 'UTF-8').'</strong> como importe del servicio contratado, el que será cubierto mediante los pagos pactados en la administración.</p>';

      $html .= '<p><strong>SEGUNDA.</strong> En los supuestos de que los abonos pactados no se cubran oportunamente pasados los 30 días del primer abono no cubierto, se dará por terminado el compromiso aquí pactado, conviniendo ambas partes, que la cantidad que se haya entregado, queda como depósito efectivo a favor del titular a cuenta de la prestación de un servicio funerario requerido a futuro. Este compromiso se aplicará también cuando el titular decida cancelar voluntariamente.</p>';

      $html .= '<p><strong>TERCERA.</strong> Si el servicio funerario es requerido antes de haberse pagado totalmente, GRUPO UREÑA FUNERARIOS otorgará el servicio cuando se lo soliciten, debiendo cubrir el titular el saldo pendiente 2 horas antes de que termine el servicio.</p>';

      $html .= '<p><strong>CUARTA.</strong> Bajo ninguna circunstancia se podrán sumar las aportaciones de 2 (dos) o más contratos diferentes, para la prestación de un servicio.</p>';

      $html .= '<p><strong>QUINTA.</strong> Los servicios funerarios, se otorgarán únicamente por GRUPO UREÑA FUNERARIOS en sus cuatro direcciones, con horario establecido las 24 horas del día, los 365 días del año y los servicios a otorgar serán los siguientes:</p>';

      $html .= '<ul>
        <li>A) Traslado del cuerpo desde cualquier unidad médica, SEMEFO o domicilio particular en esta ciudad a las capillas de velación o al domicilio particular en esta Ciudad según solicite.</li>
        <li>B) Ataúd metálico o de madera estándar, color único modelo español o similar.</li>
        <li>C) Servicio de velación en capillas de GRUPO UREÑA FUNERARIOS hasta por 24 horas.</li>
        <li>D) Servicio de cafetería en la capilla funeraria.</li>
        <li>E) Equipo de velación para domicilio hasta 24 horas (Opcional).</li>
        <li>F) Servicio de cafetería básico, si la velación fuese en el domicilio del particular (Opcional).</li>
        <li>G) 50 Sillas plegables y toldo de protección para velación en domicilio (Opcional).</li>
        <li>H) Renta de ataúd metálico o de madera estándar, color único (en caso de cremación).</li>
        <li>I) Servicio de cremación en el crematorio de GRUPO UREÑA FUNERARIOS (Opcional).</li>
        <li>J) Urna para cenizas modelo Ecopack en caso de cremación (Opcional).</li>
        <li>K) Asesoría y trámite del permiso para sepultar o cremar (Opcional).</li>
        <li>L) Asesoría y trámite del acta de defunción.</li>
        <li>M) Servicios de vehículos especiales (Vans y Carrozas) para los traslados.</li>
        <li>N) Traslado al cementerio o crematorio de GRUPO UREÑA FUNERARIOS, dentro del municipio de León.</li>
        <li>O) Cien recuerdos para los familiares. P) Libro de recuerdo. Q) Arreglo Estético (No embalsamado).</li>
      </ul>';

      $html .= '<p><strong>SEXTA.</strong> En caso de fallecimiento accidental instantáneo del titular únicamente, quedará automáticamente liquidado el saldo, siempre y cuando vaya al corriente con sus pagos.</p>';

      $html .= '<p><strong>NOVENA.</strong> Este servicio contratado, podrá ser transferible a persona distinta al titular, previa autorización del contratante.</p>';

      $html .= '<p><strong>DÉCIMA SEGUNDA.</strong> El contratante tendrá un plazo de cinco días hábiles contados a partir del día siguiente a la firma del contrato, para poder cancelar la contratación en base a la NOM-036-SCFI-2000.</p>';

      $html .= '<div class="firmas" style="display: flex; justify-content: space-between; margin-top: 30px;">
        <div style="text-align: center;">_________________________<br>GRUPO UREÑA FUNERARIOS</div>
        <div style="text-align: center;">_________________________<br>'.htmlspecialchars($titular, ENT_QUOTES, 'UTF-8').'<br>TITULAR</div>
      </div>';

      $html .= '<br><br><br><br><br><br><br>';

      $html .= '<h1>CESIÓN DE DERECHOS</h1>';
      $html .= '<p>Por este medio transfiero los derechos y obligaciones del presente contrato con número <strong>'.htmlspecialchars($folio, ENT_QUOTES, 'UTF-8').'</strong> a nombre de ________________________________ siendo yo, <strong>'.htmlspecialchars($titular, ENT_QUOTES, 'UTF-8').'</strong>, titular del mismo, y lo cedo para la persona quien llevo el nombre en vida de ________________________________ por lo cual deslindo a GRUPO UREÑA FUNERARIOS, de cualquier obligación hacia mi con respecto al presente contrato, en situaciones futuras.</p>';

      $html .= '<div style="margin-top: 20px; text-align: center;">_________________________<br>Titular del contrato</div>';

        break;
  default:
      $html .= '<h2>Contrato de Servicio</h2>';
      if ($tipo_contrato) {
          $html .= '<h3>'.$tipo_contrato.'</h3>';
      }
      $html .= '<p class="folio">Folio: '.htmlspecialchars($folio,ENT_QUOTES,'UTF-8').'</p>';
      $html .= '<p>EL C. <strong>'.htmlspecialchars($titular,ENT_QUOTES,'UTF-8').'</strong> con Domicilio en <strong>'.htmlspecialchars($dom,ENT_QUOTES,'UTF-8').'</strong> el siguiente paquete para cuando lo solicite, teniendo derecho a lo estipulado en las cláusulas siguientes que incluyen:</p>';

      $html .= '<ul>
        <li>Recolección de Cadáver dentro de la Zona Urbana de León, Guanajuato.</li>
        <li>Incineración de un cadáver en fecha de requerimiento de tal servicio.</li>
        <li>Tramitación ante las autoridades correspondientes para llevar a cabo la incineración.</li>
        <li>Gestoría del acta de Defunción.</li>
        <li>Personal de la funeraria para la realización de dicho servicio.</li>
        <li>Urna para Cenizas Modelo Grecia.</li>
        <li>El contrato podrá ser transferible para cualquier persona.</li>
      </ul>';

      $html .= '<p>El paquete anterior será proporcionado en la cd. de León, Gto. En nuestras instalaciones, además tendrá un costo de $'.$costo.' pesos con un pago inicial de $100.00 pesos y con pagos semanales de $100.00 pesos. Si el cliente desea aportar 2 mensualidades consecutivas podrá hacerlo para terminar de pagar más pronto dicho convenio. Si el cliente deja de aportar 2 mensualidades consecutivas, será motivo para rescindir el presente contrato y perderá en su caso las mensualidades aportadas.</p>';
      $html .= '<p>Si el cliente llega a necesitar del servicio de la incineración antes de haber cubierto el costo del mismo, deberá cubrir las mensualidades restantes en el momento de solicitar el servicio para poder hacer efectivo el servicio.</p>';
      $html .= '<p>En caso de fallecimiento accidental instantáneo del titular únicamente, quedará automáticamente liquidado el saldo, siempre y cuando vaya al corriente de sus pagos.</p>';
      $html .= '<p>Nota: Si el cliente requiere un servicio extra que no esté estipulado en las cláusulas anteriores, los gastos correrán por cuenta del mismo.</p>';
      $html .= '<p>El presente contrato podrá ser rescindido por la empresa en momento y forma, si llegase a existir alguna anomalía que afectase a cualquiera de los participantes.</p>';
      $html .= '<p>Los participantes de dicho contrato firman al pie de la hoja el haber acordado, entendido y dan por aceptado dicho contrato.</p>';

      $html .= '<div class="firmas">
        <div class="firma">_________________________<br>'.htmlspecialchars($titular,ENT_QUOTES,'UTF-8').'<br><span class="muted">Contratante</span></div>
        <div class="firma">_________________________<br>Lic. Christian Ureña<br><span class="muted">Grupo Ureña Funerarios</span></div>
        <div class="firma muted">León, Gto. A '.htmlspecialchars($fecha_contrato,ENT_QUOTES,'UTF-8').'</div>
      </div>';

      $html .= '</body></html>';

}





      $dompdf = new \Dompdf\Dompdf();
      $dompdf->loadHtml($html, 'UTF-8');
      $dompdf->setPaper('letter', 'portrait');
      $dompdf->render();

      header('Content-Type: application/pdf');
      header('Content-Disposition: inline; filename="Contrato_'.$idc.'.pdf"');
      echo $dompdf->output();
      exit;

    } catch (Exception $e) {
      $_SESSION['_alerts'] = "<div class='alert alert-danger'>Error al generar PDF: ".$e->getMessage()."</div>";
      redirect("contratos.ver&id_contrato={$idc}");
    }
    break;

  case 'titulo_pdf':
    $idc = (int)($_GET['id_contrato'] ?? 0);
    $c = qone("
      SELECT c.*, t.*
      FROM futuro_contratos c
      LEFT JOIN titular_contrato tc ON tc.id_contrato = c.id_contrato
      LEFT JOIN titulares t ON t.id_titular = tc.id_titular
      WHERE c.id_contrato = ?
    ", [$idc]);
    if (!$c) die("Contrato no encontrado");

    require_once 'dompdf/autoload.inc.php';

    $titular = mb_strtoupper(trim("{$c['nombre']} {$c['apellido_p']} {$c['apellido_m']}"), 'UTF-8');
    $folio = $idc;
    $dia = date('d');
    $mes = strtoupper(__nombre_mes(date('m')));




    $anio = date('Y');
    // Usamos el id_contrato con padding como número de control
    $num_control = str_pad($idc, 6, '0', STR_PAD_LEFT);

    // Ajusta los valores de 'top' y 'left' para coincidir con tu formato preimpreso
    $html = '<html><head><meta charset="utf-8">
    <style>
      @page { margin: 0; }
      body { font-family: sans-serif; font-size: 12pt; }
      .abs { position: absolute; }
    </style></head><body>';

    // Coordenadas aproximadas (Ajustar según impresión real)
    // Nombre
    $html .= '<div class="abs" style="top: 560px; left: 70px; width: 500px; text-align:center;">'.htmlspecialchars($titular).'</div>';
    $html .= '<div class="abs" style="top: 590px; left: 480px;">'.htmlspecialchars($folio).'</div>';
    $html .= '<div class="abs" style="top: 640px; left: 410px;">'.htmlspecialchars($dia).'</div>';
    $html .= '<div class="abs" style="top: 640px; left: 480px;">'.htmlspecialchars($mes).'</div>';
    $html .= '<div class="abs" style="top: 640px; left: 620px;">'.htmlspecialchars($anio).'</div>';
    $html .= '<div class="abs" style="top: 715px; left: 630px;">'.htmlspecialchars($num_control).'</div>';

    $html .= '</body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Titulo_'.$idc.'.pdf"');
    echo $dompdf->output();
    exit;
    break;

  default:
    redirect('contratos.listar');
}
