# Scenarios PT*: the findings of the first real GitHub run that concern the tools (feedback items 11 and 13 to 18).
# Sourced by ../proof.sh with its helpers in scope (check, check_php, branch, commit, edit, build_fixture, KIT, ROOT, SHELL_BIN). It
# builds its own scratch repository under "$ROOT/pt" and does not use the fixtures of proof.sh.

PT_SAVED_ROOT="$ROOT"
ROOT="$PT_SAVED_ROOT/pt"
mkdir -p "$ROOT"
build_fixture

# ---------------------------------------------------------------- item 11: frontend-test-skip and a PHP helper called fit
branch pt-fit
mkdir -p resources/js/domain
printf 'const x = self::fit($panel);\nconst y = Foo::fit(1);\nconst z = $this->fit(2);\n' > resources/js/domain/helper.ts
commit "PROJ-9 chore: a helper that is called fit, in the PHP way"
check_php PT01 "item 11: self::fit(, Foo::fit( and ->fit( are method calls, not a focused test" 0 '^frontend-test-skip +max +0$' "$SHELL_BIN" scripts/ci-ratchets.sh --head
printf 'fit("x", () => {});\n' > resources/js/domain/focused.test.ts
commit "PROJ-9 test: a real focused test (control)"
check_php PT02 "item 11: a bare fit( is still a focused test (control)" 0 '^frontend-test-skip +max +1$' "$SHELL_BIN" scripts/ci-ratchets.sh --head

# ---------------------------------------------------------------- item 13: FRONTEND_LOGIC counts every non-component TypeScript file
branch pt-logic
mkdir -p resources/js/domain/orders resources/js/pages
printf 'export const a = 1;\n' > resources/js/domain/orders/pricing.ts
printf 'export const b = 1;\n' > resources/js/domain/orders/pricing.test.ts
printf 'export default function Page() { return null; }\n' > resources/js/pages/home.tsx
commit "PROJ-9 chore: logic in a domain folder"
check PT03 "item 13: a .ts file in a domain folder (not hooks, utils, lib) is a frontend logic file" 0 'resources/js/domain/orders/pricing\.ts' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --counted | sed -n "/^Counted: frontend logic/,/^$/p"'
check PT04 "item 13: a test file and a component are not logic" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --counted | sed -n "/^Counted: frontend logic/,/^$/p" | grep -c -E "pricing\.test\.ts|home\.tsx"; exit 0'
check PT05 "item 13: every run prints how many files the test rows counted, without a flag" 0 '^The test rows counted: [0-9]+ behavior classes, [0-9]+ support classes, [0-9]+ frontend logic files, [0-9]+ frontend components' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline; exit 0'
check PT06 "item 13: --counted lists the components too" 0 'resources/js/pages/home\.tsx' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh --offline --counted | sed -n "/^Counted: frontend components/,/^$/p"'

# ---------------------------------------------------------------- item 14: a .git file that points outside this machine's paths
PT_WT="$ROOT/worktree-seen-from-elsewhere"
mkdir -p "$PT_WT/scripts"
cp "$KIT/tools/kit-config.sh" "$KIT/tools/ci-ratchets.sh" "$KIT/tools/debt-dashboard.sh" "$PT_WT/scripts/"
printf 'gitdir: /host/only/path/repo/.git/worktrees/x\n' > "$PT_WT/.git"
check PT07 "item 14: a .git file that points to a missing folder stops the ratchet script with the reason" fail 'points to /host/only/path/repo/.git/worktrees/x, which does not exist here.*run the kit scripts on the host' "$SHELL_BIN" -c "cd '$PT_WT' && bash scripts/ci-ratchets.sh --head"
check PT08 "item 14: the dashboard stops the same way, exit 64" 0 '^64$' "$SHELL_BIN" -c "cd '$PT_WT/scripts' && bash debt-dashboard.sh --offline > /dev/null 2>&1; echo \$?"
printf 'gitdir: %s/work/.git\n' "$ROOT" > "$PT_WT/.git"
check PT09 "item 14: a .git file that points to a folder that exists passes the check (control)" 0 '^ok$' "$SHELL_BIN" -c "cd '$PT_WT' && . scripts/kit-config.sh && echo ok"
command rm -f "$PT_WT/.git"

