#!/bin/bash

set -euo pipefail

[ -f node_modules/.package-lock.json ] || npm ci
git fetch origin "${GITHUB_BASE_REF:-main}"

CHANGED_FE_FILE=$(mktemp)
export CHANGED_FE_FILE
git diff --name-only --diff-filter=d FETCH_HEAD HEAD -- 'resources/js/' > "$CHANGED_FE_FILE"

cp .env.example .env

STATUS=0
bash scripts/ci-parallel.sh \
    "Typecheck::npm run types" \
    "Unused code::npm run knip" \
    "npm audit::npm audit --omit=dev --audit-level=low" \
    "Commit messages::bash scripts/ci-commit-messages.sh" \
    'Codestyle::if [ -s "$CHANGED_FE_FILE" ]; then xargs npx prettier --write < "$CHANGED_FE_FILE"; fi' \
    "Formatting::npx prettier --check resources/js .storybook vitest.setup.ts vite.config.ts scripts eslint.config.js" \
    "Build::npx vite build --sourcemap false" \
    || STATUS=$?

if [ "${CI_AUTOFIX:-1}" = "1" ] && ! git diff --quiet; then
    git commit -am "PROJ-0 style: format the frontend files"
    git push
    echo "The formatter changed files. The fix is committed and pushed; this run stops and the next one checks the result."
    exit 1
fi

exit "$STATUS"
