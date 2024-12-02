<?php
require_once("./vendor/autoload.php");
$mypid = getmypid();

srand(intval(round(microtime(true)*100)+$mypid));
mt_srand(intval(round(microtime(true)*100)+$mypid));

$sleep = rand(0, 20000) / 10000;
error_log($mypid.' '."start ".$sleep);

$f = fopen('php://stdin', 'r');
if ($f) {
	$requestBody = '';
	do {
		$d = fread($f, 4096);
		$requestBody .= $d;
	} while ($d);
	fclose($f);
}
if (!$requestBody) $requestBody = '';

if ($requestBody && is_string($requestBody)) {
	$requestBody = @json_decode($requestBody, true);
}
$response = [
	'status' => 0,
];
if ($requestBody && is_array($requestBody)) {
	// CURL request
	sleep(intval($sleep));


	$response = [
		'status' => 1,
		'status_code' => 200,
		'status_text' => 'OK',
		'response_time' => $sleep,
		'response_text' => 'Ok',
		'response_unixtime' => time(),
		'job' => $requestBody,
	];
}





print(json_encode($response));

// echo "END";
error_log($mypid.' '."end");
exit(0);