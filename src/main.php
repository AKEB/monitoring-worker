<?php
require_once("./version.php");
require_once("./vendor/autoload.php");
srand(intval(round(microtime(true)*100)));
mt_srand(intval(round(microtime(true)*100)));

global $PWD;
$PWD = __DIR__;

$app = new \APP();

$app->config();

$app->init();

$app->run();

echo "Exiting";
