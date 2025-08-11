<?php
  require 'conexion.php';        
  $articulo = $_POST['articulo'];
  
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $query = $pdo->prepare("SELECT id, articulo, marca FROM articulos WHERE articulo= :articulo");
  $query->bindParam(':articulo', $articulo);
  $query->execute();

  $cadena = "<select class='form-control ml-3' style='width: 200px;'  id='marca' name='marca'>";
  while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $cadena = $cadena . '<option value=' . $row['marca'] . '>' . $row['marca'] . '</option>';
  }
  echo $cadena . "</select>";


  
  ?>