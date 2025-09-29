<?php
function logged_in(){return !empty($_SESSION['user']);}
function require_login(){if(!logged_in())redirect('auth.login');}
function login($usuario,$pass){
  $u=qone("SELECT * FROM usuarios WHERE usuario=?",[$usuario]);
  if($u && password_verify($pass,$u['pass'])){
    $_SESSION['user']=$u;return true;
  }
  return false;
}
function logout(){unset($_SESSION['user']);}
