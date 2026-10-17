@extends('layouts.metronic.app')

@section('title', 'Store Products & Merchandise')

@section('content')
<div class="flex flex-col gap-6" x-data="{ showCreateModal: false }">
    <x-metronic.page-header
        title="Store Catalog & Inventory"
        description="Manage branded merchandise, employee uniforms, building supplies, and client equipment with real-time stock guard."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <button type="button" @click="showCreateModal = true" class="kt-btn kt-btn-primary">
                    + Add New Product
                </button>
                <a class="kt-btn kt-btn-outline" href="{{ route('store.index') }}">Back to Store</a>
            </div>
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('status') === 'success')
        <div class="p-4 rounded-xl bg-success/10 border border-success/20 text-success text-sm font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Create Product Modal / Form --}}
    <div x-show="showCreateModal" class="kt-card p-6 border-2 border-primary/30" x-cloak>
        <div class="flex items-center justify-between mb-4 border-b border-border pb-3">
            <h3 class="text-base font-bold text-foreground">Add New Catalog Product</h3>
            <button type="button" @click="showCreateModal = false" class="text-xs text-muted-foreground hover:text-foreground">✕ Close</button>
        </div>

        <form action="{{ route('store.products.store') }}" method="POST">
            @csrf
            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="kt-form-label">Product Name *</label>
                    <input class="kt-input" name="name" required type="text" placeholder="e.g. Branded Institutional High-Vis Field Vest">
                </div>
                <div>
                    <label class="kt-form-label">SKU / Item Code</label>
                    <input class="kt-input" name="sku" type="text" placeholder="VEST-HV-01">
                </div>
                <div>
                    <label class="kt-form-label">Unit Price (GHS) *</label>
                    <input class="kt-input" name="price" required type="number" step="0.01" min="0.01" placeholder="120.00">
                </div>
                <div>
                    <label class="kt-form-label">Initial Stock Quantity *</label>
                    <input class="kt-input" name="stock_quantity" required type="number" step="1" min="0" placeholder="50">
                </div>
                <div>
                    <label class="kt-form-label">Audience Scope *</label>
                    <select class="kt-select" name="audience" required>
                        <option value="staff_only">Staff Only (Internal Requisition / Purchase)</option>
                        <option value="public">Public (Available to estate occupants & clients)</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="kt-form-label">Description / Specifications</label>
                    <textarea class="kt-textarea" name="description" rows="2" placeholder="Material specifications, sizing, safety certifications..."></textarea>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="showCreateModal = false" class="kt-btn kt-btn-outline">Cancel</button>
                <button type="submit" class="kt-btn kt-btn-primary">Save Product</button>
            </div>
        </form>
    </div>

    {{-- Products Table --}}
    <div class="kt-card p-6">
        <div class="overflow-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-sm">
                <thead>
                    <tr class="border-b border-border/60 text-left text-xs font-semibold text-muted-foreground">
                        <th class="py-3 px-4">Product Name & SKU</th>
                        <th class="py-3 px-4">Audience</th>
                        <th class="py-3 px-4">Price</th>
                        <th class="py-3 px-4">Stock Level</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Quick Buy / Requisition</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    @forelse($products as $prod)
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-foreground">{{ $prod->name }}</div>
                                <div class="text-xs text-muted-foreground font-mono">{{ $prod->sku ?? 'NO-SKU' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm {{ $prod->audience === 'public' ? 'kt-badge-success' : 'kt-badge-primary' }}">
                                    {{ $prod->audience }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-foreground">
                                GHS {{ number_format($prod->price_cents / 100, 2) }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold {{ $prod->stock_quantity > 5 ? 'text-foreground' : 'text-destructive' }}">
                                    {{ $prod->stock_quantity }} in stock
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm {{ $prod->is_active ? 'kt-badge-success' : 'kt-badge-destructive' }}">
                                    {{ $prod->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($prod->stock_quantity > 0)
                                    <form action="{{ route('store.checkout') }}" method="POST" class="inline-flex items-center gap-2 justify-end">
                                        @csrf
                                        <input type="hidden" name="customer_name" value="{{ auth()->user()?->first_name }} {{ auth()->user()?->last_name }}">
                                        <input type="hidden" name="customer_email" value="{{ auth()->user()?->email }}">
                                        <input type="hidden" name="items[0][product_id]" value="{{ $prod->id }}">
                                        <input type="hidden" name="items[0][quantity]" value="1">
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">
                                            Buy 1 Unit
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-destructive font-semibold">Sold Out</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-muted-foreground">
                                No products created yet. Click "+ Add New Product" to populate your institutional catalog.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>
@endsection

