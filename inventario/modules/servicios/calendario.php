<?php
// 1. CONFIGURACIÓN Y DATOS
require_once '../../config/db.php';
require_once '../../config/global.php';
require_once '../../includes/auth_check.php';

// 2. OBTENER SERVICIOS ACTIVOS PARA EL CALENDARIO
// Buscamos servicios activos para proyectarlos en el calendario
$sql = "SELECT s.id_servicio, s.folio, s.modalidad, s.created_at, 
               f.nom_fallecido, f.dom_velacion, f.municipio
        FROM servicios s
        INNER JOIN servicio_fallecido sf ON s.id_servicio = sf.id_servicio
        INNER JOIN fallecido f ON sf.id_fallecido = f.id_fallecido
        WHERE s.eliminado = 0 AND s.cerrado = 0";

$resultado = mysqli_query($conexion, $sql);

$eventos = [];

while($row = mysqli_fetch_assoc($resultado)) {
    
    // Determinar color según ubicación (Lógica de Negocio)
    // Si la dirección contiene "Capilla" o "Sala", es interno.
    $ubicacion = strtolower($row['dom_velacion']);
    $color_clase = 'bg-success'; // Default: Domicilio (Verde)
    
    if (strpos($ubicacion, 'capilla') !== false || strpos($ubicacion, 'sala') !== false || strpos($ubicacion, 'funeraria') !== false) {
        $color_clase = 'bg-primary'; // Capilla (Azul)
    } elseif ($row['modalidad'] == 'cremacion') {
        $color_clase = 'bg-warning'; // Cremación directa (Amarillo/Naranja)
    }

    // Construir objeto evento para FullCalendar
    $eventos[] = [
        'id' => $row['id_servicio'],
        'title' => $row['nom_fallecido'],
        'start' => date('Y-m-d\TH:i:s', strtotime($row['created_at'])), // Formato ISO8601
        'description' => $row['dom_velacion'],
        'className' => $color_clase,
        'extendedProps' => [
            'folio' => $row['folio'],
            'modalidad' => ucfirst($row['modalidad']),
            'ubicacion' => $row['dom_velacion']
        ]
    ];
}

// Convertir a JSON para que JS lo lea
$json_eventos = json_encode($eventos);

// 3. UI
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<link href="<?php echo BASE_URL; ?>src/plugins/src/fullcalendar/fullcalendar.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo BASE_URL; ?>src/plugins/css/light/fullcalendar/custom-fullcalendar.css" rel="stylesheet" type="text/css" />
<link href="<?php echo BASE_URL; ?>src/plugins/css/dark/fullcalendar/custom-fullcalendar.css" rel="stylesheet" type="text/css" />

<style>
    /* Ajuste de altura para que el calendario se vea bien */
    #calendar { height: 750px; }
    .fc-event { cursor: pointer; }
</style>

<div id="content" class="main-content">
    <div class="layout-px-spacing">

        <div class="middle-content container-xxl p-0">
            
            <div class="secondary-nav">
                <div class="breadcrumbs-container" data-page-heading="Analytics">
                    <header class="header navbar navbar-expand-sm">
                        <div class="d-flex breadcrumb-content">
                            <div class="page-header">
                                <div class="page-title"><h3>Calendario de Salas y Servicios</h3></div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <a href="asignar.php" class="btn btn-secondary">Nueva Asignación</a>
                        </div>
                    </header>
                </div>
            </div>

            <div class="row layout-top-spacing">
                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12 layout-spacing">
                    <div class="widget widget-calendar">
                        <div class="widget-content">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="modalEvento" tabindex="-1" role="dialog" aria-labelledby="lblModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lblModal">Detalle del Servicio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="avatar avatar-xl">
                            <span class="avatar-title rounded-circle bg-primary-transparent text-primary" id="mdlLetra">SR</span>
                        </div>
                    </div>
                    <h4 class="text-center" id="mdlTitulo">Nombre Fallecido</h4>
                    <p class="text-center text-muted" id="mdlFolio">#FOLIO</p>
                    
                    <div class="list-group mt-4">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Ubicación:</strong>
                            <span id="mdlUbicacion" class="text-end">Dirección</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Modalidad:</strong>
                            <span id="mdlModalidad">Inhumación</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Inicio:</strong>
                            <span id="mdlFecha">00/00/0000</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="btnGestionar" class="btn btn-primary">Gestionar Servicio</a>
                </div>
            </div>
        </div>
    </div>

    <?php 
    $extra_scripts = '
    <script src="'.BASE_URL.'src/plugins/src/fullcalendar/fullcalendar.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var calendarEl = document.getElementById("calendar");
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: "dayGridMonth",
                headerToolbar: {
                    left: "prev,next today",
                    center: "title",
                    right: "dayGridMonth,timeGridWeek,timeGridDay"
                },
                locale: "es", // Idioma español
                events: '.$json_eventos.', // Inyectamos JSON desde PHP
                
                eventClick: function(info) {
                    // Llenar Modal con datos del evento clickeado
                    var props = info.event.extendedProps;
                    
                    document.getElementById("mdlTitulo").innerText = info.event.title;
                    document.getElementById("mdlFolio").innerText = "Folio: " + props.folio;
                    document.getElementById("mdlUbicacion").innerText = props.ubicacion;
                    document.getElementById("mdlModalidad").innerText = props.modalidad;
                    document.getElementById("mdlFecha").innerText = info.event.start.toLocaleString();
                    
                    // Configurar botón de ir a gestión
                    document.getElementById("btnGestionar").href = "seguimiento.php?id=" + info.event.id;
                    
                    // Mostrar modal
                    var myModal = new bootstrap.Modal(document.getElementById("modalEvento"));
                    myModal.show();
                }
            });
            
            calendar.render();
        });
    </script>
    ';
    
    include '../../includes/footer.php'; 
    ?>
</div>