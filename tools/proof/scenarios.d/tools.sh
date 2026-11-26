# Scenarios TX*: the defects the audit of the tools found (findings B-01 to B-27 and D-026, E-003). Sourced by proof.sh with its
# helpers in scope (check, check_php, branch, commit, edit, big_file, dashboard, commits, ROOT, KIT, SHELL_BIN).
# Each scenario builds its own scratch repository under "$ROOT" or uses a branch of the fixture; the cwd is left as it was found.

TX_CWD="$PWD"
TX="$ROOT/tx"
mkdir -p "$TX"

# tx_gate <audit text>: the audit gate on standard input, with an exceptions file that does not exist unless AUDIT_EXCEPTIONS_FILE is set
tx_gate() {
    printf '%s' "$1" | bash "$KIT/tools/audit-gate.sh"
}

# ---------------------------------------------------------------- audit-gate.sh (B-01 to B-04)
GOOD_AUDIT='{"advisories":{"a/b":[{"advisoryId":"PKSA-1","title":"x"}]},"abandoned":[]}'
export AUDIT_EXCEPTIONS_FILE="$TX/no-such-exceptions.json"
check TX01 "audit-gate: a truncated audit document fails, it is not zero advisories" fail 'JSON is incomplete|cannot be parsed' tx_gate '{"advisories":{"a/b":[{"advisoryId":"PKS'
check TX02 "audit-gate: a JSON error object without the advisories key fails" fail 'no key matching' tx_gate '{"error":"boom"}'
check TX03 "audit-gate: an empty object fails" fail 'no key matching' tx_gate '{}'
check TX04 "audit-gate: an audit with no advisory passes (control)" 0 '^0$' tx_gate '{"advisories":[],"abandoned":[]}'
check TX05 "audit-gate: an audit with one advisory fails and counts one (control)" fail '^1$' "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2" 2> /dev/null' _ "$GOOD_AUDIT" "$KIT/tools/audit-gate.sh"
check TX06 "audit-gate: the same advisory under two packages counts once" fail '^1$' "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2" 2> /dev/null' _ '{"advisories":{"a/b":[{"advisoryId":"PKSA-1"}],"c/d":[{"advisoryId":"PKSA-1"}]}}' "$KIT/tools/audit-gate.sh"

mkdir -p "$TX/date-shim"
printf '#!/bin/sh\ncase " $* " in *" -u "*) echo 2026-11-26 ;; *) echo 2026-11-27 ;; esac\n' > "$TX/date-shim/date"
chmod +x "$TX/date-shim/date"
printf '{"exceptions": [\n{"id": "PKSA-9", "reason": "r", "added": "2026-11-27", "expires": "2026-11-30"}\n]}\n' > "$TX/exc-future.json"
printf '{"exceptions": [\n{"id": "PKSA-9", "reason": "r", "added": "2026-11-26", "expires": "2026-11-30"}\n]}\n' > "$TX/exc-today.json"
NINE='{"advisories":{"a/b":[{"advisoryId":"PKSA-9"}]}}'
check TX07 "audit-gate: today is the UTC date: an added date that is ahead of UTC (a developer east of UTC) is in the future" fail 'added 2026-11-27 is in the future' env "PATH=$TX/date-shim:$PATH" "AUDIT_EXCEPTIONS_FILE=$TX/exc-future.json" "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2"' _ "$NINE" "$KIT/tools/audit-gate.sh"
check TX07b "audit-gate: an added date of the UTC day passes (control)" 0 'covered by an exception' env "PATH=$TX/date-shim:$PATH" "AUDIT_EXCEPTIONS_FILE=$TX/exc-today.json" "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2"' _ "$NINE" "$KIT/tools/audit-gate.sh"

