#!/usr/bin/env bash

# >>> config: one group of values per tool, chosen on the command line with --format <name>; FORMATS lists the names
FORMATS="tsc knip"
# FINDING_<name>: an extended regular expression: the lines of the command's output that are findings.
# NORMALIZE_<name>: a sed -E expression that removes what moves when code above a finding changes: line and column numbers.
# SECTION_<name>: empty, or an extended regular expression for a heading line. A finding is then written as
# "<heading without its count>: <finding>", so the same line under two headings is two findings.
# tsc: the TypeScript compiler.
FINDING_tsc='error TS[0-9]+'
NORMALIZE_tsc='s/\(([0-9]+),([0-9]+)\)//; s/:[0-9]+:[0-9]+//'
SECTION_tsc=''
# SKIP_<name>: empty, or an extended regular expression for a heading whose lines are not findings (the tool's own hints).
# knip: the unused-code tool, in its default reporter: a heading per kind ("Unused exports (2)"), then one line per finding.
FINDING_knip='^[^[:space:]]'
NORMALIZE_knip='s/:[0-9]+:[0-9]+$//; s/[[:space:]]+/ /g; s/ $//'
SECTION_knip='^[A-Z][A-Za-z ]+ \([0-9]+\)$'
SKIP_knip='^Configuration hints'
# <<< config

set -uo pipefail

usage() {
    echo "Usage: bash keyed-baseline.sh <baseline file> [--write] --format <name> -- <command that prints one finding per line>" >&2
    echo "  Compares the findings of the command with the baseline file: one line per finding, no line numbers, sorted." >&2
    echo "  Fails on a finding that is not in the file and on a line of the file that is no longer found." >&2
    echo "  --format <name>  the tool whose output the command prints; one of: $FORMATS" >&2
    echo "  --write          rewrites the file from the findings; the ratchet row type-baseline stops it growing" >&2
    exit 64
}

[ "$#" -ge 5 ] || usage
BASELINE="$1"
shift
WRITE=0
FORMAT=""
while [ "$#" -gt 0 ] && [ "$1" != "--" ]; do
    case "$1" in
        --write) WRITE=1 ;;
        --format)
            shift
            FORMAT="${1:-}"
            ;;
        *) usage ;;
    esac
    shift
done
[ "${1:-}" = "--" ] || usage
shift
[ "$#" -ge 1 ] || usage

case " $FORMATS " in
    *" $FORMAT "*) [ -n "$FORMAT" ] && [[ "$FORMAT" =~ ^[A-Za-z0-9_]+$ ]] || usage ;;
    *)
        echo "Keyed baseline: no format \"$FORMAT\". The config block has: $FORMATS. A tool with another output gets its own FINDING_, NORMALIZE_ and SECTION_ values there, and its name in FORMATS." >&2
        exit 64
        ;;
esac
eval "FINDING=\${FINDING_$FORMAT-} NORMALIZE=\${NORMALIZE_$FORMAT-} SECTION=\${SECTION_$FORMAT-} SKIP=\${SKIP_$FORMAT-}"
[ -n "$FINDING" ] || {
    echo "Keyed baseline: the format $FORMAT has no FINDING_$FORMAT in the config block." >&2
    exit 64
}

export LC_ALL=C
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

STATUS=0
"$@" > "$WORK/output" 2>&1 || STATUS=$?
FINDING="$FINDING" SECTION="$SECTION" SKIP="$SKIP" awk '
    ENVIRON["SECTION"] != "" && $0 ~ ENVIRON["SECTION"] { heading = $0; sub(/[[:space:]]*\([0-9]+\)$/, "", heading); skipping = (ENVIRON["SKIP"] != "" && heading ~ ENVIRON["SKIP"]); next }
    skipping { next }
    $0 ~ ENVIRON["FINDING"] { print (heading == "" ? "" : heading ": ") $0 }' "$WORK/output" | sed -E "${NORMALIZE:-s/^//}" | sort > "$WORK/now"

if [ "$STATUS" -ne 0 ] && [ ! -s "$WORK/now" ]; then
    echo "Keyed baseline: the command failed (exit $STATUS) without a finding in the format $FORMAT, so nothing can be compared. Its output:" >&2
    cat "$WORK/output" >&2
    exit 1
fi

if [ "$WRITE" -eq 1 ]; then
    mkdir -p "$(dirname "$BASELINE")"
    cat "$WORK/now" > "$BASELINE"
    echo "Keyed baseline: $BASELINE rewritten, $(wc -l < "$WORK/now" | tr -d ' ') lines."
    exit 0
fi

if [ -f "$BASELINE" ]; then
    sort "$BASELINE" > "$WORK/then"
else
    : > "$WORK/then"
fi

NEW=$(comm -23 "$WORK/now" "$WORK/then")
STALE=$(comm -13 "$WORK/now" "$WORK/then")
FAILED=0
if [ -n "$NEW" ]; then
    echo "Keyed baseline: findings that are not in $BASELINE. FAILS: fix them; a line is never added to the baseline"
    printf '%s\n' "$NEW" | sed 's/^/  /'
    FAILED=1
fi
if [ -n "$STALE" ]; then
    echo "Keyed baseline: lines of $BASELINE that are no longer found. FAILS: they are fixed, so drop them by running this command with --write"
    printf '%s\n' "$STALE" | sed 's/^/  /'
    FAILED=1
fi
if [ "$FAILED" -eq 0 ]; then
    echo "Keyed baseline: $(wc -l < "$WORK/now" | tr -d ' ') findings, the same as $BASELINE."
fi
exit "$FAILED"
