<?php
//session_start();
if (isset($_SESSION["usuario"])) {
  require 'conexion.php';

    //CONSULTA PARA OBTENER EL TOTAL DE EQUIPOS
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $querya = $pdo->prepare("SELECT * FROM equipos ");
      $querya->execute();
      $bb=0;
      $ca=0;
      $cd=0;
      $cr=0;
      $fl=0;
      $pd=0;
      $si=0;
      $tr=0;
      $bbd=0;
      $cad=0;
      $cdd=0;
      $crd=0;
      $fld=0;
      $pdd=0;
      $sid=0;
      $trd=0;
      while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {
        if ($rowa['equipo']=='biombo') {
          $bb=$bb+1;
          if ($rowa['estatus']=='disponible') {
            $bbd=$bbd+1;
          }
        }elseif ($rowa['equipo']=='carpa') {
          $ca=$ca+1;
          if ($rowa['estatus']=='disponible') {
            $cad=$cad+1;
          }
        }elseif ($rowa['equipo']=='candeleros') {
          $cd=$cd+1;
          if ($rowa['estatus']=='disponible') {
            $cdd=$cdd+1;
          }
        }elseif ($rowa['equipo']=='cristo_angel') {
          $cr=$cr+1;
          if ($rowa['estatus']=='disponible') {
            $crd=$crd+1;
          }
        }elseif ($rowa['equipo']=='floreros') {
          $fl=$fl+1;
          if ($rowa['estatus']=='disponible') {
            $fld=$fld+1;
          }
        }elseif ($rowa['equipo']=='pedestal') {
          $pd=$pd+1;
          if ($rowa['estatus']=='disponible') {
            $pdd=$pdd+1;
          }
        }elseif ($rowa['equipo']=='sillas') {
          $si=$si+1;
          if ($rowa['estatus']=='disponible') {
            $sid=$sid+1;
          }
        }elseif ($rowa['equipo']=='torcheros') {
          $tr=$tr+1;
          if ($rowa['estatus']=='disponible') {
            $trd=$trd+1;
          }
        }
      }

  
        ?>
        <tr>
              <td>biombo</td>
              <td><?php echo $bb-2 ?></td>
              <td><?php echo $bbd-1 ?></td>
        </tr>
        <tr>
              <td>carpa</td>
              <td><?php echo $ca-2 ?></td>
              <td><?php echo $cad-1 ?></td>
        </tr>
        <tr>
              <td>candeleros</td>
              <td><?php echo $cd-2 ?></td>
              <td><?php echo $cdd-1 ?></td>
        </tr>
        <tr>
              <td>cristo_angel</td>
              <td><?php echo $cr-2 ?></td>
              <td><?php echo $crd-1 ?></td>
        </tr>
        <tr>
              <td>floreros</td>
              <td><?php echo $fl-2 ?></td>
              <td><?php echo $fld-1 ?></td>
        </tr>
        <tr>
              <td>pedestal</td>
              <td><?php echo $pd-2 ?></td>
              <td><?php echo $pdd-1 ?></td>
        </tr>
        <tr>
              <td>sillas</td>
              <td><?php echo $si-2 ?></td>
              <td><?php echo $sid-1 ?></td>
        </tr>
        <tr>
              <td>torcheros</td>
              <td><?php echo $tr-2 ?></td>
              <td><?php echo $trd-1 ?></td>
        </tr>
      
      <?php
      
    
}else {
    header("location:index.php");
  }
?>