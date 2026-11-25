#!/usr/bin/env bash

set -uo pipefail

usage() {
    echo "Usage: bash scripts/debt-dashboard.sh [--offline] [--details] [--report]"
    echo "  --offline  skip the rows that need the npm and composer registries (the npm rows also need node_modules: run npm ci first)"
    echo "  --details  list the files and packages behind every row that is not at its target"
    echo "  --report   always exit 0"
    echo "Exit status: 0 when every row is at its target, 1 when a row is open or could not be measured."
}

OFFLINE=0
DETAILS=0
REPORT=0
for argument in "$@"; do
    case "$argument" in
        --offline) OFFLINE=1 ;;
        --details) DETAILS=1 ;;
        --report) REPORT=1 ;;
        -h | --help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            exit 64
            ;;
    esac
done

cd "$(git rev-parse --show-toplevel)" || exit 64

NPM="${NPM:-npm}"
MAX_LINES=400
DOCS_MAX_AGE="6 months"
CAPS_FILE=scripts/debt-caps.json

TOTAL=0
OPEN=0
UNKNOWN=0
SKIPPED=0
DETAIL_FILE=$(mktemp)
trap 'rm -f "$DETAIL_FILE"' EXIT

count_text() {
    printf '%s' "$1" | grep -c . || true
}

row() {
    local group="$1" name="$2" now="$3" target="$4" kind="${5:-max}" status shown="$4"
    TOTAL=$((TOTAL + 1))
    [ "$kind" = "min" ] && shown=">=$target"
    if ! [[ "$now" =~ ^[0-9]+$ && "$target" =~ ^[0-9]+$ ]]; then
        status="UNKNOWN"
        now="?"
        UNKNOWN=$((UNKNOWN + 1))
    elif { [ "$kind" = "max" ] && [ "$now" -le "$target" ]; } || { [ "$kind" = "min" ] && [ "$now" -ge "$target" ]; }; then
        status="ok"
    else
        status="OPEN"
        OPEN=$((OPEN + 1))
    fi
    printf '%-8s %-38s %7s %7s  %s\n' "$group" "$name" "$now" "$shown" "$status"
}

skipped() {
    SKIPPED=$((SKIPPED + 1))
    printf '%-8s %-38s %7s %7s  %s\n' "$1" "$2" "-" "0" "skipped"
}

detail() {
    [ "$DETAILS" -eq 1 ] || return 0
    [ -n "$2" ] || return 0
    printf '\n%s\n%s\n' "$1" "$2" >> "$DETAIL_FILE"
}

cap() {
    php -r "$JSON_COUNT" -- "\$d['$1']" < "$CAPS_FILE" 2> /dev/null
}

read -r -d '' JSON_COUNT <<'PHP' || true
$d = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
echo (int) eval('return ' . $argv[1] . ';');
PHP

read -r -d '' NPM_BEHIND <<'PHP' || true
$outdated = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$lock = json_decode(file_get_contents('package-lock.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($outdated as $name => $entry) {
    $entry = array_is_list($entry) ? end($entry) : $entry;
    $latest = $entry['latest'] ?? null;
    $current = $lock['packages']['node_modules/' . $name]['version'] ?? null;
    if ($latest === null || $current === null) {
        fwrite(STDERR, $name . " has no latest or locked version\n");
        exit(2);
    }
    if (version_compare($current, $latest, '<')) {
        $kind = (int) explode('.', $current)[0] < (int) explode('.', $latest)[0] ? 'major' : 'minor';
        echo implode("\t", [$name, $current, $latest, $kind]), "\n";
    }
}
PHP

read -r -d '' COMPOSER_BEHIND <<'PHP' || true
$d = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach ($d['locked'] ?? [] as $package) {
    $status = $package['latest-status'] ?? '';
    if ($status !== 'up-to-date' && ! str_starts_with($package['version'], 'dev-')) {
        echo implode("\t", [$package['name'], $package['version'], $package['latest'] ?? '?', $status === 'update-possible' ? 'major' : 'minor']), "\n";
    }
}
PHP

read -r -d '' UNTESTED <<'PHP' || true
$text = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $text .= $file->getPathname() . "\n" . file_get_contents($file->getPathname()) . "\n";
    }
}
$total = 0;
$untested = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app', FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || ! preg_match('#/(Actions|Services|Jobs|Listeners|Commands)/#', $path)) {
        continue;
    }
    $total++;
    if (! str_contains($text, $file->getBasename('.php'))) {
        $untested[] = $path;
    }
}
sort($untested);
echo count($untested), ' of ', $total, "\n", implode("\n", $untested), "\n";
PHP

