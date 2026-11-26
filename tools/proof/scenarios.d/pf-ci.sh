# Scenarios PF*: the GitHub side of the CI skeletons after the first real run (the runner form, the unenforced mode, the gitleaks install
# on a runner, the sha recipe, the Dependabot form, the cost and weekly mutation variant, the settings read in step 1). Sourced by
# ../proof.sh with its helpers in scope (check, check_php, KIT, ROOT, SHELL_BIN). Text checks over the skeletons and the docs, a YAML
# parse where ruby exists, and the comparison of the filled runner skeleton with the example that the contract test reads. The contract
# guard itself runs in a Pest project (reference-project/tests/Unit/CiHostFormsArchitectureTest.php), not here.

PF="$ROOT/pf"
mkdir -p "$PF"
PF_CONTAINER="$KIT/ci/github-actions.yml"
PF_RUNNER="$KIT/ci/github-actions-runner.yml"

# check_ruby: like check_php, for the scenarios that parse YAML with ruby's own library.
check_ruby() {
    if [ -n "${PROOF_ONLY:-}" ] && ! grep -q -E -e "$PROOF_ONLY" <<< "$1"; then
        return 0
    fi
    if command -v ruby > /dev/null 2>&1; then
        check "$@"
        return
    fi
    SKIPPED=$((SKIPPED + 1))
    printf '%s | %s | %s | %s | %s\n' "$1" "$2" "-" "skipped: needs ruby" "skipped" >> "$TABLE"
}

# pf_code <file>: the lines that are not comments or blank.
pf_code() {
    grep -v -E '^[[:space:]]*#' "$1" | grep -v -E '^[[:space:]]*$'
}

# pf_fill_runner: the runner skeleton with every placeholder filled the way CiHostExamples::githubRunnerWorkflow() fills it.
pf_fill_runner() {
    local zero=0000000000000000000000000000000000000000000000000000000000000000
    sed -E \
        -e "s/<main branch>/main/; s/<other deploying branch>/develop/" \
        -e "s/<cron expression[^>]*>/17 3 * * */" \
        -e "s/<maximum minutes>/20/" \
        -e "s/<40-character commit sha of the release the project pins> # <release, for example v[0-9.]+>/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1/" \
        -e "s/<40-character commit sha of the setup-php release> # <release, for example v2>/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2/" \
        -e "s/<40-character commit sha of the setup-node release> # <release, for example v7.1.0>/cccccccccccccccccccccccccccccccccccccccc # v7.1.0/" \
        -e "s/<PHP version of production, for example 8.4>/8.4/" \
        -e "s/<extensions the project needs, comma separated>/mbstring, pdo_pgsql/" \
        -e "s/<file that names the Node version, for example .nvmrc>/.nvmrc/" \
        -e "s/<install the PHP dependencies from the lock file>/composer install/" \
        -e "s/<install the frontend dependencies from the lock file>/npm ci/" \
        -e "s/GITLEAKS_VERSION: <.*/GITLEAKS_VERSION: 8.30.1/" \
        -e "s/GITLEAKS_SHA256: <.*/GITLEAKS_SHA256: $zero/" \
        -e "s#<the image of DATABASE_IMAGE in scripts/kit-config.sh>#postgres:17#" \
        -e "s/<backend test command, writing a clover coverage report>/composer test:unit/" \
        -e "s#<path of the clover report>#coverage/clover.xml#" \
        -e "s/<frontend test command.*/npm test/" \
        -e "s/<boot the stack with seeded data.*/php artisan serve --env=ci \&/" \
        -e "s/<smoke suite command.*/npx playwright test/" \
        "$PF_RUNNER" | grep -v -E '^[[:space:]]*#' | grep -v -E '^[[:space:]]*$' | sed -E 's/ # (the part that holds|holds the coverage).*$//'
}
pf_example() {
    printf '%s\n' '<?php' 'require $argv[1];' 'echo Tests\Support\Architecture\CiHostExamples::githubRunnerWorkflow();' > "$PF/example.php"
    php "$PF/example.php" "$KIT/reference-project/tests/Support/Architecture/CiHostExamples.php" | grep -v -E '^[[:space:]]*$'
}
pf_runner_vs_example() {
    pf_fill_runner > "$PF/runner-filled.yml"
    pf_example > "$PF/runner-example.yml"
    if grep -q '<' "$PF/runner-filled.yml"; then
        echo "a placeholder is left after the fill:"
        grep -n '<' "$PF/runner-filled.yml"
        return 1
    fi
    diff "$PF/runner-filled.yml" "$PF/runner-example.yml" && echo same
}
pf_runner_yaml() {
    pf_fill_runner > "$PF/runner-filled.yml"
    ruby -ryaml -e 'd = YAML.load_file(ARGV[0]); puts "jobs: " + d["jobs"].keys.join(",")' "$PF/runner-filled.yml" 2> /dev/null
}

