<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Jobs\SendBillingMessage;
use App\Mail\InvoiceBillingNotice;
use App\Models\BillingMessage;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Tenancy\BillingAgreementService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class BillingCommunicationService
{
    public function issue(Invoice $invoice): bool
    {
        return $invoice->getConnection()->transaction(function () use ($invoice): bool {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Invoice::STATUS_DRAFT) {
                return false;
            }
            $locked->update(['status' => Invoice::STATUS_ISSUED]);
            $this->issued($locked);
            $locked->getConnection()->afterCommit(fn () => \App\Events\InvoiceIssued::dispatch($locked));

            return true;
        });
    }

    public function contact(Tenant $tenant): ?string
    {
        $email = mb_strtolower(mb_trim((string) ($tenant->meta['billing_email'] ?? $tenant->email)));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public function issued(Invoice $invoice, bool $resend = false): void
    {
        if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE], true)) {
            return;
        }
        $this->queue($invoice->tenant, 'invoice_issued', 'issued:'.$invoice->id.($resend ? ':resend:'.Str::uuid() : ''), $this->invoicePayload($invoice), $invoice);
    }

    public function receipt(Invoice $invoice, bool $resend = false): void
    {
        if ($invoice->status !== Invoice::STATUS_PAID) {
            return;
        }
        $this->queue($invoice->tenant, 'payment_receipt', 'receipt:'.$invoice->id.($resend ? ':resend:'.Str::uuid() : ''), $this->invoicePayload($invoice), $invoice);
    }

    public function runDue(): int
    {
        $before = BillingMessage::query()->count();
        BillingMessage::query()->where('status', 'processing')->where('attempts', '>=', 3)
            ->where('lease_until', '<=', now())->update([
                'status' => 'failed', 'lease_until' => null,
                'last_error' => 'Delivery was interrupted after the final attempt. Review transport records before resending.',
            ]);
        $cycle = today()->addMonthNoOverflow()->startOfMonth();
        $days = (int) today()->diffInDays($cycle);
        if (in_array($days, config('billing.communications.cycle_days', [7, 1]), true)) {
            Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($cycle, $days): void {
                $agreement = app(BillingAgreementService::class)->activeFor($tenant, $cycle);
                if ($agreement === null) {
                    return;
                }
                $card = $agreement->snapshot['hybrid'];
                $plan = $card['website_plans'][$agreement->plan_slug];
                $fixed = (float) $plan[$agreement->currency]
                    + max(0, $agreement->editor_seats - $plan['editors']) * $card['extra_editor'][$agreement->currency];
                foreach ($agreement->snapshot['selected_packages'] as $slug) {
                    $fixed += $card['packages'][$slug][$agreement->currency];
                }
                $this->queue($tenant, 'cycle_upcoming', 'cycle:'.$agreement->id.':'.$cycle->toDateString().':'.$days, [
                    'subject' => 'Your next workspace billing month starts '.$cycle->format('j M'),
                    'tenant_name' => $tenant->name, 'currency' => $agreement->currency, 'fixed_amount' => round($fixed, 2),
                    'plan' => $plan['name'], 'cycle_start' => $cycle->toDateString(), 'agreement_id' => $agreement->id,
                    'setup_amount' => $agreement->setup_required && $agreement->effective_from->isSameMonth($cycle) ? $plan['setup'][$agreement->currency] : 0,
                ]);
            });
        }
        Invoice::query()->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE])
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->whereNotNull('due_at')->each(function (Invoice $invoice): void {
                $days = (int) today()->diffInDays($invoice->due_at->copy()->startOfDay(), false);
                if ($days < 0 && $invoice->status === Invoice::STATUS_ISSUED) {
                    Invoice::query()->whereKey($invoice->id)->where('status', Invoice::STATUS_ISSUED)
                        ->update(['status' => Invoice::STATUS_OVERDUE]);
                    $invoice->refresh();
                    if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE], true)) {
                        return;
                    }
                }
                if (in_array($days, config('billing.communications.due_days', [7, 3, 0]), true)
                    || in_array(-$days, config('billing.communications.overdue_days', [7, 14]), true) && $days < 0) {
                    $kind = $days < 0 ? 'invoice_overdue' : 'invoice_due';
                    $this->queue($invoice->tenant, $kind, $kind.':'.$invoice->id.':'.$invoice->due_at->toDateString().':'.$days,
                        $this->invoicePayload($invoice), $invoice);
                }
            });
        BillingMessage::query()->whereIn('status', ['pending', 'failed', 'processing', 'blocked'])->where('attempts', '<', 3)
            ->where(fn ($query) => $query->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
            ->each(fn (BillingMessage $message) => SendBillingMessage::dispatch($message->id)->afterCommit());

        return BillingMessage::query()->count() - $before;
    }

    public function deliver(string $id): void
    {
        $message = BillingMessage::query()->getConnection()->transaction(function () use ($id): ?BillingMessage {
            $message = BillingMessage::query()->lockForUpdate()->find($id);
            if ($message === null || in_array($message->status, ['sent', 'cancelled'], true)
                || $message->lease_until?->isFuture() || $message->attempts >= 3) {
                return null;
            }
            $invoice = $message->invoice;
            $invalid = $this->contact($message->tenant) !== $message->recipient || $message->tenant->status->value !== 'active';
            if ($message->kind === 'cycle_upcoming') {
                $agreement = app(BillingAgreementService::class)->activeFor($message->tenant, \Carbon\Carbon::parse($message->payload['cycle_start']));
                $invalid = $invalid || $agreement?->id !== $message->payload['agreement_id'] || today()->gte(\Carbon\Carbon::parse($message->payload['cycle_start']));
            } elseif ($message->kind === 'payment_receipt') {
                $invalid = $invalid || $invoice?->status !== Invoice::STATUS_PAID;
            } else {
                $invalid = $invalid || $invoice === null || ! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE], true)
                    || $invoice->due_at?->toDateString() !== $message->payload['due_date'];
                $latest = BillingMessage::query()->where('invoice_id', $message->invoice_id)
                    ->where('recipient', $message->recipient)->whereIn('kind', ['invoice_issued', 'invoice_due', 'invoice_overdue'])
                    ->where('status', '!=', 'cancelled')->latest()->orderByDesc('id')->value('id');
                $invalid = $invalid || $latest !== $message->id;
            }
            if ($invalid) {
                $message->update(['status' => 'cancelled', 'last_error' => 'Billing state or contact changed before delivery.']);

                return null;
            }
            if ($invoice !== null && $message->kind !== 'payment_receipt'
                && ! app(InvoiceCheckoutService::class)->availableFor($invoice)) {
                $message->update(['status' => 'blocked', 'lease_until' => null, 'last_error' => 'Payment provider configuration is missing. Billing mail resumes after setup.']);

                return null;
            }
            $message->update(['status' => 'processing', 'attempts' => $message->attempts + 1, 'lease_until' => now()->addMinutes(10)]);

            return $message;
        });
        if ($message === null) {
            return;
        }
        try {
            Mail::to($message->recipient)->send(new InvoiceBillingNotice($message));
            $message->update(['status' => 'sent', 'sent_at' => now(), 'lease_until' => null, 'last_error' => null]);
        } catch (Throwable $error) {
            $message->update(['status' => 'failed', 'lease_until' => null, 'last_error' => 'Mail transport did not confirm delivery; retry pending.']);
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'subject' => 'Workspace invoice '.$invoice->number, 'tenant_name' => $invoice->tenant->name,
            'currency' => $invoice->currency, 'amount' => (float) $invoice->total, 'number' => $invoice->number,
            'due_date' => $invoice->due_at?->toDateString(), 'period_start' => $invoice->period_start->toDateString(),
            'period_end' => $invoice->period_end->toDateString(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function queue(Tenant $tenant, string $kind, string $key, array $payload, ?Invoice $invoice = null): void
    {
        if (in_array($kind, ['invoice_due', 'invoice_overdue'], true)) {
            $payload['subject'] = ($kind === 'invoice_overdue' ? 'Overdue invoice — ' : 'Payment reminder — ').$payload['number'];
        }
        $recipient = $this->contact($tenant);
        if ($recipient === null) {
            return;
        }
        $message = BillingMessage::query()->firstOrCreate(['dedup_key' => hash('sha256', $key.':'.$recipient)], [
            'tenant_id' => $tenant->id, 'invoice_id' => $invoice?->id, 'kind' => $kind,
            'recipient' => $recipient, 'status' => 'pending', 'payload' => $payload,
        ]);
        if ($message->wasRecentlyCreated) {
            SendBillingMessage::dispatch($message->id)->afterCommit();
        }
    }
}
