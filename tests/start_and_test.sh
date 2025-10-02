#!/bin/bash


# Start entrypoint and wait for server to be ready
# Then run tests phpunit
# Used on CI to run tests

set -e

/opt/entrypoint.sh &
while ! echo . | curl --silent --fail localhost:8085 > /dev/null; do sleep 1; done # Wait for server initialized
while ! echo . | nc -W 1 yeswiki-db 3306; do sleep 1; done # Wait for server initialized

./tests/tests.sh