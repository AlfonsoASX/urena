<?php
function form_input($name,$label,$value='',$opts=[]){
  $type=$opts['type']??'text';
  $req=!empty($opts['required'])?'required':'';
  return "<div class='mb-2'><label class='form-label'>$label</label>
    <input type='$type' name='$name' value='".e($value)."' class='form-control' $req></div>";
}
function form_select($name,$label,$options,$value=null){
  $out="<div class='mb-2'><label class='form-label'>$label</label><select name='$name' class='form-select'>";
  foreach($options as $v=>$t){
    $sel=((string)$v===(string)$value)?'selected':'';
    $out.="<option value='".e($v)."' $sel>".e($t)."</option>";
  }
  return $out."</select></div>";
}
function ui_table($headers,$rows){
  $h="<table class='table table-striped'><thead><tr>";
  foreach($headers as $th)$h.="<th>$th</th>";
  $h.="</tr></thead><tbody>";
  foreach($rows as $r){$h.="<tr>";foreach($r as $c)$h.="<td>$c</td>";$h.="</tr>";}
  return $h."</tbody></table>";
}