if [ -f "$PF_RUNNER" ]; then
    check_ruby PF01 "github-actions-runner.yml with its placeholders filled is a YAML document with the seven jobs" 0 '^jobs: checks,checks-result,checks-main,backend-tests,frontend-tests,smoke,scheduled$' pf_runner_yaml
    check PF02 "github-actions-runner.yml: no container and no image digest outside a comment" 0 '^0$' "$SHELL_BIN" -c "grep -v -E '^[[:space:]]*#' '$PF_RUNNER' | grep -c -E 'container:|@sha256'; exit 0"
    if [ -f "$KIT/reference-project/tests/Support/Architecture/CiHostExamples.php" ]; then
        check_php PF03 "github-actions-runner.yml with its placeholders filled equals the runner example of the contract test, line for line" 0 '^same$' pf_runner_vs_example
    fi
    check PF04 "github-actions-runner.yml: every service publishes a port and every job reaches it at 127.0.0.1 (3 services, 3 ports, 3 hosts, no host by name)" 0 '^3 3 3 0$' "$SHELL_BIN" -c "pf() { grep -v -E '^[[:space:]]*#' '$PF_RUNNER' | grep -c -E -e \"\$1\"; }; echo \"\$(pf '^    services:') \$(pf \"^        ports:\") \$(pf 'DB_HOST: 127\\.0\\.0\\.1') \$(pf 'DB_HOST: database')\"; exit 0"
    check PF05 "github-actions-runner.yml: the gitleaks install is a sudo install with a checked download, and the checksum file is named gitleaks_<version>_checksums.txt" 0 '^yes$' "$SHELL_BIN" -c "for word in 'sudo install -m 0755 gitleaks' 'sha256sum -c' 'gitleaks_<version>_checksums.txt'; do grep -q -F -e \"\$word\" '$PF_RUNNER' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
    check PF06 "github-actions-runner.yml: setup-php and setup-node come pinned by a sha placeholder, every uses line (checkout, setup-php, setup-node)" 0 '^0$' "$SHELL_BIN" -c "grep -v -E '^[[:space:]]*#' '$PF_RUNNER' | grep -E '^[[:space:]]+- uses: ' | grep -c -v -E '@<40-character commit sha of the (release the project pins|setup-php release|setup-node release)> # <release'; exit 0"
    check PF07 "github-actions-runner.yml: the five engines (postgres, mysql, mariadb, mongo, redis) have their port and health tool in the header" 0 '^yes$' "$SHELL_BIN" -c "for word in 'pg_isready' 'mysqladmin ping' 'healthcheck.sh' 'mongosh' 'redis-cli ping' \"'27017:27017'\" \"'6379:6379'\" 'MONGODB_URI' 'REDIS_HOST'; do grep -q -F -e \"\$word\" '$PF_RUNNER' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
fi

if [ -f "$PF_CONTAINER" ]; then
    check PF10 "github-actions.yml: the sha recipe is the tags endpoint, and the runner skeleton gives the same" 0 '^2$' "$SHELL_BIN" -c "grep -l -F 'repos/<owner>/<repo>/tags --jq' '$PF_CONTAINER' '$PF_RUNNER' | wc -l | tr -d ' '"
    check PF11 "github-actions.yml: the unenforced mode, the weekly mutation condition, the smoke environment and the container-form sudo note are written" 0 '^yes$' "$SHELL_BIN" -c "for word in 'Unenforced mode' \"if: github.event.schedule == '23 4 * * 0'\" 'APP_ENV=staging' 'sudo install -m 0755 gitleaks' 'Actions minutes'; do grep -q -F -e \"\$word\" '$PF_CONTAINER' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
    check PF12 "github-actions.yml: the container skeleton still has no sudo outside a comment (a container runs as root), and still says where the runner form is" 0 '^0 1$' "$SHELL_BIN" -c "echo \"\$(grep -v -E '^[[:space:]]*#' '$PF_CONTAINER' | grep -c 'sudo') \$(grep -c -F 'ci/github-actions-runner.yml' '$PF_CONTAINER' | sed 's/^[1-9][0-9]*$/1/')\"; exit 0"
    check PF13 "github-actions.yml: the Mongo and Redis blocks are named beside the three SQL engines" 0 '^yes$' "$SHELL_BIN" -c "for word in 'mongosh --quiet' 'redis-cli ping' 'MONGODB_URI'; do grep -q -F -e \"\$word\" '$PF_CONTAINER' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"
fi