TWO='{"advisories":{"a/b":[{"advisoryId":"PKSA-1"},{"advisoryId":"PKSA-2"}]}}'
printf '{"exceptions": [\n{"id": "PKSA-1 PKSA-2", "reason": "r", "added": "2026-11-26", "expires": "2026-11-30"}\n]}\n' > "$TX/exc-space.json"
printf '{"exceptions": [\n{"id": "PKSA-1", "reason": "   ", "added": "2026-11-26", "expires": "2026-11-30"}\n]}\n' > "$TX/exc-blank.json"
printf '{"exceptions": [\n{"id": "PKSA-1", "reason": "r", "added": "2026-11-26", "expires": "2026-11-30"}, {"id": "PKSA-2", "reason": "r", "added": "2026-11-26", "expires": "2026-11-30"}\n]}\n' > "$TX/exc-two-on-a-line.json"
check TX08 "audit-gate: an id with a space does not cover two advisories" fail 'holds a space' env AUDIT_GATE_TODAY=2026-11-26 "AUDIT_EXCEPTIONS_FILE=$TX/exc-space.json" "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2"' _ "$TWO" "$KIT/tools/audit-gate.sh"
check TX09 "audit-gate: a reason of spaces is no reason" fail 'lacks id, reason' env AUDIT_GATE_TODAY=2026-11-26 "AUDIT_EXCEPTIONS_FILE=$TX/exc-blank.json" "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2"' _ "$TWO" "$KIT/tools/audit-gate.sh"
check TX10 "audit-gate: two entries on one line are a problem, not a silent loss of the first" fail 'more than one entry' env AUDIT_GATE_TODAY=2026-11-26 "AUDIT_EXCEPTIONS_FILE=$TX/exc-two-on-a-line.json" "$SHELL_BIN" -c 'printf "%s" "$1" | bash "$2"' _ "$TWO" "$KIT/tools/audit-gate.sh"
unset AUDIT_EXCEPTIONS_FILE

# ---------------------------------------------------------------- debt-dashboard.sh (B-05 to B-09, E-003)
branch tx-dash
edit scripts/debt-dashboard.sh "s/^OPEN_CLASS=.*$/OPEN_CLASS='^(class'/"
check TX11 "dashboard: an invalid OPEN_CLASS stops the run, the row does not read 0" fail 'OPEN_CLASS in the config block is not a valid extended regular expression' dashboard --offline
git checkout -q -- scripts/debt-dashboard.sh
edit scripts/debt-dashboard.sh "s/^NOT_A_CLASS=.*$/NOT_A_CLASS='^(abstract'/"
check TX12 "dashboard: an invalid NOT_A_CLASS stops the run" fail 'NOT_A_CLASS in the config block is not a valid' dashboard --offline
git checkout -q -- scripts/debt-dashboard.sh
edit scripts/debt-dashboard.sh "s/^SOURCE_FILES=.*$/SOURCE_FILES='^(app'/"
check TX13 "dashboard: an invalid SOURCE_FILES stops the run" fail 'SOURCE_FILES in the config block is not a valid' dashboard --offline
git checkout -q -- scripts/debt-dashboard.sh

branch tx-nonewline
big_file 400 | awk '{ printf "%s%s", sep, $0; sep = "\n" }' > app/Services/NoNewline.php
git add app/Services/NoNewline.php
check_php TX14 "dashboard: a file of 401 lines without a final newline is over the limit (wc -l says 400)" fail '^size +source-files-over-limit +2 +1 +OPEN' dashboard --offline

branch tx-docs
edit scripts/debt-dashboard.sh 's/^DOCS_PATHS=.*$/DOCS_PATHS=""/; s/^DOCS_MAX_AGE_DAYS=.*$/DOCS_MAX_AGE_DAYS=0/'
check_php TX15 "dashboard: an empty DOCS_PATHS counts no doc (it listed every tracked file)" 0 '^docs +docs-older-than-limit +0 +0 +ok' dashboard
git checkout -q -- scripts/debt-dashboard.sh

branch tx-rows
printf '1\n2\n' > fixtures/two.txt
edit scripts/debt-dashboard.sh "s#cat fixtures/majors.json \| json 'count locked'#cat fixtures/two.txt#"
check_php TX16 "dashboard: a command row that prints two lines is UNKNOWN, not 12" fail '^command +dependencies-majors-behind +\? +[0-9?]+ +UNKNOWN' dashboard --offline
git checkout -q -- scripts/debt-dashboard.sh
command rm -f fixtures/two.txt

