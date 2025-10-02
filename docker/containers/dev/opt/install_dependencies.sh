#!/bin/bash

set -e

composer install
export COREPACK_ENABLE_DOWNLOAD_PROMPT=0
yarn install