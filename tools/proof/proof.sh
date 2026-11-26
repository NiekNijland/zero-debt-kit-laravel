#!/usr/bin/env bash
# Proof for the tools. Usage: bash proof.sh [bash binary to run the tools with] [label]
# Builds scratch repositories in a fresh folder, runs every scenario, prints the table.
# KIT is the kit folder (default: two folders up from this file). PROOF_OUT is where the
# scratch repositories, proof-<label>.log (full output) and proof-<label>.table go (default: a new temporary folder).
#
# PROOF_ONLY=<extended regular expression on the scenario id> runs only the scenarios whose id matches (for example PROOF_ONLY='^DX'
# or 'R0[1-3]'); the others are not run and not counted. A filter that is not a valid expression, or that matches no scenario, stops
# with exit 64 before anything is built. A scenario that a selected one reads the result of (PROOF_STEPS: a check that writes
# files or commits) still runs, silently and uncounted, so a filtered run gives the verdicts of the full run. When the selected
# scenarios live only in scenario files that do not need the fixtures of this file ("# proof-needs: main" is not in their head),
# those fixtures are not built.
# PROOF_LIST=1 prints the scenario ids (loop variables expanded) and runs nothing. PROOF_AUDIT=1 records which checks change the
# scratch tree (proof-steps-<label>.txt): the ids for PROOF_STEPS. Without any of them, every scenario runs.
# Scenario ids are unique: a duplicate stops every run with exit 1. The exit status is 0 when no scenario that ran was wrong.

set -uo pipefail

SELF_HERE=$(cd "$(dirname "$0")" && pwd)

# proof_ids: "<scenario file><TAB><id>" for every scenario written in proof.sh and scenarios.d/*.sh, in file order. The helpers that
# wrap check (check_php, message_case, tx_message) count; an id built from a loop variable ("RX36-$tool") is expanded with the
# words of the nearest "for <variable> in <words>; do" above it.
proof_ids() {
    local file
    for file in "$SELF_HERE/proof.sh" "$SELF_HERE"/scenarios.d/*.sh; do
        awk -v file="$(basename "$file")" '
            /^[[:space:]]*for [A-Za-z_][A-Za-z0-9_]* in .*; do[[:space:]]*$/ {
                line = $0; sub(/^[[:space:]]*for /, "", line)
                v = line; sub(/ .*/, "", v)
                words = line; sub(/^[A-Za-z_][A-Za-z0-9_]* in /, "", words); sub(/; do[[:space:]]*$/, "", words)
                LIST[v] = words
                next
            }
            /^[[:space:]]*(check_php|check|message_case|tx_message)[[:space:]]+"?[A-Za-z0-9_$-]+/ {
                line = $0
                sub(/^[[:space:]]*(check_php|check|message_case|tx_message)[[:space:]]+"?/, "", line)
                match(line, /^[A-Za-z0-9_$-]+/)
                id = substr(line, 1, RLENGTH)
                if (id ~ /^[$][0-9@*]*$/) next
                dollar = index(id, "$")
                if (dollar == 0) { print file "\t" id; next }
                name = substr(id, dollar + 1)
                match(name, /^[A-Za-z_][A-Za-z0-9_]*/)
                name = substr(name, 1, RLENGTH)
                count = split(LIST[name], w, " ")
                if (count == 0) { print file "\t" id; next }
                for (i = 1; i <= count; i++) {
                    expanded = id
                    sub("[$]" name, w[i], expanded)
                    print file "\t" expanded
                }
            }' "$file"
    done
}

DUPLICATES=$(proof_ids | cut -f2 | sort | uniq -d | tr '\n' ' ')
if [ -n "$DUPLICATES" ]; then
    echo "Proof: scenario ids must be unique; these are used more than once: $DUPLICATES" >&2
    exit 1
fi

if [ "${PROOF_LIST:-}" = 1 ]; then
    proof_ids | cut -f2 | sort
    exit 0
fi

# The scenario files whose head says "# proof-needs: main": they use the fixtures and helpers that proof.sh builds below.
NEEDS_MAIN=" $(grep -l '^# proof-needs: main' "$SELF_HERE"/scenarios.d/*.sh | while read -r f; do basename "$f"; done | tr '\n' ' ')"
RUN_MAIN=1
SELECTED_FILES=""
if [ -n "${PROOF_ONLY:-}" ]; then
    printf '' | grep -E -e "$PROOF_ONLY" > /dev/null 2>&1
    if [ "$?" -gt 1 ]; then
        echo "Proof: PROOF_ONLY is not a valid extended regular expression: $PROOF_ONLY" >&2
        exit 64
    fi
    SELECTED_IDS=$(proof_ids | cut -f2 | grep -E -e "$PROOF_ONLY")
    if [ -z "$SELECTED_IDS" ]; then
        echo "Proof: PROOF_ONLY=$PROOF_ONLY matches no scenario id (PROOF_LIST=1 prints them)." >&2
        exit 64
    fi
    SELECTED_FILES=" $(proof_ids | awk -F'\t' 'NR == FNR { selected[$0] = 1; next } $2 in selected && !seen[$1]++ { print $1 }' <(printf '%s\n' "$SELECTED_IDS") - | tr '\n' ' ')"
    case "$SELECTED_FILES" in *" proof.sh "*) ;; *)
        RUN_MAIN=0
        for _f in $SELECTED_FILES; do
            case "$NEEDS_MAIN" in *" $_f "*) RUN_MAIN=1 ;; esac
        done
        ;;
    esac
fi

# The checks that write files or commit, so that a later check reads what they leave (found with PROOF_AUDIT=1). Under PROOF_ONLY
# they run even when the filter does not select them.
PROOF_STEPS="H01 H02 B01 B07 MU03 MU04 MV01 MV06 CX06b CX08 CX08b CX08c CX08d CX10 CX10b CX10c CX12 CX13 DX14 DX25 RX30 RX30b RX31b TX21 TX24 TX26 TX33 TX39 TX40 VX01 VX09 VX10 VX15 VX16 VX17 VX18 VX19 VX22"

SHELL_BIN="${1:-/bin/bash}"
LABEL="${2:-bash}"
HERE=$(cd "$(dirname "$0")" && pwd)
KIT="${KIT:-$(cd "$HERE/../.." && pwd)}"
OUT="${PROOF_OUT:-$(mktemp -d)}"
ROOT=$(mktemp -d "$OUT/run-$LABEL-XXXXXX")
LOG="$OUT/proof-$LABEL.log"
TABLE="$OUT/proof-$LABEL.table"
STEPS_FILE="$OUT/proof-steps-$LABEL.txt"
: > "$LOG"
: > "$TABLE"
[ "${PROOF_AUDIT:-}" != 1 ] || : > "$STEPS_FILE"

# The tools call "bash" themselves: make that the binary under test.
SHIM="$ROOT/shim"
mkdir -p "$SHIM"
ln -s "$SHELL_BIN" "$SHIM/bash"
export PATH="$SHIM:$PATH"
export GIT_AUTHOR_NAME=proof GIT_AUTHOR_EMAIL=proof@example.test GIT_COMMITTER_NAME=proof GIT_COMMITTER_EMAIL=proof@example.test
unset CI CHECK_BASE CHECK_HEAD BITBUCKET_PR_DESTINATION_BRANCH GITHUB_BASE_REF CI_MERGE_REQUEST_TARGET_BRANCH_NAME BITBUCKET_BRANCH GITHUB_HEAD_REF CI_MERGE_REQUEST_SOURCE_BRANCH_NAME RATCHET_JSON_TOOL

PASSED=0
FAILED=0
SKIPPED=0
HAVE_PHP=0
! command -v php > /dev/null 2>&1 || HAVE_PHP=1

# check <id> <scenario> <expected exit: 0|fail> <expected text (extended regex)> <command...>
check() {
    local id="$1" scenario="$2" want_exit="$3" want_text="$4" output status got verdict counted=1 before=""
    shift 4
    if [ -n "${PROOF_ONLY:-}" ] && ! grep -q -E -e "$PROOF_ONLY" <<< "$id"; then
        case " $PROOF_STEPS " in
            *" $id "*) counted=0 ;;
            *) return 0 ;;
        esac
    fi
    [ "${PROOF_AUDIT:-}" != 1 ] || before=$(tree_fingerprint)
    output=$("$@" 2>&1)
    status=$?
    if [ "${PROOF_AUDIT:-}" = 1 ] && [ "$before" != "$(tree_fingerprint)" ]; then
        echo "$id" >> "$STEPS_FILE"
    fi
    if [ "$counted" -eq 0 ]; then
        {
            echo "================================================================"
            echo "$id  (a step of the proof: run for the checks that read what it leaves, not counted)"
            echo "command: $*"
            echo "exit: $status"
            echo "$output"
        } >> "$LOG"
        return 0
    fi
    {
        echo "================================================================"
        echo "$id  $scenario"
        echo "command: $*"
        echo "exit: $status"
        echo "$output"
    } >> "$LOG"
    got="exit $status"
    verdict=ok
    if [ "$want_exit" = "0" ] && [ "$status" -ne 0 ]; then verdict=WRONG; fi
    if [ "$want_exit" = "fail" ] && [ "$status" -eq 0 ]; then verdict=WRONG; fi
    if ! grep -q -E -e "$want_text" <<< "$output"; then
        verdict=WRONG
        got="$got, text not found"
    else
        got="$got, \"$(grep -E -e "$want_text" <<< "$output" | awk 'NR == 1' | sed -E 's/[[:space:]]+/ /g' | cut -c1-90)\""
    fi
    if [ "$verdict" = ok ]; then PASSED=$((PASSED + 1)); else FAILED=$((FAILED + 1)); fi
    printf '%s | %s | %s, "%s" | %s | %s\n' "$id" "$scenario" "$([ "$want_exit" = 0 ] && echo "exit 0" || echo "exit not 0")" "$want_text" "$got" "$verdict" >> "$TABLE"
}

# check_php: a scenario that needs php on the PATH (the default fixture counts a NEON file). Without php it is skipped, not failed.
check_php() {
    if [ -n "${PROOF_ONLY:-}" ] && ! grep -q -E -e "$PROOF_ONLY" <<< "$1"; then
        case " $PROOF_STEPS " in
            *" $1 "*) [ "$HAVE_PHP" -eq 0 ] || check "$@" ;;
        esac
        return 0
    fi
    if [ "$HAVE_PHP" -eq 1 ]; then
        check "$@"
        return
    fi
    SKIPPED=$((SKIPPED + 1))
    printf '%s | %s | %s | %s | %s\n' "$1" "$2" "-" "skipped: needs php" "skipped" >> "$TABLE"
}

# tree_fingerprint: the files of the scratch tree (not the object store, logs, index or fetch bookkeeping of a repository) with
# their size and modification time. A check that changes it is a step (PROOF_AUDIT=1).
tree_fingerprint() {
    find "$ROOT" -type f ! -path '*/.git/objects/*' ! -path '*/.git/logs/*' ! -path '*/.git/index' ! -path '*/.git/FETCH_HEAD' ! -path '*/.git/*.lock' ! -path '*/objects/*' -exec stat -f '%Fm %z %N' {} + 2> /dev/null | sort | cksum
}

big_file() {
    local lines="$1" i=0
    echo "<?php"
    while [ "$i" -lt "$lines" ]; do
        echo "\$value$i = $i;"
        i=$((i + 1))
    done
}

# ---------------------------------------------------------------- fixture: PHP backend with a React frontend, default config block
build_fixture() {
    local origin="$ROOT/origin.git" work="$ROOT/work"
    git init -q --bare -b main "$origin"
    git init -q -b main "$work"
    cd "$work" || exit 1
    git remote add origin "file://$origin"
    mkdir -p app/Actions app/Services app/Policies bootstrap config database routes tests/Unit tests/Feature resources/js/hooks resources/js/components scripts docs fixtures
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-ratchets.sh" "$KIT/tools/debt-dashboard.sh" "$KIT/tools/ci-commit-messages.sh" "$KIT/tools/ci-parallel.sh" "$KIT/tools/keyed-baseline.sh" scripts/
    fill_placeholders
    edit scripts/debt-dashboard.sh 's/^ANALYSER_SWITCHES_TARGET=.*$/ANALYSER_SWITCHES_TARGET=2/; s/^MIN_ROW_TARGETS=.*$/MIN_ROW_TARGETS="coverage-floor:80"/'
    edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
coverage-floor                         min  number coverage.yml  threshold: ([0-9]+)\
mutation-floor                         min  number scripts\/mutation-floor.txt  ^threshold: ([0-9]+)$\
layout-list                            max  grep   lists\/layout.txt  ^./'
    printf 'threshold: 60\n' > scripts/mutation-floor.txt
    mkdir -p lists
    printf 'app/Old.php\napp/Older.php\n' > lists/layout.txt
    printf 'coverage:\n  threshold: 80\n' > coverage.yml
    cat > scripts/debt-caps.json <<'JSON'
{
    "source-files-over-limit": 1,
    "frontend-files-over-limit": 0,
    "test-files-over-limit": 0,
    "open-classes": 0,
    "classes-no-test-names": 1,
    "classes-without-own-test": 1,
    "support-classes-without-own-test": 0,
    "frontend-logic-without-test": 0,
    "frontend-components-without-test": 0,
    "docs-older-than-limit": 0,
    "dependency-advisories": 0,
    "dependencies-abandoned": 0,
    "dependencies-majors-behind": 0
}
JSON
    # The registry rows read fixture files here, so the proof needs no network.
    cat > "$ROOT/rows.txt" <<'ROWS'
    "dependency-advisories::online::cat fixtures/audit.json | json 'count advisories.*'"
    "dependencies-abandoned::online::cat fixtures/audit.json | json 'count abandoned'"
    "dependencies-behind::shown::cat fixtures/outdated.json | json 'count locked'"
    "dependencies-majors-behind::always::cat fixtures/majors.json | json 'count locked'"
ROWS
    awk -v rows="$ROOT/rows.txt" '
        /^COMMAND_ROWS=\(/ { print; while ((getline line < rows) > 0) print line; skip = 1; next }
        skip && /^\)/ { skip = 0 }
        !skip' scripts/debt-dashboard.sh > scripts/debt-dashboard.tmp
    cat scripts/debt-dashboard.tmp > scripts/debt-dashboard.sh
    command rm scripts/debt-dashboard.tmp
    echo '{"advisories": [], "abandoned": []}' > fixtures/audit.json
    echo '{"locked": [{"name": "a/a"}, {"name": "b/b"}]}' > fixtures/outdated.json
    echo '{"locked": []}' > fixtures/majors.json
    printf 'parameters:\n    level: 10\n    checkMissingOverrideMethodAttribute: true\n    noEnvCallsOutsideOfConfig: true\n    paths:\n        - app\n' > phpstan.neon
    echo '{"name": "proof/fixture", "config": {"audit": {"ignore": []}}}' > composer.json
    printf 'export default [\n    { rules: { "no-console": "error" } },\n];\n' > eslint.config.js
    printf 'pipelines:\n  pull-requests:\n    "**":\n      - step:\n          script:\n            - bash scripts/ci-ratchets.sh\n' > bitbucket-pipelines.yml
    printf '<?php\n\nnamespace App\\Actions;\n\nfinal class CreateOrder\n{\n    public function handle(): int\n    {\n        return 1; // TODO remove the constant\n    }\n}\n' > app/Actions/CreateOrder.php
    printf '<?php\n\nnamespace App\\Actions;\n\nfinal class Create\n{\n}\n' > app/Actions/Create.php
    printf '<?php\n\nnamespace App\\Policies;\n\nfinal class OrderPolicy\n{\n}\n' > app/Policies/OrderPolicy.php
    big_file 450 > app/Services/Big.php
    printf '<?php\n' > bootstrap/app.php
    printf '<?php\n\nreturn [];\n' > config/app.php
    printf '<?php\n' > database/seed.php
    printf '<?php\n' > routes/web.php
    printf '<?php\n\nuse App\\Actions\\CreateOrder;\n\nit("creates an order", function (): void {\n    $double = Mockery::mock(Clock::class);\n    expect((new CreateOrder)->handle())->toBe(1);\n});\n' > tests/Unit/CreateOrderTest.php
    printf '<?php\n\nuse App\\Policies\\OrderPolicy;\n\nit("allows", function (): void {\n    expect(new OrderPolicy)->toBeObject();\n});\n' > tests/Unit/OrderPolicyTest.php
    printf '<?php\n\nit("works", function (): void {\n    expect(Big::class)->toBeString();\n});\n' > tests/Unit/BigTest.php
    printf 'export function useThing(): number {\n    return 1;\n}\n' > resources/js/hooks/use-thing.ts
    printf 'import { useThing } from "./use-thing";\n\ntest("thing", () => {\n    expect(useThing()).toBe(1);\n});\n' > resources/js/hooks/use-thing.test.ts
    printf 'export function Button() {\n    return <button />;\n}\n' > resources/js/components/button.tsx
    printf 'import { Button } from "./button";\n\ntest("button", () => {\n    expect(Button).toBeDefined();\n});\n' > resources/js/components/button.test.tsx
    echo "# Guide" > docs/guide.md
    git add -A
    git commit -q -m "PROJ-1 chore: fixture"
    git push -q origin main
}

