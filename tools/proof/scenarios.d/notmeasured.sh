# proof-needs: main
# Scenarios NMD*: a dashboard row whose measurement needs node_modules or vendor says "not measured" when that folder is missing or
# empty, never 0 (an auditor with no node_modules read dependencies-majors-behind as 0 and nearly lowered a cap on it). Sourced by
# proof.sh with its helpers in scope (check_php, branch, edit, dashboard, ROOT, SHELL_BIN). The package manager is a fake that
# prints an empty list, as npm outdated does without node_modules.

NM_CWD="$PWD"
NM="$ROOT/nm"
mkdir -p "$NM/bin"
printf '#!/bin/sh\necho "{}"\n' > "$NM/bin/npm"
printf '#!/bin/sh\necho "{}"\n' > "$NM/bin/composer"

nm_dashboard() { PATH="$NM/bin:$PATH" "$SHELL_BIN" scripts/debt-dashboard.sh "$@"; }

branch nm-node
edit scripts/debt-dashboard.sh "s#cat fixtures/majors.json \| json 'count locked'#npm outdated --json | json 'count .'#"
check_php NMD01 "dashboard: a row that runs npm outdated with no node_modules is not measured, not 0" fail '^command +dependencies-majors-behind +\? +[0-9?]+ +UNKNOWN \(not measured: node_modules is missing' nm_dashboard --offline
mkdir -p node_modules
check_php NMD02 "dashboard: an empty node_modules is no measurement either" fail 'not measured: node_modules is missing or empty' nm_dashboard --offline
mkdir -p node_modules/pkg
check_php NMD03 "dashboard: with node_modules installed the same row is measured (control)" 0 '^command +dependencies-majors-behind +0 +0 +ok' nm_dashboard --offline
command rm -rf node_modules
edit scripts/debt-caps.json 's/"dependencies-majors-behind": 0/"dependencies-majors-behind": 3/'
check_php NMD04 "dashboard: a cap is never lowered from a row that is not measured: no 'lower the cap' hint on the false zero" 0 '^0$' "$SHELL_BIN" -c "PATH='$NM/bin':\$PATH bash scripts/debt-dashboard.sh --offline | grep -c 'lower the cap to 0'; true"
check_php NMD05 "dashboard --final: a row that is not measured fails, it is not a pass" fail 'not measured: node_modules is missing' nm_dashboard --final --offline
git checkout -q -- scripts/debt-dashboard.sh scripts/debt-caps.json

branch nm-vendor
edit scripts/debt-dashboard.sh "s#cat fixtures/majors.json \| json 'count locked'#cat vendor/bin/outdated.txt 2>/dev/null | grep -c .#"
check_php NMD06 "dashboard: a row that reads vendor/bin with no vendor folder is not measured" fail '^command +dependencies-majors-behind +\? +[0-9?]+ +UNKNOWN \(not measured: vendor is missing' dashboard --offline
mkdir -p vendor/bin
check_php NMD07 "dashboard: with a vendor folder the same row is measured (control)" 0 '^command +dependencies-majors-behind +0 +0 +ok' dashboard --offline
command rm -rf vendor
git checkout -q -- scripts/debt-dashboard.sh

branch nm-locked
edit scripts/debt-dashboard.sh "s#cat fixtures/majors.json \| json 'count locked'#cat fixtures/majors.json | json 'count locked' \# composer outdated --locked#"
check_php NMD08 "dashboard: a row that reads the lock file needs no vendor folder (control)" 0 '^command +dependencies-majors-behind +0 +0 +ok' nm_dashboard --offline
git checkout -q -- scripts/debt-dashboard.sh

branch nm-sum
edit scripts/debt-dashboard.sh "s#cat fixtures/majors.json \| json 'count locked'#echo \$(( \$(cat fixtures/majors.json | json 'count locked') + \$(pnpm outdated --json | json 'count .') ))#"
check_php NMD09 "dashboard: a sum over two package managers is not measured when one is not installed (empty reads as 0 in the sum)" 0 'not measured: pnpm is not installed|^pnpm is installed here' "$SHELL_BIN" -c "if command -v pnpm > /dev/null 2>&1; then echo 'pnpm is installed here'; else bash scripts/debt-dashboard.sh --offline; fi; exit 0"
git checkout -q -- scripts/debt-dashboard.sh

cd "$NM_CWD" || exit 1

# The README states the number of self-test cases the script prints.
check DOCX08 "docs: the README names the number of self-test cases that the script prints" 0 'same' "$SHELL_BIN" -c "n=\$(bash '$KIT/tools/ci-ratchets.sh' --self-test 2>&1 | grep -o '[0-9]* cases run' | grep -o '^[0-9]*'); grep -q \"runs \$n regex cases\" '$KIT/README.md' && echo same || echo \"README differs from \$n\""
