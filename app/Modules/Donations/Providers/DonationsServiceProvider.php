<?php

declare(strict_types=1);

namespace App\Modules\Donations\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\Donations\Console\ChargePledges;
use App\Modules\Donations\Listeners\RecordOnlineGift;
use App\Modules\Finance\Events\GatewayChargeSucceeded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class DonationsServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'donations';
    }

    public function getModuleName(): string
    {
        return 'Donations';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::middleware('web')->group(__DIR__.'/../Routes/public.php');
        Event::listen(GatewayChargeSucceeded::class, RecordOnlineGift::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([ChargePledges::class]);
    }
}