read -r -d '' UNTESTED_OWN <<'PHP' || true
$tests = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $tests[$file->getBasename('.php')] = true;
    }
}
$total = 0;
$untested = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app', FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || ! preg_match('#/(Actions|Services|Jobs|Listeners|Commands)/#', $path)) {
        continue;
    }
    $total++;
    if (! isset($tests[$file->getBasename('.php') . 'Test'])) {
        $untested[] = $path;
    }
}
sort($untested);
echo count($untested), ' of ', $total, "\n", implode("\n", $untested), "\n";
PHP

read -r -d '' UNTESTED_SUPPORT <<'PHP' || true
$tests = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $tests[$file->getBasename('.php')] = true;
    }
}
$total = 0;
$untested = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app', FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || str_contains($path, '/Http/Controllers/') || ! preg_match('#(/Policies/.+Policy|/Http/Middleware/[^/]+|^app/Rules/[^/]+|/Mail/[^/]+Mail|/Notifications/[^/]+Notification|^app/Casts/[^/]+)\.php$#', $path)) {
        continue;
    }
    if (preg_match('/^(abstract class|interface|trait) /m', file_get_contents($path))) {
        continue;
    }
    $total++;
    if (! isset($tests[$file->getBasename('.php') . 'Test'])) {
        $untested[] = $path;
    }
}
sort($untested);
echo count($untested), ' of ', $total, "\n", implode("\n", $untested), "\n";
PHP

read -r -d '' FRONTEND_COMPONENTS_UNTESTED <<'PHP' || true
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/js', FilesystemIterator::SKIP_DOTS)) as $file) {
    $files[$file->getPathname()] = true;
}
$total = 0;
$untested = [];
foreach (array_keys($files) as $path) {
    if (! preg_match('#\.tsx$#', $path) || preg_match('#^resources/js/(Pages|Components/ui)/#', $path)) {
        continue;
    }
    if (preg_match('#/(hooks|utils|lib)/|/use-[^/]+\.tsx$#', $path)) {
        continue;
    }
    if (preg_match('#\.(test|stories)\.tsx$|/(mocks|__mocks__|storybook|stories|types)/|test-helpers|test-mocks#', $path)) {
        continue;
    }
    $total++;
    $base = preg_replace('#\.tsx$#', '', $path);
    $tested = false;
    foreach (array_keys($files) as $candidate) {
        if (str_starts_with($candidate, $base . '.') && preg_match('#\.test\.tsx?$#', $candidate)) {
            $tested = true;
            break;
        }
    }
    if (! $tested) {
        $untested[] = $path;
    }
}
sort($untested);
echo count($untested), ' of ', $total, "\n", implode("\n", $untested), "\n";
PHP

read -r -d '' FRONTEND_LOGIC_UNTESTED <<'PHP' || true
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/js', FilesystemIterator::SKIP_DOTS)) as $file) {
    $files[$file->getPathname()] = true;
}
$total = 0;
$untested = [];
foreach (array_keys($files) as $path) {
    if (! preg_match('#\.tsx?$#', $path) || ! preg_match('#/(hooks|utils|lib)/|/use-[^/]+\.tsx?$#', $path)) {
        continue;
    }
    if (preg_match('#\.(test|stories)\.tsx?$|\.d\.ts$|/(mocks|__mocks__|storybook|types)/|test-helpers|test-mocks#', $path)) {
        continue;
    }
    $total++;
    $base = preg_replace('#\.tsx?$#', '', $path);
    $tested = false;
    foreach (array_keys($files) as $candidate) {
        if (str_starts_with($candidate, $base . '.') && preg_match('#\.test\.tsx?$#', $candidate)) {
            $tested = true;
            break;
        }
    }
    if (! $tested) {
        $untested[] = $path;
    }
}
sort($untested);
echo count($untested), ' of ', $total, "\n", implode("\n", $untested), "\n";
PHP

read -r -d '' DOCS_OLD <<'PHP' || true
$cut = strtotime('-' . $argv[1]);
$seen = [];
$time = 0;
foreach (explode("\n", stream_get_contents(STDIN)) as $line) {
    if ($line === '') {
        continue;
    }
    if ($line[0] === '@') {
        $time = (int) substr($line, 1);
    } else {
        $seen[$line] ??= $time;
    }
}
asort($seen);
foreach ($seen as $path => $time) {
    if ($time < $cut && is_file($path)) {
        echo date('Y-m-d', $time), '  ', $path, "\n";
    }
}
PHP

