<?php

spl_autoload_register(function($name) {
    $sep = DIRECTORY_SEPARATOR;
    $name = trim($name, '\\');
    if (preg_match('/^Eagleye\\\\/', $name)) {
        $name = str_replace(array('/', '\\'), $sep, preg_replace('/^Eagleye/', '', $name));
        $file = __DIR__. $sep. 'library'. $sep. $name. '.php';
        if (is_file($file)) {
            include_once($file);
        }
    }
});

define('SECRET_KEY', '.secret.data.');

date_default_timezone_set('Asia/Shanghai');