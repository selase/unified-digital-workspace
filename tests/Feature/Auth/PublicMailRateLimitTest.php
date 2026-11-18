<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

final class PublicMailRateLimitTest extends TestCase
{
    use \Tests\CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Cache::flush();
        config(['database.connections.landlord' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        \Illuminate\Support\Facades\DB::purge('landlord');
        \Illuminate\Support\Facades\Schema::connection('landlord')->create('tenants', function ($table): void {
            $table->id();
            $table->string('custom_domain')->nullable();
            $table->string('custom_domain_status')->nullable();
            $table->softDeletes();
        });
        Mail::fake();
        Notification::fake();
    }

    public function test_source_limit_survives_recipient_rotation(): void
    {
        foreach (self::paths() as $path) {
            Cache::flush();
            for ($i = 0; $i < 5; $i++) {
                $this->postJson($path, ['email' => "invalid-{$i}"])->assertStatus(422);
            }
            $this->postJson($path, ['email' => 'sixth-invalid'])->assertStatus(429)->assertHeader('Retry-After');
        }
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_recipient_limit_survives_ip_and_case_rotation(): void
    {
        foreach (self::paths() as $path) {
            Cache::flush();
            for ($i = 1; $i <= 3; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$i}"])->postJson($path, ['email' => 'Invalid-Recipient'])->assertStatus(422);
            }
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9'])->postJson($path, ['email' => ' INVALID-RECIPIENT '])->assertStatus(429);
        }
    }

    public function test_application_budget_survives_source_and_recipient_rotation(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$i}"])->postJson('/register', ['email' => "invalid-{$i}"])->assertStatus(422);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.201'])->postJson('/register', ['email' => 'invalid-last'])->assertStatus(429);
    }

    private static function paths(): array
    {
        return ['/register', '/forgot-password'];
    }
}
