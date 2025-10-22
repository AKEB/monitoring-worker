#!/bin/bash

cd /app/

cd /app && composer install --prefer-dist --no-interaction --no-dev --no-scripts

echo "Worker started successfully.";