branch() {
    cd "$ROOT/work" || exit 1
    git checkout -q -f main
    git clean -q -fd
    git checkout -q -B "$1" main
}

commit() {
    git add -A
    git commit -q -m "$1"
}

ratchets() {
    CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh "$@"
}

dashboard() {
    "$SHELL_BIN" scripts/debt-dashboard.sh "$@"
}

commits() {
    CHECK_BASE=main "$SHELL_BIN" scripts/ci-commit-messages.sh
}

edit() {
    # edit <file> <sed expression>
    sed -E "$2" "$1" > "$1.tmp" && cat "$1.tmp" > "$1"
    command rm "$1.tmp"
}

# The values a project must set before checks.sh and the commit-message check run: the kit ships them as placeholders.
fill_placeholders() {
    edit scripts/kit-config.sh 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/'
    edit scripts/ci-commit-messages.sh "s/^TICKET=.*$/TICKET='PROJ-[0-9]+'/; s/^EXAMPLE=.*$/EXAMPLE='PROJ-123 feat: add the export'/"
}

# Under PROOF_ONLY the scenarios of this file and the fixtures they share are skipped when every selected scenario lives in a
# scenario file that builds its own (RUN_MAIN=0).
if [ "$RUN_MAIN" -eq 1 ]; then

build_fixture

# ---------------------------------------------------------------- syntax
for script in kit-config.sh ci-ratchets.sh debt-dashboard.sh ci-commit-messages.sh ci-parallel.sh keyed-baseline.sh; do
    check "S-$script" "bash -n $script with $("$SHELL_BIN" -c 'echo $BASH_VERSION')" 0 '^$' "$SHELL_BIN" -n "$KIT/tools/$script"
done
[ ! -f "$KIT/ci/checks.sh" ] || check "S-checks.sh" "bash -n ci/checks.sh" 0 '^$' "$SHELL_BIN" -n "$KIT/ci/checks.sh"

# ---------------------------------------------------------------- ratchets
branch r01
echo "note" > docs/note.md
commit "PROJ-2 docs: a note"
check_php R01 "ratchet: a branch that changes no counter passes" 0 '^todo +down +1 +1 +\+0 +ok' ratchets

branch r02
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
handle-methods                         max  grep   @SOURCE_PATHS  function handle/'
printf '<?php\n\nfinal class Second\n{\n    public function handle(): void\n    {\n    }\n}\n' > app/Actions/Second.php
printf '<?php\n\nit("seconds", function (): void {\n    expect(Second::class)->toBeString();\n});\n' > tests/Unit/SecondTest.php
commit "PROJ-2 ci: a new ratchet row, and one more site of what it counts"
check_php R02 "ratchet: a new row passes, although what it counts goes up in the same PR" 0 '^handle-methods +down +1 +2 +\+1 +new row' ratchets

branch r03
printf '<?php\n// TODO another\n' > app/Services/Other.php
commit "PROJ-2 feat: one more todo"
check_php R03 "ratchet: a row that goes up fails" fail '^todo +down +1 +2 +\+1 +FAILS' ratchets

branch r04
printf '<?php\n// TODO another\n' > app/Services/Other.php
commit "PROJ-2 feat: one more todo

[ratchet: todo] the vendor fix lands next week
[ratchet: comments] the todo is a comment"
check_php R04 "ratchet: the override tag in a commit that touches the row's files passes" 0 'allowed by [0-9a-f]+: the vendor fix lands next week' ratchets

branch r05
mkdir -p tests/Feature
printf '<?php\n\nit("fakes", function (): void {\n    Event::fake();\n    expect(true)->toBeTrue();\n});\n' > tests/Feature/FakeTest.php
commit "PROJ-2 test: fake everything

[ratchet: event-fake-all] wanted"
check_php R05 "ratchet: a row at 0 takes no override" fail '^event-fake-all +down +0 +1 +\+1 +FAILS' ratchets

branch r06
edit scripts/ci-ratchets.sh '/^todo +max/d'
commit "PROJ-2 ci: drop the todo row"
check_php R06 "ratchet: a removed row that is not at 0 fails" fail '^todo +- +1 +- +- +removed, FAILS' ratchets

branch r06b
edit scripts/ci-ratchets.sh '/^todo +max/d'
commit "PROJ-2 ci: drop the todo row

[ratchet: todo] counted by the linter rule from now on"
check_php R06b "ratchet: a removed row with the tag in a commit that touches the script passes" 0 '^todo +- +1 +- +- +removed, allowed' ratchets

branch r06c
edit scripts/ci-ratchets.sh '/^event-fake-all +max/d'
commit "PROJ-2 ci: drop a row at zero"
check_php R06c "ratchet: a removed max row at 0 passes" 0 '^event-fake-all +- +0 +- +- +removed at 0' ratchets

branch r07
edit scripts/ci-ratchets.sh 's/^(todo +max +grep +[^ ]+ +).*$/\1FIXME/'
commit "PROJ-2 ci: narrow the todo row"
check_php R07 "ratchet: a redefined row fails and prints was/now" fail '^todo +down +1 +0 +-1 +redefined, FAILS' ratchets

branch r07b
edit scripts/ci-ratchets.sh 's/^(todo +max +grep +[^ ]+ +).*$/\1FIXME/'
commit "PROJ-2 ci: narrow the todo row

[ratchet: todo] TODO is banned by the linter now"
check_php R07b "ratchet: a redefined row with the tag passes" 0 '^todo +down +1 +0 +-1 +redefined, allowed' ratchets

branch r08
edit scripts/ci-ratchets.sh 's/^SOURCE_EXTENSIONS="(.*),vue"$/SOURCE_EXTENSIONS="\1"/'
commit "PROJ-2 ci: one source extension less"
check_php R08 "ratchet: the script edited outside its table fails" fail 'scripts/ci-ratchets.sh changed outside its table\. FAILS' ratchets

branch r08b
edit scripts/ci-ratchets.sh 's/^SOURCE_EXTENSIONS="(.*),vue"$/SOURCE_EXTENSIONS="\1"/'
commit "PROJ-2 ci: one source extension less

[ratchet: script] the project has no vue files"
check_php R08b "ratchet: the same edit with [ratchet: script] passes" 0 'changed outside its table, allowed' ratchets

branch r08c
mkdir -p ci
git mv scripts/ci-ratchets.sh ci/ci-ratchets.sh
cp scripts/kit-config.sh ci/kit-config.sh
commit "PROJ-2 ci: move the ratchet script"
check_php R08c "ratchet: a moved script fails (rows are not new rows)" fail 'ci/ci-ratchets.sh moved here from scripts/ci-ratchets.sh\. FAILS' env CHECK_BASE=main "$SHELL_BIN" ci/ci-ratchets.sh

branch r08d
edit scripts/ci-ratchets.sh 's/^set -euo pipefail$/set -euo pipefail\
[ -z "${SKIP:-x}" ] \&\& exit 9; exit 0/'
commit "PROJ-2 ci: quietly switch the ratchets off"
check_php R08d "ratchet: a script that was switched off passes its own run (the known limit)" 0 '^$' ratchets
mkdir -p "$ROOT/base"
git show origin/main:scripts/ci-ratchets.sh > "$ROOT/base/ci-ratchets.sh"
git show origin/main:scripts/kit-config.sh > "$ROOT/base/kit-config.sh"
check_php R08e "ratchet: the base's copy run with --guard catches that edit" fail 'scripts/ci-ratchets.sh changed outside its table\. FAILS' env CHECK_BASE=main "$SHELL_BIN" "$ROOT/base/ci-ratchets.sh" --guard scripts/ci-ratchets.sh

branch r08f
edit scripts/debt-dashboard.sh 's/^SHRINK_ONLY="(.*)"$/SHRINK_ONLY="\1 todo"/'
commit "PROJ-2 ci: todo is shrink-only now"
check_php R08f "ratchet: a guarded script (the dashboard) changed without the tag fails" fail 'scripts/debt-dashboard.sh changed\. FAILS' ratchets

branch r08g
edit scripts/kit-config.sh 's/^DEFAULT_BASE_BRANCH="main"$/DEFAULT_BASE_BRANCH="trunk"/'
commit "PROJ-2 ci: another default branch"
check_php R08g "ratchet: the shared config file (kit-config.sh) changed without the tag fails" fail 'scripts/kit-config.sh changed\. FAILS' ratchets

branch r08h
git mv scripts/debt-caps.json caps.json
edit scripts/kit-config.sh 's/^CAPS_FILE=.*$/CAPS_FILE="caps.json"/'
commit "PROJ-2 ci: move the caps file

[ratchet: script] the caps file moves to the root"
check_php R08h "ratchet: a value of kit-config.sh that redefines rows (CAPS_FILE) passes with [ratchet: script] in the commit that touches it" 0 '^cap-source-files-over-limit +down +1 +1 +\+0 +redefined, allowed' ratchets
check_php R08i "ratchet: the base is counted with the base's own kit-config.sh (the cap is read from the old path there)" 0 'was: max json scripts/debt-caps.json value source-files-over-limit' ratchets

branch r08j
command rm scripts/kit-config.sh
check_php R08j "ratchet: without kit-config.sh next to it the script stops and says so" fail 'kit-config.sh belongs next to this script' ratchets
git checkout -q -- scripts/kit-config.sh

branch r09
edit scripts/debt-caps.json 's/"source-files-over-limit": 1/"source-files-over-limit": 2/; s/"classes-no-test-names": 1/"classes-no-test-names": 0/'
commit "PROJ-2 chore: move a cap"
check_php R09 "ratchet: one cap up fails, even when another cap falls by the same amount" fail '^cap-source-files-over-limit +down +1 +2 +\+1 +FAILS' ratchets

branch r09b
edit scripts/debt-caps.json 's/"source-files-over-limit": 1/"source-files-over-limit": 2/'
commit "PROJ-2 chore: raise a cap

[ratchet: cap-source-files-over-limit] generated client, owner decision D-12"
check_php R09b "ratchet: a raised cap with the tag in a commit that touches the caps file passes" 0 '^cap-source-files-over-limit +down +1 +2 +\+1 +allowed' ratchets

branch r10
edit scripts/debt-caps.json 's/^\{$/{\
    "ci-duration-minutes": 12,/'
commit "PROJ-2 chore: a cap without a row"
check_php R10 "ratchet: a caps key without its cap-<key> row is caught" fail 'the key ci-duration-minutes in scripts/debt-caps.json has no row cap-ci-duration-minutes' ratchets

branch r10b
edit scripts/debt-caps.json '/"dependencies-abandoned"/d'
commit "PROJ-2 chore: drop a caps key but keep its row"
check_php R10b "ratchet: a cap-<key> row whose key is gone fails" fail 'cap-dependencies-abandoned: missing key dependencies-abandoned at the head' ratchets

branch r11
check_php R11 "ratchet: a row whose only path is optional and missing counts 0, not the whole tree" 0 '^refactor-skip max 0$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
git rm -q eslint.config.js
commit "PROJ-2 chore: no lint config"
check_php R11b "ratchet: same for a grep row (lint-config-off without the lint config)" 0 '^lint-config-off +down +0 +0 +\+0 +ok' ratchets

branch r12
mkdir -p packages/billing/src
printf '<?php\n// TODO hidden from every row\n' > packages/billing/src/Invoice.php
commit "PROJ-2 feat: a new top-level folder"
check_php R12 "ratchet: a tracked source file outside every path the rows cover fails" fail '^  packages/billing/src/Invoice.php$' ratchets

branch r13
check_php R13 "ratchet: no base variable in CI fails" fail 'no base branch\. This is a CI run' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/ci-ratchets.sh
check_php R13b "ratchet: GitLab's variable is read" 0 '^Ratchets against origin/main' env -u CHECK_BASE CI=true CI_MERGE_REQUEST_TARGET_BRANCH_NAME=main "$SHELL_BIN" scripts/ci-ratchets.sh
check_php R13c "ratchet: GitHub's variable is read" 0 '^Ratchets against origin/main' env -u CHECK_BASE CI=true GITHUB_BASE_REF=main "$SHELL_BIN" scripts/ci-ratchets.sh
check_php R13d "ratchet: Bitbucket's variable is read" 0 '^Ratchets against origin/main' env -u CHECK_BASE CI=true BITBUCKET_PR_DESTINATION_BRANCH=main "$SHELL_BIN" scripts/ci-ratchets.sh

branch r16
printf 'coverage:\n  threshold: 70\n' > coverage.yml
commit "PROJ-2 ci: lower the coverage floor"
check_php R16 "ratchet: a min row over a number in a committed file (the coverage floor) fails when it falls" fail '^coverage-floor +up +80 +70 +-10 +FAILS' ratchets

branch r17
printf 'coverage:\n  threshold: 85\n' > coverage.yml
commit "PROJ-2 ci: raise the coverage floor"
check_php R17 "ratchet: the same row may go up" 0 '^coverage-floor +up +80 +85 +\+5 +ok' ratchets
printf 'threshold: 50\n' > scripts/mutation-floor.txt
commit "PROJ-2 ci: lower the mutation floor"
check_php R17c "ratchet: the mutation floor (scripts/mutation-floor.txt, the row of tools/README.md) fails when it falls" fail '^mutation-floor +up +60 +50 +-10 +FAILS' ratchets
check_php R17b "ratchet: the base's copy with --guard passes an untouched script" 0 'unchanged outside the table' env CHECK_BASE=main "$SHELL_BIN" "$ROOT/base/ci-ratchets.sh" --guard scripts/ci-ratchets.sh

branch r18
git rm -q lists/layout.txt
edit scripts/ci-ratchets.sh '/^layout-list +max/d'
commit "PROJ-2 refactor: empty the layout list and delete it with its row"
check_php R18 "ratchet: a list emptied and deleted with its row in one PR fails without the row's tag (the row counted 2 on the base)" fail '^layout-list +- +2 +- +- +removed, FAILS without \[ratchet: layout-list\]' ratchets

branch r18b
git rm -q lists/layout.txt
edit scripts/ci-ratchets.sh '/^layout-list +max/d'
commit "PROJ-2 refactor: empty the layout list and delete it with its row

[ratchet: layout-list] list emptied and deleted"
check_php R18b "ratchet: the same PR with [ratchet: <name>] in the commit that removes the row passes" 0 '^layout-list +- +2 +- +- +removed, allowed' ratchets

