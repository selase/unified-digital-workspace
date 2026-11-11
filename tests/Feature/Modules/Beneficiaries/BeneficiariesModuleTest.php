<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Beneficiaries\Database\Seeders\BeneficiariesDemoSeeder;
use App\Modules\Beneficiaries\Models\BenAccessLog;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenCase;
use App\Modules\Beneficiaries\Models\BenCaseNote;
use App\Modules\Beneficiaries\Models\BenDocument;
use App\Modules\Beneficiaries\Models\BenReferral;
use App\Modules\Beneficiaries\Models\BenService;
use App\Modules\Beneficiaries\Services\BeneficiaryService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>|null  $only  grant just these permissions instead of all
 * @return array{0: User, 1: Tenant}
 */
function createBeneficiariesContext(?array $only = null): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.beneficiaries.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'beneficiaries', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($only ?? $permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-beneficiaries', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('beneficiaries', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/Beneficiaries/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant];
}

function consentedPerson(Tenant $tenant, string $name = 'Ama Serwaa', string $programme = 'School feeding'): BenBeneficiary
{
    $service = app(BeneficiaryService::class);
    $person = $service->register($tenant->id, $name, ['gender' => 'female', 'programme' => $programme, 'region' => 'Volta', 'phone' => '0244000000', 'date_of_birth' => '2015-04-02'], null);

    return $service->recordConsent($person, 'Receive services', today(), null);
}

describe('Beneficiary privacy rules', function (): void {
    test('registration gives a reference and no consent, and nothing can be done for the person until consent is recorded', function (): void {
        [, $tenant] = createBeneficiariesContext();
        $service = app(BeneficiaryService::class);
        $person = $service->register($tenant->id, 'Kofi Mensah', ['programme' => 'Clean water'], null);

        expect($person->reference)->toBe('BEN-'.mb_str_pad((string) $person->id, 5, '0', STR_PAD_LEFT))->and($person->hasValidConsent())->toBeFalse();
        expect(fn () => $service->recordService($person, today(), 'food', null, null, null))->toThrow(DomainException::class, 'consent')
            ->and(fn () => $service->openCase($person, 'Case', 'general', 'normal', null))->toThrow(DomainException::class, 'consent')
            ->and(fn () => $service->recordConsent($person, '  ', today(), null))->toThrow(DomainException::class);

        $service->recordConsent($person, 'Services and reports', today(), null);
        expect($person->fresh()->hasValidConsent())->toBeTrue()->and($service->recordService($person->fresh(), today(), 'food', 'Rice', 5_000, null)->value)->toBe(5_000);
    });

    test('withdrawing consent locks case work and services again', function (): void {
        [, $tenant] = createBeneficiariesContext();
        $service = app(BeneficiaryService::class);
        $person = consentedPerson($tenant);
        $case = $service->openCase($person, 'Case', 'health', 'normal', null);

        $service->withdrawConsent($person, null);
        expect($person->fresh()->hasValidConsent())->toBeFalse()
            ->and(fn () => $service->recordService($person->fresh(), today(), 'food', null, null, null))->toThrow(DomainException::class)
            ->and(fn () => $service->addNote($case, 'More', false, null))->toThrow(DomainException::class)
            ->and(fn () => $service->withdrawConsent($person->fresh(), null))->toThrow(DomainException::class);

        $service->recordConsent($person->fresh(), 'Agreed again', today(), null);
        expect($person->fresh()->hasValidConsent())->toBeTrue();
    });

    test('services need a real type, a past date and an active person; exiting closes open cases', function (): void {
        [, $tenant] = createBeneficiariesContext();
        $service = app(BeneficiaryService::class);
        $person = consentedPerson($tenant);

        expect(fn () => $service->recordService($person, today(), 'unicorns', null, null, null))->toThrow(DomainException::class)
            ->and(fn () => $service->recordService($person, today()->addDay(), 'food', null, null, null))->toThrow(DomainException::class, 'future');

        $open = $service->openCase($person, 'Open one', 'general', 'normal', null);
        $service->exit($person, 'Completed programme', null);

        expect($person->fresh()->status)->toBe('exited')->and($open->fresh()->status)->toBe('closed')->and($open->fresh()->outcome)->toContain('Completed programme')
            ->and(fn () => $service->recordService($person->fresh(), today(), 'food', null, null, null))->toThrow(DomainException::class, 'in the programme')
            ->and(fn () => $service->exit($person->fresh(), 'Again', null))->toThrow(DomainException::class);
    });

    test('anonymising erases personal details and case notes but keeps the counts, and cannot be repeated or edited', function (): void {
        [, $tenant] = createBeneficiariesContext();
        $service = app(BeneficiaryService::class);
        $person = consentedPerson($tenant);
        $service->recordService($person, today(), 'medical', 'Malaria treatment', null, null);
        $case = $service->openCase($person, 'Sensitive matter', 'protection', 'high', null);
        $service->addNote($case, 'Very private detail', true, null);

        $service->anonymise($person, null);
        $erased = $person->fresh();

        expect($erased->full_name)->toBe('Anonymised')->and($erased->phone)->toBeNull()->and($erased->date_of_birth)->toBeNull()->and($erased->isAnonymised())->toBeTrue()
            ->and($erased->programme)->toBe('School feeding')->and($erased->region)->toBe('Volta')->and($erased->gender)->toBe('female')
            ->and(BenCaseNote::query()->count())->toBe(0)->and($case->fresh()->title)->toBe('Case (anonymised)')
            ->and(BenService::query()->first()->description)->toBeNull()->and(BenService::query()->count())->toBe(1)
            ->and(fn () => $service->anonymise($erased, null))->toThrow(DomainException::class)
            ->and(fn () => $service->update($erased, ['full_name' => 'Back again'], null))->toThrow(DomainException::class)
            ->and(fn () => $service->recordService($erased, today(), 'food', null, null, null))->toThrow(DomainException::class);

        $summary = $service->summary($tenant->id, today()->startOfYear());
        expect($summary['total'])->toBe(1)->and($summary['by_programme'])->toBe(['School feeding' => 1])->and($summary['services_by_type'])->toBe(['medical' => 1]);
    });

    test('names show as initials unless the viewer may see personal details', function (): void {
        [, $tenant] = createBeneficiariesContext();
        $person = consentedPerson($tenant, 'Ama Serwaa Boateng');

        expect($person->displayName(true))->toBe('Ama Serwaa Boateng')->and($person->displayName(false))->toBe('A. S. B.');
    });

    test('every change and view is written to the access log', function (): void {
        [, $tenant] = createBeneficiariesContext();
        $service = app(BeneficiaryService::class);
        $actor = (string) Str::uuid();
        $person = consentedPerson($tenant);
        $service->recordService($person, today(), 'food', null, null, $actor);
        $case = $service->openCase($person, 'C', 'general', 'normal', $actor);
        $service->addNote($case, 'N', true, $actor);
        $service->anonymise($person, $actor);

        expect(BenAccessLog::query()->pluck('action')->all())->toBe(['registered', 'consent_recorded', 'service_recorded', 'case_opened', 'note_added', 'anonymised']);
    });
});

