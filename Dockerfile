FROM akeb/php:8.3

ARG WORKER_VERSION="v0.0.0"
ENV WORKER_VERSION=${WORKER_VERSION}

COPY ./src/ /app/
WORKDIR /app/

RUN composer install
RUN touch /app/version.php
RUN echo '<?php\ndefine("WORKER_VERSION", "'${WORKER_VERSION}'");' > /app/version.php

CMD ["php", "main.php"]