# The weekly mutation variant as written in the skeleton: the two crons and the step condition parse, and the cron is the one the
# condition names. ruby reads a filled copy where the variant is applied.
pf_weekly_parse() {
    pf_fill_runner > "$PF/weekly.yml"
    sed -i.bak -e "s/^    - cron: '17 3 \* \* \*'/&\n    - cron: '23 4 * * 0'/" -e "s/^      - run: bash scripts\/mutation.sh/&\n        if: github.event.schedule == '23 4 * * 0'/" "$PF/weekly.yml"
    ruby -ryaml -e 'd = YAML.load_file(ARGV[0]); crons = d[true]["schedule"].map { |e| e["cron"] }; step = d["jobs"]["scheduled"]["steps"].last; puts crons.join("|") + " " + step["if"]' "$PF/weekly.yml" 2> /dev/null
}
check_ruby PF14 "the weekly mutation variant: two crons and a mutation step whose condition names the second" 0 '^17 3 \* \* \*\|23 4 \* \* 0 github\.event\.schedule == .23 4 \* \* 0.$' pf_weekly_parse

# The Dependabot form of templates/tool-configs.md: the fenced block parses and holds what the kit's Renovate file holds.
pf_dependabot() {
    awk '/^\*\*The Dependabot form\*\*/ { seen = 1 } seen && /^```yaml/ { on = 1; next } seen && on && /^```/ { exit } seen && on { print }' "$KIT/templates/tool-configs.md" > "$PF/dependabot.yml"
    ruby -ryaml -e '
        d = YAML.load_file(ARGV[0])
        u = d["updates"]
        eco = u.map { |e| e["package-ecosystem"] }
        prefixed = u.all? { |e| e["commit-message"]["prefix"].to_s.include?("chore(deps)") }
        majors_off = u.select { |e| e["package-ecosystem"] != "github-actions" }.all? { |e| e["ignore"].to_a.any? { |i| i["update-types"].to_a.include?("version-update:semver-major") } }
        actions_open = u.find { |e| e["package-ecosystem"] == "github-actions" }["ignore"].nil?
        increase = u.select { |e| e["package-ecosystem"] != "github-actions" }.all? { |e| e["versioning-strategy"] == "increase" }
        puts "version=#{d["version"]} #{eco.join(",")} prefix=#{prefixed} majors_off=#{majors_off} actions_open=#{actions_open} increase=#{increase}"
    ' "$PF/dependabot.yml" 2> /dev/null
}
check_ruby PF15 "templates/tool-configs.md: the Dependabot form parses, has the three ecosystems, the commit prefix, majors off for the manifests and open for the actions" 0 '^version=2 composer,npm,github-actions prefix=true majors_off=true actions_open=true increase=true$' pf_dependabot
check PF16 "CODEOWNERS.example: the unenforced state is described and the dependency bot files are covered" 0 '^yes$' "$SHELL_BIN" -c "for word in '4. Unenforced' 'Upgrade to GitHub Pro' '/renovate.json <owner>' '/.github/dependabot.yml <owner>'; do grep -q -F -e \"\$word\" '$KIT/ci/CODEOWNERS.example' || { echo \"missing: \$word\"; exit 1; }; done; echo yes"

# The method files say the same things as the skeletons (a statement and the command it names, not the prose).
pf_doc_words() {
    local file="$1" word
    shift
    for word in "$@"; do
        grep -q -F -e "$word" "$KIT/$file" || { echo "$file is missing: $word"; return 1; }
    done
    echo yes
}
check PF20 "04-guards.md: the runner form, the unenforced mode, the weekly mutation condition, the cost reading and the smoke environment are written" 0 '^yes$' pf_doc_words 04-guards.md 'ci/github-actions-runner.yml' 'unenforced mode' "if: github.event.schedule ==" 'GitHub Actions cost' 'APP_ENV=staging' '.github/dependabot.yml' 'actions/runs/<id>/timing' 'gh api user --jq .plan'
check PF21 "07-process.md: the merge style for the format commit, who deletes head branches and the unenforced mode are written" 0 '^yes$' pf_doc_words 07-process.md 'gh pr merge --merge' 'delete_branch_on_merge' 'Unenforced mode' 'gh pr checks <number>'
check PF22 "02-step-1-discover.md: step 1.1 reads the plan, the branch rules, head-branch deletion, the merge styles, the dependency bot, the fork setting and the minutes" 0 '^yes$' pf_doc_words 02-step-1-discover.md 'branches/<main>/protection' 'delete_branch_on_merge' 'allow_squash_merge' 'Dependabot or Renovate' 'fork-approval setting does not exist' 'Actions minutes' 'hosted as a URL'
check PF23 "01-principles.md: a hosted formatter config is said to read 0 and the lowered number is said not to be computable" 0 '^yes$' pf_doc_words 01-principles.md 'reads 0 whatever the hosted config holds' 'is not a number the row can compute'

# The files of this change carry no run URL and no repository path of the run they came from.
check PF30 "the CI skeletons and the docs of this change name no run URL (actions/runs/<number>)" 0 '^0$' "$SHELL_BIN" -c "cat '$KIT/ci/github-actions.yml' '$KIT/ci/github-actions-runner.yml' '$KIT/ci/CODEOWNERS.example' '$KIT/04-guards.md' '$KIT/07-process.md' '$KIT/02-step-1-discover.md' '$KIT/templates/tool-configs.md' | grep -c -E 'actions/runs/[0-9]'; exit 0"
