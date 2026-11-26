# Scenarios GR*: the findings of the second cold review of tools/ and ci/ (G-02, G-07, G-08, G-13, G-14, G-17, G-19, G-21). The others
# of that review have their scenarios next to the tool they concern (RX22b-d, RX30b-RX32c, C09b-c, C11, TX17b, TX24b-d, DX24-DX27).
# Sourced by ../proof.sh with its helpers in scope (check, check_php, branch, commit, edit, build_fixture, KIT, ROOT, SHELL_BIN). It
# builds its own scratch repository under "$ROOT/gr" and does not use the fixtures of proof.sh.

GR_SAVED_ROOT="$ROOT"
ROOT="$GR_SAVED_ROOT/gr"
mkdir -p "$ROOT"
build_fixture

# ---------------------------------------------------------------- G-02: no kit script reads as a skipped step
# The expression of the ratchet row ci-skipped-steps is run over every script of the kit that a project installs in scripts/:
# a success-forcing tail in one of them would raise the row of every project that installs it.
gr_skipped_steps() {
    local expression file count total=0
    expression=$(awk '/^ci-skipped-steps /{ $1 = $2 = $3 = $4 = ""; sub(/^ +/, ""); print; exit }' "$KIT/tools/ci-ratchets.sh")
    for file in "$KIT"/tools/*.sh "$KIT"/tools/*.mjs "$KIT"/ci/*.sh "$KIT"/ci/*.yml; do
        [ -f "$file" ] || continue
        count=$(grep -c -E -e "$expression" "$file")
        total=$((total + count))
        [ "$count" -eq 0 ] || echo "$file: $count"
    done
    echo "total $total"
}
check GR01 "G-02: no script of the kit (tools/ and ci/) matches the ci-skipped-steps expression: installing them raises no row" 0 '^total 0$' gr_skipped_steps

# ---------------------------------------------------------------- G-07: an invalid DOCS_PATHS pathspec is UNKNOWN, never 0
branch gr-docs
mkdir -p docs
echo old > docs/old.md
git add -A
GIT_AUTHOR_DATE="2020-01-01T00:00:00" GIT_COMMITTER_DATE="2020-01-01T00:00:00" git commit -q -m "PROJ-5 docs: an old doc"
check_php GR02 "G-07: a doc last changed in 2020 is counted by the docs row (control)" fail '^docs +docs-older-than-limit +1 +0 +OPEN' dashboard
edit scripts/debt-dashboard.sh 's/^DOCS_PATHS=.*$/DOCS_PATHS=":(bogus)docs"/'
check_php GR03 "G-07: an invalid pathspec in DOCS_PATHS makes the row UNKNOWN, not 0 and ok" fail '^docs +docs-older-than-limit +\? +\? +UNKNOWN' dashboard
git checkout -q -- scripts/debt-dashboard.sh

# ---------------------------------------------------------------- G-08: every alternative of every regex row has a case
GR_MUT="$ROOT/mut"
mkdir -p "$GR_MUT"
# gr_selftest <sed expression on ci-ratchets.sh | -> [extra cases file]: the self-test of a changed copy of the script
gr_prepare() {
    command rm -rf "$GR_MUT/run"
    mkdir -p "$GR_MUT/run"
    cp "$KIT/tools/kit-config.sh" "$GR_MUT/run/kit-config.sh"
    if [ "$1" = "-" ]; then cp "$KIT/tools/ci-ratchets.sh" "$GR_MUT/run/ci-ratchets.sh"; else sed -E "$1" "$KIT/tools/ci-ratchets.sh" > "$GR_MUT/run/ci-ratchets.sh"; fi
}
gr_selftest() {
    gr_prepare "$1"
    (cd "$GR_MUT/run" && "$SHELL_BIN" ci-ratchets.sh --self-test ${2:+"$2"} 2>&1)
}
check GR04 "G-08: the shipped self-test has a case for every alternative of every regex row" 0 'Self-test: [0-9]+ cases run, 0 wrong, 0 skipped .*; [0-9]+ alternatives of the regex rows, 0 without a case' gr_selftest -
check GR05 "G-08: a name dropped from the formatter list is caught (the case of that name counts 0)" fail 'formatter-rules counts 0, not 1: +"lowercase_keywords"' gr_selftest 's/\|lowercase_keywords\|/|/'
check GR06 "G-08: a case deleted is caught: its alternative has no spelling left" fail 'no case exercises the alternative "lowercase_keywords"' gr_selftest '/^formatter-rules 1 +"lowercase_keywords": true,$/d'
check GR07 "G-08: a misspelt switch name in ANALYSER_SWITCHES is caught" fail 'analyser-switches counts 0, not 1: +"noImplicitReturns": true' gr_selftest 's/\|noImplicitReturns\|/|noImplicitReturn|/'
check GR08 "G-08: an alternative removed from a table row (a test double) is caught" fail 'test-doubles counts' gr_selftest 's/\|namedMock\|/|/'
check GR09 "G-08: an alternative removed from the skipped-step row is caught" fail 'ci-skipped-steps counts 0, not 1' gr_selftest 's/\|command\[\[:space:\]\]\+true\|/|/'
check GR10 "G-08: the spellings the review found missing have their cases (numeric lint severity, NEON booleans, serial describe, set +e)" 0 '^5$' "$SHELL_BIN" -c "grep -c -E -e '^lint-config-off 1 +\"no-console\": 0,\$|^analyser-ignore 1 +reportUnmatchedIgnoredErrors: (False|no)\$|^frontend-test-skip 1 test.describe.serial.only|^ci-skipped-steps 1 set ~PL~e\$' '$KIT/tools/ci-ratchets.sh'"

# G-17: a case of the project's own file that names no row is a failure, not a skip
printf 'todoo 5 // TODO\nnotarow 1 x\n' > "$GR_MUT/extra.txt"
check GR11 "G-17: a row named in the project's case file that is not in the table fails the self-test" fail 'todoo is named by a case of .* but is no row of the table' gr_selftest - "$GR_MUT/extra.txt"

# G-21: in CI a self-test case skipped for want of php or node is a failure, outside CI a skip
gr_farm() {
    local dir="$ROOT/path-bare" tool
    mkdir -p "$dir"
    for tool in git grep awk sed wc tr cat sort comm mktemp rm dirname basename date xargs env cut head mkdir cp uname; do
        [ -n "$(command -v "$tool")" ] || continue
        [ -e "$dir/$tool" ] || ln -s "$(command -v "$tool")" "$dir/$tool"
    done
    [ -e "$dir/bash" ] || ln -s "$SHELL_BIN" "$dir/bash"
    echo "$dir"
}
GR_BARE=$(gr_farm)
gr_selftest_bare() { (cd "$GR_MUT/run" && env PATH="$GR_BARE" "$@" "$SHELL_BIN" ci-ratchets.sh --self-test 2>&1); }
gr_prepare -
check GR12 "G-21: without php and node a developer's self-test skips the block and neon cases and still passes" 0 'Self-test: [0-9]+ cases run, 0 wrong, [1-9][0-9]* skipped' gr_selftest_bare CI=
check GR13 "G-21: the same in CI fails and says which tools are missing" fail 'skipped because a counter tool \(php or node\) is missing' gr_selftest_bare CI=1

# ---------------------------------------------------------------- G-13: template literals and new URL(...) are rewritten by move-ts
GR_MV="$ROOT/mv"
mkdir -p "$GR_MV/src/old dir"
(
    cd "$GR_MV" || exit 1
    git init -q -b main
    echo 'export const a = 1;' > "src/old dir/a.ts"
    printf 'const lazy = () => import(`./old dir/a`);\nconst url = new URL("./old dir/a.ts", import.meta.url);\nconst dyn = (n: string) => import(`./old dir/${n}`);\n' > src/main.ts
    git add -A
    git commit -q -m "PROJ-1 chore: base"
    printf 'src/old dir/a.ts\tsrc/new dir/a.ts\n' > moves.tsv
) > /dev/null 2>&1
check GR14 "G-13: a constant template literal import and new URL() follow a move (a path with a space)" 0 'Move: 1 map lines applied' "$SHELL_BIN" -c "cd '$GR_MV' && node '$KIT/tools/move-ts.mjs' moves.tsv --roots src"
check GR14b "G-13: the template literal is rewritten" 0 'import\(`\./new dir/a`\)' cat "$GR_MV/src/main.ts"
check GR14c "G-13: new URL(...) is rewritten" 0 'new URL\("\./new dir/a\.ts"' cat "$GR_MV/src/main.ts"
check GR14d "G-13: a template literal built at run time (\${...}) is left alone" 0 'import\(`\./old dir/\$\{n\}`\)' cat "$GR_MV/src/main.ts"

# ---------------------------------------------------------------- G-14: map-normalise matches whole names on the left too
printf '%s\t%s\n' 'a\New\Foo' 'b\Old\Foo' > "$GR_MV/names.tsv"
gr_norm() { printf '%s\n' "$1" | bash "$KIT/tools/map-normalise.sh" "$GR_MV/names.tsv" --forward; }
check GR15 "G-14: a different class whose name ends in a mapped name is left alone (a longer qualified name)" 0 '^MyOther\\a\\New\\Foo$' gr_norm 'MyOther\a\New\Foo'
check GR15b "G-14: a name glued to a letter before it is left alone" 0 '^xa\\New\\Foo$' gr_norm 'xa\New\Foo'
check GR15c "G-14: a fully qualified name with a leading backslash still maps" 0 '^\\b\\Old\\Foo$' gr_norm '\a\New\Foo'
check GR15d "G-14: the doubled form inside a JSON string still maps" 0 '^"\\\\b\\\\Old\\\\Foo"$' gr_norm '"\\a\\New\\Foo"'

# ---------------------------------------------------------------- G-19: the audit gate validates the whole JSON with jq, node or php
gr_audit() { printf '%s' "$1" | env AUDIT_EXCEPTIONS_FILE="$ROOT/none.json" "${@:2}" "$SHELL_BIN" "$KIT/tools/audit-gate.sh" 2>&1; }
check GR16 "G-19: a garbage key between the brackets fails (jq, node or php reads the whole text)" fail 'cannot be parsed' gr_audit '{"advisories":[], "garbage": }'
check GR16b "G-19: a top-level array is no audit document" fail 'incomplete' gr_audit '[{"advisories":1}]'
check GR16c "G-19: a whole document with no advisory passes (control)" 0 '^0$' gr_audit '{"advisories":[]}'
GR_NOJSON=$(gr_farm)
check GR17 "G-19: with none of jq, node and php the gate fails instead of trusting the brackets" fail 'none of jq, node or php is installed' gr_audit '{"advisories":[], "garbage": }' PATH="$GR_NOJSON"

cd "$GR_SAVED_ROOT" || exit 1
ROOT="$GR_SAVED_ROOT"
