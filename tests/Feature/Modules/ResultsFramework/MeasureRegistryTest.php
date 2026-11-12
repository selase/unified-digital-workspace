<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\MeasureRegistry;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/fixtures.php';

test('the registry returns enabled tenant measures with inclusive ranges and scoped filters', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $other = Tenant::factory()->create();
    $registry = app(MeasureRegistry::class);
    expect($registry->definitions($tenant))->toBe([]);
    enableResultMeasureModules($tenant, 'beneficiaries', 'volunteers', 'donations', 'grants', 'memberships', 'forms');

    $from = CarbonImmutable::parse('2026-01-01');
    $to = CarbonImmutable::parse('2026-01-31');
    $before = $from->subDay()->toDateString();
    $after = $to->addDay()->toDateString();
    $start = $from->toDateString();
    $end = $to->toDateString();
    $db = DB::connection('tenant');
    $insert = fn (string $table, array $fields): int => $db->table($table)->insertGetId($fields);
    $owned = fn (string $table, array $fields, ?Tenant $owner = null): int => $insert($table, $fields + ['tenant_id' => ($owner ?? $tenant)->id, 'uuid' => (string) Str::uuid()]);

    $person = $owned('ben_beneficiaries', ['full_name' => 'One', 'programme' => 'Water', 'enrolled_on' => $start]);
    $second = $owned('ben_beneficiaries', ['full_name' => 'Two', 'programme' => 'Food', 'enrolled_on' => $end]);
    $outside = $owned('ben_beneficiaries', ['full_name' => 'Outside', 'enrolled_on' => $before]);
    $foreign = $owned('ben_beneficiaries', ['full_name' => 'Foreign', 'programme' => 'Other tenant', 'enrolled_on' => $start], $other);
    foreach ([[$person, $start, 'food'], [$person, $end, 'food'], [$second, $end, 'medical'], [$outside, $before, 'food']] as [$id, $date, $type]) {
        $owned('ben_services', ['beneficiary_id' => $id, 'delivered_on' => $date, 'type' => $type]);
    }
    $owned('ben_services', ['beneficiary_id' => $foreign, 'delivered_on' => $start, 'type' => 'food'], $other);

    $volunteer = $owned('vol_volunteers', ['name' => 'Current', 'joined_on' => $before, 'status' => 'active']);
    $owned('vol_volunteers', ['name' => 'Later', 'joined_on' => $after, 'status' => 'active']);
    $owned('vol_volunteers', ['name' => 'Inactive', 'joined_on' => $start, 'status' => 'inactive']);
    $foreignVolunteer = $owned('vol_volunteers', ['name' => 'Foreign', 'joined_on' => $start, 'status' => 'active'], $other);
    foreach ([[$start, 60, 'approved'], [$end, 90, 'approved'], [$end, 600, 'pending'], [$after, 600, 'approved']] as [$date, $minutes, $status]) {
        $owned('vol_hours', ['volunteer_id' => $volunteer, 'worked_on' => $date, 'minutes' => $minutes, 'status' => $status]);
    }
    $owned('vol_hours', ['volunteer_id' => $foreignVolunteer, 'worked_on' => $start, 'minutes' => 600, 'status' => 'approved'], $other);

    $campaign = $owned('don_campaigns', ['name' => 'Water campaign']);
    $foreignCampaign = $owned('don_campaigns', ['name' => 'Foreign campaign'], $other);
    $donor = $owned('don_donors', ['name' => 'Donor']);
    $otherDonor = $owned('don_donors', ['name' => 'Other donor']);
    $foreignDonor = $owned('don_donors', ['name' => 'Foreign donor'], $other);
    $account = $owned('fin_accounts', ['name' => 'Bank', 'code' => '101', 'type' => 'asset']);
    foreach ([[$donor, $campaign, $start, 1200, null], [$donor, null, $end, 800, null], [$otherDonor, $campaign, $end, 9900, now()], [$otherDonor, $campaign, $after, 9900, null], [$foreignDonor, $foreignCampaign, $end, 9900, null]] as $index => [$id, $campaignId, $date, $amount, $voided]) {
        $owned('don_donations', ['donor_id' => $id, 'campaign_id' => $campaignId, 'date' => $date, 'amount' => $amount, 'voided_at' => $voided, 'deposit_account_id' => $account, 'number' => 'D'.$index, 'receipt_number' => 'R'.$index], $id === $foreignDonor ? $other : $tenant);
    }

    $funder = $owned('grt_funders', ['name' => 'Funder']);
    $grant = $owned('grt_grants', ['funder_id' => $funder, 'title' => 'Grant', 'total_amount' => 9000]);
    foreach ([[$end, 3100, 'received', $tenant], [$end, 6000, 'expected', $tenant], [$after, 6000, 'received', $tenant], [$end, 6000, 'received', $other]] as [$date, $amount, $status, $owner]) {
        $owned('grt_instalments', ['grant_id' => $grant, 'expected_on' => $before, 'received_on' => $date, 'amount' => $amount, 'status' => $status], $owner);
    }

    $type = $owned('mem_types', ['name' => 'Member type']);
    foreach ([[$before, $end, 'active', $tenant], [$start, $before, 'active', $tenant], [$end, null, 'pending', $tenant], [$after, null, 'active', $tenant], [$start, null, 'active', $other]] as $index => [$joined, $expires, $status, $owner]) {
        $owned('mem_members', ['type_id' => $type, 'number' => 'M'.$index, 'name' => 'Member', 'joined_on' => $joined, 'expires_on' => $expires, 'status' => $status], $owner);
    }
    $member = $db->table('mem_members')->where('number', 'M0')->value('id');
    $contact = $owned('fin_contacts', ['name' => 'Member']);
    $invoice = $owned('fin_invoices', ['contact_id' => $contact, 'issue_date' => $before, 'status' => 'partly_paid']);
    $insert('mem_renewals', ['tenant_id' => $tenant->id, 'member_id' => $member, 'type_id' => $type, 'invoice_id' => $invoice, 'period_start' => $before, 'period_end' => $after, 'amount' => 5000, 'status' => 'billed', 'created_at' => $before]);
    foreach ([[$start, 1300, $tenant], [$end, 700, $tenant], [$before, 500, $tenant], [$end, 9000, $other]] as $index => [$date, $amount, $owner]) {
        $payment = $owned('fin_payments', ['contact_id' => $contact, 'number' => 'P'.$index, 'date' => $date, 'amount' => $amount + 900], $owner);
        $insert('fin_payment_allocations', ['invoice_id' => $invoice, 'payment_id' => $payment, 'amount' => $amount]);
    }

    $unrelatedInvoice = $owned('fin_invoices', ['contact_id' => $contact, 'issue_date' => $start, 'status' => 'paid']);
    $unrelatedPayment = $owned('fin_payments', ['contact_id' => $contact, 'number' => 'UNRELATED', 'date' => $end, 'amount' => 4100]);
    $insert('fin_payment_allocations', ['invoice_id' => $unrelatedInvoice, 'payment_id' => $unrelatedPayment, 'amount' => 4100]);

    $form = $owned('form_definitions', ['title' => 'Survey', 'slug' => 'survey', 'created_by' => $user->uuid]);
    $secondForm = $owned('form_definitions', ['title' => 'Other survey', 'slug' => 'other', 'created_by' => $user->uuid]);
    $foreignForm = $owned('form_definitions', ['title' => 'Foreign survey', 'slug' => 'foreign', 'created_by' => $user->uuid], $other);
    foreach ([[$form, $start.' 00:00:00', $tenant], [$secondForm, $end.' 23:59:59', $tenant], [$form, $after.' 00:00:00', $tenant], [$foreignForm, $start.' 12:00:00', $other]] as [$id, $date, $owner]) {
        $owned('form_submissions', ['form_definition_id' => $id, 'responses_json' => '{}', 'created_at' => $date], $owner);
    }

    $figures = [
        'beneficiaries.people_reached' => 2.0, 'beneficiaries.services_delivered' => 3.0, 'beneficiaries.new_registrations' => 2.0,
        'volunteers.hours_approved' => 2.5, 'volunteers.active_volunteers' => 1.0,
        'donations.amount_raised' => 20.0, 'donations.gifts_received' => 2.0, 'donations.donors_who_gave' => 1.0,
        'grants.funds_received' => 31.0,
        'memberships.active_members' => 1.0, 'memberships.new_members' => 2.0, 'memberships.dues_collected' => 20.0,
        'forms.submissions' => 2.0,
    ];

    $definitions = $registry->definitions($tenant);
    expect(array_keys($definitions))->toEqualCanonicalizing(array_keys($figures));
    foreach ($figures as $key => $expected) {
        expect($registry->value($tenant, $key, new MeasureQuery($from, $to)))->toBe($expected);
    }
    expect($definitions['beneficiaries.people_reached']->filters['programme'])->toBe(['Food' => 'Food', 'Water' => 'Water'])
        ->and($registry->value($tenant, 'beneficiaries.people_reached', new MeasureQuery($from, $to, ['programme' => 'Water', 'service_type' => 'food'])))->toBe(1.0)
        ->and($registry->value($tenant, 'beneficiaries.services_delivered', new MeasureQuery($from, $to, ['service_type' => 'medical'])))->toBe(1.0)
        ->and($definitions['donations.amount_raised']->filters['campaign_id'])->toBe([$campaign => 'Water campaign'])
        ->and($registry->value($tenant, 'donations.amount_raised', new MeasureQuery($from, $to, ['campaign_id' => (string) $campaign])))->toBe(12.0)
        ->and($definitions['forms.submissions']->filters['form_id'])->not->toHaveKey($foreignForm)
        ->and($registry->value($tenant, 'forms.submissions', new MeasureQuery($from, $to, ['form_id' => (string) $form])))->toBe(1.0)
        ->and(fn () => $registry->validateSelection($tenant, 'forms.submissions', ['form_id' => (string) $foreignForm]))->toThrow(DomainException::class)
        ->and(fn () => $registry->validateSelection($tenant, 'forms.submissions', ['unknown' => 'x']))->toThrow(DomainException::class)
        ->and(fn () => $registry->breakdown($tenant, 'forms.submissions', 'gender', new MeasureQuery($from, $to)))->toThrow(DomainException::class);
});

