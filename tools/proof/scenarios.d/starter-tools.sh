# proof-needs: main
# Scenarios ST*: the defects a real project found in the tools (no remote, first configuration of copied scripts, the two rows the
# kit names without a fixture, mutation.sh's process count, the autoload row, defaults that must follow SOURCE_PATHS, the first
# measurement of a cap, an abandoned dependency). Sourced by ../proof.sh with its helpers in scope (check, check_php, branch,
# commit, edit, big_file, dashboard, ratchets, ROOT, KIT, SHELL_BIN). Own scratch repositories under "$ROOT/st".

ST="$ROOT/st"
mkdir -p "$ST"
ST_CWD="$PWD"

# st_copy <folder>: the checks script with its stubs and the shared config, in a repository that has no remote.
st_stub_repo() {
    local dir="$1" base="$2"
    git init -q -b "$base" "$dir"
    mkdir -p "$dir/scripts"
    (
        cd "$dir" || exit 1
        cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-parallel.sh" scripts/
        cp "$KIT/ci/checks.sh" scripts/checks.sh
        edit scripts/kit-config.sh 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/'
        edit scripts/checks.sh 's/::<[^"]*>"/::true"/'
        printf 'echo "stub ratchets $*"\n' > scripts/ci-ratchets.sh
        printf 'echo "stub dashboard $*"\n' > scripts/debt-dashboard.sh
        printf 'echo "stub commit messages"\n' > scripts/ci-commit-messages.sh
        git add -A
        git commit -q -m "PROJ-1 chore: base"
    ) > /dev/null 2>&1
}

# ---------------------------------------------------------------- finding 13: checks.sh without a remote
if [ -f "$KIT/ci/checks.sh" ]; then
    st_stub_repo "$ST/norem" main
    (cd "$ST/norem" && git checkout -q -b feature) > /dev/null 2>&1
    st_stub_repo "$ST/trunk" trunk
    st_stub_repo "$ST/cinorem" main
    check ST01 "checks.sh in a fresh repository without a remote: the local base branch stands in, the gate part runs" 0 'the local branch main stands in for origin/main' "$SHELL_BIN" -c "cd '$ST/norem' && bash scripts/checks.sh --part gate"
    check ST01b "checks.sh without a remote: the part is run against that base (the gate's own lines are printed)" 0 'stub ratchets' "$SHELL_BIN" -c "cd '$ST/norem' && bash scripts/checks.sh --part gate"
    check ST02 "checks.sh without a remote and without the base branch: 'no base' is said, and the run fails" fail 'no base, so the script guard cannot run' "$SHELL_BIN" -c "cd '$ST/trunk' && bash scripts/checks.sh --part gate"
    check ST03 "checks.sh without a remote in CI: no stand-in is made, the run fails where it fetches" fail 'cannot fetch origin/main, so the script guard cannot run' "$SHELL_BIN" -c "cd '$ST/cinorem' && CI=1 CHECK_BASE=main bash scripts/checks.sh --part gate"
fi

# ---------------------------------------------------------------- finding 13 and 14 with the real ratchet script: a first local series
st_real_repo() {
    local dir="$1"
    git init -q -b main "$dir"
    (
        cd "$dir" || exit 1
        mkdir -p scripts app bootstrap config database routes tests resources/js
        cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-ratchets.sh" scripts/
        for d in app bootstrap config database routes tests resources/js; do printf '<?php\n' > "$d/a.php"; done
        cp "$KIT/tools/debt-caps.json" scripts/debt-caps.json
        printf 'parameters:\n    level: 10\n' > phpstan.neon
        git add -A
        git commit -q -m "PROJ-1 chore: copy the scripts"
    ) > /dev/null 2>&1
}
st_real_repo "$ST/real"
(
    cd "$ST/real" || exit 1
    git checkout -q -b configure
    edit scripts/kit-config.sh 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/'
    git add -A
    git commit -q -m "PROJ-2 chore: configure the scripts"
) > /dev/null 2>&1
check ST04 "ratchets without a remote: the local main is the base; configuring scripts that main already holds fails without the tag" fail 'kit-config\.sh changed\. FAILS without \[ratchet: script\]' "$SHELL_BIN" -c "cd '$ST/real' && bash scripts/ci-ratchets.sh 2>&1"
check ST05 "ratchets: the failure names the tag for the first configuration of installed scripts" fail 'first configuration of scripts that an earlier commit on the base copied needs the tag too' "$SHELL_BIN" -c "cd '$ST/real' && bash scripts/ci-ratchets.sh 2>&1"
(
    cd "$ST/real" || exit 1
    git commit -q --allow-empty -m "PROJ-3 chore: nothing"
    edit scripts/kit-config.sh 's/^REMOTE=.*$/REMOTE="origin"/; s/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/'
    printf '# configured\n' >> scripts/kit-config.sh
    git add -A
    git commit -q -m "PROJ-3 chore: more configuration

[ratchet: script] configure the installed scripts"
) > /dev/null 2>&1
check ST06 "ratchets: with [ratchet: script] configure the installed scripts in the commit, the change passes" 0 'kit-config\.sh changed, allowed' "$SHELL_BIN" -c "cd '$ST/real' && bash scripts/ci-ratchets.sh 2>&1"

