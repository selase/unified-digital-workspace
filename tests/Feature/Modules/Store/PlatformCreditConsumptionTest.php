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
        $email = (new Symfony\Component\Mime\Email)
            ->from('sender@example.com')->to($user->email)->subject('Delivered')->text('Delivered');
        $response = new Illuminate\Mail\SentMessage(new Symfony\Component\Mailer\SentMessage(
            $email, Symfony\Component\Mailer\Envelope::create($email)
        ));
        $sentEvent = new NotificationSent($user, $notification, 'mail', $response);
        $listener->handleSent($sentEvent);

        $wallet->refresh();
        expect($wallet->balance)->toBe(0)
            ->and($wallet->total_consumed)->toBe(1);

        // Next pre-flight check with 0 credits -> blocked (returns false)
        expect($listener->handleSending($sendingEvent))->toBeFalse();
    });

    test('system emails such as password reset and email verification are exempt from credit quotas even at zero balance', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $wallet = TenantCreditWallet::create([
            'tenant_id' => $tenant->id,
            'credit_type' => 'email',
            'balance' => 0,
            'total_purchased' => 0,
            'total_consumed' => 0,
        ]);

        $listener = app(NotificationDeliveryCreditListener::class);

        $resetPasswordNotification = new Illuminate\Auth\Notifications\ResetPassword('token-123');
        $verifyEmailNotification = new Illuminate\Auth\Notifications\VerifyEmail();

        // Password reset is allowed despite 0 balance
        $sendingReset = new NotificationSending($user, $resetPasswordNotification, 'mail');
        expect($listener->handleSending($sendingReset))->toBeTrue();

        // Successful sent does not decrement wallet or consume credit
        $sentReset = new NotificationSent($user, $resetPasswordNotification, 'mail', null);
        $listener->handleSent($sentReset);
        $wallet->refresh();
        expect($wallet->balance)->toBe(0)
            ->and($wallet->total_consumed)->toBe(0);

        // Verification email is allowed despite 0 balance
        $sendingVerify = new NotificationSending($user, $verifyEmailNotification, 'mail');
        expect($listener->handleSending($sendingVerify))->toBeTrue();

        $sentVerify = new NotificationSent($user, $verifyEmailNotification, 'mail', null);
        $listener->handleSent($sentVerify);
        $wallet->refresh();
        expect($wallet->balance)->toBe(0)
            ->and($wallet->total_consumed)->toBe(0);
    });
});
