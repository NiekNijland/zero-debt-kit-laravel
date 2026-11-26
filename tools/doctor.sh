#!/usr/bin/env bash

# Kit doctor: which of the kit's scripts in a project's scripts/ folder have drifted from the kit's copy, and how to bring them back.
#
#   bash scripts/doctor.sh --kit <path to the kit folder> [--json] [--upgrade-plan [--apply]]    run in the project
#   bash tools/doctor.sh --project <path to the project> [...]                                  run from the kit
#
# A project copies the kit's scripts into scripts/ and fills the config block of each ("# >>> config" to "# <<< config"). This
# tool compares every installed script with the kit's copy while IGNORING what the project owns: the config block, the rows of the
# ratchet table in ci-ratchets.sh and the commands of the task lines in checks.sh. It reports per script:
#   identical        the body is the kit's and every config value is the kit's default
#   config-differs   the body is the kit's; some config values are the project's own decisions (listed by key)
#   body-differs     the body is not the kit's (a short diff summary and line counts; the older kit commit it equals, if any)
#   config-holds-code  the body is the kit's but the config block holds code: a line that is no assignment, comment or blank, or an
#                    assignment that runs something (an unquoted ; & | < > or backtick, a $( ). The block is for values only.
#   missing          the kit has the script, the project has not (optional scripts are reported, not counted)
#   project-only     the project has the script, the kit's folder has not
# A config block that has a key the other one lacks (a new key of the kit) counts as a divergence: the script needs a value.
# With --kit pointing into a git repository, the newest kit commit whose script bodies equal the project's is named
# ("matches kit v0.9.0 / commit abc1234"), or "diverged: body edited locally".
#
# --upgrade-plan prints the steps that bring the bodies to the kit's version and keep the project's config block. It changes
# nothing. With --apply it also does it: the kit's body with the project's config values and ratchet-table rows and task commands
# put back, written only under scripts/ (a temporary file, then a rename). A changed script that GUARDED_FILES lists is a guarded file: the commit needs
# [ratchet: script] <reason> and a code-owner review (UPGRADING.md).
#
# Exit 0: nothing diverged. Exit 1: a body differs, a required script is missing, a project-only kit script exists or a config
# key is missing or extra. Exit 64: a usage error. Writes nothing without --apply.

set -uo pipefail

KIT=""
PROJECT=""
SCRIPTS_DIR="scripts"
JSON=0
PLAN=0
APPLY=0

usage() {
    echo "Usage: bash scripts/doctor.sh --kit <kit folder> [--json] [--upgrade-plan [--apply]]" >&2
    echo "       bash tools/doctor.sh --project <project folder> [--json] [--upgrade-plan [--apply]]" >&2
    echo "       [--scripts-dir <folder, default scripts>]" >&2
    exit 64
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --kit) [ "$#" -ge 2 ] || usage; KIT="$2"; shift 2 ;;
        --project) [ "$#" -ge 2 ] || usage; PROJECT="$2"; shift 2 ;;
        --scripts-dir) [ "$#" -ge 2 ] || usage; SCRIPTS_DIR="$2"; shift 2 ;;
        --json) JSON=1; shift ;;
        --upgrade-plan) PLAN=1; shift ;;
        --apply) APPLY=1; PLAN=1; shift ;;
        -h | --help) usage ;;
        *) echo "Doctor: unknown argument $1" >&2; usage ;;
    esac
done

SELF_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
if [ -z "$KIT" ]; then
    if [ -f "$SELF_DIR/../tools/ci-ratchets.sh" ] && [ -f "$SELF_DIR/../VERSION" ]; then
        KIT="$SELF_DIR/.."
    else
        echo "Doctor: --kit <path to the kit folder> is required (the folder that holds tools/ and ci/)" >&2
        exit 64
    fi
