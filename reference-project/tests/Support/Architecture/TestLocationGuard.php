<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * A test sits in the folder of the class it covers: `tests/<tier>/<folder>/<Class>Test.php` mirrors `<app>/<folder>/<Class>.php`.
 * A feature test that covers a flow sits in a folder of the app, or in the home folder of code outside the app.
 */
final readonly class TestLocationGuard
{
    /**
     * @param list<string> $tiers the folders of the test folder that hold tests, such as the unit, feature and integration tiers
     * @param array<string, string> $homes the folders of a tier that cover code outside the app folder, with the folder of the repository they cover
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $appFolder,
        private string $testFolder,
        private array $tiers,
        private array $homes,
    ) {
    }

    /**
     * @return list<string> `<test file>: <what is wrong>` per test that does not sit where the code it covers sits
     */
    public function violations(): array
    {
        $appRoot = $this->root . '/' . $this->appFolder . '/';
        $classes = [];
        $folders = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->appFolder) as $file) {
            $relative = mb_substr($file, mb_strlen($appRoot));
            $folder = dirname($relative) === '.' ? '' : dirname($relative);
            $classes[basename($relative, '.php')][$folder] = true;

            for ($ancestor = $folder; $ancestor !== '' && $ancestor !== '.'; $ancestor = dirname($ancestor)) {
                $folders[$ancestor] = true;
            }
        }

        $violations = [];

        foreach ($this->tiers as $tier) {
            $tierRoot = $this->root . '/' . $this->testFolder . '/' . $tier . '/';

            foreach ($this->inspector->phpFiles($tierRoot, mayBeAbsent: true) as $file) {
                if (! str_ends_with($file, 'Test.php')) {
                    continue;
                }

                $relative = mb_substr($file, mb_strlen($tierRoot));
                $folder = dirname($relative) === '.' ? '' : dirname($relative);
                $name = basename($relative, 'Test.php');
                $path = $this->testFolder . '/' . $tier . '/' . $relative;

                if (isset($classes[$name])) {
                    if (! isset($classes[$name][$folder])) {
                        $violations[] = $path . ': the class ' . $name . ' lives in ' . $this->appFolder . '/' . implode(' and ' . $this->appFolder . '/', array_keys($classes[$name])) . ', the test belongs in the same folder of ' . $this->testFolder . '/' . $tier;
                    }

                    continue;
                }

                $home = explode('/', $folder)[0];

                if ($folder !== '' && ! isset($folders[$folder]) && ! isset($this->homes[$home])) {
                    $violations[] = $path . ': ' . $this->appFolder . '/' . $folder . ' does not exist, the test belongs in the folder of the code it covers';
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
