<?php

declare(strict_types=1);

use App\Models\Landlord\Event as EventModel;
use App\Models\Tenant\User;
use App\Modules\Events\Services\AuditLogService;
use App\Modules\Users\Events\UserSuspendedEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Tests\Support\Feature\Modules\Events\AuditEventHelpers;

it('puts nothing in the audit trail before the transaction around it commits', function (): void {
    $this->createTenantAndMakeCurrent();

    $immediate = array_values(array_filter(
        AuditEventHelpers::everyConcreteEventClass(),
        static fn (string $class): bool => AuditEventHelpers::isAuditEvent($class) && ! is_subclass_of($class, ShouldDispatchAfterCommit::class),
    ));

    expect($immediate)->toBe([]);
});

it('writes an audit event to the log once the transaction commits', function (): void {
    $this->createTenantAndMakeCurrent();

    $user = User::factory()->create();
    $this->actingAs($user);

    DB::transaction(static function () use ($user): void {
        UserSuspendedEvent::dispatch($user);

        expect(EventModel::query()->where('name', UserSuspendedEvent::class)->exists())->toBeFalse();
    });
    resolve(AuditLogService::class)->flush();

    expect(EventModel::query()->where('name', UserSuspendedEvent::class)->exists())->toBeTrue();
});

it('leaves no audit line behind when the transaction rolls back', function (): void {
    $this->createTenantAndMakeCurrent();

    $user = User::factory()->create();
    $this->actingAs($user);

    DB::beginTransaction();
    UserSuspendedEvent::dispatch($user);
    DB::rollBack();
    resolve(AuditLogService::class)->flush();

    expect(EventModel::query()->where('name', UserSuspendedEvent::class)->exists())->toBeFalse();
});
