<?php

class APP {
	private array $jobs = [];
	private int $job_sync_last_time = 0;
	private bool $restart_command_from_server = false;

	private int $send_state_time = 0;
	private array $send_state_jobs = [];

	const JOB_TYPE_CURL = 0;

	private function syncConfigData(?array $configData) {
		if (!$configData || !is_array($configData)) return;
		$this->restart_command_from_server = false;
		\Config::getInstance()->worker_id = intval(isset($configData['worker_id']) && $configData['worker_id'] ? $configData['worker_id'] : \Config::getInstance()->worker_id);
		\Config::getInstance()->worker_threads = intval(isset($configData['worker_threads']) && $configData['worker_threads'] ? $configData['worker_threads'] : \Config::getInstance()->worker_threads);
		\Config::getInstance()->jobs_get_timeout = intval(isset($configData['jobs_get_timeout']) && $configData['jobs_get_timeout'] ? $configData['jobs_get_timeout'] : \Config::getInstance()->jobs_get_timeout);
		\Config::getInstance()->loop_timeout = intval(isset($configData['loop_timeout']) && $configData['loop_timeout'] ? $configData['loop_timeout'] : \Config::getInstance()->loop_timeout);
		\Config::getInstance()->response_send_timeout = intval(isset($configData['response_send_timeout']) && $configData['response_send_timeout'] ? $configData['response_send_timeout'] : \Config::getInstance()->response_send_timeout);
		\Config::getInstance()->logs_write_timeout = intval(isset($configData['logs_write_timeout']) && $configData['logs_write_timeout'] ? $configData['logs_write_timeout'] : \Config::getInstance()->logs_write_timeout);
		if (isset($configData['restart']) && $configData['restart'] == 'true') {
			$this->restart_command_from_server = true;
		}
	}

	private function syncJobsData(?array $jobsData) {
		if (!$jobsData || !is_array($jobsData)) return;
		$jobs = [];
		$exist_job_ids = array_keys($this->jobs);
		$new_job_ids = [];
		foreach ($jobsData as $job) {
			$jobs[$job['job_id']] = $job;
			if (!in_array($job['job_id'], $exist_job_ids)) {
				$new_job_ids[] = $job['job_id'];
			}
		}
		if ($this->jobs) {
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
				if (
					isset($this->jobs[$job_id]['job']) &&
					is_array($this->jobs[$job_id]['job']) &&
					isset($this->jobs[$job_id]['job']['start_time']) &&
					$this->jobs[$job_id]['job']['start_time'] > 0
				) {
					$jobs[$job_id]['start_time'] = $this->jobs[$job_id]['job']['start_time'];
				}

				$this->jobs[$job_id]['job'] = $jobs[$job_id];
			}
		}
		foreach ($new_job_ids as $job_id) {
			$this->jobs[$job_id]['status'] = 'created';
			$this->jobs[$job_id]['job'] = $jobs[$job_id];
		}

