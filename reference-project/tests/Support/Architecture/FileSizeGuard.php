<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use Throwable;

/**
 * A PHP file has at most 400 lines, tests included. The one category that is exempt is a data file: a file whose only
 * statement is `return [...]` (a list of translations, a generated table). The category is read from the code, so no
 * folder is named here and a data folder that gets a class in it is checked again.
 */
final readonly class FileSizeGuard
{
    public const int LIMIT = 400;

    /**
     * @param list<string> $folders the folders to scan, relative to the root
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->folders as $folder) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $folder) as $file) {
                $violation = $this->inSource(substr($file, strlen($this->root) + 1), (string) file_get_contents($file));

                if ($violation !== null) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }

    public function inSource(string $path, string $source): ?string
    {
        $lines = substr_count($source, "\n");

        if ($lines <= self::LIMIT || self::isData($source)) {
            return null;
        }

        return $path . ' has ' . $lines . ' lines, the limit is ' . self::LIMIT . ': split it by behavior (a test into <Class><Topic>Test.php, a class into the classes it already holds), not by size; a file that only returns a list of data is exempt';
    }

    private static function isData(string $source): bool
    {
        try {
            $statements = PhpSource::parse($source);
        } catch (Throwable) {
            return false;
        }

        $statements = array_values(array_filter($statements, static fn ($statement): bool => ! $statement instanceof Declare_ && ! $statement instanceof Nop));

        return count($statements) === 1 && $statements[0] instanceof Return_ && $statements[0]->expr instanceof Array_;
    }
}