branch r18c
git rm -q lists/layout.txt
commit "PROJ-2 refactor: delete the list file and keep its row"
check_php R18c "ratchet: deleting the list file and keeping its row fails and says what to do" fail 'layout-list: missing lists/layout.txt at the head' ratchets

branch r19
edit phpstan.neon '/noEnvCallsOutsideOfConfig/d'
commit "PROJ-2 chore: drop an analyser switch"
check_php R19 "ratchet: analyser-switches (a min row) fails when a switch is removed from the PHP analyser config" fail '^analyser-switches +up +2 +1 +-1 +FAILS' ratchets

branch r19b
edit phpstan.neon 's/checkMissingOverrideMethodAttribute: true/checkMissingOverrideMethodAttribute: false/'
commit "PROJ-2 chore: switch an analyser switch off"
check_php R19b "ratchet: the same when a switch is set to false" fail '^analyser-switches +up +2 +1 +-1 +FAILS' ratchets

branch r20
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
only-pathspec                          max  grep   :(glob)app\/**\/*.php  TODO/'
commit "PROJ-2 ci: a row whose paths are a pathspec only"
check_php R20 "ratchet: a row whose only path is a pathspec fails clearly, it does not count 0" fail 'only-pathspec at the head: a row needs at least one real path' ratchets
check_php R20b "ratchet --head: the same row prints ? and the reason" fail 'only-pathspec cannot be counted: a row needs at least one real path' "$SHELL_BIN" scripts/ci-ratchets.sh --head

branch r21
echo '{"resources/js/a.ts": {"no-console": {"count": 2}, "eqeqeq": {"count": 1}}}' > eslint-suppressions.json
mkdir -p baselines
printf 'src/a.ts: error TS2532: Object is possibly undefined.\nsrc/a.ts: error TS2532: Object is possibly undefined.\n' > baselines/no-unchecked-indexed-access.txt
commit "PROJ-2 chore: baselines that grow"
check_php R21 "ratchet: lint-baseline adds up the counts of the linter's bulk suppressions (3)" fail '^lint-baseline +down +0 +3 +\+3 +FAILS' ratchets
check_php R21b "ratchet: type-baseline counts the lines of the keyed baselines (2)" fail '^type-baseline +down +0 +2 +\+2 +FAILS' ratchets

branch r22
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
strict-types-missing                   max  without @SOURCE_PATHS,@TEST_PATHS  (^|\/)[^\/.]+\\.php$ ^declare\\(strict_types=1\\);/'
commit "PROJ-2 ci: a row over the files without the strict-types line"
check_php R22 "ratchet: a without row counts the files that lack a line (the 11 PHP files of the fixture), and arrives as a new row" 0 '^strict-types-missing +down +11 +11 +\+0 +new row' ratchets
git push -q origin r22
git checkout -q -B r22b r22
printf '<?php\n\ndeclare(strict_types=1);\n' > routes/web.php
mkdir -p resources/views
printf '<div>no strict line, and not counted: a template</div>\n' > resources/views/page.blade.php
commit "PROJ-2 refactor: one file gets the line; a new template has none"
check_php R22b "ratchet: a file that gets the line lowers the row; a template (page.blade.php) is not counted" 0 '^strict-types-missing +down +11 +10 +-1 +ok' env CHECK_BASE=r22 "$SHELL_BIN" scripts/ci-ratchets.sh
git checkout -q -B r22c r22
printf '<?php\n\nfinal class Loose\n{\n}\n' > app/Services/Loose.php
printf '<?php\n\nit("is loose", function (): void {\n    expect(Loose::class)->toBeString();\n});\n' > tests/Unit/LooseTest.php
commit "PROJ-2 feat: a new class and its test, both without the line"
check_php R22c "ratchet: new files without the line raise the row and fail" fail '^strict-types-missing +down +11 +13 +\+2 +FAILS' env CHECK_BASE=r22 "$SHELL_BIN" scripts/ci-ratchets.sh
git checkout -q -B r22d r22
edit scripts/ci-ratchets.sh 's/^(strict-types-missing +max +without +[^ ]+ +).*$/\1onlyone/'
commit "PROJ-2 ci: a without row with one expression"
check_php R22d "ratchet: a without row with one expression fails clearly" fail 'a without row needs two expressions' env CHECK_BASE=r22 "$SHELL_BIN" scripts/ci-ratchets.sh

# counters that the reviews said could be met without the work
branch r14
mkdir -p app/Samples tests/Samples resources/js/samples
cat > app/Samples/Comments.php <<'PHP'
<?php
#[Attribute]
final class Comments
{
    public string $url = 'http://example.test/a#b';
    public string $color = '#fff';
    // one
    # two
    public int $three = 3; // three
    public int $four = 4; # four
    /* five */
    /** six, prose on the opening line */
    /**
     * seven
     * @return int
     */
    public function eight(): int { return 8; /* eight */ }
}
PHP
cat > resources/js/samples/comments.tsx <<'TSX'
export function Sample() {
    const link = "https://example.test";
    return (
        <div>
            {/* nine */}
            <a href="#top">top</a>
        </div>
    );
}
TSX
cat > tests/Samples/DoublesTest.php <<'PHP'
<?php
it('doubles', function (): void {
    $a = Mockery::mock(A::class);
    $b = Mockery::spy(B::class);
    $c = \Mockery::namedMock('C');
    $d = $this->mock(D::class);
    $e = $this->spy(E::class);
    $f = $this->partialMock(F::class);
    $g = test()->mock(G::class);
    $h = mock(H::class);
    $i = spy(I::class);
    $j = $this->createMock(J::class);
    $k = $this->createStub(K::class);
    $l = $this->createPartialMock(L::class, []);
    $m = $this->getMockBuilder(M::class);
    $n = $this->prophesize(N::class);
});
PHP
cat > tests/Samples/SkipsTest.php <<'PHP'
<?php
it('one', function (): void {})->skip('later');
it('two', function (): void {})->todo();
it('three', function (): void {})
    ->skip();
final class SkipsTest
{
    #[RequiresPhp('>= 9')]
    public function testFour(): void { $this->markTestSkipped('later'); }
    public function testSix(): void { $this->markTestIncomplete(); }
}
PHP
printf '# A heading in a Markdown file is not a comment\n' > app/Samples/NOTES.md
mkdir -p resources/views
printf '<div>\n    <!-- ten -->\n    {{-- eleven --}}\n    <a href="#top">top</a>\n</div>\n' > resources/views/sample.blade.php
commit "PROJ-2 test: samples for the counters"
check_php R14 "ratchet: comments counts // # trailing /* */ JSX, docblock prose and the markup comments <!-- and {{-- (11 new), not #[Attribute], URLs or '#fff'" fail '^comments +down +1 +12 +\+11 ' ratchets
check_php R14b "ratchet: test-doubles counts all 14 spellings" fail '^test-doubles +down +1 +15 +\+14 ' ratchets
check_php R14c "ratchet: test-skip counts skip, todo, a chained skip, #[Requires, markTestSkipped, markTestIncomplete (6)" fail '^test-skip +down +0 +6 +\+6 ' ratchets

branch r15
printf 'export default [\n    { ignores: ["**/legacy/**", "resources/js/old.ts"] },\n    { rules: { "no-console": "off" } },\n];\n' > eslint.config.js
printf 'pipelines:\n  pull-requests:\n    "**":\n      - step:\n          script:\n            - bash scripts/ci-ratchets.sh || true\n' > bitbucket-pipelines.yml
mkdir -p .github/workflows
printf 'jobs:\n  a:\n    continue-on-error: true\n    steps: []\n' > .github/workflows/ci.yml
printf 'a:\n  allow_failure: true\n  script: [x]\n' > .gitlab-ci.yml
commit "PROJ-2 ci: weaken everything

[ratchet: script] proof"
check_php R15 "ratchet: glob entries in lint ignores are counted (2)" fail '^lint-config-ignores +down +0 +2 +\+2 +FAILS' ratchets
check_php R15b "ratchet: ci-skipped-steps counts || true, continue-on-error and allow_failure (3)" fail '^ci-skipped-steps +down +0 +3 +\+3 +FAILS' ratchets
check_php R15c "ratchet: lint-config-off counts the switched-off rule" fail '^lint-config-off +down +0 +1 +\+1 +FAILS' ratchets

# ---------------------------------------------------------------- a runner with one JSON tool and a small fixture
farm() {
    # farm <name> <tools...>: a folder that is the whole PATH
    local dir="$ROOT/path-$1" tool
    shift
    mkdir -p "$dir"
    for tool in git grep awk sed wc tr cat sort mktemp rm dirname basename date xargs env cut head "$@"; do
        [ -n "$(command -v "$tool")" ] || continue
        ln -s "$(command -v "$tool")" "$dir/$tool"
    done
    ln -s "$SHELL_BIN" "$dir/bash"
    echo "$dir"
}

build_node_project() {
    local origin="$ROOT/node-origin.git" work="$ROOT/node"
    git init -q --bare -b main "$origin"
    git init -q -b main "$work"
    cd "$work" || exit 1
    git remote add origin "file://$origin"
    mkdir -p src test scripts
    cp "$KIT/tools/ci-ratchets.sh" "$KIT/tools/kit-config.sh" scripts/
    edit scripts/ci-ratchets.sh 's/^SOURCE_PATHS=.*$/SOURCE_PATHS="src"/; s/^TEST_PATHS=.*$/TEST_PATHS="test"/; s/^TEST_SKIP_PATHS=.*$/TEST_SKIP_PATHS="test"/; s/^FRONTEND_PATHS=.*$/FRONTEND_PATHS=""/; s/^UNCOUNTED_PATHS=.*$/UNCOUNTED_PATHS="eslint.config.js?"/; s/^ANALYSER_CONFIGS=.*$/ANALYSER_CONFIGS=""/; s/^ANALYSER_BASELINE=.*$/ANALYSER_BASELINE=""/; s/^REFACTOR_CONFIG=.*$/REFACTOR_CONFIG=""/; s/^AUDIT_MANIFEST=.*$/AUDIT_MANIFEST="package.json"/; s/^(audit-ignore .*) count\? config\.audit\.ignore$/\1 count? auditIgnore/'
    cp "$KIT/tools/debt-caps.json" scripts/debt-caps.json
    echo '{"name": "proof", "auditIgnore": ["GHSA-1", "GHSA-2"]}' > package.json
    printf 'export const a = 1; // TODO one\n' > src/a.ts
    printf 'test("a", () => {});\n' > test/a.test.ts
    git add -A
    git commit -q -m "PROJ-1 chore: fixture"
    git push -q origin main
    git checkout -q -b change
    echo '{"name": "proof", "auditIgnore": ["GHSA-1", "GHSA-2", "GHSA-3"]}' > package.json
    git add -A
    git commit -q -m "PROJ-2 chore: ignore one more advisory"
}

build_node_project
ONLY_JQ=$(farm jq jq)
ONLY_NODE=$(farm node node)
ONLY_PHP=$(farm php php)
NO_TOOL=$(farm none)
cd "$ROOT/node" || exit 1
check N01 "ratchet on a runner without php: PATH holds git, grep and jq only" fail '^audit-ignore +down +2 +3 +\+1 +FAILS' env PATH="$ONLY_JQ" CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
check N02 "same, PATH holds node only" fail '^audit-ignore +down +2 +3 +\+1 +FAILS' env PATH="$ONLY_NODE" CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
check_php N03 "same, PATH holds php only" fail '^audit-ignore +down +2 +3 +\+1 +FAILS' env PATH="$ONLY_PHP" CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
check N04 "same, none of jq, node, php: fails with a clear message" fail 'a json row needs jq, node or php on the runner; none was found' env PATH="$NO_TOOL" CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
printf 'export default [{ ignores: ["dist/**"] }];\n' > eslint.config.js
git add -A
git commit -q -m "PROJ-2 chore: a lint config"
check N05 "a block row (lint config) with node only" fail '^lint-config-ignores +down +0 +1 +\+1 +FAILS' env PATH="$ONLY_NODE" CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
check N06 "a block row with jq only: fails with a clear message" fail 'a block row needs node or php on the runner' env PATH="$ONLY_JQ" CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
for tool in jq node php; do
    run=check
    [ "$tool" != php ] || run=check_php
    $run "N07-$tool" "json operations agree ($tool): value, count, count?, leaves, keys, wildcard" 0 '^3 2 0 3 a,b,c 3$' "$SHELL_BIN" -c '
        j() { RATCHET_JSON_TOOL='"$tool"' bash scripts/ci-ratchets.sh --json "$1"; }
        doc="{\"a\": 3, \"b\": {\"x\": [1, 2], \"y\": [3]}, \"c\": [{\"k\": 1}, {\"k\": 2}]}"
        echo "$(echo "$doc" | j "value a") $(echo "$doc" | j "count b") $(echo "$doc" | j "count? nope") $(echo "$doc" | j "leaves b") $(echo "$doc" | j "keys ." | tr "\n" "," | sed "s/,$//") $(echo "$doc" | j "count b.*")"'
    $run "N08-$tool" "a missing key is an error, not 0 ($tool)" fail 'missing key nope' "$SHELL_BIN" -c 'echo "{}" | RATCHET_JSON_TOOL='"$tool"' bash scripts/ci-ratchets.sh --json "value nope"'
done

# ---------------------------------------------------------------- shallow clones
cd "$ROOT/work" || exit 1
git checkout -q -f main
git checkout -q -B deep main
i=0
while [ "$i" -lt 5 ]; do
    echo "$i" > "docs/deep-$i.md"
    commit "PROJ-3 docs: deep $i"
    i=$((i + 1))
done
git push -q origin deep
git clone -q --depth 1 --branch deep "file://$ROOT/origin.git" "$ROOT/shallow"
cd "$ROOT/shallow" || exit 1
check_php H01 "ratchet in a clone of depth 1: deepens until it finds the merge base" 0 '^todo +down +1 +1 +\+0 +ok' env CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
git clone -q --depth 1 --branch deep "file://$ROOT/origin.git" "$ROOT/shallow2"
cd "$ROOT/shallow2" || exit 1
check H02 "commit check in a clone of depth 1: deepens and reads all 5 commits" 0 '5 commits between origin/main' env CHECK_BASE=main "$SHELL_BIN" scripts/ci-commit-messages.sh
git clone -q --depth 1 --branch deep "file://$ROOT/origin.git" "$ROOT/shallow3"
cd "$ROOT/shallow3" || exit 1
git fetch -q --depth 1 origin "+refs/heads/main:refs/remotes/origin/main"
git remote set-url origin "file://$ROOT/gone.git"
check H03 "ratchet in a shallow clone that cannot be deepened: fails and says what to set" fail 'Clone with full depth' env CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
check H04 "commit check in the same clone: fails, does not pass" fail 'Clone with full depth' env CHECK_BASE=main "$SHELL_BIN" scripts/ci-commit-messages.sh

# ---------------------------------------------------------------- dashboard
branch d01
check_php D01 "dashboard gate: rows above zero but at their cap pass" 0 '^size +source-files-over-limit +1 +1 +ok' dashboard --offline --details
check_php D01b "dashboard gate: a ratchet row above zero is shown as held" 0 '^ratchet +todo +1 +- +held' dashboard --offline

branch d02
big_file 450 > app/Services/Big2.php
commit "PROJ-2 feat: a second big file"
check_php D02 "dashboard gate: a row above its cap fails" fail '^size +source-files-over-limit +2 +1 +OPEN' dashboard --offline --details
check_php D02b "dashboard gate: the open row is followed by one line of what to do" fail '^ +do this: split each file by responsibility until it has at most 400 lines' dashboard --offline --details

