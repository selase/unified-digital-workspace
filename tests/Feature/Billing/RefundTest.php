<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('supplier admins can initiate a refund for a successful transaction', function () {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    grantOrganisationAdminAccess($user);

    Illuminate\Support\Facades\Gate::before(fn ($actor, $ability) => $ability === 'admin.tenants.update' && $actor->is($user) ? true : null);

    $transaction = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'amount' => 5000,
        'status' => 'success',
        'provider_transaction_id' => 'ch_test_123',
    ]);

    // Mock Gateway
    $gateway = mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')
        ->with('ch_test_123')
        ->once()
        ->andReturn('re_test_refund_id');

    // Bind mock
    $this->app->instance(PaymentGateway::class, $gateway);

    // Act
    $response = actingAs($user)
        ->post(route('billing.refund', ['transaction' => $transaction, 'subdomain' => $tenant->slug]));

    // Assert
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Refund initiated successfully.');

    expect($transaction->refresh()->status)->toBe('refunded');
});

test('refunds cannot be initiated for failed transactions', function () {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    grantOrganisationAdminAccess($user);

    $transaction = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'failed',
    ]);

    // Act
    $response = actingAs($user)
        ->post(route('billing.refund', ['transaction' => $transaction, 'subdomain' => $tenant->slug]));

    // Assert
    $response->assertStatus(403); // Or 422
});

test('refund button is visible for success transactions', function () {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    grantOrganisationAdminAccess($user);
    Illuminate\Support\Facades\Gate::before(fn ($actor, $ability) => $ability === 'admin.tenants.update' && $actor->is($user) ? true : null);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'success',
        'provider_transaction_id' => 'ch_visible',
    ]);

    $response = actingAs($user)->get(route('billing.index', ['subdomain' => $tenant->slug]));

    $response->assertSee('Refund');
    $response->assertSee(route('billing.refund', ['transaction' => Transaction::first(), 'subdomain' => $tenant->slug]));
});

test('organisation administrators cannot initiate supplier refunds even for their own transactions', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    grantOrganisationAdminAccess($user);
    $transaction = Transaction::factory()->create(['tenant_id' => $tenant->id, 'status' => 'success']);
    $this->actingAs($user)->post(route('billing.refund', $transaction))->assertForbidden();
    expect($transaction->fresh()->status)->toBe('success');
    $this->get(route('billing.index'))->assertOk()->assertDontSee('Issue refund?');
});
