<div class="row">
    <!--<div class="col-xl- col-lg-1 col-md-1 col-sm-1 mt-3"></div>-->
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mt-3 container-fluid text-center">
        <!--<input type="text" style="width: 200px;" class="form-control" placeholder="Buscar" id="busqueda" name="busqueda">    -->
        <br>
        <table class="table table-responsive table-striped table-dark">
                <thead>
                    <tr>
                      <th>Id_Act</th>
                      <th>Descripción</th>
                      <th>Detalles</th>
                      <th>Registrado_Por</th>
                      <th>Asignado_a</th>
                      <th>Fecha_Hora</th>
                      <th>Estatus</th>
                    </tr>
                </thead>
                <?php
                    $pdob = $pdo;
                    $pdoc = $pdo;
                    
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $querya = $pdo->prepare("SELECT ta.id_actividad, ta.descripcion, ta.estatus, ta.created_at, ta.id, u.nombre 
                    FROM tablero_actividades ta
                    INNER JOIN usuarios u ON ta.id = u.id
                    ORDER BY FIELD (estatus, 'nueva','planeada','proceso','finalizada')
                    LIMIT 1000
                    ");
                    $querya -> execute();
                    
                    
                    
                ?>
                <tbody id="tblista">
                <?php    
                    while ($rowa = $querya->fetch(PDO::FETCH_ASSOC)) {        
                ?>
                     <tr>
                        <td><?php echo $rowa['id_actividad'] ?></td>
                        <td><?php echo $rowa['descripcion'] ?></td>
                        <td><a href="actividad.php?id_act=<?php echo $rowa['id_actividad'] ?>" style="color: white; text-decoration-line: underline;">ver detalles</a></td>
                        
                        <td><?php echo $rowa['nombre'] ?></td>
                          <?php
                          $pdoc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $queryc = $pdoc->prepare("SELECT u.nombre FROM tablero_asignaciones ta
                          INNER JOIN usuarios u ON ta.id = u.id 
                          WHERE id_actividad = :id_actividad 
                          ORDER BY id_asignacion DESC LIMIT 1");
                          $queryc->bindParam(':id_actividad',$rowa['id_actividad']);
                          $queryc -> execute();
                          if ($rowc = $queryc->fetch(PDO::FETCH_ASSOC)) {
                            ?> 
                            <td><?php echo $rowc['nombre'] ?></td>
                            <?php
                          }else {
                            ?> 
                            <td>sin asignar</td>
                            <?php
                          }
                          
                          ?>
                        
                        <td><?php echo $nueva_fecha = date("d-m-Y H:i:s", strtotime($rowa['created_at'])); ?></td>
                        
                        <?php 
                          if ($rowa['estatus']=="nueva") {
                            echo '<td class="text-danger">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="proceso") {
                            echo '<td class="text-primary">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="planeada") {
                            echo '<td class="text-warning">' . $rowa['estatus']  . '</td>';
                          }elseif ($rowa['estatus']=="finalizada") {
                            echo '<td class="text-success">' . $rowa['estatus']  . '</td>';
                          }
                        ?>
                     </tr>
                     <?php 
                        }
                        $pdo = null;
                        $pdob = null;
                        $pdoc = null;
                        $querya = null;
                      ?>
                </tbody>
            </table>
        </div>    
    </div>