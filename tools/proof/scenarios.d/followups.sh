# proof-needs: main
# Scenarios FX*: the cross-area follow-ups of the CI audit (the self-test in the gate part, the base of mutation.sh, the code-owners
# files as guarded files). Sourced by ../proof.sh with its helpers in scope (check, check_php, branch, commit, edit, ROOT, KIT, SHELL_BIN).
# Own scratch repositories under "$ROOT"; the fixture's main branch is put back at the end.

# ---------------------------------------------------------------- the self-test runs in the gate part of checks.sh (C-ci)
FX_WORK="$ROOT/fx-work"
if [ -d "$CX_BARE" ] && [ -f "$KIT/ci/checks.sh" ]; then
    git clone -q "file://$CX_BARE" "$FX_WORK" > /dev/null 2>&1
    # A ratchet script that prints the arguments it was given, and fails its self-test when STUB_FAIL is set.
    printf 'case "${1:-}" in --self-test) echo "stub self-test"; [ -z "${STUB_FAIL:-}" ] || exit 1 ;; *) echo "stub ratchets $*" ;; esac\n' > "$FX_WORK/scripts/ci-ratchets.sh"
    fx() { (cd "$FX_WORK" && "$@"); }
    check FX01 "checks.sh --part gate runs ci-ratchets.sh --self-test after the ratchets" 0 'stub self-test' fx env CHECK_BASE=main "$SHELL_BIN" scripts/checks.sh --part gate
    check FX02 "checks.sh --part gate fails when the self-test fails" fail 'stub self-test' fx env CHECK_BASE=main STUB_FAIL=1 "$SHELL_BIN" scripts/checks.sh --part gate
    check FX03 "checks.sh --part gate: the order is the base's guard, the ratchets, the self-test, the dashboard" 0 '^stub ratchets,stub ratchets,stub self-test,stub dashboard$' fx "$SHELL_BIN" -c "env CHECK_BASE=main bash scripts/checks.sh --part gate | grep -E '^stub (ratchets|self-test|dashboard)' | sed -E 's/^(stub [a-z-]+).*/\1/' | tr '\n' ',' | sed 's/,\$//'"
    check FX04 "checks.sh --part static does not run the self-test (it belongs to the gate)" 0 '^0$' fx "$SHELL_BIN" -c "env CHECK_BASE=main bash scripts/checks.sh --part static | grep -c 'stub self-test'; exit 0"
    check FX05 "checks.sh --main --part gate runs the self-test too (it reads no base)" 0 'stub self-test' fx env CI=true "$SHELL_BIN" scripts/checks.sh --main --part gate
fi

