<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinItem;
use App\Modules\Finance\Models\FinStockMovement;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quantities of the items you sell. Selling an item on an invoice takes it out of stock, a credit note puts it back,
 * and voiding either undoes the movement. Stock is counted in units only; it does not post to the ledger, so what
 * you paid a supplier is still recorded on the bill.
 */
final class StockService
{
    public function onHand(FinItem $item): int
    {
        return (int) FinStockMovement::query()->where('item_id', $item->id)->sum('quantity_thousandths');
    }

    /**
     * Brings stock in: an opening count, goods received, or a correction (positive or negative).
     */
    public function record(FinItem $item, string $kind, float $quantity, CarbonInterface $date, ?int $unitCost = null, ?string $note = null, ?string $userId = null): FinStockMovement
    {
        if (! $item->track_stock) {
            throw new DomainException('Switch on stock tracking for this item first.');
        }
        if (! in_array($kind, ['opening', 'purchase', 'adjustment'], true)) {
            throw new DomainException('Choose opening stock, goods received, or an adjustment.');
        }
        $thousandths = (int) round($quantity * 1000);
        if ($thousandths === 0 || ($kind !== 'adjustment' && $thousandths < 0)) {
            throw new DomainException($kind === 'adjustment' ? 'Enter how many to add or remove.' : 'Enter a quantity greater than zero.');
        }
        if ($date->gt(today())) {
            throw new DomainException('Stock cannot be dated in the future.');
        }

        return DB::connection('tenant')->transaction(function () use ($item, $kind, $thousandths, $date, $unitCost, $note, $userId): FinStockMovement {
            if ($thousandths < 0 && $this->onHand(FinItem::query()->lockForUpdate()->findOrFail($item->id)) + $thousandths < 0) {
                throw new DomainException('That would take stock below zero.');
            }

            return FinStockMovement::query()->create([
                'tenant_id' => $item->tenant_id, 'item_id' => $item->id, 'moved_on' => $date->toDateString(), 'quantity_thousandths' => $thousandths,
                'kind' => $kind, 'unit_cost' => $unitCost, 'note' => $note, 'created_by' => $userId,
            ]);
        });
    }

    /** Takes sold items out of stock when an invoice is issued, or puts them back for a credit note. Refuses to oversell. */
    public function applyDocument(FinInvoice $document, ?string $userId = null): void
    {
        $isCredit = $document->type === FinInvoice::TYPE_CREDIT_NOTE;
        $totals = $this->trackedTotals($document);

        foreach ($totals as $itemId => $thousandths) {
            $item = FinItem::query()->lockForUpdate()->findOrFail($itemId);
            if (! $isCredit && $this->onHand($item) < $thousandths) {
                throw new DomainException('Not enough stock of '.$item->name.': '.number_format($this->onHand($item) / 1000, 3).' on hand, '.number_format($thousandths / 1000, 3).' needed.');
            }
            FinStockMovement::query()->create([
                'tenant_id' => $document->tenant_id, 'item_id' => $itemId, 'moved_on' => $document->issue_date->toDateString(),
                'quantity_thousandths' => $isCredit ? $thousandths : -$thousandths, 'kind' => $isCredit ? 'return' : 'sale',
                'source_type' => $document->type, 'source_id' => $document->id, 'note' => ($isCredit ? 'Credit note ' : 'Invoice ').$document->number, 'created_by' => $userId,
            ]);
        }
    }

    /** Undoes the movements a voided document made. Safe to call twice. */
    public function reverseDocument(FinInvoice $document, ?string $userId = null): void
    {
        $original = FinStockMovement::query()->where('source_type', $document->type)->where('source_id', $document->id)->whereIn('kind', ['sale', 'return'])->get();
        if ($original->isEmpty() || FinStockMovement::query()->where('source_type', $document->type)->where('source_id', $document->id)->where('kind', 'void')->exists()) {
            return;
        }

        foreach ($original as $movement) {
            FinStockMovement::query()->create([
                'tenant_id' => $movement->tenant_id, 'item_id' => $movement->item_id, 'moved_on' => today()->toDateString(), 'quantity_thousandths' => -$movement->quantity_thousandths,
                'kind' => 'void', 'source_type' => $document->type, 'source_id' => $document->id, 'note' => 'Voided '.$document->number, 'created_by' => $userId,
            ]);
        }
    }

    /**
     * Tracked items with what is on hand and whether they need reordering.
     *
     * @return Collection<int, array{item: FinItem, on_hand: int, low: bool}>
     */
    public function levels(string $tenantId): Collection
    {
        $items = FinItem::query()->where('tenant_id', $tenantId)->where('track_stock', true)->orderBy('name')->get();
        $totals = FinStockMovement::query()->where('tenant_id', $tenantId)->whereIn('item_id', $items->pluck('id'))->groupBy('item_id')->selectRaw('item_id, SUM(quantity_thousandths) as total')->pluck('total', 'item_id');

        return $items->map(fn (FinItem $item): array => [
            'item' => $item,
            'on_hand' => (int) ($totals[$item->id] ?? 0),
            'low' => $item->is_active && (int) ($totals[$item->id] ?? 0) <= $item->reorder_level_thousandths,
        ]);
    }

    /**
     * Quantity per tracked item on a document's lines.
     *
     * @return array<int, int>
     */
    private function trackedTotals(FinInvoice $document): array
    {
        $document->loadMissing('lines');
        $trackedIds = FinItem::query()->where('tenant_id', $document->tenant_id)->where('track_stock', true)->whereIn('id', $document->lines->pluck('item_id')->filter())->pluck('id')->all();

        $totals = [];
        foreach ($document->lines as $line) {
            if ($line->item_id !== null && in_array($line->item_id, $trackedIds, true)) {
                $totals[$line->item_id] = ($totals[$line->item_id] ?? 0) + $line->quantity_thousandths;
            }
        }
        ksort($totals);

        return $totals;
    }
}
