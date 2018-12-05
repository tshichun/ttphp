<?php
$path = dirname(__FILE__) . '/';
$env = substr(strrchr($path, '-'), 1, 3);
require($path . 'library/app.php');
App::run($path, $env ? $env : 'dev');
