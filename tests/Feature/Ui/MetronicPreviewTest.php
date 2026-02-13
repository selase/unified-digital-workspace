<?php

declare(strict_types=1);

use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders the metronic preview for authenticated users', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/metronic-preview')
        ->assertSuccessful()
        ->assertSee('Metronic UI Preview')
        ->assertSee('assets/metronic/css/styles.css');
});