# ---------------------------------------------------------------- item 15: Infection needs its Composer plugin allowed
PT_MU="$ROOT/infection"
mkdir -p "$PT_MU/scripts" "$PT_MU/vendor/bin"
cp "$KIT/tools/mutation.sh" "$KIT/tools/kit-config.sh" "$PT_MU/scripts/"
sed -i.bak 's/<pest, infection or stryker>/infection/; s/<source folders>/app/' "$PT_MU/scripts/mutation.sh"
printf 'threshold: 70\n' > "$PT_MU/scripts/mutation-floor.txt"
printf '#!/bin/sh\necho "infection $*"\n' > "$PT_MU/vendor/bin/infection"
chmod +x "$PT_MU/vendor/bin/infection"
printf '{"require-dev": {"infection/infection": "^0.35"}}\n' > "$PT_MU/composer.json"
check PT10 "item 15: infection without allow-plugins for infection/extension-installer stops with the fix" fail 'composer config allow-plugins\.infection/extension-installer true.*never pipe' "$SHELL_BIN" -c "cd '$PT_MU' && bash scripts/mutation.sh"
printf '{"require-dev": {"infection/infection": "^0.35"}, "config": {"allow-plugins": {"infection/extension-installer": true}}}\n' > "$PT_MU/composer.json"
check PT11 "item 15: with the plugin allowed the run reaches infection (control)" 0 '^infection --threads=max --min-msi=70$' "$SHELL_BIN" -c "cd '$PT_MU' && bash scripts/mutation.sh"

# ---------------------------------------------------------------- item 16: a repository younger than the docs age limit
branch pt-young
check_php PT12 "item 16: a repository whose first commit is younger than the limit says the docs row cannot fire yet" 0 'docs-older-than-limit cannot fire before 20[0-9-]+: the first commit of this repository is newer than 180 days' dashboard
ROOT_TREE=$(git hash-object -t tree /dev/null)
PT_OLD=$(GIT_AUTHOR_DATE="2020-01-01T00:00:00" GIT_COMMITTER_DATE="2020-01-01T00:00:00" git commit-tree "$ROOT_TREE" -m "PROJ-1 chore: the first commit")
git merge -q --allow-unrelated-histories -m "PROJ-1 chore: join an old history" "$PT_OLD"
check_php PT13 "item 16: with a first commit older than the limit there is no such line (control)" 0 '^0$' "$SHELL_BIN" -c 'bash scripts/debt-dashboard.sh | grep -c "cannot fire"; exit 0'

# ---------------------------------------------------------------- item 17: a counted list into a row that is at 0
branch pt-lint
echo '{"resources/js/a.ts": {"no-console": {"count": 2}, "eqeqeq": {"count": 1}}}' > eslint-suppressions.json
commit "PROJ-9 chore: the first strict lint list, in the row that stands at 0"
check_php PT14 "item 17: the list in a row that stands at 0 is a move 0 to 3 and fails, with no override at 0 (control)" fail '^lint-baseline +down +0 +3 +\+3 +FAILS' ratchets
branch pt-rename
echo '{"resources/js/a.ts": {"no-console": {"count": 2}, "eqeqeq": {"count": 1}}}' > eslint-suppressions.json
edit scripts/ci-ratchets.sh 's/^lint-baseline( +)max/lint-suppressions\1max/'
commit "PROJ-9 chore: the lint list under a new row name"
check_php PT15 "item 17: the renamed row is a new row at its first value, the old one is removed at 0" 0 '^lint-suppressions +down +0 +3 +\+3 +new row' ratchets
check_php PT16 "item 17: the old name is a removal at 0, which takes no tag of its own" 0 '^lint-baseline +- +0 +- +- +removed at 0' ratchets
branch pt-rename-untagged
echo '{"resources/js/a.ts": {"no-console": {"count": 2}}}' > eslint-suppressions.json
edit scripts/ci-ratchets.sh 's/^lint-baseline( +)max/lint-suppressions\1max/'
commit "PROJ-9 chore: the same rename with no tag at all"
check_php PT17 "item 17: the rename takes no tag: the removal is at 0 and the new row is a first measurement" 0 '^lint-suppressions +down +0 +2 +\+2 +new row' ratchets

cd "$PT_SAVED_ROOT" || exit 1
ROOT="$PT_SAVED_ROOT"