describe('Beneficiary documents and referrals', function (): void {
    test('documents and referrals need consent, and anonymising deletes the files', function (): void {
        [, $tenant] = createBeneficiariesContext();
        Storage::fake('tenant');
        $service = app(BeneficiaryService::class);
        $stranger = $service->register($tenant->id, 'No Consent', [], null);
        $person = consentedPerson($tenant);

        expect(fn () => $service->attachDocument($stranger, 'photo', UploadedFile::fake()->image('a.jpg'), null))->toThrow(DomainException::class, 'consent')
            ->and(fn () => $service->refer($stranger, 'Clinic', null, null, null, null))->toThrow(DomainException::class, 'consent')
            ->and(fn () => $service->attachDocument($person, 'photo', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), null))->toThrow(DomainException::class, 'photo must be an image')
            ->and(fn () => $service->attachDocument($person, 'other', UploadedFile::fake()->create('a.exe', 10), null))->toThrow(DomainException::class, 'PDF or image')
            ->and(fn () => $service->attachDocument($person, 'other', UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'), null))->toThrow(DomainException::class, '5 MB')
            ->and(fn () => $service->attachDocument($person, 'bogus', UploadedFile::fake()->image('a.jpg'), null))->toThrow(DomainException::class, 'kind of document');

        $document = $service->attachDocument($person, 'photo', UploadedFile::fake()->image('face.jpg'), null);
        Storage::disk('tenant')->assertExists($document->path);

        $otherCase = $service->openCase(consentedPerson($tenant, 'Kofi Other'), 'Other case', 'general', 'normal', null);
        expect(fn () => $service->refer($person, 'Clinic', null, $otherCase, null, null))->toThrow(DomainException::class, 'belongs to someone else')
            ->and(fn () => $service->refer($person, 'Clinic', null, null, today()->subDay(), null))->toThrow(DomainException::class, 'past');

        $referral = $service->refer($person, 'Ho Teaching Hospital', 'Eye test', null, today()->addDays(7), null);
        expect($referral->status)->toBe('made')->and($referral->isOpen())->toBeTrue()
            ->and(fn () => $service->updateReferral($referral, 'made', null, null))->toThrow(DomainException::class);
        $service->updateReferral($referral, 'completed', 'Glasses given', null);
        expect($referral->fresh()->status)->toBe('completed')->and(fn () => $service->updateReferral($referral->fresh(), 'declined', null, null))->toThrow(DomainException::class, 'finished');

        $service->anonymise($person->fresh(), null);
        Storage::disk('tenant')->assertMissing($document->path);
        expect(BenDocument::query()->where('beneficiary_id', $person->id)->count())->toBe(0)
            ->and($referral->fresh()->referred_to)->toBe('Anonymised')->and($referral->fresh()->outcome)->toBeNull();
    });

    test('the screens attach, open and remove documents (logged) and manage referrals; staff without confidential access see none', function (): void {
        [$user, $tenant] = createBeneficiariesContext();
        Storage::fake('tenant');
        $person = consentedPerson($tenant);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('beneficiaries.beneficiaries.documents.store', $person->uuid), ['kind' => 'id', 'file' => UploadedFile::fake()->create('ghana-card.pdf', 40, 'application/pdf')])->assertSessionHas('success');
        $document = BenDocument::query()->firstOrFail();
        $this->get(route('beneficiaries.beneficiaries.show', $person->uuid))->assertOk()->assertSee('ghana-card.pdf')->assertSee('Referrals');
        $this->get(route('beneficiaries.beneficiaries.documents.download', $document->uuid))->assertOk();
        expect(BenAccessLog::query()->where('action', 'document_viewed')->count())->toBe(1);
        $this->post(route('beneficiaries.beneficiaries.documents.store', $person->uuid), ['kind' => 'id'])->assertSessionHasErrors('file');

        $this->post(route('beneficiaries.beneficiaries.referrals.store', $person->uuid), ['referred_to' => 'Social Welfare', 'follow_up_on' => today()->addDays(3)->toDateString()])->assertSessionHas('success');
        $referral = BenReferral::query()->firstOrFail();
        $this->post(route('beneficiaries.beneficiaries.referrals.store', $person->uuid), [])->assertSessionHasErrors('referred_to');
        $this->post(route('beneficiaries.beneficiaries.referrals.update', $referral->uuid), ['status' => 'accepted', 'outcome' => 'Seen'])->assertSessionHas('success');
        expect($referral->fresh()->status)->toBe('accepted');

        $this->delete(route('beneficiaries.beneficiaries.documents.destroy', $document->uuid))->assertSessionHas('success');
        expect(BenDocument::query()->count())->toBe(0);

        [$limited, $limitedTenant] = createBeneficiariesContext(['beneficiaries.view']);
        $other = consentedPerson($limitedTenant);
        $kept = app(BeneficiaryService::class)->attachDocument($other, 'photo', UploadedFile::fake()->image('p.jpg'), null);
        $this->actingAs($limited)->withSession(['active_tenant_id' => $limitedTenant->id]);
        $this->get(route('beneficiaries.beneficiaries.show', $other->uuid))->assertOk()->assertDontSee('Photos and documents');
        $this->get(route('beneficiaries.beneficiaries.documents.download', $kept->uuid))->assertForbidden();
    });
});

