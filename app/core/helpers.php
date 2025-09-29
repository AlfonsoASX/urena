<?php
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}
function redirect($r){header("Location: ?r=$r");exit;}
function render($title,$html){
  $content=$html;
  $GLOBALS['page_title']=$title;
  require __DIR__.'/../layout/base.php';
  exit;
}
