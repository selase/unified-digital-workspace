<?php

declare(strict_types=1);

use App\Listeners\Tenancy\MeterTenantMail;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Mail\Message;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'database.connections' => [], 'cache.default' => 'array',
        'mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array'],
    ]);
    $tenant = new Tenant;
    $tenant->forceFill(['id' => (string) Str::uuid()]);
    app(TenantContext::class)->setTenant($tenant);
});

it('delivers landlord mail without accessing a database despite stale tenant context', function (): void {
    $sent = Mail::mailer('array')->raw('Landlord', function (Message $message): void {
        $message->from('system@example.com')->to('recipient@example.com')->subject('Landlord');
    });

    expect($sent)->toBeInstanceOf(Illuminate\Mail\SentMessage::class);
});

it('ignores malformed or conflicting attribution without querying a tenant database', function (string $mode): void {
    if ($mode === 'conflict') {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([
            'tenant_id' => (string) Str::uuid(), 'data' => ['commandName' => SendQueuedMailable::class],
        ]);
        app(MeterTenantMail::class)->handleJobProcessing(new JobProcessing('sync', $job));
    }
    $sent = Mail::mailer('array')->raw('Test', function (Message $message) use ($mode): void {
        $message->from('sender@example.com')->to('recipient@example.com')->subject('Test');
        $message->getSymfonyMessage()->getHeaders()->addTextHeader(
            MeterTenantMail::TENANT_HEADER, $mode === 'conflict' ? (string) Str::uuid() : 'invalid-tenant'
        );
    });

    expect($sent)->toBeInstanceOf(Illuminate\Mail\SentMessage::class);
})->with(['malformed', 'conflict']);

it('clears captured mail attribution after a queue exception before the next landlord send', function (): void {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([
        'tenant_id' => (string) Str::uuid(), 'data' => ['commandName' => SendQueuedMailable::class],
    ]);
    $listener = app(MeterTenantMail::class);
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobFinished(new JobExceptionOccurred('sync', $job, new RuntimeException('Failed job')));
    $sent = Mail::mailer('array')->raw('Landlord', function (Message $message): void {
        $message->from('system@example.com')->to('recipient@example.com')->subject('Landlord');
    });

    expect($sent)->toBeInstanceOf(Illuminate\Mail\SentMessage::class);
});
