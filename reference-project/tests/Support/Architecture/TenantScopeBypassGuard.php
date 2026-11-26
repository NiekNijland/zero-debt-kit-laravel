<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Str;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpToken;

/**
 * The tenant scope is bypassed in one file, and a modeled table is never queried raw. A call that removes a global scope is found by name, and
 * `DB::table('<a table of a model>')` is found by the table, which the guard reads from the model folder: the declared `$table`, or the plural of
 * the class name.
 */
final readonly class TenantScopeBypassGuard
{
    private const array BYPASS_METHODS = ['withoutglobalscope', 'withoutglobalscopes', 'newquerywithoutscope', 'newquerywithoutscopes'];

    /**
     * @param string $models the model folder, relative to the root
     * @param string $allowed the path relative to the root of the one file that removes the tenant scopes
     * @param string $owner the short name of the owner, for the message
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private string $models,
        private string $allowed,
        private string $owner,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $tables = $this->tables();
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            $path = substr($file, strlen($this->root) + 1);

            if ($path === $this->allowed) {
                if ($this->inSource((string) file_get_contents($file), $tables) === []) {
                    $violations[] = $path . ': ' . $this->owner . ' no longer removes a tenant scope: the exemption of this file is stale, remove it';
                }

                continue;
            }

            foreach ($this->inSource((string) file_get_contents($file), $tables) as $message) {
                $violations[] = $path . ': ' . $message;
            }
        }

        return $violations;
    }

    /**
     * @return list<string> the table of every concrete class of the model folder
     */
    public function tables(): array
    {
        $tables = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->models) as $file) {
            $statements = new ParserFactory()->createForHostVersion()->parse((string) file_get_contents($file)) ?? [];

            foreach (new NodeFinder()->findInstanceOf($statements, Class_::class) as $class) {
                if ($class->isAbstract() || $class->name === null) {
                    continue;
                }

                $declared = null;

                foreach (new NodeFinder()->findInstanceOf([$class], PropertyItem::class) as $property) {
                    if ($property->name->toString() === 'table' && $property->default instanceof String_) {
                        $declared = $property->default->value;
                    }
                }

                $tables[] = $declared ?? Str::snake(Str::pluralStudly($class->name->toString()));
            }
        }

        return $tables;
    }

    /**
     * @param list<string> $tables the tables of the models
     * @return list<string>
     */
    public function inSource(string $source, array $tables): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $messages = [];

        foreach ($tokens as $index => $token) {
            $next = $tokens[$index + 1] ?? null;

            if ($token->is(T_STRING) && in_array(strtolower($token->text), self::BYPASS_METHODS, true) && $next?->text === '(') {
                $messages[] = 'line ' . $token->line . ': ' . $token->text . '() removes the tenant scope: read another tenant\'s rows with ->acrossTenants() on the model, a scope of ' . $this->owner;
            }

            if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && in_array(ltrim($token->text, '\\'), ['DB', 'Illuminate\\Support\\Facades\\DB'], true)
                && $next?->is(T_DOUBLE_COLON) === true && ($tokens[$index + 2] ?? null)?->text === 'table' && ($tokens[$index + 3] ?? null)?->text === '('
                && ($tokens[$index + 4] ?? null)?->is(T_CONSTANT_ENCAPSED_STRING) === true) {
                $table = (string) preg_replace('/ as .*$/i', '', trim($tokens[$index + 4]->text, '\'"'));

                if (in_array($table, $tables, true)) {
                    $messages[] = 'line ' . $token->line . ': DB::table(\'' . $table . '\') queries a table that has a model: query the model, so its scopes apply';
                }
            }
        }

        return $messages;
    }
}
