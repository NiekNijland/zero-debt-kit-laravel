<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Modules\Shipments\Contracts\ShippingMethod;
use App\Modules\Shipments\Support\ShippingMethods;

final readonly class RegistryGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return self::mismatches($this->implementers(), ShippingMethods::ALL, ShippingMethod::class);
    }

    /**
     * @param list<string> $implementers
     * @param list<string> $registered
     * @return list<string>
     */
    public static function mismatches(array $implementers, array $registered, string $contract): array
    {
        $violations = [];

        foreach (array_diff($implementers, $registered) as $class) {
            $violations[] = $class . ' implements ShippingMethod but is not in ShippingMethods::ALL, so the checkout never offers it: add it to that list';
        }

        foreach ($registered as $class) {
            if (! in_array($class, $implementers, true) && ! is_subclass_of($class, $contract)) {
                $violations[] = $class . ' is in ShippingMethods::ALL but does not implement ShippingMethod: implement it in a service named XShippingService, or take it off the list';
            }

            if (! str_ends_with($class, 'ShippingService')) {
                $violations[] = $class . ' is in ShippingMethods::ALL but is not named XShippingService: rename it';
            }
        }

        foreach (array_diff_key($registered, array_unique($registered)) as $class) {
            $violations[] = $class . ' is in ShippingMethods::ALL twice: list it once';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function implementers(): array
    {
        $implementers = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inspector->declaredTypes($file) as $class => $related) {
                if (in_array(ShippingMethod::class, $related, true) && class_exists($class) && is_subclass_of($class, ShippingMethod::class)) {
                    $implementers[] = $class;
                }
            }
        }

        return $implementers;
    }
}
