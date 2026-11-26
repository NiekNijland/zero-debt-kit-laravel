# Scenarios RT*: the defects a real app found in the scripts (findings P3-11, P3-12, P5-1, P5-6): doctor and a row, a config value or
# a task line the kit changed, task lines a project added, a part of checks.sh that reads no base in CI, and --head with
# uncommitted files. Sourced by proof.sh with its helpers in scope (check, KIT, ROOT, SHELL_BIN). Self-contained: own scratch folders
# under "$ROOT/rt".

RT="$ROOT/rt"
mkdir -p "$RT"
RTK="$RT/kit"

rt_doctor() { "$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$RTK" "$@"; }

# A scratch kit with two commits of the kit's own scripts: the second changes a ratchet row (analyser-level), a config value
# (OVERRIDE_AT_ZERO) and a task line (Secret scan).
mkdir -p "$RTK/tools" "$RTK/ci"
(
    cd "$RTK" || exit 1
    git init -q -b main
    for f in ci-ratchets debt-dashboard ci-commit-messages ci-parallel kit-config; do cp "$KIT/tools/$f.sh" tools/; done
    cp "$KIT/ci/checks.sh" ci/
    echo "1.0.0" > VERSION
    git add -A && git commit -q -m "kit v1" && git tag v1
    sed -i.bak 's/^analyser-level  *max  grep .*$/&|^[[:space:]]+level:[[:space:]]+11$/' tools/ci-ratchets.sh
    sed -i.bak 's/^OVERRIDE_AT_ZERO="\(.*\)"$/OVERRIDE_AT_ZERO="\1,rt-extra"/' tools/ci-ratchets.sh
    sed -i.bak 's/--log-opts=\$REMOTE\/\$BASE\.\.\${CHECK_HEAD:-HEAD}/--log-opts=$REMOTE\/$BASE..${CHECK_HEAD:-HEAD} --max-count=9999/' ci/checks.sh
    rm -f tools/*.bak ci/*.bak
    echo "1.1.0" > VERSION
    git add -A && git commit -q -m "kit v2"
) > /dev/null 2>&1

rt_project() { # rt_project <folder> <revision>
    mkdir -p "$1/scripts"
    local f
    for f in tools/ci-ratchets.sh tools/debt-dashboard.sh tools/ci-commit-messages.sh tools/ci-parallel.sh tools/kit-config.sh ci/checks.sh; do
        git -C "$RTK" show "$2:$f" > "$1/scripts/$(basename "$f")"
    done
}

# p-stale: v1 scripts, plus a task line the project added after Lint, the project's own command on Format, and its own edit of the todo row
rt_project "$RT/p-stale" v1
sed -i.bak 's|^    "Lint::.*$|&\
    "Manifest floor::composer bump --dry-run"|; s|^    "Format::.*$|    "Format::vendor/bin/pint --test"|' "$RT/p-stale/scripts/checks.sh"
sed -i.bak 's/^todo  *max  grep .*$/&|NOTE:/' "$RT/p-stale/scripts/ci-ratchets.sh"
rm -f "$RT"/p-stale/scripts/*.bak
rt_project "$RT/p-tasks" HEAD
sed -i.bak 's|^    "Lint::.*$|&\
    "Manifest floor::composer bump --dry-run"|' "$RT/p-tasks/scripts/checks.sh"
rm -f "$RT"/p-tasks/scripts/*.bak

check RT01 "P5-1 doctor: a row the kit changed since the project took it is reported, and the run diverges" fail 'ratchet rows the kit changed .*: analyser-level' rt_doctor --project "$RT/p-stale"
check RT02 "P5-1 doctor: a config value that equals an older kit default is reported as the kit's change" fail 'config values the kit changed .*: OVERRIDE_AT_ZERO' rt_doctor --project "$RT/p-stale"
check RT03 "P5-1 doctor: a task line that equals an older kit line is reported, a line the project filled in is not" fail 'task lines the kit changed .*: Secret scan#1$' rt_doctor --project "$RT/p-stale"
check RT03b "P5-1 doctor: the row the project edited itself is listed as its own and kept" fail 'equal no kit commit.*: todo$' rt_doctor --project "$RT/p-stale"
check RT03c "P5-1 doctor --json: the kit-changed lists are in the report" fail '"ratchet_rows_kit_changed":\["analyser-level"\]' rt_doctor --project "$RT/p-stale" --json
check RT04 "P3-11 doctor: a task line the project added to checks.sh is not a body difference (a project at the kit's version exits 0)" 0 'checks\.sh +identical' rt_doctor --project "$RT/p-tasks"

cp -R "$RT/p-stale" "$RT/p-apply"
rt_doctor --project "$RT/p-apply" --apply > "$RT/apply.out" 2>&1
check RT05 "P5-1 doctor --apply: the row the kit changed is the kit's row" 0 'level:\[\[:space:\]\]\+11\$' grep '^analyser-level' "$RT/p-apply/scripts/ci-ratchets.sh"
check RT06 "P5-1 doctor --apply: the config value is the kit's again" 0 'rt-extra' grep '^OVERRIDE_AT_ZERO' "$RT/p-apply/scripts/ci-ratchets.sh"
check RT07 "P5-1 doctor --apply: the project's own row edit is kept" 0 'NOTE:' grep '^todo ' "$RT/p-apply/scripts/ci-ratchets.sh"
check RT08 "P3-11 doctor --apply: the task line the project added is still there, once, at its place after Lint" 0 '^1$' "$SHELL_BIN" -c "grep -c 'Manifest floor::composer bump' '$RT/p-apply/scripts/checks.sh'; awk '/\"Lint::/ { getline nxt; if (nxt ~ /Manifest floor/) found = 1 } END { exit !found }' '$RT/p-apply/scripts/checks.sh'"
check RT09 "P3-11 doctor --apply: the project's command on Format is kept, the kit's changed Secret scan line is back" 0 'max-count=9999' grep 'Secret scan::' "$RT/p-apply/scripts/checks.sh"
check RT09b "P3-11 doctor --apply: the project's Format command is kept" 0 'pint --test' grep '"Format::' "$RT/p-apply/scripts/checks.sh"
check RT10 "P5-1 doctor after --apply: only the project's own row edit is left, not a divergence" 0 'Diverged: no' rt_doctor --project "$RT/p-apply"
check RT10b "P5-1 doctor --apply: says which rows, values and task lines it carried" 0 'ratchet rows the kit changed are the kit.s again: analyser-level' cat "$RT/apply.out"

# A kit folder without git history cannot tell a kit change from a project edit: the rows are listed and --apply keeps them.
mkdir -p "$RT/kit-nogit"
cp -R "$RTK/tools" "$RTK/ci" "$RTK/VERSION" "$RT/kit-nogit/"
check RT11 "P5-1 doctor without a kit history: rows that differ are listed as differing, not carried" 0 'differ from the kit.s and equal no kit commit.s .*: analyser-level todo' "$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$RT/kit-nogit" --project "$RT/p-stale"

# ---------------------------------------------------------------- checks.sh: a part that reads no base runs in CI without one (P5-6)
RTC="$RT/checks"
mkdir -p "$RTC/scripts"
(
    cd "$RTC" || exit 1
    git init -q -b main
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-parallel.sh" scripts/
    cp "$KIT/ci/checks.sh" scripts/checks.sh
    sed -i.bak 's/^DATABASE_IMAGE=.*$/DATABASE_IMAGE="postgres:17"/' scripts/kit-config.sh
    sed -i.bak 's/::<[^"]*>"/::true"/' scripts/checks.sh
    rm -f scripts/*.bak
    printf 'echo "stub ratchets"\n' > scripts/ci-ratchets.sh
    printf 'echo "stub dashboard $*"\n' > scripts/debt-dashboard.sh
    printf 'echo "stub commit messages"\n' > scripts/ci-commit-messages.sh
    git add -A && git commit -q -m "PROJ-1 chore: base"
) > /dev/null 2>&1
rt_checks() { (cd "$RTC" && env -u CHECK_BASE "$@"); }
check RT12 "P5-6 checks.sh: --part static in CI with no base, no pull request and no --main runs" 0 '=== Format \(' rt_checks CI=true "$SHELL_BIN" scripts/checks.sh --part static
check RT12b "P5-6 checks.sh: --part guards in CI with no base runs too" 0 '=== Guard tests \(' rt_checks CI=true "$SHELL_BIN" scripts/checks.sh --part guards
check RT13 "P5-6 checks.sh: --part repo (commit messages, secret scan) in CI with no base still stops and names --main" fail 'no base branch\. This is a CI run' rt_checks CI=true "$SHELL_BIN" scripts/checks.sh --part repo
check RT13b "P5-6 checks.sh: --part gate in CI with no base still stops" fail 'no base branch\. This is a CI run' rt_checks CI=true "$SHELL_BIN" scripts/checks.sh --part gate
check RT13c "P5-6 checks.sh: a whole run in CI with no base still stops" fail 'no base branch\. This is a CI run' rt_checks CI=true "$SHELL_BIN" scripts/checks.sh

# ---------------------------------------------------------------- ci-ratchets.sh --head counts the commit (P3-12)
RTH="$RT/head"
mkdir -p "$RTH/scripts" "$RTH/app"
(
    cd "$RTH" || exit 1
    git init -q -b main
    cp "$KIT/tools/ci-ratchets.sh" "$KIT/tools/kit-config.sh" scripts/
    printf '<?php\n$a = 1;\n' > app/a.php
    git add -A && git commit -q -m "PROJ-1 chore: base"
) > /dev/null 2>&1
rt_head() { (cd "$RTH" && "$SHELL_BIN" scripts/ci-ratchets.sh --head 2>&1; exit 0); }
check RT14 "P3-12 ratchets --head: a clean tree prints no note about uncommitted files" 0 '^0$' "$SHELL_BIN" -c "cd '$RTH' && bash scripts/ci-ratchets.sh --head 2>&1 | grep -c 'not the working tree'; exit 0"
printf '<?php\nif ($a == $b) {}\n' > "$RTH/app/new.php"
check RT15 "P3-12 ratchets --head: an untracked file is not counted, and the output says so" 0 'counts the commit HEAD, not the working tree: 1 path\(s\) with uncommitted or untracked changes are not counted' rt_head
rm -f "$RTH/app/new.php"
