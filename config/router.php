<?php
// Router for PHP's built-in web server (php -S): serve existing files and folders directly,
// send everything else (pretty permalinks like /hallo-welt/) to WordPress' index.php.
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($path !== '/' && file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
