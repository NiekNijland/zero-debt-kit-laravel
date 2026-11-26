# Scenarios RX*: the defects of the ratchet script found by an audit, one scenario per fix. Sourced by ../proof.sh with its helpers.
# The scratch repositories are built under "$ROOT/rx"; ROOT is put back at the end.

RX_SAVED_ROOT="$ROOT"
ROOT="$RX_SAVED_ROOT/rx"
mkdir -p "$ROOT"
build_fixture

# rx_row <name> <line>: put a row into the table of the working copy, below the todo row
rx_row() {
    ROW="$2" awk '{ print } /^todo +max/ { print ENVIRON["ROW"] }' scripts/ci-ratchets.sh > scripts/ci-ratchets.tmp
    cat scripts/ci-ratchets.tmp > scripts/ci-ratchets.sh
    command rm scripts/ci-ratchets.tmp
}

# ---------------------------------------------------------------- --self-test
cd "$ROOT/work" || exit 1
git checkout -q -f main
check RX01 "self-test: every regex row against its fixture, in a scratch repository, outside any repository too" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" -c "cd '$RX_SAVED_ROOT' && '$SHELL_BIN' '$ROOT/work/scripts/ci-ratchets.sh' --self-test"
check RX02 "self-test: the config block of the project is what is tested (the fixture project has an extra table row, which has no fixture: no failure)" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" scripts/ci-ratchets.sh --self-test
mkdir -p "$ROOT/mut"
cp scripts/kit-config.sh "$ROOT/mut/kit-config.sh"
sed 's/it|test|describe)(\\\.(concurrent/it|test|describe)(\\.(concurrentX/' scripts/ci-ratchets.sh > "$ROOT/mut/ci-ratchets.sh"
check RX03 "self-test: an expression that stops matching a spelling fails and names the row and the line" fail 'frontend-test-skip counts 0, not 1: it\.concurrent\.skip' "$SHELL_BIN" "$ROOT/mut/ci-ratchets.sh" --self-test
sed 's/\[})\]->(skip/[})]->(skipX/' scripts/ci-ratchets.sh > "$ROOT/mut/ci-ratchets.sh"
check RX04 "self-test: an expression that no longer matches fails, whichever row" fail 'test-skip counts 0, not 1' "$SHELL_BIN" "$ROOT/mut/ci-ratchets.sh" --self-test
printf 'todo 0 // TODO this must not count\n' > "$ROOT/mut/extra.txt"
check RX05 "self-test: a case file of the project's own is read too, and an over-count fails" fail 'todo counts 1, not 0: // TODO this must not count' "$SHELL_BIN" scripts/ci-ratchets.sh --self-test "$ROOT/mut/extra.txt"

# ---------------------------------------------------------------- A-01: attributes that make grep skip a file
branch x01
printf 'tests/Unit/* binary\n' > .gitattributes
printf '<?php\n\nit("x")->skip();\nit("y")->skip();\n' > tests/Unit/SkipTest.php
commit "PROJ-5 test: skips behind a binary attribute"
check_php RX10 "A-01: a .gitattributes line that marks the tests binary does not hide the new skips" fail '^test-skip +down +0 +2 +\+2 +FAILS' ratchets

git checkout -q -f main
printf 'tests/Unit/* -diff\n' > .gitattributes
commit "PROJ-5 chore: an attribute file on the base"
git push -q origin main
branch x03
printf 'tests/Unit/* binary\n' > .gitattributes
commit "PROJ-5 chore: change the attribute file"
check_php RX11 "A-01: .gitattributes is a guarded file once it exists on the base" fail 'Script: \.gitattributes changed\. FAILS' ratchets
branch x04
printf '<?php\n\nit("x")->skip();\n' > tests/Unit/SkipTest.php
commit "PROJ-5 test: a skip in a file the base attributes call -diff"
check_php RX12 "A-01: with a -diff attribute on the base, a skip in a file under it is still counted" fail '^test-skip +down +0 +1 +\+1 +FAILS' ratchets

# ---------------------------------------------------------------- A-02: a trailing comment on the level
branch x05
edit phpstan.neon 's/level: 10/level: 9 # was 10/'
commit "PROJ-5 chore: lower the analyser level with a comment"
check_php RX13 "A-02: 'level: 9 # was 10' counts as a level below the top" fail '^analyser-level +down +0 +1 +\+1 +FAILS' ratchets

