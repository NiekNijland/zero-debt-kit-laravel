# Scenarios DX*: tools/doctor.sh, the kit doctor (which scripts of a project drifted from the kit, and the upgrade). Sourced by
# proof.sh with its helpers in scope (check, KIT, ROOT, SHELL_BIN). Each scenario builds scratch folders under "$ROOT/dx".

DX="$ROOT/dx"
mkdir -p "$DX"
DXK="$DX/kit"

dx_doctor() { "$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK" "$@"; }

# A scratch kit with two versions: v1 (tag v1) and v2 (VERSION 1.1.0), whose bodies of three scripts differ by one line.
# It has no prepush.sh, so a project that has one holds a project-only file.
mkdir -p "$DXK/tools" "$DXK/ci"
(
    cd "$DXK" || exit 1
    git init -q -b main
    for f in ci-ratchets debt-dashboard ci-commit-messages ci-parallel kit-config; do cp "$KIT/tools/$f.sh" tools/; done
    cp "$KIT/ci/checks.sh" ci/
    echo "1.0.0" > VERSION
    git add -A && git commit -q -m "kit v1" && git tag v1
    for f in tools/ci-ratchets.sh tools/ci-parallel.sh ci/checks.sh; do echo "# doctor-proof: a line the second version adds" >> "$f"; done
    echo "1.1.0" > VERSION
    git add -A && git commit -q -m "kit v2"
) > /dev/null 2>&1

# dx_project <folder> <revision>: scripts/ holds the kit's scripts of that revision
dx_project() {
    mkdir -p "$1/scripts"
    local f
    for f in tools/ci-ratchets.sh tools/debt-dashboard.sh tools/ci-commit-messages.sh tools/ci-parallel.sh tools/kit-config.sh ci/checks.sh; do
        git -C "$DXK" show "$2:$f" > "$1/scripts/$(basename "$f")"
    done
}

dx_project "$DX/p-ok" HEAD
dx_project "$DX/p-old" v1
dx_project "$DX/p-cfg" HEAD
sed -i.bak 's/^MAX_LINES=400$/MAX_LINES=300/' "$DX/p-cfg/scripts/debt-dashboard.sh" && rm "$DX/p-cfg/scripts/debt-dashboard.sh.bak"
dx_project "$DX/p-body" HEAD
echo 'echo "local edit"' >> "$DX/p-body/scripts/ci-commit-messages.sh"
dx_project "$DX/p-missing" HEAD
rm "$DX/p-missing/scripts/ci-commit-messages.sh"
dx_project "$DX/p-only" HEAD
cp "$KIT/ci/prepush.sh" "$DX/p-only/scripts/prepush.sh"
dx_project "$DX/p-key" HEAD
sed -i.bak 's/^MAX_LINES=400$/MAX_LINES=400\
OLD_KEY=1/' "$DX/p-key/scripts/debt-dashboard.sh" && rm "$DX/p-key/scripts/debt-dashboard.sh.bak"

check DX01 "doctor: copies equal to the kit pass, and the kit commit and version are named" 0 'matches kit v1\.1\.0 / commit [0-9a-f]+' dx_doctor --project "$DX/p-ok"
check DX02 "doctor: a changed config value only is the project's decision: exit 0, the key and both values listed" 0 'MAX_LINES = 300 +\(kit: 400\)' dx_doctor --project "$DX/p-cfg"
check DX03 "doctor: config-differs names the status of the file" 0 'debt-dashboard\.sh +config-differs' dx_doctor --project "$DX/p-cfg"
check DX04 "doctor: a body edited locally fails and says no kit commit has it" fail 'diverged: body edited locally' dx_doctor --project "$DX/p-body"
check DX05 "doctor: a body that differs shows a diff summary with line counts" fail 'the kit has 0 line\(s\) the project lacks, the project has 1 line\(s\) the kit lacks' dx_doctor --project "$DX/p-body"
check DX06 "doctor: a copy of the previous kit version is named as that version" fail 'matches kit v1\.0\.0 / commit [0-9a-f]+ \(1 kit commit' dx_doctor --project "$DX/p-old"
check DX07 "doctor: a missing required script fails" fail 'ci-commit-messages\.sh +missing' dx_doctor --project "$DX/p-missing"
check DX08 "doctor: a script the kit's folder does not have is project-only" fail 'prepush\.sh +project-only' dx_doctor --project "$DX/p-only"
check DX09 "doctor: a config key the kit does not have is a divergence" fail 'config keys only in the project .*: OLD_KEY' dx_doctor --project "$DX/p-key"
check DX10 "doctor --json: status per file and the divergence flag" fail '"name":"ci-commit-messages.sh","status":"missing"' dx_doctor --project "$DX/p-missing" --json
check DX11 "doctor --json: a project with only its own config values has diverged false" 0 '"diverged":false' dx_doctor --project "$DX/p-cfg" --json

