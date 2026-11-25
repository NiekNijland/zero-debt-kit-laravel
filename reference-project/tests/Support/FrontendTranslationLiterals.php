<?php

declare(strict_types=1);

namespace Tests\Support;

final class FrontendTranslationLiterals
{
    private const string TRANSLATOR_CALL = '/(?<![\w$.])(?:t|translation|translate)\(/';

    private const string KEY_PROPERTY = '/(?<![\w$.])[a-z][\w$]*Key\s*(?<operator>=(?![=>])|:)/';

    private const string KEY_MAP = '/\b(?:const|let)\s+[A-Za-z][\w$]*(?:Keys?|KEYS?|Labels?|LABELS?|Messages?|MESSAGES?)\s*(?::[^=;{]*)?=(?![=>])\s*(?=[{\[])/';

    private const string KEY_CONSTANT = '/\b(?:const|let)\s+[A-Z][A-Z0-9_]*(?:MESSAGE|LABEL|TITLE|ERROR|FAILED)(?:_KEY)?\s*(?::[^=;{]*)?=(?![=>])/';

    private const string KEY_SHAPE = '/^[a-z][\w-]*(\.[\w-]+)+$/';

    private const array JOINS_A_STRING = ['===', '!==', '==', '!=', '+', 'in', 'instanceof', 'typeof'];

    private const array INDEXES_A_STRING = ['.', '?.', '['];

    private const string NESTED = 'nested';

    /**
     * @return list<array{0: 'keys'|'templates', 1: string, 2: int}>
     */
    public static function literalsIn(string $source): array
    {
        $literals = [];

        preg_match_all(self::TRANSLATOR_CALL, $source, $calls, PREG_OFFSET_CAPTURE);

        foreach ($calls[0] as [$call, $offset]) {
            foreach (self::operands(self::expression($source, $offset + strlen($call))) as $token) {
                $literals[] = self::literal($token, keyShaped: false);
            }
        }

        preg_match_all(self::KEY_PROPERTY, $source, $properties, PREG_OFFSET_CAPTURE);

        foreach ($properties[0] as $index => [$property, $offset]) {
            $start = $offset + strlen($property);
            $first = self::token($source, $start);

            if ($first === null) {
                continue;
            }

            $assigned = $properties['operator'][$index][0] === '=';
            $tokens = match (true) {
                $assigned && in_array($first[0], ['string', 'template'], true) => [$first],
                $assigned && $first[1] === '{' => self::operands(self::expression($source, $first[2])),
                default => self::operands(self::expression($source, $start)),
            };

            foreach ($tokens as $token) {
                $literals[] = self::literal($token, keyShaped: true);
            }
        }

        preg_match_all(self::KEY_CONSTANT, $source, $constants, PREG_OFFSET_CAPTURE);

        foreach ($constants[0] as [$constant, $offset]) {
            $value = self::token($source, $offset + strlen($constant));

            if ($value !== null && $value[0] === 'string') {
                $literals[] = self::literal($value, keyShaped: true);
            }
        }

        preg_match_all(self::KEY_MAP, $source, $maps, PREG_OFFSET_CAPTURE);

        foreach ($maps[0] as [$map, $offset]) {
            foreach (self::values($source, $offset + strlen($map)) as $token) {
                $literals[] = self::literal($token, keyShaped: true);
            }
        }

        return array_values(array_filter($literals));
    }

    /**
     * @param array{0: string, 1: string, 2: int, 3: int} $token
     * @return array{0: 'keys'|'templates', 1: string, 2: int}|null
     */
    private static function literal(array $token, bool $keyShaped): ?array
    {
        [$type, $value, , $offset] = $token;

        if ($type === 'template' && str_contains($value, FrontendTranslationKeys::PLACEHOLDER)) {
            return $keyShaped ? null : ['templates', $value, $offset];
        }

        if ($keyShaped && preg_match(self::KEY_SHAPE, $value) !== 1) {
            return null;
        }

        return $value === '' || str_contains($value, '\\') ? null : ['keys', $value, $offset];
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int}>
     */
    private static function expression(string $source, int $offset): array
    {
        $tokens = [];
        $brackets = [];
        $previous = null;

        while (($token = self::token($source, $offset)) !== null) {
            $offset = $token[2];
            $punctuation = $token[0] === 'punct' ? $token[1] : null;

            if ($brackets === [] && in_array($punctuation, [',', ';', ')', ']', '}'], true)) {
                break;
            }

            if (in_array($punctuation, [')', ']', '}'], true)) {
                array_pop($brackets);
            }

            $nested = in_array(self::NESTED, $brackets, true);
            $tokens[] = $nested ? [self::NESTED, '', $offset, $offset] : $token;

            if (in_array($punctuation, ['(', '[', '{'], true)) {
                $groups = $punctuation === '(' && ! $nested
                    && ! in_array($previous[0] ?? null, ['word', 'string', 'template'], true)
                    && ! in_array($previous[1] ?? null, [')', ']'], true);
                $brackets[] = $groups ? 'group' : self::NESTED;
            }

            $previous = $token;
        }

        return $tokens;
    }

