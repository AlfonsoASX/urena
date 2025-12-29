<?php

if (($_GET['r'] ?? '') === 'api.gestiones_hoy') {
  require_once __DIR__."/../core/db.php";
  header('Content-Type: application/json; charset=utf-8');

  try {
    $rows = qall("
      SELECT g.latitud, g.longitud,
             COALESCE(t.titular,'') AS titular,
             CONCAT(p.nombre,' ',p.apellido_p) AS cobrador
      FROM futuro_gestion g
      LEFT JOIN vw_titular_contrato t ON t.id_contrato=g.id_contrato
      LEFT JOIN futuro_contrato_cobrador fc ON fc.id_contrato=g.id_contrato
      LEFT JOIN futuro_personal p ON p.id_personal=fc.id_personal
      WHERE DATE(g.fecha_registro)=CURDATE()
        AND g.latitud IS NOT NULL AND g.longitud IS NOT NULL
        AND g.latitud != 0 AND g.longitud != 0
    ");
  } catch(Throwable $e){
    $rows = [];
  }

  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  exit;
}
