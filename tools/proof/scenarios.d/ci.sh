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
