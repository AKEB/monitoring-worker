<?php

class APP {
	private $server_host = 'https://timeload-local.my.games/'; // '' // Server host [SERVER_HOST]
	private $worker_name = 'local'; // '' // Worker name [WORKER_NAME]
	private $worker_uid = ''; // Worker Unique identifier [WORKER_UID]
	private $worker_threads = 4; // Worker threads count [WORKER_THREADS]
	private $jobs_get_timeout = 5; // Как часто запрашивать новые данные в сервера (в секундах) [JOBS_GET_TIMEOUT]
	private $loop_timeout = 200000; // Задержка в цикле обработки (в миллисекундах) [LOOP_TIMEOUT]
	private $response_send_timeout = 5; // Как часто отправлять данные на сервер (в секундах) [RESPONSE_SEND_TIMEOUT]
	private $logs_write_timeout = 10; // Как часто писать сообщения в логах (в секундах) [LOGS_WRITE_TIMEOUT]

	private $worker_id = '';
	private $worker_version = '';
	private $key = 'a5kcsXCDgmHg9UG7gCHCek8adMsNHFeE';
	private $protocol_version = '1.0';
	private $jobs = [];
	private $job_sync_last_time = 0;

	const JOB_TYPE_CURL = 0;

	public function config() {
		echo date("Y-m-d H:i:s",time()).' '.'Starting config'.PHP_EOL;
		$this->server_host = strval($_ENV['SERVER_HOST'] ?? $this->server_host);
		$this->worker_name = strval($_ENV['WORKER_NAME'] ?? $this->worker_name);
		$this->worker_uid = strval($_ENV['WORKER_UID'] ?? md5($this->worker_name . '' . $this->server_host));
		$this->worker_threads = intval($_ENV['WORKER_THREADS'] ?? $this->worker_threads);

		$this->jobs_get_timeout = intval($_ENV['JOBS_GET_TIMEOUT'] ?? $this->jobs_get_timeout);
		$this->loop_timeout = intval($_ENV['LOOP_TIMEOUT'] ?? $this->loop_timeout);
		$this->response_send_timeout = intval($_ENV['RESPONSE_SEND_TIMEOUT'] ?? $this->response_send_timeout);
		$this->logs_write_timeout = intval($_ENV['LOGS_WRITE_TIMEOUT'] ?? $this->logs_write_timeout);
		$this->worker_version = defined('WORKER_VERSION') ? constant('WORKER_VERSION') : $this->worker_version;

		echo date("Y-m-d H:i:s",time()).' Config='.json_encode([
			'server_host' => $this->server_host,
			'worker_name' => $this->worker_name,
			'worker_uid' => $this->worker_uid,
			'worker_threads' => $this->worker_threads,
			'jobs_get_timeout' => $this->jobs_get_timeout,
			'loop_timeout' => $this->loop_timeout,
			'response_send_timeout' => $this->response_send_timeout,
			'logs_write_timeout' => $this->logs_write_timeout,
			'worker_version' => $this->worker_version,
		]) . PHP_EOL;

	}

	public function init() {
		echo date("Y-m-d H:i:s",time()).' '.'Starting init'.PHP_EOL;
		$params = [
			'worker_name' => $this->worker_name,
			'worker_uid' => $this->worker_uid,
			'key' => $this->key,
			'protocol_version' => $this->protocol_version,
			'worker_version' => $this->worker_version,
		];
		$headers = [
			'Accept: application/json',
			'Accept-Encoding: gzip, deflate',
			'Accept-Language: en-US,en;q=0.5',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=utf-8',
			'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
		];
		$curl = new \AKEB\CurlGet($this->server_host . 'api/monitoring/init/', [], [], $headers);
		$curl->setBody(json_encode($params));
		$curl->setMethod('POST');
		$curl->setDebug(false);
		$curl->setSslVerify(false);
		$curl->timeout = 10;
		$curl->connectTimeout = 20;
		$curl->exec();
		if ($curl->responseCode != 200 || !$curl->responseBody) {
			echo date("Y-m-d H:i:s",time()).' '.'Response code: '. $curl->responseCode.' Response Error:'. $curl->responseError. PHP_EOL;
			exit(1);
		}
		if (!is_array($curl->responseBody)) {
			$curl->responseBody = json_decode($curl->responseBody, true);
		}
		if (isset($curl->responseBody['error']) && $curl->responseBody['error']) {
			echo date("Y-m-d H:i:s",time()).' '.'Response code: '. $curl->responseCode. PHP_EOL;
			exit(1);
		}
		$response = $curl->responseBody['data'] ?? [];
		// $this->worker_name = $response['worker_name'] ?? $this->worker_name;
		$this->worker_id = intval($response['worker_id'] ?? $this->worker_id);
		$this->server_host = strval($response['server_host'] ?? $this->server_host);
		$this->key = strval($response['key'] ?? $this->key);
		$this->jobs_get_timeout = intval($response['jobs_get_timeout'] ?? $this->jobs_get_timeout);
		$this->loop_timeout = intval($response['loop_timeout'] ?? $this->loop_timeout);
		$this->response_send_timeout = intval($response['response_send_timeout'] ?? $this->response_send_timeout);
		$this->logs_write_timeout = intval($response['logs_write_timeout'] ?? $this->logs_write_timeout);
	}

