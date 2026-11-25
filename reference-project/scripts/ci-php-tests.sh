#!/bin/bash

set -euo pipefail

cp .env.ci.dist .env
composer install --no-scripts

composer testcov-ci

bash scripts/upload-coverage.sh php build/test-reports/clover.xml
