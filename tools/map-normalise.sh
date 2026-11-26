#!/usr/bin/env bash

# Maps names in a text back to what they were before a move (or forward), so the lists of two stacks can be diffed.
#   some-dump | bash scripts/map-normalise.sh <moves.tsv> [--forward] > normalised.txt
# The map is the one of the move (old name, a tab, new name). Default: every new name becomes its old name, so a dump of the
# moved stack equals a dump of the old stack when nothing but the names changed. The longest name is replaced first, and a name
# written with doubled backslashes (as in JSON and in a serialized string's escaped form) is replaced too. A name is replaced
# only where it is not the start of a longer name (the next character is not a letter, digit or underscore), so
# App\Domain\UserProfile is not read as App\Domain\User plus "Profile". The map is read once and sorted once.
# It reads standard input and writes standard output; it changes no file.

set -euo pipefail

MAP="${1:-}"
DIRECTION="${2:-}"
[ -r "$MAP" ] || {
    echo "Usage: some-dump | bash scripts/map-normalise.sh <moves.tsv> [--forward]" >&2
    exit 64
}

awk -F'\t' -v forward="$([ "$DIRECTION" = "--forward" ] && echo 1 || echo 0)" '
    function word_char(c) {
        return c ~ /^[A-Za-z0-9_]$/
    }
    # Replaces from with to, except where the match is the start of a longer name (the next character continues a name).
    function replace_all(text, from, to,    out, position, after) {
        out = ""
        while ((position = index(text, from)) > 0) {
            after = substr(text, position + length(from), 1)
            if (after != "" && word_char(after)) {
                out = out substr(text, 1, position)
                text = substr(text, position + 1)
                continue
            }
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
    FILENAME == ARGV[1] {
        if ($1 == "" || $2 == "") next
        count += 1
        from[count] = forward ? $1 : $2
        to[count] = forward ? $2 : $1
        next
    }
    FNR == 1 {
        # The longest name goes first: sorted once, when the map has been read.
        for (i = 1; i <= count; i++) order[i] = i
        for (i = 2; i <= count; i++) {
            key = order[i]
            j = i - 1
            while (j >= 1 && length(from[order[j]]) < length(from[key])) { order[j + 1] = order[j]; j-- }
            order[j + 1] = key
        }
        for (i = 1; i <= count; i++) {
            k = order[i]
            from_doubled[i] = doubled(from[k]); to_doubled[i] = doubled(to[k]); from_single[i] = from[k]; to_single[i] = to[k]
        }
    }
    {
        line = $0
        for (i = 1; i <= count; i++) {
            line = replace_all(line, from_doubled[i], to_doubled[i])
            line = replace_all(line, from_single[i], to_single[i])
        }
        print line
    }
' "$MAP" -
