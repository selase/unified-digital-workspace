<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiaries\Http\Requests\AddDocumentRequest;
use App\Modules\Beneficiaries\Http\Requests\AddNoteRequest;
use App\Modules\Beneficiaries\Http\Requests\OpenCaseRequest;
use App\Modules\Beneficiaries\Http\Requests\RecordConsentRequest;
use App\Modules\Beneficiaries\Http\Requests\RecordServiceRequest;
use App\Modules\Beneficiaries\Http\Requests\ReferRequest;
use App\Modules\Beneficiaries\Http\Requests\SaveBeneficiaryRequest;
use App\Modules\Beneficiaries\Http\Requests\UpdateReferralRequest;
use App\Modules\Beneficiaries\Models\BenAccessLog;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenCase;
use App\Modules\Beneficiaries\Models\BenDocument;
use App\Modules\Beneficiaries\Models\BenReferral;
use App\Modules\Beneficiaries\Services\BeneficiaryService;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BeneficiaryController extends Controller
{
    use ResolvesFinanceTenant;

    public function overview(Request $request, BeneficiaryService $service): View
    {
        $tenantId = $this->allow($request, 'beneficiaries.view');

        return view('beneficiaries::dashboard', [
            'summary' => $service->summary($tenantId, today()->startOfYear()),
            'openCases' => BenCase::query()->where('tenant_id', $tenantId)->where('status', 'open')->count(),
            'highPriority' => BenCase::query()->where('tenant_id', $tenantId)->where('status', 'open')->where('priority', 'high')->count(),
            'noConsent' => BenBeneficiary::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereNull('anonymised_at')->where(fn ($q) => $q->where('consent_given', false)->orWhereNotNull('consent_withdrawn_on'))->count(),
            'mayViewPersonal' => (bool) $request->user()?->can('beneficiaries.confidential.view'),
        ]);
    }

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'beneficiaries.view');
        $mayViewPersonal = (bool) $request->user()?->can('beneficiaries.confidential.view');
        $search = $request->string('search')->trim()->toString();
        $programme = $request->string('programme')->toString();

        return view('beneficiaries::beneficiaries.index', [
            'beneficiaries' => BenBeneficiary::query()->where('tenant_id', $tenantId)
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('reference', 'ilike', "%{$search}%")->when($mayViewPersonal, fn ($inner) => $inner->orWhere('full_name', 'ilike', "%{$search}%"))))
                ->when($programme !== '', fn ($query) => $query->where('programme', $programme))
                ->orderByDesc('id')->paginate(25)->withQueryString(),
            'programmes' => BenBeneficiary::query()->where('tenant_id', $tenantId)->whereNotNull('programme')->distinct()->orderBy('programme')->pluck('programme'),
            'mayViewPersonal' => $mayViewPersonal,
            'search' => $search,
            'programme' => $programme,
        ]);
    }

    public function store(SaveBeneficiaryRequest $request, BeneficiaryService $service): RedirectResponse
    {
        $beneficiary = $service->register($this->tenantId($request), (string) $request->validated('full_name'), $request->safe()->except('full_name'), $this->actorId($request));

        return redirect()->route('beneficiaries.beneficiaries.show', $beneficiary->uuid)->with('success', 'Registered as '.$beneficiary->reference.'. Record consent before recording services or opening a case.');
    }

    public function show(Request $request, BeneficiaryService $service, string $beneficiary): View
    {
        $tenantId = $this->allow($request, 'beneficiaries.view');
        $model = $this->find($tenantId, $beneficiary);
        $mayViewPersonal = (bool) $request->user()?->can('beneficiaries.confidential.view');
        $service->log($tenantId, $this->actorId($request), $mayViewPersonal ? 'viewed' : 'viewed_limited', $model);

        $cases = $model->cases()->with(['notes' => fn ($query) => $mayViewPersonal ? $query : $query->where('is_confidential', false)])->latest('opened_on')->get();

        return view('beneficiaries::beneficiaries.show', [
            'beneficiary' => $model,
            'cases' => $cases,
            'services' => $model->services()->latest('delivered_on')->limit(50)->get(),
            'documents' => $mayViewPersonal ? $model->documents()->latest()->get() : collect(),
            'referrals' => $model->referrals()->latest('referred_on')->get(),
            'mayViewPersonal' => $mayViewPersonal,
        ]);
    }

    public function update(SaveBeneficiaryRequest $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        try {
            $service->update($this->find($this->tenantId($request), $beneficiary), $request->validated(), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Details updated.');
    }

    public function consent(RecordConsentRequest $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        try {
            $service->recordConsent($this->find($this->tenantId($request), $beneficiary), (string) $request->validated('consent_scope'), Carbon::parse($request->validated('consent_date')), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Consent recorded.');
    }

    public function withdrawConsent(Request $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.manage');

        try {
            $service->withdrawConsent($this->find($tenantId, $beneficiary), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Consent withdrawn. No new services or case work can be recorded.');
    }

    public function exit(Request $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.manage');
        $reason = mb_trim((string) $request->input('reason', ''));

        try {
            abort_if($reason === '', 422, 'Say why the person is leaving the programme.');
            $service->exit($this->find($tenantId, $beneficiary), $reason, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Marked as exited and open cases closed.');
    }

    public function anonymise(Request $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.anonymise');
        abort_unless($request->input('confirm') === 'ANONYMISE', 422, 'Type ANONYMISE to confirm.');

        try {
            $service->anonymise($this->find($tenantId, $beneficiary), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Personal details erased. Only anonymous counts remain.');
    }

    public function openCase(OpenCaseRequest $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        try {
            $service->openCase($this->find($this->tenantId($request), $beneficiary), (string) $request->validated('title'), (string) $request->validated('category'), (string) $request->validated('priority'), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Case opened.');
    }

    public function closeCase(Request $request, BeneficiaryService $service, string $case): RedirectResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.cases.manage');
        $outcome = mb_trim((string) $request->input('outcome', ''));
        abort_if($outcome === '', 422, 'Say how the case ended.');

        try {
            $service->closeCase(BenCase::query()->where('tenant_id', $tenantId)->where('uuid', $case)->firstOrFail(), $outcome, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Case closed.');
    }

    public function addNote(AddNoteRequest $request, BeneficiaryService $service, string $case): RedirectResponse
    {
        $model = BenCase::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $case)->firstOrFail();

        try {
            $service->addNote($model, (string) $request->validated('note'), $request->boolean('is_confidential'), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Note added.');
    }

    public function addDocument(AddDocumentRequest $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        try {
            $service->attachDocument($this->find($this->tenantId($request), $beneficiary), (string) $request->validated('kind'), $request->file('file'), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Document attached.');
    }

    /** Only staff who may see personal details can open a document, and each opening is logged. */
    public function downloadDocument(Request $request, BeneficiaryService $service, string $document): StreamedResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.confidential.view');
        $model = BenDocument::query()->where('tenant_id', $tenantId)->where('uuid', $document)->with('beneficiary')->firstOrFail();
        abort_unless(Storage::disk('tenant')->exists($model->path), 404);
        $service->log($tenantId, $this->actorId($request), 'document_viewed', $model->beneficiary, $model->kind);

        return Storage::disk('tenant')->download($model->path, $model->name, ['Content-Type' => $model->mime]);
    }

    public function removeDocument(Request $request, BeneficiaryService $service, string $document): RedirectResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.manage');
        $service->removeDocument(BenDocument::query()->where('tenant_id', $tenantId)->where('uuid', $document)->firstOrFail(), $this->actorId($request));

        return back()->with('success', 'Document removed.');
    }

    public function refer(ReferRequest $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $case = $request->validated('case_id') ? BenCase::query()->where('tenant_id', $tenantId)->find((int) $request->validated('case_id')) : null;

        try {
            $service->refer(
                $this->find($tenantId, $beneficiary),
                (string) $request->validated('referred_to'),
                $request->validated('reason'),
                $case,
                $request->validated('follow_up_on') ? Carbon::parse($request->validated('follow_up_on')) : null,
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Referral recorded.');
    }

    public function updateReferral(UpdateReferralRequest $request, BeneficiaryService $service, string $referral): RedirectResponse
    {
        $model = BenReferral::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $referral)->firstOrFail();

        try {
            $service->updateReferral($model, (string) $request->validated('status'), $request->validated('outcome'), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Referral updated.');
    }

    public function recordService(RecordServiceRequest $request, BeneficiaryService $service, string $beneficiary): RedirectResponse
    {
        try {
            $service->recordService(
                $this->find($this->tenantId($request), $beneficiary),
                Carbon::parse($request->validated('delivered_on')),
                (string) $request->validated('type'),
                $request->validated('description'),
                $request->validated('value') !== null && $request->validated('value') !== '' ? Money::toMinor($request->validated('value')) : null,
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Service recorded.');
    }

    public function auditLog(Request $request): View
    {
        $tenantId = $this->allow($request, 'beneficiaries.audit.view');

        return view('beneficiaries::audit', [
            'entries' => BenAccessLog::query()->where('tenant_id', $tenantId)->latest('id')->paginate(50),
            'references' => BenBeneficiary::query()->where('tenant_id', $tenantId)->pluck('reference', 'id'),
            'users' => \App\Models\User::query()->whereIn('uuid', BenAccessLog::query()->where('tenant_id', $tenantId)->whereNotNull('user_uuid')->distinct()->pluck('user_uuid'))->pluck('email', 'uuid'),
        ]);
    }

    private function find(string $tenantId, string $uuid): BenBeneficiary
    {
        return BenBeneficiary::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }
}
