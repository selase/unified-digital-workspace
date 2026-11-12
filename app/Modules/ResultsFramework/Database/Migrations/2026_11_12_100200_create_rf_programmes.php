<?php

declare(strict_types=1);

use App\Modules\ResultsFramework\Support\RollbackPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rf_programmes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('planning');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->unsignedBigInteger('grant_id')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->foreignId('programme_id')->nullable()->constrained('rf_programmes')->restrictOnDelete();
        });

        DB::table('rf_indicators')->select('id', 'tenant_id', 'programme')->chunkById(500, function ($indicators): void {
            foreach ($indicators as $indicator) {
                $name = mb_trim((string) $indicator->programme) === '' ? 'General' : $indicator->programme;
                $keys = ['tenant_id' => $indicator->tenant_id, 'name' => $name];
                $id = DB::table('rf_programmes')->where($keys)->value('id') ?? DB::table('rf_programmes')->insertGetId($keys + [
                    'uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('rf_indicators')->where('tenant_id', $indicator->tenant_id)->where('id', $indicator->id)->update(['programme_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        (new RollbackPreflight)->assertTenantCanRollback(DB::connection());

        Schema::table('rf_indicators', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('programme_id');
        });
        Schema::dropIfExists('rf_programmes');
    }
};
