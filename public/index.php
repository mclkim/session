<?php
require __DIR__ . '/../vendor/autoload.php';

ini_set('session.save_handler', 'files');
$handler = new Mcl\Session\SecureHandler();
session_set_save_handler($handler, true);
session_start();

if (empty($_SESSION['time'])) {
    $_SESSION['time'] = time(); // set the time
}
session_write_close();

var_dump($_SESSION);