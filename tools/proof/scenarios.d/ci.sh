# Scenarios for ci/checks.sh and ci/prepush.sh. Sourced by ../proof.sh with its helpers in scope (check, edit, ROOT, KIT, SHELL_BIN).
# Own scratch repositories under "$ROOT"; ids start with CX.

CX_BARE="$ROOT/cx-origin.git"
CX_WORK="$ROOT/cx-work"
CX_OTHER="$ROOT/cx-other"
CX_STUBS="$ROOT/cx-stubs"
if [ -f "$KIT/ci/checks.sh" ] && [ -f "$KIT/ci/prepush.sh" ]; then
    git init -q --bare -b main "$CX_BARE"
    git init -q -b main "$CX_WORK"
    mkdir -p "$CX_WORK/scripts" "$CX_STUBS"
    (
        cd "$CX_WORK" || exit 1
        git remote add origin "file://$CX_BARE"
        cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-parallel.sh" scripts/
        cp "$KIT/ci/checks.sh" scripts/checks.sh
        cp "$KIT/ci/prepush.sh" scripts/prepush.sh
        edit scripts/kit-config.sh 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/'
        edit scripts/checks.sh 's/::<[^"]*>"/::true"/'
        printf 'echo "stub ratchets"\n' > scripts/ci-ratchets.sh
        printf 'echo "stub dashboard $*"\n' > scripts/debt-dashboard.sh
        printf 'echo "stub commit messages"\n' > scripts/ci-commit-messages.sh
        printf 'threshold: 50\n' > scripts/coverage-floor.txt
        printf '<?xml version="1.0"?><coverage><project><metrics statements="10" coveredstatements="6"/></project></coverage>\n' > "$ROOT/cx-clover.xml"
        git add -A
        git commit -q -m "PROJ-1 chore: base"
        git push -q origin main
        git clone -q "file://$CX_BARE" "$CX_OTHER"
    ) > /dev/null 2>&1
    # A gitleaks that only prints the range it was given and fails, so the log shows it.
    printf '#!/bin/sh\necho "gitleaks $*"\nexit 1\n' > "$CX_STUBS/gitleaks"
    chmod +x "$CX_STUBS/gitleaks"

    cx() { (cd "$CX_WORK" && "$@"); }

    check CX01 "checks.sh --list-parts prints the parts and nothing else" 0 '^gate$' cx "$SHELL_BIN" scripts/checks.sh --list-parts
    check CX02 "checks.sh --list-parts with another flag is a usage error (64)" fail 'Usage: bash scripts/checks.sh' cx "$SHELL_BIN" scripts/checks.sh --list-parts --main
    check CX03 "checks.sh --part repo --main runs no commit-message check and no secret scan (--main after --part is honoured)" 0 '^0$' cx "$SHELL_BIN" -c "env CI=true bash scripts/checks.sh --part repo --main | grep -c -E 'Commit messages|Secret scan'; exit 0"
    check CX03b "checks.sh --part static --main with CI set and no base branch exits 0" 0 '=== Format \(' cx env CI=true "$SHELL_BIN" scripts/checks.sh --part static --main
    check CX04 "checks.sh --scheduled --part x is a usage error, the part is not ignored" fail 'Usage: bash scripts/checks.sh' cx "$SHELL_BIN" scripts/checks.sh --scheduled --part static
    check CX04b "checks.sh --main --scheduled is a usage error" fail 'Usage: bash scripts/checks.sh' cx "$SHELL_BIN" scripts/checks.sh --main --scheduled
    check CX04c "checks.sh --part twice is a usage error" fail 'Usage: bash scripts/checks.sh' cx "$SHELL_BIN" scripts/checks.sh --part gate --part static
    check CX05 "checks.sh --part with no name says the parts (64)" fail 'needs a name: gate static guards repo' cx "$SHELL_BIN" scripts/checks.sh --part
    check CX05b "checks.sh --part with an unknown name says the parts" fail 'no part "nope"' cx "$SHELL_BIN" scripts/checks.sh --part nope
    check CX06 "checks.sh --coverage reads the report and the floor" 0 'Coverage: 60% of the lines' cx "$SHELL_BIN" scripts/checks.sh --coverage "$ROOT/cx-clover.xml"
    check CX06b "checks.sh --coverage below the floor fails" fail 'below the floor of 70%' cx "$SHELL_BIN" -c "printf 'threshold: 70\n' > scripts/coverage-floor.txt && bash scripts/checks.sh --coverage '$ROOT/cx-clover.xml'; status=\$?; git checkout -q scripts/coverage-floor.txt; exit \$status"

    # A name in PARTS that is no task: the part would run with a task missing and nothing would say so.
    check CX07 "checks.sh: a part naming a task that does not exist stops the run" fail 'names "Typo", which is no task of this file' cx "$SHELL_BIN" -c "cp scripts/checks.sh scripts/checks.typo.sh && sed -i.bak 's/\"guards::Guard tests\"/\"guards::Guard tests|Typo\"/' scripts/checks.typo.sh && bash scripts/checks.typo.sh --part guards; status=\$?; rm -f scripts/checks.typo.sh scripts/checks.typo.sh.bak; exit \$status"
    check CX07b "checks.sh: the same file with the real names passes that part" 0 'guards' cx "$SHELL_BIN" -c "bash scripts/checks.sh --part guards 2>&1 | grep -c -i -E 'guard|true' > /dev/null; bash scripts/checks.sh --part guards > /dev/null && echo guards"

    # The parts that never read the base do not fetch it: a remote that is gone fails the parts that need it only.
    check CX08 "checks.sh --part static does not fetch the base (the remote is gone, the part passes)" 0 '.' cx env CHECK_BASE=main "$SHELL_BIN" -c "git remote set-url origin file:///nonexistent && bash scripts/checks.sh --part static; status=\$?; git remote set-url origin 'file://$CX_BARE'; exit \$status"
    check CX08b "checks.sh --part guards does not fetch the base either" 0 '.' cx env CHECK_BASE=main "$SHELL_BIN" -c "git remote set-url origin file:///nonexistent && bash scripts/checks.sh --part guards; status=\$?; git remote set-url origin 'file://$CX_BARE'; exit \$status"
    check CX08c "checks.sh --part repo (commit messages, secret scan) still fetches and fails when it cannot" fail 'cannot fetch origin/main' cx env CHECK_BASE=main "$SHELL_BIN" -c "git remote set-url origin file:///nonexistent && bash scripts/checks.sh --part repo; status=\$?; git remote set-url origin 'file://$CX_BARE'; exit \$status"
    check CX08d "checks.sh --part gate still fetches and fails when it cannot" fail 'cannot fetch origin/main' cx env CHECK_BASE=main "$SHELL_BIN" -c "git remote set-url origin file:///nonexistent && bash scripts/checks.sh --part gate; status=\$?; git remote set-url origin 'file://$CX_BARE'; exit \$status"

    # The secret scan reads the same commit as the ratchets: CHECK_HEAD when set, HEAD otherwise.
    check CX09 "checks.sh: the secret scan range ends at CHECK_HEAD when it is set" fail 'gitleaks git --redact --no-banner --log-opts=origin/main\.\.abc1234' cx env CHECK_BASE=main CHECK_HEAD=abc1234 PATH="$CX_STUBS:$PATH" "$SHELL_BIN" scripts/checks.sh --part repo
    check CX09b "checks.sh: without CHECK_HEAD the secret scan range ends at HEAD" fail 'log-opts=origin/main\.\.HEAD' cx env CHECK_BASE=main PATH="$CX_STUBS:$PATH" "$SHELL_BIN" scripts/checks.sh --part repo

    # prepush.sh: another clone pushed, the local origin/main is behind: one fetch first, no "cannot lock ref" from four parts.
    (
        cd "$CX_OTHER" && echo one > one.txt && git add -A && git commit -q -m "PROJ-2 feat: one" && git push -q origin main
    ) > /dev/null 2>&1
    cx_prepush_run() {
        (cd "$CX_WORK" && env -u CHECK_BASE "$SHELL_BIN" scripts/prepush.sh 2>&1)
        local status=$?
        return $status
    }
    check CX10 "prepush.sh: a stale origin/main is fetched once and every part passes" 0 '^PASS +[0-9]+s +Format' cx_prepush_run
    check CX10b "prepush.sh: no part reports a lock error on the remote-tracking ref" 0 '^0$' "$SHELL_BIN" -c "cd '$CX_WORK' && git update-ref refs/remotes/origin/main HEAD && env -u CHECK_BASE bash scripts/prepush.sh 2>&1 | grep -c 'cannot lock ref'; exit 0"
    (
        cd "$CX_OTHER" && echo two > two.txt && git add -A && git commit -q -m "PROJ-3 feat: two" && git push -q origin main
    ) > /dev/null 2>&1
    check CX10c "prepush.sh: after another push the origin/main it checked against is the new tip" 0 '^same$' "$SHELL_BIN" -c "cd '$CX_WORK' && env -u CHECK_BASE bash scripts/prepush.sh > /dev/null 2>&1; [ \"\$(git rev-parse origin/main)\" = \"\$(git --git-dir='$CX_BARE' rev-parse main)\" ] && echo same"
    check CX11 "prepush.sh with an argument that is not --main is a usage error (64)" fail 'Usage: bash scripts/prepush.sh' cx "$SHELL_BIN" scripts/prepush.sh --scheduled
    check CX12 "prepush.sh: a remote that cannot be fetched stops it before any part runs" fail 'Prepush: cannot fetch origin/main' cx env -u CHECK_BASE "$SHELL_BIN" -c "git remote set-url origin file:///nonexistent && bash scripts/prepush.sh; status=\$?; git remote set-url origin 'file://$CX_BARE'; exit \$status"
    check CX13 "prepush.sh --main needs no fetch (the remote is gone, the parts pass)" 0 '.' cx env -u CHECK_BASE CI= "$SHELL_BIN" -c "git remote set-url origin file:///nonexistent && bash scripts/prepush.sh --main; status=\$?; git remote set-url origin 'file://$CX_BARE'; exit \$status"
