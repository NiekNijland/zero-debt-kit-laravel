<?php

declare(strict_types=1);

use Tests\Support\Unit\ValidatedInputArchitectureHelpers;

dataset('raw input reads', [
    'input' => ['<?php $a = $request->input(\'name\');'],
    'typed reader' => ['<?php $a = $request->string(\'name\')->toString();'],
    'query' => ['<?php $a = $request->query(\'tab\');'],
    'safe' => ['<?php $a = $request->safe()->only([\'a\']);'],
    'boolean' => ['<?php $a = $request->boolean(\'enabled\');'],
    'filled' => ['<?php if ($request->filled(\'code\')) {}'],
    'file' => ['<?php $a = $request->file(\'image\');'],
    'all' => ['<?php $a = $request->all();'],
    'except' => ['<?php $a = $request->except(\'token\');'],
    'helper' => ['<?php $a = request()->input(\'name\');'],
    'helper with a key' => ['<?php $a = request(\'name\');'],
    'facade' => ['<?php use Illuminate\Support\Facades\Request; $a = Request::input(\'name\');'],
    'property' => ['<?php $a = $this->request->integer(\'page\');'],
]);

it('detects input a controller reads past its form request', function (string $source): void {
    expect(ValidatedInputArchitectureHelpers::rawReadLinesIn($source))->toHaveCount(1);
})->with('raw input reads');

it('accepts validated input, accessors of the form request and what is not input', function (): void {
    $source = <<<'PHP'
        <?php
        $a = $request->validated();
        $b = $request->validated('name', 'default');
        $c = $request->validatedString('name');
        $d = $request->searchFilter();
        $e = $request->user();
        $f = $request->url();
        $g = $request->session()->get('key');
        $h = $request->header('Idempotency-Key');
        $i = request()->userAgent();
        $j = $collection->only(['a']);
        $k = $query->get();
        PHP;

    expect(ValidatedInputArchitectureHelpers::rawReadLinesIn($source))->toBe([]);
});

it('reads request input in controllers only from validated data', function (): void {
    expect(ValidatedInputArchitectureHelpers::offendersInControllers())->toBe([]);
});