oversized() {
    xargs -0 wc -l 2> /dev/null | awk -v max="$MAX_LINES" '$2 != "total" && $1 > max {print $1 "  " $2}' | sort -rn
}

printf 'Zero-debt dashboard, %s, %s\n\n' "$(git rev-parse --short HEAD)" "$(date -u +%Y-%m-%d)"
printf '%-8s %-38s %7s %7s  %s\n' "group" "counter" "now" "target" "status"

if RATCHETS=$(bash scripts/ci-ratchets.sh --head 2> /dev/null) && [ -n "$RATCHETS" ]; then
    while read -r name direction value; do
        case "$name" in
            eslint-config-ignores) target=1 ;;
            phpstan-paths) target=6 ;;
            phpstan-includes) target=7 ;;
            phpstan-level-10) target=2 ;;
            tsconfig-include) target=8 ;;
            dashboard-cap-headroom) target=$value ;;
            comments | test-doubles | event-fake-all) target=$value ;;
            *) target=0 ;;
        esac
        kind=max
        [ "$direction" = "min" ] && kind=min
        row ratchet "$name" "$value" "$target" "$kind"
    done <<< "$RATCHETS"
else
    row ratchet "ci-ratchets.sh --head" "?" 0
fi

BACKEND_BIG=$(git ls-files -z -- 'app/*.php' 'resources/*.php' | grep -zv '^resources/translations/' | oversized)
row size "backend-files-over-$MAX_LINES-lines" "$(count_text "$BACKEND_BIG")" 0
detail "Backend files over $MAX_LINES lines" "$BACKEND_BIG"

FRONTEND_BIG=$(git ls-files -z -- 'resources/js/*.ts' 'resources/js/*.tsx' | grep -zEv '\.(test|stories)\.tsx?$|/(mocks|__mocks__)/|^resources/js/Components/ui/sidebar/sidebar\.tsx$|^resources/js/types/generated\.ts$' | oversized)
row size "frontend-files-over-$MAX_LINES-lines" "$(count_text "$FRONTEND_BIG")" 0
detail "Frontend files over $MAX_LINES lines" "$FRONTEND_BIG"

TEST_BIG=$({ git ls-files -z -- 'tests/*.php'; git ls-files -z -- 'resources/js/*.test.ts' 'resources/js/*.test.tsx'; } | oversized)
row size "test-files-over-$MAX_LINES-lines" "$(count_text "$TEST_BIG")" 0
detail "Test files over $MAX_LINES lines" "$TEST_BIG"

UNTESTED_OUT=$(php -r "$UNTESTED" 2> /dev/null)
row tests "untested-behavior-classes (by name)" "$(head -1 <<< "$UNTESTED_OUT" | awk '{print $1}')" 0
detail "Actions, services, jobs, listeners and commands that no test file names ($(head -1 <<< "$UNTESTED_OUT"))" "$(tail -n +2 <<< "$UNTESTED_OUT")"

UNTESTED_OWN_OUT=$(php -r "$UNTESTED_OWN" 2> /dev/null)
row tests "behavior-classes-without-own-test" "$(head -1 <<< "$UNTESTED_OWN_OUT" | awk '{print $1}')" 0
detail "Actions, services, jobs, listeners and commands without a <Class>Test.php file ($(head -1 <<< "$UNTESTED_OWN_OUT"))" "$(tail -n +2 <<< "$UNTESTED_OWN_OUT")"

UNTESTED_SUPPORT_OUT=$(php -r "$UNTESTED_SUPPORT" 2> /dev/null)
row tests "support-classes-without-own-test" "$(head -1 <<< "$UNTESTED_SUPPORT_OUT" | awk '{print $1}')" "$(cap support-classes-without-own-test)"
detail "Policies, middleware, rules, mails, notifications and casts without a <Class>Test.php file ($(head -1 <<< "$UNTESTED_SUPPORT_OUT"))" "$(tail -n +2 <<< "$UNTESTED_SUPPORT_OUT")"

FRONTEND_LOGIC_OUT=$(php -r "$FRONTEND_LOGIC_UNTESTED" 2> /dev/null)
row tests "frontend-logic-without-test" "$(head -1 <<< "$FRONTEND_LOGIC_OUT" | awk '{print $1}')" "$(cap frontend-logic-without-test)"
detail "Hooks and files under hooks, utils or lib without a <file>.test.ts(x) next to them ($(head -1 <<< "$FRONTEND_LOGIC_OUT"))" "$(tail -n +2 <<< "$FRONTEND_LOGIC_OUT")"