fi

# The skeletons: what a regression would silently bring back (text checks; the YAML itself is read by the contract test).
if [ -f "$KIT/ci/github-actions.yml" ]; then
    check CX20 "github-actions.yml: merge-first, no CHECK_HEAD and no checkout ref outside a comment" 0 '^0$' "$SHELL_BIN" -c "grep -v -E '^[[:space:]]*#' '$KIT/ci/github-actions.yml' | grep -c -E 'CHECK_HEAD|^[[:space:]]+ref:'; exit 0"
    check CX21 "github-actions.yml: a concurrency group that cancels pull requests only, and the aggregate required check" 0 '^2$' "$SHELL_BIN" -c "grep -c -E \"^  cancel-in-progress: .*== 'pull_request'|^  checks-result:\" '$KIT/ci/github-actions.yml'"
    check CX22 "github-actions.yml: every database service has its password variable and a health check (3 services, comments left out)" 0 '^3 3$' "$SHELL_BIN" -c "grep -v -E '^[[:space:]]*#' '$KIT/ci/github-actions.yml' > '$ROOT/cx-skeleton-code.yml'; echo \"\$(grep -c 'POSTGRES_PASSWORD: ci' '$ROOT/cx-skeleton-code.yml') \$(grep -c -e '--health-cmd' '$ROOT/cx-skeleton-code.yml')\""
    check CX23 "github-actions.yml: every checkout has the full history (6 checkouts, 6 fetch-depth lines)" 0 '^6 6$' "$SHELL_BIN" -c "echo \"\$(grep -c -E '^      - uses: actions/checkout@' '$KIT/ci/github-actions.yml') \$(grep -c -E '^          fetch-depth: 0' '$KIT/ci/github-actions.yml')\""
    check CX24 "github-actions.yml: the file is not a copy for the existing workflow: it names itself kit-checks.yml" 0 '[Cc]opy this file to \.github/workflows/kit-checks\.yml' "$SHELL_BIN" -c "tr '\n' ' ' < '$KIT/ci/github-actions.yml' | sed -E 's/#//g; s/  +/ /g' | grep -o -i 'copy this file to .github/workflows/kit-checks.yml'"