    /**
     * @param list<array{0: string, 1: string, 2: int, 3: int}> $tokens
     * @return list<array{0: string, 1: string, 2: int, 3: int}>
     */
    private static function operands(array $tokens): array
    {
        $operands = [];

        foreach ($tokens as $index => $token) {
            if (! in_array($token[0], ['string', 'template'], true)) {
                continue;
            }

            $before = $tokens[$index - 1][1] ?? null;
            $after = $tokens[$index + 1][1] ?? null;

            if (! in_array($before, self::JOINS_A_STRING, true) && ! in_array($after, [...self::JOINS_A_STRING, ...self::INDEXES_A_STRING], true)) {
                $operands[] = $token;
            }
        }

        return $operands;
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int}>
     */
    private static function values(string $source, int $offset): array
    {
        $values = [];
        $depth = 0;
        $previous = null;
        $pending = null;

        while (($token = self::token($source, $offset)) !== null) {
            $offset = $token[2];

            if ($pending !== null) {
                [$candidate, $before] = $pending;
                $isPropertyName = $token[1] === ':' && in_array($before, ['{', ','], true);

                if (! $isPropertyName && ! in_array($token[1], [...self::JOINS_A_STRING, ...self::INDEXES_A_STRING], true)) {
                    $values[] = $candidate;
                }

                $pending = null;
            }

            if ($token[0] === 'punct' && in_array($token[1], ['(', '[', '{'], true)) {
                $depth++;
            } elseif ($token[0] === 'punct' && in_array($token[1], [')', ']', '}'], true) && --$depth === 0) {
                break;
            } elseif (in_array($token[0], ['string', 'template'], true) && ! in_array($previous, self::JOINS_A_STRING, true)) {
                $pending = [$token, $previous];
            }

            $previous = $token[1];
        }

        return $values;
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: int}|null
     */
    private static function token(string $source, int $offset): ?array
    {
        $length = strlen($source);

        while ($offset < $length) {
            if (ctype_space($source[$offset])) {
                $offset++;
            } elseif (substr($source, $offset, 2) === '//') {
                $end = strpos($source, "\n", $offset);
                $offset = $end === false ? $length : $end;
            } elseif (substr($source, $offset, 2) === '/*') {
                $end = strpos($source, '*/', $offset + 2);
                $offset = $end === false ? $length : $end + 2;
            } else {
                break;
            }
        }

        if ($offset >= $length) {
            return null;
        }

        $start = $offset;
        $character = $source[$offset];

        if ($character === '"' || $character === "'") {
            $end = $offset + 1;

            while ($end < $length && $source[$end] !== $character && $source[$end] !== "\n") {
                $end += $source[$end] === '\\' ? 2 : 1;
            }

            return ['string', substr($source, $offset + 1, $end - $offset - 1), $end + 1, $start];
        }

        if ($character === '`') {
            return self::template($source, $offset);
        }

        if (preg_match('/\G(?:[A-Za-z_$][\w$]*|\d[\w.]*|===|!==|\?\?=|\?\?|\?\.|==|!=|=>|&&|\|\||\.\.\.|[<>]=?|.)/s', $source, $match, 0, $offset) === 1) {
            return [preg_match('/^[A-Za-z_$\d]/', $match[0]) === 1 ? 'word' : 'punct', $match[0], $offset + strlen($match[0]), $start];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: int}
     */
    private static function template(string $source, int $offset): array
    {
        $length = strlen($source);
        $shape = '';
        $position = $offset + 1;

        while ($position < $length && $source[$position] !== '`') {
            if ($source[$position] === '\\') {
                $shape .= substr($source, $position, 2);
                $position += 2;
            } elseif (substr($source, $position, 2) === '${') {
                $shape .= FrontendTranslationKeys::PLACEHOLDER;
                $position += 2;
                $depth = 1;

                while ($depth > 0 && ($token = self::token($source, $position)) !== null) {
                    $position = $token[2];

                    if ($token[1] === '{') {
                        $depth++;
                    } elseif ($token[1] === '}') {
                        $depth--;
                    }
                }
            } else {
                $shape .= $source[$position];
                $position++;
            }
        }

        return ['template', $shape, $position + 1, $offset];
    }
}
