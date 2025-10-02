#!/bin/bash

set -xe

/opt/entrypoint.sh &
./tests/tests.sh