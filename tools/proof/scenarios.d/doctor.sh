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