branch tx-lowered
check_php TX17 "dashboard --final: a min goal below the kit's number (a hosted formatter config) passes and says so" 0 '^ratchet +coverage-floor +80 +80 +ok \(goal lowered: the kit holds 95; needs a decision row' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --final --offline; exit 0'

# A shallow clone: --final must not skip docs-older-than-limit without a signal; the gate says why it skips.
command rm -rf "$TX/shallow"
git clone -q --depth 1 "file://$ROOT/origin.git" "$TX/shallow" > /dev/null 2>&1
check_php TX18 "dashboard --final in a shallow clone: docs-older-than-limit is UNKNOWN, with the reason" fail '^docs +docs-older-than-limit +\? +\? +UNKNOWN \(shallow clone' "$SHELL_BIN" -c "cd '$TX/shallow' && bash scripts/debt-dashboard.sh --final"
check_php TX19 "dashboard gate in a shallow clone: the row is skipped and the reason is printed" 0 '^docs +docs-older-than-limit +- +- +skipped \(shallow clone\)' "$SHELL_BIN" -c "cd '$TX/shallow' && bash scripts/debt-dashboard.sh | grep docs-older; exit 0"
check_php TX20 "dashboard --offline: the skip names its reason" 0 '^docs +docs-older-than-limit +- +- +skipped \(--offline\)' dashboard --offline

# ---------------------------------------------------------------- keyed-baseline.sh (B-10, B-11)
KB="$TX/kb"
mkdir -p "$KB"
printf 'a.ts: error TS2322 x\nb.ts: error TS2322 y\n' > "$KB/seed.txt"
printf '%s\n' 'src/a.ts(1,2): error TS2322: no' 'src/b.ts(3,4): error TS2345: no' > "$KB/two.out"
printf '%s\n' 'src/a.ts(9,9): error TS2322: no' > "$KB/one.out"
printf '%s\n' 'src/a.ts(9,9): error TS2322: no' 'src/c.ts(1,1): error TS7006: new' > "$KB/grown.out"
check TX21 "keyed-baseline: the first file is written with --init" 0 'rewritten, 2 lines' bash "$KIT/tools/keyed-baseline.sh" "$KB/base.txt" --init --format tsc -- cat "$KB/two.out"
check TX22 "keyed-baseline: --write refuses to add a finding the file lacks" fail 'would add findings that are not in' bash "$KIT/tools/keyed-baseline.sh" "$KB/base.txt" --write --format tsc -- cat "$KB/grown.out"
check TX22b "keyed-baseline: the baseline file is untouched after that refusal" 0 '^2$' "$SHELL_BIN" -c "wc -l < '$KB/base.txt' | tr -d ' '"
check TX23 "keyed-baseline: a tsc start-up error with no position is no finding: --write fails and keeps the file" fail 'failed \(exit 1\) without a finding' bash "$KIT/tools/keyed-baseline.sh" "$KB/base.txt" --write --format tsc -- bash -c "echo \"error TS5058: The specified path does not exist: 'x'.\"; exit 1"
check TX23b "keyed-baseline: the file still holds the two real lines" 0 '^2$' "$SHELL_BIN" -c "wc -l < '$KB/base.txt' | tr -d ' '"
check TX24 "keyed-baseline: --write drops a fixed line" 0 'rewritten, 1 lines' bash "$KIT/tools/keyed-baseline.sh" "$KB/base.txt" --write --format tsc -- cat "$KB/one.out"
printf '%s\n' 'npm warn exec The following package was not found and will be installed: knip@5.0.0' 'Excellent, Knip found no issues.' > "$KB/knip-clean.out"
printf '%s\n' 'npm warn exec noise' 'Unused exports (1)' 'src/a.ts:3:1  foo' > "$KB/knip-one.out"
check TX25 "keyed-baseline: knip lines before a heading are not findings (a clean run is zero findings)" 0 '0 findings, the same as' bash "$KIT/tools/keyed-baseline.sh" "$KB/knip-base.txt" --format knip -- cat "$KB/knip-clean.out"
check TX26 "keyed-baseline: the first write of a knip file keeps only the finding under its heading" 0 'rewritten, 1 lines' bash "$KIT/tools/keyed-baseline.sh" "$KB/knip-base.txt" --init --format knip -- cat "$KB/knip-one.out"

