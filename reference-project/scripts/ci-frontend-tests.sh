#!/bin/bash

set -euo pipefail

[ -f node_modules/.package-lock.json ] || npm ci

npx vitest run --coverage

# The tests decide this step. A failed upload only costs the frontend number in the coverage service, which is informational.
upload() {
    bash scripts/upload-coverage.sh frontend build/frontend-coverage/lcov.info
}

upload || echo "Frontend coverage was not uploaded to the coverage service."
