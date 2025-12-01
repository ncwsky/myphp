<?php

require_once __DIR__ . '/../ext/Image.php';

$w = (int)($_GET['w'] ?? 80);
$h = (int)($_GET['h'] ?? 36);
$size = (int)($_GET['size'] ?? 18);
$len = (int)($_GET['len'] ?? 4);
$type = (int)($_GET['number'] ?? 0);

Image::code($w, $h, $size, $len, $type, function ($code): void {
    if (!isset($_SESSION)) { //如果没有开启，session，则开启session
        session_start();
    }
    $_SESSION['code'] = strtolower($code); //小写
});
