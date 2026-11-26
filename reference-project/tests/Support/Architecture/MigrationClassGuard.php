<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;

/**
 * A migration that ran in production is history: it names no application class, because a class that moves or is deleted must not break a migration
 * that has run. The live role and permission enums are named in the same breath, since they are the classes that change most.
 */
final readonly class MigrationClassGuard
{
    /**
     * @param string $folder the migration folder, relative to the root
     * @param string $liveEnums the namespace of the live role and permission enums, as written in a `use`
     * @param string $applicationNamespace the root namespace of the application, with one trailing backslash
     */
    public function __construct(
        private string $root,
        private string $folder,
        private string $liveEnums,
        private string $applicationNamespace,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $files = Globs::of($this->root . '/' . $this->folder . '/*.php');
        $violations = $files === [] ? [$this->folder . ': no migration was read, so the guard has read nothing: is the folder renamed?'] : [];

        foreach ($files as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $message) {
                $violations[] = substr($file, strlen($this->root) + 1) . ': ' . $message;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $messages = [];

        if (preg_match('/' . preg_quote($this->liveEnums, '/') . '\\\\(Permission|Role)\b|(?<![\w\\\\])(Permission|Role)::/', $source) === 1) {
            $messages[] = 'a migration uses the live permission or role class: write the name as a string, so a rename of the class cannot break a migration that has run';
        }

        if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($this->applicationNamespace, '/') . '/', $source) === 1) {
            $messages[] = 'a migration names an application class: write the table, the column and the value as they are, so a move of the class cannot break a migration that has run';
        }

        return $messages;
    }
}