# ---------------------------------------------------------------- mutation.sh (B-13, B-14, B-15)
MU="$TX/mutation"
mkdir -p "$MU/scripts" "$MU/app" "$MU/vendor/bin" "$MU/bin"
cp "$KIT/tools/mutation.sh" "$KIT/tools/kit-config.sh" "$MU/scripts/"
sed -i.bak 's/<pest, infection or stryker>/pest/; s/<source folders>/app/' "$MU/scripts/mutation.sh"
printf 'threshold: 70\n' > "$MU/scripts/mutation-floor.txt"
printf '#!/bin/sh\necho "pest $*"\n' > "$MU/vendor/bin/pest"
printf '#!/bin/sh\necho "npx $* floor=$MUTATION_FLOOR"\necho "INFO MutationTestReportHelper Final mutation score of 100.00 is greater than or equal to break threshold $MUTATION_FLOOR"\n' > "$MU/bin/npx"
chmod +x "$MU/vendor/bin/pest" "$MU/bin/npx"
UMLAUT=$(printf 'app/\303\234n\303\257.php')
(
    cd "$MU" && git init -q -b main && printf 'a\nb\nc\nd\ne\nf\n' > app/Old.php && printf 'a\nb\nc\nd\ne\nf\n' > app/Other.php && printf 'x\n' > app/keep.php &&
        git add -A && git commit -q -m "PROJ-1 chore: base" && git update-ref refs/remotes/origin/main HEAD &&
        git mv app/Old.php app/New.php && printf 'g\n' >> app/New.php && printf 'z\n' > "$UMLAUT" && git add -A && git commit -q -m "PROJ-1 chore: rename and add"
) > /dev/null 2>&1
check TX27 "mutation.sh --changed: a renamed and edited file is mutated (it was dropped, and the run said nothing to mutate)" 0 '--path=app/New\.php' "$SHELL_BIN" -c "cd '$MU' && CHECK_BASE=main bash scripts/mutation.sh --changed"
check TX28 "mutation.sh --changed: a non-ASCII path is passed as it is, not quoted and escaped" 0 "--path=[^ ]*$UMLAUT" "$SHELL_BIN" -c "cd '$MU' && CHECK_BASE=main bash scripts/mutation.sh --changed"
sed -i.bak 's/^MUTATION_TOOL=.*$/MUTATION_TOOL="stryker"/' "$MU/scripts/mutation.sh"
check TX29 "mutation.sh stryker: the floor goes in as MUTATION_FLOOR, and the changed files as one comma list" 0 '^npx stryker run --mutate app/New\.php,.* floor=70$' "$SHELL_BIN" -c "cd '$MU' && PATH='$MU/bin:'\$PATH CHECK_BASE=main bash scripts/mutation.sh --changed"
check TX29b "mutation.sh stryker: the whole-tree run carries the floor too" 0 '^npx stryker run floor=70$' "$SHELL_BIN" -c "cd '$MU' && PATH='$MU/bin:'\$PATH bash scripts/mutation.sh"

# ---------------------------------------------------------------- map-normalise.sh (B-16, B-17)
printf 'App\\Models\\User\tApp\\Domain\\User\n' > "$TX/user.tsv"
check TX30 "map-normalise: a longer name that starts with a mapped name is left alone" 0 '^App\\Domain\\UserProfile App\\Models\\User;$' "$SHELL_BIN" -c "printf 'App\\\\Domain\\\\UserProfile App\\\\Domain\\\\User;\n' | bash '$KIT/tools/map-normalise.sh' '$TX/user.tsv'"
check TX31 "map-normalise: the doubled-backslash form is bounded the same way" 0 '^App\\\\Domain\\\\UserProfile App\\\\Models\\\\User$' "$SHELL_BIN" -c "printf 'App\\\\\\\\Domain\\\\\\\\UserProfile App\\\\\\\\Domain\\\\\\\\User\n' | bash '$KIT/tools/map-normalise.sh' '$TX/user.tsv'"
awk 'BEGIN { for (i = 1; i <= 400; i++) printf "A\\Old\\C%d\tA\\New\\C%d\n", i, i }' > "$TX/big.tsv"
awk 'BEGIN { for (i = 1; i <= 1200; i++) printf "x A\\New\\C%d y\n", i % 400 + 1 }' > "$TX/big.txt"
check TX32 "map-normalise: 400 map entries over 1200 lines run in seconds, and map the last line right" 0 '^fast x A\\Old\\C1 y$' "$SHELL_BIN" -c "s=\$SECONDS; last=\$(bash '$KIT/tools/map-normalise.sh' '$TX/big.tsv' < '$TX/big.txt' | tail -1); [ \$((SECONDS - s)) -lt 10 ] && echo \"fast \$last\"; exit 0"

