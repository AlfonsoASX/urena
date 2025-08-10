<?php
//session_start();
if (isset($_SESSION["usuario"])) {
  require 'conexion.php';
//CONSULTA PARA OBTENER EL TOTAL DE EQUIPOS
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$querya = $pdo->prepare("SELECT * FROM equipos WHERE estatus = 'ocupado' AND id_equipo NOT LIKE '%1sin%' ");
$querya->execute();
while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {

    ?>
    <tr>
    <td><?php echo $rowa['equipo'] ?></td>
        <td><?php echo $rowa['id_equipo'] ?></td>
        <td><?php echo $rowa['estatus'] ?></td>
        <?php 
        //CONSULTA PARA OBTENER EL ID DEL SERVICIO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryb = $pdo->prepare("SELECT id_servicio FROM servicio_equipo WHERE id_equipo = :id_equipo AND fecha = :fecha");
        $queryb->bindParam(':id_equipo',$rowa['id_equipo']);
        $queryb->bindParam(':fecha',$rowa['updated_at']);
        $queryb->execute();
        $rowb = $queryb->fetch(PDO::FETCH_ASSOC);
        ?>
        <td><?php echo $rowb['id_servicio'] ?></td>
        <?php
        //CONSULTA PARA OBTENER EL ID DEL FALLECIDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryc = $pdo->prepare("SELECT id_fallecido FROM servicio_fallecido WHERE id_servicio = :id_servicio");
        $queryc->bindParam(':id_servicio',$rowb['id_servicio']);
        $queryc->execute();
        $rowc = $queryc->fetch(PDO::FETCH_ASSOC);
        //CONSULTA PARA OBTENER EL NOMBRE DEL FALLECIDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $queryd = $pdo->prepare("SELECT nom_fallecido FROM fallecido WHERE id_fallecido = :id_fallecido");
        $queryd->bindParam(':id_fallecido',$rowc['id_fallecido']);
        $queryd->execute();
        $rowd = $queryd->fetch(PDO::FETCH_ASSOC);

        ?>
        <td><?php echo $rowd['nom_fallecido'] ?></td>
        <td><?php echo $rowa['updated_at'] ?></td>
    </tr>
    <?php
}


}else {
    header("location:index.php");
  }
?>