describe('Offline field entry', function (): void {
    test('queued entries are recorded once each, rechecked for consent and validity, and safe to resend', function (): void {
        [$user, $tenant] = createBeneficiariesContext();
        $consented = consentedPerson($tenant);
        $locked = app(BeneficiaryService::class)->register($tenant->id, 'No Consent', [], null);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $payload = ['entries' => [
            ['id' => 'a-1', 'reference' => mb_strtolower($consented->reference), 'type' => 'food', 'delivered_on' => today()->toDateString(), 'description' => 'Rice and beans'],
            ['id' => 'a-2', 'reference' => $locked->reference, 'type' => 'food', 'delivered_on' => today()->toDateString()],
            ['id' => 'a-3', 'reference' => 'BEN-99999', 'type' => 'food', 'delivered_on' => today()->toDateString()],
            ['id' => 'a-4', 'reference' => $consented->reference, 'type' => 'bogus', 'delivered_on' => today()->toDateString()],
            ['id' => 'a-5', 'reference' => $consented->reference, 'type' => 'medical', 'delivered_on' => today()->addDay()->toDateString()],
        ]];

        $results = collect($this->postJson(route('beneficiaries.field.sync'), $payload)->assertOk()->json('results'))->keyBy('id');
        expect($results['a-1']['ok'])->toBeTrue()->and($results['a-2']['ok'])->toBeFalse()->and($results['a-2']['message'])->toContain('consent')
            ->and($results['a-3']['message'])->toContain('BEN-99999')->and($results['a-4']['ok'])->toBeFalse()->and($results['a-5']['message'])->toContain('future');
        expect(BenService::query()->count())->toBe(1);

        $again = collect($this->postJson(route('beneficiaries.field.sync'), $payload)->json('results'))->keyBy('id');
        expect($again['a-1']['message'])->toBe('Already recorded.')->and(BenService::query()->count())->toBe(1)
            ->and(BenAccessLog::query()->where('action', 'service_recorded')->count())->toBe(1);

        $this->postJson(route('beneficiaries.field.sync'), ['entries' => []])->assertStatus(422);
    });

    test('the page, token and service worker are available to staff who can record services only', function (): void {
        [$user, $tenant] = createBeneficiariesContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('beneficiaries.field'))->assertOk()->assertSee('Field entry')->assertSee('names are never stored on the phone', false);
        $this->getJson(route('beneficiaries.field.token'))->assertOk()->assertJsonStructure(['token']);
        $this->get(route('beneficiaries.field.sw'))->assertOk()->assertHeader('Service-Worker-Allowed', '/beneficiaries/')->assertSee('field-entry-v1', false);

        $user->revokePermissionTo('beneficiaries.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('beneficiaries.field'))->assertForbidden();
        $this->postJson(route('beneficiaries.field.sync'), ['entries' => [['id' => 'x', 'reference' => 'BEN-00001', 'type' => 'food', 'delivered_on' => today()->toDateString()]]])->assertForbidden();
    });
});

