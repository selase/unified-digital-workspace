<?php

declare(strict_types=1);

use App\Mail\Billing\MonthlyInvoiceRunFailed;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\Tenancy\PdfInvoiceService;
use Illuminate\Support\Facades\Mail;

it('emails the operators when an invoice cannot be generated', function (): void {
    Mail::fake();
    $package = Package::factory()->create(['price_ghs' => null]);
    $tenant = Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id]);

    $this->artisan('billing:generate-invoices', ['--month' => '2026-09'])->assertFailed();

    Mail::assertSent(MonthlyInvoiceRunFailed::class, function (MonthlyInvoiceRunFailed $mail) use ($tenant): bool {
        return $mail->hasTo('hiselase@gmail.com')
            && $mail->hasTo('hello@wearepurpledot.com')
            && $mail->hasTo('support@wearepurpledot.com')
            && $mail->period === '2026-09'
            && array_key_exists($tenant->name, $mail->failures);
    });
});

it('does not email anyone when every invoice is generated', function (): void {
    Mail::fake();

    $this->artisan('billing:generate-invoices', ['--month' => '2026-09'])->assertSuccessful();

    Mail::assertNothingSent();
});

it('prints supplier details and the billing contact on the invoice pdf', function (): void {
    config(['billing.supplier.address' => '1 Test Street, Accra', 'billing.supplier.tin' => 'C0001234567']);
    $tenant = Tenant::factory()->create(['email' => 'info@old.example', 'meta' => ['billing_email' => 'finance@client.example']]);
    $invoice = Invoice::query()->create([
        'tenant_id' => $tenant->id, 'number' => 'INV-TEST-0001', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
        'due_at' => now()->addDays(14), 'status' => 'draft', 'currency' => 'GHS', 'subtotal' => 1500, 'tax_total' => 0, 'total' => 1500,
    ]);
    InvoiceItem::query()->create(['invoice_id' => $invoice->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 1500, 'subtotal' => 1500]);

    $html = view('pdf.invoice', ['invoice' => $invoice->load(['tenant', 'items'])])->render();

    expect($html)->toContain('Purpledot Limited')
        ->toContain('1 Test Street, Accra')
        ->toContain('C0001234567')
        ->toContain('finance@client.example')
        ->not->toContain('info@old.example');
    expect(app(PdfInvoiceService::class)->output($invoice))->toStartWith('%PDF');
});