# ---------------------------------------------------------------- move-ts.mjs (B-18 to B-22)
# tx_ts_repo <name>: a scratch repository with src/a, src/b, src/c, and the folder of the repository printed
tx_ts_repo() {
    local dir="$TX/mv-$1"
    command rm -rf "$dir"
    mkdir -p "$dir/src/a" "$dir/src/b" "$dir/src/c"
    (
        cd "$dir" && git init -q -b main &&
            printf 'export const util = 1;\n' > src/c/util.ts &&
            printf 'export const bar = 2;\n' > src/b/bar.ts &&
            printf 'import { util } from "../c/util";\nimport { bar } from "../b/bar";\nexport const self = util + bar;\n' > src/a/self.ts &&
            printf 'import { self } from "../a/self";\nexport const user = self;\n' > src/b/user.ts &&
            git add -A && git commit -q -m "PROJ-1 chore: base"
    ) > /dev/null 2>&1
    echo "$dir"
}
MOVE="node $KIT/tools/move-ts.mjs"

MV=$(tx_ts_repo cafe)
(
    cd "$MV" && printf 'import { util } from "../c/util";\nexport const cafe = util;\n' > "src/a/caf$(printf '\303\251').ts" &&
        printf 'import { cafe } from "../a/caf%s";\nexport const c = cafe;\n' "$(printf '\303\251')" > src/b/c.ts &&
        git add -A && git commit -q -m "PROJ-1 chore: cafe" && printf 'src/a/caf%s.ts\tsrc/z/deep/caf%s.ts\n' "$(printf '\303\251')" "$(printf '\303\251')" > map.tsv
) > /dev/null 2>&1
check TX33 "move-ts: a file with a non-ASCII name moves" 0 'Move: 1 map lines applied' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src"
check TX34 "move-ts: the moved non-ASCII file gets its relative specifier recomputed" 0 'from "\.\./\.\./c/util";' cat "$MV/src/z/deep/caf$(printf '\303\251').ts"
check TX35 "move-ts: an importer of the non-ASCII file follows it" 0 'from "\.\./z/deep/caf' cat "$MV/src/b/c.ts"
check TX36 "move-ts --verify: passes after that move" 0 'no specifier names an old path' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src --verify"

MV=$(tx_ts_repo half)
printf 'src/a/self.ts\tsrc/z/deep/self.ts\nsrc/a/untracked.ts\tsrc/z/deep/untracked.ts\n' > "$MV/map.tsv"
printf 'export const u = 1;\n' > "$MV/src/a/untracked.ts"
check TX37 "move-ts: a map line whose file is not tracked stops the run before any file moves" fail 'untracked\.ts: not tracked by git' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src"
check TX37b "move-ts: nothing moved: the first file of the map is still at its old path" 0 '^yes$' "$SHELL_BIN" -c "cd '$MV' && [ -f src/a/self.ts ] && [ ! -e src/z ] && echo yes"

MV=$(tx_ts_repo clash)
printf 'export const foo = 1;\n' > "$MV/src/a/foo.ts"
printf 'export const foo = 2;\n' > "$MV/src/c/foo.ts"
(cd "$MV" && git add -A && git commit -q -m "PROJ-1 chore: foo" && printf 'src/a/foo.ts\tsrc/c/foo.ts\n' > map.tsv) > /dev/null 2>&1
check TX38 "move-ts: a target that already exists is an error, and no importer is rewritten to the other file" fail 'both exist' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src"

