<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Services;

use App\Models\Tenant;
use App\Modules\HrmsCore\Enums\ApplicationStatus;
use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use App\Modules\HrmsCore\Models\Recruitment\CandidateApplication;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a public careers-page submission into a candidate and an application,
 * the same records HR creates by hand from the recruitment screen.
 */
final class CareersApplicationService
{
    public function __construct(private readonly AlertDispatcher $alerts) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null, cover_letter?: string|null, current_employer?: string|null, current_position?: string|null, years_of_experience?: int|null, highest_qualification?: string|null}  $data
     */
    public function apply(JobPosting $posting, array $data): CandidateApplication
    {
        if (! $posting->is_external || ! $posting->isAcceptingApplications()) {
            throw new DomainException('This job is no longer accepting applications.');
        }

        $email = mb_strtolower(mb_trim($data['email']));

        $application = DB::connection('tenant')->transaction(function () use ($posting, $data, $email): CandidateApplication {
            $candidate = Candidate::query()
                ->where('tenant_id', $posting->tenant_id)
                ->where('email', $email)
                ->first() ?? Candidate::query()->create([
                    'tenant_id' => $posting->tenant_id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'current_employer' => $data['current_employer'] ?? null,
                    'current_position' => $data['current_position'] ?? null,
                    'years_of_experience' => $data['years_of_experience'] ?? null,
                    'highest_qualification' => $data['highest_qualification'] ?? null,
                    'source' => 'careers_page',
                    'status' => 'active',
                ]);

            if ($candidate->isBlacklisted()) {
                throw new DomainException('We could not accept this application.');
            }

            $alreadyApplied = CandidateApplication::withTrashed()
                ->where('candidate_id', $candidate->id)
                ->where('posting_id', $posting->id)
                ->exists();

            if ($alreadyApplied) {
                throw new DomainException('You have already applied for this job.');
            }

            $application = CandidateApplication::query()->create([
                'tenant_id' => $posting->tenant_id,
                'candidate_id' => $candidate->id,
                'posting_id' => $posting->id,
                'application_number' => 'APP-'.now()->format('Y').'-'.mb_strtoupper(Str::random(5)),
                'status' => ApplicationStatus::Submitted,
                'stage' => 'application',
                'cover_letter' => $data['cover_letter'] ?? null,
            ]);

            $posting->incrementApplications();

            return $application;
        });

        if ($tenant = Tenant::query()->find($posting->tenant_id)) {
            $this->alerts->dispatchSafely(
                $tenant,
                AlertEvent::JOB_APPLICATION,
                'New job application',
                $data['first_name'].' '.$data['last_name'].' applied for "'.$posting->title.'".',
                'job-application-'.$application->id,
            );
        }

        return $application;
    }
}
