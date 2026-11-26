# Scenarios RG*: the reference guards keep what the kit says a hand-written guard keeps. Sourced by proof.sh with its helpers in scope
# (check, check_php, KIT, ROOT, SHELL_BIN). The guards themselves run in Pest, which this suite does not have; what is read here is
# the text of the files, with the expressions of the ratchet table of tools/ci-ratchets.sh, so a guard that would raise a row of the
# project that copies it fails here first.

RGDIR="$KIT/reference-project/tests/Support"

# rg_row_expression <row>: the expression of one row of the table in tools/ci-ratchets.sh.
rg_row_expression() {
    grep -E "^$1 +(max|min) +grep " "$KIT/tools/ci-ratchets.sh" | head -n 1 | sed -E 's/^[a-z-]+ +[a-z]+ +grep +[^ ]+ +//'
}

# rg_row_count <row> <file>...: the number of lines of the files that the row counts.
rg_row_count() {
    local row="$1" expression
    shift
    expression=$(rg_row_expression "$row")
    [ -n "$expression" ] || { echo "no row $row"; return 1; }
    cat "$@" | grep -c -E -e "$expression" || true
}

if [ -d "$RGDIR/Architecture" ]; then
    check RG01 "the guards of tests/Support/Architecture count 0 lines of the row byte-string-functions (the multibyte forms are written)" 0 '^0$' rg_row_count byte-string-functions "$RGDIR"/Architecture/*.php
    check RG02 "the guards of tests/Support/Architecture count 0 lines of the row loose-comparisons (an operator in a string is built from parts)" 0 '^0$' rg_row_count loose-comparisons "$RGDIR"/Architecture/*.php
    check RG03 "TypedReceivers, ReportNotLogGuard, RawTableGuard and GlobalImportGuard count 0 lines of the row comments (the tags stay, the prose is gone)" 0 '^0$' rg_row_count comments "$RGDIR/Architecture/TypedReceivers.php" "$RGDIR/Architecture/ReportNotLogGuard.php" "$RGDIR/Architecture/RawTableGuard.php" "$RGDIR/Architecture/GlobalImportGuard.php"
    check RG04 "TypedReceivers reads its stacks through end() and never through a key it has not checked (the analyser's offsetAccess errors)" 0 '^0$' "$SHELL_BIN" -c "grep -c 'array_key_last' '$RGDIR/Architecture/TypedReceivers.php'; exit 0"
fi

rg_lint() {
    local file bad=0
    for file in "$RGDIR"/Architecture/*.php "$RGDIR"/Ci/*.php "$KIT"/reference-project/tests/Unit/GithubWorkflowArchitectureTest.php "$KIT"/reference-project/tests/Unit/DocumentedPathArchitectureTest.php; do
        php -l "$file" > /dev/null 2>&1 || { echo "syntax error: $file"; bad=1; }
    done
    [ "$bad" -eq 0 ] && echo clean
    return "$bad"
}
if [ -d "$RGDIR/Architecture" ]; then
    check_php RG05 "every reference guard, example and thin test of the CI contract passes php -l" 0 '^clean$' rg_lint
fi

# The GitHub skeleton with its placeholders filled is the example the contract test reads (tests/Support/Ci/CiHostExamples.php), and
# the guard accepts that example: so the skeleton passes its own guard unmodified (the Pest test of the guard holds the second half).
rg_fill_skeleton() {
    local zero=0000000000000000000000000000000000000000000000000000000000000000
    sed -E \
        -e "s/<main branch>/main/; s/<other deploying branch>/develop/" \
        -e "s/<cron expression[^>]*>/17 3 * * */" \
        -e "s/<maximum minutes>/20/" \
        -e "s#image: <image name>@sha256:<digest>.*#image: php:8.4@sha256:$zero#" \
        -e "s/<40-character commit sha of the release the project pins> # <release, for example v[0-9.]+>/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1/" \
        -e "s/<install dependencies from the lock file>/composer install/" \
        -e "s/GITLEAKS_VERSION: <.*/GITLEAKS_VERSION: 8.30.1/" \
        -e "s/GITLEAKS_SHA256: <.*/GITLEAKS_SHA256: $zero/" \
        -e "s/GH_VERSION: <.*/GH_VERSION: 2.106.0/" \
        -e "s/GH_SHA256: <.*/GH_SHA256: $zero/" \
        -e "s#<the image of DATABASE_IMAGE in scripts/kit-config.sh>#postgres:17#" \
        -e "s/<backend test command, writing a clover coverage report>/composer test:unit/" \
        -e "s#<path of the clover report>#coverage/clover.xml#" \
        -e "s/<frontend test command.*/npm test/" \
        -e "s/<boot the stack with seeded data.*/php artisan serve --env=ci \&/" \
        -e "s/<smoke suite command.*/npx playwright test/" \
        "$KIT/ci/github-actions.yml" | grep -v -E '^[[:space:]]*#' | grep -v -E '^[[:space:]]*$' | sed -E 's/ # (the part that holds|holds the coverage).*$//'
}
rg_example() {
    printf '%s\n' '<?php' 'require $argv[1];' 'echo Tests\Support\Ci\CiHostExamples::githubWorkflow();' > "$ROOT/rg-example.php"
    php "$ROOT/rg-example.php" "$RGDIR/Ci/CiHostExamples.php" | grep -v -E '^[[:space:]]*$'
}
rg_skeleton_vs_example() {
    rg_fill_skeleton > "$ROOT/rg-skeleton-filled.yml"
    rg_example > "$ROOT/rg-example.yml"
    if grep -q '<' "$ROOT/rg-skeleton-filled.yml"; then
        echo "a placeholder is left after the fill:"
        grep -n '<' "$ROOT/rg-skeleton-filled.yml"
        return 1
    fi
    diff "$ROOT/rg-skeleton-filled.yml" "$ROOT/rg-example.yml" && echo same
}
if [ -f "$KIT/ci/github-actions.yml" ] && [ -f "$RGDIR/Ci/CiHostExamples.php" ]; then
    check_php RG06 "github-actions.yml with its placeholders filled equals the example that the GitHub guard accepts (tests/Support/Ci), line for line" 0 '^same$' rg_skeleton_vs_example
fi

if [ -f "$RGDIR/Architecture/GithubWorkflowGuard.php" ]; then
    check RG07 "the GitHub guard names its owner exceptions as arguments: the test script behind the backend command and the auxiliary workflows" 0 '^yes$' "$SHELL_BIN" -c "for word in 'backendTestScript' 'auxiliaryWorkflows'; do grep -q -e \"\$word\" '$RGDIR/Architecture/GithubWorkflowGuard.php' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
fi
