<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * Queries are written as scopes of the model: no custom Eloquent builder and no query-string builder.
 */
final readonly class EloquentBuilderGuard
{
    /**
     * @param string $queryStringPackage the namespace of the query-string builder package, with single backslashes
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private string $queryStringPackage,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over($this->inspector, $this->root, [$this->folder], fn (string $path, string $source): array => $this->inSource($source));
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $pattern = '/\bnewEloquentBuilder\b|UseEloquentBuilder|' . preg_quote($this->queryStringPackage, '/') . '\b/';

        return preg_match($pattern, $source) === 1 ? ['a custom Eloquent builder or a query-string builder: write the query as a scope of the model and start it at Model::query()'] : [];
    }
}
