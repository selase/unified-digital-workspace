<?php

declare(strict_types=1);

use App\Models\Tenant;

/**
 * Tenant.parent_id forms a single-parent tree. ancestors() walks up the
 * chain (excluding self), descendants() walks down transitively. Both
 * cap at Tenant::HIERARCHY_DEPTH_CAP to keep recursion bounded and the
 * UI sane.
 */
test('root tenant has no ancestors and finds all descendants', function (): void {
    $root = Tenant::factory()->create(['parent_id' => null]);
    $childA = Tenant::factory()->create(['parent_id' => $root->id]);
    $childB = Tenant::factory()->create(['parent_id' => $root->id]);
    $grandchild = Tenant::factory()->create(['parent_id' => $childA->id]);

    expect($root->ancestors())->toBe([]);
    expect($root->descendants()->pluck('id')->all())
        ->toContain($childA->id, $childB->id, $grandchild->id);
});

test('grandchild walks up to root', function (): void {
    $root = Tenant::factory()->create(['parent_id' => null]);
    $child = Tenant::factory()->create(['parent_id' => $root->id]);
    $grandchild = Tenant::factory()->create(['parent_id' => $child->id]);

    $ancestors = collect($grandchild->fresh()->ancestors())->pluck('id')->all();

    expect($ancestors)->toBe([$child->id, $root->id]);
});

test('depth cap prevents runaway recursion via descendants', function (): void {
    $a = Tenant::factory()->create(['parent_id' => null]);
    $b = Tenant::factory()->create(['parent_id' => $a->id]);
    $c = Tenant::factory()->create(['parent_id' => $b->id]);
    // Depth 3 — should NOT appear in descendants of $a
    $d = Tenant::factory()->create(['parent_id' => $c->id]);

    $descendants = $a->descendants()->pluck('id')->all();

    expect($descendants)->toContain($b->id, $c->id);
    expect($descendants)->not->toContain($d->id);
});