fi

# The skeleton with its placeholders filled is the example that the contract test reads (CiHostExamples::githubWorkflow()): a line
# edited in one and not in the other leaves the test green against a stale copy. Comments and blank lines are left out of both.
cx_fill_github() {
    local zero=0000000000000000000000000000000000000000000000000000000000000000
    sed -E \
        -e "s/<main branch>/main/; s/<other deploying branch>/develop/" \
        -e "s/<cron expression[^>]*>/17 3 * * */" \
        -e "s/<maximum minutes>/20/" \
        -e "s#image: <image name>@sha256:<digest>.*#image: php:8.4@sha256:$zero#" \
        -e "s/<40-character commit sha of the release the project pins> # <release, for example v[0-9.]+>/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1/" \
        -e "s/<install dependencies from the lock file>/composer install/" \
        -e "s/GITLEAKS_VERSION: <.*/GITLEAKS_VERSION: 8.30.1/" \
        -e "s/GITLEAKS_SHA256: <.*/GITLEAKS_SHA256: $zero/" \
        -e "s/GH_VERSION: <.*/GH_VERSION: 2.106.0/" \
        -e "s/GH_SHA256: <.*/GH_SHA256: $zero/" \
        -e "s#<the image of DATABASE_IMAGE in scripts/kit-config.sh>#postgres:17#" \
        -e "s/<backend test command, writing a clover coverage report>/composer test:unit/" \
        -e "s#<path of the clover report>#coverage/clover.xml#" \
        -e "s/<frontend test command.*/npm test/" \
        -e "s/<boot the stack with seeded data.*/php artisan serve --env=ci \&/" \
        -e "s/<smoke suite command.*/npx playwright test/" \
        "$KIT/ci/github-actions.yml" | grep -v -E '^[[:space:]]*#' | grep -v -E '^[[:space:]]*$' | sed -E 's/ # (the part that holds|holds the coverage).*$//'
}
cx_github_example() {
    printf '%s\n' '<?php' 'require $argv[1];' 'echo Tests\Support\Architecture\CiHostExamples::githubWorkflow();' > "$ROOT/cx-example.php"
    php "$ROOT/cx-example.php" "$KIT/reference-project/tests/Support/Architecture/CiHostExamples.php" | grep -v -E '^[[:space:]]*$'
}
cx_skeleton_vs_example() {
    cx_fill_github > "$ROOT/cx-skeleton-filled.yml"
    cx_github_example > "$ROOT/cx-example.yml"
    if grep -q '<' "$ROOT/cx-skeleton-filled.yml"; then
        echo "a placeholder is left after the fill:"
        grep -n '<' "$ROOT/cx-skeleton-filled.yml"
        return 1
    fi
    diff "$ROOT/cx-skeleton-filled.yml" "$ROOT/cx-example.yml" && echo same
}
if [ -f "$KIT/ci/github-actions.yml" ] && [ -f "$KIT/reference-project/tests/Support/Architecture/CiHostExamples.php" ]; then
    check_php CX27 "github-actions.yml with its placeholders filled equals the example of the contract test, line for line" 0 '^same$' cx_skeleton_vs_example
