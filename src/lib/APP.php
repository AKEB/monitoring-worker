<?php

class APP {
	private $server_host = '';
	private $worker_name = '';

	public function config() {
		echo date("Y-m-d H:i:s",time()).' '.'Starting config'.PHP_EOL;
		$this->server_host = $_ENV['SERVER_HOST'] ?? '';
		$this->worker_name = $_ENV['WORKER_NAME'] ?? '';
	}

	public function init() {
		echo date("Y-m-d H:i:s",time()).' '.'Starting init'.PHP_EOL;
	}

	public function run():void {
		echo date("Y-m-d H:i:s",time()).' '.'Starting run loop...'.PHP_EOL;
		while (true) {
			$log_test = date("Y-m-d H:i:s", time()) . ' ';
			$log_test .= 'Server: '. $this->server_host.' ';
			$log_test .= 'Worker: '. $this->worker_name.' ';
			$log_test .= PHP_EOL;
			echo $log_test;
			sleep(5);
		}
	}
}