describe('Beneficiary screens', function (): void {
    test('staff register a person, record consent, a service, a case and a note, and the access log fills', function (): void {
        [$user, $tenant] = createBeneficiariesContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('beneficiaries.index'))->assertOk()->assertSee('Registered');
        $this->post(route('beneficiaries.beneficiaries.store'), ['full_name' => 'Efua Owusu', 'gender' => 'female', 'programme' => 'Maternal health', 'region' => 'Volta'])->assertRedirect();
        $person = BenBeneficiary::query()->firstOrFail();
        $this->get(route('beneficiaries.beneficiaries.show', $person->uuid))->assertOk()->assertSee('No consent recorded yet');

        $this->post(route('beneficiaries.beneficiaries.services.store', $person->uuid), ['delivered_on' => today()->toDateString(), 'type' => 'medical'])->assertSessionHas('error');
        $this->post(route('beneficiaries.beneficiaries.consent', $person->uuid), ['consent_scope' => 'Services and reports', 'consent_date' => today()->toDateString()])->assertSessionHas('success');
        $this->post(route('beneficiaries.beneficiaries.services.store', $person->uuid), ['delivered_on' => today()->toDateString(), 'type' => 'medical', 'description' => 'Check-up', 'value' => '25'])->assertSessionHas('success');
        $this->post(route('beneficiaries.beneficiaries.cases.store', $person->uuid), ['title' => 'Follow-up', 'category' => 'health', 'priority' => 'high'])->assertSessionHas('success');
        $case = BenCase::query()->firstOrFail();
        $this->post(route('beneficiaries.beneficiaries.cases.notes', $case->uuid), ['note' => 'Referred', 'is_confidential' => '1'])->assertSessionHas('success');
        $this->get(route('beneficiaries.beneficiaries.show', $person->uuid))->assertOk()->assertSee('Check-up')->assertSee('Referred')->assertSee('(confidential)');
        $this->post(route('beneficiaries.beneficiaries.cases.close', $case->uuid), ['outcome' => 'Resolved'])->assertSessionHas('success');
        $this->get(route('beneficiaries.beneficiaries.index', ['search' => 'Efua']))->assertOk()->assertSee('Efua Owusu');
        $this->get(route('beneficiaries.audit'))->assertOk()->assertSee('consent recorded')->assertSee($person->reference);
    });

    test('staff without the confidential permission see initials and no confidential notes, and the view is logged as limited', function (): void {
        [$user, $tenant] = createBeneficiariesContext(['beneficiaries.view']);
        $service = app(BeneficiaryService::class);
        $person = consentedPerson($tenant, 'Ama Serwaa Boateng');
        $case = $service->openCase($person, 'Case', 'general', 'normal', null);
        $service->addNote($case, 'Open note', false, null);
        $service->addNote($case, 'Secret note', true, null);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $page = $this->get(route('beneficiaries.beneficiaries.show', $person->uuid))->assertOk();
        $page->assertSee('A. S. B.')->assertDontSee('Ama Serwaa Boateng')->assertSee('Open note')->assertDontSee('Secret note')->assertDontSee('0244000000');
        $this->get(route('beneficiaries.beneficiaries.index', ['search' => 'Boateng']))->assertOk()->assertDontSee('Ama Serwaa');
        expect(BenAccessLog::query()->where('action', 'viewed_limited')->count())->toBe(1);
    });

    test('anonymise, audit log and writes are permission checked, and anonymising needs a typed confirmation', function (): void {
        [$user, $tenant] = createBeneficiariesContext(['beneficiaries.view', 'beneficiaries.manage', 'beneficiaries.anonymise']);
        $person = consentedPerson($tenant);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('beneficiaries.audit'))->assertForbidden();
        $this->post(route('beneficiaries.beneficiaries.cases.store', $person->uuid), ['title' => 'x', 'category' => 'general', 'priority' => 'normal'])->assertForbidden();
        $this->post(route('beneficiaries.beneficiaries.anonymise', $person->uuid), ['confirm' => 'yes'])->assertStatus(422);
        expect($person->fresh()->isAnonymised())->toBeFalse();
        $this->post(route('beneficiaries.beneficiaries.anonymise', $person->uuid), ['confirm' => 'ANONYMISE'])->assertSessionHas('success');
        expect($person->fresh()->isAnonymised())->toBeTrue();

        $user->revokePermissionTo(['beneficiaries.anonymise', 'beneficiaries.manage']);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $other = consentedPerson($tenant, 'Second Person');
        $this->post(route('beneficiaries.beneficiaries.anonymise', $other->uuid), ['confirm' => 'ANONYMISE'])->assertForbidden();
        $this->post(route('beneficiaries.beneficiaries.store'), ['full_name' => 'X'])->assertForbidden();
    });

    test('validation is enforced', function (): void {
        [$user, $tenant] = createBeneficiariesContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('beneficiaries.beneficiaries.store'), ['gender' => 'robot', 'date_of_birth' => today()->addDay()->toDateString()])->assertSessionHasErrors(['full_name', 'gender', 'date_of_birth']);
        $person = consentedPerson($tenant);
        $this->post(route('beneficiaries.beneficiaries.services.store', $person->uuid), ['delivered_on' => today()->toDateString(), 'type' => 'invalid'])->assertSessionHasErrors('type');
        $this->post(route('beneficiaries.beneficiaries.exit', $person->uuid), ['reason' => ''])->assertStatus(422);
    });
});

describe('Beneficiaries demo seeder', function (): void {
    test('creates a register with consent, services and cases, once, and refuses production', function (): void {
        [, $tenant] = createBeneficiariesContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(BeneficiariesDemoSeeder::class);

        expect(BenBeneficiary::query()->count())->toBe(12)->and(BenBeneficiary::query()->where('consent_given', false)->count())->toBe(1)
            ->and(BenCase::query()->where('status', 'open')->count())->toBe(2)->and(BenService::query()->count())->toBe(33);

        $count = BenService::query()->count();
        $this->seed(BeneficiariesDemoSeeder::class);
        expect(BenService::query()->count())->toBe($count);

        BenCaseNote::query()->delete();
        BenCase::query()->delete();
        BenService::query()->delete();
        BenBeneficiary::query()->delete();
        app()->detectEnvironment(fn (): string => 'production');
        (new BeneficiariesDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(BenBeneficiary::query()->count())->toBe(0);
    });
});