test('live indicator configuration persists and disabled measures are unavailable', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.indicators.index'))->assertOk()->assertSee('beneficiaries.people_reached')->assertDontSee('volunteers.hours_approved');
    $fields = [
        'name' => 'Live count', 'target' => 10, 'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->toDateString(),
        'measure_key' => 'beneficiaries.people_reached', 'measure_filters' => ['beneficiaries.people_reached' => ['service_type' => 'food']],
    ];
    $this->post(route('results-framework.indicators.store'), array_replace($fields, ['measure_key' => 'volunteers.hours_approved']))
        ->assertSessionHas('error');
    $this->post(route('results-framework.indicators.store'), $fields)->assertSessionHas('success');

    $indicator = ResultIndicator::query()->where('name', 'Live count')->firstOrFail();
    expect($indicator->measure_filters)->toBe(['service_type' => 'food']);
    $indicator->readings()->create(['tenant_id' => $tenant->id, 'recorded_on' => today(), 'value' => 99]);
    expect(app(IndicatorService::class)->current($indicator))->toBe(0.0);
    TenantModule::query()->where('tenant_id', $tenant->id)->where('module_slug', 'beneficiaries')->update(['is_enabled' => false]);
    Cache::flush();
    expect(app(IndicatorService::class)->current($indicator))->toBeNull();
    $this->get(route('results-framework.indicators.show', $indicator->uuid))->assertOk()->assertSee('Unavailable')->assertDontSee('Add a reading');
    $this->get(route('results-framework.indicators.edit', $indicator->uuid))->assertOk()->assertSee('Unavailable');
    unset($fields['measure_filters']);
    $this->put(route('results-framework.indicators.update', $indicator->uuid), array_replace($fields, ['name' => 'Edited while unavailable']))
        ->assertRedirect(route('results-framework.indicators.show', $indicator->uuid));
    expect($indicator->fresh()->measure_filters)->toBe(['service_type' => 'food']);
    $report = $this->get(route('results-framework.report'))->assertOk();
    expect($report->streamedContent())->toContain('Unavailable');
});