MV=$(tx_ts_repo forms)
(
    cd "$MV" && printf 'const a = jest.requireActual("../a/self");\nconst b = jest.requireMock("../a/self");\nvi.unmock("../a/self");\nvi.importMock("../a/self");\nconst r = require.resolve("../a/self");\njest.setMock("../a/self", {});\njest.doUnmock("../a/self");\n' > src/b/forms.ts &&
        git add -A && git commit -q -m "PROJ-1 chore: forms" && printf 'src/a/self.ts\tsrc/z/self.ts\n' > map.tsv
) > /dev/null 2>&1
check TX39 "move-ts: requireActual, requireMock, unmock, importMock, require.resolve, setMock and doUnmock follow the move" 0 '^7$' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src > /dev/null && grep -c '\"../z/self\"' src/b/forms.ts"

MV=$(tx_ts_repo crlf)
printf 'src/a/self.ts\tsrc/z/self.ts\r\n' > "$MV/map.tsv"
check TX40 "move-ts: a map with CRLF line ends moves to src/z/self.ts, not to a name with a carriage return" 0 '^src/z/self\.ts$' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src > /dev/null && git ls-files src/z"

MV=$(tx_ts_repo verify)
printf 'src/a/self.ts\tsrc/z/deep/self.ts\n' > "$MV/map.tsv"
(cd "$MV" && mkdir -p src/z/deep && git mv src/a/self.ts src/z/deep/self.ts) > /dev/null 2>&1
check TX41 "move-ts --verify: a moved file whose relative specifier names no file (a half move) fails" fail 'names no file' "$SHELL_BIN" -c "cd '$MV' && $MOVE map.tsv --roots src --verify"

# ---------------------------------------------------------------- ci-commit-messages.sh (B-24)
tx_message() {
    # tx_message <id> <scenario> <0|fail> <text> <commit message>
    branch "tx-$1"
    echo "$1" > "docs/$1.md"
    commit "$5"
    check "$1" "$2" "$3" "$4" commits
}
tx_message TX42 "commit check rejects: a single-parent commit titled like a merge" fail 'Merge branch' "Merge branch 'x' into y"
tx_message TX43 "commit check rejects: a single-parent commit titled Merge pull request" fail 'Merge pull request' "Merge pull request #5 from a/b"
tx_message TX44 "commit check rejects: a Revert title with work after the quote" fail 'plus smuggled work' 'Revert "bad title" plus smuggled work'
tx_message TX45 "commit check accepts: a Revert title that ends with its quote (control)" 0 '1 commits between' 'Revert "PROJ-12 feat: add the export"'

# ---------------------------------------------------------------- ci-parallel.sh (B-25 to B-27, D-026)
check TX46 "ci-parallel: a task with an empty command fails with 64, it is not a green check" fail 'has no name or no command' "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "Empty::" "Good::echo good"
check TX47 "ci-parallel: a task of spaces fails with 64" fail 'has no name or no command' "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "Spaces::   "
check TX48 "ci-parallel: a task without :: fails with 64" fail 'is not Name::command' "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "just a command"
check TX49 "ci-parallel: a limit that is no number is an error, not a timeout of every command" fail 'whole numbers of seconds' env CI_CHECK_TIMEOUT=abc "$SHELL_BIN" "$KIT/tools/ci-parallel.sh" "A::echo a"
check TX50 "ci-parallel: stopped with TERM, it leaves no watchdog behind" 0 '^0$' "$SHELL_BIN" -c 'CI_CHECK_TIMEOUT=7601 bash "$0" "a::sleep 7301" "b::sleep 7302" > /dev/null 2>&1 & pid=$!; sleep 3; kill -TERM $pid; sleep 2; ps -axo command | grep -c -E "^sleep (7301|7302|7601)$"; pkill -f "^sleep (7301|7302|7601)$" 2> /dev/null; exit 0' "$KIT/tools/ci-parallel.sh"
check TX51 "ci-parallel: the reference project's copy is the kit's" 0 '^$' diff "$KIT/tools/ci-parallel.sh" "$KIT/reference-project/scripts/ci-parallel.sh"

cd "$TX_CWD" || exit 1
