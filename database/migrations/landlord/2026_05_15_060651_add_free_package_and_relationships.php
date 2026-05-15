<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a "Free" package as the default landing tier for any tenant that
 * hasn't been explicitly assigned to a paid package. Idempotent — re-runs
 * are no-ops, and the migration deliberately does NOT touch existing
 * tenant.package_id values (a separate command handles backfilling).
 *
 * Free starts with no entitlements; module-functional features are added
 * to tenants directly via ModuleManager when modules are enabled. SaaS
 * extras (analytics, custom-domains, sso, commerce) require an upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('packages')->where('slug', 'free')->first();

        if ($existing !== null) {
            return;
        }

        DB::table('packages')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0.00,
            'interval' => 'month',
            'billing_model' => 'flat_rate',
            'description' => 'Default landing tier — no paid SaaS features. Use this for evaluation, nonprofit, or sandboxed tenants.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only remove if no tenant is on this package, so we never orphan data.
        $assigned = DB::table('tenants')
            ->whereIn('package_id', DB::table('packages')->where('slug', 'free')->pluck('id'))
            ->exists();

        if ($assigned) {
            return;
        }

        DB::table('packages')->where('slug', 'free')->delete();
    }
};