fi
[ -d "$KIT/tools" ] || { echo "Doctor: $KIT is not a kit folder: it has no tools/" >&2; exit 64; }
KIT=$(cd "$KIT" && pwd)
[ -n "$PROJECT" ] || PROJECT=$(pwd)
[ -d "$PROJECT" ] || { echo "Doctor: project folder $PROJECT does not exist" >&2; exit 64; }
PROJECT=$(cd "$PROJECT" && pwd)
SDIR="$PROJECT/$SCRIPTS_DIR"
[ -d "$SDIR" ] || { echo "Doctor: $SDIR does not exist: the project has no kit scripts installed" >&2; exit 64; }

# name:required (1) or optional (0). The kit path of a name is ci/ for checks.sh and prepush.sh, tools/ for the others.
NAMES="ci-ratchets.sh:1 debt-dashboard.sh:1 ci-commit-messages.sh:1 ci-parallel.sh:1 keyed-baseline.sh:0 audit-gate.sh:0 mutation.sh:0 checks.sh:1 prepush.sh:0 kit-config.sh:1 doctor.sh:0"

kit_path() {
    case "$1" in
        checks.sh | prepush.sh) echo "ci/$1" ;;
        *) echo "tools/$1" ;;
    esac
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/kit" "$TMP/proj" "$TMP/out"

# ---------------------------------------------------------------- what the project owns, and how to read it
# normalize: a script without the config block, the rows of the ratchet table and the commands of the task lines.
normalize() {
    awk '
        /^# >>> config/ { skip = 1; next }
        /^# <<< config/ { skip = 0; next }
        skip { next }
        /^    cat <<.TABLE.$/ { print; intable = 1; next }
        intable && /^TABLE$/ { intable = 0 }
        intable { next }
        /^[[:space:]]+"[^":]+::.*"$/ { line = $0; sub(/::.*"$/, "::...\"", line); print line; next }
        { print }'
}

