# Monitoring Worker

## Environment

```ini
TZ=UTC
SERVER_HOST=<YOUR_SERVER_URL> # Server host https://monitoring.example.com/
WORKER_KEY_HASH=<YOUR_WORKER_KEY_HASH> # Worker hash. Get it from your server

# WORKER_THREADS=10 # Worker threads count
# JOBS_GET_TIMEOUT=10 # Как часто запрашивать новые данные в сервера (в секундах)
# PROXY_HOST= # Proxy host
# PROXY_TYPE= # Proxy type
# LOOP_TIMEOUT=200000 # Задержка в цикле обработки (в миллисекундах)
# RESPONSE_SEND_TIMEOUT=10 # Как часто отправлять данные на сервер (в секундах)
# LOGS_WRITE_TIMEOUT=10 # Как часто писать сообщения в логах (в секундах)

```

## Docker compose

```yml
---
services:
  worker:
    image: "akeb/monitoring-worker:dev"
    restart: always
    volumes:
      - ./logs/php/:/var/log/php/:rw
    environment:
      - TZ=UTC
      - WORKER_KEY_HASH=${WORKER_KEY_HASH}
      - SERVER_HOST=${SERVER_HOST}

```