# ---------------------------------------------------------------- finding 15 and 16: the rows the kit names have fixtures
ST_SELFTEST="$ST/selftest"
mkdir -p "$ST_SELFTEST"
cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-ratchets.sh" "$ST_SELFTEST/"
grep -v '^unused-ignore@' "$KIT/tools/ci-ratchets.sh" > "$ST_SELFTEST/no-unused.sh"
grep -v '^queue-bus-fake-all [01] ' "$KIT/tools/ci-ratchets.sh" > "$ST_SELFTEST/no-queue.sh"
check ST07 "--self-test passes with a fixture for every row the kit names" 0 ' 0 wrong' "$SHELL_BIN" -c "cd '$ST_CWD' && bash '$ST_SELFTEST/ci-ratchets.sh' --self-test"
check ST07b "--self-test fails when unused-ignore has no fixture" fail 'unused-ignore is a row the kit names and has no fixture' "$SHELL_BIN" -c "cd '$ST_CWD' && bash '$ST_SELFTEST/no-unused.sh' --self-test"
check ST07c "--self-test fails when queue-bus-fake-all has no fixture" fail 'queue-bus-fake-all is a row the kit names and has no fixture' "$SHELL_BIN" -c "cd '$ST_CWD' && bash '$ST_SELFTEST/no-queue.sh' --self-test"
check ST07d "--self-test names a project's own row without cases and does not fail for it (a table row of the fixture project)" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" scripts/ci-ratchets.sh --self-test

branch st-unused
printf '<?php\n\nreturn (new Config())\n    ->addNamedFilter(NamedFilter::fromString("a/b"))\n    ->addPatternFilter(PatternFilter::fromString("/^c\\//"));\n' > composer-unused.php
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
unused-ignore                          max  grep   composer-unused.php?  (NamedFilter|PatternFilter)::fromString/'
git add -A
git commit -q -m "PROJ-9 chore: a PHP config for the unused-package tool" > /dev/null 2>&1
check_php ST08 "unused-ignore: a grep row on a PHP config counts the filter entries" 0 '^unused-ignore max 2$' "$SHELL_BIN" scripts/ci-ratchets.sh --head

# ---------------------------------------------------------------- finding 17: mutation.sh does not start one worker per core
st_mutation() {
    local dir="$ST/mut-$1" processes="$2"
    mkdir -p "$dir/scripts" "$dir/vendor/bin" "$dir/app"
    git init -q -b main "$dir" 2> /dev/null
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/mutation.sh" "$dir/scripts/"
    printf 'threshold: 70\n' > "$dir/scripts/mutation-floor.txt"
    printf '#!/bin/sh\necho "pest $*"\n' > "$dir/vendor/bin/pest"
    chmod +x "$dir/vendor/bin/pest"
    (
        cd "$dir" || exit 1
        edit scripts/mutation.sh 's/^MUTATION_TOOL=.*$/MUTATION_TOOL="pest"/; s/^MUTATION_SOURCE_FOLDERS=.*$/MUTATION_SOURCE_FOLDERS="app"/'
        [ -z "$processes" ] || edit scripts/mutation.sh "s/^MUTATION_PROCESSES=.*\$/MUTATION_PROCESSES=\"$processes\"/"
    )
}
st_mutation default ""
st_mutation eight 8
st_mutation bad max
check ST09 "mutation.sh: the default is 4 processes" 0 '^pest --mutate --everything --parallel --processes=4 --min=70$' "$SHELL_BIN" -c "cd '$ST/mut-default' && bash scripts/mutation.sh"
check ST09b "mutation.sh: MUTATION_PROCESSES raises it" 0 '--processes=8 ' "$SHELL_BIN" -c "cd '$ST/mut-eight' && bash scripts/mutation.sh"
check ST09c "mutation.sh: a value that is no whole number stops the run" fail 'MUTATION_PROCESSES is "max": a whole number from 1' "$SHELL_BIN" -c "cd '$ST/mut-bad' && bash scripts/mutation.sh"

