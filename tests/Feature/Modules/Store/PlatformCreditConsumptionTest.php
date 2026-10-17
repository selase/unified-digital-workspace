<?php

declare(strict_types=1);

use App\Listeners\NotificationDeliveryCreditListener;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\PlatformCreditService;
use App\Notifications\InstitutionalWorkflowAlert;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

describe('P1-3: Platform Credit Consumption & Quota Listeners Test', function () {
    test('PlatformCreditService atomic consumeCredit decrements balance and tracks consumption', function () {
        $tenant = Tenant::factory()->create();

        $wallet = TenantCreditWallet::create([
            'tenant_id' => $tenant->id,
            'credit_type' => 'sms',
            'balance' => 5,
            'total_purchased' => 5,
            'total_consumed' => 0,
        ]);

        $service = app(PlatformCreditService::class);

        expect($service->hasBalance((string) $tenant->id, 'sms', 5))->toBeTrue()
            ->and($service->hasBalance((string) $tenant->id, 'sms', 6))->toBeFalse()
            ->and($service->getBalance((string) $tenant->id, 'sms'))->toBe(5);

        // Consume 2 credits
        $success = $service->consumeCredit((string) $tenant->id, 'sms', 2);

        expect($success)->toBeTrue()
            ->and($service->getBalance((string) $tenant->id, 'sms'))->toBe(3);

        $wallet->refresh();
        expect($wallet->balance)->toBe(3)
            ->and($wallet->total_consumed)->toBe(2);

        // Rejection when requesting more than balance
        $overdraftSuccess = $service->consumeCredit((string) $tenant->id, 'sms', 10);
        expect($overdraftSuccess)->toBeFalse();

        // Balance unchanged after failed attempt
        expect($service->getBalance((string) $tenant->id, 'sms'))->toBe(3);
    });

    test('NotificationDeliveryCreditListener blocks sending on zero balance and bills strictly on sent', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $wallet = TenantCreditWallet::create([
            'tenant_id' => $tenant->id,
            'credit_type' => 'email',
            'balance' => 1,
            'total_purchased' => 1,
            'total_consumed' => 0,
        ]);

        $listener = app(NotificationDeliveryCreditListener::class);
        $notification = new InstitutionalWorkflowAlert('Test Alert', 'Message content', 1);

        // Pre-flight check with 1 credit available -> allowed
        $sendingEvent = new NotificationSending($user, $notification, 'mail');
        expect($listener->handleSending($sendingEvent))->toBeTrue();

        // Successful delivery -> bills 1 credit
        $sentEvent = new NotificationSent($user, $notification, 'mail', null);
        $listener->handleSent($sentEvent);

        $wallet->refresh();
        expect($wallet->balance)->toBe(0)
            ->and($wallet->total_consumed)->toBe(1);

        // Next pre-flight check with 0 credits -> blocked (returns false)
        expect($listener->handleSending($sendingEvent))->toBeFalse();
    });
});
