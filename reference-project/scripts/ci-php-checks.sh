#!/bin/bash

set -euo pipefail

bash scripts/ci-ratchets.sh
bash scripts/debt-dashboard.sh --offline --details
cp .env.ci.dist .env
composer install --no-scripts
git fetch origin "${GITHUB_BASE_REF:-main}"

CHANGED_DIR=$(mktemp -d)
export CHANGED_DIR
git diff --name-only --diff-filter=d FETCH_HEAD HEAD -- '*.php' > "$CHANGED_DIR/php"
git diff --name-only --diff-filter=d FETCH_HEAD HEAD -- 'app/*.php' 'bootstrap/app.php' 'config/*.php' 'database/*.php' > "$CHANGED_DIR/rector"

STATUS=0
bash scripts/ci-parallel.sh \
    "Lint::composer lint" \
    "Static analysis::composer analyse -- --no-progress" \
    "Composer audit::composer audit --locked --abandoned=fail" \
    "Composer validate::composer validate --strict --no-check-publish" \
    "Architecture tests::php artisan test --compact --group=arch" \
    'Codestyle::if [ -s "$CHANGED_DIR/php" ]; then xargs vendor/bin/pint --config pint.json < "$CHANGED_DIR/php"; fi && if [ -s "$CHANGED_DIR/rector" ]; then xargs -n 500 vendor/bin/rector process < "$CHANGED_DIR/rector"; fi' \
    "Formatting::vendor/bin/pint --test --parallel --config pint.json" \
    'Generated types::{ [ -f node_modules/.package-lock.json ] || npm ci --no-audit --no-fund; } && php artisan typescript:transform && { git diff --exit-code resources/js/types/generated.ts || { echo "resources/js/types/generated.ts does not match the PHP Data classes and enums. Run php artisan typescript:transform and commit the result."; false; }; }; status=$?; git checkout -- resources/js/types/generated.ts; exit "$status"' \
    || STATUS=$?

if [ "${CI_AUTOFIX:-1}" = "1" ] && ! git diff --quiet; then
    git commit -am "PROJ-0 style: format the PHP files"
    git push
    echo "The formatter changed files. The fix is committed and pushed; this run stops and the next one checks the result."
    exit 1
fi

exit "$STATUS"
