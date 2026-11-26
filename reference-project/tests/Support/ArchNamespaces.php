<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

/**
 * Pest throws a `DirectoryNotFoundException` for an `arch()` rule over a namespace with a wildcard (`App\Modules\*\Exceptions`) when no folder matches
 * it, while it passes a rule over a plain namespace with no folder and one over an empty list. A project cannot keep a rule for exceptions, commands or
 * jobs before its first such class unless the rule works on an absent folder: `$app->existing('App\Modules\*\Exceptions')` answers the namespace when a
 * folder matches it and an empty list when none does, so the rule is on from the first day and checks the first class when it arrives.
 *
 * The rule over the folders that do not exist yet is held by the layout guard (the folder names) and, once a class exists, by this rule.
 */
final readonly class ArchNamespaces
{
    /**
     * @param string $root the repository root
     * @param string $prefix the root namespace of the application, without a trailing backslash
     * @param string $folder the folder of that namespace, relative to the root
     */
    public function __construct(
        private string $root,
        private string $prefix,
        private string $folder,
    ) {
    }

    /**
     * @return string|list<never> the namespace when a folder matches it, an empty list (a rule with nothing to check) when none does
     */
    public function existing(string $namespace): string|array
    {
        if (! str_starts_with($namespace, $this->prefix . '\\')) {
            throw new InvalidArgumentException('The namespace ' . $namespace . ' is not below ' . $this->prefix . ': name a namespace of the application');
        }

        $relative = str_replace('\\', '/', substr($namespace, strlen($this->prefix) + 1));

        return Globs::of($this->root . '/' . $this->folder . '/' . $relative, GLOB_ONLYDIR) === [] ? [] : $namespace;
    }
}