test('retired programme filters keep calculating and the list loads programme options once', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $person = App\Modules\Beneficiaries\Models\BenBeneficiary::query()->create([
        'tenant_id' => $tenant->id, 'full_name' => 'Person', 'programme' => 'Water',
    ]);
    $person->services()->create(['tenant_id' => $tenant->id, 'delivered_on' => today(), 'type' => 'food']);
    $service = app(IndicatorService::class);
    foreach (['First', 'Second'] as $name) {
        $indicator = $service->create($tenant->id, $name, 10, today(), today(), [
            'measure_key' => 'beneficiaries.people_reached', 'measure_filters' => ['programme' => 'Water'],
        ]);
    }
    expect($service->current($indicator))->toBe(1.0);

    $person->update(['programme' => 'Food']);
    $figure = $service->current($indicator);
    $optionQueries = 0;
    DB::listen(function (Illuminate\Database\Events\QueryExecuted $event) use (&$optionQueries): void {
        if (str_contains($event->sql, 'select distinct "programme"')) {
            $optionQueries++;
        }
    });
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('results-framework.indicators.index'))->assertOk();

    expect([$figure, $optionQueries])->toBe([0.0, 1]);
});

test('measure rollback refuses to discard newer keys or filters', function (array $fields): void {
    [, $tenant] = createMonitoringContext();
    $indicator = ResultIndicator::query()->create($fields + [
        'tenant_id' => $tenant->id, 'name' => 'Guard', 'target' => 10, 'starts_on' => today(), 'ends_on' => today(),
    ]);
    $migration = require app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100500_add_measures_to_rf_indicators.php');
    DB::usingConnection('tenant', function () use ($migration, $indicator, $fields): void {
        expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'cannot be represented');
        $schema = DB::connection('tenant')->getSchemaBuilder();
        expect($schema->hasColumns('rf_indicators', ['measure_key', 'measure_filters']))->toBeTrue()
            ->and($schema->hasColumn('rf_indicators', 'source'))->toBeFalse()
            ->and($indicator->fresh()->only(array_keys($fields)))->toBe($fields);
    });
})->with([
    'new key' => [['measure_key' => 'forms.submissions']],
    'saved filters' => [['measure_key' => 'beneficiaries.people_reached', 'measure_filters' => ['programme' => 'Water']]],
]);

