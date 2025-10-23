<?php
require_once("./vendor/autoload.php");
$mypid = getmypid();

srand(intval(round(microtime(true)*100)+$mypid));
mt_srand(intval(round(microtime(true)*100)+$mypid));

error_log($mypid.' '."start");

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
$job = $requestBody ?? [];
$response = [
	'status' => 0,
];
if ($job && is_array($job)) {
	// CURL request
	$response = [
		'status' => 1,
		'status_code' => 0,
		'status_text' => '',
		'response_unixtime' => time(),
	];

	// sleep(intval($sleep));
	if ($job['type'] == \APP::JOB_TYPE_CURL) {
		$curl = new \AKEB\CurlGet($job['url'],[],[],[
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
		]);
		$curl->setMethod($job['method'] ?? 'GET');
		$curl->timeout = $job['timeout'] ?? 24;
		$curl->connectTimeout = $job['timeout'] ?? 24;
		if (
			!isset($job['ssl_verify']) ||
			$job['ssl_verify'] === '1' ||
			$job['ssl_verify'] === 'true' ||
			$job['ssl_verify'] === 1
		) {
			$job['ssl_verify'] = true;
		}else {
			$job['ssl_verify'] = false;
		}
		$curl->setSslVerify($job['ssl_verify']);

		$curl->setDebug(false);
		$curl->setCurlopt(CURLOPT_FOLLOWLOCATION, true);
		$curl->setCurlopt(CURLOPT_CERTINFO, true);
		$curl->setCurlopt(CURLOPT_VERBOSE, false);
		$curl->setCurlopt(CURLOPT_MAXREDIRS, $job['max_redirects'] ?? 10);
		$curl->exec();
		$info = $curl->responseInfo;

		// $response['info'] = $info;
		$response['status'] = 1;
		$response['status_code'] = $info['http_code'] ?? 0;
		$response['status_text'] = '';
		$response['redirect_count'] = $info['redirect_count'] ?? 0;
		$response['total_time'] = $info['total_time'] ?? 0;
		$response['namelookup_time'] = $info['namelookup_time'] ?? 0;
		$response['connect_time'] = $info['connect_time'] ?? 0;
		$response['pretransfer_time'] = $info['pretransfer_time'] ?? 0;
		$response['starttransfer_time'] = $info['starttransfer_time'] ?? 0;
		$response['redirect_time'] = $info['redirect_time'] ?? 0;

		$response['total_time_us'] = $info['total_time_us'] ?? 0;
		$response['namelookup_time_us'] = $info['namelookup_time_us'] ?? 0;
		$response['connect_time_us'] = $info['connect_time_us'] ?? 0;
		$response['pretransfer_time_us'] = $info['pretransfer_time_us'] ?? 0;
		$response['starttransfer_time_us'] = $info['starttransfer_time_us'] ?? 0;
		$response['redirect_time_us'] = $info['redirect_time_us'] ?? 0;

		$response['appconnect_time_us'] = $info['appconnect_time_us'] ?? 0;
		$response['posttransfer_time_us'] = $info['posttransfer_time_us'] ?? 0;

		$response['effective_method'] = $info['effective_method'] ?? '';
		$response['primary_ip'] = $info['primary_ip'] ?? '';
		$response['primary_port'] = $info['primary_port'] ?? 0;
		$response['http_version'] = $info['http_version'] ?? 0;
		$response['protocol'] = $info['protocol'] ?? 0;
		$response['ssl_verifyresult'] = $info['ssl_verifyresult'] ?? 0;
		$response['scheme'] = $info['scheme'] ?? '';

		if (isset($info['certinfo']) && $info['certinfo'] && is_array($info['certinfo'])) {
			$cert = $info['certinfo'][0];
			$response['cert_expire'] = isset($cert['Expire date']) && $cert['Expire date'] ? strtotime($cert['Expire date']) : 0;
		}
	}
}

print(json_encode($response));

error_log($mypid.' '."end");
exit(0);
