<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\Finance\Events\GatewayChargeSucceeded;
use App\Modules\Memberships\Console\SyncMemberships;
use App\Modules\Memberships\Listeners\ApplyMembershipPayments;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class MembershipsServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'memberships';
    }

    public function getModuleName(): string
    {
        return 'Memberships';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::middleware('web')->group(__DIR__.'/../Routes/public.php');
        Event::listen(GatewayChargeSucceeded::class, ApplyMembershipPayments::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([SyncMemberships::class]);
    }
}
