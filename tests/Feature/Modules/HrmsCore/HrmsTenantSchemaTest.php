<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Database\Seeders\GradeSeeder;
use App\Modules\HrmsCore\Database\Seeders\HrmsCoreSeeder;
use App\Modules\HrmsCore\Database\Seeders\LeaveCategorySeeder;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsAppraisalWebController;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsSalaryWebController;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalPeriod;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalTemplate;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Salary\Allowance;
use App\Modules\HrmsCore\Models\Salary\AllowanceType;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Modules\HrmsCore\Models\Salary\SalaryStep;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @param  class-string<Illuminate\Database\Eloquent\Model>  $modelClass
 */
function createHrmsTenantSlugRecord(string $modelClass, string $slug): Illuminate\Database\Eloquent\Model
{
    $attributes = ['name' => 'Annual Leave', 'slug' => $slug];

    if ($modelClass === AppraisalPeriod::class) {
        $attributes += ['cycle' => 'annual', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'];
    }

    if ($modelClass === LeaveCategory::class) {
        $attributes['default_days'] = 21;
    }

    if ($modelClass === Allowance::class) {
        $attributes['allowance_type_id'] = AllowanceType::create(['name' => 'Housing', 'slug' => (string) Illuminate\Support\Str::uuid()])->id;
    }

    return $modelClass::create($attributes);
}

it('keeps HRMS slugs unique within each shared tenant', function (string $modelClass): void {
    $first = setActiveTenantForTest();
    $one = createHrmsTenantSlugRecord($modelClass, 'annual-leave');
    $second = setActiveTenantForTest();
    $two = createHrmsTenantSlugRecord($modelClass, 'annual-leave');

    expect($one->tenant_id)->toBe($first->id)
        ->and($two->tenant_id)->toBe($second->id)
        ->and($modelClass::query()->where('slug', 'annual-leave')->count())->toBe(1);

    expect(fn () => DB::connection('tenant')->transaction(fn () => createHrmsTenantSlugRecord($modelClass, 'annual-leave')))
        ->toThrow(QueryException::class);
})->with([
    AllowanceType::class, Allowance::class, AppraisalPeriod::class,
    AppraisalTemplate::class, LeaveCategory::class, SalaryLevel::class, SalaryStep::class,
]);

it('generates appraisal and salary slugs using only the active tenant', function (string $controllerClass, string $method, string $modelClass): void {
    setActiveTenantForTest();
    createHrmsTenantSlugRecord($modelClass, 'annual-review');
    $tenant = setActiveTenantForTest();
    $controller = app($controllerClass);
    $generator = new ReflectionMethod($controllerClass, $method);
    $arguments = $method === 'uniqueSlug' ? [$modelClass, 'Annual Review', 'salary', (string) $tenant->id] : ['Annual Review', (string) $tenant->id];

    expect($generator->invokeArgs($controller, $arguments))->toBe('annual-review');
    createHrmsTenantSlugRecord($modelClass, 'annual-review');
    expect($generator->invokeArgs($controller, $arguments))->toBe('annual-review-2');
})->with([
    [HrmsAppraisalWebController::class, 'uniquePeriodSlug', AppraisalPeriod::class],
    [HrmsAppraisalWebController::class, 'uniqueTemplateSlug', AppraisalTemplate::class],
    [HrmsSalaryWebController::class, 'uniqueSlug', SalaryLevel::class],
    [HrmsSalaryWebController::class, 'uniqueSlug', SalaryStep::class],
    [HrmsSalaryWebController::class, 'uniqueSlug', AllowanceType::class],
    [HrmsSalaryWebController::class, 'uniqueSlug', Allowance::class],
]);

it('seeds defaults only for the active tenant and is repeatable', function (): void {
    $unrelated = Tenant::factory()->create();
    $first = setActiveTenantForTest();
    $this->seed(HrmsCoreSeeder::class);
    $second = setActiveTenantForTest();
    $this->seed(HrmsCoreSeeder::class);
    $this->seed(HrmsCoreSeeder::class);

    foreach ([$first, $second] as $tenant) {
        app(TenantContext::class)->setTenant($tenant);
        expect(LeaveCategory::query()->where('slug', 'annual-leave')->count())->toBe(1);
    }

    expect(DB::connection('tenant')->table('hrms_grades')->where('tenant_id', $first->id)->count())->toBe(8)
        ->and(DB::connection('tenant')->table('hrms_grades')->where('tenant_id', $second->id)->count())->toBe(8)
        ->and(DB::connection('tenant')->table('hrms_grades')->where('tenant_id', $unrelated->id)->count())->toBe(0);
});

it('does not seed HRMS defaults without an active tenant', function (): void {
    app(TenantContext::class)->clear();
    $grades = DB::connection('tenant')->table('hrms_grades')->count();
    $categories = DB::connection('tenant')->table('hrms_leave_categories')->count();
    $this->seed([HrmsCoreSeeder::class, GradeSeeder::class, LeaveCategorySeeder::class]);

    expect(DB::connection('tenant')->table('hrms_grades')->count())->toBe($grades)
        ->and(DB::connection('tenant')->table('hrms_leave_categories')->count())->toBe($categories);
});

it('removes the legacy employee user constraint and global slug indexes idempotently', function (): void {
    $schema = Schema::connection('tenant');
    $schema->table('hrms_employees', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->dropIndex(['user_id']);
        $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
    });
    $tables = ['hrms_allowance_types', 'hrms_allowances', 'hrms_appraisal_periods', 'hrms_appraisal_templates', 'hrms_leave_categories', 'hrms_salary_levels', 'hrms_salary_steps'];

    foreach ($tables as $tableName) {
        $schema->table($tableName, function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
        });
    }
    $original = DB::getDefaultConnection();
    DB::setDefaultConnection('tenant');

    try {
        $migration = require app_path('Modules/HrmsCore/Database/Migrations/2026_10_20_000001_fix_hrms_tenant_constraints.php');
        $migration->up();
        $migration->up();
        $migration->down();
        expect(collect($schema->getForeignKeys('hrms_employees'))->contains(fn (array $key): bool => in_array('user_id', $key['columns'], true)))->toBeFalse()
            ->and($schema->hasIndex('hrms_employees', ['user_id']))->toBeTrue();

        foreach ($tables as $tableName) {
            expect($schema->hasIndex($tableName, ['tenant_id', 'slug'], 'unique'))->toBeTrue()
                ->and($schema->hasIndex($tableName, ['slug'], 'unique'))->toBeFalse();
        }
    } finally {
        DB::setDefaultConnection($original);
    }
});

it('enables HRMS on a dedicated database while employees reference landlord users', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['isolation_mode' => 'db_per_tenant', 'db_driver' => 'pgsql']);
    $database = 'tenant_'.str_replace('-', '_', (string) $tenant->id);

    try {
        app(TenantProvisioner::class)->provision($tenant);
        $tenant->refresh();
        app(TenantContext::class)->setTenant($tenant);
        app(TenantDatabaseManager::class)->configure($tenant);
        app(ModuleManager::class)->enableForTenantWithMigrations('hrms-core', $tenant);

        expect(Schema::connection('tenant')->hasTable('users'))->toBeFalse();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        expect($employee->user?->id)->toBe($user->id);
        $this->seed(HrmsCoreSeeder::class);
        expect(LeaveCategory::query()->where('slug', 'annual-leave')->exists())->toBeTrue();
    } finally {
        dropTenantDatabase($database);
        app(TenantContext::class)->clear();
        app(TenantDatabaseManager::class)->configureShared();
    }
});