branch d03
check_php D03 "dashboard --final: a measured row above zero fails although it is at its cap" fail '^size +source-files-over-limit +1 +0 +OPEN' dashboard --final --offline
check_php D03b "dashboard --final: a ratchet row above zero fails" fail '^ratchet +todo +1 +0 +OPEN' dashboard --final --offline
check_php D03c "dashboard --final: an open ratchet row says what to do" fail 'do this: bring the row to 0: its line in ci-ratchets.sh says what it counts and where' dashboard --final --offline
check_php D03d "dashboard --final: every OPEN line is followed by a do-this line" 0 '^same$' "$SHELL_BIN" -c 'out=$(bash scripts/debt-dashboard.sh --final --offline 2>&1); [ "$(grep -c " OPEN" <<< "$out")" -eq "$(grep -c "^ *do this: " <<< "$out")" ] && echo same; exit 0'

branch d04
git rm -q app/Services/Big.php app/Actions/Create.php lists/layout.txt
edit scripts/ci-ratchets.sh '/^layout-list +max/d'
printf '<?php\n\nnamespace App\\Actions;\n\nfinal class CreateOrder\n{\n    public function handle(): int\n    {\n        return 1; // the constant is the contract\n    }\n}\n' > app/Actions/CreateOrder.php
mkdir -p tests/Feature
printf '<?php\n\nit("fakes", function (): void {\n    Event::fake();\n    expect(true)->toBeTrue();\n});\n' > tests/Feature/FakeTest.php
edit scripts/debt-caps.json 's/: [0-9]+/: 0/'
edit fixtures/outdated.json 's/.*/{"locked": []}/'
commit "PROJ-2 chore: everything at zero except the shrink-only rows

[ratchet: event-fake-all] proof"
check_php D04 "dashboard --final: comments, test-doubles and event-fake-all above zero do not fail it" 0 '^ratchet +comments +1 +- +shrink-only' dashboard --final --offline
check_php D04b "dashboard --final: the summary shows no open row" 0 ' 0 open, 0 not measured' dashboard --final --offline

branch d05
edit scripts/debt-dashboard.sh 's/^SHRINK_ONLY="(.*)"$/SHRINK_ONLY="\1 cap-source-files-over-limit"/'
git rm -q app/Actions/Create.php lists/layout.txt
edit scripts/ci-ratchets.sh '/^layout-list +max/d'
printf '<?php\n\nnamespace App\\Actions;\n\nfinal class CreateOrder\n{\n    public function handle(): int\n    {\n        return 1;\n    }\n}\n' > app/Actions/CreateOrder.php
edit scripts/debt-caps.json 's/"(classes-no-test-names|classes-without-own-test|dependencies-behind)": [0-9]+/"\1": 0/'
edit fixtures/outdated.json 's/.*/{"locked": []}/'
commit "PROJ-2 chore: one cap with an owner decision

[ratchet: script] owner decision D-12: the generated client stays over the limit"
check_php D05 "dashboard --final: a cap listed in SHRINK_ONLY (owner decision) does not fail it" 0 '^ratchet +cap-source-files-over-limit +1 +- +shrink-only' dashboard --final --offline
check_php D05b "dashboard --final: its measured row is held to the cap, not to zero" 0 '^size +source-files-over-limit +1 +1 +ok' dashboard --final --offline
edit scripts/debt-caps.json 's/"source-files-over-limit": 1/"source-files-over-limit": 2/'
check_php D05c "dashboard --final: a cap above its row fails" fail '^size +source-files-over-limit +1 +2 +OPEN \(the cap 2 is above the row\)' dashboard --final --offline
check_php D05e "dashboard --final: that row says what to lower the cap to" fail 'do this: lower the cap of source-files-over-limit in scripts/debt-caps.json to 1' dashboard --final --offline
check_php D05d "dashboard gate: the same cap passes and says what to lower it to" 0 '^size +source-files-over-limit +1 +2 +ok \(lower the cap to 1\)' dashboard --offline
git checkout -q -- scripts/debt-caps.json

branch d06
edit scripts/debt-caps.json 's/^\{$/{\
    "ci-duration-minutes": 12,/'
check_php D06 "dashboard: a caps key that no row measures and no ratchet row holds is UNKNOWN" fail '^caps +ci-duration-minutes +\? +\? +UNKNOWN' dashboard --offline
git checkout -q -- scripts/debt-caps.json
edit scripts/debt-caps.json '/"test-files-over-limit"/d'
check_php D06b "dashboard: a row whose key is missing from the caps file is UNKNOWN" fail '^size +test-files-over-limit +\? +\? +UNKNOWN \(no key test-files-over-limit' dashboard --offline
git checkout -q -- scripts/debt-caps.json
edit scripts/ci-ratchets.sh '/^cap-test-files-over-limit /d'
check_php D06c "dashboard: a caps key without its cap-<key> ratchet row is UNKNOWN" fail '^size +test-files-over-limit +\? +\? +UNKNOWN \(no ratchet row cap-test-files-over-limit' dashboard --offline
git checkout -q -- scripts/ci-ratchets.sh
echo 'not json' > scripts/debt-caps.json
check_php D06d "dashboard: an unreadable caps file is UNKNOWN, with no PHP warning in the output" fail '^caps +scripts/debt-caps.json +\? +\? +UNKNOWN \(cannot be read' dashboard --offline
check_php D06e "dashboard: that output holds no interpreter warning" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline 2>&1 | grep -c -i -E "warning|notice|deprecated|stack trace"; exit 0'
git checkout -q -- scripts/debt-caps.json

branch d07
check_php D07 "dashboard: class Create is untested although CreateOrder is imported (no substring match)" 0 '^app/Actions/Create.php$' dashboard --offline --details
printf '<?php\n\nit("creates", function (): void {\n    expect(resolve(Create::class))->toBeObject();\n});\n' > tests/Unit/CreateTest.php
git add -A
check_php D07b "dashboard: a ::class reference and an own test file clear it" 0 '^tests +classes-no-test-names +0 +1 +ok \(lower the cap to 0\)' dashboard --offline --details

branch d08
big_file 450 > app/Services/Untracked.php
check_php D08 "dashboard: an untracked file is not counted" 0 '^size +source-files-over-limit +1 +1 +ok' dashboard --offline
command rm app/Services/Untracked.php

branch d09
check_php D09 "dashboard without --offline: the online rows run and the docs row is measured" 0 '^docs +docs-older-than-limit +0 +0 +ok' dashboard
check_php D09b "dashboard: a command row reads JSON through json" 0 '^command +dependencies-majors-behind +0 +0 +ok' dashboard --offline
mkdir -p packages/x
printf '<?php\n' > packages/x/a.php
commit "PROJ-2 feat: a folder no row counts"
check_php D09c "dashboard: a tracked source file outside the row paths is a failing row" fail '^ratchet +files-outside-row-paths +1 +0 +OPEN' dashboard --offline

branch d10
cat > "$ROOT/row.txt" <<'ROW'
    "ci-duration-minutes::online::cat fixtures/pipeline.json | json 'value minutes'"
ROW
awk -v row="$ROOT/row.txt" '{ print } /^COMMAND_ROWS=\(/ { while ((getline line < row) > 0) print line }' scripts/debt-dashboard.sh > scripts/debt-dashboard.tmp
cat scripts/debt-dashboard.tmp > scripts/debt-dashboard.sh
command rm scripts/debt-dashboard.tmp
edit scripts/debt-caps.json 's/^\{$/{\
    "ci-duration-minutes": 12,/'
edit scripts/ci-ratchets.sh 's/^(cap-dependencies-majors-behind .*)$/\1\
cap-ci-duration-minutes                max  json   @CAPS_FILE  value ci-duration-minutes/'
echo '{"minutes": 11}' > fixtures/pipeline.json
commit "PROJ-2 ci: the CI duration as a row of the project's own

[ratchet: script] a COMMAND_ROWS line for the CI duration"
check_php D10d "dashboard: a row the project adds without its line in ROW_ADVICE is UNKNOWN" fail '^command +ci-duration-minutes +\? +\? +UNKNOWN \(no "do this" line for ci-duration-minutes in ROW_ADVICE\)' dashboard
edit scripts/debt-dashboard.sh 's/^ROW_ADVICE=\(\)$/ROW_ADVICE=("ci-duration-minutes::speed up or split the slowest step of the pipeline")/'
commit "PROJ-2 ci: what to do when the CI duration is open

[ratchet: script] the do-this line of the CI duration row"
check_php D10 "a row the project adds (key, cap-<key> row, COMMAND_ROWS line, ROW_ADVICE line, the script tag): the ratchets pass" 0 '^cap-ci-duration-minutes +down +0 +12 +\+12 +new row' ratchets
check_php D10b "dashboard: that row is measured against its cap in the full run" 0 '^command +ci-duration-minutes +11 +12 +ok \(lower the cap to 11\)' dashboard
check_php D10c "dashboard --offline: an online row of the project's own is skipped" 0 '^command +ci-duration-minutes +- +- +skipped' dashboard --offline
echo '{"minutes": 14}' > fixtures/pipeline.json
check_php D10e "dashboard: that row above its cap prints the project's own do-this line" fail 'do this: speed up or split the slowest step of the pipeline' dashboard
git checkout -q -- fixtures

branch d11
mkdir -p app/Models
printf '<?php\n\nnamespace App\\Models;\n\nclass Open\n{\n}\n' > app/Models/Open.php
printf '<?php\n\nnamespace App\\Models;\n\nreadonly class Ro\n{\n}\n' > app/Models/Ro.php
printf '<?php\n\nnamespace App\\Models;\n\nclass Base\n{\n}\n' > app/Models/Base.php
printf '<?php\n\nnamespace App\\Models;\n\nfinal class Child extends Base\n{\n}\n' > app/Models/Child.php
printf '<?php\n\nnamespace App\\Models;\n\nabstract class Shape\n{\n}\n' > app/Models/Shape.php
commit "PROJ-2 feat: open, closed, abstract and parent classes"
check_php D11 "dashboard: open-classes counts an open class, an open readonly class and a concrete parent (3), above its cap; the abstract class is not counted" fail '^classes +open-classes +3 +0 +OPEN' dashboard --offline --details
check_php D11b "dashboard --details: the open class is named" fail '^app/Models/Open.php$' dashboard --offline --details
check_php D11c "dashboard: a final class is not counted, also when it extends a class" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --details 2>&1 | grep -c -E "^app/Models/Child\.php$"; exit 0'
check_php D11k "dashboard --details: a concrete class that another class extends is named, as an open class" 0 '^1$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --details 2>&1 | grep -c -E "^app/Models/Base\.php$"; exit 0'
check_php D11l "dashboard --details: an abstract class is not counted" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --details 2>&1 | grep -c -E "^app/Models/Shape\.php$"; exit 0'
check_php D11g "dashboard: the open row says what to do" fail 'do this: close each concrete class for extension after the pre-scan' dashboard --offline
printf '<?php\n\nnamespace App\\Models {\n    class Indented\n    {\n    }\n}\n' > app/Models/Indented.php
printf '<?php\n\nnamespace App\\Models;\n\n#[Deprecated] class Tagged\n{\n}\n' > app/Models/Tagged.php
printf '<?php\n\nnamespace App\\Models;\n\n#[Deprecated] final class TaggedClosed\n{\n}\n' > app/Models/TaggedClosed.php
printf '<?php\n\nnamespace App\\Models {\n    abstract class IndentedShape\n    {\n    }\n}\n' > app/Models/IndentedShape.php
printf '<?php\n\nnamespace App\\Models;\n\nabstract readonly class RoShape\n{\n}\n' > app/Models/RoShape.php
git add -A
check_php D11h "dashboard: an indented class and a class after an attribute on the same line are counted, an indented abstract class and an abstract readonly class are not (5 open)" fail '^classes +open-classes +5 +0 +OPEN' dashboard --offline --details
check_php D11i "dashboard --details: the two are named, the two abstract ones are not" 0 '^2$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --details 2>&1 | grep -c -E "^app/Models/(Indented|Tagged|IndentedShape|RoShape)\.php$"; exit 0'
check_php D11j "dashboard: a final class after an attribute is not counted" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --details 2>&1 | grep -c -E "^app/Models/TaggedClosed\.php$"; exit 0'
git rm -q -f app/Models/Indented.php app/Models/Tagged.php app/Models/TaggedClosed.php app/Models/IndentedShape.php app/Models/RoShape.php
edit scripts/debt-caps.json 's/"open-classes": 0/"open-classes": 3/'
check_php D11d "dashboard gate: the same count at its cap passes" 0 '^classes +open-classes +3 +3 +ok' dashboard --offline
commit "PROJ-2 chore: raise the cap of the open classes

[ratchet: cap-open-classes] wanted"
check_php D11e "ratchet: that cap was 0 on the base, so the tag does not let it rise" fail '^cap-open-classes +down +0 +3 +\+3 +FAILS' ratchets
check D11m "no row counts protected methods: the kit's scripts and caps file do not name protected-methods" 0 '^0$' "$SHELL_BIN" -c 'cat "'"$KIT"'/tools/ci-ratchets.sh" "'"$KIT"'/tools/debt-dashboard.sh" "'"$KIT"'/tools/debt-caps.json" | grep -c -e "protected-methods"; exit 0'

branch d12
mkdir -p app/Actions/Billing
printf '<?php\n\nnamespace App\\Actions\\Billing;\n\nfinal class CreateInvoice\n{\n}\n' > app/Actions/Billing/CreateInvoice.php
commit "PROJ-2 feat: a behavior class in a subdomain folder"
check_php D12 "dashboard: a behavior class in a subdomain folder under Actions is counted" fail '^app/Actions/Billing/CreateInvoice.php$' dashboard --offline --details

branch d16
edit scripts/debt-dashboard.sh "s/^SOURCE_FILES=.*$/SOURCE_FILES='^(app\/.*\\\\.php|resources\/views\/.*\\\\.blade\\\\.php)$'/"
mkdir -p resources/views
big_file 450 > resources/views/report.blade.php
git add -A
check_php D16 "dashboard, server-rendered templates: with the SOURCE_FILES value of tools/README.md a view over the limit is in the size row" fail '^size +source-files-over-limit +2 +1 +OPEN' dashboard --offline --details
check_php D16b "dashboard --details names the view with its 451 lines" fail '^ *451 +resources/views/report.blade.php$' dashboard --offline --details
git checkout -q -- scripts/debt-dashboard.sh

# --final after the close: what gates, what is only shown, and the target of the min row
cd "$ROOT/work" || exit 1
git checkout -q -f main
git clean -q -fd
git checkout -q d04
edit fixtures/outdated.json 's/.*/{"locked": [{"name": "a\/a"}, {"name": "b\/b"}]}/'
check_php D13 "dashboard --final, full run: dependencies-behind (minor and patch) is shown with its count and does not fail" 0 '^command +dependencies-behind +2 +- +shown \(never fails\)' dashboard --final
check_php D13b "dashboard --final, full run: the summary shows no open row with two packages behind" 0 ' 0 open, 0 not measured' dashboard --final
check_php D13c "dashboard --offline: a shown row is skipped like an online row" 0 '^command +dependencies-behind +- +- +skipped' dashboard --final --offline
echo '{"locked": [{"name": "c/c"}]}' > fixtures/majors.json
check_php D13d "dashboard --final: a major behind still fails, and the row says what to do" fail 'do this: upgrade each major in a pull request of its own' dashboard --final
git checkout -q -- fixtures
edit scripts/debt-dashboard.sh 's/^ANALYSER_SWITCHES_TARGET=.*$/ANALYSER_SWITCHES_TARGET=3/'
check_php D14 "dashboard --final: the min row analyser-switches below its target fails" fail '^ratchet +analyser-switches +2 +3 +OPEN' dashboard --final --offline
check_php D14b "dashboard --final: that row says what to do" fail 'do this: switch on, in every config of ANALYSER_CONFIGS and TYPE_CHECKER_CONFIGS' dashboard --final --offline
check_php D14c "dashboard gate: the same row is held, with its target shown, and passes" 0 '^ratchet +analyser-switches +2 +3 +held \(only goes up\)' dashboard --offline
edit scripts/debt-dashboard.sh 's/^ANALYSER_SWITCHES_TARGET=.*$/ANALYSER_SWITCHES_TARGET=""/'
check_php D14d "dashboard --final: without a target the row is UNKNOWN, not passed" fail '^ratchet +analyser-switches +2 +\? +UNKNOWN \(ANALYSER_SWITCHES_TARGET is not a number\)' dashboard --final --offline
git checkout -q -- scripts/debt-dashboard.sh
check_php D14e "dashboard --final: at its target the row is ok" 0 '^ratchet +analyser-switches +2 +2 +ok' dashboard --final --offline

