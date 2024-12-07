#!/bin/bash

# docker buildx build --tag akeb/monitoring-worker --platform linux/amd64,linux/arm/v7,linux/arm64/v8 ./



docker build \
  --build-arg WORKER_VERSION=local \
  --tag akeb/monitoring-worker:local \
  ${PWD}/
