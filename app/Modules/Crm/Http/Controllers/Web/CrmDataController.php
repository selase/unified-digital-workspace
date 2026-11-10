<?php

declare(strict_types=1);

namespace App\Modules\Crm\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Crm\Models\CrmAccount;
use App\Modules\Crm\Models\CrmDeal;
use App\Modules\Crm\Models\CrmLead;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Moves CRM records in and out as CSV, so a client list can be loaded from a spreadsheet and reported on elsewhere. */
final class CrmDataController extends Controller
{
    public const IMPORT_LIMIT = 1000;

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_if(! $request->user()?->can('crm.view'), 403);
        $tenantId = $this->tenantId($request);

        [$header, $rows] = match ($type) {
            'leads' => [
                ['Reference', 'Name', 'Email', 'Phone', 'Organisation', 'Source', 'Status', 'Created'],
                CrmLead::query()->where('tenant_id', $tenantId)->orderBy('id')->cursor()->map(fn (CrmLead $lead): array => [$lead->reference_code, $lead->full_name, $lead->email, $lead->phone, $lead->organisation, $lead->source, $lead->status, $lead->created_at?->toDateString()]),
            ],
            'accounts' => [
                ['Code', 'Name', 'Type', 'Industry', 'Email', 'Phone', 'Status'],
                CrmAccount::query()->where('tenant_id', $tenantId)->orderBy('id')->cursor()->map(fn (CrmAccount $account): array => [$account->code, $account->name, $account->account_type, $account->industry, $account->email, $account->phone, $account->status]),
            ],
            'deals' => [
                ['Title', 'Account', 'Stage', 'Currency', 'Value', 'Probability %', 'Expected close'],
                CrmDeal::query()->where('tenant_id', $tenantId)->with('account:id,name')->orderBy('id')->cursor()->map(fn (CrmDeal $deal): array => [$deal->title, $deal->account?->name, $deal->stage, $deal->currency, number_format($deal->value_pesewas / 100, 2, '.', ''), $deal->probability, $deal->expected_close_date?->toDateString()]),
            ],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($cell): string => $this->safe($cell), $row), ',', '"', '');
            }
            fclose($out);
        }, 'crm-'.$type.'-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Loads leads from a CSV with a header row: name (required), email, phone, organisation. Existing emails are skipped. */
    public function importLeads(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->can('crm.leads.manage'), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], ['file.required' => 'Choose a CSV file.', 'file.mimes' => 'Upload a CSV file.', 'file.max' => 'The file must be 2 MB or less.']);
        $tenantId = $this->tenantId($request);

        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        $header = array_map(fn ($cell): string => Str::of((string) $cell)->replaceMatches('/^\xEF\xBB\xBF/', '')->lower()->trim()->toString(), (array) fgetcsv($handle, 0, ',', '"', ''));
        $nameColumn = array_search('name', $header, true) !== false ? array_search('name', $header, true) : array_search('full_name', $header, true);

        if ($nameColumn === false) {
            fclose($handle);

            return back()->with('status', 'error')->with('message', 'The first row must be a header with a "name" column (and optionally email, phone, organisation).');
        }

        $column = fn (string $key): int|false => array_search($key, $header, true);
        $imported = 0;
        $skipped = 0;
        $seen = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($seen >= self::IMPORT_LIMIT) {
                break;
            }
            $seen++;
            $name = mb_trim((string) ($row[$nameColumn] ?? ''));
            $email = ($i = $column('email')) !== false ? mb_strtolower(mb_trim((string) ($row[$i] ?? ''))) : '';

            if ($name === '' || ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL))
                || ($email !== '' && CrmLead::query()->where('tenant_id', $tenantId)->where('email', $email)->exists())) {
                $skipped++;

                continue;
            }

            CrmLead::query()->create([
                'tenant_id' => $tenantId,
                'reference_code' => 'LEAD-'.mb_strtoupper(Str::random(6)),
                'source' => CrmLead::SOURCE_DIRECT,
                'enquiry_type' => 'general',
                'full_name' => mb_substr($name, 0, 255),
                'email' => $email !== '' ? $email : null,
                'phone' => ($i = $column('phone')) !== false ? mb_substr(mb_trim((string) ($row[$i] ?? '')), 0, 64) ?: null : null,
                'organisation' => ($i = $column('organisation')) !== false ? mb_substr(mb_trim((string) ($row[$i] ?? '')), 0, 255) ?: null : null,
                'status' => 'new',
            ]);
            $imported++;
        }
        fclose($handle);

        return redirect()->route('crm.leads.index')->with('status', 'success')->with('message', "Imported {$imported} lead(s); skipped {$skipped} (blank name, bad or duplicate email)".($seen >= self::IMPORT_LIMIT ? '. Only the first '.self::IMPORT_LIMIT.' rows are read per file' : '').'.');
    }

    /** Stops spreadsheet programs running a cell as a formula. */
    private function safe(mixed $cell): string
    {
        $value = (string) ($cell ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }

    private function tenantId(Request $request): string
    {
        $tenant = $request->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return (string) $tenant->id;
        }
        $active = app(TenantContext::class)->getTenant();
        if ($active !== null) {
            return (string) $active->id;
        }
        $session = $request->session()->get('active_tenant_id');
        abort_if(! is_string($session) || $session === '', 403);

        return $session;
    }
}
