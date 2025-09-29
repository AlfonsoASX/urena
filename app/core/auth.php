<?php

function logged_in(){return !empty($_SESSION['user']);}

function current_user(){return $_SESSION['user'] ?? null;}

function require_login(){if(!logged_in())redirect('auth.login');}

function login($usuario,$pass){
  $u=qone("SELECT * FROM usuarios WHERE usuario=?",[$usuario]);
  if($u && password_verify($pass,$u['pass'])){
    $_SESSION['user']=$u;
    return true;
  }
  return false;
}

function logout(){unset($_SESSION['user']);}

function user_has_role($roles){
  $user=current_user();
  if(!$user) return false;
  $perfil=$user['perfil'] ?? null;
  if($perfil===null) return false;
  if($perfil==='admin') return true;
  if(is_string($roles)) $roles=[$roles];
  return in_array($perfil,$roles,true);
}

function require_role($roles){
  if(user_has_role($roles)) return;
  flash("<div class='alert alert-danger'>No tienes permisos para esta acción.</div>");
  redirect('home.index');
}
