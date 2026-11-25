<?php

declare(strict_types=1);

use Tests\Support\Unit\OneModulePerDomainArchitectureHelpers;

it('has one module per domain, never two that differ only by a plural or a prefix', function (): void {
    expect(OneModulePerDomainArchitectureHelpers::modules())->toContain('Tenants', 'Users', 'Roles')
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(OneModulePerDomainArchitectureHelpers::modules()))->toBe([])
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(['Products', 'ProductPrices', 'ProductImages']))->toBe(['ProductPrices overlaps Products', 'ProductImages overlaps Products'])
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(['Order', 'Orders']))->toBe(['Orders overlaps Order'])
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(['Invoice', 'Invoices']))->toBe(['Invoices overlaps Invoice'])
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(['Stock', 'StockLevels']))->toBe(['StockLevels overlaps Stock'])
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(['Users', 'UserRoles']))->toBe(['UserRoles overlaps Users'])
        ->and(OneModulePerDomainArchitectureHelpers::overlaps(['Rate', 'Ratings', 'Returns', 'TaxReturns', 'Stock', 'Stockists']))->toBe([]);
});
