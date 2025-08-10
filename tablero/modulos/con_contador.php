<?php

$nueva = 0;
$proceso = 0;
$pendiente = 0;
$planeada = 0;
$finalizada = 0;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$query = $pdo->prepare("SELECT estatus FROM tablero_actividades
LIMIT 1000
");
$query -> execute();
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    if ($row['estatus']=='nueva') {
        $nueva++;
    }elseif ($row['estatus']=='proceso') {
        $proceso++;
    }elseif ($row['estatus']=='pendiente') {
        $pendiente++;
    }elseif ($row['estatus']=='planeada') {
        $planeada++;
    }elseif ($row['estatus']=='finalizada') {
        $finalizada++;
    }
} 

?>