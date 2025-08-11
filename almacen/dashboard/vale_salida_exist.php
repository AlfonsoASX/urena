<?php
require 'conexion.php';
$ing_art1 = $_POST['articulo'];
$ing_marc1 = $_POST['marca'];
var_dump($_POST);
//consulta para obtener la existencia actual
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$query = $pdo->prepare("SELECT id, articulo, marca, existencias FROM articulos WHERE articulo=:ing_art1 AND marca=:ing_marc1");
$query->bindParam(':ing_art1', $ing_art1);
$query->bindParam(':ing_marc1', $ing_marc1);
$query->execute();

// $cadena = "<select class='form-control ml-3' style='width: 200px;'  id='marca' name='marca'>";
$cadena2 = "<input type='text' class='form-control text-center ml-3' id='existencias' name='existencias' style='width: 110px;' value='";
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    // $cadena = $cadena . '<option value=' . $row['marca'] . '>' . $row['marca'] . '</option>';
    $cadena2 = $cadena2 . $row['existencias'] . "'>";
    echo $cadena2;
}
 // echo $cadena . "</select>";
 
?>