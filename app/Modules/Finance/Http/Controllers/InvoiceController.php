<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveInvoiceRequest;
use App\Modules\Finance\Mail\FinanceInvoiceMail;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Models\FinTax;
use App\Modules\Finance\Services\FinancePdf;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

final class InvoiceController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $type = in_array($request->query('type'), [FinInvoice::TYPE_QUOTE, FinInvoice::TYPE_CREDIT_NOTE], true) ? (string) $request->query('type') : FinInvoice::TYPE_INVOICE;
        $status = $request->string('status')->trim()->toString();

        return view('finance::invoices.index', [
            'type' => $type,
            'status' => $status,
            'documents' => FinInvoice::query()->where('tenant_id', $tenantId)->where('type', $type)
                ->when($status === 'overdue', fn ($query) => $query->whereIn('status', [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID])->whereDate('due_date', '<', today()))
                ->when($status !== '' && $status !== 'overdue', fn ($query) => $query->where('status', $status))
                ->with('contact')->latest('issue_date')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');

        return view('finance::invoices.form', $this->formData($tenantId, new FinInvoice([
            'type' => $request->query('type') === FinInvoice::TYPE_QUOTE ? FinInvoice::TYPE_QUOTE : FinInvoice::TYPE_INVOICE,
            'issue_date' => today(),
            'terms' => $settings->for($tenantId)->invoice_footer,
        ])));
    }

    public function store(SaveInvoiceRequest $request, InvoiceService $invoices): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        try {
            $invoice = $invoices->saveDraft(
                $tenantId,
                $this->contact($tenantId, (int) $request->validated('contact_id')),
                $request->safe()->only(['type', 'issue_date', 'due_date', 'notes', 'terms', 'currency', 'exchange_rate']),
                $request->lineItems(),
                null,
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.invoices.show', $invoice->uuid)->with('success', 'Draft saved.');
    }

    public function show(Request $request, OnlinePaymentService $online, string $invoice): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = $this->find($tenantId, $invoice)->load(['contact', 'lines.tax', 'allocations']);

        return view('finance::invoices.show', [
            'document' => $model,
            'payLink' => $model->type === FinInvoice::TYPE_INVOICE && $online->canCollect($model) ? $online->paymentUrl($model) : null,
            'settings' => app(FinanceSettings::class)->for($tenantId),
        ]);
    }

    public function edit(Request $request, string $invoice): View|RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        $model = $this->find($tenantId, $invoice)->load('lines');

        if ($model->status !== FinInvoice::STATUS_DRAFT) {
            return redirect()->route('finance.invoices.show', $model->uuid)->with('error', 'Only drafts can be edited.');
        }

        return view('finance::invoices.form', $this->formData($tenantId, $model));
    }

    public function update(SaveInvoiceRequest $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $model = $this->find($tenantId, $invoice);

        try {
            $invoices->saveDraft(
                $tenantId,
                $this->contact($tenantId, (int) $request->validated('contact_id')),
                $request->safe()->only(['type', 'issue_date', 'due_date', 'notes', 'terms', 'currency', 'exchange_rate']),
                $request->lineItems(),
                $model,
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.invoices.show', $model->uuid)->with('success', 'Draft updated.');
    }

    public function send(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');

        try {
            $sent = $invoices->send($this->find($tenantId, $invoice), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.invoices.show', $sent->uuid)->with('success', $sent->number.' is now issued.');
    }

    public function email(Request $request, OnlinePaymentService $online, string $invoice): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        $model = $this->find($tenantId, $invoice)->load('contact');
        $settings = app(FinanceSettings::class)->for($tenantId);

        if ($model->status === FinInvoice::STATUS_DRAFT || $model->status === FinInvoice::STATUS_VOID) {
            return back()->with('error', 'Issue the document before emailing it.');
        }

        $recipient = $model->contact->email;
        if (! is_string($recipient) || $recipient === '') {
            return back()->with('error', 'This customer has no email address.');
        }

        Mail::to($recipient)->send(new FinanceInvoiceMail($model, $settings->legal_name ?: (string) config('app.name'), $online->paymentUrl($model), $settings->email));

        return back()->with('success', 'Emailed to '.$recipient.'.');
    }

    public function pdf(Request $request, FinancePdf $pdf, string $invoice): \Illuminate\Http\Response
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = $this->find($tenantId, $invoice);

        return response($pdf->output($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($model).'"',
        ]);
    }

    public function creditNote(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');

        try {
            $note = $invoices->createCreditNote($this->find($tenantId, $invoice), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.invoices.edit', $note->uuid)->with('success', 'Credit note draft created. Reduce the lines to credit only part of the invoice, then issue it.');
    }

    public function convert(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');

        try {
            $draft = $invoices->convertQuote($this->find($tenantId, $invoice), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.invoices.edit', $draft->uuid)->with('success', 'Invoice draft created from the quote.');
    }

    public function void(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.invoices.manage');
        $model = $this->find($tenantId, $invoice);

        try {
            if ($model->status === FinInvoice::STATUS_DRAFT) {
                $model->lines()->delete();
                $model->delete();

                return redirect()->route('finance.invoices.index')->with('success', 'Draft deleted.');
            }

            $invoices->void($model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Document voided.');
    }

    private function find(string $tenantId, string $uuid): FinInvoice
    {
        return FinInvoice::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }

    private function contact(string $tenantId, int $id): FinContact
    {
        return FinContact::query()->where('tenant_id', $tenantId)->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $tenantId, FinInvoice $document): array
    {
        $settings = app(FinanceSettings::class)->for($tenantId);

        return [
            'document' => $document,
            'contacts' => FinContact::query()->where('tenant_id', $tenantId)->where('is_active', true)->where('is_customer', true)->orderBy('name')->get(),
            'items' => FinItem::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'incomeAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'income')->where('is_active', true)->orderBy('code')->get(),
            'taxes' => $settings->tax_enabled ? FinTax::query()->where('tenant_id', $tenantId)->where('is_active', true)->get() : collect(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'settings' => $settings,
        ];
    }
}