# ---------------------------------------------------------------- mutation.sh --changed resolves the base like the other scripts (C-13)
FX_MUT_BARE="$ROOT/fx-mut-origin.git"
FX_MUT="$ROOT/fx-mut"
git init -q --bare -b main "$FX_MUT_BARE"
git init -q -b main "$FX_MUT"
(
    cd "$FX_MUT" || exit 1
    git remote add origin "file://$FX_MUT_BARE"
    mkdir -p scripts app vendor/bin
    cp "$KIT/tools/kit-config.sh" "$KIT/tools/mutation.sh" scripts/
    cp scripts/mutation.sh scripts/mutation-infection.sh
    edit scripts/mutation.sh 's/^MUTATION_TOOL=.*$/MUTATION_TOOL="pest"/; s/^MUTATION_SOURCE_FOLDERS=.*$/MUTATION_SOURCE_FOLDERS="app"/'
    edit scripts/mutation-infection.sh 's/^MUTATION_TOOL=.*$/MUTATION_TOOL="infection"/; s/^MUTATION_SOURCE_FOLDERS=.*$/MUTATION_SOURCE_FOLDERS="app"/'
    printf 'threshold: 50\n' > scripts/mutation-floor.txt
    printf '#!/bin/sh\necho "pest $*"\n' > vendor/bin/pest
    printf '#!/bin/sh\necho "infection $*"\n' > vendor/bin/infection
    chmod +x vendor/bin/pest vendor/bin/infection
    printf '<?php\n' > app/A.php
    git add -A
    git commit -q -m "base"
    git push -q origin main
    git checkout -q -b release
    printf '<?php\n' > app/R.php
    git add -A
    git commit -q -m "release only"
    git push -q origin release
    git checkout -q -b feature
    printf '<?php\n' > app/F.php
    git add -A
    git commit -q -m "feature"
) > /dev/null 2>&1
fxm() { (cd "$FX_MUT" && "$@"); }
check FX10 "mutation.sh --changed (pest): CHECK_BASE names the base, only the files changed since release are mutated" 0 '^pest --mutate --parallel --path=app/F\.php --min=50$' fxm env CHECK_BASE=release "$SHELL_BIN" scripts/mutation.sh --changed
check FX11 "mutation.sh --changed (pest): without CHECK_BASE the host's variable (GITHUB_BASE_REF) is the base" 0 '^pest --mutate --parallel --path=app/F\.php --min=50$' fxm env GITHUB_BASE_REF=release "$SHELL_BIN" scripts/mutation.sh --changed
check FX12 "mutation.sh --changed (pest): the variable named last in BASE_BRANCH_VARS works too (GitLab)" 0 '^pest --mutate --parallel --path=app/F\.php --min=50$' fxm env CI_MERGE_REQUEST_TARGET_BRANCH_NAME=release "$SHELL_BIN" scripts/mutation.sh --changed
check FX13 "mutation.sh --changed (pest): CHECK_BASE wins over the host's variable" 0 '^pest --mutate --parallel --path=app/F\.php,app/R\.php --min=50$' fxm env CHECK_BASE=main GITHUB_BASE_REF=release "$SHELL_BIN" scripts/mutation.sh --changed
check FX14 "mutation.sh --changed (pest): with no variable the default branch is the base" 0 '^pest --mutate --parallel --path=app/F\.php,app/R\.php --min=50$' fxm "$SHELL_BIN" scripts/mutation.sh --changed
check FX15 "mutation.sh --changed (infection): the host's variable reaches --git-diff-base" 0 'infection .*--git-diff-base=origin/release$' fxm env BITBUCKET_PR_DESTINATION_BRANCH=release "$SHELL_BIN" scripts/mutation-infection.sh --changed
check FX16 "mutation.sh --changed (infection): CHECK_BASE wins and the default is the fallback" 0 'infection .*--git-diff-base=origin/main$' fxm "$SHELL_BIN" scripts/mutation-infection.sh --changed

# ---------------------------------------------------------------- the code-owners files are guarded files (C-ci, code owners)
cd "$ROOT/work" || exit 1
git checkout -q -f main
FX_MAIN_SHA=$(git rev-parse HEAD)
mkdir -p .github
printf '* @owner\n' > .github/CODEOWNERS
commit "PROJ-9 chore: a code-owners file on the base"
git push -q origin main
branch fx30
printf 'scripts/ @someone-else\n' > .github/CODEOWNERS
commit "PROJ-9 chore: change the owners"
check_php FX30 "ratchets: editing .github/CODEOWNERS without the script tag fails" fail 'Script: \.github/CODEOWNERS changed\. FAILS' ratchets
printf 'scripts/ @someone-else\n.github/ @someone-else\n' > .github/CODEOWNERS
commit "PROJ-9 chore: change the owners again

[ratchet: script] the owners of scripts change"
check_php FX31 "ratchets: the same edit with [ratchet: script] passes" 0 'Script: \.github/CODEOWNERS changed, allowed' ratchets
git checkout -q -f main
git reset -q --hard "$FX_MAIN_SHA"
git push -q -f origin main
branch fx32
printf '* @owner\n' > CODEOWNERS
commit "PROJ-9 chore: a new code-owners file"
check_php FX32 "ratchets: a code-owners file that is not on the base yet is no change of a guarded file" 0 '^Ratchets against' ratchets
git checkout -q -f main
git branch -D fx30 fx32 > /dev/null 2>&1

# ---------------------------------------------------------------- the self-test of a project that reads another manifest key (the gate runs it)
mkdir -p "$ROOT/fx-st"
cp "$KIT/tools/kit-config.sh" "$ROOT/fx-st/kit-config.sh"
sed 's/^audit-ignore \(.*\) count? config.audit.ignore$/audit-ignore \1 count? auditIgnore/' "$KIT/tools/ci-ratchets.sh" > "$ROOT/fx-st/ci-ratchets.sh"
check FX20 "self-test: a project whose audit-ignore row reads another key (package.json) skips the composer cases and passes" 0 'Self-test: [0-9]+ cases run, 0 wrong, 2 skipped' "$SHELL_BIN" -c "cd '$ROOT' && '$SHELL_BIN' fx-st/ci-ratchets.sh --self-test"
