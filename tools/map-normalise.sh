#!/usr/bin/env bash

# Maps names in a text back to what they were before a move (or forward), so the lists of two stacks can be diffed.
#   some-dump | bash scripts/map-normalise.sh <moves.tsv> [--forward] > normalised.txt
# The map is the one of the move (old name, a tab, new name). Default: every new name becomes its old name, so a dump of the
# moved stack equals a dump of the old stack when nothing but the names changed. The longest name is replaced first, and a name
# written with doubled backslashes (as in JSON and in a serialized string's escaped form) is replaced too.
# It reads standard input and writes standard output; it changes no file.

set -euo pipefail

MAP="${1:-}"
DIRECTION="${2:-}"
[ -r "$MAP" ] || {
    echo "Usage: some-dump | bash scripts/map-normalise.sh <moves.tsv> [--forward]" >&2
    exit 64
}

awk -F'\t' -v forward="$([ "$DIRECTION" = "--forward" ] && echo 1 || echo 0)" '
    function replace_all(text, from, to,    out, position) {
        out = ""
        while ((position = index(text, from)) > 0) {
            out = out substr(text, 1, position - 1) to
            text = substr(text, position + length(from))
        }
        return out text
    }
    function doubled(name,    result) {
        result = name
        gsub(/\\/, "\\\\", result)
        return result
    }
    FNR == NR {
        if ($1 == "" || $2 == "") next
        count += 1
        from[count] = forward ? $1 : $2
        to[count] = forward ? $2 : $1
        next
    }
    {
        for (i = 1; i <= count; i++) order[i] = i
        for (i = 2; i <= count; i++) {
            key = order[i]
            j = i - 1
            while (j >= 1 && length(from[order[j]]) < length(from[key])) { order[j + 1] = order[j]; j-- }
            order[j + 1] = key
        }
        line = $0
        for (i = 1; i <= count; i++) {
            k = order[i]
            line = replace_all(line, doubled(from[k]), doubled(to[k]))
            line = replace_all(line, from[k], to[k])
        }
        print line
    }
' "$MAP" -
