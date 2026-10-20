<?php

declare(strict_types=1);

use App\Enum\UsageMetric;
use App\Listeners\Tenancy\MeterTenantMail;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Models\UsageRollup;
use App\Models\User;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Notifications\InstitutionalWorkflowAlert;
use App\Services\Tenancy\PricingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Message;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    $this->tenant = setActiveTenantForTest();
    config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
});

function sendMeteredTestMail(?string $tenantId = null, bool $multiple = false): void
{
    Mail::mailer('array')->raw('Usage test', function (Message $message) use ($tenantId, $multiple): void {
        $message->from('sender@example.com')->to('one@example.com')->subject('Usage test');
        if ($tenantId !== null) {
            $message->getSymfonyMessage()->getHeaders()->addTextHeader(MeterTenantMail::TENANT_HEADER, $tenantId);
        }
        if ($multiple) {
            $message->cc('two@example.com')->bcc('three@example.com');
        }
    });
}

test('successful explicit tenant mail counts each envelope recipient without using ambient context', function (): void {
    $otherTenant = Tenant::factory()->create();
    app(TenantContext::class)->setTenant($otherTenant);

    sendMeteredTestMail($this->tenant->id, true);

    $event = UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->sole();
    expect($event->tenant_id)->toBe($this->tenant->id);
    expect((float) $event->quantity)->toBe(3.0);
    expect($event->meta['billing_mode'])->toBe('metered');
    $rollup = UsageRollup::query()->where('metric', UsageMetric::EMAIL_COUNT)->sole();
    expect((float) $rollup->value)->toBe(3.0);
    expect($rollup->dimensions['billing_mode'])->toBe('metered');
});

test('landlord mail and invalid tenant headers never use a leftover tenant context', function (): void {
    sendMeteredTestMail();
    sendMeteredTestMail('not-a-tenant');
    sendMeteredTestMail((string) Illuminate\Support\Str::uuid());

    expect(UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->count())->toBe(0);
});

test('failed mail transport never records email usage', function (): void {
    Event::listen(MessageSending::class, function (): never {
        throw new TransportException('Delivery failed');
    });

    expect(fn () => sendMeteredTestMail($this->tenant->id))->toThrow(TransportException::class);
    expect(UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->count())->toBe(0);
});

test('queue tenant attribution is captured per mail job and reset between jobs', function (): void {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([
        'tenant_id' => $this->tenant->id,
        'data' => ['commandName' => SendQueuedMailable::class],
    ]);
    $listener = app(MeterTenantMail::class);
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    sendMeteredTestMail();
    $listener->handleJobFinished(new JobProcessed('sync', $job));
    sendMeteredTestMail();
    $jobWithoutTenant = Mockery::mock(Job::class);
    $jobWithoutTenant->shouldReceive('payload')->andReturn(['data' => ['commandName' => SendQueuedMailable::class]]);
    $listener->handleJobProcessing(new JobProcessing('sync', $jobWithoutTenant));
    sendMeteredTestMail();

    expect((float) UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->sum('quantity'))->toBe(1.0);
});

test('conflicting header and queued tenant metadata is ignored', function (): void {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([
        'tenant_id' => $this->tenant->id, 'data' => ['commandName' => SendQueuedMailable::class],
    ]);
    app(MeterTenantMail::class)->handleJobProcessing(new JobProcessing('sync', $job));
    sendMeteredTestMail(Tenant::factory()->create()->id);

    expect(UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->count())->toBe(0);
});

test('successful notifications record the actual prepaid debit once and blocked sends record nothing', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $wallet = TenantCreditWallet::query()->create([
        'tenant_id' => $this->tenant->id, 'credit_type' => 'email',
        'balance' => 1, 'total_purchased' => 1, 'total_consumed' => 0,
    ]);
    $notification = new InstitutionalWorkflowAlert('Test', 'Test message', 1);
    Notification::sendNow($user, $notification, ['mail']);
    Notification::sendNow($user, new InstitutionalWorkflowAlert('Blocked', 'Blocked', 1), ['mail']);

    expect($wallet->fresh()->balance)->toBe(0);
    $event = UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->sole();
    expect((float) $event->quantity)->toBe(1.0);
    expect($event->meta['billing_mode'])->toBe('prepaid');
    expect(UsageRollup::query()->where('metric', UsageMetric::EMAIL_COUNT)->sole()->dimensions['billing_mode'])->toBe('prepaid');
});

test('notifications without a wallet are metered and failed notifications leave no billable usage', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    Notification::sendNow($user, new InstitutionalWorkflowAlert('Test', 'Test message', 1), ['mail']);
    expect(UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->sole()->meta['billing_mode'])->toBe('metered');
    Event::listen(MessageSending::class, function (): never {
        throw new TransportException('Delivery failed');
    });
    expect(fn () => Notification::sendNow($user, new InstitutionalWorkflowAlert('Failure', 'Test', 1), ['mail']))
        ->toThrow(TransportException::class);
    expect(UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->count())->toBe(1);
});

test('invoice quantities exclude prepaid email rollups and retain metered and legacy quantities', function (): void {
    foreach (['prepaid' => 7, 'metered' => 3, 'legacy' => 2] as $mode => $value) {
        UsageRollup::query()->create([
            'tenant_id' => $this->tenant->id, 'period' => 'day', 'period_start' => now()->startOfDay(),
            'metric' => UsageMetric::EMAIL_COUNT, 'value' => $value,
            'dimensions' => $mode === 'legacy' ? [] : ['billing_mode' => $mode],
        ]);
    }
    $quantities = app(PricingService::class)->billableQuantities($this->tenant, now()->startOfMonth(), now()->endOfMonth());
    expect($quantities[UsageMetric::EMAIL_COUNT->value])->toBe(5.0);
});

test('cancelled notification mail does not debit prepaid credits or record usage', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $wallet = TenantCreditWallet::query()->create([
        'tenant_id' => $this->tenant->id, 'credit_type' => 'email',
        'balance' => 1, 'total_purchased' => 1, 'total_consumed' => 0,
    ]);
    Event::listen(MessageSending::class, fn (): bool => false);
    Notification::sendNow($user, new InstitutionalWorkflowAlert('Cancelled', 'Test', 1), ['mail']);

    expect($wallet->fresh()->balance)->toBe(1);
    expect(UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->count())->toBe(0);
});

test('only the confirmed wallet debit is prepaid on a multiple recipient notification', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $wallet = TenantCreditWallet::query()->create([
        'tenant_id' => $this->tenant->id, 'credit_type' => 'email',
        'balance' => 1, 'total_purchased' => 1, 'total_consumed' => 0,
    ]);
    $notification = new class extends Illuminate\Notifications\Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): Illuminate\Notifications\Messages\MailMessage
        {
            return (new Illuminate\Notifications\Messages\MailMessage)
                ->subject('Recipients')->line('Recipients')->cc('copy@example.com')->bcc('blind@example.com');
        }
    };
    Notification::sendNow($user, $notification, ['mail']);

    expect($wallet->fresh()->total_consumed)->toBe(1);
    $usage = UsageEvent::query()->where('type', UsageMetric::EMAIL_COUNT)->get();
    expect((float) $usage->filter(fn (UsageEvent $event): bool => $event->meta['billing_mode'] === 'prepaid')->sum('quantity'))->toBe(1.0);
    expect((float) $usage->filter(fn (UsageEvent $event): bool => $event->meta['billing_mode'] === 'metered')->sum('quantity'))->toBe(2.0);
});
