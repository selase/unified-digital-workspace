<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);
    $this->actingAs($this->user);
});

it('lists only the active tenants non-draft invoices with pagination', function (): void {
    grantOrganisationAdminAccess($this->user);
    $otherTenant = Tenant::factory()->create();

    foreach ([$this->tenant->id => 'OWN', $otherTenant->id => 'OTHER'] as $tenantId => $prefix) {
        foreach (['issued', 'paid', 'overdue', 'void', 'draft'] as $status) {
            Invoice::query()->create([
                'tenant_id' => $tenantId,
                'number' => $prefix.'-'.$status,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
                'due_at' => null,
                'status' => $status,
                'currency' => 'GHS',
                'subtotal' => 123.45,
                'tax_total' => 0,
                'total' => 123.45,
            ]);
        }
    }

    $response = $this->get(route('billing.invoices.index'))
        ->assertOk()
        ->assertViewIs('billing.invoices.index')
        ->assertSee('GHS 123.45')
        ->assertDontSee('OWN-draft')
        ->assertDontSee('OTHER-');

    $response->assertViewHas('invoices', fn ($invoices): bool => $invoices->total() === 4 && $invoices->perPage() === 15);
    foreach (Invoice::query()->where('tenant_id', $this->tenant->id)->where('status', '!=', 'draft')->get() as $invoice) {
        $response->assertSee($invoice->number)
            ->assertSee(route('billing.invoices.show', $invoice->id), false)
            ->assertSee(route('billing.invoices.download', $invoice->id), false);
    }
});

it('shows a useful empty invoice state', function (): void {
    grantOrganisationAdminAccess($this->user);

    $this->get(route('billing.invoices.index'))->assertOk()->assertSee('No invoices issued yet.');
});

it('forbids the invoice list without organisation billing permission', function (): void {
    $this->get(route('billing.invoices.index'))->assertForbidden();
});
