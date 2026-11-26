<?php

declare(strict_types=1);

use Tests\Support\Architecture\ClosedClassGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$run = static function (array $files, array $open = []): array {
    $tree = ['app/.gitkeep' => ''];

    foreach (Narrow::stringMap($files) as $path => $declaration) {
        $tree[$path] = str_contains($declaration, '<?php') ? $declaration : "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Modules\\Orders\\Support;\n\n{$declaration}\n";
    }

    $root = SourceSandbox::create($tree);

    try {
        $guard = new ClosedClassGuard(new PhpFileInspector(), $root, 'app', Narrow::strings($open));
        $violations = $guard->violations();

        return [$violations, $guard->explain($violations)];
    } finally {
        SourceSandbox::remove($root);
    }
};

dataset('classes that are open', [
    'a concrete class that is not final' => [['app/Modules/Orders/Support/Cart.php' => 'class Cart {}'], [], ['open-class App\Modules\Orders\Support\Cart'], 'App\Modules\Orders\Support\Cart is not final: declare it `final`'],
    'a readonly class that is not final' => [['app/Modules/Orders/Support/Money.php' => 'readonly class Money {}'], [], ['open-class App\Modules\Orders\Support\Money'], 'is not final'],
    'a concrete parent that another class extends' => [['app/Modules/Orders/Support/Cart.php' => 'class Cart {}', 'app/Modules/Orders/Support/GiftCart.php' => 'final class GiftCart extends Cart {}'], [], ['extended-concrete App\Modules\Orders\Support\Cart', 'open-class App\Modules\Orders\Support\Cart'], 'make the parent `abstract`'],
    'a final parent that another class extends' => [['app/Modules/Orders/Support/Cart.php' => 'final class Cart {}', 'app/Modules/Orders/Support/GiftCart.php' => 'final class GiftCart extends Cart {}'], [], ['extended-concrete App\Modules\Orders\Support\Cart'], 'is concrete and another class extends it'],
    'a line of the open list for a class that is final' => [['app/Modules/Orders/Support/Cart.php' => 'final class Cart {}'], ['App\Modules\Orders\Support\Cart'], ['stale-open App\Modules\Orders\Support\Cart'], 'is on the list of open classes and is final, abstract or gone: delete the line'],
    'a line of the open list for a class that is gone' => [[], ['App\Modules\Orders\Support\Gone'], ['stale-open App\Modules\Orders\Support\Gone'], 'delete the line'],
    'a file the parser cannot read' => [['app/Broken.php' => "<?php\nfinal class Broken {\n"], [], ['unreadable app/Broken.php: Syntax error, unexpected EOF on line 3'], 'unreadable app/Broken.php'],
]);

it('fails a concrete class that is open, a concrete class that is extended and a stale line of the open list', function (array $files, array $open, array $entries, string $words) use ($run): void {
    [$violations, $explained] = $run($files, $open);

    expect($violations)->toBe($entries)
        ->and(implode("\n", $explained))->toContain($words);
})->with('classes that are open');

dataset('classes that are closed', [
    'a final class and a final readonly class' => [['app/Modules/Orders/Support/Cart.php' => 'final class Cart {}', 'app/Modules/Orders/Support/Money.php' => 'final readonly class Money {}'], []],
    'an abstract base with a final child' => [['app/Modules/Orders/Support/Base.php' => 'abstract class Base {}', 'app/Modules/Orders/Support/Cart.php' => 'final class Cart extends Base {}'], []],
    'an interface, a trait and an enum' => [['app/Modules/Orders/Support/Shape.php' => 'interface Shape {}', 'app/Modules/Orders/Support/HasShape.php' => 'trait HasShape {}', 'app/Modules/Orders/Support/Mode.php' => "enum Mode: string { case FAST = 'fast'; }"], []],
    'an anonymous class' => [['app/Modules/Orders/Support/Maker.php' => 'final class Maker { public function make(): object { return new class {}; } }'], []],
    'a class named like the keyword' => [['app/Modules/Orders/Support/AbstractFactory.php' => 'final class AbstractFactory {}'], []],
    'a concrete parent on the open list, with a reason in the list' => [['app/Modules/Orders/Support/Cart.php' => 'class Cart {}', 'app/Modules/Orders/Support/GiftCart.php' => 'final class GiftCart extends Cart {}'], ['App\Modules\Orders\Support\Cart']],
    'a child of a vendor class that is not ours' => [['app/Modules/Orders/Support/Failure.php' => 'final class Failure extends \RuntimeException {}'], []],
    'no code' => [[], []],
]);

it('accepts final classes, abstract bases, other kinds of type and a decision row of the open list', function (array $files, array $open) use ($run): void {
    [$violations] = $run($files, $open);

    expect($violations)->toBe([]);
})->with('classes that are closed');

it('keeps every concrete class in app final, with no open class on the list', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();

    expect($inspector->phpFiles($root . '/app'))->not->toBe([])
        ->and(new ClosedClassGuard($inspector, $root, 'app')->violations())->toBe([]);
});
