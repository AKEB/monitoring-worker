FROM php:8.3

ARG WORKER_VERSION="v0.0.0"

ENV PATH="$PATH:/usr/local/bin"
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV WORKER_VERSION=${WORKER_VERSION}

RUN apt-get clean \
    && apt-get update -y --allow-insecure-repositories
RUN apt-get install -y --allow-unauthenticated \
    curl \
    ca-certificates apt-transport-https \
    wget dnsutils \
    libcurl4 \
    libcurl4-openssl-dev \
    git
RUN docker-php-ext-install curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY ./src/ /app/

WORKDIR /app/
RUN composer install

RUN touch /app/version.php
RUN echo '<?php\ndefine("WORKER_VERSION", "'${WORKER_VERSION}'");' > /app/version.php

CMD ["php", "main.php"]
