<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * A model event listener registered through `observe()`: on the class (`User::observe(...)`, `static::observe(...)`, an aliased
 * or fully qualified name, a variable class `$model::observe(...)`), on an instance (`$model->observe(...)`, nullsafe too) and
 * in the first-class-callable form (`User::observe(...)`). The rule: a model registers its events in `booted()`. The calls come
 * from the shared reader, so a comment, a string and a method of another name are not read. A method that is only named
 * `observe` on a class that is not a model is the one near miss the guard cannot tell apart (reasoned, not run against real code).
 */
final readonly class ObserverRegistrationGuard
{
    private const string MESSAGE = 'an observer registered with observe(): register the model events in booted() of the model, with static::created(...) and the other event methods';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string> `line N: message` per call in the source
     */
    public function inSource(string $source): array
    {
        $found = [];

        foreach ($this->inspector->calls($source) as $call) {
            if ($call['name'] === 'observe' && in_array($call['kind'], ['static', 'method', 'nullsafe'], true)) {
                $found[] = 'line ' . $call['line'] . ': ' . self::MESSAGE;
            }
        }

        return $found;
    }

    /**
     * @return list<string> `file:line: message` relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $message) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . mb_substr($message, mb_strlen('line '));
            }
        }

        sort($violations);

        return $violations;
    }
}