	public function run():void {
		global $PWD;
		echo date("Y-m-d H:i:s",time()).' '.'Starting run loop...'.PHP_EOL;
		$logs = $PWD.'/logs/php_errors.log';

		$descriptor_spec = [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"], // ["pipe", "w"],
			2 => ["file", $logs, "a"],
		];
		$cwd = $PWD;
		$command_php = 'php -d memory_limit=32M -d allow_url_fopen=true -d error_log='.$logs;
		$log_time = 0;
		$send_time = 0;
		$send_jobs = [];
		while (true) {
			if ($this->job_sync_last_time < time() - $this->jobs_get_timeout) {
				$this->syncJobs();
				$this->job_sync_last_time = time();
				$log_test = date("Y-m-d H:i:s", time()) . ' ';
				$log_test .= 'Server: '. $this->server_host.' ';
				$log_test .= 'Worker: '. $this->worker_name.' ';
				$log_test .= 'Jobs: '.count($this->jobs).' ';
				echo $log_test.PHP_EOL;
			}

			$running_threads = 0;
			foreach ($this->jobs as $job_id=>$job) {
				if (isset($job['resource']) && $job['resource'] && is_resource($job['resource'])) {
					// Задача запущена
					$running_threads++;
				}
			}
			foreach ($this->jobs as $job_id=>&$job) {
				if (isset($job['resource']) && $job['resource'] && is_resource($job['resource'])) {
					// Задача запущена
					$etat=proc_get_status($job['resource']);
					// var_dump($etat);
					if ($etat['running'] == false) {
						// Задача завершилась
						$response = fread($job['pipes'][1], 4096);
						if ($response && is_string($response)) {
							$response = @json_decode($response, true);
						}
						if ($response && is_array($response)) {
							$job['response'] = $response;
						}
						fclose($job['pipes'][1]);
						proc_close($job['resource']);
						unset($job['resource']);
						$job['pipes'] = [];
						if ($etat['exitcode'] == 0 || ($etat['exitcode'] == -1 && $etat['termsig'] == 15)) {
							$job['job']['update_time'] = time();
							$job['state'] = 'finished';
						} else {
							$job['job']['update_time'] = time() - $job['job']['repeat_seconds'];
							$job['state'] = 'finished with error';
						}
						$send_jobs[] = $job;
					} else {
						// Задача работает
						$job['state'] = 'running';
					}
				} else {
					// Задача не запущена
					$job['state'] = '';
					if ($job['status'] == 'deleted' || !$job['job'] || !is_array($job['job'])) {
						// Задача удалена
						unset($this->jobs[$job_id]);
						continue;
					}
					$job['job'] = $job['job'] ?? [];
					if (!is_array(is_array($job['job']))) continue;
					if (!isset($job['job']['type'])) continue;

					if ($job['job']['update_time'] + $job['job']['repeat_seconds'] > time()) {
						$job['state'] = 'waiting';
						continue;
					}
					if ($this->worker_threads <= $running_threads) {
						$job['state'] = '';
						continue;
					}
					if ($job['job']['type'] == static::JOB_TYPE_CURL) {
						$job['pipes'] = [];
						$job['resource'] = proc_open(
							$command_php.' curl.php ',
							$descriptor_spec,
							$job['pipes'],
							$cwd
						);
						if (is_resource($job['resource'])) {
							$running_threads++;

							$job_json = json_encode($job['job']);
							fwrite($job['pipes'][0], $job_json, mb_strlen($job_json));
							stream_set_blocking($job['pipes'][0], 0);
							stream_set_timeout($job['pipes'][0], 5);
							fclose($job['pipes'][0]);
							$job['state'] = 'starting';
						} else {
							// Не удалось запустить, откладываем на 5 секунд
							$job['job']['update_time'] = time() + 5 - $job['job']['repeat_seconds'];
							$job['state'] = 'error_runing';
						}
					} else {
						$job['job']['update_time'] = time() + 3600;
					}
				}
			}
			unset($job);
			if ($send_time < time() - $this->response_send_timeout && $send_jobs) {
				// Отправить результат и update_time
				if ($this->sendJobState($send_jobs)) {
					$send_time = time();
					$send_jobs = [];
				}
			}
			if ($log_time < time() - $this->logs_write_timeout) {
				$log_time = time();
				$log_test = date("Y-m-d H:i:s", time()) . ' Loop ';
				echo $log_test.PHP_EOL;
				foreach ($this->jobs as $job_id => $job) {
					$log_test = date("Y-m-d H:i:s", time()) . ' ';
					$log_test.= 'Job ID: '. $job_id.' ';
					$log_test.= 'Status: '. $job['status'].' ';
					$log_test.= 'State: '. $job['state'].' ';
					$log_test.= 'UpdateTime: '. date("Y-m-d H:i:s", $job['job']['update_time'] ?? 0).' ';
					echo $log_test.PHP_EOL;
				}
			}
			usleep($this->loop_timeout);
		}
	}

