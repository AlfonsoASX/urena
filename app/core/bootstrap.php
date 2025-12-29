<?php
session_start();
$cfg = require __DIR__.'/../config/.env.php';
$GLOBALS['cfg'] = $cfg;

require __DIR__.'/db.php';
require __DIR__.'/helpers.php';
require __DIR__.'/ui.php';
require __DIR__.'/auth.php';