# ---------------------------------------------------------------- commit messages
message_case() {
    # message_case <id> <scenario> <0|fail> <text> <commit message>
    branch "c-$1"
    echo "$1" > "docs/$1.md"
    commit "$5"
    check "$1" "$2" "$3" "$4" commits
}
message_case C01 "commit check accepts: PROJ-12 feat: subject" 0 '1 commits between' "PROJ-12 feat: add the export"
message_case C02 "commit check accepts: scope and breaking mark" 0 '1 commits between' "PROJ-12 fix(api)!: drop the old field"
message_case C03 "commit check rejects: type(TICKET): subject" fail 'feat\(PROJ-12\): add the export' "feat(PROJ-12): add the export"
message_case C04 "commit check rejects: ticket without a type" fail 'PROJ-12 add the export' "PROJ-12 add the export"
message_case C05 "commit check rejects: type without a ticket" fail 'feat: add the export' "feat: add the export"
message_case C06 "commit check rejects: another project's ticket" fail 'OTHER-1 feat' "OTHER-1 feat: add the export"
message_case C07 "commit check rejects: an unknown type" fail 'PROJ-12 wip' "PROJ-12 wip: add the export"
message_case C08 "commit check rejects: no subject" fail 'PROJ-12 feat:' "PROJ-12 feat: "
message_case C09 "commit check accepts: a revert generated by git" 0 '1 commits between' 'Revert "PROJ-12 feat: add the export"'
message_case C09b "G-15: a revert of a subject without the form is rejected" fail 'Revert "no ticket here"' 'Revert "no ticket here"'
message_case C09c "G-15: a revert of a revert of a subject with the form is accepted" 0 '1 commits between' 'Reapply "Revert "PROJ-12 feat: add the export""'

branch c-merge
echo x > docs/x.md
commit "PROJ-12 docs: x"
git checkout -q main
git checkout -q -B side main
echo y > docs/y.md
commit "PROJ-12 docs: y"
git checkout -q c-merge
git merge -q --no-ff -m "Merge branch 'side' into c-merge" side
check C10 "commit check accepts: a merge commit" 0 '2 commits between' commits
git commit -q --allow-empty -m "Merge 1234567 into 89abcde"
check C11 "G-15: a single-parent commit titled like a synthetic merge has no form and is counted and rejected" fail '89abcde' commits

branch c-env
echo z > docs/z.md
commit "PROJ-12 docs: z"
check C12 "commit check: no base variable in CI fails" fail 'no base branch\. This is a CI run' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/ci-commit-messages.sh
check C13 "commit check: GitLab's variable is read" 0 '1 commits between origin/main' env -u CHECK_BASE CI=true CI_MERGE_REQUEST_TARGET_BRANCH_NAME=main "$SHELL_BIN" scripts/ci-commit-messages.sh
check C14 "commit check: GitHub's variable is read" 0 '1 commits between origin/main' env -u CHECK_BASE CI=true GITHUB_BASE_REF=main "$SHELL_BIN" scripts/ci-commit-messages.sh
check C15 "commit check: Bitbucket's variable is read" 0 '1 commits between origin/main' env -u CHECK_BASE CI=true BITBUCKET_PR_DESTINATION_BRANCH=main "$SHELL_BIN" scripts/ci-commit-messages.sh

branch c-synthetic
echo s > docs/s.md
commit "PROJ-12 docs: s"
HEAD_COMMIT=$(git rev-parse HEAD)
git checkout -q --detach origin/main
git merge -q --no-ff -m "Merge $HEAD_COMMIT into $(git rev-parse origin/main)" "$HEAD_COMMIT"
check C16 "commit check on GitHub's checkout (HEAD is the merge of the head into the base)" 0 '1 commits between' env CHECK_BASE=main "$SHELL_BIN" scripts/ci-commit-messages.sh
check_php C17 "ratchet on the same checkout" 0 '^todo +down +1 +1 +\+0 +ok' env CHECK_BASE=main "$SHELL_BIN" scripts/ci-ratchets.sh
git checkout -q -f main

branch c-prefix
edit scripts/ci-commit-messages.sh "s/^TICKET=.*$/TICKET='NOTICKET'/"
commit "NOTICKET ci: a fixed prefix for a project without tickets

[ratchet: script] proof"
check C18 "commit check with a fixed prefix (no ticket system)" 0 '1 commits between' commits
edit scripts/ci-commit-messages.sh "s/^TICKET=.*$/TICKET='<ticket pattern>'/"
check C19 "commit check: the placeholder ticket pattern the kit ships refuses to run" fail 'TICKET or EXAMPLE in the config block of this script still holds a placeholder' commits
git checkout -q -- scripts/ci-commit-messages.sh
check C19b "the kit's own copy ships the two placeholders, and no project's ticket" 0 '^2$' "$SHELL_BIN" -c 'grep -c -E "^(TICKET=.<ticket pattern>.|EXAMPLE=.<example subject>.)$" "'"$KIT"'/tools/ci-commit-messages.sh"; exit 0'
check C19c "the kit's kit-config.sh ships the database image as a placeholder" 0 '^DATABASE_IMAGE="<engine:major of production>"$' cat "$KIT/tools/kit-config.sh"

# ---------------------------------------------------------------- parallel runner
cd "$ROOT" || exit 1
check P01 "ci-parallel: two commands run and pass" 0 '^PASS +[0-9]+s +Two$' "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "One::echo one" "Two::sleep 1; echo two"
check P02 "ci-parallel: one failure fails the run and the other still reports" fail '^FAIL +[0-9]+s +Bad$' "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "Good::echo good" "Bad::echo bad; exit 7"
check P02b "ci-parallel: the output of the failed command is printed under its name" fail '^=== Bad \([0-9]+s, exit 7\)$' "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "Good::echo good" "Bad::echo bad; exit 7"
check P03 "ci-parallel: a hung command is stopped at the limit, with its children" fail '^TIMEOUT +[0-9]+s +Hung$' env CI_CHECK_TIMEOUT=2 "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "Quick::echo quick" "Hung::echo start; sleep 97 & sleep 98"
sleep 1
check P03b "ci-parallel: nothing of the hung command is left running" 0 '^0$' "$SHELL_BIN" -c 'pgrep -f "sleep 9[78]" | grep -c . ; exit 0'
check P04 "ci-parallel: no GNU timeout and no wait -n in the script" 0 '^0$' "$SHELL_BIN" -c 'grep -c -E "(^|[^_A-Z])timeout -k|wait -n" "'"$KIT"'/tools/ci-parallel.sh"; exit 0'

# ---------------------------------------------------------------- keyed baseline
mkdir -p "$ROOT/keyed"
cd "$ROOT/keyed" || exit 1
printf 'src/a.ts(3,5): error TS2532: Object is possibly undefined.\nsrc/a.ts(9,1): error TS2532: Object is possibly undefined.\nFound 2 errors.\n' > first.txt
printf 'src/a.ts(13,5): error TS2532: Object is possibly undefined.\nsrc/a.ts(40,1): error TS2532: Object is possibly undefined.\n' > moved.txt
printf 'src/a.ts(3,5): error TS2532: Object is possibly undefined.\nsrc/a.ts(9,1): error TS2532: Object is possibly undefined.\nsrc/b.ts(1,1): error TS7006: Parameter x implicitly has an any type.\n' > more.txt
printf 'src/a.ts(3,5): error TS2532: Object is possibly undefined.\nsrc/a.ts(9,1): error TS2532: Object is possibly undefined.\nsrc/a.ts(11,1): error TS2532: Object is possibly undefined.\n' > third.txt
printf 'src/a.ts(3,5): error TS2532: Object is possibly undefined.\n' > fewer.txt
check B01 "keyed-baseline --init: writes one line per finding" 0 'baseline.txt rewritten, 2 lines' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" baseline.txt --init --format tsc -- cat first.txt
check B01b "keyed-baseline: the file holds no line or column number" 0 '^0$' "$SHELL_BIN" -c 'grep -c -E "[0-9]+,[0-9]+" baseline.txt; exit 0'
check B02 "keyed-baseline: the same findings on other lines pass" 0 '2 findings, the same as baseline.txt' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" baseline.txt --format tsc -- cat moved.txt
check B03 "keyed-baseline: a new finding fails and is named" fail '^  src/b.ts: error TS7006' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" baseline.txt --format tsc -- cat more.txt
check B03b "keyed-baseline: a third occurrence of a finding that is in the file twice fails" fail 'findings that are not in baseline.txt' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" baseline.txt --format tsc -- cat third.txt
check B04 "keyed-baseline: a line that is no longer found (stale) fails" fail 'no longer found' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" baseline.txt --format tsc -- cat fewer.txt
check B05 "keyed-baseline: a command that fails without a finding fails, it does not read as zero findings" fail 'failed \(exit 1\) without a finding' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" baseline.txt --format tsc -- false
check B06 "keyed-baseline: a missing baseline file with no finding passes" 0 '0 findings, the same as none.txt' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" none.txt --format tsc -- true

# a second tool format: the unused-code tool (a heading per kind, then one line per finding, padded in columns)
cat > knip-first.sh <<'KNIP'
printf 'Unused files (1)\nsrc/old.ts\nUnused dependencies (1)\nleft-pad  package.json:12:5\nUnused exports (2)\napp       src/http/server.ts:1:14\nlistener  src/http/server.ts:9:14\nUnused exported types (1)\nOrder  interface  src/orders/order.ts:3:18\n'
exit 1
KNIP
cat > knip-moved.sh <<'KNIP'
printf 'Unused files (1)\nsrc/old.ts\nUnused dependencies (1)\nleft-pad  package.json:14:5\nUnused exports (2)\napp       src/http/server.ts:21:14\nlistener  src/http/server.ts:30:14\nUnused exported types (1)\nOrder  interface  src/orders/order.ts:8:18\n'
exit 1
KNIP
cat > knip-more.sh <<'KNIP'
printf 'Unused files (1)\nsrc/old.ts\nUnused dependencies (1)\nleft-pad  package.json:12:5\nUnused exports (3)\napp                 src/http/server.ts:1:14\nlistener            src/http/server.ts:9:14\naVeryLongExportName  src/http/server.ts:12:14\nUnused exported types (1)\nOrder  interface  src/orders/order.ts:3:18\n'
exit 1
KNIP
cat > knip-kind.sh <<'KNIP'
printf 'Unused files (1)\nsrc/old.ts\nUnused dependencies (1)\nleft-pad  package.json:12:5\nUnused devDependencies (1)\nleft-pad  package.json:12:5\nUnused exports (2)\napp       src/http/server.ts:1:14\nlistener  src/http/server.ts:9:14\nUnused exported types (1)\nOrder  interface  src/orders/order.ts:3:18\n'
exit 1
KNIP
cat > knip-fewer.sh <<'KNIP'
printf 'Unused exports (1)\napp  src/http/server.ts:1:14\n'
exit 1
KNIP
check B07 "keyed-baseline --format knip --init: the unused-code tool's findings are written, one line each (5), although the tool exits 1" 0 'knip.txt rewritten, 5 lines' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --init --format knip -- "$SHELL_BIN" knip-first.sh
check B07b "keyed-baseline: a line holds its heading, no count, no position and no column padding" 0 '^Unused exports: listener src/http/server.ts$' cat knip.txt
check B07c "keyed-baseline: a finding that is only a path (an unused file) is kept under its heading" 0 '^Unused files: src/old.ts$' cat knip.txt
check B08 "keyed-baseline --format knip: the same findings on other lines pass" 0 '5 findings, the same as knip.txt' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format knip -- "$SHELL_BIN" knip-moved.sh
check B08b "keyed-baseline --format knip: a new finding fails and is named, and the wider column it causes changes no other line" fail '^  Unused exports: aVeryLongExportName src/http/server.ts$' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format knip -- "$SHELL_BIN" knip-more.sh
check B08c "keyed-baseline --format knip: only that one line is new" 0 '^1$' "$SHELL_BIN" -c 'bash "'"$KIT"'/tools/keyed-baseline.sh" knip.txt --format knip -- bash knip-more.sh | grep -c "^  "; exit 0'
check B08d "keyed-baseline --format knip: the same line under another heading is another finding" fail '^  Unused devDependencies: left-pad package.json$' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format knip -- "$SHELL_BIN" knip-kind.sh
check B08e "keyed-baseline --format knip: fixed findings are stale lines" fail 'no longer found' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format knip -- "$SHELL_BIN" knip-fewer.sh
check B09 "keyed-baseline: the first format does not read the second tool's output as findings" fail 'failed \(exit 1\) without a finding in the format tsc' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format tsc -- "$SHELL_BIN" knip-first.sh
check B10 "keyed-baseline: an unknown format fails and lists the formats" fail 'no format "biome"\. The config block has: tsc knip' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format biome -- true
check B10b "keyed-baseline: no --format is a usage error" fail '^Usage: bash keyed-baseline.sh' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt -- cat first.txt
cat > knip-hints.sh <<'KNIP'
printf 'Unused files (1)\nsrc/old.ts\nUnused dependencies (1)\nleft-pad  package.json:12:5\nUnused exports (2)\napp       src/http/server.ts:1:14\nlistener  src/http/server.ts:9:14\nUnused exported types (1)\nOrder  interface  src/orders/order.ts:3:18\nConfiguration hints (2)\nknip.json  Remove from ignoreBinaries  php\nknip.json  Refine entry pattern  src/x.ts\n'
exit 1
KNIP
check B11 "keyed-baseline --format knip: the tool's Configuration hints are not findings (still 5)" 0 '5 findings, the same as knip.txt' "$SHELL_BIN" "$KIT/tools/keyed-baseline.sh" knip.txt --format knip -- "$SHELL_BIN" knip-hints.sh

