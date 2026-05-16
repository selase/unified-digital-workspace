<?php

declare(strict_types=1);

use App\Services\Auth\AbilityAliasService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3.4 — cutover migration.
 *
 * Phase 3.2 (mirror migration) added module.action.scope twin rows alongside
 * every legacy "verb noun" permission and copied their role + model grants
 * across. Phase 3.3 swept every authorize() / can() call site over to the
 * new names. Once that ships and is verified live, the legacy rows are no
 * longer reachable from application code — only the alias bridge in
 * AuthServiceProvider keeps them resolvable, and that bridge fails open
 * (returns null) when the legacy row is absent.
 *
 * This migration drops the legacy rows. role_has_permissions and
 * model_has_permissions have ON DELETE CASCADE FKs to permissions.id so
 * the dependent rows would be removed automatically, but we delete them
 * explicitly first for clarity, idempotency, and to keep the migration
 * portable to any future schema where the cascade is removed.
 *
 * Down is reversible: every legacy row is recreated from the alias map,
 * borrowing the category from its new-style twin, and its role + model
 * grants are mirrored back from the twin. So a rollback returns the DB
 * to the same dual-name state the mirror migration produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyNames = array_values(AbilityAliasService::all());

            $legacyIds = DB::table('permissions')
                ->whereIn('name', $legacyNames)
                ->pluck('id');

            if ($legacyIds->isEmpty()) {
                return;
            }

            DB::table('role_has_permissions')
                ->whereIn('permission_id', $legacyIds)
                ->delete();

            DB::table('model_has_permissions')
                ->whereIn('permission_id', $legacyIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $legacyIds)
                ->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (AbilityAliasService::all() as $newName => $legacyName) {
                $new = DB::table('permissions')->where('name', $newName)->first();

                if ($new === null) {
                    continue;
                }

                $legacyId = DB::table('permissions')->where('name', $legacyName)->value('id');

                if ($legacyId === null) {
                    $legacyId = DB::table('permissions')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'name' => $legacyName,
                        'guard_name' => $new->guard_name,
                        'category' => $new->category,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $roleAssignments = DB::table('role_has_permissions')
                    ->where('permission_id', $new->id)
                    ->get();

                foreach ($roleAssignments as $assignment) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $legacyId,
                        'role_id' => $assignment->role_id,
                    ]);
                }

                $modelAssignments = DB::table('model_has_permissions')
                    ->where('permission_id', $new->id)
                    ->get();

                foreach ($modelAssignments as $assignment) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $legacyId,
                        'model_type' => $assignment->model_type,
                        'model_id' => $assignment->model_id,
                        'tenant_id' => $assignment->tenant_id ?? null,
                    ]);
                }
            }
        });
    }
};
