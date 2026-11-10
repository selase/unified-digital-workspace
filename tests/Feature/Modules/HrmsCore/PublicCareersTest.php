<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use App\Modules\HrmsCore\Mail\ApplicationReceivedMail;
use App\Modules\HrmsCore\Models\Recruitment\CandidateApplication;
use App\Modules\HrmsCore\Models\Recruitment\CandidateDocument;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $attributes
 */
function careersPosting(string $tenantId, array $attributes): JobPosting
{
    $department = Department::query()->create(['tenant_id' => $tenantId, 'name' => 'Dept '.Illuminate\Support\Str::random(5), 'is_active' => true]);
    $requisition = JobRequisition::query()->create([
        'tenant_id' => $tenantId,
        'title' => 'Req',
        'department_id' => $department->id,
        'requisition_number' => 'REQ-'.Illuminate\Support\Str::random(6),
        'status' => 'approved',
        'vacancies' => 1,
    ]);

    return JobPosting::query()->create($attributes + ['tenant_id' => $tenantId, 'requisition_id' => $requisition->id]);
}

/**
 * @return array{0: Tenant, 1: JobPosting}
 */
function createCareersContext(array $postingOverrides = []): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);
    app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

    $posting = careersPosting($tenant->id, $postingOverrides + [
        'title' => 'Backend Engineer',
        'slug' => 'backend-engineer',
        'description' => 'Build things.',
        'is_external' => true,
        'is_active' => true,
        'posted_date' => now()->subDay(),
    ]);

    return [$tenant, $posting];
}

function applicationPayload(array $overrides = []): array
{
    return $overrides + [
        'first_name' => 'Ama',
        'last_name' => 'Mensah',
        'email' => 'ama@example.test',
        'cover_letter' => 'I can help.',
        'form_token' => EnquiryToken::issue(time() - 10),
    ];
}

describe('Public careers page', function (): void {
    test('lists open external jobs and hides closed, internal and other organisations\' ones', function (): void {
        [$tenant, $open] = createCareersContext();
        careersPosting($tenant->id, ['title' => 'Closed Role', 'slug' => 'closed', 'is_external' => true, 'is_active' => true, 'closing_date' => now()->subDay()]);
        careersPosting($tenant->id, ['title' => 'Internal Role', 'slug' => 'internal', 'is_external' => false, 'is_internal' => true, 'is_active' => true]);
        $other = Tenant::factory()->create();
        careersPosting($other->id, ['title' => 'Foreign Role', 'slug' => 'foreign', 'is_external' => true, 'is_active' => true]);

        $this->get(route('hrms-core.public.careers.index'))
            ->assertOk()->assertSee('Backend Engineer')
            ->assertDontSee('Closed Role')->assertDontSee('Internal Role')->assertDontSee('Foreign Role');
    });

    test('shows a job with its application form and counts the view', function (): void {
        [, $posting] = createCareersContext();

        $this->get(route('hrms-core.public.careers.show', 'backend-engineer'))
            ->assertOk()->assertSee('Apply for this job')->assertSee('name="form_token"', false);

        expect($posting->fresh()->views_count)->toBe(1);
        $this->get(route('hrms-core.public.careers.show', 'nope'))->assertNotFound();
    });

    test('records an application as a candidate and sends the alert', function (): void {
        [$tenant, $posting] = createCareersContext();
        Illuminate\Support\Facades\Notification::fake();

        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload())
            ->assertRedirect()->assertSessionHas('applied');

        $application = CandidateApplication::query()->firstOrFail();
        expect($application->status->value)->toBe('submitted')
            ->and(Candidate::query()->where('email', 'ama@example.test')->value('source'))->toBe('careers_page')
            ->and($posting->fresh()->applications_count)->toBe(1);
    });

    test('refuses a second application for the same job from the same email', function (): void {
        createCareersContext();
        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload());
        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload(['email' => 'AMA@example.test']))
            ->assertSessionHasErrors('email');

        expect(CandidateApplication::query()->count())->toBe(1);
    });

    test('ignores bots: honeypot filled, missing or too-fast token', function (): void {
        createCareersContext();

        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload(['website' => 'http://spam.test']))->assertRedirect();
        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload(['form_token' => EnquiryToken::issue()]))->assertSessionHasErrors('form_token');
        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload(['form_token' => 'garbage']))->assertSessionHasErrors('form_token');

        expect(CandidateApplication::query()->count())->toBe(0);
    });

    test('rejects an invalid application and a closed job', function (): void {
        [, $posting] = createCareersContext();

        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload(['email' => 'not-an-email', 'first_name' => '']))
            ->assertSessionHasErrors(['email', 'first_name']);

        $posting->update(['closing_date' => now()->subDay()]);
        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload())->assertNotFound();
        expect(CandidateApplication::query()->count())->toBe(0);
    });
});

describe('Careers CV upload and acknowledgement', function (): void {
    test('an applicant can attach a CV, gets a confirmation email, and only a PDF or Word file is accepted', function (): void {
        [$tenant] = createCareersContext();
        Storage::fake('tenant');
        Mail::fake();
        $url = route('hrms-core.public.careers.apply', 'backend-engineer');

        $this->post($url, applicationPayload(['cv' => UploadedFile::fake()->create('cv.exe', 10)]))->assertSessionHasErrors('cv');
        $this->post($url, applicationPayload(['cv' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('cv');
        expect(CandidateApplication::query()->count())->toBe(0);

        $this->post($url, applicationPayload(['cv' => UploadedFile::fake()->create('ama-cv.pdf', 80, 'application/pdf')]))->assertRedirect()->assertSessionHas('applied');

        $document = CandidateDocument::query()->firstOrFail();
        expect($document->type)->toBe('resume')->and($document->name)->toBe('ama-cv.pdf')->and($document->application_id)->toBe(CandidateApplication::query()->value('id'));
        Storage::disk('tenant')->assertExists($document->file_path);
        Mail::assertSent(ApplicationReceivedMail::class, fn (ApplicationReceivedMail $mail): bool => $mail->hasTo('ama@example.test') && $mail->jobTitle === 'Backend Engineer');
    });

    test('an application without a CV still works, and a mail failure does not lose it', function (): void {
        createCareersContext();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

        $this->post(route('hrms-core.public.careers.apply', 'backend-engineer'), applicationPayload())->assertRedirect()->assertSessionHas('applied');
        expect(CandidateApplication::query()->count())->toBe(1)->and(CandidateDocument::query()->count())->toBe(0);
    });
});