# ---------------------------------------------------------------- finding 12: the autoload row writes nothing
AUTOLOAD_ROW=$(grep -o "composer dump-autoload -o [^\`]*grep -c 'psr-4'" "$KIT/tools/README.md" | awk 'NR == 1')
if command -v composer > /dev/null 2>&1 && [ -n "$AUTOLOAD_ROW" ]; then
    mkdir -p "$ST/autoload/app"
    printf '{"name":"proof/autoload","autoload":{"psr-4":{"App\\\\":"app/"}}}\n' > "$ST/autoload/composer.json"
    printf '<?php\nnamespace App;\nclass Wrong {}\n' > "$ST/autoload/app/Right.php"
    printf '<?php\nnamespace App;\nclass Good {}\n' > "$ST/autoload/app/Good.php"
    (cd "$ST/autoload" && composer install -q --no-interaction) > /dev/null 2>&1
    check ST10 "the autoload-conformance command counts the class the autoloader skips" 0 '^1$' "$SHELL_BIN" -c "cd '$ST/autoload' && $AUTOLOAD_ROW"
    check ST10b "the autoload-conformance command writes nothing into vendor" 0 '^unchanged$' "$SHELL_BIN" -c "cd '$ST/autoload' && before=\$(find vendor -type f -exec cksum {} + | sort | cksum) && $AUTOLOAD_ROW > /dev/null; after=\$(find vendor -type f -exec cksum {} + | sort | cksum); [ \"\$before\" = \"\$after\" ] && echo unchanged"
fi

# ---------------------------------------------------------------- finding 20 and 9: the defaults follow SOURCE_PATHS, generated files are not read
branch st-defaults
mkdir -p lang database/migrations
big_file 450 > lang/messages.php
big_file 450 > database/migrations/big.php
commit "PROJ-9 chore: long files in lang and database"
check_php ST11 "dashboard: the default SOURCE_FILES covers database and lang (the folders of SOURCE_PATHS)" fail '^size +source-files-over-limit +3 +1 +OPEN' dashboard --offline
branch st-generated
mkdir -p bootstrap/cache
printf '<?php\n\n// TODO generated\nreturn [];\n' > bootstrap/cache/packages.php
check_php ST12 "ratchets: an untracked generated file under bootstrap/cache is not counted (tracked files only)" 0 '^todo max 1$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
rm -rf bootstrap/cache

# ---------------------------------------------------------------- finding 18: --final --offline does not claim the end state
branch st-final
check ST13 "dashboard --final --offline says that skipped rows do not prove the end state" 0 'Final: [0-9]+ row\(s\) were skipped, so this run does not prove the end state' dashboard --final --offline --report

# ---------------------------------------------------------------- finding 19: an abandoned dev dependency with no replacement
branch st-abandoned
echo '{"advisories": [], "abandoned": {"vendor/pkg": null}}' > fixtures/audit.json
edit scripts/debt-caps.json 's/"dependencies-abandoned": 0/"dependencies-abandoned": 1/'
commit "PROJ-9 chore: an abandoned package"
check_php ST14 "dashboard --final: an abandoned package with its cap but not in SHRINK_ONLY is OPEN" fail '^command +dependencies-abandoned +1 +0 +OPEN' dashboard --final
edit scripts/debt-dashboard.sh 's/^SHRINK_ONLY="(.*)"$/SHRINK_ONLY="\1 cap-dependencies-abandoned"/'
check_php ST15 "dashboard --final: with cap-dependencies-abandoned in SHRINK_ONLY the row is held to its cap and says why" 0 '^command +dependencies-abandoned +1 +1 +ok \(shrink-only by a decision row' dashboard --final --report
git checkout -q -- scripts/debt-dashboard.sh

branch st-cap0
edit scripts/debt-caps.json 's/"dependencies-abandoned": 0/"dependencies-abandoned": 1/'
commit "PROJ-9 chore: the cap for an abandoned package, no tag"
check_php ST16 "ratchets: raising cap-dependencies-abandoned from 0 without the tag fails" fail '^cap-dependencies-abandoned +down +0 +1 +\+1 +FAILS' ratchets
branch st-cap0b
edit scripts/debt-caps.json 's/"dependencies-abandoned": 0/"dependencies-abandoned": 1/'
commit "PROJ-9 chore: the cap for an abandoned package

[ratchet: cap-dependencies-abandoned] first measurement 1, the decision row names the package"
check_php ST17 "ratchets: the same raise with the tag passes (the cap is in OVERRIDE_AT_ZERO)" 0 '^cap-dependencies-abandoned +down +0 +1 +\+1 +allowed' ratchets
branch st-cap0c
edit scripts/debt-caps.json 's/"open-classes": 0/"open-classes": 1/'
commit "PROJ-9 chore: another cap from 0, tagged

[ratchet: cap-open-classes] first measurement 1"
check_php ST18 "ratchets: a cap that is not in OVERRIDE_AT_ZERO still takes no override at 0" fail '^cap-open-classes +down +0 +1 +\+1 +FAILS' ratchets

cd "$ST_CWD" || exit 1
