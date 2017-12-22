#!/usr/bin/env bash
set -o allexport
source ./.env
set +o allexport

# Create mysql volume target dir if not exists already, and set it as current user (needed for container mapping)
mkdir -p ${MYSQL_HOST_VOLUME_PATH}
sudo chown -R ${HOST_USER}:${HOST_USER} ${MYSQL_HOST_VOLUME_PATH}
sudo chmod -R 775 ${MYSQL_HOST_VOLUME_PATH}

# Create target dir if not exists already, and set it as current user (needed for container mapping)
mkdir -p ${SYMFONY_HOST_RELATIVE_APP_PATH}
sudo chown -R ${HOST_USER}:${HOST_USER} ${SYMFONY_HOST_RELATIVE_APP_PATH}
sudo chmod -R 775 ${SYMFONY_HOST_RELATIVE_APP_PATH}

docker-compose up -d --build