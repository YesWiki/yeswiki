#!/bin/bash


# Start entrypoint and wait for server to be ready
# Then run tests phpunit
# Used on CI to run tests

set -xe

/opt/entrypoint.sh &
while ! echo exit | curl --silent --fail localhost > /dev/null; do sleep 1; done # Wait for server initialized
while ! echo exit | nc yeswiki-db 3306; do sleep 1; done # Wait for server initialized

./tests/tests.sh