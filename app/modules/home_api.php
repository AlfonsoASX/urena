<?php

require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/api.php';
require_once __DIR__.'/../core/home_dashboard.php';

api_require_login();

$action = $action ?? 'kpis';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
  api_error(405, 'Metodo no permitido.');
}

switch ($action) {
case 'kpis':
  api_success(home_dashboard_kpis(), 'KPIs obtenidos correctamente.');
  break;

case 'widgets':
  api_success(home_dashboard_widgets(), 'Widgets obtenidos correctamente.');
  break;

default:
  api_error(404, 'Endpoint no encontrado.');
}