FRONTEND_COMPONENTS_OUT=$(php -r "$FRONTEND_COMPONENTS_UNTESTED" 2> /dev/null)
row tests "frontend-components-without-test" "$(head -1 <<< "$FRONTEND_COMPONENTS_OUT" | awk '{print $1}')" "$(cap frontend-components-without-test)"
detail "Components (.tsx outside Pages and Components/ui) without a <file>.test.tsx next to them ($(head -1 <<< "$FRONTEND_COMPONENTS_OUT"))" "$(tail -n +2 <<< "$FRONTEND_COMPONENTS_OUT")"

DOCS_ROW="docs-older-than-${DOCS_MAX_AGE// /-}"
if [ "$OFFLINE" -eq 1 ] || [ "$(git rev-parse --is-shallow-repository)" = "true" ]; then
    skipped docs "$DOCS_ROW"
elif DOCS_OLD_OUT=$(git log --name-only --no-renames --format='@%ct' -- docs | php -r "$DOCS_OLD" -- "$DOCS_MAX_AGE" 2> /dev/null); then
    row docs "$DOCS_ROW" "$(count_text "$DOCS_OLD_OUT")" 0
    detail "Docs whose last change is older than $DOCS_MAX_AGE: update, archive or delete" "$DOCS_OLD_OUT"
else
    row docs "$DOCS_ROW" "?" 0
fi

if [ "$OFFLINE" -eq 1 ]; then
    for name in npm-vulnerabilities npm-behind-latest npm-majors-behind composer-advisories composer-abandoned composer-behind-latest composer-majors-behind; do
        skipped deps "$name"
    done
else
    NPM_AUDIT=$($NPM audit --json 2> /dev/null)
    row deps npm-vulnerabilities "$(php -r "$JSON_COUNT" -- "isset(\$d['error']) ? throw new RuntimeException('npm audit failed') : \$d['metadata']['vulnerabilities']['total']" <<< "$NPM_AUDIT" 2> /dev/null)" 0
    [ "$DETAILS" -eq 0 ] || detail "npm audit" "$($NPM audit 2> /dev/null | head -80)"

    NPM_OUTDATED=$($NPM outdated --json 2> /dev/null)
    if [ -f node_modules/.package-lock.json ] && NPM_LINES=$(php -r "$NPM_BEHIND" <<< "$NPM_OUTDATED" 2> /dev/null); then
        row deps npm-behind-latest "$(count_text "$NPM_LINES")" 0
        row deps npm-majors-behind "$(count_text "$(awk -F'\t' '$4 == "major"' <<< "$NPM_LINES")")" 0
        detail "npm packages behind the latest release (package, locked, latest, kind)" "$NPM_LINES"
    else
        row deps npm-behind-latest "?" 0
        row deps npm-majors-behind "?" 0
    fi

    COMPOSER_AUDIT=$(composer audit --locked --format=json --no-interaction 2> /dev/null)
    row deps composer-advisories "$(php -r "$JSON_COUNT" -- 'array_sum(array_map(count(...), $d["advisories"]))' <<< "$COMPOSER_AUDIT" 2> /dev/null)" 0
    row deps composer-abandoned "$(php -r "$JSON_COUNT" -- 'count($d["abandoned"])' <<< "$COMPOSER_AUDIT" 2> /dev/null)" 0

    COMPOSER_OUTDATED=$(composer outdated --locked --direct --format=json --no-interaction 2> /dev/null)
    if COMPOSER_LINES=$(php -r "$COMPOSER_BEHIND" <<< "$COMPOSER_OUTDATED" 2> /dev/null); then
        row deps composer-behind-latest "$(count_text "$COMPOSER_LINES")" 0
        row deps composer-majors-behind "$(count_text "$(awk -F'\t' '$4 == "major"' <<< "$COMPOSER_LINES")")" 0
        detail "Direct composer packages behind the latest release (package, locked, latest, kind)" "$COMPOSER_LINES"
    else
        row deps composer-behind-latest "?" 0
        row deps composer-majors-behind "?" 0
    fi
fi

printf '\n%s rows: %s at target, %s open, %s not measured, %s skipped\n' "$((TOTAL + SKIPPED))" "$((TOTAL - OPEN - UNKNOWN))" "$OPEN" "$UNKNOWN" "$SKIPPED"
cat "$DETAIL_FILE"

[ "$REPORT" -eq 1 ] || [ "$((OPEN + UNKNOWN))" -eq 0 ]
