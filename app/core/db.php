<?php
function db() {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfg = $GLOBALS['cfg'];
  $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // 🚀 Permitir consultas grandes
  $pdo->exec("SET SQL_BIG_SELECTS=1");

  return $pdo;
}

function q($sql, $params = []) {
  $pdo = db();
  $pdo->exec("SET SQL_BIG_SELECTS=1");
  $st = $pdo->prepare($sql);
  return $st->execute($params);
}

function qall($sql, $params = []) {
  $pdo = db();
  $pdo->exec("SET SQL_BIG_SELECTS=1");
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

function qone($sql, $params = []) {
  $pdo = db();
  $pdo->exec("SET SQL_BIG_SELECTS=1");
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $r = $st->fetch();
  return $r ?: null;
}