# ---------------------------------------------------------------- A-03: a switch commented out
branch x06
edit phpstan.neon 's/^    checkMissingOverrideMethodAttribute: true/    # checkMissingOverrideMethodAttribute: true/'
commit "PROJ-5 chore: comment a switch out"
check_php RX14 "A-03: a strictness switch that is commented out no longer counts, so the min row falls" fail '^analyser-switches +up +2 +1 +-1 +FAILS' ratchets

# ---------------------------------------------------------------- A-04, A-09, A-15: test rows
branch x07
printf '<?php\n\nit("x")->skip();\n' > tests/Unit/PestSkipTest.php
commit "PROJ-5 test: a Pest skip after the test call"
check_php RX15 "A-04: it('x')->skip() on the test call itself counts" fail '^test-skip +down +0 +1 +\+1 +FAILS' ratchets

branch x08
mkdir -p tests/Feature
printf '<?php\n\nit("fakes", function (): void {\n    Event::fake([]);\n});\n' > tests/Feature/FakeEmptyTest.php
commit "PROJ-5 test: an empty fake list"
check_php RX16 "A-09: Event::fake([]) fakes every event and counts" fail '^event-fake-all +down +0 +1 +\+1 +FAILS' ratchets

branch x09
printf 'Never call $this->markTestSkipped( in a test.\n' > tests/README.md
commit "PROJ-5 docs: a note that mentions a skip call"
check_php RX17 "A-15: a Markdown file in the test folder is not counted by test-skip" 0 '^test-skip +down +0 +0 +\+0 +ok' ratchets

branch x10
printf 'import { test } from "vitest";\n\ntest.describe.skip("x", () => {});\n' > resources/js/hooks/skipped.test.ts
commit "PROJ-5 test: a describe skip"
check_php RX18 "A-05: test.describe.skip counts as a frontend skip" fail '^frontend-test-skip +down +0 +1 +\+1 +FAILS' ratchets

# ---------------------------------------------------------------- A-17: the tag must start a line
branch x11
printf '<?php\n// TODO another\n' > app/Services/Other.php
commit "PROJ-5 feat: one more todo

Do not forget the [ratchet: todo] tag, said the reviewer."
check_php RX19 "A-17: a tag in the middle of a line is no override" fail '^todo +down +1 +2 +\+1 +FAILS' ratchets

# ---------------------------------------------------------------- E-002: a max row at 0 takes an override when it is on the list
git checkout -q -f main
edit app/Actions/CreateOrder.php 's# // TODO remove the constant##'
commit "PROJ-5 chore: no comments left"
git push -q origin main
branch x12
printf '<?php\n// why this constant\n' > app/Services/Other.php
commit "PROJ-5 feat: a comment that is needed

[ratchet: comments] the constant is a protocol number"
check_php RX20 "E-002: the comments row at 0 takes an override with a reason" 0 '^comments +down +0 +1 +\+1 +allowed' ratchets
branch x13
printf '<?php\n// why this constant\n' > app/Services/Other.php
commit "PROJ-5 feat: a comment without a reason"
check_php RX21 "E-002: the same row at 0 without the tag still fails" fail '^comments +down +0 +1 +\+1 +FAILS' ratchets
branch x14
printf '<?php\n\nit("x")->skip();\n' > tests/Unit/PestSkipTest.php
commit "PROJ-5 test: a skip with a tag

[ratchet: test-skip] a row at 0 takes none"
check_php RX22 "E-002: a row that is not on the list takes no override at 0" fail '^test-skip +down +0 +1 +\+1 +FAILS' ratchets

# ---------------------------------------------------------------- A-07: shallow fork clones and branch names that look like options
branch x15
echo "note" > docs/note.md
commit "PROJ-5 docs: a note"
git push -q origin x15
git clone -q --depth 1 --branch x15 "file://$ROOT/origin.git" "$ROOT/shallow-fork"
cd "$ROOT/shallow-fork" || exit 1
check_php RX30 "A-07: a shallow clone of a fork PR whose head branch is not on the remote still deepens" 0 '^todo +down +0 +0 +\+0 +ok' env CI=1 CHECK_BASE=main GITHUB_HEAD_REF=forkuser-branch "$SHELL_BIN" scripts/ci-ratchets.sh
command rm -f "$ROOT/PWNED"
check_php RX31 "A-07: a head branch that starts with a dash is never read as a git option" 0 'not-created' "$SHELL_BIN" -c "CI=1 CHECK_BASE=main GITHUB_HEAD_REF='--upload-pack=touch $ROOT/PWNED; git-upload-pack' '$SHELL_BIN' scripts/ci-ratchets.sh > /dev/null 2>&1; [ ! -e '$ROOT/PWNED' ] && echo not-created"
check RX32 "A-07: a base branch that starts with a dash fails before git is run with it" fail 'starts with a dash' env CHECK_BASE='--upload-pack=x' "$SHELL_BIN" scripts/ci-ratchets.sh
cd "$ROOT/work" || exit 1

