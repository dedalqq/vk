#!/usr/bin/php5
<?php

include('../vk.php');
$config = include('config.php');

$call_bake = function($name, $text) {
    exec("notify-send -i ax-applet -h int:x:100 '{$name}' '{$text}'\n");
};

$vk = new vk($config['app_id'], $config['token']);
$vk->connectToLongPoll($call_bake);