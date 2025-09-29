<?php
// app/modules/auth.php

require_once __DIR__.'/../core/db.php';
require_once __DIR__.'/../core/helpers.php';
require_once __DIR__.'/../core/ui.php';
require_once __DIR__.'/../core/auth.php';

switch ($action ?? 'login') {

  /* ===========================
   * LOGOUT
   * =========================*/
  case 'logout':
    logout();
    flash("<div class='alert alert-info'>Sesión cerrada.</div>");
    redirect('auth.login');
  break;

  /* ===========================
   * LOGIN (GET/POST)
   * =========================*/
  case 'login':
  default:
    // Si ya está logueado, redirige al panel
    if (logged_in()) {
      redirect('home.index');
    }

    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      try { csrf_verify(); } catch (RuntimeException $e) {
        $msg = "<div class='alert alert-danger'>Sesión inválida, vuelve a intentar.</div>";
      }
      $usuario = trim($_POST['usuario'] ?? '');
      $pass    = (string)($_POST['pass'] ?? '');

      if ($usuario === '' || $pass === '') {
        $msg = "<div class='alert alert-warning'>Ingresa usuario y contraseña.</div>";
      } else {
        // usa login() definido en core/auth.php (password_verify)
        if (login($usuario, $pass)) {
          flash("<div class='alert alert-success'>¡Bienvenido!</div>");
          redirect('home.index');
        } else {
          $msg = "<div class='alert alert-danger'>Usuario o contraseña incorrectos.</div>";
        }
      }
    }

    // Formulario
    ob_start(); ?>
    <div class="row justify-content-center mt-5">
      <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="h4 mb-3 text-center">Acceso</h1>
            <?= $msg ?>
            <form method="post" action="?r=auth.login" autocomplete="off">
              <?= csrf_field() ?>
              <?= form_input('usuario', 'Usuario', $_POST['usuario'] ?? '', ['required'=>true]) ?>
              <?= form_input('pass', 'Contraseña', '', ['type'=>'password','required'=>true]) ?>
              <button class="btn btn-primary w-100 mt-2">Entrar</button>
            </form>

          </div>
        </div>
      </div>
    </div>
    <?php
    render('Ingresar', ob_get_clean());
}
