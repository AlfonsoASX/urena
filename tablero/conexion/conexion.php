<?php 

$servidor="mysql:dbname=almacen;host=127.0.0.1";
$usuario="root";
$password="Siempre_estamos_unidos_";

try{
    $pdo = new PDO($servidor,$usuario,$password);
   // echo "Conectado..";
}catch(PDOException $e){
    echo "Falla en la conexión" . $e->getMessage();
    exit;
}
return $pdo;
?>