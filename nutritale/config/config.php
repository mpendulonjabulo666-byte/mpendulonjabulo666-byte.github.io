<?php
// Fill these in with your own database's connection details before
// running install.php. If you're on shared hosting, your host's control
// panel (e.g. cPanel > MySQL Databases) will give you these values.
define('DB_HOST', 'localhost');
define('DB_NAME', 'nutritale');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'NutriTale');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');
