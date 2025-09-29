<?php
function db() {
  static $pdo=null;
  if ($pdo) return $pdo;
  $cfg = $GLOBALS['cfg'];
  $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
function q($sql,$params=[]){$st=db()->prepare($sql);return $st->execute($params);}
function qall($sql,$params=[]){$st=db()->prepare($sql);$st->execute($params);return $st->fetchAll();}
function qone($sql,$params=[]){$st=db()->prepare($sql);$st->execute($params);$r=$st->fetch();return $r?:null;}