# The config block entries: "<key><TAB><the lines of the entry, joined by \001>". mode=extract prints them, mode=merge prints the
# script with the entries of projfile in place of the entries of the script (and the project's table and task lines).
ENTRIES_AWK='
function ndq(s,  t) { t = s; return gsub(/"/, "", t) }
function nsq(s,  t) { t = s; return gsub(/\047/, "", t) }
function has_code(s,   n, i, c, nx, q, prev) {
    # An assignment that runs something: an unquoted ; & | < > or backtick or $( , or a backtick or $( inside double quotes.
    n = length(s); q = ""; prev = " "
    for (i = 1; i <= n; i++) {
        c = substr(s, i, 1); nx = substr(s, i + 1, 1)
        if (q == "\047") { if (c == "\047") q = ""; prev = c; continue }
        if (c == "\\") { i++; prev = "x"; continue }
        if (q == "\"") {
            if (c == "\"") q = ""
            else if (c == "`" || (c == "$" && nx == "(")) return 1
            prev = c; continue
        }
        if (c == "\047") q = "\047"
        else if (c == "\"") q = "\""
        else if (c == "#" && (prev == " " || prev == "\t" || prev == SEP)) { while (i < n && substr(s, i + 1, 1) != SEP) i++; prev = SEP; continue }
        else if (c == ";" || c == "&" || c == "|" || c == "<" || c == ">" || c == "`" || (c == "$" && nx == "(")) return 1
        prev = c
    }
    return 0
}
function finish(   out) {
    if (mode == "extract") {
        out = raw
        if (flat) gsub(/\t/, " ", out)
        print key "\t" out
        if (has_code(raw)) print "#code:" key "\t" out
    } else {
        out = (key in P) ? P[key] : raw
        gsub(SEP, "\n", out)
        print out
    }
    cmode = ""
}
function begin_entry(line,   v) {
    key = line
    sub(/=.*/, "", key)
    sub(/\+$/, "", key)
    v = line
    sub(/^[A-Za-z_][A-Za-z0-9_]*\+?=/, "", v)
    raw = line
    cmode = ""
    if (v ~ /^\(/ && v !~ /\)[[:space:]]*(#.*)?$/) cmode = "a"
    else if (v ~ /^"/ && ndq(v) % 2 == 1) cmode = "d"
    else if (v ~ /^\047/ && nsq(v) % 2 == 1) cmode = "s"
    if (cmode == "") finish()
}
BEGIN {
    SEP = "\001"
    if (mode == "merge") {
        while ((getline l < projfile) > 0) { i = index(l, "\t"); P[substr(l, 1, i - 1)] = substr(l, i + 1) }
        if (taskfile != "") while ((getline l < taskfile) > 0) { i = index(l, "\t"); T[substr(l, 1, i - 1)] = substr(l, i + 1) }
    }
}
/^# >>> config/ { inblock = 1; if (mode == "merge") print; next }
/^# <<< config/ {
    if (cmode != "") finish()
    inblock = 0
    if (mode == "merge") print
    next
}
inblock {
    if (cmode != "") {
        raw = raw SEP $0
        if (cmode == "a" && $0 ~ /^\)/) finish()
        else if (cmode == "d" && ndq($0) % 2 == 1) finish()
        else if (cmode == "s" && nsq($0) % 2 == 1) finish()
        next
    }
    if ($0 ~ /^[A-Za-z_][A-Za-z0-9_]*\+?=/) { begin_entry($0); next }
    if (mode == "extract" && $0 !~ /^[[:space:]]*(#.*)?$/) { out = $0; gsub(/\t/, " ", out); print "#code:line" (++ncode) "\t" out }
    if (mode == "merge") print
    next
}
mode == "merge" && /^    cat <<.TABLE.$/ {
    print
    intable = 1
    if (tablefile != "") { skiptable = 1; while ((getline l < tablefile) > 0) print l }
    next
}
mode == "merge" && intable && /^TABLE$/ { intable = 0; skiptable = 0; print; next }
mode == "merge" && intable && skiptable { next }
mode == "merge" && /^[[:space:]]+"[^":]+::.*"$/ {
    name = $0; sub(/^[[:space:]]+"/, "", name); sub(/::.*/, "", name)
    tcount[name]++
    k = name "#" tcount[name]
    if (k in T) print T[k]; else print
    next
}
mode == "merge" { print }
'

entries() { # entries <file> [flat]
    awk -v mode=extract -v flat="${2:-0}" "$ENTRIES_AWK" "$1"
}

task_lines() { # task_lines <file>: "<name>#<n><TAB><whole line>" outside the config block
    awk '
        /^# >>> config/ { skip = 1; next }
        /^# <<< config/ { skip = 0; next }
        skip { next }
        /^[[:space:]]+"[^":]+::.*"$/ {
            name = $0; sub(/^[[:space:]]+"/, "", name); sub(/::.*/, "", name)
            n[name]++
            print name "#" n[name] "\t" $0
        }' "$1"
}

table_lines() { # table_lines <file>: the rows of the ratchet table, as written
    awk "/^    cat <<'TABLE'\$/ { inside = 1; next } /^TABLE\$/ { inside = 0 } inside" "$1"
}

has_table() { grep -q "^    cat <<'TABLE'\$" "$1"; }
has_block() { grep -q '^# >>> config' "$1" && grep -q '^# <<< config' "$1"; }

# compare_entries <project entries> <kit entries>: V<TAB>key<TAB>project<TAB>kit, K<TAB>key (only in the kit), P<TAB>key (only in the project)
compare_entries() {
    awk '
        BEGIN { FS = "\t" }
        NR == FNR { i = index($0, "\t"); k = substr($0, 1, i - 1); A[k] = substr($0, i + 1); order[++n] = k; next }
        { i = index($0, "\t"); k = substr($0, 1, i - 1); v = substr($0, i + 1); seen[k] = 1
          if (!(k in A)) print "K\t" k
          else if (A[k] != v) print "V\t" k "\t" A[k] "\t" v }
        END { for (j = 1; j <= n; j++) if (!(order[j] in seen)) print "P\t" order[j] }
    ' "$1" "$2"
}

show() { # show <value>: one line, shortened
    printf '%s' "$1" | tr '\001' '/' | sed -E 's/[[:space:]]+/ /g' | awk '{ if (length($0) > 90) print substr($0, 1, 87) "..."; else print }'
}

jstr() { printf '%s' "$1" | tr -d '\r' | tr '\n' ' ' | sed 's/\\/\\\\/g; s/"/\\"/g' | sed "s/$(printf '\t')/\\\\t/g"; }

list_json() {
    local x out2="" sep=""
    for x in $1; do
        out2="$out2$sep\"$(jstr "$x")\""
        sep=","
    done
    printf '[%s]' "$out2"
}

kit_git() { git -C "$KIT" rev-parse --git-dir > /dev/null 2>&1; }

# ---------------------------------------------------------------- compare every script
KIT_VERSION="unknown"
[ ! -f "$KIT/VERSION" ] || KIT_VERSION=$(head -n 1 "$KIT/VERSION" | tr -d '[:space:]')

DIVERGED=0
N_IDENT=0 N_CONFIG=0 N_BODY=0 N_MISSING=0 N_ONLY=0 N_CODE=0
INSTALLED=""
JSON_FILES=""
: > "$TMP/plan"
: > "$TMP/apply_list"
: > "$TMP/order"

for pair in $NAMES; do
    name="${pair%%:*}"
    required="${pair##*:}"
    kfile="$KIT/$(kit_path "$name")"
    pfile="$SDIR/$name"
    out="$TMP/out/$name"
    : > "$out"
    status=""
    added=0 removed=0 vkeys="" kkeys="" pkeys="" rows_new="" rows_own="" codelines=""

    if [ ! -f "$pfile" ] && [ ! -f "$kfile" ]; then
        continue
    elif [ -f "$pfile" ] && [ ! -f "$kfile" ]; then
        status="project-only"
        N_ONLY=$((N_ONLY + 1))
        DIVERGED=1
        echo "the kit folder has no $(kit_path "$name"): nothing to compare with" >> "$out"
    elif [ ! -f "$pfile" ]; then
        if [ "$required" -eq 1 ]; then
            status="missing"
            N_MISSING=$((N_MISSING + 1))
            DIVERGED=1
            echo "the kit has it and the project has not: copy $(kit_path "$name") to $SCRIPTS_DIR/$name and set its config block" >> "$out"
            echo "$name" >> "$TMP/apply_list"
        else
            status="missing-optional"
            echo "not installed (optional: install it when the project uses it)" >> "$out"
        fi
    else
        INSTALLED="$INSTALLED $name"
        normalize < "$kfile" > "$TMP/kit/$name"
        normalize < "$pfile" > "$TMP/proj/$name"
        entries "$kfile" 1 > "$TMP/kit/$name.cfg"
        entries "$pfile" 1 > "$TMP/proj/$name.cfg"
        compare_entries "$TMP/proj/$name.cfg" "$TMP/kit/$name.cfg" > "$TMP/out/$name.cmp"
        vkeys=$(awk -F'\t' '$1 == "V" && $2 !~ /^#code:/ { printf "%s%s", sep, $2; sep = " " }' "$TMP/out/$name.cmp")
        kkeys=$(awk -F'\t' '$1 == "K" && $2 !~ /^#code:/ { printf "%s%s", sep, $2; sep = " " }' "$TMP/out/$name.cmp")
        pkeys=$(awk -F'\t' '$1 == "P" && $2 !~ /^#code:/ { printf "%s%s", sep, $2; sep = " " }' "$TMP/out/$name.cmp")
        # Code in the config block: a line that is no assignment, comment or blank, or an assignment that runs something.
        codelines=$(awk -F'\t' '$1 == "P" && $2 ~ /^#code:/ { print $2 }' "$TMP/out/$name.cmp" | while read -r ck; do grep -F "$ck$(printf '\t')" "$TMP/proj/$name.cfg" | awk 'NR == 1' | cut -f2-; done)
        if ! cmp -s "$TMP/kit/$name" "$TMP/proj/$name"; then
            status="body-differs"
            N_BODY=$((N_BODY + 1))
            DIVERGED=1
            diff -u "$TMP/proj/$name" "$TMP/kit/$name" | tail -n +3 > "$TMP/out/$name.diff"
            added=$(grep -c '^+' "$TMP/out/$name.diff")
            removed=$(grep -c '^-' "$TMP/out/$name.diff")
            echo "the kit has $added line(s) the project lacks, the project has $removed line(s) the kit lacks (outside the config block)" >> "$out"
            sed -n '1,14p' "$TMP/out/$name.diff" | cut -c1-140 | sed 's/^/  | /' >> "$out"
            total=$(wc -l < "$TMP/out/$name.diff" | tr -d ' ')
            [ "$total" -le 14 ] || echo "  | ... $((total - 14)) more diff line(s): diff -u $SCRIPTS_DIR/$name $KIT/$(kit_path "$name")" >> "$out"
            echo "$name" >> "$TMP/apply_list"
        elif [ -n "$kkeys$pkeys" ]; then
            status="config-keys-differ"
            N_CONFIG=$((N_CONFIG + 1))
            DIVERGED=1
            echo "$name" >> "$TMP/apply_list"
        elif [ -n "$vkeys" ]; then
            status="config-differs"
            N_CONFIG=$((N_CONFIG + 1))
        else
            status="identical"
            N_IDENT=$((N_IDENT + 1))
        fi
        if [ -n "$codelines" ]; then
            if [ "$status" = identical ]; then N_IDENT=$((N_IDENT - 1)); fi
            if [ "$status" = config-differs ] || [ "$status" = config-keys-differ ]; then N_CONFIG=$((N_CONFIG - 1)); fi
            [ "$status" = body-differs ] || status="config-holds-code"
            N_CODE=$((N_CODE + 1))
            DIVERGED=1
            echo "config block holds code (only assignments, comments and the ratchet table are allowed there); the lines:" >> "$out"
            printf '%s\n' "$codelines" | cut -c1-140 | sed 's/^/  | /' >> "$out"
            grep -qx "$name" "$TMP/apply_list" || echo "$name" >> "$TMP/apply_list"
        fi
        if [ -n "$kkeys" ]; then
            echo "config keys only in the kit (the project needs a value): $kkeys" >> "$out"
            DIVERGED=1
            grep -qx "$name" "$TMP/apply_list" || echo "$name" >> "$TMP/apply_list"
        fi
        if [ -n "$pkeys" ]; then
            echo "config keys only in the project (the kit dropped or renamed them): $pkeys" >> "$out"
            DIVERGED=1
        fi
        if [ -n "$vkeys" ]; then
            echo "config values that differ from the kit's default (the project's own decisions):" >> "$out"
            awk -F'\t' '$1 == "V" { print $2 "\t" $3 "\t" $4 }' "$TMP/out/$name.cmp" | while IFS="$(printf '\t')" read -r k pv kv; do
                echo "  $k = $(show "${pv#"$k"=}")   (kit: $(show "${kv#"$k"=}"))" >> "$out"
            done
        fi
        if [ "$name" = ci-ratchets.sh ] && has_table "$kfile" && has_table "$pfile"; then
            table_lines "$kfile" | awk 'NF { print $1 }' | sort > "$TMP/kit/rows"
            table_lines "$pfile" | awk 'NF { print $1 }' | sort > "$TMP/proj/rows"
            rows_new=$(comm -23 "$TMP/kit/rows" "$TMP/proj/rows" | tr '\n' ' ' | sed 's/ $//')
            rows_own=$(comm -13 "$TMP/kit/rows" "$TMP/proj/rows" | tr '\n' ' ' | sed 's/ $//')
            [ -z "$rows_new" ] || echo "ratchet rows of the kit that the project does not have (a decision per row, not copied): $rows_new" >> "$out"
            [ -z "$rows_own" ] || echo "ratchet rows only the project has (its own, kept): $rows_own" >> "$out"
        fi
    fi
    echo "$status" > "$TMP/out/$name.status"
    frag="{\"name\":\"$name\",\"status\":\"$status\",\"required\":$([ "$required" -eq 1 ] && echo true || echo false)"
    frag="$frag,\"body_lines_kit_only\":$added,\"body_lines_project_only\":$removed,\"config_holds_code\":$([ -n "$codelines" ] && echo true || echo false)"
    frag="$frag,\"config_values_differ\":$(list_json "$vkeys"),\"config_keys_only_in_kit\":$(list_json "$kkeys"),\"config_keys_only_in_project\":$(list_json "$pkeys")"
    frag="$frag,\"ratchet_rows_not_in_project\":$(list_json "$rows_new"),\"ratchet_rows_only_in_project\":$(list_json "$rows_own")}"
    JSON_FILES="${JSON_FILES:+$JSON_FILES,}$frag"
    printf '%s\n' "$name" >> "$TMP/order"
done

# ---------------------------------------------------------------- which kit commit the copies equal
HIST_TEXT=""
HIST_JSON="null"
if [ -n "$INSTALLED" ]; then
    if kit_git && [ "$(git -C "$KIT" rev-parse --is-shallow-repository 2> /dev/null)" = "true" ]; then
        HIST_TEXT="not available: the kit clone $KIT is shallow, so the kit commit the copies equal cannot be found (fetch its full history: git -C $(printf '%q' "$KIT") fetch --unshallow)"
        HIST_JSON="{\"matches\":null,\"shallow\":true}"
    elif kit_git; then
        HEAD_SHORT=$(git -C "$KIT" rev-parse --short HEAD 2> /dev/null || echo "?")
        : > "$TMP/hist"
        for name in $INSTALLED; do
            cksum < "$TMP/proj/$name" > "$TMP/proj/$name.sum"
        done
        N_INSTALLED=$(echo $INSTALLED | wc -w | tr -d ' ')
        for c in $(git -C "$KIT" log --format=%h -- ./tools ./ci 2> /dev/null); do
            for name in $INSTALLED; do
                hit=0
                if git -C "$KIT" cat-file -e "$c:./$(kit_path "$name")" 2> /dev/null; then
                    sum=$(git -C "$KIT" show "$c:./$(kit_path "$name")" 2> /dev/null | normalize | cksum)
                    [ "$sum" != "$(cat "$TMP/proj/$name.sum")" ] || hit=1
                fi
                printf '%s\t%s\t%s\n' "$c" "$name" "$hit" >> "$TMP/hist"
            done
        done
        version_at() { git -C "$KIT" show "$1:./VERSION" 2> /dev/null | head -n 1 | tr -d '[:space:]'; }
        label_of() {
            local v
            v=$(version_at "$1")
            if [ -n "$v" ]; then echo "kit v$v / commit $1"; else echo "kit before VERSION existed / commit $1"; fi
        }
        all=$(awk -F'\t' -v n="$N_INSTALLED" '$3 == 1 { c[$1]++; if (c[$1] == n) { print $1; exit } }' "$TMP/hist")
        if [ -n "$all" ]; then
            behind=$(git -C "$KIT" rev-list --count "$all..HEAD" -- ./tools ./ci 2> /dev/null || echo "?")
            HIST_TEXT="matches $(label_of "$all")"
            if [ "$behind" = "0" ]; then
                HIST_TEXT="$HIST_TEXT (the scripts equal the kit's current ones, $HEAD_SHORT, v$KIT_VERSION)"
            else
                HIST_TEXT="$HIST_TEXT ($behind kit commit(s) to the scripts behind the kit's current $HEAD_SHORT, v$KIT_VERSION)"
            fi
            HIST_JSON="{\"matches\":true,\"commit\":\"$all\",\"version\":\"$(jstr "$(version_at "$all")")\",\"commits_behind\":\"$behind\"}"
        else
            unmatched=""
            : > "$TMP/histfiles"
            for name in $INSTALLED; do
                m=$(awk -F'\t' -v n="$name" '$2 == n && $3 == 1 { print $1; exit }' "$TMP/hist")
                if [ -n "$m" ]; then
                    [ "$(cat "$TMP/out/$name.status")" != body-differs ] || echo "  $name: an older kit copy: $(label_of "$m")" >> "$TMP/histfiles"
                else
                    unmatched="$unmatched $name"
                    echo "  $name: no kit commit has this body: edited locally" >> "$TMP/histfiles"
                fi
            done
            if [ -n "$unmatched" ]; then
                HIST_TEXT="diverged: body edited locally (no kit commit has the body of:$unmatched)"
            else
                HIST_TEXT="mixed: every script equals some kit commit, but not the same one (copies of different kit versions)"
            fi
            HIST_JSON="{\"matches\":false,\"unmatched\":\"$(jstr "$unmatched")\"}"
        fi
    else
        HIST_TEXT="not available: $KIT is not a git repository (point --kit at a clone of the kit to find the matching kit version)"
    fi
fi

# ---------------------------------------------------------------- the report
if [ "$JSON" -eq 1 ]; then
    printf '{"project":"%s","kit":"%s","kit_version":"%s","diverged":%s,' "$(jstr "$PROJECT")" "$(jstr "$KIT")" "$(jstr "$KIT_VERSION")" "$([ "$DIVERGED" -eq 1 ] && echo true || echo false)"
    printf '"history":%s,"files":[%s]}\n' "$HIST_JSON" "$JSON_FILES"
else
    echo "Doctor: project $PROJECT"
    echo "        kit     $KIT (VERSION $KIT_VERSION)"
    echo
    while read -r name; do
        [ -n "$name" ] || continue
        printf '%-26s %s\n' "$name" "$(cat "$TMP/out/$name.status")"
        sed 's/^/    /' "$TMP/out/$name"
    done < "$TMP/order"
    echo
    [ -z "$HIST_TEXT" ] || echo "Kit version: $HIST_TEXT"
    [ ! -s "$TMP/histfiles" ] || cat "$TMP/histfiles"
    echo "Summary: $N_IDENT identical, $N_CONFIG config-differs, $N_BODY body-differs, $N_MISSING missing, $N_ONLY project-only$([ "$N_CODE" -eq 0 ] || echo ", $N_CODE with code in the config block")."
    if [ "$DIVERGED" -eq 1 ]; then echo "Diverged: yes."; else echo "Diverged: no (config values that are the project's own are not a divergence)."; fi
fi

# ---------------------------------------------------------------- the upgrade plan, and --apply
if [ "$PLAN" -eq 1 ]; then
    {
        echo
        echo "Upgrade plan (kit v$KIT_VERSION)"
        if [ ! -s "$TMP/apply_list" ]; then
            echo "  Nothing to bring to the kit's version: every body equals the kit's."
        else
            echo "  Scripts to bring to the kit's version, keeping the project's config block, ratchet rows and task commands:"
            while read -r name; do
                echo "    $SCRIPTS_DIR/$name   from $(kit_path "$name")"
            done < "$TMP/apply_list"
            echo "  Do it with: bash $(printf '%q' "$KIT/tools/doctor.sh") --project $(printf '%q' "$PROJECT") --upgrade-plan --apply"
            echo "  By hand, per script: cp $(printf '%q' "$KIT")/<tools|ci>/<name> $SCRIPTS_DIR/<name>, then set the values of the old config block again"
            echo "  (the values that differ are listed above; run this tool again and expect no body-differs)."
        fi
        echo "  Then: commit with the tag [ratchet: script] <reason> in the message: a changed script that GUARDED_FILES"
        echo "  (in the config block of ci-ratchets.sh) lists is a guarded file (by default every script but prepush.sh and doctor.sh), and a code owner reviews the pull request (UPGRADING.md)."
        echo "  Then run: bash $SCRIPTS_DIR/ci-ratchets.sh --self-test and bash $SCRIPTS_DIR/checks.sh --part gate"
    } >&2
fi

if [ "$APPLY" -eq 1 ]; then
    CHANGED=""
    FAILED=0
    while read -r name; do
        [ -n "$name" ] || continue
        kfile="$KIT/$(kit_path "$name")"
        pfile="$SDIR/$name"
        tmpf="$SDIR/.$name.doctor-new.$$"
        if [ ! -f "$pfile" ]; then
            cp "$kfile" "$tmpf" || { FAILED=1; continue; }
            if ! chmod +x "$tmpf" 2> /dev/null; then echo "Doctor: cannot make $tmpf executable: set the mode of $SCRIPTS_DIR/$name by hand" >&2; fi
        else
            if has_block "$kfile" && ! has_block "$pfile"; then
                echo "Doctor: $SCRIPTS_DIR/$name has no config block markers: upgrade it by hand (the project's values cannot be found)" >&2
                FAILED=1
                continue
            fi
            entries "$pfile" 0 > "$TMP/proj/$name.raw.all"
            # A value that runs something is not carried into the new script: the kit's default comes back for that key.
            awk -F'\t' 'NR == FNR { if ($1 ~ /^#code:/ && $1 !~ /^#code:line/) bad[substr($1, 7)] = 1; next } $1 !~ /^#code:/ && !($1 in bad) { print }' "$TMP/proj/$name.raw.all" "$TMP/proj/$name.raw.all" > "$TMP/proj/$name.raw"
            if [ "$(grep -c '^#code:' "$TMP/proj/$name.raw.all")" -gt 0 ]; then
                echo "Doctor: $SCRIPTS_DIR/$name: the config block held code; the lines that were no assignment are gone and a value that ran something is the kit's default again: set it by hand" >&2
            fi
            tablefile=""
            if has_table "$kfile" && has_table "$pfile"; then
                table_lines "$pfile" > "$TMP/proj/$name.table"
                tablefile="$TMP/proj/$name.table"
            fi
            taskfile="$TMP/proj/$name.tasks"
            task_lines "$pfile" > "$taskfile"
            awk -v mode=merge -v projfile="$TMP/proj/$name.raw" -v tablefile="$tablefile" -v taskfile="$taskfile" "$ENTRIES_AWK" "$kfile" > "$tmpf" || { rm -f "$tmpf"; FAILED=1; continue; }
            [ ! -x "$pfile" ] || chmod +x "$tmpf"
            if ! normalize < "$tmpf" | cmp -s - "$TMP/kit/$name" || ! bash -n "$tmpf" 2> /dev/null; then
                echo "Doctor: the merged $SCRIPTS_DIR/$name did not verify (body differs from the kit's or does not parse): left unchanged" >&2
                rm -f "$tmpf"
                FAILED=1
                continue
            fi
        fi
        mv "$tmpf" "$pfile" || { rm -f "$tmpf"; FAILED=1; continue; }
        CHANGED="$CHANGED $name"
    done < "$TMP/apply_list"
    {
        if [ -n "$CHANGED" ]; then
            echo "Applied: $SCRIPTS_DIR/ changed:$CHANGED"
            echo "Every changed guarded file needs the commit tag [ratchet: script] <reason> and a code-owner review."
            echo "A script that was missing keeps the kit's placeholders in its config block: set them before it runs."
        else
            echo "Applied: nothing changed."
        fi
    } >&2
    [ "$FAILED" -eq 0 ] || exit 1
    exit 0
fi

exit "$DIVERGED"
