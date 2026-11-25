#!/bin/bash

set -euo pipefail

if [ -z "${GITHUB_BASE_REF:-}" ]; then
    echo "Not a pull request pipeline; skipping the commit message check."
    exit 0
fi

git fetch -q origin "+refs/heads/${GITHUB_BASE_REF}:refs/remotes/origin/${GITHUB_BASE_REF}"

TICKET='PROJ-[0-9]+'
TYPE='(feat|fix|refactor|test|docs|chore|style|ci|perf|build|revert)'
INVALID=$(git log --no-merges --format='%h %s' "origin/${GITHUB_BASE_REF}..HEAD" \
    | grep -vE "^[0-9a-f]+ (${TICKET} ${TYPE}(\([^)]+\))?!?: |${TYPE}\(${TICKET}\)!?: |Revert \"|Merge pull request |Merge branch )" || true)

if [ -n "$INVALID" ]; then
    echo "Every commit starts with its ticket and a type (feat, fix, refactor, test, docs, chore, style, ci, perf, build or revert), for example \"PROJ-123 feat: ...\" or \"fix(PROJ-0): ...\":"
    echo "$INVALID"
    exit 1
fi

echo "Every commit starts with a ticket and a type."