		$this->job_sync_last_time = time();
	}

	private function getJobs() {
		\Config::getInstance()->error_log('Starting getJobs function');
		$params = [
			'worker_key_hash' => \Config::getInstance()->worker_key_hash,
			'protocol_version' => \Config::getInstance()->protocol_version,
			'worker_version' => \Config::getInstance()->worker_version,
		];
		$headers = [
			'Accept: application/json',
			'Accept-Encoding: gzip, deflate',
			'Accept-Language: en-US,en;q=0.5',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=utf-8',
			'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
		];
		$curl = new \AKEB\CurlGet(\Config::getInstance()->server_host . 'api/monitoring/get/', [], [], $headers);
		$curl->setBody(json_encode($params));
		$curl->setMethod('POST');
		$curl->setDebug(false);
		$curl->setSslVerify(false);
		if (\Config::getInstance()->proxy_host) {
			$curl->useProxy(\Config::getInstance()->proxy_host, \Config::getInstance()->proxy_type);
		}
		$curl->timeout = 10;
		$curl->connectTimeout = 20;
		$curl->exec();
		if ($curl->responseCode != 200 || !$curl->responseBody) {
			\Config::getInstance()->error_log('Response code: '. $curl->responseCode.' Response Error:'. $curl->responseError);
			sleep(5);
			exit(1);
		}
		if (!is_array($curl->responseBody)) {
			$curl->responseBody = json_decode($curl->responseBody, true);
		}
		if (isset($curl->responseBody['error']) && $curl->responseBody['error']) {
			\Config::getInstance()->error_log('Response code: '. $curl->responseCode.' Response Error:'. $curl->responseBody['error']);
			sleep(5);
			exit(1);
		}
		$configData = $curl->responseBody['data'] ?? [];
		$jobsData = $curl->responseBody['jobs'] ?? [];
		if (!$configData && !$jobsData) {
			\Config::getInstance()->error_log('Response code: '. $curl->responseCode.' Response:'. json_encode($curl->responseBody));
			sleep(5);
			exit(1);
		}
		$this->syncConfigData($configData);
		$this->syncJobsData($jobsData);
		\Config::getInstance()->error_log('Finished getJobs function');
	}

	public function init() {
		\Config::getInstance()->error_log('Starting init function');
		$this->getJobs();
		\Config::getInstance()->error_log('Finished init function');
	}

	public function loop(): void {
		global $PWD;
		\Config::getInstance()->error_log('Starting loop function');
		$log_file = '/var/log/php/php_errors.log';
		$descriptor_spec = [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"],
			2 => ["file", $log_file, "a"],
		];
		$cwd = $PWD;
		$command_php = 'php -d memory_limit=32M -d allow_url_fopen=true -d error_log='.$log_file;
		$log_time = 0;
		$this->send_state_time = 0;
		$this->send_state_jobs = [];
		while (true && !$this->restart_command_from_server) {
			if ($this->job_sync_last_time < time() - \Config::getInstance()->jobs_get_timeout) {
				$this->getJobs();
				$this->job_sync_last_time = time();
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
					$process_stat=proc_get_status($job['resource']);
					// var_dump($process_stat);
					if ($process_stat['running'] == false) {
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
						if ($process_stat['exitcode'] == 0 || ($process_stat['exitcode'] == -1 && $process_stat['termsig'] == 15)) {
							\Config::getInstance()->error_log('Finish job_id: '.$job_id);
							$job['job']['update_time'] = time() - (time() - intval($job['job']['start_time']??time()));
							$job['state'] = 'finished';
						} else {
							\Config::getInstance()->error_log('Finish error job_id: '.$job_id);
							$job['job']['update_time'] = time() - $job['job']['repeat_seconds'];
							$job['state'] = 'finished with error';
						}
						$this->send_state_jobs[] = $job;
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
					if (!is_array($job['job'])) continue;
					if (!isset($job['job']['type'])) continue;

					if ($job['job']['update_time'] + $job['job']['repeat_seconds'] > time()) {
						$job['state'] = 'waiting';
						continue;
					}
					if (\Config::getInstance()->worker_threads <= $running_threads) {
						$job['state'] = '';
						continue;
					}
					if ($job['job']['type'] == static::JOB_TYPE_CURL) {
						\Config::getInstance()->error_log('Start job_id: '.$job_id);
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
							$job['job']['start_time'] = time();
						} else {
							// Не удалось запустить, откладываем на 5 секунд
							$job['job']['update_time'] = time() + 5 - $job['job']['repeat_seconds'];
							$job['state'] = 'error_running';
						}
					} else {
						$job['job']['update_time'] = time() + 3600;
					}
				}
			}
			unset($job);
			if ($this->send_state_time < time() - \Config::getInstance()->response_send_timeout && $this->send_state_jobs) {
				// Отправить результат и update_time
				$this->sendJobsState();
			}
			if ($log_time < time() - \Config::getInstance()->logs_write_timeout) {
				$log_time = time();
				\Config::getInstance()->error_log('Loop: '.\Config::getInstance()->logs_write_timeout);
				foreach ($this->jobs as $job_id => $job) {
					$log_test = 'Job ID: '. $job_id.' ';
					$log_test .= 'Status: '. $job['status'].' ';
					$log_test .= 'State: '. $job['state'].' ';
					$log_test .= 'StartTime: '. date("Y-m-d H:i:s", $job['job']['start_time'] ?? 0).' ';
					$log_test .= 'UpdateTime: '. date("Y-m-d H:i:s", $job['job']['update_time'] ?? 0).' ';
					\Config::getInstance()->error_log($log_test);
				}
			}
			usleep(\Config::getInstance()->loop_timeout);
		}
	}

	private function sendJobsState(): void {
		if (!$this->send_state_jobs) {
			$this->send_state_time = time();
			$this->send_state_jobs = [];
			return;
		}
		\Config::getInstance()->error_log('Starting sendJobsState function');
		$params = [
			'worker_key_hash' => \Config::getInstance()->worker_key_hash,
			'protocol_version' => \Config::getInstance()->protocol_version,
			'worker_version' => \Config::getInstance()->worker_version,
			'jobs' => [],
		];
		$headers = [
			'Accept: application/json',
			'Accept-Encoding: gzip, deflate',
			'Accept-Language: en-US,en;q=0.5',
			'Cache-Control: no-cache',
			'Content-Type: application/json;charset=utf-8',
			'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
		];
		foreach ($this->send_state_jobs as $job) {
			if (!$job || !is_array($job) || !isset($job['job']) || !is_array($job['job']) || !isset($job['job']['job_id']) || !$job['job']['job_id']) continue;
			\Config::getInstance()->error_log(sprintf('Sending job %d monitor %d', $job['job']['job_id'], $job['job']['id']));
			$params['jobs'][] = [
				'job_id' => $job['job']['job_id'],
				'monitor_id' => $job['job']['id'],
				'update_time' => $job['job']['update_time'],
				'response' => $job['response'],
			];
		}
		if (count($params['jobs']) < 1) {
			$this->send_state_time = time();
			$this->send_state_jobs = [];
			return;
		}
		$curl = new \AKEB\CurlGet(\Config::getInstance()->server_host . 'api/monitoring/state/', [], [], $headers);
		$curl->setBody(json_encode($params));
		$curl->setMethod('POST');
		$curl->setDebug(false);
		$curl->setSslVerify(false);
		if (\Config::getInstance()->proxy_host) {
			$curl->useProxy(\Config::getInstance()->proxy_host, \Config::getInstance()->proxy_type);
		}
		$curl->timeout = 10;
		$curl->connectTimeout = 20;
		$curl->exec();
		if ($curl->responseCode != 200 ||!$curl->responseBody) {
			$this->send_state_time = time();
			return;
		}
		$this->send_state_time = time();
		$this->send_state_jobs = [];
		\Config::getInstance()->error_log('Finished sendJobsState function');
	}

}
