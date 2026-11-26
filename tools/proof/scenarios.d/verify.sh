# Scenarios VX*: the tool formats and commands checked against RECORDED OUTPUT of the real tools. The fixtures in
# tools/proof/fixtures/real/ are the output of knip 6.48.0, TypeScript 7.0.2, Stryker 10.1.1, Infection 0.35.12 and Pest 5.3.7,
# recorded on 2026-11-26 in scratch projects (paths replaced by /project); nothing here is invented output.
# Sourced by proof.sh with its helpers in scope (check, ROOT, KIT, HERE, SHELL_BIN).

VX="$ROOT/vx"
FX="$HERE/fixtures/real"
mkdir -p "$VX"
vx_kb() { bash "$KIT/tools/keyed-baseline.sh" "$@"; }

# ---------------------------------------------------------------- keyed-baseline.sh --format knip (knip 6.48.0)
check VX01 "knip format: the real findings of all kinds are written, 11 lines" 0 'rewritten, 11 lines' vx_kb "$VX/knip.txt" --init --format knip -- cat "$FX/knip-6.48.0-findings.out"
check VX02 "knip format: a padded last column leaves no position in the key (trailing space before file:line:col)" 0 '^Unused exports: unusedExport src/lib\.ts$' cat "$VX/knip.txt"
check VX03 "knip format: no line of the baseline holds a position" 0 '^none$' "$SHELL_BIN" -c "! grep -qE ':[0-9]+:[0-9]+' '$VX/knip.txt' && echo none"
check VX04 "knip format: the same real output compares equal to the file it wrote" 0 '11 findings, the same as' vx_kb "$VX/knip.txt" --format knip -- cat "$FX/knip-6.48.0-findings.out"
check VX05 "knip format: the Configuration hints section is no finding" 0 '^none$' "$SHELL_BIN" -c "! grep -qE 'Configuration hints|knip\\.json|Remove from' '$VX/knip.txt' && echo none"
check VX06 "knip format: a clean run (no output at all, exit 0) is zero findings" 0 '0 findings, the same as' vx_kb "$VX/knip-empty.txt" --format knip -- cat "$FX/knip-6.48.0-clean.out"
check VX07 "knip format: a run with hints only is zero findings" 0 '0 findings, the same as' vx_kb "$VX/knip-empty.txt" --format knip -- cat "$FX/knip-6.48.0-hints-only.out"
check VX08 "knip format: a crashed knip (exit 2, ERROR line) is not zero findings" fail 'failed \(exit 2\) without a finding' vx_kb "$VX/knip-empty.txt" --format knip -- "$SHELL_BIN" -c "cat '$FX/knip-6.48.0-crash.out'; exit 2"

# ---------------------------------------------------------------- keyed-baseline.sh --format tsc (TypeScript 7.0.2)
check VX09 "tsc format: the real plain output (6 errors, 2 continuation lines) gives 6 lines" 0 'rewritten, 6 lines' vx_kb "$VX/tsc-plain.txt" --init --format tsc -- cat "$FX/tsc-7.0.2-plain.out"
check VX10 "tsc format: the real --pretty output, colour codes and all, gives the same 6 lines" 0 'rewritten, 6 lines' vx_kb "$VX/tsc-pretty.txt" --init --format tsc -- cat "$FX/tsc-7.0.2-pretty-colour.out"
check VX11 "tsc format: the plain and the pretty baseline are the same file (switching --pretty does not invalidate it)" 0 '^same$' "$SHELL_BIN" -c "cmp -s '$VX/tsc-plain.txt' '$VX/tsc-pretty.txt' && echo same"
check VX12 "tsc format: no key holds a position" 0 '^none$' "$SHELL_BIN" -c "! grep -qE ':[0-9]+:[0-9]+|\\([0-9]+,[0-9]+\\)' '$VX/tsc-plain.txt' && echo none"
check VX13 "tsc format: a real start-up error (TS5058, exit 1) is no finding and fails" fail 'failed \(exit 1\) without a finding' vx_kb "$VX/tsc-plain.txt" --format tsc -- "$SHELL_BIN" -c "cat '$FX/tsc-7.0.2-startup-error.out'; exit 1"
check VX14 "tsc format: a real bad option (TS5023, exit 1) is no finding and fails" fail 'failed \(exit 1\) without a finding' vx_kb "$VX/tsc-plain.txt" --format tsc -- "$SHELL_BIN" -c "cat '$FX/tsc-7.0.2-bad-option.out'; exit 1"