test('measure migration refuses an unknown legacy source before changing the schema', function (): void {
    [, $tenant] = createMonitoringContext();
    $migration = require app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100500_add_measures_to_rf_indicators.php');
    DB::usingConnection('tenant', function () use ($migration, $tenant): void {
        $migration->down();
        $db = DB::connection('tenant');
        $id = $db->table('rf_indicators')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'name' => 'Legacy', 'source' => 'unknown',
            'target' => 10, 'starts_on' => today(), 'ends_on' => today(),
        ]);
        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'unrecognised legacy source');
        $schema = $db->getSchemaBuilder();
        expect($schema->hasColumn('rf_indicators', 'source'))->toBeTrue()
            ->and($schema->hasColumn('rf_indicators', 'measure_key'))->toBeFalse()
            ->and($schema->hasColumn('rf_indicators', 'measure_filters'))->toBeFalse()
            ->and($db->table('rf_indicators')->where('id', $id)->value('source'))->toBe('unknown');
        $db->table('rf_indicators')->where('id', $id)->update(['source' => 'manual']);
        $migration->up();
    });
});

test('full rollback preflight refuses before dropping later tables or changing stored rows', function (bool $withVariance): void {
    [, $tenant] = createMonitoringContext();
    $indicator = ResultIndicator::query()->create([
        'tenant_id' => $tenant->id, 'name' => 'Protected configuration', 'target' => 10,
        'starts_on' => today(), 'ends_on' => today(), 'measure_key' => 'forms.submissions',
    ]);
    if ($withVariance) {
        $indicator->varianceNotes()->create(['tenant_id' => $tenant->id, 'reason' => 'Preserve this note']);
    }
    $db = DB::connection('tenant');
    $schema = $db->getSchemaBuilder();
    $tables = ['rf_indicators', 'rf_readings', 'rf_programmes', 'rf_results', 'rf_reading_breakdowns', 'rf_targets', 'rf_variance_notes'];
    $snapshot = fn (): array => array_map(fn (string $table): array => [
        $schema->getColumnListing($table), $db->table($table)->where('tenant_id', $tenant->id)->orderBy('id')->get()->toJson(),
    ], $tables);
    $before = $snapshot();
    $guard = app(App\Modules\ResultsFramework\Support\RollbackPreflight::class);
    $expected = $withVariance ? 'Variance notes' : 'Unrepresentable indicator measures or filters';
    expect($guard->tenantBlockers($db))->toContain('Unrepresentable indicator measures or filters', $expected);
    expect(fn () => $guard->assertTenantCanRollback($db))->toThrow(RuntimeException::class, $expected);
    $migration = require app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100800_create_rf_variance_notes.php');
    DB::usingConnection('tenant', fn () => expect(fn () => $migration->down())->toThrow(RuntimeException::class, $expected));
    expect($snapshot())->toBe($before)->and($schema->hasTable('pr_indicators'))->toBeFalse();
})->with(['later table empty' => [false], 'later table has a note' => [true]]);
