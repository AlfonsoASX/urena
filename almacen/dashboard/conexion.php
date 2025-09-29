<?php 

$servidor="mysql:dbname=ganas001_urena;host=ganas001.mysql.guardedhost.com";
$usuario="ganas001_urena";
$password="t*H3q2pb6Kk)";

try{
    $pdo = new PDO($servidor,$usuario,$password);
   // echo "Conectado..";
}catch(PDOException $e){
    echo "Falla en la conexión" . $e->getMessage();
    exit;
}
return $pdo;