# ---------------------------------------------------------------- A-08, A-18: names outside ASCII
git checkout -q -f main
rx_row strict-types-missing 'strict-types-missing                   max  without @SOURCE_PATHS,@TEST_PATHS  (^|/)[^/.]+\.php$ ^declare\(strict_types=1\);'
commit "PROJ-5 ci: a without row

[ratchet: script] the row of the proof"
git push -q origin main
branch x16
RX_BEFORE=$("$SHELL_BIN" scripts/ci-ratchets.sh --head 2> /dev/null | awk '$1 == "strict-types-missing" { print $3 }')
printf '<?php\n\nfinal class Uni\n{\n}\n' > "$(printf 'app/\303\234ni.php')"
commit "PROJ-5 feat: a file with a letter outside ASCII"
RX_AFTER=$("$SHELL_BIN" scripts/ci-ratchets.sh --head 2> /dev/null | awk '$1 == "strict-types-missing" { print $3 }')
check RX33 "A-08: a PHP file without the strict-types line is counted when its name is not ASCII" 0 'counted-name' "$SHELL_BIN" -c '[ "$2" -eq $(( $1 + 1 )) ] && echo counted-name' _ "$RX_BEFORE" "$RX_AFTER"

branch x17
git mv scripts "$(printf 'scr\303\257pts')"
commit "PROJ-5 chore: a folder with a letter outside ASCII"
check RX34 "A-18: a script folder with a non-ASCII name is found as a committed file" 0 '^Ratchets against' "$SHELL_BIN" -c "CHECK_BASE=main '$SHELL_BIN' \"\$(printf 'scr\\303\\257pts')/ci-ratchets.sh\" 2>&1; true"

# ---------------------------------------------------------------- A-16: the NEON counter
branch x18
printf 'parameters:\n    level: 10\n    ignoreErrors:\n    - '"'"'#a#'"'"'\n    -\n        message: '"'"'#b#'"'"'\n        count: 3 # why\n' > phpstan.neon
commit "PROJ-5 chore: a baseline in the YAML form with the dash at the key's indent"
check_php RX35 "A-16: a list at the indent of its key and a count with a comment are read" 0 '^analyser-baseline max 4$' "$SHELL_BIN" -c "CHECK_BASE=main '$SHELL_BIN' scripts/ci-ratchets.sh --head 2>&1"

# ---------------------------------------------------------------- A-19: the json runners agree
for tool in jq node php; do
    check "RX36-$tool" "A-19: 1.0 is the whole number 1 for $tool" 0 '^1$' env RATCHET_JSON_TOOL="$tool" "$SHELL_BIN" -c "echo '{\"a\": 1.0}' | '$SHELL_BIN' scripts/ci-ratchets.sh --json 'value a'"
    check "RX37-$tool" "A-19: a number past 2^53 is refused by $tool" fail 'not a whole number' env RATCHET_JSON_TOOL="$tool" "$SHELL_BIN" -c "echo '{\"a\": 12345678901234567890}' | '$SHELL_BIN' scripts/ci-ratchets.sh --json 'value a'"
done

# ---------------------------------------------------------------- A-22: a character in an expression that the two sides read differently
branch x19
sed 's/^ANALYSER_SWITCHES="/ANALYSER_SWITCHES="\\\\/' scripts/ci-ratchets.sh > scripts/ci-ratchets.tmp
cat scripts/ci-ratchets.tmp > scripts/ci-ratchets.sh
command rm scripts/ci-ratchets.tmp
check RX38 "A-22: a backslash in ANALYSER_SWITCHES stops the run with the rule" fail 'holds a backslash, a dollar sign or a backtick' ratchets --head

# ---------------------------------------------------------------- an empty path list takes its exclude pathspecs with it
branch x20
edit scripts/ci-ratchets.sh 's/^TEST_SKIP_PATHS="tests"/TEST_SKIP_PATHS=""/'
check_php RX39 "test-skip with an empty TEST_SKIP_PATHS counts 0 and does not complain about a pathspec" 0 '^test-skip max 0$' "$SHELL_BIN" -c "CHECK_BASE=main '$SHELL_BIN' scripts/ci-ratchets.sh --head 2>&1"

ROOT="$RX_SAVED_ROOT"
