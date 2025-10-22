<?php
require_once("./version.php");
require_once("./vendor/autoload.php");
srand(intval(round(microtime(true)*100)));
mt_srand(intval(round(microtime(true)*100)));

global $PWD;
$PWD = __DIR__;

$app = new \APP();

\Config::getInstance();
date_default_timezone_set(\Config::getInstance()->timezone);

$app->init();

$app->loop();

echo "Exiting";