# --upgrade-plan changes nothing
DX_SUM_BEFORE=$(cat "$DX"/p-old/scripts/* | cksum)
check DX12 "doctor --upgrade-plan: prints the steps, the tag and the code-owner review (exit 1: the project diverges)" fail 'tag \[ratchet: script\] <reason>' dx_doctor --project "$DX/p-old" --upgrade-plan
check DX13 "doctor --upgrade-plan: writes nothing without --apply" 0 '^same$' "$SHELL_BIN" -c "[ \"\$(cat '$DX'/p-old/scripts/* | cksum)\" = '$DX_SUM_BEFORE' ] && echo same || echo changed"

# --apply: the new body, the project's config value, ratchet row and task command stay
dx_project "$DX/p-apply" v1
sed -i.bak 's/^MAX_LINES=400$/MAX_LINES=300/' "$DX/p-apply/scripts/debt-dashboard.sh"
sed -i.bak 's/^SOURCE_PATHS=.*$/SOURCE_PATHS="src"/; s/^todo  *max/my-own-row                           max  grep   @SOURCE_PATHS  XYZZY\
&/' "$DX/p-apply/scripts/ci-ratchets.sh"
sed -i.bak 's/^    "Format::<formatter in check mode over the whole tree>"$/    "Format::vendor\/bin\/pint --test"/' "$DX/p-apply/scripts/checks.sh"
rm "$DX"/p-apply/scripts/*.bak
check DX14 "doctor --apply: changes only scripts/ and says every changed guarded file needs the tag and a code-owner review" 0 'Every changed guarded file needs the commit tag \[ratchet: script\] <reason> and a code-owner review' dx_doctor --project "$DX/p-apply" --upgrade-plan --apply
check DX15 "doctor --apply: afterwards the config value of the dashboard is still the project's, and nothing else differs" 0 'MAX_LINES = 300' dx_doctor --project "$DX/p-apply"
check DX16 "doctor --apply: the project's config value of ci-ratchets.sh is kept" 0 '^SOURCE_PATHS="src"$' cat "$DX/p-apply/scripts/ci-ratchets.sh"
check DX17 "doctor --apply: the project's own ratchet row is kept" 0 '^my-own-row +max +grep' cat "$DX/p-apply/scripts/ci-ratchets.sh"
check DX18 "doctor --apply: the project's task command of checks.sh is kept" 0 '"Format::vendor/bin/pint --test"' cat "$DX/p-apply/scripts/checks.sh"
check DX19 "doctor --apply: the line the new kit version adds is there" 0 'doctor-proof: a line the second version adds' cat "$DX/p-apply/scripts/ci-parallel.sh"
check DX20 "doctor: after --apply the project matches the current kit version" 0 'matches kit v1\.1\.0' dx_doctor --project "$DX/p-apply"
check DX21 "doctor --apply: the applied scripts still parse" 0 '^$' "$SHELL_BIN" -c "for f in '$DX'/p-apply/scripts/*.sh; do bash -n \"\$f\" || exit 1; done"

# run from the project's own scripts/ folder
cp "$KIT/tools/doctor.sh" "$DX/p-ok/scripts/doctor.sh"
check DX22 "doctor: run from the project as scripts/doctor.sh with --kit (the kit folder of the scratch kit has no doctor.sh: project-only)" fail 'doctor\.sh +project-only' "$SHELL_BIN" -c "cd '$DX/p-ok' && bash scripts/doctor.sh --kit '$DXK'"
check DX23 "doctor: without --kit and outside the kit it stops with a usage error" fail '--kit <path to the kit folder> is required' "$SHELL_BIN" -c "cd '$DX/p-ok' && bash scripts/doctor.sh"

# G-06: code in a config block is a divergence, and --apply puts the kit's block back
dx_project "$DX/p-code" HEAD
sed -i.bak 's/^# <<< config$/exit 0\
# <<< config/' "$DX/p-code/scripts/ci-ratchets.sh"
sed -i.bak 's/^REMOTE="origin"$/REMOTE="origin"; touch \/tmp\/doctor-proof-not-run/' "$DX/p-code/scripts/kit-config.sh"
sed -i.bak 's/^MAX_LINES=400$/MAX_LINES="$(id -u)"/' "$DX/p-code/scripts/debt-dashboard.sh"
rm "$DX"/p-code/scripts/*.bak
dx_project "$DX/p-ok2" HEAD
check DX24 "G-06: a bare line in a config block (exit 0) is reported, not read as identical" fail 'ci-ratchets\.sh +config-holds-code' dx_doctor --project "$DX/p-code"
check DX24b "G-06: the line itself is shown" fail '\| exit 0' dx_doctor --project "$DX/p-code"
check DX24c "G-06: a value with a trailing command is code, not only a config difference" fail 'kit-config\.sh +config-holds-code' dx_doctor --project "$DX/p-code"
check DX24d "G-06: a value with a command substitution is code" fail 'debt-dashboard\.sh +config-holds-code' dx_doctor --project "$DX/p-code"
check DX24e "G-06: --json says it too" fail '"name":"ci-ratchets.sh","status":"config-holds-code".*"config_holds_code":true' dx_doctor --project "$DX/p-code" --json
check DX24f "G-06: the kit's own scripts, with their multi-line and quoted values, are no code" 0 'Summary: 6 identical' dx_doctor --project "$DX/p-ok2"
check DX25 "G-06: --apply removes the code from the config blocks" 0 'Applied: scripts/ changed:' dx_doctor --project "$DX/p-code" --apply
check DX25b "G-06: after --apply no config block holds code" 0 'Summary: [0-9]+ identical, [0-9]+ config-differs, 0 body-differs, 0 missing, 0 project-only\.' dx_doctor --project "$DX/p-code"

# G-09: a shallow kit clone is named, and the commits behind count only commits to the scripts
git clone -q --depth 1 "file://$DXK" "$DX/kshallow" > /dev/null 2>&1
check DX26 "G-09: a shallow kit clone is named as the reason no kit version is found" 0 'the kit clone .* is shallow' "$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DX/kshallow" --project "$DX/p-ok2"
(cd "$DXK" && echo "docs only" > NOTES.md && git add -A && git commit -q -m "kit docs") > /dev/null 2>&1
check DX26b "G-09: a docs-only kit commit is not 'behind': the scripts equal the kit's current ones" 0 'the scripts equal the kit.s current ones' dx_doctor --project "$DX/p-ok2"

# G-10: the commands of the plan are copy-paste safe for a path with a space
mkdir -p "$DX/my proj"
dx_project "$DX/my proj" v1
check DX27 "G-10: the plan quotes a project path that holds a space" fail 'my\\ proj --upgrade-plan --apply' dx_doctor --project "$DX/my proj" --upgrade-plan

# F51: --apply carried neither a config key only the project has, nor the self-test cases of a row the project defined itself, and
# replaced a value that equals an older kit default without showing both. A project at v1 with: a key its own row reads (PROJ_FLOOR_FILE),
# its own row guide-future-tense (the kit has that row only in its EXTRA fixture) with its own cases.
dx_project "$DX/p-carry" v1
sed -i.bak 's/^SOURCE_PATHS=.*$/&\
PROJ_FLOOR_FILE="scripts\/floor.txt"/; s/^todo  *max  grep .*$/&\
proj-floor                             min  number @PROJ_FLOOR_FILE\
guide-future-tense                     max  grep   @SOURCE_PATHS  SHALL_ZZ/; /^guide-future-tense [01] /d; s/^todo 1 \/\/ TODO later$/&\
guide-future-tense 1 SHALL_ZZ is here\
guide-future-tense 0 shall/' "$DX/p-carry/scripts/ci-ratchets.sh"
rm -f "$DX"/p-carry/scripts/*.bak
cp -R "$DX/p-carry" "$DX/p-carry-apply"
dx_doctor --project "$DX/p-carry-apply" --apply > "$DX/carry-apply.out" 2>&1
check DX30 "F51: the project's own row and its own cases pass the self-test before the upgrade (the setup is sound)" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" -c "cd '$DX/p-carry' && bash scripts/ci-ratchets.sh --self-test"
check DX31 "F51: a key the project added and its own row reads is listed as the project's addition, not as a dropped one" fail 'config keys the project added .*: PROJ_FLOOR_FILE' dx_doctor --project "$DX/p-carry"
check DX32 "F51: --apply keeps the key the project added in the config block" 0 '^PROJ_FLOOR_FILE="scripts/floor.txt"$' cat "$DX/p-carry-apply/scripts/ci-ratchets.sh"
check DX33 "F51: --apply keeps the project's cases of its own row: the self-test of the upgraded script passes" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" -c "cd '$DX/p-carry-apply' && bash scripts/ci-ratchets.sh --self-test"
check DX34 "F51: --apply says which keys it kept" 0 'config keys only the project has are kept.*PROJ_FLOOR_FILE' cat "$DX/carry-apply.out"
check DX34b "F51: --apply names the rows whose EXTRA and CASES lines are the project's" 0 'rows the project defined itself are kept: .*guide-future-tense' cat "$DX/carry-apply.out"
check DX35 "F51: after --apply the project is not diverged" 0 'Diverged: no' dx_doctor --project "$DX/p-carry-apply"
check DX36 "F51: the kit's body is still there after --apply (the line the second version adds)" 0 'doctor-proof: a line the second version adds' cat "$DX/p-carry-apply/scripts/ci-ratchets.sh"
cp -R "$DX/p-key" "$DX/p-key-apply"
dx_doctor --project "$DX/p-key-apply" --apply > /dev/null 2>&1
check DX37 "F51: a key only the project has, which nothing reads, is kept by --apply too (and stays reported)" 0 '^OLD_KEY=1$' cat "$DX/p-key-apply/scripts/debt-dashboard.sh"

# A value that equals an older kit default is replaced, and the output shows both values and the way to keep one.
DXK2="$DX/kit2"
mkdir -p "$DXK2/tools" "$DXK2/ci"
(
    cd "$DXK2" || exit 1
    git init -q -b main
    for f in ci-ratchets debt-dashboard ci-commit-messages ci-parallel kit-config; do cp "$KIT/tools/$f.sh" tools/; done
    cp "$KIT/ci/checks.sh" ci/
    echo "1.0.0" > VERSION
    git add -A && git commit -q -m "kit v1" && git tag v1
    sed -i.bak 's/^OVERRIDE_AT_ZERO="\(.*\)"$/OVERRIDE_AT_ZERO="\1,dx-extra"/' tools/ci-ratchets.sh
    rm -f tools/*.bak
    echo "1.1.0" > VERSION
    git add -A && git commit -q -m "kit v2"
) > /dev/null 2>&1
mkdir -p "$DX/p-stale/scripts"
for f in tools/ci-ratchets.sh tools/debt-dashboard.sh tools/ci-commit-messages.sh tools/ci-parallel.sh tools/kit-config.sh ci/checks.sh; do
    git -C "$DXK2" show "v1:$f" > "$DX/p-stale/scripts/$(basename "$f")"
done
cp -R "$DX/p-stale" "$DX/p-keep"
sed -i.bak 's/^OVERRIDE_AT_ZERO="\(.*\)"$/OVERRIDE_AT_ZERO="\1" # kept on purpose: the owner pinned it/' "$DX/p-keep/scripts/ci-ratchets.sh"
rm -f "$DX"/p-keep/scripts/*.bak
"$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK2" --project "$DX/p-stale" --apply > "$DX/stale-apply.out" 2>&1
check DX38 "F51: --apply lists the replaced value as a conflict, with the project's value and the kit's" 0 'now: OVERRIDE_AT_ZERO=".*dx-extra' cat "$DX/stale-apply.out"
check DX38b "F51: the conflict block shows the project's earlier value too" 0 'was: OVERRIDE_AT_ZERO="comments' cat "$DX/stale-apply.out"
check DX38c "F51: the conflict block says how to keep the project's value" 0 'trailing comment with the reason' cat "$DX/stale-apply.out"
check DX39 "F51: a value with a trailing comment is the project's decision, not a value the kit changed" 0 '^0$' "$SHELL_BIN" -c "'$SHELL_BIN' '$KIT/tools/doctor.sh' --kit '$DXK2' --project '$DX/p-keep' | grep -c 'config values the kit changed' || true"

# F52: a kit upgrade widened a path list (the dashboard's SOURCE_FILES gained database/) and the open-classes row failed the project's
# gate in the same pull request. --apply runs the dashboard offline before and after and lists the rows that moved up. The scratch kit
# has v1 with a SOURCE_FILES that lacks database/, and v2 with the kit's own value.
DXK3="$DX/kit3"
mkdir -p "$DXK3/tools" "$DXK3/ci"
(
    cd "$DXK3" || exit 1
    git init -q -b main
    for f in ci-ratchets debt-dashboard ci-commit-messages ci-parallel kit-config; do cp "$KIT/tools/$f.sh" tools/; done
    cp "$KIT/ci/checks.sh" ci/
    sed -i.bak "s/^SOURCE_FILES='^(app|bootstrap|config|database|routes|lang)/SOURCE_FILES='^(app|bootstrap|config|routes|lang)/" tools/debt-dashboard.sh
    rm -f tools/*.bak
    echo "1.0.0" > VERSION
    git add -A && git commit -q -m "kit v1" && git tag v1
    cp "$KIT/tools/debt-dashboard.sh" tools/
    echo "1.1.0" > VERSION
    git add -A && git commit -q -m "kit v2"
) > /dev/null 2>&1
# dx_widen_project <folder> <database file: yes|no>: a git repository with the kit's v1 scripts and a class under app/ (and database/)
dx_widen_project() {
    mkdir -p "$1/scripts" "$1/app" "$1/database/seeders" "$1/bootstrap" "$1/config" "$1/routes" "$1/tests"
    local f
    for f in tools/ci-ratchets.sh tools/debt-dashboard.sh tools/ci-commit-messages.sh tools/ci-parallel.sh tools/kit-config.sh ci/checks.sh; do
        git -C "$DXK3" show "v1:$f" > "$1/scripts/$(basename "$f")"
    done
    cp "$KIT/tools/debt-caps.json" "$1/scripts/debt-caps.json"
    printf '<?php\nclass Alpha {}\n' > "$1/app/Alpha.php"
    [ "$2" != yes ] || printf '<?php\nclass Seeder {}\n' > "$1/database/seeders/Seeder.php"
    touch "$1/bootstrap/.gitkeep" "$1/config/.gitkeep" "$1/routes/.gitkeep" "$1/tests/.gitkeep" "$1/database/.gitkeep"
    (cd "$1" && git init -q -b main && git add -A && git -c user.email=a@b.c -c user.name=proof commit -q -m init) > /dev/null 2>&1
}
dx_widen_project "$DX/p-widen" yes
dx_widen_project "$DX/p-nowiden" no
mkdir -p "$DX/p-nogit/scripts"
for f in tools/ci-ratchets.sh tools/debt-dashboard.sh tools/kit-config.sh; do git -C "$DXK3" show "v1:$f" > "$DX/p-nogit/scripts/$(basename "$f")"; done
"$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK3" --project "$DX/p-widen" --apply > "$DX/widen-apply.out" 2>&1
"$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK3" --project "$DX/p-nowiden" --apply > "$DX/nowiden-apply.out" 2>&1
"$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK3" --project "$DX/p-nogit" --apply > "$DX/nogit-apply.out" 2>&1
check DX40 "F52: --apply lists the dashboard row that moved up because the kit widened SOURCE_FILES" 0 'open-classes 1 -> 2' cat "$DX/widen-apply.out"
check DX40b "F52: the list says to fix the rows in the same pull request" 0 'moved up after --apply.*fix each in this pull request' cat "$DX/widen-apply.out"
check DX41 "F52: a project the widening does not touch reads 'no row moved up'" 0 'no row moved up' cat "$DX/nowiden-apply.out"
check DX42 "F52: a project the dashboard cannot run in (no git repository) reads 'not run' with the reason" 0 'Dashboard after --apply: not run \(the project is not a git repository\)' cat "$DX/nogit-apply.out"
check DX43 "F52: the plan says that --apply runs the dashboard offline before and after" 0 'runs the dashboard offline \(--offline\) before and after' "$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK3" --project "$DX/p-widen" --upgrade-plan

# F53: a value of a counted config expression (REFACTOR_SETS, FORMATTER_RULES, ANALYSER_SWITCHES) that the project kept with a trailing
# reason comment is its own decision; the kit's self-test cases of that row describe the kit's value, so a case that counts
# differently under the kept value is reported, not failed. A value without the comment still has to fit the cases.
# dx_sets_project <folder> <comment: yes|no>: a project of kit v1 whose three expressions differ from the kit's (the prepared set
# earlyReturn added, rule strict_comparison and switch checkOctaneCompatibility removed).
dx_sets_project() {
    dx_project "$1" v1
    local tail=""
    [ "$2" != yes ] || tail=' # kept: our code uses the earlyReturn set, and we have no octane or strict comparison'
    sed -i.bak \
        -e "/^REFACTOR_SETS=/ { s/(deadCode|/(earlyReturn|deadCode|/; s/\"\$/\"$tail/; }" \
        -e "/^FORMATTER_RULES=/ { s/(strict_comparison|/(/; s/\"\$/\"$tail/; }" \
        -e "/^ANALYSER_SWITCHES=/ { s/checkOctaneCompatibility|//; s/\"\$/\"$tail/; }" \
        "$1/scripts/ci-ratchets.sh"
    rm -f "$1"/scripts/*.bak
}
dx_sets_project "$DX/p-sets-kept" yes
dx_sets_project "$DX/p-sets-bare" no
cp -R "$DX/p-sets-kept" "$DX/p-sets-kept-apply"
"$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$DXK" --project "$DX/p-sets-kept-apply" --apply > "$DX/sets-apply.out" 2>&1
check DX44 "F53: the setup changed the values (the self-test of the variant without a comment fails on refactor-sets)" fail 'refactor-sets counts 1, not 0: .*earlyReturn' "$SHELL_BIN" -c "cd '$DX/p-sets-bare' && bash scripts/ci-ratchets.sh --self-test"
check DX44b "F53: a value without a reason comment still has to fit the kit's cases (formatter-rules)" fail 'formatter-rules counts 0, not 1' "$SHELL_BIN" -c "cd '$DX/p-sets-bare' && bash scripts/ci-ratchets.sh --self-test"
check DX44c "F53: a value without a reason comment still has to fit the kit's cases (analyser-switches)" fail 'analyser-switches counts 0, not 1' "$SHELL_BIN" -c "cd '$DX/p-sets-bare' && bash scripts/ci-ratchets.sh --self-test"
check DX45 "F53: with the reason comment the self-test passes for all three counted values" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" -c "cd '$DX/p-sets-kept' && bash scripts/ci-ratchets.sh --self-test"
check DX45b "F53: the self-test names the values whose cases it did not fail" 0 'case\(s\) count differently under the config value\(s\) the project kept with a reason comment \(ANALYSER_SWITCHES REFACTOR_SETS FORMATTER_RULES\)' "$SHELL_BIN" -c "cd '$DX/p-sets-kept' && bash scripts/ci-ratchets.sh --self-test"
check DX46 "F53: after the doctor's --apply the kept values are still there" 0 'REFACTOR_SETS=".*earlyReturn.*" # kept' cat "$DX/p-sets-kept-apply/scripts/ci-ratchets.sh"
check DX46b "F53: after the doctor's --apply the self-test passes with no hand edit of the cases" 0 'Self-test: [0-9]+ cases run, 0 wrong' "$SHELL_BIN" -c "cd '$DX/p-sets-kept-apply' && bash scripts/ci-ratchets.sh --self-test"
check DX46c "F53: the upgraded script is the kit's second version, so the cases are the kit's" 0 'doctor-proof: a line the second version adds' cat "$DX/p-sets-kept-apply/scripts/ci-ratchets.sh"
# A project's own cases file stays strict: it describes the project's value, so a wrong count in it fails even with the comment.
printf 'refactor-sets 0     ->withPreparedSets(earlyReturn: true)\n' > "$DX/sets-own-cases.txt"
check DX47 "F53: a case of the project's own cases file that counts differently still fails" fail 'refactor-sets counts 1, not 0' "$SHELL_BIN" -c "cd '$DX/p-sets-kept' && bash scripts/ci-ratchets.sh --self-test '$DX/sets-own-cases.txt'"
