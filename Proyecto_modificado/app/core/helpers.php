<?php

/**
 * Helper: Escape HTML output.
 */
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: redirect to a given route.
 */
function redirect($r){
  header("Location: ?r=$r");
  exit;
}

/**
 * Flash helpers ------------------------------------------------------------
 */
function flash($html){
  if(!isset($_SESSION['_alerts'])) $_SESSION['_alerts']='';
  $_SESSION['_alerts'].=$html;
}

function consume_flash(){
  $html=$_SESSION['_alerts'] ?? '';
  unset($_SESSION['_alerts']);
  return $html;
}

/**
 * CSRF helpers -------------------------------------------------------------
 */
function csrf_token(){
  if(empty($_SESSION['_csrf'])){
    $_SESSION['_csrf']=bin2hex(random_bytes(16));
  }
  return $_SESSION['_csrf'];
}

function csrf_field(){
  return "<input type='hidden' name='_token' value='".e(csrf_token())."'>";
}

function csrf_verify(){
  $token=$_POST['_token'] ?? $_GET['_token'] ?? null;
  if(!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)){
    throw new RuntimeException('Token CSRF inválido.');
  }
}

/**
 * Rendering ---------------------------------------------------------------
 */
function render($title,$html){
  $content=$html;
  $GLOBALS['page_title']=$title;
  $GLOBALS['_flash']=consume_flash();
  require __DIR__.'/../layout/base.php';
  exit;
}

/**
 * Form helpers ------------------------------------------------------------
 */
function form_attrs(array $attrs): string {
  $pairs=[];
  foreach ($attrs as $k=>$v) {
    if ($v===false || $v===null) continue;
    if ($v===true) {
      $pairs[]=$k;
    } else {
      $pairs[]=$k."='".e($v)."'";
    }
  }
  return implode(' ', $pairs);
}

function form_input($name,$label,$value='',$opts=[]){
  $type=$opts['type'] ?? 'text';
  $help=$opts['help'] ?? '';
  $attrs=[
    'type'=>$type,
    'name'=>$name,
    'id'=>$opts['id'] ?? $name,
    'value'=>$type==='password' ? '' : ($opts['value'] ?? $value),
    'class'=>$opts['class'] ?? 'form-control',
    'required'=>!empty($opts['required']) ? 'required' : null,
    'min'=>$opts['min'] ?? null,
    'max'=>$opts['max'] ?? null,
    'step'=>$opts['step'] ?? null,
    'placeholder'=>$opts['placeholder'] ?? null,
    'maxlength'=>$opts['maxlength'] ?? null,
    'pattern'=>$opts['pattern'] ?? null,
    'autocomplete'=>$opts['autocomplete'] ?? null,
  ];
  if (!empty($opts['readonly'])) $attrs['readonly']='readonly';
  if (!empty($opts['disabled'])) $attrs['disabled']='disabled';
  $attrs = array_filter($attrs, fn($v) => $v !== null);
  $html="<div class='mb-2'><label class='form-label' for='".e($attrs['id'])."'>".e($label)."</label>";
  $html.="<input ".form_attrs($attrs).">";
  if($help){
    $html.="<div class='form-text'>".e($help)."</div>";
  }
  $html.="</div>";
  return $html;
}

function form_select($name,$label,$options,$value=null,$opts=[]){
  $attrs=[
    'name'=>$name,
    'id'=>$opts['id'] ?? $name,
    'class'=>$opts['class'] ?? 'form-select',
    'required'=>!empty($opts['required']) ? 'required' : null,
    'disabled'=>!empty($opts['disabled']) ? 'disabled' : null,
  ];
  $html="<div class='mb-2'><label class='form-label' for='".e($attrs['id'])."'>".e($label)."</label>";
  $html.="<select ".form_attrs(array_filter($attrs, fn($v)=>$v!==null)).">";
  if (!empty($opts['placeholder'])) {
    $html.="<option value=''>".e($opts['placeholder'])."</option>";
  }
  foreach($options as $v=>$t){
    $sel=((string)$v===(string)$value)?' selected':'';
    $html.="<option value='".e($v)."'$sel>".e($t)."</option>";
  }
  $html.="</select></div>";
  return $html;
}
