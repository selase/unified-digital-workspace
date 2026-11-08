<?php

declare(strict_types=1);

namespace App\Modules\Finance\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\Finance\Console\RunRecurringInvoices;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Services\Gateways\PaystackFinanceGateway;
use Illuminate\Support\Facades\Route;

final class FinanceServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'finance';
    }

    public function getModuleName(): string
    {
        return 'Finance';
    }

    protected function registerBindings(): void
    {
        $this->app->bind(FinancePaymentGateway::class, PaystackFinanceGateway::class);
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Customer-facing pages and the gateway webhook are public: they authenticate by signed URL or HMAC.
        Route::middleware('web')->group(__DIR__.'/../Routes/public.php');
    }

    protected function registerCommands(): void
    {
        $this->commands([RunRecurringInvoices::class]);
    }
}
