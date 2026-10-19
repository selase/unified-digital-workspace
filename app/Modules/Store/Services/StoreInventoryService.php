<?php

declare(strict_types=1);

namespace App\Modules\Store\Services;

use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Models\StoreOrderItem;
use App\Modules\Store\Models\StoreProduct;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stringable;

final class StoreInventoryService
{
    /**
     * Atomically locks stock and places an order.
     *
     * @param  array{customer_name: string, customer_email: string, payment_method?: string, payment_reference?: string|null}  $customerData
     * @param  array<int, array{product_id: int, quantity: int}>  $cartItems
     */
    public function placeOrder(string $tenantId, array $customerData, array $cartItems, string|Stringable|null $userUuid = null): StoreOrder
    {
        $userUuidString = $userUuid !== null ? (string) $userUuid : null;
        if (empty($cartItems)) {
            throw new DomainException('Cart cannot be empty.');
        }

        return DB::transaction(function () use ($tenantId, $customerData, $cartItems, $userUuidString): StoreOrder {
            $totalAmountCents = 0;
            $itemsToCreate = [];

            foreach ($cartItems as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                $product = StoreProduct::where('id', $productId)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new DomainException("Product #{$productId} is not available in this store.");
                }

                if ($product->stock_quantity < $qty) {
                    throw new DomainException("Insufficient stock for product [{$product->name}]. Requested {$qty}, but only {$product->stock_quantity} available.");
                }

                // Decrement stock atomically
                $affected = StoreProduct::where('id', $product->id)
                    ->where('tenant_id', $tenantId)
                    ->where('stock_quantity', '>=', $qty)
                    ->decrement('stock_quantity', $qty);

                if ($affected === 0) {
                    throw new DomainException("Stock race condition detected for product [{$product->name}]. Insufficient units remaining.");
                }

                $itemTotal = $product->price_cents * $qty;
                $totalAmountCents += $itemTotal;

                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price_cents' => $product->price_cents,
                    'total_price_cents' => $itemTotal,
                ];
            }

            $status = 'pending';

            $order = StoreOrder::create([
                'tenant_id' => $tenantId,
                'order_number' => 'ORD-'.mb_strtoupper(Str::random(8)),
                'user_id' => $userUuidString,
                'customer_name' => $customerData['customer_name'],
                'customer_email' => $customerData['customer_email'],
                'total_amount_cents' => $totalAmountCents,
                'currency' => 'GHS',
                'status' => $status,
                'payment_method' => $customerData['payment_method'] ?? 'portal_balance',
                'payment_reference' => null,
            ]);

            foreach ($itemsToCreate as $it) {
                StoreOrderItem::create([
                    'tenant_id' => $tenantId,
                    'store_order_id' => $order->id,
                    'store_product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'unit_price_cents' => $it['unit_price_cents'],
                    'total_price_cents' => $it['total_price_cents'],
                ]);
            }

            \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
                (string) $tenantId,
                'store.order.placed',
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'total_amount_cents' => $order->total_amount_cents,
                    'status' => $order->status,
                ]
            );

            return $order->load('items.product');
        });
    }

    /**
     * Atomically confirms payment for a pending order.
     */
    public function confirmPayment(StoreOrder $order, string $paymentReference): StoreOrder
    {
        return DB::transaction(function () use ($order, $paymentReference): StoreOrder {
            /** @var StoreOrder $locked */
            $locked = StoreOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                return $locked;
            }

            $locked->update([
                'status' => 'paid',
                'payment_reference' => $paymentReference,
            ]);

            \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::dispatch(
                (string) $locked->tenant_id,
                'store.order.paid',
                [
                    'order_id' => $locked->id,
                    'order_number' => $locked->order_number,
                    'total_amount_cents' => $locked->total_amount_cents,
                    'customer_name' => $locked->customer_name,
                    'customer_email' => $locked->customer_email,
                ]
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Atomically cancels an order and restores reserved stock.
     */
    public function cancelOrder(StoreOrder $order): StoreOrder
    {
        return DB::transaction(function () use ($order): StoreOrder {
            /** @var StoreOrder $locked */
            $locked = StoreOrder::where('id', $order->id)->with('items')->lockForUpdate()->firstOrFail();

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            // Restore stock for all order items
            foreach ($locked->items as $item) {
                StoreProduct::where('id', $item->store_product_id)
                    ->where('tenant_id', $locked->tenant_id)
                    ->increment('stock_quantity', (int) $item->quantity);
            }

            $locked->update(['status' => 'cancelled']);

            return $locked->fresh() ?? $locked;
        });
    }
}