	private function syncJobs(): void {
		echo date("Y-m-d H:i:s",time()).' '.'Starting syncJobs...'.PHP_EOL;
		$params = [
			'worker_id' => $this->worker_id,
			'key' => $this->key,
			'protocol_version' => $this->protocol_version,
		];
		$headers = [
			'Accept: application/json',
			'Accept-Encoding: gzip, deflate',
			'Accept-Language: en-US,en;q=0.5',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=utf-8',
			'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
		];
		$curl = new \AKEB\CurlGet($this->server_host . 'api/monitoring/get/', [], [], $headers);
		$curl->setBody(json_encode($params));
		$curl->setMethod('POST');
		$curl->setDebug(false);
		$curl->setSslVerify(false);
		$curl->timeout = 10;
		$curl->connectTimeout = 20;
		$curl->exec();

		if ($curl->responseCode != 200 || !$curl->responseBody) return;
		if (!is_array($curl->responseBody)) {
			$curl->responseBody = json_decode($curl->responseBody, true);
		}
		if (isset($curl->responseBody['error']) && $curl->responseBody['error']) return;
		$response = $curl->responseBody['data'] ?? [];
		$this->server_host = strval($response['server_host'] ?? $this->server_host);
		$this->key = strval($response['key'] ?? $this->key);
		$this->jobs_get_timeout = intval($response['jobs_get_timeout'] ?? $this->jobs_get_timeout);
		$this->loop_timeout = intval($response['loop_timeout'] ?? $this->loop_timeout);
		$this->response_send_timeout = intval($response['response_send_timeout'] ?? $this->response_send_timeout);
		$this->logs_write_timeout = intval($response['logs_write_timeout'] ?? $this->logs_write_timeout);

		$jobs = [];
		$exist_job_ids = array_keys($this->jobs);
		$new_job_ids = [];
		if (!isset($response['jobs']) || !$response['jobs'] || !is_array($response['jobs'])) return;
		foreach ($response['jobs'] as $job) {
			$jobs[$job['job_id']] = $job;
			if (!in_array($job['job_id'], $exist_job_ids)) {
				$new_job_ids[] = $job['job_id'];
			}
		}
		foreach ($this->jobs as $job_id => $job_info) {
			if (!isset($jobs[$job_id])) {
				$this->jobs[$job_id]['job'] = null;
				$this->jobs[$job_id]['status'] = 'deleted';
				continue;
			}
			$this->jobs[$job_id]['status'] = 'updated';
			if (
				isset($this->jobs[$job_id]['job']) &&
				is_array($this->jobs[$job_id]['job']) &&
				$this->jobs[$job_id]['job']['update_time'] > $jobs[$job_id]['update_time']
			) {
				$jobs[$job_id]['update_time'] = $this->jobs[$job_id]['job']['update_time'];
			}
			$this->jobs[$job_id]['job'] = $jobs[$job_id];
		}
		foreach ($new_job_ids as $job_id) {
			$this->jobs[$job_id]['status'] = 'created';
			$this->jobs[$job_id]['job'] = $jobs[$job_id];
		}
		$this->job_sync_last_time = time();
	}

	private function sendJobState(array $jobs) {
		echo date("Y-m-d H:i:s",time()).' '.'sendJobState '.PHP_EOL;
		$params = [
			'worker_id' => $this->worker_id,
			'key' => $this->key,
			'jobs' => [],
			'protocol_version' => $this->protocol_version,
		];
		foreach ($jobs as $job) {
			if (!$job || !is_array($job) || !isset($job['job']) || !is_array($job['job']) || !isset($job['job']['job_id']) || !$job['job']['job_id']) continue;
			echo "send job: " . $job['job']['job_id'] . PHP_EOL;
			$params['jobs'][] = [
				'job_id' => $job['job']['job_id'],
				'monitor_id' => $job['job']['id'],
				'update_time' => $job['job']['update_time'],
				'response' => $job['response'],
			];
		}
		var_export($params);
		if (count($params['jobs']) < 1) return false;

		$headers = [
			'Accept: application/json',
			'Accept-Encoding: gzip, deflate',
			'Accept-Language: en-US,en;q=0.5',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=utf-8',
			'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
		];
		$curl = new \AKEB\CurlGet($this->server_host . 'api/monitoring/jobSync/', [], [], $headers);
		$curl->setBody(json_encode($params));
		$curl->setMethod('POST');
		$curl->setDebug(false);
		$curl->setSslVerify(false);
		$curl->timeout = 10;
		$curl->connectTimeout = 20;
		$curl->exec();
		if ($curl->responseCode != 200 ||!$curl->responseBody) return false;
		echo date("Y-m-d H:i:s",time()).' '.'sendJobState True'.PHP_EOL;
		return true;
	}
}