fi
if [ -f "$KIT/ci/github-actions.yml" ]; then
    check CX28 "github-actions.yml: the three database engines are named with their variable and health tool, and a private image with its credentials" 0 '^yes$' "$SHELL_BIN" -c "for word in POSTGRES_PASSWORD MYSQL_ROOT_PASSWORD MARIADB_ROOT_PASSWORD pg_isready mysqladmin healthcheck.sh 'packages: read' 'credentials:'; do grep -q -e \"\$word\" '$KIT/ci/github-actions.yml' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
    check CX29 "github-actions.yml and proof.yml: no pin of a node20 action (checkout v4, setup-node v4, setup-php 2.32) outside a comment" 0 '^0$' "$SHELL_BIN" -c "cat '$KIT/ci/github-actions.yml' '$KIT/.github/workflows/proof.yml' | grep -v -E '^[[:space:]]*#' | grep -c -E '11bd71901bbe5b1630ceea73d27597364c9af683|49933ea5288caeca8642d1e84afbd3f7d6820020|9e72090525849c5e82e596468b86eb55e9cc5401|# v4\.[0-9.]+'; exit 0"
    check CX30 "github-actions.yml: the nightly job sets GH_TOKEN and GH_REPO and installs gh with a checked download" 0 '^yes$' "$SHELL_BIN" -c "for word in '^      GH_TOKEN: ' '^      GH_REPO: ' 'GH_SHA256}  gh.tgz'; do grep -q -e \"\$word\" '$KIT/ci/github-actions.yml' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
fi
if [ -f "$KIT/ci/gitlab-ci.yml" ]; then
    check CX25 "gitlab-ci.yml: a branch rule of the workflow starts a pipeline for the push source only" 0 '^0$' "$SHELL_BIN" -c "grep -E 'if: \\\$CI_COMMIT_BRANCH' '$KIT/ci/gitlab-ci.yml' | grep -v -c 'CI_PIPELINE_SOURCE == \"push\"'; exit 0"
fi
if [ -f "$KIT/ci/CODEOWNERS.example" ]; then
    check CX26 "CODEOWNERS.example: the scripts, the pipeline files, the guard folder and the file itself have a rule with an owner" 0 '^6$' "$SHELL_BIN" -c "grep -c -E '^(/scripts/|/\\.github/workflows/|/\\.gitlab-ci\\.yml|/tests/Support/Architecture/|/\\.github/CODEOWNERS|/CODEOWNERS) <owner>\$' '$KIT/ci/CODEOWNERS.example'"
fi