# ---------------------------------------------------------------- mutation.sh, Stryker 10.1.1 (recorded runs behind a fake npx)
VXM="$VX/mut"
mkdir -p "$VXM/scripts" "$VXM/src/(grp)" "$VXM/bin"
cp "$KIT/tools/mutation.sh" "$KIT/tools/kit-config.sh" "$VXM/scripts/"
sed -i.bak 's/<pest, infection or stryker>/stryker/; s/<source folders>/src/' "$VXM/scripts/mutation.sh"
printf '#!/bin/sh\necho "ARGS: $*"\ncat "$VX_FIXTURE"\nexit "${VX_STATUS:-0}"\n' > "$VXM/bin/npx"
chmod +x "$VXM/bin/npx"
printf 'a\n' > "$VXM/src/a.ts"
printf 'a\n' > "$VXM/src/a.test.ts"
printf 'a\n' > "$VXM/src/styles.css"
printf 'a\n' > "$VXM/src/[id].ts"
printf 'a\n' > "$VXM/src/(grp)/page.tsx"
printf 'a\n' > "$VXM/src/keep.ts"
(
    cd "$VXM" && git init -q -b main && git add -A && git commit -q -m "PROJ-1 chore: base" && git update-ref refs/remotes/origin/main HEAD &&
        printf 'b\n' >> src/a.ts && printf 'b\n' >> src/a.test.ts && printf 'b\n' >> src/styles.css && printf 'b\n' >> "src/[id].ts" && printf 'b\n' >> "src/(grp)/page.tsx" &&
        git add -A && git commit -q -m "PROJ-1 chore: change"
) > /dev/null 2>&1
vx_mut() { # vx_mut <floor> <fixture> <status> [--changed]
    printf 'threshold: %s\n' "$1" > "$VXM/scripts/mutation-floor.txt"
    local floor="$1" fixture="$2" status="$3"
    shift 3
    (cd "$VXM" && PATH="$VXM/bin:$PATH" VX_FIXTURE="$fixture" VX_STATUS="$status" CHECK_BASE=main bash scripts/mutation.sh "$@")
}
check VX15 "mutation.sh stryker: below the floor, the real run's exit 1 and its 'breaking threshold 60' line come through" fail 'under breaking threshold 60' vx_mut 60 "$FX/stryker-10.1.1-below-floor.out" 1
check VX16 "mutation.sh stryker: above the floor (exit 0, 'break threshold 40') passes" 0 'greater than or equal to break threshold 40' vx_mut 40 "$FX/stryker-10.1.1-above-floor.out" 0
check VX17 "mutation.sh stryker: a run that names another floor than the file's (a config that ignores MUTATION_FLOOR) fails even at exit 0" fail 'does not name the floor 60' vx_mut 60 "$FX/stryker-10.1.1-above-floor.out" 0
check VX18 "mutation.sh stryker --changed: only source files are passed: no test file, no .css; the globbing characters of [id] and (grp) become ?" 0 'ARGS: stryker run --mutate src/\?grp\?/page\.tsx,src/\?id\?\.ts,src/a\.ts$' vx_mut 40 "$FX/stryker-10.1.1-above-floor.out" 0 --changed
check VX19 "mutation.sh stryker --changed: with only a test file and a .css changed there is nothing to mutate" 0 'changes no source file' "$SHELL_BIN" -c "cd '$VXM' && git checkout -q -b vx-only && git reset -q --hard refs/remotes/origin/main && printf 'c\n' >> src/a.test.ts && printf 'c\n' >> src/styles.css && git add -A && git commit -q -m 'PROJ-1 chore: tests' && printf 'threshold: 40\n' > scripts/mutation-floor.txt && PATH='$VXM/bin:'\$PATH VX_FIXTURE='$FX/stryker-10.1.1-above-floor.out' CHECK_BASE=main bash scripts/mutation.sh --changed"

# ---------------------------------------------------------------- Infection 0.35.12 and Pest 5.3.7: recorded outputs the script's choices rest on
check VX20 "infection: a run below --min-msi fails with the MSI message (recorded)" 0 'should be 90%, but actual is 80%' cat "$FX/infection-0.35.12-below-msi.out"
check VX21 "infection: --git-diff-filter=AM on a renamed and edited file mutates nothing and exits 0 (recorded: why the script passes AMR)" 0 'Could not find any modified files' cat "$FX/infection-0.35.12-filter-AM-rename.out"
check VX22 "mutation.sh infection --changed passes AMR, not AM, and the base as origin/<base>" 0 'infection --threads=max --min-msi=40 --git-diff-filter=AMR --git-diff-base=origin/main' "$SHELL_BIN" -c "
    cd '$VXM' && git checkout -q vx-only 2>/dev/null; git reset -q --hard HEAD
    mkdir -p vendor/bin && printf '#!/bin/sh\necho \"infection \$*\"\n' > vendor/bin/infection && chmod +x vendor/bin/infection
    sed 's/^MUTATION_TOOL=.*\$/MUTATION_TOOL=\"infection\"/' scripts/mutation.sh > scripts/mutation-infection.sh
    printf 'threshold: 40\n' > scripts/mutation-floor.txt
    printf 'd\n' >> src/a.ts && git add -A && git commit -q -m 'PROJ-1 chore: src' && CHECK_BASE=main bash scripts/mutation-infection.sh --changed"
check VX23 "pest: a run below --min fails with the score message (recorded)" 0 'Mutation score below expected: 62\.5 %\. Minimum: 95\.0 %' cat "$FX/pest-5.3.7-below-min.out"
check VX24 "pest: --path takes the comma list: both files are mutated (recorded)" 0 'Mutations: 3 uncovered, 5 tested' cat "$FX/pest-5.3.7-path-comma-list.out"