# ---------------------------------------------------------------- a small project with a pipeline file of its own and no php anywhere:
# the checks.sh, the first-integration and the script-guard scenarios. Every scenario here runs without php.
ts_configure() {
    mkdir -p scripts
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-ratchets.sh" "$KIT/tools/debt-dashboard.sh" "$KIT/tools/ci-commit-messages.sh" "$KIT/tools/ci-parallel.sh" "$KIT/tools/keyed-baseline.sh" "$KIT/tools/debt-caps.json" scripts/
    cp "$KIT/ci/checks.sh" scripts/checks.sh
    fill_placeholders
    edit scripts/debt-dashboard.sh 's/^ANALYSER_SWITCHES_TARGET=.*$/ANALYSER_SWITCHES_TARGET=2/; s/^MIN_ROW_TARGETS=.*$/MIN_ROW_TARGETS=""/'
    edit scripts/ci-ratchets.sh 's/^SOURCE_PATHS=.*$/SOURCE_PATHS="src"/; s/^TEST_PATHS=.*$/TEST_PATHS="src"/; s/^TEST_SKIP_PATHS=.*$/TEST_SKIP_PATHS=""/; s/^FRONTEND_PATHS=.*$/FRONTEND_PATHS=""/; s/^UNCOUNTED_PATHS=.*$/UNCOUNTED_PATHS="eslint.config.js?"/; s/^ANALYSER_CONFIGS=.*$/ANALYSER_CONFIGS=""/; s/^ANALYSER_BASELINE=.*$/ANALYSER_BASELINE=""/; s/^TYPE_CHECKER_CONFIGS=.*$/TYPE_CHECKER_CONFIGS="tsconfig.json"/; s/^REFACTOR_CONFIG=.*$/REFACTOR_CONFIG=""/; s/^AUDIT_MANIFEST=.*$/AUDIT_MANIFEST="package.json"/; s/^(audit-ignore .*) count\? config\.audit\.ignore$/\1 count? auditIgnore/'
    cat > "$ROOT/ts-rows.txt" <<'ROWS'
    "dependency-advisories::online::cat fixtures/audit.json | json 'value metadata.vulnerabilities.total'"
    "dependencies-abandoned::online::cat fixtures/audit.json | json 'count? deprecated'"
    "dependencies-behind::shown::cat fixtures/outdated.json | json 'count .'"
    "dependencies-majors-behind::online::cat fixtures/outdated.json | json 'count .'"
ROWS
    awk -v rows="$ROOT/ts-rows.txt" '
        /^COMMAND_ROWS=\(/ { print; while ((getline line < rows) > 0) print line; skip = 1; next }
        skip && /^\)/ { skip = 0 }
        !skip' scripts/debt-dashboard.sh > scripts/debt-dashboard.tmp
    cat scripts/debt-dashboard.tmp > scripts/debt-dashboard.sh
    command rm scripts/debt-dashboard.tmp
    edit scripts/debt-dashboard.sh "s/^SOURCE_FILES=.*$/SOURCE_FILES='^src\/.*\\\\.ts$'/; s/^FRONTEND_FILES=.*$/FRONTEND_FILES=''/; s/^TEST_FILES=.*$/TEST_FILES='\\\\.test\\\\.ts$'/; s/^OPEN_CLASS=.*$/OPEN_CLASS=''/; s/^BEHAVIOR_CLASSES=.*$/BEHAVIOR_CLASSES='^src\/modules\/[^\/]+\/(actions|services|jobs|listeners|commands)\/(.*\/)?[^\/]+\\\\.ts$'/; s/^SUPPORT_CLASSES=.*$/SUPPORT_CLASSES=''/; s/^NOT_A_CLASS=.*$/NOT_A_CLASS=''/; s/^TEST_NAME_SUFFIX=.*$/TEST_NAME_SUFFIX=\".test\"/; s/^FRONTEND_LOGIC=.*$/FRONTEND_LOGIC=''/; s/^FRONTEND_COMPONENTS=.*$/FRONTEND_COMPONENTS=''/"
    edit scripts/checks.sh '/^ +"[A-Za-z ]+::/s/::<[^"]*"/::true"/; s/"Secret scan::gitleaks[^"]*"/"Secret scan::true"/'
    mkdir -p fixtures
    echo '{"metadata": {"vulnerabilities": {"total": 0}}}' > fixtures/audit.json
    echo '{}' > fixtures/outdated.json
}

build_ts_project() {
    local origin="$ROOT/ts-origin.git" work="$ROOT/ts"
    git init -q --bare -b main "$origin"
    git init -q -b main "$work"
    cd "$work" || exit 1
    git remote add origin "file://$origin"
    mkdir -p src/modules/orders/actions src/modules/orders/services .github/workflows scripts
    echo '{"name": "proof-ts", "private": true}' > package.json
    printf '{\n    "compilerOptions": {\n        "strict": true,\n        "noUncheckedIndexedAccess": true\n    }\n}\n' > tsconfig.json
    printf 'name: CI\non:\n  pull_request:\njobs:\n  deploy:\n    runs-on: ubuntu-24.04\n    steps:\n      - run: ./deploy.sh\n' > .github/workflows/ci.yml
    printf 'export const createOrder = (): number => 1;\n' > src/modules/orders/actions/create-order.ts
    printf 'import { createOrder } from "./create-order";\n\ntest("creates an order", () => {\n    expect(createOrder()).toBe(1);\n});\n' > src/modules/orders/actions/create-order.test.ts
    printf 'export class OrderService {\n    count(): number {\n        return 0;\n    }\n}\n' > src/modules/orders/services/order-service.ts
    printf 'import {\n    OrderService,\n} from "./order-service";\n\ntest("counts", () => {\n    expect(new OrderService().count()).toBe(0);\n});\n' > src/modules/orders/services/order-service.test.ts
    git add -A
    git commit -q -m "PROJ-1 chore: a small project with a pipeline of its own"
    git push -q origin main
}

ts_branch() {
    cd "$ROOT/ts" || exit 1
    git checkout -q -f "${2:-main}"
    git clean -q -fd
    git checkout -q -B "$1" "${2:-main}"
}

build_ts_project
ts_branch integrate-untagged
ts_configure
printf '  checks:\n    runs-on: ubuntu-24.04\n    steps:\n      - run: bash scripts/checks.sh\n' >> .github/workflows/ci.yml
commit "PROJ-2 ci: install the checks"
check T01 "first integration PR: an existing pipeline file changed without [ratchet: script] fails, although the script is new" fail 'Script: \.github/workflows changed\. FAILS without \[ratchet: script\]' ratchets

ts_branch integrate
ts_configure
git add -A
git reset -q -- .github
git commit -q -m "PROJ-2 ci: the tools, with their config blocks set"
printf '  checks:\n    runs-on: ubuntu-24.04\n    steps:\n      - run: bash scripts/checks.sh\n' >> .github/workflows/ci.yml
commit "PROJ-2 ci: add the checks step to the pipeline

[ratchet: script] install the kit's checks step"
check T02 "first integration PR: the same edit with the tag passes, and every row is a new row" 0 'Script: \.github/workflows changed, allowed' ratchets
check T02b "first integration PR: analyser-switches counts the two switches of tsconfig.json" 0 '^analyser-switches +up +2 +2 +\+0 +new row' ratchets
check T04 "checks.sh on the first integration PR: no copy on the base to guard with, says so, and passes" 0 'scripts/ci-ratchets.sh is not on origin/main yet' env -u CHECK_BASE CI=true GITHUB_BASE_REF=main "$SHELL_BIN" scripts/checks.sh
check T04b "checks.sh: the labels are what runs" 0 '^PASS +[0-9]+s +Guard tests$' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
git push -q origin integrate:main

ts_branch t07 integrate
edit tsconfig.json 's/"strict": true/"strict": false/'
commit "PROJ-3 chore: strict off"
check T07 "ratchet: analyser-switches fails when a tsconfig switch is set to false" fail '^analyser-switches +up +2 +1 +-1 +FAILS' ratchets

ts_branch t07b integrate
edit tsconfig.json '/noUncheckedIndexedAccess/d; s/"strict": true,/"strict": true/'
commit "PROJ-3 chore: drop a compiler flag"
check T07b "ratchet: the same when the flag is removed from tsconfig.json" fail '^analyser-switches +up +2 +1 +-1 +FAILS' ratchets

ts_branch t08 integrate
printf 'it.skip("later", () => {});\n// @ts-ignore\nexport const x = 1;\n' > src/modules/orders/actions/create-order.test.ts
commit "PROJ-3 test: a skipped test next to the source"
check T08b "ratchet: a type-checker suppression in the source is counted" fail '^ts-suppressions +down +0 +1 +\+1 +FAILS' ratchets


ts_branch t09 integrate
printf '  extra:\n    runs-on: ubuntu-24.04\n    steps:\n      - run: echo extra\n' >> .github/workflows/ci.yml
commit "PROJ-3 ci: a pipeline edit without the tag"
check T09 "checks.sh on a later pull request: the base's copy with --guard fails an untagged pipeline edit" fail 'Script: \.github/workflows changed\. FAILS' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh

ts_branch t10 integrate
check T10 "checks.sh: no base variable in CI fails and names --main" fail 'no base branch\. This is a CI run.*checks\.sh --main' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/checks.sh
check T10b "checks.sh --main: runs without a base, leaves out the commit-message check, and passes" 0 '^PASS +[0-9]+s +Guard tests$' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/checks.sh --main
check T10c "checks.sh --main: the commit-message check did not run" 0 '^0$' "$SHELL_BIN" -c 'CI=true bash scripts/checks.sh --main 2>&1 | grep -c "Commit messages"; exit 0'
check T10d "checks.sh --scheduled: the dashboard in its full run measures the rows --offline skips" 0 '^docs +docs-older-than-limit +0 +0 +ok' "$SHELL_BIN" scripts/checks.sh --scheduled
edit scripts/checks.sh 's/^DASHBOARD_MODE=""$/DASHBOARD_MODE="--final"/'
check T10e "checks.sh with DASHBOARD_MODE=--final: the scheduled run is the final dashboard" 0 '^Zero-debt dashboard \(final\)' "$SHELL_BIN" scripts/checks.sh --scheduled
git checkout -q -- scripts/checks.sh
check T16 "checks.sh --list-parts names the parts of the split pipeline" 0 '^static$' "$SHELL_BIN" scripts/checks.sh --list-parts
check T16b "checks.sh --main --part guards runs only the tasks of that part" 0 '^PASS +[0-9]+s +Guard tests$' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/checks.sh --main --part guards
check T16c "checks.sh --main --part guards: a task of another part did not run" 0 '^0$' "$SHELL_BIN" -c 'CI=true bash scripts/checks.sh --main --part guards 2>&1 | grep -c "Analyser"; exit 0'
check T16d "checks.sh --part with an unknown name fails and lists the parts" fail 'no part "nonsense"' "$SHELL_BIN" scripts/checks.sh --part nonsense
check T16e "checks.sh --part gate runs the dashboard and no task" 0 '^Zero-debt dashboard' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh --part gate
edit scripts/checks.sh 's/Type coverage[|]Lint"/Type coverage"/'
check T16f "checks.sh: a task that is in no part stops the run (no check falls out of the split)" fail 'the task "Lint" is in 0 parts' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/checks.sh --main
git checkout -q -- scripts/checks.sh
cp "$KIT/ci/prepush.sh" scripts/prepush.sh
check T16g "prepush.sh runs every part side by side" 0 '^PASS +[0-9]+s +guards$' env -u CHECK_BASE CI=true "$SHELL_BIN" scripts/prepush.sh --main
command rm scripts/prepush.sh
git remote set-url origin "file://$ROOT/gone.git"
check T11 "checks.sh: a fetch of the base that fails stops the run loudly" fail 'cannot fetch origin/main, so the script guard cannot run\. FAILS' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
git remote set-url origin "file://$ROOT/ts-origin.git"

# ---------------------------------------------------------------- the strictness rows: the refactoring tool's sets, the formatter's
# rules, the advisory-blocking package, the skip entries, and the end goals of the min rows. Every scenario here runs without php:
# the rows are grep and block rows, and the configs are the ones templates/tool-configs.md tells a project to copy.
ts_branch strict integrate
awk '/^## PHP formatter/ { want = 1 } want && /^```json$/ { inside = 1; next } inside && /^```$/ { exit } inside' "$KIT/templates/tool-configs.md" > pint.json
{
    echo '<?php'
    awk '/^## PHP refactoring tool/ { want = 1 } want && /^```php$/ { inside = 1; next } inside && /^```$/ { exit } inside' "$KIT/templates/tool-configs.md"
} > rector.php
printf '{\n    "name": "proof/strict",\n    "require-dev": {\n        "roave/security-advisories": "dev-latest"\n    }\n}\n' > composer.json
printf 'includes:\n    - vendor/larastan/larastan/extension.neon\n    - vendor/nesbot/carbon/extension.neon\n    - phar://phpstan.phar/conf/bleedingEdge.neon\nparameters:\n    level: 10\n    checkMissingOverrideMethodAttribute: true\n' > phpstan.neon
printf 'threshold: 80\n' > scripts/coverage-floor.txt
edit scripts/ci-ratchets.sh 's/^REFACTOR_CONFIG=.*$/REFACTOR_CONFIG="rector.php?"/; s/^AUDIT_MANIFEST=.*$/AUDIT_MANIFEST="composer.json"/; s/^UNCOUNTED_PATHS=.*$/UNCOUNTED_PATHS="eslint.config.js?,rector.php?"/; s/^TYPE_CHECKER_CONFIGS=.*$/TYPE_CHECKER_CONFIGS="tsconfig.json,phpstan.neon"/'
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
coverage-floor                         min  number scripts\/coverage-floor.txt  ^threshold: ([0-9]+)$/'
commit "PROJ-7 ci: the kit's formatter and refactoring configs, the advisory-blocking package and the coverage floor

[ratchet: script] the config values of the strictness rows"
git push -q origin strict:strict-main
check X01 "ratchet --head: the formatter config of templates/tool-configs.md holds the 21 rules of FORMATTER_RULES" 0 '^formatter-rules min 21$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X02 "ratchet --head: the refactoring config of templates/tool-configs.md holds the 18 sets of REFACTOR_SETS" 0 '^refactor-sets min 18$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X03 "ratchet --head: that config has no skip entry" 0 '^refactor-skip max 0$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X04 "ratchet --head: the advisory-blocking package at dev-latest counts 1" 0 '^advisory-block min 1$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X05 "ratchet --head: analyser-switches counts the bleeding-edge include and the date extension's include, and not the framework extension's (2 flags, 1 switch, 2 includes)" 0 '^analyser-switches min 5$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X06 "the new rows start at their measured values: a pull request that only adds them passes" 0 '^formatter-rules +up +0 +21 +\+21 +ok' ratchets
check X06b "the same for the coverage floor at its measured value" 0 '^coverage-floor +up +.*new row' ratchets

ts_branch x07 strict
edit pint.json 's/"final_class": true/"final_class": false/'
commit "PROJ-7 chore: switch a formatter rule off"
check X07 "ratchet: a formatter rule of the list that is switched off fails" fail '^formatter-rules +up +21 +20 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x07b strict
edit pint.json '/"mb_str_functions"/d'
commit "PROJ-7 chore: drop a formatter rule"
check X07b "ratchet: the same when the rule is removed from the config" fail '^formatter-rules +up +21 +20 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x08 strict
edit rector.php 's/codingStyle: true/codingStyle: false/'
commit "PROJ-7 chore: switch a prepared set off"
check X08 "ratchet: a prepared set of the refactoring tool that is switched off fails" fail '^refactor-sets +up +18 +17 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x08b strict
edit rector.php '/LARAVEL_IF_HELPERS/d'
commit "PROJ-7 chore: drop a framework set"
check X08b "ratchet: a framework set that is removed fails" fail '^refactor-sets +up +18 +17 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x09 strict
edit rector.php 's/^    ->withPhpSets\(\);$/    ->withPhpSets()\
    ->withSkip([\
        RemoveUnusedPrivateMethodRector::class,\
        StaticClosureRector::class => [__DIR__ . "\/app\/Legacy"],\
    ])\
    ->withSkipPath(__DIR__ . "\/app\/Generated");/'
commit "PROJ-7 chore: skip entries in the refactoring config"
check X09 "ratchet: every skip entry is counted and fails: two rules and a path in withSkip, and a withSkipPath (4)" fail '^refactor-skip +down +0 +4 +\+4 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x09b strict
printf '\n$rectorConfig->skip([\n    FinalizeClassesWithoutChildrenRector::class,\n]);\n' >> rector.php
commit "PROJ-7 chore: a skip in the old config form"
check X09b "ratchet: a skip in the old config form is counted" fail '^refactor-skip +down +0 +1 +\+1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x10 strict
edit composer.json 's/"dev-latest"/"^1.0"/'
commit "PROJ-7 chore: pin the advisory-blocking package"
check X10 "ratchet: the advisory-blocking package off dev-latest fails" fail '^advisory-block +up +1 +0 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x11 strict
edit phpstan.neon '/bleedingEdge/d'
commit "PROJ-7 chore: drop the bleeding-edge include"
check X11 "ratchet: analyser-switches fails when the bleeding-edge include is removed" fail '^analyser-switches +up +5 +4 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x12 strict
printf 'threshold: 79\n' > scripts/coverage-floor.txt
commit "PROJ-7 ci: lower the coverage floor"
check X12 "ratchet: the coverage floor fails when it falls" fail '^coverage-floor +up +80 +79 +-1 +FAILS' env CHECK_BASE=strict-main "$SHELL_BIN" scripts/ci-ratchets.sh

