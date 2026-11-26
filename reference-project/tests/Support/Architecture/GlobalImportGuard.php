<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;

/**
 * A file without a namespace gains nothing from `use Name;`: PHP ignores the import of a global class there and warns. The guard
 * fails the file. It reads the parsed statements, so a comment after the line, an indent, a line break of any kind, a digit in
 * the name, several names in one `use`, a leading backslash and a `namespace { ... }` block are one spelling each, and a word
 * `namespace` in a string, a heredoc or a comment is not a namespace. A function or constant import of a bare name is as
 * ineffective as a class import (PHP warns for all three); an aliased import and a qualified name keep their effect and pass.
 */
final readonly class GlobalImportGuard
{
    private const string MESSAGE = 'imports a global class by its bare name in a file without a namespace, which PHP ignores: delete the use line and write the class as it is, or declare a namespace';

    /**
     * @param list<string> $folders folders under the root that the tree scan reads, relative to it
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
    ) {
    }

    /**
     * @return list<string> the messages for the source: none, or one
     */
    public function inSource(string $source): array
    {
        $statements = PhpSource::parse($source);
        $scope = self::globalScope($statements);

        foreach ($scope as $statement) {
            if ($statement instanceof Use_ && self::hasBareName($statement)) {
                return [self::MESSAGE];
            }
        }

        return [];
    }

    /**
     * @return list<string> `path: message` per file
     */
    public function violations(): array
    {
        $violations = FolderScan::over($this->inspector, $this->root, $this->folders, fn (string $path, string $source): array => $this->inSource($source));

        sort($violations);

        return $violations;
    }

    /**
     * The statements that sit in the global namespace: the top level of a file with no namespace declaration, or the body of
     * a `namespace { ... }` block. A file with a named namespace has none.
     *
     * @param array<Stmt> $statements
     * @return array<Stmt>
     */
    private static function globalScope(array $statements): array
    {
        $scope = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Namespace_) {
                if ($statement->name === null) {
                    array_push($scope, ...$statement->stmts);
                }

                continue;
            }

            $scope[] = $statement;
        }

        return array_values(array_filter($statements, static fn (Stmt $statement): bool => $statement instanceof Namespace_ && $statement->name !== null)) === [] ? $scope : [];
    }

    private static function hasBareName(Use_ $statement): bool
    {
        foreach ($statement->uses as $item) {
            if (count($item->name->getParts()) === 1 && $item->alias === null) {
                return true;
            }
        }

        return false;
    }
}
