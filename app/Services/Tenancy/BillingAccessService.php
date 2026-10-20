<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantBillingAccess;
use App\Models\TenantBillingAgreement;
use App\Models\TenantBillingMonthlyUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class BillingAccessService
{
    public const LEVELS = ['website_editor', 'self_service', 'operational'];

    public function __construct(private readonly BillingAgreementService $agreements) {}

    public function assign(Tenant $tenant, User $user, string $level): void
    {
        if (! in_array($level, self::LEVELS, true)) {
            throw ValidationException::withMessages(['level' => 'Select a valid billing access level.']);
        }

        $tenant->getConnection()->transaction(function () use ($tenant, $user, $level): void {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (! $this->isMember($tenant, $user) || $user->isGlobalSuperAdmin()) {
                throw ValidationException::withMessages(['user_id' => 'Select an organisation member, excluding platform support.']);
            }

            $agreement = $this->agreements->activeFor($tenant);
            $futureCapacity = TenantBillingAgreement::query()->where('tenant_id', $tenant->id)
                ->where('effective_from', '>', today()->toDateString())->min('editor_seats');
            $capacities = array_filter([$agreement?->editor_seats, $futureCapacity], fn ($value): bool => $value !== null);
            if ($capacities === []) {
                throw ValidationException::withMessages(['level' => 'Schedule a billing agreement before assigning access.']);
            }

            if ($level === 'website_editor') {
                $editors = TenantBillingAccess::query()->where('tenant_id', $tenant->id)
                    ->whereHas('user.tenants', fn ($query) => $query->where('tenants.id', $tenant->id))
                    ->where('user_id', '!=', $user->id)->where('level', 'website_editor')->count();
                if ($editors >= (int) min($capacities)) {
                    throw ValidationException::withMessages(['level' => 'All website editor seats are assigned.']);
                }
            }

            TenantBillingAccess::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id], ['level' => $level],
            );
        });
    }

    public function revoke(Tenant $tenant, User $user): void
    {
        $tenant->getConnection()->transaction(function () use ($tenant, $user): void {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (! $this->isMember($tenant, $user)) {
                throw ValidationException::withMessages(['user_id' => 'Select an organisation member.']);
            }

            TenantBillingAccess::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->delete();
        });
    }

    /** @return list<array{label: string, route: string, module: string, params?: array<string, string>}> */
    public function workspaceLinks(Tenant $tenant, User $user): array
    {
        $level = $this->level($tenant, $user);
        $links = [];
        $candidates = $level === 'website_editor'
            ? [
                ['Website', 'cms-core.index', 'cms-core', 'cms.posts.view'],
                ['Media', 'cms-core.media.index', 'cms-core', 'cms.media.view'],
                ['Forms', 'forms.index', 'forms', 'forms.view'],
            ]
            : ($level === 'self_service' ? [
                ['My leave', 'hrms-core.leave-requests.index', 'hrms-core', 'hrms.leave.view'],
                ['Published documents', 'document-management.documents.index', 'document-management', 'documents.view'],
                ['Forms', 'forms.index', 'forms', 'forms.view'],
                ['My incident reports', 'incident-management.incidents.index', 'incident-management', 'incidents.create'],
            ] : []);
        foreach ($candidates as [$label, $route, $module, $permission]) {
            if ($user->can($permission) && app(\App\Services\ModuleManager::class)->isEnabledForTenant($module, $tenant)) {
                $links[] = compact('label', 'route', 'module');
            }
        }
        if ($level === 'self_service') {
            $links[] = ['label' => 'My notifications', 'route' => 'notifications.index', 'module' => 'core'];
        }
        $links[] = ['label' => 'My profile', 'route' => 'profile.index', 'module' => 'core', 'params' => ['user' => (string) $user->uuid]];

        return $links;
    }

    public function isSelfService(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();
        $request = request();
        if ($tenant !== null && $request->attributes->get('hybrid_billing_access_allowed') === true
            && $request->attributes->get('hybrid_billing_tenant_id') === $tenant->id
            && $request->attributes->get('hybrid_billing_user_id') === $user->getAuthIdentifier()) {
            return $request->attributes->get('hybrid_billing_access_level') === 'self_service';
        }

        return $tenant !== null && $this->agreements->activeFor($tenant) !== null
            && $this->level($tenant, $user) === 'self_service';
    }

    public function level(Tenant $tenant, User $user): ?string
    {
        if (! $this->isMember($tenant, $user) || $user->isGlobalSuperAdmin()) {
            return null;
        }

        $level = TenantBillingAccess::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->value('level');

        return in_array($level, self::LEVELS, true) ? $level : null;
    }

    public function recordActivity(Tenant $tenant, User $user, ?string $admittedLevel = null, ?CarbonInterface $admittedMonth = null): void
    {
        if ($admittedLevel !== null && ! in_array($admittedLevel, self::LEVELS, true)) {
            throw new InvalidArgumentException('Unknown admitted billing access level.');
        }
        $month = ($admittedMonth ?? now())->copy()->startOfMonth();
        if ($this->agreements->activeFor($tenant, $month) === null) {
            return;
        }

        $tenant->getConnection()->transaction(function () use ($tenant, $user, $admittedLevel, $month): void {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $level = $admittedLevel ?? $this->level($tenant, $user);
            if ($level === null) {
                return;
            }

            $record = TenantBillingMonthlyUser::query()->firstOrCreate([
                'tenant_id' => $tenant->id, 'user_id' => $user->id, 'month' => $month->toDateString(),
            ], ['level' => $level]);

            if (array_search($level, self::LEVELS, true) > array_search($record->level, self::LEVELS, true)) {
                $record->update(['level' => $level]);
            }
        });
    }

    /** @return array{self_service: int, operational: int} */
    public function counts(Tenant $tenant, CarbonInterface $month): array
    {
        $counts = TenantBillingMonthlyUser::query()->where('tenant_id', $tenant->id)
            ->where('month', $month->copy()->startOfMonth()->toDateString())
            ->selectRaw('level, COUNT(*) as aggregate')->groupBy('level')->pluck('aggregate', 'level');

        return ['self_service' => (int) ($counts['self_service'] ?? 0), 'operational' => (int) ($counts['operational'] ?? 0)];
    }

    public function isMember(Tenant $tenant, User $user): bool
    {
        return $tenant->users()->where('users.id', $user->id)->exists();
    }
}