ts_branch x13 strict
edit scripts/debt-dashboard.sh 's/^ANALYSER_SWITCHES_TARGET=.*$/ANALYSER_SWITCHES_TARGET=5/; s/^MIN_ROW_TARGETS=.*$/MIN_ROW_TARGETS="coverage-floor:95 refactor-sets:18 formatter-rules:21 advisory-block:1"/'
commit "PROJ-7 ci: the kit's end goals of the min rows

[ratchet: script] MIN_ROW_TARGETS as the kit ships it, without the frontend entry"
check X13 "dashboard gate: a min row with an end goal shows it and is held, below the goal too" 0 '^ratchet +coverage-floor +80 +95 +held \(only goes up\)' dashboard --offline
check X13b "dashboard --final: the coverage floor below 95 fails" fail '^ratchet +coverage-floor +80 +95 +OPEN' dashboard --final --offline
check X13c "dashboard --final: that row says what to do" fail 'do this: add the missing tests and raise the committed floor to the measured value' dashboard --final --offline
check X13d "dashboard --final: the sets, the rules and the advisory-blocking package at their goals pass" fail '^ratchet +formatter-rules +21 +21 +ok' dashboard --final --offline
printf 'threshold: 94\n' > scripts/coverage-floor.txt
commit "PROJ-7 test: coverage at 94"
check X13e "dashboard --final: 94 is below the goal" fail '^ratchet +coverage-floor +94 +95 +OPEN' dashboard --final --offline
printf 'threshold: 95\n' > scripts/coverage-floor.txt
commit "PROJ-7 test: coverage at 95"
check X13f "dashboard --final: the coverage floor at 95 passes" 0 '^ratchet +coverage-floor +95 +95 +ok' dashboard --final --offline
check X13g "dashboard --final: with every min row at its goal no row is open" 0 ' 0 open, 0 not measured' dashboard --final --offline
edit pint.json 's/"strict_comparison": true/"strict_comparison": false/'
commit "PROJ-7 chore: a rule that waits for its own pull request"
check X13h "dashboard --final: a formatter rule that is still off fails the end state" fail '^ratchet +formatter-rules +20 +21 +OPEN' dashboard --final --offline
check X13i "dashboard --final: that row says what to do" fail 'do this: switch on, in FORMATTER_CONFIG, the rules of FORMATTER_RULES that are missing' dashboard --final --offline
git reset -q --hard HEAD~1
edit scripts/ci-ratchets.sh '/^coverage-floor +min/d'
commit "PROJ-7 ci: drop the coverage row"
check X13j "dashboard --final: a goal without its ratchet row is UNKNOWN, not passed" fail '^ratchet +coverage-floor +\? +95 +UNKNOWN \(no ratchet row coverage-floor' dashboard --final --offline
git reset -q --hard HEAD~1
edit scripts/debt-dashboard.sh 's/^MIN_ROW_TARGETS=.*$/MIN_ROW_TARGETS="coverage-floor:high"/'
commit "PROJ-7 ci: a goal that is no number"
check X13k "dashboard --final: a goal that is no number is UNKNOWN" fail '^ratchet +coverage-floor +95 +\? +UNKNOWN \(the number of coverage-floor in MIN_ROW_TARGETS is not a number\)' dashboard --final --offline

