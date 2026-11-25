#!/usr/bin/env bash

set -euo pipefail

read -r -d '' RATCHETS <<'TABLE' || true
phpstan-ignore-errors  max  grep    phpstan.neon,phpstan-tests.neon?,phpstan-baseline.neon?  ignoreErrors|baseline
phpstan-exclude-paths  max  grep    phpstan.neon,phpstan-tests.neon?  excludePaths
phpstan-paths          min  neon    phpstan.neon,phpstan-tests.neon?  parameters.paths
phpstan-includes       min  neon    phpstan.neon,phpstan-tests.neon?  includes
phpstan-level-10       min  grep    phpstan.neon,phpstan-tests.neon  ^[[:space:]]+level:[[:space:]]+(10|max)[[:space:]]*$
phpstan-inline         max  grep    app,bootstrap,config,database,routes,tests  @phpstan-ignore
rector-skip            max  grep    rector.php  withSkip
composer-audit-ignore  max  json    composer.json  count((array) ($d['config']['audit']['ignore'] ?? []))
arch-ignoring          max  grep    tests/Unit/ArchAuthorizationTest.php,tests/Unit/ArchClassShapeTest.php,tests/Unit/ArchContainerTest.php,tests/Unit/ArchHttpTest.php,tests/Unit/ArchJsonEndpointsTest.php,tests/Unit/ArchMigrationsConfigTest.php,tests/Unit/ArchModelsTest.php  ->ignoring\(
arch-allowed-files     max  grep    tests/Unit/ArchAuthorizationTest.php,tests/Unit/ArchClassShapeTest.php,tests/Unit/ArchContainerTest.php,tests/Unit/ArchHttpTest.php,tests/Unit/ArchJsonEndpointsTest.php,tests/Unit/ArchMigrationsConfigTest.php,tests/Unit/ArchModelsTest.php  ['"](app|bootstrap|config|database|routes)/[^'"]+\.php['"]
arch-reasoned-lists    max  grep    tests/Unit/ArchAuthorizationTest.php,tests/Unit/ArchClassShapeTest.php,tests/Unit/ArchContainerTest.php,tests/Unit/ArchHttpTest.php,tests/Unit/ArchJsonEndpointsTest.php,tests/Unit/ArchMigrationsConfigTest.php,tests/Unit/ArchModelsTest.php  \$reasoned|neverBuiltByTheContainer
pest-skip              max  grep    tests  \}\)->(skip|todo)\(|->todo\(|markTest(Skipped|Incomplete)\(
test-doubles           max  grep    tests  Mockery::mock\(|(^|[^\\])\$this->mock\(|test\(\)->mock\(
event-fake-all         max  grep    tests  Event::fake\(\)
knip-ignore            max  json    knip.json  leaves(array_filter($d, fn (string $key): bool => str_starts_with($key, 'ignore'), ARRAY_FILTER_USE_KEY))
eslint-disable         max  grep    resources/js,.storybook,vitest.setup.ts  eslint-disable|/\*[[:space:]]*eslint[[:space:]]
eslint-config-off      max  block   eslint.config.js  ^(.*?)\n\s*\/\/\scategory-exemptions  ["'](off|warn)["']
eslint-config-ignores  max  block   eslint.config.js  (?:ignores:\s*|globalIgnores\(\s*)\[([^\]]*)\]  (?<![^\s\[])["'](?![^"']*[*{])[^"']+["']
vitest-skip            max  grep    resources/js  (^|[^A-Za-z0-9_$.])(it|test|describe)\.(skip|todo|only)\(
ts-suppressions        max  grep    resources/js,.storybook,vitest.setup.ts,vite.config.ts  @ts-(expect-error|ignore|nocheck)
tsconfig-exclude       max  json    tsconfig.json  count($d['exclude'] ?? [])
tsconfig-include       min  json    tsconfig.json  count($d['include'] ?? [])
dashboard-cap-headroom min  json    scripts/debt-caps.json  1000 - array_sum($d)
comments               max  grep    app,bootstrap,config,database,resources/js,routes,tests  ^[[:space:]]*(//[^/]|/\*[^*]|\*[ ][^@ /])
TABLE

read -r -d '' COUNTER <<'PHP' || true
function leaves(mixed $value): int
{
    if (! is_array($value)) {
        return 1;
    }
    $found = 0;
    array_walk_recursive($value, function () use (&$found): void {
        $found++;
    });

    return $found;
}

function missing(string $what): never
{
    fwrite(STDERR, $what . "\n");
    exit(3);
}

function evaluate(string $expression): mixed
{
    return eval('return ' . $expression . ';');
}

function collect(array $tokens, int $from, string $stop): array
{
    $depth = 0;
    $text = '';
    for ($i = $from; $i < count($tokens); $i++) {
        $token = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        if ($depth === 0 && $token === $stop) {
            return [$text, $i];
        }
        $depth += match ($token) {
            '(', '[', '{', '${', '{$' => 1,
            ')', ']', '}' => -1,
            default => 0,
        };
        $text .= $token;
    }
    missing('unterminated expression');
}

function significant(array $tokens, int $i, int $step): int
{
    do {
        $i += $step;
    } while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));

    return $i;
}

function neon(string $source, string $target): int
{
    $stack = [];
    $total = 0;
    $found = false;
    $entry = null;
    foreach (preg_split('/\R/', $source) as $line) {
        if (preg_match('/^\s*(#.*)?$/', $line)) {
            continue;
        }
        preg_match('/^(\s*)(.*)$/', $line, $parts);
        $indent = strlen($parts[1]);
        while ($stack !== [] && end($stack)['indent'] >= $indent) {
            array_pop($stack);
        }
        if ($entry !== null && count($stack) <= $entry['index']) {
            $entry = null;
        }
        $keys = array_column(array_filter($stack, fn (array $node): bool => $node['key'] !== null), 'key');
        $path = implode('.', $keys);
        $direct = ! in_array(null, array_column($stack, 'key'), true);
        if (preg_match('/^-(\s|$)/', $parts[2])) {
            if ($direct && ($path === $target || str_starts_with($path, $target . '.'))) {
                $total++;
                $found = true;
                $entry = ['indent' => $indent, 'index' => count($stack)];
            }
            $stack[] = ['indent' => $indent, 'key' => null];

            continue;
        }
        if (! preg_match('/^([A-Za-z0-9_.-]+):\s*(.*)$/', $parts[2], $pair)) {
            continue;
        }
        if ($entry !== null && $pair[1] === 'count' && count($stack) === $entry['index'] + 1 && end($stack)['indent'] === $entry['indent'] && ctype_digit($pair[2])) {
            $total += (int) $pair[2] - 1;
            $entry = null;
        }
        $full = $path === '' ? $pair[1] : $path . '.' . $pair[1];
        if ($full === $target && $direct) {
            $found = true;
            if (preg_match('/^\[(.*)\]$/', $pair[2], $inline)) {
                $total += count(array_filter(array_map('trim', explode(',', $inline[1])), 'strlen'));
            }
        }
        $stack[] = ['indent' => $indent, 'key' => $pair[1]];
    }
    $found || missing('no ' . $target);

    return $total;
}

[, $kind, $spec] = $argv + [null, '', ''];
$source = stream_get_contents(STDIN);

try {
    $count = match ($kind) {
        'return' => (function () use ($source): int {
            $file = tempnam(sys_get_temp_dir(), 'ratchet');
            file_put_contents($file, $source);
            $value = require $file;
            unlink($file);

            return leaves($value);
        })(),
        'const', 'call' => (function () use ($kind, $spec, $source): int {
            $tokens = token_get_all($source);
            $names = explode(',', $spec);
            $seen = [];
            $total = 0;
            foreach ($tokens as $i => $token) {
                if (! is_array($token) || $token[0] !== T_STRING || ! in_array($token[1], $names, true)) {
                    continue;
                }
                $before = $tokens[significant($tokens, $i, -1)] ?? null;
                $after = significant($tokens, $i, 1);
                if ($kind === 'const' && is_array($before) && $before[0] === T_CONST && ($tokens[$after] ?? null) === '=') {
                    [$expression] = collect($tokens, $after + 1, ';');
                    $total += leaves(evaluate($expression));
                    $seen[$token[1]] = true;
                }
                if ($kind === 'call' && is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true) && ($tokens[$after] ?? null) === '(') {
                    [$arguments] = collect($tokens, $after + 1, ')');
                    $total += leaves(evaluate('[' . $arguments . ']'));
                    $seen[$token[1]] = true;
                }
            }
            if ($kind === 'const' ? count($seen) !== count($names) : $seen === []) {
                missing('no ' . $kind . ' ' . $spec);
            }

            return $total;
        })(),
        'json' => (function () use ($spec, $source): int {
            $d = json_decode($source, true, 512, JSON_THROW_ON_ERROR);

            return (int) eval('return ' . $spec . ';');
        })(),
        'neon' => neon($source, $spec),
        'block' => (function () use ($spec, $source): int {
            [$block, $item] = preg_split('/\s+/', $spec, 2);
            preg_match_all('/' . $block . '/s', $source, $blocks) || missing('no ' . $block);

            return array_sum(array_map(fn (string $text): int => preg_match_all('/' . $item . '/', $text), $blocks[1]));
        })(),
    };
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}

echo $count;
PHP

count() {
    local revision="$1" method="$2" spec="$3" path status=0 result
    shift 3
    local -a present=()

    for path in "$@"; do
        if git cat-file -e "$revision:${path%\?}" 2>/dev/null; then
            present+=("${path%\?}")
        elif [ "${path%\?}" = "$path" ]; then
            echo "missing ${path}" >&2
            return 3
        fi
    done

    if [ "$method" = "grep" ]; then
        result=$(git grep -I -h -o -E -e "$spec" "$revision" -- "${present[@]}") || status=$?
        [ "$status" -le 1 ] || return 2
        [ -n "$result" ] && wc -l <<< "$result" | tr -d ' ' || echo 0
        return 0
    fi

    for path in "${present[@]}"; do
        git show "$revision:$path"
    done | php -r "$COUNTER" -- "$method" "$spec"
}

if [ "${1:-}" = "--head" ]; then
    while read -r name direction method paths spec; do
        [ -n "$name" ] || continue
        IFS=, read -ra files <<< "$paths"
        printf '%s %s %s\n' "$name" "$direction" "$(count "${RATCHET_HEAD:-HEAD}" "$method" "$spec" "${files[@]}")"
    done <<< "$RATCHETS"
    exit 0
fi

DESTINATION="${GITHUB_BASE_REF:-main}"

fail() {
    echo "Ratchets: $1" >&2
    exit 1
}

git fetch -q origin "$DESTINATION" || fail "cannot fetch origin/$DESTINATION"
DESTINATION_SHA=$(git rev-parse FETCH_HEAD)
HEAD_SHA=$(git rev-parse "${RATCHET_HEAD:-HEAD}")

find_base() {
    BASE=$(git merge-base "$HEAD_SHA" "$DESTINATION_SHA" 2>/dev/null)
}

for deepen in 200 2000 all; do
    find_base && break
    [ "$(git rev-parse --is-shallow-repository)" = "true" ] || break
    if [ "$deepen" = "all" ]; then
        git fetch -q --unshallow origin "$DESTINATION" ${GITHUB_HEAD_REF:+"$GITHUB_HEAD_REF"} || fail "cannot unshallow the clone"
    else
        git fetch -q --deepen="$deepen" origin "$DESTINATION" ${GITHUB_HEAD_REF:+"$GITHUB_HEAD_REF"} || fail "cannot deepen the clone"
    fi
done
find_base || fail "no merge base between $HEAD_SHA and origin/$DESTINATION, even after unshallowing the clone"

FAILED=0
ERRORS=$(mktemp)
trap 'rm -f "$ERRORS"' EXIT

echo "Ratchets against origin/$DESTINATION (merge base ${BASE:0:9}, head ${HEAD_SHA:0:9})"
printf '%-22s %4s %7s %7s %7s  %s\n' "ratchet" "may" "base" "head" "change" "result"

while read -r name direction method paths spec; do
    [ -n "$name" ] || continue
    IFS=, read -ra files <<< "$paths"

    status=0
    before=$(count "$BASE" "$method" "$spec" "${files[@]}" 2>/dev/null) || status=$?
    [ "$status" -eq 0 ] || [ "$status" -eq 3 ] || fail "cannot count $name at the merge base"
    [ "$status" -eq 0 ] || before=0

    status=0
    after=$(count "$HEAD_SHA" "$method" "$spec" "${files[@]}" 2>"$ERRORS") || status=$?
    [ "$status" -ne 3 ] || fail "$name: $(cat "$ERRORS") at the head. A list or file moved; update its row in scripts/ci-ratchets.sh"
    [ "$status" -eq 0 ] || fail "cannot count $name at the head: $(cat "$ERRORS")"
    [[ "$before" =~ ^[0-9]+$ && "$after" =~ ^[0-9]+$ ]] || fail "$name did not count to a number ($before, $after)"

    change=$((after - before))
    if [ "$direction" = "max" ]; then
        worse=$((change > 0))
        allowed_way="down"
    else
        worse=$((change < 0))
        allowed_way="up"
    fi
    result="ok"
    allowed=""

    if [ "$worse" -eq 1 ]; then
        for commit in $(git log --full-history --no-merges --format=%h "$BASE..$HEAD_SHA" -- "${files[@]%\?}"); do
            reason=$(git log -1 --format=%B "$commit" | grep -o -E "\[ratchet: $name\][[:space:]]*[^[:space:]].*" | sed -E "s/^\[ratchet: $name\][[:space:]]*//" | head -1 || true)
            [ -z "$reason" ] || allowed+="                         allowed by $commit: $reason"$'\n'
        done
        if [ "$allowed_way" = "down" ] && [ "$before" -eq 0 ]; then
            allowed=""
            result="FAILS"
            FAILED=1
        elif [ -n "$allowed" ]; then
            result="allowed"
        else
            result="FAILS"
            FAILED=1
        fi
    fi

    printf '%-22s %4s %7s %7s %+7d  %s\n' "$name" "$allowed_way" "$before" "$after" "$change" "$result"
    [ -z "$allowed" ] || printf '%s' "$allowed"
done <<< "$RATCHETS"

if [ "$FAILED" -ne 0 ]; then
    echo
    echo "A ratchet moved the wrong way. A row that is at 0 never takes an override: fix the entries. Other rows can be explained in a commit that touches that row's files:"
    echo "  [ratchet: <name>] <reason>"
    exit 1
fi
