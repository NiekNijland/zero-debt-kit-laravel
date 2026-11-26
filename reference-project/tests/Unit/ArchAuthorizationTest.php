<?php

declare(strict_types=1);

use App\Http\Requests\Admin\Products\StoreProductRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Models\Landlord\Tenant;
use App\Modules\Roles\Actions\AssignRoles;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;

uses()->group('arch');

arch('assigning roles never authorizes through the gate')
    ->expect(AssignRoles::class)
    ->not->toUse([Gate::class, GateContract::class]);

arch('requests that check data against a tenant take the tenant from the route')
    ->expect([
        StoreUserRequest::class,
        StoreProductRequest::class,
    ])
    ->not->toUse(Tenant::class);
