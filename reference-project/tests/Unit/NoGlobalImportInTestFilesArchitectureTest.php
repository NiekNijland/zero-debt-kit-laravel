<?php

declare(strict_types=1);

uses()->group('arch');

it('never imports a global class by its bare name in a test file without a namespace', function (): void {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (preg_match('/^namespace\s/m', $source) === 1) {
            continue;
        }

        if (preg_match('/^use [A-Za-z_]+;$/m', $source) === 1) {
            $offenders[] = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
