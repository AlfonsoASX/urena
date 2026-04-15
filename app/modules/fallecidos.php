<?php
require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

require_login();

flash("<div class='alert alert-info'>La captura de fallecidos ahora se realiza dentro del servicio funerario.</div>");
redirect('servicios.listar');
