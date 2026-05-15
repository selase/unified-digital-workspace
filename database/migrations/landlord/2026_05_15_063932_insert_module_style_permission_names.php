<?php

declare(strict_types=1);

use App\Services\Auth\AbilityAliasService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3.2 of the legacy -> module.action.scope rename.
 *
 * For every entry in AbilityAliasService::NEW_TO_LEGACY:
 *   1. Look up the legacy permission row by name.
 *   2. If it exists and the new-style row doesn't yet, INSERT a fresh
 *      row with the new name (same category, fresh UUID).
 *   3. Mirror every role_has_permissions association onto the new row.
 *   4. Mirror every model_has_permissions association onto the new row.
 *
 * Idempotent — re-runs detect existing new-style rows and skip the
 * insert; the association copies use INSERT ... ON CONFLICT DO NOTHING
 * semantics via DB::insertOrIgnore so duplicates are harmless.
 *
 * Down migration removes the new rows and their associations only —
 * legacy rows are untouched so a rollback is safe even after
 * application code has started using the new names.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (AbilityAliasService::all() as $newName => $legacyName) {
            $legacy = DB::table('permissions')->where('name', $legacyName)->first();

            if ($legacy === null) {
                continue;
            }

            $new = DB::table('permissions')->where('name', $newName)->first();

            if ($new === null) {
                $newId = DB::table('permissions')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $newName,
                    'guard_name' => $legacy->guard_name,
                    'category' => $legacy->category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $newId = $new->id;
            }

            // Mirror role associations.
            $roleAssignments = DB::table('role_has_permissions')
                ->where('permission_id', $legacy->id)
                ->get();

            foreach ($roleAssignments as $assignment) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $newId,
                    'role_id' => $assignment->role_id,
                ]);
            }

            // Mirror direct user/model associations (team-scoped).
            $modelAssignments = DB::table('model_has_permissions')
                ->where('permission_id', $legacy->id)
                ->get();

            foreach ($modelAssignments as $assignment) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $newId,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                    'tenant_id' => $assignment->tenant_id ?? null,
                ]);
            }
        }
    }

    public function down(): void
    {
        $newNames = array_keys(AbilityAliasService::all());

        $newIds = DB::table('permissions')->whereIn('name', $newNames)->pluck('id');

        if ($newIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $newIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $newIds)->delete();
        DB::table('permissions')->whereIn('id', $newIds)->delete();
    }
};
