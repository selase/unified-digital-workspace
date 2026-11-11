<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\Beneficiaries\Models\BenService;
use App\Modules\Beneficiaries\Services\BeneficiaryService;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A phone-friendly page for recording services in the field. Entries are kept on the device while there is no
 * signal and sent when it returns. Only the beneficiary reference travels offline, never names or other details,
 * and every entry is checked again on the server (consent, active status, date) when it arrives.
 */
final class FieldEntryController extends Controller
{
    use ResolvesFinanceTenant;

    public const MAX_BATCH = 100;

    public function page(Request $request): View
    {
        $this->allow($request, 'beneficiaries.manage');

        return view('beneficiaries::field.page', ['types' => BenBeneficiary::SERVICE_TYPES]);
    }

    /** A fresh CSRF token, so entries queued long ago can still be sent once the person is back online and signed in. */
    public function token(Request $request): JsonResponse
    {
        $this->allow($request, 'beneficiaries.manage');

        return response()->json(['token' => csrf_token()]);
    }

    public function serviceWorker(): Response
    {
        return response(view('beneficiaries::field.sw')->render(), 200, ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/beneficiaries/', 'Cache-Control' => 'no-cache']);
    }

    public function sync(Request $request, BeneficiaryService $service): JsonResponse
    {
        $tenantId = $this->allow($request, 'beneficiaries.manage');
        $entries = $request->validate(['entries' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH], 'entries.*.id' => ['required', 'string', 'max:64'], 'entries.*.reference' => ['required', 'string', 'max:32'], 'entries.*.type' => ['required', 'string'], 'entries.*.delivered_on' => ['required', 'date'], 'entries.*.description' => ['nullable', 'string', 'max:255']])['entries'];

        $results = [];
        foreach ($entries as $entry) {
            $results[] = ['id' => $entry['id']] + $this->process($service, $tenantId, $entry, $this->actorId($request));
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @param  array{id: string, reference: string, type: string, delivered_on: string, description?: string|null}  $entry
     * @return array{ok: bool, message: string}
     */
    private function process(BeneficiaryService $service, string $tenantId, array $entry, ?string $userUuid): array
    {
        if (BenService::query()->where('tenant_id', $tenantId)->where('client_ref', $entry['id'])->exists()) {
            return ['ok' => true, 'message' => 'Already recorded.'];
        }

        $beneficiary = BenBeneficiary::query()->where('tenant_id', $tenantId)->where('reference', mb_strtoupper(mb_trim($entry['reference'])))->first();
        if (! $beneficiary instanceof BenBeneficiary) {
            return ['ok' => false, 'message' => 'No beneficiary has the reference '.$entry['reference'].'.'];
        }

        try {
            $record = $service->recordService($beneficiary, Carbon::parse($entry['delivered_on']), $entry['type'], $entry['description'] ?? null, null, $userUuid);
        } catch (DomainException $error) {
            return ['ok' => false, 'message' => $error->getMessage()];
        }
        $record->forceFill(['client_ref' => $entry['id']])->save();

        return ['ok' => true, 'message' => 'Recorded.'];
    }
}
