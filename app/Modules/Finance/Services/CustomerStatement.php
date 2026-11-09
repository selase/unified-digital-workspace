<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinPayment;
use Carbon\CarbonInterface;

/** What one customer has been billed, has credited and has paid, with a running balance. */
final class CustomerStatement
{
    /**
     * @return array{contact: FinContact, from: CarbonInterface, to: CarbonInterface, opening: int, lines: list<array{date: CarbonInterface, type: string, number: string, debit: int, credit: int, balance: int}>, closing: int}
     */
    public function build(FinContact $contact, CarbonInterface $from, CarbonInterface $to): array
    {
        $documents = FinInvoice::query()->where('tenant_id', $contact->tenant_id)->where('contact_id', $contact->id)
            ->whereIn('type', [FinInvoice::TYPE_INVOICE, FinInvoice::TYPE_CREDIT_NOTE])
            ->whereNotIn('status', [FinInvoice::STATUS_DRAFT, FinInvoice::STATUS_VOID])
            ->whereDate('issue_date', '<=', $to->toDateString())->get();
        $payments = FinPayment::query()->where('tenant_id', $contact->tenant_id)->where('contact_id', $contact->id)
            ->whereDate('date', '<=', $to->toDateString())->get();

        $entries = collect();
        foreach ($documents as $document) {
            $isCredit = $document->type === FinInvoice::TYPE_CREDIT_NOTE;
            $entries->push(['date' => $document->issue_date, 'type' => $isCredit ? 'Credit note' : 'Invoice', 'number' => (string) $document->number, 'debit' => $isCredit ? 0 : $document->total, 'credit' => $isCredit ? $document->total : 0]);
        }
        foreach ($payments as $payment) {
            $entries->push(['date' => $payment->date, 'type' => 'Payment', 'number' => (string) $payment->number, 'debit' => 0, 'credit' => $payment->amount]);
        }

        $entries = $entries->sortBy(fn (array $entry): string => $entry['date']->toDateString().$entry['type'].$entry['number'])->values();

        $opening = (int) $entries->filter(fn (array $entry): bool => $entry['date']->lt($from))->sum(fn (array $entry): int => $entry['debit'] - $entry['credit']);
        $balance = $opening;
        $lines = [];
        foreach ($entries->filter(fn (array $entry): bool => $entry['date']->gte($from)) as $entry) {
            $balance += $entry['debit'] - $entry['credit'];
            $lines[] = $entry + ['balance' => $balance];
        }

        return ['contact' => $contact, 'from' => $from, 'to' => $to, 'opening' => $opening, 'lines' => $lines, 'closing' => $balance];
    }
}
