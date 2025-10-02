#!/bin/bash

set -xe

/opt/entrypoint_test.sh &
while ! echo exit | curl --silent --fail localhost; do sleep 1; done # Wait for server initialized

./tests/reset.sh

./vendor/bin/phpunit --do-not-cache-result --stderr tests $1

for foldername in `ls -d tools/*/tests`
do
    ./vendor/bin/phpunit --do-not-cache-result --stderr $foldername $1 || exit 1
done