ts_branch x14 integrate
mkdir -p src/legacy
printf '<?php\n\nif ($a == $b || $a != $c || $a === $d || $a !== $e || $a <= $f || $a >= $g || $h = [1 => 2]) {\n    $when = new DateTime();\n    $then = new \\DateTime("now");\n    $day = Carbon::now();\n    $fine = new DateTimeImmutable();\n    $also = CarbonImmutable::now();\n    $size = strlen($a) + mb_strlen($b) + $c->strlen($d) + substr_count($e, $f);\n    $part = substr($a, 0, 2) . Str::substr($b, 0, 2) . strtolower($g);\n}\n' > src/legacy/sample.php
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
loose-comparisons                      max  grep   @SOURCE_PATHS,@TEST_PATHS  (^|[^=!<>])(==|!=)([^=]|$)\
mutable-dates                          max  grep   @SOURCE_PATHS,@TEST_PATHS  (^|[^A-Za-z0-9_])(DateTime|Carbon)(::|[(])\
byte-string-functions                  max  grep   @SOURCE_PATHS,@TEST_PATHS  (^|[^A-Za-z0-9_>:$\\])(strlen|substr|strpos|strrpos|stripos|strripos|strstr|stristr|strrchr|substr_count|str_split|strtolower|strtoupper)[(]/'
commit "PROJ-7 ci: the three rows that count a formatter rule's sites before the rule goes on

[ratchet: script] rows of tools/README.md, Adding a row"
check X16 "ratchet --head: loose-comparisons counts == and != (2), and not ===, !==, <=, >= or =>" 0 '^loose-comparisons max 2$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X16b "ratchet --head: mutable-dates counts new DateTime, new \\DateTime and Carbon:: (3), and not the immutable classes" 0 '^mutable-dates max 3$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
check X16c "ratchet --head: byte-string-functions counts strlen, substr_count, substr and strtolower (4), and not mb_strlen, a method or a static call" 0 '^byte-string-functions max 4$' "$SHELL_BIN" scripts/ci-ratchets.sh --head

ts_branch x14b integrate
check X14 "checks.sh: the refactoring dry run is a line of the checks step" 0 '^PASS +[0-9]+s +Refactor$' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
check X14b "checks.sh: so is the type coverage" 0 '^PASS +[0-9]+s +Type coverage$' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
check X14c "the kit's checks.sh ships both lines as placeholders, so a project cannot leave them out unnoticed" 0 '^2$' "$SHELL_BIN" -c 'grep -c -E "^ +.(Refactor|Type coverage)::<" "$0"; exit 0' "$KIT/ci/checks.sh"
check X15 "the kit's dashboard ships the end goals: coverage 95, 18 sets, 21 rules, the advisory-blocking package" 0 '^MIN_ROW_TARGETS="coverage-floor:95 frontend-logic-coverage-floor:95 refactor-sets:18 formatter-rules:21 advisory-block:1"$' grep -E '^MIN_ROW_TARGETS=' "$KIT/tools/debt-dashboard.sh"

# A layout move (step 5). The tree is not in the kit's layout yet, so the expressions of the test rows name today's
# folder as well as the kit's: the number signed in step 1 is the real one, and the move changes no row.
ts_branch legacy integrate
mkdir -p src/orders
printf 'export const syncOrders = (): number => 5;\n' > src/orders/sync-orders.ts
edit scripts/debt-dashboard.sh "s/^BEHAVIOR_CLASSES='(.*)'$/BEHAVIOR_CLASSES='\\1|^src\/orders\/[^\/]+\\\\.ts$'/"
edit scripts/debt-caps.json 's/"(classes-no-test-names|classes-without-own-test)": 0/"\1": 1/'
printf 'threshold: 60\n' > scripts/mutation-floor.txt
edit scripts/ci-ratchets.sh 's/^(todo +max.*)$/\1\
mutation-floor                         min  number scripts\/mutation-floor.txt  ^threshold: ([0-9]+)$/'
commit "PROJ-5 chore: a tree that is not in the kit's layout yet, measured with today's folder in the expression"
git push -q origin legacy:legacy-main
check T13 "dashboard: a behavior file in today's folder is counted through the alternative in BEHAVIOR_CLASSES (1, at its cap)" 0 '^tests +classes-without-own-test +1 +1 +ok' dashboard --offline --details
ts_branch t13 legacy
mkdir -p src/modules/orders/services
git mv src/orders/sync-orders.ts src/modules/orders/services/sync-orders.ts
commit "PROJ-5 refactor: move the file into its module folder"
check T13b "a move into a behavior folder, with the old folder in the expression: the row is unchanged" 0 '^tests +classes-without-own-test +1 +1 +ok' dashboard --offline --details
check T13c "the same for the row of the classes no test names" 0 '^tests +classes-no-test-names +1 +1 +ok' dashboard --offline --details
check T13d "that move as a pull request: no cap moves and checks.sh passes" 0 '^cap-classes-without-own-test +down +1 +1 +\+0 +ok' env CHECK_BASE=legacy-main "$SHELL_BIN" scripts/checks.sh
edit scripts/debt-dashboard.sh "s/^BEHAVIOR_CLASSES=.*$/BEHAVIOR_CLASSES='^src\/modules\/[^\/]+\/(actions|services|jobs|listeners|commands)\/(.*\/)?[^\/]+\\\\.ts$'/"
commit "PROJ-5 ci: the old-folder alternative goes with the layout list

[ratchet: script] the layout list is empty: BEHAVIOR_CLASSES names the kit's folders only"
check T13e "removing the old-folder alternative after the move, with the script tag: the row is still unchanged" 0 '^tests +classes-without-own-test +1 +1 +ok' dashboard --offline --details
check T13f "and checks.sh passes that pull request" 0 'Script: scripts/debt-dashboard.sh changed, allowed' env CHECK_BASE=legacy-main "$SHELL_BIN" scripts/checks.sh
ts_branch plain integrate
mkdir -p src/orders
printf 'export const syncOrders = (): number => 5;\n' > src/orders/sync-orders.ts
commit "PROJ-5 chore: the same tree measured with the kit's folders only"
git push -q origin plain:plain-main
check T13g "without the alternative the file in today's folder is not counted (0): the number signed in step 1 would be wrong" 0 '^tests +classes-without-own-test +0 +0 +ok' dashboard --offline --details
ts_branch t13h plain
mkdir -p src/modules/orders/services
git mv src/orders/sync-orders.ts src/modules/orders/services/sync-orders.ts
commit "PROJ-5 refactor: move the file into its module folder"
check T13h "and the move then raises the row above its cap of 0, in a pull request that is a pure move" fail '^tests +classes-without-own-test +1 +0 +OPEN' env CHECK_BASE=plain-main "$SHELL_BIN" scripts/checks.sh

ts_branch t14 legacy
printf 'threshold: 50\n' > scripts/mutation-floor.txt
commit "PROJ-5 ci: lower the mutation floor"
check T14 "ratchet, no php: the mutation floor in scripts/mutation-floor.txt fails when it falls" fail '^mutation-floor +up +60 +50 +-10 +FAILS' env CHECK_BASE=legacy-main "$SHELL_BIN" scripts/ci-ratchets.sh

# The docs row: decision records and the archive are history. The scheduled run must not turn red on them.
ts_branch t15 integrate
mkdir -p docs/decisions docs/archive
echo "# One backend pattern" > docs/decisions/0001-one-backend-pattern.md
echo "# Zero-debt plan" > docs/archive/zero-debt-plan.md
echo "# Guide" > docs/guide.md
git add -A
LONG_AGO="@$(($(date +%s) - 200 * 86400)) +0000"
GIT_AUTHOR_DATE="$LONG_AGO" GIT_COMMITTER_DATE="$LONG_AGO" git commit -q -m "PROJ-6 docs: a decision record, an archived plan and a guide, 200 days ago"
check T15 "scheduled run: of three docs last changed 200 days ago only the living one is counted (DOCS_PATHS excludes docs/decisions and docs/archive)" fail '^docs +docs-older-than-limit +1 +0 +OPEN' "$SHELL_BIN" scripts/checks.sh --scheduled
check T15b "the details name the living doc" fail '^docs/guide.md$' "$SHELL_BIN" scripts/checks.sh --scheduled
check T15c "and neither the decision record nor the archived plan" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/checks.sh --scheduled 2>&1 | grep -c -E "^docs/(decisions|archive)/"; exit 0'
check T15d "the open row says what to do with a living doc" fail 'do this: update each doc, move it to the archive folder or delete it' "$SHELL_BIN" scripts/checks.sh --scheduled
git mv docs/guide.md docs/archive/guide.md
commit "PROJ-6 docs: archive the old guide"
check T15e "a doc moved to the archive folder is no longer counted: the scheduled run passes with the old history files" 0 '^docs +docs-older-than-limit +0 +0 +ok' "$SHELL_BIN" scripts/checks.sh --scheduled
edit scripts/debt-dashboard.sh 's/^DOCS_PATHS=.*$/DOCS_PATHS="docs"/'
check T15f "DOCS_PATHS without the excludes counts the two history files that are still 200 days old (the value is a list of git pathspecs)" fail '^docs +docs-older-than-limit +2 +0 +OPEN' "$SHELL_BIN" scripts/checks.sh --scheduled
git checkout -q -- scripts/debt-dashboard.sh

# Bitbucket: a pull-request pipeline merges the destination into the checkout first, so HEAD is that merge.
# The branch here was cut before the tools landed on the main branch.
cd "$ROOT/ts" || exit 1
git checkout -q -f main
git clean -q -fd
git checkout -q -B bb-old main
printf 'export const refundOrder = (): number => 3;\n' > src/modules/orders/actions/refund-order.ts
printf 'import { refundOrder } from "./refund-order";\n\ntest("refunds", () => {\n    expect(refundOrder()).toBe(3);\n});\n' > src/modules/orders/actions/refund-order.test.ts
commit "PROJ-4 feat: a branch cut before the tools landed"
SOURCE_COMMIT=$(git rev-parse HEAD)
git fetch -q origin "+refs/heads/main:refs/remotes/origin/main"
git checkout -q --detach bb-old
git merge -q --no-ff -m "Merge main into bb-old" origin/main
check M01 "checks.sh on Bitbucket's merge-first checkout of a branch cut before the tools landed: HEAD (the merge) is checked, and it passes" 0 '^PASS +[0-9]+s +Commit messages$' env -u CHECK_BASE -u CHECK_HEAD CI=true BITBUCKET_PR_DESTINATION_BRANCH=main BITBUCKET_BRANCH=bb-old BITBUCKET_COMMIT="$SOURCE_COMMIT" "$SHELL_BIN" scripts/checks.sh
check M01b "the same run: the ratchets compare the merge with the base" 0 "^Ratchets against origin/main \(merge base [0-9a-f]+, head $(git rev-parse --short=9 HEAD)\)" env -u CHECK_BASE -u CHECK_HEAD CI=true BITBUCKET_PR_DESTINATION_BRANCH=main BITBUCKET_BRANCH=bb-old BITBUCKET_COMMIT="$SOURCE_COMMIT" "$SHELL_BIN" scripts/checks.sh
check M02 "with CHECK_HEAD set to the source commit (the old skeleton line) the run fails, and the message names the script in the repository, not a temporary file" fail '^Ratchets: scripts/ci-ratchets.sh is not a committed file of this repository at the head \([0-9a-f]+\): commit it; on a branch that was cut before the scripts landed, merge the base branch first' env -u CHECK_BASE CI=true BITBUCKET_PR_DESTINATION_BRANCH=main BITBUCKET_BRANCH=bb-old CHECK_HEAD="$SOURCE_COMMIT" "$SHELL_BIN" scripts/checks.sh
check M02b "that message holds no temporary path" 0 '^0$' "$SHELL_BIN" -c 'CI=true BITBUCKET_PR_DESTINATION_BRANCH=main CHECK_HEAD='"$SOURCE_COMMIT"' bash scripts/checks.sh 2>&1 | grep -c -E "/tmp|/var/folders"; exit 0'
git checkout -q -f bb-old
printf 'export const voidOrder = (): number => 4; // TODO a real number\n' > src/modules/orders/actions/void-order.ts
printf 'import { voidOrder } from "./void-order";\n\ntest("voids", () => {\n    expect(voidOrder()).toBe(4);\n});\n' > src/modules/orders/actions/void-order.test.ts
commit "PROJ-4 feat: the old branch adds a counted line"
git checkout -q --detach bb-old
git merge -q --no-ff -m "Merge main into bb-old" origin/main
check M03 "merge-first checkout: debt the old branch adds is counted against the base and fails by name" fail '^todo +down +0 +1 +\+1 +FAILS' env -u CHECK_BASE -u CHECK_HEAD CI=true BITBUCKET_PR_DESTINATION_BRANCH=main BITBUCKET_BRANCH=bb-old "$SHELL_BIN" scripts/checks.sh
git checkout -q -f bb-old
check M04 "the same branch checked out as it is (a host that does not merge first): there is no scripts/checks.sh to run until the base is merged" fail 'No such file or directory' env -u CHECK_BASE CI=true GITHUB_BASE_REF=main "$SHELL_BIN" scripts/checks.sh

# the placeholder check of checks.sh, and the coverage line
ts_branch k02 integrate
edit scripts/checks.sh 's/^(    "Guard tests::true")$/\1\
    "Build::npm run build -- <build flags of the project>"/'
check K02 "checks.sh refuses a placeholder that is not at the start of a command" fail 'still holds placeholders' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
check K02b "checks.sh names the line" fail '<build flags of the project>' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
git checkout -q -- scripts/checks.sh
edit scripts/checks.sh 's/^(DASHBOARD_MODE="")$/\1\
# a note of the project with <angle brackets> in a comment/'
check K03 "checks.sh: angle brackets in a comment line are no placeholder" 0 '^0$' "$SHELL_BIN" -c 'CHECK_BASE=main bash scripts/checks.sh 2>&1 | grep -c "still holds placeholders"; exit 0'
git checkout -q -- scripts/checks.sh
edit scripts/kit-config.sh 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="<engine:major of production>"/'
check K04 "checks.sh refuses a placeholder left in kit-config.sh and names the file and the line" fail '^kit-config.sh:[0-9]+:DATABASE_IMAGE="<engine:major of production>"$' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
check K04b "the same in the scheduled run" fail 'still holds placeholders' "$SHELL_BIN" scripts/checks.sh --scheduled
check K04c "and on the main branch: every run stops" fail 'still holds placeholders' env CI=true "$SHELL_BIN" scripts/checks.sh --main
git checkout -q -- scripts/kit-config.sh
edit scripts/ci-commit-messages.sh "s/^TICKET=.*$/TICKET='<ticket pattern>'/"
check K05 "checks.sh refuses the placeholder ticket pattern in the config block of ci-commit-messages.sh" fail "^ci-commit-messages.sh:[0-9]+:TICKET='<ticket pattern>'$" env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
git checkout -q -- scripts/ci-commit-messages.sh
check K05b "with every value set, the <TICKET> and <type> of the failure text outside the config block are no placeholders" 0 '^0$' "$SHELL_BIN" -c 'CHECK_BASE=main bash scripts/checks.sh 2>&1 | grep -c "still holds placeholders"; exit 0'
printf 'threshold: 80\n' > scripts/coverage-floor.txt
printf '<?xml version="1.0"?>\n<coverage generated="1">\n  <project timestamp="1">\n    <file name="a.php">\n      <metrics loc="10" statements="10" coveredstatements="1"/>\n    </file>\n    <metrics files="1" loc="200" statements="200" coveredstatements="170" elements="200" coveredelements="170"/>\n  </project>\n</coverage>\n' > "$ROOT/clover.xml"
check V01 "checks.sh --coverage: the project metrics of the clover report (85%) above the committed floor (80) pass, and the run says what to raise the floor to" 0 'raise the floor in scripts/coverage-floor.txt to 85' "$SHELL_BIN" scripts/checks.sh --coverage "$ROOT/clover.xml"
printf 'threshold: 86\n' > scripts/coverage-floor.txt
check V02 "checks.sh --coverage: below the floor fails and names both numbers" fail 'Coverage: 85% of the lines \(170 of 200\), below the floor of 86%' "$SHELL_BIN" scripts/checks.sh --coverage "$ROOT/clover.xml"
check V03 "checks.sh --coverage: a report that is missing fails, it does not pass" fail 'cannot read the clover report' "$SHELL_BIN" scripts/checks.sh --coverage "$ROOT/none.xml"
echo '<coverage/>' > "$ROOT/empty.xml"
check V03b "checks.sh --coverage: a report without metrics fails" fail 'holds no project metrics' "$SHELL_BIN" scripts/checks.sh --coverage "$ROOT/empty.xml"
command rm scripts/coverage-floor.txt
check V04 "checks.sh --coverage: without the committed floor it fails" fail 'holds no line "threshold: N"' "$SHELL_BIN" scripts/checks.sh --coverage "$ROOT/clover.xml"

# The audit gate: time-boxed exceptions for a fresh advisory.
GATE_DIR="$ROOT/gate"
mkdir -p "$GATE_DIR/scripts"
cp "$KIT/tools/audit-gate.sh" "$GATE_DIR/scripts/audit-gate.sh"
AUDIT_TWO='{"advisories": {"a/a": [{"advisoryId": "PKSA-1111"}], "b/b": [{"advisoryId": "PKSA-2222"}]}}'
printf '%s\n' '{"exceptions": [' '{"id": "PKSA-1111", "reason": "not reachable: the route is not mounted", "added": "2026-11-17", "expires": "2026-11-24"}' ']}' > "$GATE_DIR/scripts/audit-exceptions.json"
gate() { (cd "$GATE_DIR" && printf '%s' "$1" | env AUDIT_GATE_TODAY="$2" "$SHELL_BIN" scripts/audit-gate.sh); }
check G01 "audit gate: one advisory covered, one not, counts 1 and fails" fail '^1$' gate "$AUDIT_TWO" 2026-11-18
check G02 "audit gate: the covered advisory is named as covered" fail 'PKSA-1111 is covered by an exception' gate "$AUDIT_TWO" 2026-11-18
check G03 "audit gate: no advisory at all passes with 0" 0 '^0$' gate '{"advisories": []}' 2026-11-18
check G04 "audit gate: on the end date the exception still holds" fail '^1$' gate "$AUDIT_TWO" 2026-11-24
check G05 "audit gate: the day after the end date it is expired, and the advisory is open again" fail 'PKSA-1111: the exception expired on 2026-11-24' gate "$AUDIT_TWO" 2026-11-25
check G06 "audit gate: an empty input is a failure, not a green audit" fail 'printed no JSON' gate '' 2026-11-18
printf '%s\n' '{"exceptions": [' '{"id": "PKSA-1111", "reason": "r", "added": "2026-11-17", "expires": "2026-11-25"}' ']}' > "$GATE_DIR/scripts/audit-exceptions.json"
check G07 "audit gate: eight days from added to expires is refused" fail 'lasts 8 days, at most 7' gate '{"advisories": []}' 2026-11-18
printf '%s\n' '{"exceptions": [' '{"id": "PKSA-1111", "reason": "", "added": "2026-11-17", "expires": "2026-11-20"}' ']}' > "$GATE_DIR/scripts/audit-exceptions.json"
check G08 "audit gate: an entry without a reason is refused" fail 'lacks id, reason, added or expires' gate '{"advisories": []}' 2026-11-18
printf '%s\n' '{"exceptions": [' '{"id": "PKSA-1111", "reason": "r", "added": "2026-11-17", "expires": "2026-11-20"},' '{"id": "PKSA-1111", "reason": "r", "added": "2026-11-17", "expires": "2026-11-20"}' ']}' > "$GATE_DIR/scripts/audit-exceptions.json"
check G09 "audit gate: the same id twice is refused" fail 'listed twice' gate '{"advisories": []}' 2026-11-18
printf '%s\n' '{"exceptions": [' '{"id": "PKSA-1111", "reason": "r", "added": "2026-11-19", "expires": "2026-11-20"}' ']}' > "$GATE_DIR/scripts/audit-exceptions.json"
check G10 "audit gate: an added date in the future is refused" fail 'is in the future' gate '{"advisories": []}' 2026-11-18
printf '%s\n' '{"exceptions": [' '{"id": "PKSA-1111", "reason": "r", "added": "2026-02-30", "expires": "2026-03-02"}' ']}' > "$GATE_DIR/scripts/audit-exceptions.json"
check G11 "audit gate: a date that does not exist is refused" fail 'must be dates' gate '{"advisories": []}' 2026-11-18
command rm "$GATE_DIR/scripts/audit-exceptions.json"
check G12 "audit gate: without an exceptions file every advisory is open" fail '^2$' gate "$AUDIT_TWO" 2026-11-18

# mutation.sh: the floor file is read, the whole-tree and the changed-files forms call the tool with it
MUT_DIR="$ROOT/mutation"
mkdir -p "$MUT_DIR/scripts" "$MUT_DIR/app" "$MUT_DIR/vendor/bin"
cp "$KIT/tools/mutation.sh" "$KIT/tools/kit-config.sh" "$MUT_DIR/scripts/"
sed -i.bak 's/<pest, infection or stryker>/pest/; s/<source folders>/app/' "$MUT_DIR/scripts/mutation.sh"
printf 'threshold: 70\n' > "$MUT_DIR/scripts/mutation-floor.txt"
printf '#!/bin/sh\necho "pest $*"\n' > "$MUT_DIR/vendor/bin/pest"
chmod +x "$MUT_DIR/vendor/bin/pest"
(
    cd "$MUT_DIR" && git init -q -b main && echo a > app/a.php && git add -A && git commit -q -m "PROJ-1 chore: base" &&
        git checkout -q -b change && echo b > app/b.php && git add -A && git commit -q -m "PROJ-1 chore: change"
) > /dev/null 2>&1
check MU01 "mutation.sh: the whole tree runs the tool with the committed floor" 0 '^pest --mutate --everything --parallel --processes=4 --min=70$' "$SHELL_BIN" -c "cd '$MUT_DIR' && bash scripts/mutation.sh"
check MU02 "mutation.sh --changed: only the files the branch changes, with the same floor" 0 '^pest --mutate --parallel --processes=4 --path=app/b\.php --min=70$' "$SHELL_BIN" -c "cd '$MUT_DIR' && CHECK_BASE=main bash scripts/mutation.sh --changed"
check MU03 "mutation.sh: a floor file without a threshold line fails" fail 'holds no line "threshold: N"' "$SHELL_BIN" -c "cd '$MUT_DIR' && echo nothing > scripts/mutation-floor.txt && bash scripts/mutation.sh"
check MU04 "mutation.sh: the shipped placeholders stop the run" fail 'still holds a placeholder' "$SHELL_BIN" -c "cd '$MUT_DIR' && cp '$KIT/tools/mutation.sh' scripts/mutation.sh && bash scripts/mutation.sh"

# move-ts.mjs: files move from a map, specifiers follow, a second run changes nothing, --verify and --reverse work
MV_DIR="$ROOT/move-ts"
mkdir -p "$MV_DIR/src/a" "$MV_DIR/src/b" "$MV_DIR/tests"
(
    cd "$MV_DIR" && git init -q -b main && echo 'export const x = 1;' > src/a/x.ts &&
        printf 'import { x } from "./x";\nimport { y } from "../b/y";\nexport const w = x + y;\n' > src/a/w.ts &&
        echo 'export const y = 2;' > src/b/y.ts && printf 'import { w } from "@/a/w";\nvi.mock("../src/a/x");\n' > tests/t.test.ts &&
        git add -A && git commit -q -m "PROJ-1 chore: base" && printf 'src/a/x.ts\tsrc/c/x.ts\nsrc/a/w.ts#w\tsrc/d/e/w.ts\n' > moves.tsv
) > /dev/null 2>&1
check MV01 "move-ts: files move and the specifiers of the moved file and of its importers follow" 0 'Move: 2 map lines applied' "$SHELL_BIN" -c "cd '$MV_DIR' && node '$KIT/tools/move-ts.mjs' moves.tsv --roots src,tests"
check MV02 "move-ts: a relative import of a moved file is recomputed from its new folder" 0 'from "../../c/x";' cat "$MV_DIR/src/d/e/w.ts"
check MV03 "move-ts: an alias import and a mock path follow the move" 0 '^import \{ w \} from "@/d/e/w";$' cat "$MV_DIR/tests/t.test.ts"
check MV04 "move-ts --verify: nothing names an old path" 0 'no specifier names an old path' "$SHELL_BIN" -c "cd '$MV_DIR' && node '$KIT/tools/move-ts.mjs' moves.tsv --roots src,tests --verify"
check MV05 "move-ts: a second run changes no file" 0 '^$' "$SHELL_BIN" -c "cd '$MV_DIR' && git add -A && node '$KIT/tools/move-ts.mjs' moves.tsv --roots src,tests > /dev/null && git diff --stat"
check MV06 "move-ts --reverse: the same tool takes the group back out" 0 'from "\./x";' "$SHELL_BIN" -c "cd '$MV_DIR' && node '$KIT/tools/move-ts.mjs' moves.tsv --reverse --roots src,tests > /dev/null && cat src/a/w.ts"
check MV07 "move-ts --verify after the reverse move with the forward map fails" fail 'still exists|missing' "$SHELL_BIN" -c "cd '$MV_DIR' && node '$KIT/tools/move-ts.mjs' moves.tsv --roots src,tests --verify"

# map-normalise.sh: the names of a moved stack map back to the old names, longest first, doubled backslashes too
printf 'App\\Old\\Foo\tApp\\New\\Foo\nApp\\Old\\FooBar\tApp\\New\\FooBar\n' > "$ROOT/names.tsv"
check NM01 "map-normalise: the longest name goes first, so FooBar does not become OldBar" 0 '^x App\\Old\\FooBar y App\\Old\\Foo$' "$SHELL_BIN" -c "printf 'x App\\\\New\\\\FooBar y App\\\\New\\\\Foo\n' | bash '$KIT/tools/map-normalise.sh' '$ROOT/names.tsv'"
check NM02 "map-normalise --forward maps old names to new ones" 0 '^App\\New\\Foo$' "$SHELL_BIN" -c "printf 'App\\\\Old\\\\Foo\n' | bash '$KIT/tools/map-normalise.sh' '$ROOT/names.tsv' --forward"

[ ! -f "$KIT/ci/checks.sh" ] || {
    branch k01
    cp "$KIT/ci/checks.sh" scripts/checks.sh
    commit "PROJ-2 ci: the one command"
    check K01 "ci/checks.sh with its placeholders still in it refuses to run" fail 'still holds placeholders' env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh
}

fi

# Scenario files of tools/proof/scenarios.d/ run here with the helpers above (check, check_php, branch, commit, edit, build_fixture).
# Each file builds its own scratch repositories under "$ROOT" and leaves the working directory as it found it. A file whose head
# says "# proof-needs: main" also uses the fixtures and helpers of this file (the gate, the move-ts project, the branches of work).
# Under PROOF_ONLY only the files that hold a selected scenario are run.
for scenario_file in "$HERE"/scenarios.d/*.sh; do
    [ -f "$scenario_file" ] || continue
    [ -z "${PROOF_ONLY:-}" ] || case "$SELECTED_FILES" in *" $(basename "$scenario_file") "*) ;; *) continue ;; esac
    START_DIR=$(pwd)
    # shellcheck disable=SC1090
    . "$scenario_file"
    cd "$START_DIR" || exit 1
done

echo
echo "bash under test: $("$SHELL_BIN" -c 'echo $BASH_VERSION')"
column -t -s '|' "$TABLE" 2> /dev/null || cat "$TABLE"
echo
echo "$PASSED as expected, $FAILED wrong, $SKIPPED skipped (needs php). Full output: $LOG"
if [ -n "${PROOF_ONLY:-}" ] && [ "$((PASSED + FAILED + SKIPPED))" -eq 0 ]; then
    echo "Proof: PROOF_ONLY=$PROOF_ONLY selected scenarios by id but none of them ran: that is no green run." >&2
    exit 64
fi
[ "$FAILED" -eq 0 ]
