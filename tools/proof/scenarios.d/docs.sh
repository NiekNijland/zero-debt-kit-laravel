# Scenarios DOCX*: the numbers and names the docs claim against the kit itself. Sourced by proof.sh with its helpers in scope
# (check, KIT, ROOT, SHELL_BIN). Each scenario builds a scratch copy of the few files it reads under "$ROOT/docx".

DOCX="$ROOT/docx"
mkdir -p "$DOCX"

# docx_scenario_claims <folder>: every number written directly before the word "scenarios" in the README, the launch post and the
# proof workflow of <folder> must be at most the number of scenario ids that the proof lists (a lower bound of the real count: the
# ids built in a loop are not listed). Prints each claim; exit 1 when one is above the count.
docx_scenario_claims() {
    local dir="$1" real file claim bad=0
    real=$(PROOF_LIST=1 "$SHELL_BIN" "$KIT/tools/proof/proof.sh" | wc -l | tr -d ' ')
    for file in README.md docs/launch-post-draft.md .github/workflows/proof.yml; do
        [ -f "$dir/$file" ] || { echo "missing $file"; bad=1; continue; }
        while read -r claim; do
            [ -n "$claim" ] || continue
            if [ "$claim" -gt "$real" ]; then
                echo "$file claims $claim scenarios, the proof lists $real"
                bad=1
            else
                echo "$file claims $claim scenarios, the proof lists $real: ok"
            fi
        done < <(grep -o -E '[0-9]+ scenarios' "$dir/$file" | grep -o -E '^[0-9]+')
    done
    return "$bad"
}

# docx_versions <folder>: VERSION, the version of .claude-plugin/plugin.json and the first version heading of CHANGELOG.md agree.
docx_versions() {
    local dir="$1" version plugin changelog
    version=$(tr -d '[:space:]' < "$dir/VERSION")
    plugin=$(sed -n 's/^[[:space:]]*"version":[[:space:]]*"\([^"]*\)".*/\1/p' "$dir/.claude-plugin/plugin.json" | head -n 1)
    changelog=$(sed -n 's/^## \[\([0-9][0-9.]*\)\].*/\1/p' "$dir/CHANGELOG.md" | head -n 1)
    echo "VERSION $version, plugin.json $plugin, CHANGELOG $changelog"
    [ -n "$version" ] && [ "$version" = "$plugin" ] && [ "$version" = "$changelog" ]
}

# docx_rows <folder>: the row names of the two README tables of the start set equal the names of the table in tools/ci-ratchets.sh.
docx_rows() {
    local dir="$1" readme script missing=0 name
    readme=$(awk '/^### Rows that ship/ { on = 1; next } /^### Rows the project adds/ { on = 0 } on && /^\| `/ { print }' "$dir/README.md" |
        sed -E 's/^\| `([^`]+)`.*/\1/' | sort)
    script=$(awk "/^    cat <<'TABLE'\$/ { inside = 1; next } /^TABLE\$/ { inside = 0 } inside && NF { print \$1 }" "$dir/tools/ci-ratchets.sh" | sort)
    echo "README names $(printf '%s\n' "$readme" | grep -c .) rows, the script table $(printf '%s\n' "$script" | grep -c .)"
    for name in $script; do
        printf '%s\n' "$readme" | grep -qx -- "$name" || { echo "not in the README tables: $name"; missing=1; }
    done
    for name in $readme; do
        printf '%s\n' "$script" | grep -qx -- "$name" || { echo "not in the script table: $name"; missing=1; }
    done
    return "$missing"
}

# A scratch copy of the files the three functions read, so a scenario can break one of them.
docx_copy() {
    local to="$1" f
    for f in README.md VERSION CHANGELOG.md .claude-plugin/plugin.json docs/launch-post-draft.md .github/workflows/proof.yml tools/ci-ratchets.sh; do
        mkdir -p "$to/$(dirname "$f")"
        cp "$KIT/$f" "$to/$f"
    done
}

docx_copy "$DOCX/ok"
docx_copy "$DOCX/claim"
# The workflow comment gets a claim of its own that no proof can meet.
echo "# the suite runs 99999 scenarios" >> "$DOCX/claim/.github/workflows/proof.yml"
docx_copy "$DOCX/version"
echo "9.9.9" > "$DOCX/version/VERSION"
docx_copy "$DOCX/rowreadme"
sed -i.bak '/^| `todo` |/d' "$DOCX/rowreadme/README.md" && rm -f "$DOCX/rowreadme/README.md.bak"
docx_copy "$DOCX/rowscript"
sed -i.bak '/^todo  *max/d' "$DOCX/rowscript/tools/ci-ratchets.sh" && rm -f "$DOCX/rowscript/tools/ci-ratchets.sh.bak"

check DOCX01 "docs: no scenario count in the README, the launch post or the proof workflow is above the number of ids the proof lists" 0 'README.md claims [0-9]+ scenarios' docx_scenario_claims "$DOCX/ok"
check DOCX02 "docs: a claim of more scenarios than the proof lists fails and names the file" fail 'proof.yml claims 99999 scenarios' docx_scenario_claims "$DOCX/claim"
check DOCX03 "docs: VERSION, plugin.json and the newest changelog heading agree" 0 'VERSION [0-9.]+, plugin.json [0-9.]+, CHANGELOG [0-9.]+' docx_versions "$DOCX/ok"
check DOCX04 "docs: a VERSION that differs from plugin.json and the changelog fails" fail 'VERSION 9\.9\.9, plugin\.json' docx_versions "$DOCX/version"
check DOCX05 "docs: the README ratchet tables and the script table name the same rows" 0 'README names [0-9]+ rows, the script table [0-9]+' docx_rows "$DOCX/ok"
check DOCX06 "docs: a row in the script table that the README lacks fails and is named" fail 'not in the README tables: todo' docx_rows "$DOCX/rowreadme"
check DOCX07 "docs: a row in the README that the script table lacks fails and is named" fail 'not in the script table: todo' docx_rows "$DOCX/rowscript"
