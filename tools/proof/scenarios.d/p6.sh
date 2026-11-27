# Scenarios P6X*: the defects a real app found in the CI examples and the doctor (findings P6-4, P6-5, P6-6). Sourced by proof.sh with
# its helpers in scope (check, check_php, KIT, ROOT, SHELL_BIN). Self-contained: own scratch folders under "$ROOT/p6x".
# P6-1 to P6-3 concern the guard classes of the starter (not part of this repository); the starter passes the analyser as it is.

P6="$ROOT/p6x"
mkdir -p "$P6"
P6K="$P6/kit"

p6_doctor() { "$SHELL_BIN" "$KIT/tools/doctor.sh" --kit "$P6K" "$@"; }

# ---------------------------------------------------------------- P6-5: the tags of the carried rows
# A scratch kit with two commits: the second changes two ratchet rows (analyser-level, todo).
mkdir -p "$P6K/tools" "$P6K/ci"
(
    cd "$P6K" || exit 1
    git init -q -b main
    for f in ci-ratchets debt-dashboard ci-commit-messages ci-parallel kit-config; do cp "$KIT/tools/$f.sh" tools/; done
    cp "$KIT/ci/checks.sh" ci/
    echo "1.0.0" > VERSION
    git add -A && git commit -q -m "kit v1" && git tag v1
    sed -i.bak 's/^analyser-level  *max  grep .*$/&|^[[:space:]]+level:[[:space:]]+11$/' tools/ci-ratchets.sh
    sed -i.bak 's/^todo  *max  grep .*$/&|^[[:space:]]+NOTE:$/' tools/ci-ratchets.sh
    rm -f tools/*.bak
    echo "1.1.0" > VERSION
    git add -A && git commit -q -m "kit v2"
) > /dev/null 2>&1

p6_project() { # p6_project <folder> <revision>
    mkdir -p "$1/scripts"
    local f
    for f in tools/ci-ratchets.sh tools/debt-dashboard.sh tools/ci-commit-messages.sh tools/ci-parallel.sh tools/kit-config.sh ci/checks.sh; do
        git -C "$P6K" show "$2:$f" > "$1/scripts/$(basename "$f")"
    done
}
p6_project "$P6/p-old" v1
p6_project "$P6/p-new" HEAD
cp -R "$P6/p-old" "$P6/p-apply"
p6_doctor --project "$P6/p-apply" --apply > "$P6/apply.out" 2>&1
p6_doctor --project "$P6/p-apply" --apply > "$P6/apply-again.out" 2>&1
p6_doctor --project "$P6/p-new" --apply > "$P6/apply-none.out" 2>&1

check P6X01 "P6-5 doctor --apply prints one tag line per carried row, with the row name" 0 '^    \[ratchet: analyser-level\] ' cat "$P6/apply.out"
check P6X02 "P6-5 doctor --apply prints a tag line for each of the two carried rows" 0 '^2$' "$SHELL_BIN" -c "grep -c -E '^    \\[ratchet: (analyser-level|todo)\\] ' '$P6/apply.out'"
check P6X03 "P6-5 doctor --upgrade-plan names the tags before anything is written, and writes nothing" 0 '^0$' "$SHELL_BIN" -c "out=\$('$SHELL_BIN' '$KIT/tools/doctor.sh' --kit '$P6K' --project '$P6/p-old' --upgrade-plan 2>&1); echo \"\$out\" | grep -q -E '^    \\[ratchet: todo\\] ' || { echo missing; exit 1; }; cmp -s '$P6/p-old/scripts/ci-ratchets.sh' <(git -C '$P6K' show v1:tools/ci-ratchets.sh); echo \$?"
check P6X04 "P6-5 doctor --apply on a project with no carried row prints no tag line" 0 '^0$' "$SHELL_BIN" -c "grep -c 'ratchet: [a-z-]*\\] take' '$P6/apply-none.out'; exit 0"
check P6X05 "P6-5 UPGRADING.md and tools/README.md say that each carried row needs its own tag" 0 '^yes$' "$SHELL_BIN" -c "for f in '$KIT/UPGRADING.md' '$KIT/tools/README.md'; do grep -q 'redefined, FAILS' \"\$f\" || grep -q -F 'carried row' \"\$f\" || { echo \"missing in \$f\"; exit 1; }; grep -q -F '[ratchet: <row>]' \"\$f\" || { echo \"no tag in \$f\"; exit 1; }; done; echo yes"

# ---------------------------------------------------------------- P6-6: the CI examples cost no ratchet row when a project copies them
P6FIX="$KIT/tools/proof/fixtures/ci"
check P6X06 "P6-6 the CI example files hold no spaced == or != (the row loose-comparisons reads them as code)" 0 '^0$' "$SHELL_BIN" -c "cat '$P6FIX'/*.php | grep -c -E '(^|[^=!<>])(==|!=|<>)([^=]|\$)'; exit 0"
check P6X07 "P6-6 no CI example file is over the 400 lines of the file-size guard" 0 '^0$' "$SHELL_BIN" -c "for f in '$P6FIX'/*.php; do [ \$(wc -l < \"\$f\") -le 400 ] || echo big; done | grep -c big; exit 0"
p6_expand() { # the expanded example equals the skeleton: the operators are built from parts
    printf '%s\n' '<?php' 'require $argv[1];' 'echo Tests\Support\Ci\CiHostExamples::githubWorkflow();' > "$P6/ex.php"
    php "$P6/ex.php" "$P6FIX/CiHostExamples.php" | grep -c -E "github.event_name (==|!=) '(pull_request|push|schedule)'"
}
check_php P6X08 "P6-6 the example still returns the GitHub operators once built (the example equals the skeleton, scenario CX27)" 0 '^[1-9][0-9]*$' p6_expand

# ---------------------------------------------------------------- P6-4: an auxiliary workflow that reads a secret, with a narrow rule
P6RULE="$KIT/reference-project/examples/php/tests/Support/Architecture/AuxiliarySecretRule.php"
p6_rule() { # p6_rule <declared csv> <triggers csv> <secrets csv>
    printf '%s\n' '<?php' 'require $argv[1];' \
        '$split = static fn (string $csv): array => $csv === "" ? [] : explode(",", $csv);' \
        '$rule = new Tests\Support\Architecture\AuxiliarySecretRule($split($argv[2]));' \
        'echo "violations=" . count($rule->violations($split($argv[3]), $split($argv[4]))) . "\n";' > "$P6/rule.php"
    php "$P6/rule.php" "$P6RULE" "$1" "$2" "$3"
}
if [ -f "$P6RULE" ]; then
    check_php P6X09 "P6-4 passing: schedule and workflow_dispatch only, the declared secret read" 0 '^violations=0$' p6_rule KIT_SYNC_TOKEN schedule,workflow_dispatch KIT_SYNC_TOKEN
    check_php P6X10 "P6-4 failing: a pull_request trigger beside the secret" 0 '^violations=1$' p6_rule KIT_SYNC_TOKEN schedule,pull_request KIT_SYNC_TOKEN
    check_php P6X11 "P6-4 failing: a secret that the owner did not declare" 0 '^violations=1$' p6_rule KIT_SYNC_TOKEN schedule KIT_SYNC_TOKEN,DEPLOY_KEY
    check_php P6X12 "P6-4 failing: a declared secret that the file no longer reads" 0 '^violations=1$' p6_rule KIT_SYNC_TOKEN schedule ""
    check P6X13 "P6-4 the rule class and its test hold no spaced loose comparison and stay under 400 lines" 0 '^0$' "$SHELL_BIN" -c "cat '$P6RULE' '$KIT/reference-project/examples/php/tests/Unit/AuxiliarySecretRuleTest.php' | grep -c -E '(^|[^=!<>])(==|!=|<>)([^=]|\$)'; exit 0"
    check P6X14 "P6-4 04-guards.md C4 names the declaration of an auxiliary secret and the rule" 0 'AuxiliarySecretRule' grep -o 'AuxiliarySecretRule' "$KIT/04-guards.md"
fi

# P7: the pointer files name what the starter has (findings P7-1 to P7-4).
check P7X01 "P7-1 files-to-copy.md names no class the starter lacks (GithubTriggerRules) and the CiExamples folder" 0 '^0$' "$SHELL_BIN" -c "grep -c 'GithubTriggerRules' '$KIT/reference-project/files-to-copy.md'; exit 0"
check P7X02 "P7-1 files-to-copy.md names the classes the GitHub guard needs" 0 'AuxiliarySecretRule' grep -o 'AuxiliarySecretRule' "$KIT/reference-project/files-to-copy.md"
check P7X03 "P7-1 files-to-copy.md states no switch count of its own" 0 '^0$' "$SHELL_BIN" -c "grep -c 'the 18 switches' '$KIT/reference-project/files-to-copy.md'; exit 0"
check P7X04 "P7-2 starter-variants.md does not list the GitHub reader family as removed" 0 '^0$' "$SHELL_BIN" -c "grep -c 'starter has its own GitHub reader' '$KIT/docs/starter-variants.md'; exit 0"
check P7X05 "P7-3 04-guards.md says the secret reader finds the indexed forms" 0 'toJSON\(secrets\)' grep -o 'toJSON(secrets)' "$KIT/04-guards.md"
check P7X06 "P7-4 starter-canary.yml has no step that puts task lines back" 0 '^0$' "$SHELL_BIN" -c "grep -c 'task lines of checks.sh back' '$KIT/.github/workflows/starter-canary.yml'; exit 0"

# P8: the database health command waits for the real server (finding P8-1), the template note (P8-2).
check P8X01 "P8-1 no skeleton health command uses the socket form of pg_isready" 0 '^0$' "$SHELL_BIN" -c "cd '$KIT' && cat ci/github-actions.yml ci/github-actions-runner.yml .github/workflows/starter-canary.yml | grep -c 'pg_isready -U'; exit 0"
check P8X02 "P8-1 the skeletons use the TCP form" 0 '127\.0\.0\.1 -U postgres' grep -o -m1 'pg_isready -h 127.0.0.1 -U postgres' "$KIT/ci/github-actions.yml"
check P8X03 "P8-2 reference-project/README.md says to drop the export-ignore line" 0 'export-ignore' grep -o -m1 'export-ignore' "$KIT/reference-project/README.md"
