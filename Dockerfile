FROM akeb/php-fpm-8.4:latest

ARG WORKER_VERSION="v0.0.0"
ENV WORKER_VERSION=${WORKER_VERSION}

COPY ./src/ /app/
WORKDIR /app/
RUN mkdir /app/logs/

COPY run_on_start.sh /run_on_start.sh

RUN composer install --prefer-dist --no-interaction --no-dev --no-scripts
RUN touch /app/version.php
RUN touch /app/version.php
RUN echo '<?php\n' > /app/version.php
RUN echo 'define("WORKER_VERSION", "'${WORKER_VERSION}'");' >> /app/version.php
RUN echo 'define("SERVER_URL", "'${SERVER_URL}'");' >> /app/version.php

CMD ["/bin/bash", "-c", "/usr/sbin/logrotate -f /etc/logrotate.conf;cron;/run_on_start.sh;php -d error_log=/var/log/php/php_errors.log -d memory_limit=128M -d allow_url_fopen=true main.php"]
