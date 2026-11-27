# proof-needs: main
# Scenarios NX*: findings of a second run of the method on a real application: the lint-config-off expression reads the severity
# position only, the self-test counts a case in an excluded folder with the row's own pathspecs, and a base written as
# origin/<branch> is the base. Sourced by ../proof.sh with its helpers in scope (check, check_php, branch, commit, edit, ROOT, KIT,
# SHELL_BIN). Own scratch folders under "$ROOT/nx".

NX="$ROOT/nx"
mkdir -p "$NX"

# nx_selftest <awk program on ci-ratchets.sh | -> [extra cases file]: the self-test of a changed copy of the script
nx_selftest() {
    command rm -rf "$NX/run"
    mkdir -p "$NX/run"
    cp "$KIT/tools/kit-config.sh" "$NX/run/kit-config.sh"
    if [ "$1" = "-" ]; then cp "$KIT/tools/ci-ratchets.sh" "$NX/run/ci-ratchets.sh"; else awk "$1" "$KIT/tools/ci-ratchets.sh" > "$NX/run/ci-ratchets.sh"; fi
    (cd "$NX/run" && "$SHELL_BIN" ci-ratchets.sh --self-test ${2:+"$2"} 2>&1)
}

# ---------------------------------------------------------------- lint-config-off reads the severity position only
NX_OLD_ROW='lint-config-off                        max  grep   @LINT_CONFIG  ["'"'"'](off|warn)["'"'"']|["'"'"'][^"'"'"']+["'"'"'][[:space:]]*:[[:space:]]*(\[[[:space:]]*)?[01]([^0-9A-Za-z_.]|$)'
export NX_OLD_ROW
printf "lint-config-off 0     'no-console': ['error', { allow: ['warn'] }],\nlint-config-off 1     'no-console': ['warn'],\n" > "$NX/allow-case.txt"
check NX01 "self-test: the shipped lint-config-off row counts a rule at 'warn' and not the 'warn' of an allow option" 0 'Self-test: [0-9]+ cases run, 0 wrong' nx_selftest - "$NX/allow-case.txt"
check NX02 "self-test: the old lint-config-off expression counted the 'warn' of an allow option and fails that case" fail "lint-config-off counts 1, not 0: .*allow: \['warn', 'error'\]" nx_selftest '/^lint-config-off +max/ { print ENVIRON["NX_OLD_ROW"]; next } { print }'

# ---------------------------------------------------------------- the self-test applies a row's pathspecs
printf 'todo@/tests/Guards/Fixture.php 0 // TODO written in a guard fixture\n' > "$NX/excluded-case.txt"
check NX03 "self-test: a case in a folder that the row's :(exclude) names counts 0 with the row's own pathspecs" 0 'Self-test: [0-9]+ cases run, 0 wrong' nx_selftest '/^todo +max/ { sub(/:\(exclude\)\*\.md/, ":(exclude)*.md,:(exclude)tests/Guards") } { print }' "$NX/excluded-case.txt"
check NX04 "self-test: the same case against a row that does not exclude the folder counts 1 and fails" fail 'todo counts 1, not 0: // TODO written in a guard fixture' nx_selftest - "$NX/excluded-case.txt"
check NX05 "self-test: a counter that ignores the exclude pathspecs of a row is caught by the built-in case in an excluded file" fail 'counts a spelling in .*, which its pathspec :\(exclude\)\*\.md should exclude' nx_selftest '/grep -a -h -o -E -e "\$spec"/ { sub(/ \$\{filters\[@\]\+"\$\{filters\[@\]\}"\}/, "") } { print }'

# ---------------------------------------------------------------- CHECK_BASE=origin/main is the base main
cd "$ROOT/work" || exit 1
branch nx-base
echo nx > docs/nx.md
commit "PROJ-12 docs: a base written with its remote"
check_php NX10 "ratchets: CHECK_BASE=origin/main is read as the base main" 0 '^Ratchets against origin/main' env CHECK_BASE=origin/main "$SHELL_BIN" scripts/ci-ratchets.sh
check NX11 "commit check: CHECK_BASE=origin/main is read as the base main" 0 '1 commits between origin/main' env CHECK_BASE=origin/main "$SHELL_BIN" scripts/ci-commit-messages.sh
check NX12 "commit check: a base that starts with the remote and a dash is still refused" fail 'starts with a dash' env CHECK_BASE=origin/-x "$SHELL_BIN" scripts/ci-commit-messages.sh
git checkout -q -f main

