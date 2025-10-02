#!/bin/bash

set -xe

/opt/entrypoint.sh &
while ! echo exit | curl --silent --fail localhost > /dev/null; do sleep 1; done # Wait for server initialized
while ! echo exit | nc yeswiki-db 3306; do sleep 1; done # Wait for server initialized

./tests/tests.sh