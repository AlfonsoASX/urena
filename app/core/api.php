<?php

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/auth.php';

function api_route_name(): string {
  return (string)($_GET['r'] ?? '');
}

function api_is_request(): bool {
  $route = api_route_name();
  if ($route !== '') {
    [$module] = explode('.', $route) + [''];
    if (str_ends_with((string)$module, '_api')) {
      return true;
    }
  }
  $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
  $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  return strpos($accept, 'application/json') !== false || $xhr === 'xmlhttprequest';
}

function api_payload(): array {
  static $payload = null;
  if ($payload !== null) return $payload;

  $payload = $_POST;
  $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
  if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
      $decoded = json_decode($raw, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = $decoded;
      }
    }
  }

  return $payload;
}

function api_send(array $payload, int $status = 200): void {
  if (!headers_sent()) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function api_success($data = null, string $message = 'OK', int $status = 200): void {
  api_send([
    'ok' => true,
    'message' => $message,
    'data' => $data,
  ], $status);
}

function api_error(int $status, string $message, array $extra = []): void {
  api_send(array_merge([
    'ok' => false,
    'message' => $message,
  ], $extra), $status);
}

function api_require_login(): void {
  if (logged_in()) return;
  api_error(401, 'Sesion no autenticada.');
}

function api_require_role($roles): void {
  if (user_has_role($roles)) return;
  api_error(403, 'No tienes permisos para esta accion.');
}

function api_verify_csrf_if_present(): void {
  $payload = api_payload();
  $token = $payload['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
  if ($token === null || $token === '') {
    return;
  }
  $sessionToken = $_SESSION['_csrf'] ?? '';
  if (!$sessionToken || !hash_equals($sessionToken, (string)$token)) {
    api_error(403, 'Token CSRF invalido.');
  }
}