NX_MUT_BARE="$NX/mut-origin.git"
NX_MUT="$NX/mut"
git init -q --bare -b main "$NX_MUT_BARE"
git init -q -b main "$NX_MUT"
(
    cd "$NX_MUT" || exit 1
    git remote add origin "file://$NX_MUT_BARE"
    mkdir -p scripts app vendor/bin
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/mutation.sh" scripts/
    edit scripts/mutation.sh 's/^MUTATION_TOOL=.*$/MUTATION_TOOL="pest"/; s/^MUTATION_SOURCE_FOLDERS=.*$/MUTATION_SOURCE_FOLDERS="app"/'
    printf 'threshold: 50\n' > scripts/mutation-floor.txt
    printf '#!/bin/sh\necho "pest $*"\n' > vendor/bin/pest
    chmod +x vendor/bin/pest
    printf '<?php\n' > app/A.php
    git add -A
    git commit -q -m "base"
    git push -q origin main
    git checkout -q -b feature
    printf '<?php\n' > app/F.php
    git add -A
    git commit -q -m "feature"
    git checkout -q -b tests-only main
    mkdir -p tests
    printf '<?php\n' > tests/AThingTest.php
    git add -A
    git commit -q -m "tests only"
) > /dev/null 2>&1
nxm() { (cd "$NX_MUT" && "$@"); }
check NX13 "mutation.sh --changed: CHECK_BASE=origin/main names the base main" 0 '^pest --mutate --parallel --processes=4 --path=app/F\.php --min=50$' nxm "$SHELL_BIN" -c "git checkout -q feature && env CHECK_BASE=origin/main bash scripts/mutation.sh --changed"
check NX14 "mutation.sh --changed on a branch that only adds a test mutates nothing and says so (a sampled run goes by --filter, tools/README.md)" 0 'this pull request changes no source file; nothing to mutate' nxm "$SHELL_BIN" -c "git checkout -q tests-only && env CHECK_BASE=main bash scripts/mutation.sh --changed"

NX_CHK_BARE="$NX/chk-origin.git"
NX_CHK="$NX/chk"
NX_STUBS="$NX/stubs"
git init -q --bare -b main "$NX_CHK_BARE"
git init -q -b main "$NX_CHK"
mkdir -p "$NX_CHK/scripts" "$NX_STUBS"
(
    cd "$NX_CHK" || exit 1
    git remote add origin "file://$NX_CHK_BARE"
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-parallel.sh" scripts/
    cp "$KIT/ci/checks.sh" scripts/checks.sh
    cp "$KIT/ci/prepush.sh" scripts/prepush.sh
    edit scripts/kit-config.sh 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/'
    edit scripts/checks.sh 's/::<[^"]*>"/::true"/'
    printf 'echo "stub ratchets"\n' > scripts/ci-ratchets.sh
    printf 'echo "stub dashboard $*"\n' > scripts/debt-dashboard.sh
    printf 'echo "stub commit messages"\n' > scripts/ci-commit-messages.sh
    printf 'threshold: 50\n' > scripts/coverage-floor.txt
    git add -A
    git commit -q -m "PROJ-1 chore: base"
    git push -q origin main
) > /dev/null 2>&1
printf '#!/bin/sh\necho "gitleaks $*"\nexit 1\n' > "$NX_STUBS/gitleaks"
chmod +x "$NX_STUBS/gitleaks"
nxc() { (cd "$NX_CHK" && "$@"); }
check NX15 "checks.sh: CHECK_BASE=origin/main reaches the secret scan as the range origin/main..HEAD" fail 'log-opts=origin/main\.\.HEAD' nxc env CHECK_BASE=origin/main PATH="$NX_STUBS:$PATH" "$SHELL_BIN" scripts/checks.sh --part repo
check NX16 "prepush.sh: CHECK_BASE=origin/main fetches the base main and every part passes" 0 '^PASS +[0-9]+s +Format' nxc env CHECK_BASE=origin/main "$SHELL_BIN" scripts/prepush.sh
