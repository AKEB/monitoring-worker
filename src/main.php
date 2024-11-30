<?php

require_once("./vendor/autoload.php");

$app = new \APP();

$app->config();

$app->init();

$app->run();

echo "Exiting";