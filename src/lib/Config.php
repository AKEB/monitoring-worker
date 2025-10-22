<?php

enum ConfigEnvType {
	case INT;
	case FLOAT;
	case BOOL;
	case STRING;
	case ARRAY;
	case OBJECT;
	case DEFAULT;
}

class Config {
	public ?int $worker_id;

	public int $worker_threads; // Worker threads count [WORKER_THREADS]
	public int $jobs_get_timeout; // Как часто запрашивать новые данные в сервера (в секундах) [JOBS_GET_TIMEOUT]
	public int $loop_timeout; // Задержка в цикле обработки (в миллисекундах) [LOOP_TIMEOUT]
	public int $response_send_timeout; // Как часто отправлять данные на сервер (в секундах) [RESPONSE_SEND_TIMEOUT]
	public int $logs_write_timeout; // Как часто писать сообщения в логах (в секундах) [LOGS_WRITE_TIMEOUT]

	public readonly ?string $timezone; // Timezone [TZ]
	public readonly ?string $server_host; // Server host [SERVER_HOST]
	public readonly ?string $proxy_host; // Proxy host [PROXY_HOST]
	public readonly ?string $proxy_type; // Proxy port [PROXY_TYPE]
	public readonly ?string $worker_key_hash; // Worker hash. Get it from your server [WORKER_KEY_HASH]
	public readonly ?string $worker_version;
	public readonly ?string $protocol_version;

	private static $_instance;

	public static function getInstance(): self {
		if (self::$_instance === null) {
			self::$_instance = new self;
			self::$_instance->_get_params();
		}
		return self::$_instance;
	}

	private function getenv(string $key, ConfigEnvType $type=ConfigEnvType::DEFAULT, $default_value=null): mixed {
		$value = getenv(strtoupper($key));
		if ($value === false) {
			$value = $default_value;
		}
		switch ($type) {
			case ConfigEnvType::INT:
				$value = (int) $value;
				break;
			case ConfigEnvType::FLOAT:
				$value = (float) $value;
				break;
			case ConfigEnvType::BOOL:
				$value = ($value == 'true') ? true : false;
				break;
			case ConfigEnvType::STRING:
				$value = (string) $value;
				break;
			case ConfigEnvType::ARRAY:
				$value = (array) $value;
				break;
			case ConfigEnvType::OBJECT:
				$value = (object) $value;
				break;
			default:
				break;
		}
		return $value;
	}

	public function error_log(mixed $message): void {
		if (!is_string($message)) {
			$message = json_encode($message);
		}
		error_log(sprintf("[%s] %s", date("Y-m-d H:i:s",time()), $message));
	}

	private function _get_params(): void {
		$this->error_log('Starting config');

		$this->timezone = $this->getenv('TZ', ConfigEnvType::STRING, 'UTC');
		$this->server_host = $this->getenv('SERVER_HOST', ConfigEnvType::STRING, '');
		$this->proxy_host = $this->getenv('PROXY_HOST', ConfigEnvType::STRING, '');
		$this->proxy_type = $this->getenv('PROXY_TYPE', ConfigEnvType::STRING, '');
		$this->worker_key_hash = $this->getenv('WORKER_KEY_HASH', ConfigEnvType::STRING, '');
		$this->worker_threads = $this->getenv('WORKER_THREADS', ConfigEnvType::INT, 4);
		$this->jobs_get_timeout = $this->getenv('JOBS_GET_TIMEOUT', ConfigEnvType::INT, 30);
		$this->loop_timeout = $this->getenv('LOOP_TIMEOUT', ConfigEnvType::INT, 200000);
		$this->response_send_timeout = $this->getenv('RESPONSE_SEND_TIMEOUT', ConfigEnvType::INT, 5);
		$this->logs_write_timeout = $this->getenv('LOGS_WRITE_TIMEOUT', ConfigEnvType::INT, 10);
		$this->worker_version = $this->getenv('WORKER_VERSION', ConfigEnvType::STRING, '');
		$this->protocol_version = '1.0';
		$this->worker_id = 0;

		$this->error_log([
			'server_host' => $this->server_host,
			'proxy_host' => $this->proxy_host,
			'proxy_type' => $this->proxy_type,
			'worker_key_hash' => $this->worker_key_hash,
			'worker_threads' => $this->worker_threads,
			'jobs_get_timeout' => $this->jobs_get_timeout,
			'loop_timeout' => $this->loop_timeout,
			'response_send_timeout' => $this->response_send_timeout,
			'logs_write_timeout' => $this->logs_write_timeout,
			'worker_version' => $this->worker_version,
			'protocol_version' => $this->protocol_version,
		]);
	}

}
